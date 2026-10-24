const { createApp, ref, computed, watch, onMounted, onBeforeUnmount, nextTick } = Vue;

const SOCKET_URL = "https://api.infini9.net:3001";
const USERNAME_STORAGE_KEY = "ixeriox_username";
const SOUND_STORAGE_KEY = "ixeriox_chat_sound";
const CHAT_USER_COOKIE = "ixeriox_chat_user";
const PUBLIC_ROOM_ID = "iXPublic";
const DISCORD_AUTH_URL = "/auth/discord";
const DISCORD_AUTH_MESSAGE = "ixeriox:discord-auth";

/*
|--------------------------------------------------------------------------
| Privilege Class Helpers
|--------------------------------------------------------------------------
| -1        Banned    (lowest, always banned regardless of any other value)
|  0        Guest     (default class for anyone not otherwise classified)
|  1 - 19   Member
| 20 - 49   Trusted
| 50 - 79   Moderator   <- minimum class that can manage other users' classes
| 80 - 98   Admin
| 99        Owner     (highest possible class)
*/

const SITE_STAFF_LEVEL = 50;

const CHAT_PERMISSIONS = Object.freeze({
    VIEW: "chat.view",
    JOIN: "chat.join",
    SEND: "chat.send",
    INVITE: "chat.invite",
    KICK: "chat.kick",
    BAN: "chat.ban",
    UNBAN: "chat.unban",
    CHANGE_TITLE: "chat.title",
    CHANGE_TOPIC: "chat.topic",
    CHANGE_SETTINGS: "chat.settings",
    MANAGE_PRIVILEGE_CLASSES: "chat.privilegeClasses.manage",
    ASSIGN_PRIVILEGE_CLASSES: "chat.privilegeClasses.assign",
    TRANSFER_OWNERSHIP: "chat.owner.transfer",
    DELETE_ROOM: "chat.delete"
});

const CHAT_PERMISSION_LIST = Object.freeze(
    Object.values(CHAT_PERMISSIONS)
);

// Display order for the grouped user list — highest privilege first,
// banned members always last regardless of numeric value.
const TIER_ORDER = [
    { tier: "owner", label: "Owners" },
    { tier: "admin", label: "Admins" },
    { tier: "mod", label: "Moderators" },
    { tier: "trusted", label: "Trusted" },
    { tier: "member", label: "Members" },
    { tier: "guest", label: "Guests" },
    { tier: "banned", label: "Banned" }
];

// Quick presets used by the class dropdown in the permissions panel —
// representative values for each tier, still a plain number under the hood.
const CLASS_PRESETS = [
    { label: "Admin", value: 99 },
    { label: "Member", value: 1 },
    { label: "Guest", value: 0 },
    { label: "Banned", value: -1 }
];

function normalizeClass(value) {
    const n = Number(value);
    if (Number.isNaN(n)) return 0;
    return Math.max(-1, Math.min(99, Math.trunc(n)));
}

function classMeta(value) {
    const c = normalizeClass(value);

    if (c === -1) return { class: c, tier: "banned", label: "Banned" };
    if (c === 0) return { class: c, tier: "guest", label: "Guest" };
    if (c <= 19) return { class: c, tier: "member", label: "Member" };
    if (c <= 49) return { class: c, tier: "trusted", label: "Trusted" };
    if (c <= 79) return { class: c, tier: "mod", label: "Moderator" };
    if (c <= 98) return { class: c, tier: "admin", label: "Admin" };
    return { class: c, tier: "owner", label: "Owner" };
}

// Buckets a flat member list into ordered { tier, label, users } groups,
// omitting any tier that has no members in this room.
function groupUsersByTier(members) {
    const groups = TIER_ORDER.map(g => ({ ...g, users: [] }));

    members.forEach(member => {
        const tier = classMeta(member.class).tier;
        const group = groups.find(g => g.tier === tier);
        if (group) group.users.push(member);
    });

    groups.forEach(group => {
        group.users.sort((a, b) => normalizeClass(b.class) - normalizeClass(a.class));
    });

    return groups.filter(group => group.users.length > 0);
}

function roomPrivilegeClasses(room) {
    return (
        room?.raw?.privilegeClasses ??
        room?.privilegeClasses ??
        room?.raw?.rawData?.privilegeClasses ??
        {}
    );
}

function isRequiredPrivilegeClass(definition) {
    return definition?.order != null && definition.order !== "" && [-1, 0].includes(Number(definition.order));
}

function roomClassEntries(room) {
    return Object.entries(roomPrivilegeClasses(room))
        .map(([name, definition]) => ({
            name,
            order: Number(definition?.order || 0),
            permissions: Array.isArray(definition?.permissions)
                ? [...definition.permissions]
                : []
        }))
        .sort((a, b) =>
            b.order - a.order ||
            a.name.localeCompare(b.name)
        );
}

function isRoomOwner(room, member) {
    const ownerId = room?.owner ?? room?.raw?.owner ?? room?.raw?.rawData?.owner ?? room?.rawData?.owner;
    return ownerId != null && member?.id != null && String(ownerId) === String(member.id);
}

function memberRoomClass(room, member) {
    const memberId = String(member?.id ?? "");
    const classes = roomPrivilegeClasses(room);
    // Some servers send a synthetic "Room Owner" label rather than a class.
    // Ownership is displayed separately; real classes (including one with that
    // name) still resolve normally.
    const reportedClass = member?.privilegeClass ?? member?.className;
    return (
        room?.raw?.userPrivilegeClasses?.[memberId] ??
        room?.userPrivilegeClasses?.[memberId] ??
        room?.raw?.rawData?.userPrivilegeClasses?.[memberId] ??
        room?.rawData?.userPrivilegeClasses?.[memberId] ??
        (reportedClass === "Room Owner" && !Object.hasOwn(classes, reportedClass) ? undefined : reportedClass) ??
        room?.raw?.defaultPrivilegeClass ??
        room?.defaultPrivilegeClass ??
        room?.raw?.rawData?.defaultPrivilegeClass ??
        room?.rawData?.defaultPrivilegeClass ??
        "Unassigned"
    );
}

function roomClassTier(room, className) {
    const classes = roomClassEntries(room);
    const index = classes.findIndex(
        item => item.name === className
    );

    if (index < 0) return "guest";
    if (classes.length === 1 || index === 0) return "admin";

    const ratio = index / Math.max(1, classes.length - 1);
    if (ratio <= .25) return "mod";
    if (ratio <= .55) return "trusted";
    if (ratio <= .8) return "member";
    return "guest";
}

function groupUsersByPrivilegeClass(room, members) {
    const groups = new Map();

    for (const member of members || []) {
        const className = memberRoomClass(room, member);
        const definition =
            roomPrivilegeClasses(room)[className];
        const order = Number(definition?.order || 0);

        if (!groups.has(className)) {
            groups.set(className, {
                name: className,
                label: className,
                order,
                tier: roomClassTier(room, className),
                users: []
            });
        }

        groups.get(className).users.push({
            ...member,
            privilegeClass: className,
            privilegeOrder: order
        });
    }

    return [...groups.values()]
        .sort((a, b) =>
            b.order - a.order ||
            a.name.localeCompare(b.name)
        )
        .map(group => ({
            ...group,
            users: group.users.sort((a, b) =>
                memberLabel(a).localeCompare(memberLabel(b))
            )
        }));
}

// Prefer a real display name; fall back to the raw id rather than showing
// a literal "Unknown User" placeholder that some payloads send through.
function memberLabel(user) {
    const name = user?.displayName;
    if (name && name !== "Unknown User") return name;
    return user?.id || "Unknown";
}

/*
|--------------------------------------------------------------------------
| Session Helpers
|--------------------------------------------------------------------------
*/

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);

    if (parts.length === 2) {
        return parts.pop().split(";").shift();
    }

    return null;
}

function setChatUserCookie(value) {
    const encoded = encodeURIComponent(JSON.stringify(value));
    document.cookie = `${CHAT_USER_COOKIE}=${encoded}; path=/; max-age=2592000; SameSite=Lax; Secure`;
}

function getRememberedChatUser() {
    const value = getCookie(CHAT_USER_COOKIE);
    if (!value) return null;

    try {
        return JSON.parse(decodeURIComponent(value));
    } catch {
        return null;
    }
}

function clearChatUserCookie() {
    document.cookie = `${CHAT_USER_COOKIE}=; path=/; max-age=0; SameSite=Lax; Secure`;
}

function createSession() {
    let session = getCookie("ixeriox_session");

    if (!session) {
        session = crypto.randomUUID();
        document.cookie = `ixeriox_session=${session}; path=/; max-age=31536000; SameSite=Lax`;
    }

    return session;
}

function createMockUserId(session) {
    let hash = 0;

    for (let i = 0; i < session.length; i++) {
        hash = ((hash << 5) - hash) + session.charCodeAt(i);
        hash |= 0;
    }

    return "guest_" + Math.abs(hash);
}

function formatTime(time) {
    if (!time) return "";

    return new Date(time).toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit"
    });
}

function formatWhoisDate(value) {
    if (!value) return "Unknown";
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? String(value)
        : date.toLocaleString([], { dateStyle: "medium", timeStyle: "short" });
}

function decodeProfileText(value) {
    const element = document.createElement("textarea");
    element.innerHTML = String(value || "");
    return element.value;
}

// Keep routing metadata byte-for-byte equivalent to the value supplied by the
// server. Normalisation is only for comparisons and must never replace it.
function serverMessageType(message) {
    return Object.prototype.hasOwnProperty.call(message || {}, "type")
        ? message.type
        : undefined;
}

// Formatting stays as text on the wire. Render only these known styles;
// never interpret message content as HTML.
function styledMessageParts(value, styles = {}, depth = 0) {
    const text = String(value ?? "");
    if (depth >= 8) return [{ text, ...styles }];
    const pattern = /\*\*([\s\S]+?)\*\*|__([\s\S]+?)__|\*([^*]+?)\*/g;
    const parts = [];
    let offset = 0;
    for (const match of text.matchAll(pattern)) {
        if (match.index > offset) parts.push({ text: text.slice(offset, match.index), ...styles });
        const style = match[1] !== undefined ? "bold" : match[2] !== undefined ? "underline" : "italic";
        parts.push(...styledMessageParts(match[1] ?? match[2] ?? match[3], { ...styles, [style]: true }, depth + 1));
        offset = match.index + match[0].length;
    }
    if (offset < text.length) parts.push({ text: text.slice(offset), ...styles });
    return parts;
}

const CHAT_EMOJI = Object.freeze([
    ["smile", "😄"], ["grinning", "😀"], ["laughing", "😆"], ["joy", "😂"],
    ["wink", "😉"], ["heart_eyes", "😍"], ["sunglasses", "😎"], ["thinking", "🤔"],
    ["cry", "😢"], ["sob", "😭"], ["angry", "😠"], ["surprised", "😮"],
    ["thumbsup", "👍"], ["thumbsdown", "👎"], ["wave", "👋"], ["clap", "👏"],
    ["pray", "🙏"], ["muscle", "💪"], ["heart", "❤️"], ["broken_heart", "💔"],
    ["fire", "🔥"], ["tada", "🎉"], ["sparkles", "✨"], ["100", "💯"],
    ["eyes", "👀"], ["rocket", "🚀"], ["check", "✅"], ["coffee", "☕"]
]);
const EMOJI_SHORTCODES = Object.fromEntries(CHAT_EMOJI);

function expandEmojiShortcodes(text) {
    return text.replace(/:([a-z0-9_+-]+):/gi, (match, name) => Object.hasOwn(EMOJI_SHORTCODES, name.toLowerCase()) ? EMOJI_SHORTCODES[name.toLowerCase()] : match);
}

function parsedMessageLink(raw, label, context = "") {
    let candidate = raw.replace(/\\([()])/g, "$1");
    let suffix = "";
    const punctuation = candidate.match(/[.,!?;:]+$/)?.[0] || "";
    if (punctuation) { suffix = punctuation; candidate = candidate.slice(0, -punctuation.length); }
    for (const marker of ["**", "__", "*"]) {
        if (context.includes(marker) && candidate.endsWith(marker)) {
            candidate = candidate.slice(0, -marker.length);
            suffix = marker + suffix;
        }
    }
    const labelled = candidate.match(/^(https?:\/\/.+)\(([^()\n]+)\)$/i);
    if (labelled) {
        try {
            const base = new URL(labelled[1]);
            // URL(label) is unambiguous for the root-domain form. Preserve
            // parentheses in ordinary paths such as Wikipedia article URLs.
            if (base.pathname === "/" && !base.search && !base.hash) {
                candidate = labelled[1];
                if (!label || /^https?:\/\//i.test(label)) label = labelled[2];
            }
        } catch { /* Keep invalid destinations as text. */ }
    }
    for (const [open, close] of [["(", ")"], ["[", "]"], ["{", "}"]]) {
        while (candidate.endsWith(close) && candidate.split(close).length > candidate.split(open).length) {
            candidate = candidate.slice(0, -1);
            suffix = close + suffix;
        }
    }
    try {
        const url = new URL(candidate);
        if (!["http:", "https:"].includes(url.protocol) || url.username || url.password) return null;
        return { text: label || candidate, href: url.href, image: /\.(?:png|jpe?g|gif|webp|avif|bmp)$/i.test(url.pathname), suffix };
    } catch { return null; }
}

function messageContentParts(value) {
    const text = String(value ?? "");
    let marker = "\uE000link";
    while (text.includes(marker)) marker += "x";
    const links = [];
    let previewCount = 0;
    // Protect URLs before formatting; message text is never interpreted as HTML.
    const protectedText = text.replace(/\[([^\]\n]+)\]\((https?:\/\/[^\s<>]+)\)|https?:\/\/[^\s<>"']+/gi, (raw, label, destination, offset) => {
        const link = parsedMessageLink(destination || raw, label, text.slice(0, offset));
        if (!link) return raw;
        const image = link.image && previewCount < 4;
        if (image) previewCount++;
        const index = links.push({ text: link.text, href: link.href, image }) - 1;
        // Bare-link punctuation belongs to the surrounding message. Labelled
        // link syntax is consumed as a whole.
        const suffix = destination ? "" : link.suffix;
        return `${marker}${index}\uE001${suffix}`;
    });
    const pattern = new RegExp(`(${marker}\\d+\uE001)`, "g");
    return styledMessageParts(protectedText).flatMap(part => part.text.split(pattern).filter(Boolean).map(text => {
        if (text.startsWith(marker) && text.endsWith("\uE001")) {
            const link = links[Number(text.slice(marker.length, -1))];
            if (link) return { ...part, ...link };
        }
        return { ...part, text: expandEmojiShortcodes(text) };
    }));
}

function messageLinkHref(url) {
    return `/chat/link.php?url=${encodeURIComponent(url)}`;
}

function normalizeRoomListing(room) {
    if (!room || typeof room.name !== "string" || !room.name.trim()) return null;
    return {
        ...room,
        roomName: String(room.chatTitle || room.roomName || room.name),
        topic: String(room.chatTopic ?? room.topic ?? ""),
        memberCount: Math.max(0, Number(room.memberCount) || 0)
    };
}

function suggestedRoomId(title) {
    return String(title || "").normalize("NFKD").replace(/[\u0300-\u036f]/g, "")
        .toLowerCase().replace(/[^a-z0-9_-]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 64);
}

function createRoomRequest(title, id, topic, visibility) {
    const chatTitle = String(title || "").trim();
    const name = String(id || "").trim() || suggestedRoomId(chatTitle);
    if (!chatTitle || chatTitle.length > 64) throw new Error("Enter a chat name of 1–64 characters.");
    if (!/^[a-zA-Z0-9_-]{1,64}$/.test(name)) throw new Error("Choose a room address using 1–64 letters, numbers, hyphens or underscores.");
    if (!["public", "private", "invite"].includes(visibility)) throw new Error("Choose who can join this chat.");
    if (String(topic || "").length > 500) throw new Error("Keep the topic within 500 characters.");
    return { name, chatTitle, chatTopic: String(topic || "").trim(), visibility };
}

function matchingRooms(rooms, query) {
    const search = String(query || "").trim().toLowerCase();
    return rooms.filter(room => `${room.roomName} ${room.name} ${room.topic}`.toLowerCase().includes(search));
}

function youtubeVideoId(value) {
    if (typeof value !== "string") return null;
    const text = value.trim();
    if (/^[A-Za-z0-9_-]{11}$/.test(text)) return text;
    try {
        const url = new URL(text);
        if (!["https:", "http:"].includes(url.protocol)) return null;
        const host = url.hostname.toLowerCase();
        let id;
        if (host === "youtu.be") id = url.pathname.split("/")[1];
        else if (["youtube.com", "www.youtube.com", "m.youtube.com", "youtube-nocookie.com", "www.youtube-nocookie.com"].includes(host)) {
            id = url.pathname === "/watch" ? url.searchParams.get("v")
                : /^\/(embed|shorts|live)\//.test(url.pathname) ? url.pathname.split("/")[2] : null;
        }
        return /^[A-Za-z0-9_-]{11}$/.test(id || "") ? id : null;
    } catch { return null; }
}

function youtubeItems(payload) {
    const data = payload?.data ?? payload;
    const entries = Array.isArray(data) ? data : Array.isArray(data?.queue) ? data.queue : [data];
    return entries.map(entry => {
        const id = youtubeVideoId(typeof entry === "string" ? entry : entry?.videoId ?? entry?.id ?? entry?.url);
        return id ? { id, title: typeof entry?.title === "string" ? entry.title.slice(0, 200) : id } : null;
    }).filter(Boolean).slice(0, 100);
}

function updateYoutubeQueue(state, items, replace = false) {
    if (!items.length) return state;
    if (replace) return { current: items[0], queue: items.slice(1) };
    const queue = [...state.queue, ...items].slice(0, 100);
    return state.current ? { ...state, queue } : { current: queue.shift(), queue };
}

let youtubeApiPromise;
function loadYoutubeApi() {
    if (window.YT?.Player) return Promise.resolve(window.YT);
    if (youtubeApiPromise) return youtubeApiPromise;
    youtubeApiPromise = new Promise((resolve, reject) => {
        const script = document.createElement("script");
        const previous = window.onYouTubeIframeAPIReady;
        const timeout = window.setTimeout(() => fail(), 15000);
        function fail() {
            window.clearTimeout(timeout);
            script.remove();
            window.onYouTubeIframeAPIReady = previous;
            youtubeApiPromise = null;
            reject(new Error("YouTube could not load. Check your connection and try again."));
        }
        window.onYouTubeIframeAPIReady = () => {
            window.clearTimeout(timeout);
            window.onYouTubeIframeAPIReady = previous;
            resolve(window.YT);
            if (typeof previous === "function") previous();
        };
        script.src = "https://www.youtube.com/iframe_api";
        script.onerror = fail;
        document.head.appendChild(script);
    });
    return youtubeApiPromise;
}

createApp({
    setup() {
        /*
        |--------------------------------------------------------------------------
        | State
        |--------------------------------------------------------------------------
        */

        const session = ref(createSession());
        const userId = ref(null);
        const myClass = ref(0);

        const username = ref("");
        const usernameInput = ref("");
        const authMethod = ref("guest");
        const discordIdentity = ref(null);
        const rememberedSessionToken = ref(null);
        const discordConnecting = ref(false);
        const authError = ref("");

        const connected = ref(false);
        const connecting = ref(false);
        const connectionError = ref("");
        const reconnectAttempt = ref(0);

        // rooms the user has actually joined: { name, roomName, raw, memberList }
        const rooms = ref([]);
        const availableRooms = ref([]);
        const currentRoom = ref(null);
        const roomActivity = ref({});
        const inARoom = computed(() => rooms.value.length > 0);

        const showJoinDialog = ref(false);
        const showMobileUsers = ref(false);
        const mobileUsersDialog = ref(null);
        watch(showMobileUsers, async open => {
            await nextTick();
            const dialog = mobileUsersDialog.value;
            if (!dialog) return;
            if (showMobileUsers.value && open && !dialog.open) dialog.showModal();
            else if (!showMobileUsers.value && dialog.open) dialog.close();
        });
        function closeMobileUsersOnDesktop() {
            if (window.innerWidth > 800) showMobileUsers.value = false;
        }
        watch(currentRoom, () => { showMobileUsers.value = false; showEmojiPicker.value = false; });
        watch(username, () => { showMobileUsers.value = false; showJoinDialog.value = false; });
        const roomSearch = ref("");
        const chatDialog = ref(null);
        const chatDialogTab = ref("browse");
        const createChatName = ref("");
        const createChatId = ref("");
        const createChatIdSuggestion = computed(() => suggestedRoomId(createChatName.value));
        const creatingChat = ref(false);
        let createChatTimer = null;
        let pendingCreateRoomId = null;
        const createChatTopic = ref("");
        const createChatVisibility = ref("public");
        const chatDialogError = ref("");
        const joiningRoom = ref(null);
        let joinRoomTimer = null;
        let chatDialogTrigger = null;

        function createChat() {
            if (creatingChat.value || joiningRoom.value) return;
            chatDialogError.value = "";
            if (!isSocketReady()) {
                chatDialogError.value = "Reconnect before creating a chat.";
                return;
            }
            let request;
            try {
                request = createRoomRequest(createChatName.value, createChatId.value, createChatTopic.value, createChatVisibility.value);
            } catch (error) {
                chatDialogError.value = error.message;
                return;
            }
            creatingChat.value = true;
            pendingCreateRoomId = request.name;
            clearTimeout(createChatTimer);
            createChatTimer = setTimeout(() => {
                creatingChat.value = false;
                // Retain the request ID so a late success can still be recognised.
                chatDialogError.value = "Creation is taking longer than expected. Check Find a chat before retrying.";
                if (isSocketReady()) socket.emit("getRooms");
            }, 10000);
            socket.emit("createRoom", request);
        }

        function openChatDialog() {
            chatDialogTrigger = document.activeElement;
            chatDialogTab.value = "browse";
            if (!creatingChat.value) chatDialogError.value = "";
            showJoinDialog.value = true;
            if (isSocketReady()) socket.emit("getRooms");
        }

        watch(showJoinDialog, async open => {
            await nextTick();
            const dialog = chatDialog.value;
            if (open && showJoinDialog.value && dialog && !dialog.open) dialog.showModal();
            else if (!open) {
                dialog?.close();
                chatDialogTrigger?.focus();
            }
        });

        function roomAccessLabel(room) {
            if (room.visibility === "permission") return `Level ${room.requiredLevel || 0}+`;
            if (["private_users", "private", "invite"].includes(room.visibility)) return "Private";
            if (room.visibility === "unlisted") return "Unlisted";
            return "Public";
        }

        const profileImagesLoaded = ref({});

        // messages keyed by room id
        const messagesByRoom = ref({});
        const currentMessages = computed(() => messagesByRoom.value[currentRoom.value] || []);
        const memberSnapshotVersions = new Map();
        const seenMessageIdsByRoom = new Map();

        function isCurrentMemberSnapshot(roomId, version) {
            const nextVersion = Number(version);
            if (!Number.isFinite(nextVersion)) return true;

            const currentVersion = memberSnapshotVersions.get(roomId);
            if (currentVersion !== undefined && nextVersion < currentVersion) return false;

            memberSnapshotVersions.set(roomId, nextVersion);
            return true;
        }

        // When the server echoes a clientMessageId/messageId, ignore a repeated
        // delivery caused by a reconnect or retry. Keep a bounded cache per room.
        function rememberMessageId(roomId, id) {
            if (!id) return true;

            const ids = seenMessageIdsByRoom.get(roomId) || new Set();
            if (ids.has(id)) return false;

            ids.add(id);
            if (ids.size > 500) ids.delete(ids.values().next().value);
            seenMessageIdsByRoom.set(roomId, ids);
            return true;
        }

        // The server sends join/leave messages without a type; those remain in
        // the normal chat log. Only an explicit generic system type is a notice.
        function isSystemNotice(msg) {
            if (!msg?.isSystem) return false;
            const type = String(msg.type ?? "").trim().toLowerCase();
            return type === "system" || type === "systemmessage";
        }

        const visibleMessages = computed(() =>
            currentMessages.value.filter(msg => !isSystemNotice(msg))
        );

        const systemNotices = computed(() =>
            currentMessages.value.filter(isSystemNotice)
        );

        function dismissSystemNotice(room, id) {
            const roomMessages = messagesByRoom.value[room] || [];
            const index = roomMessages.findIndex(msg => msg.id === id);

            if (index !== -1) roomMessages.splice(index, 1);
        }

        function scheduleSystemNoticeDismissal(room, id) {
            window.setTimeout(() => dismissSystemNotice(room, id), 5000);
        }

        function messageParts(value) {
            const currentName = username.value.trim();
            const escapedName = currentName.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
            return messageContentParts(value).flatMap(part => {
                if (part.href || !currentName) return [{ ...part, mention: false }];
                return part.text.split(new RegExp(`(${escapedName})`, "gi"))
                    .filter(Boolean)
                    .map(text => ({ ...part, text, mention: text.toLowerCase() === currentName.toLowerCase() }));
            });
        }

        function messageMentionsCurrentUser(data) {
            const identifiers = [
                userId.value,
                username.value,
                discordIdentity.value?.username,
                discordIdentity.value?.displayName
            ].filter(Boolean).map(value => String(value).trim().toLowerCase());

            const mentions = Array.isArray(data?.mentions) ? data.mentions : [];
            if (mentions.some(mention => {
                const value = typeof mention === "object"
                    ? (mention.id || mention.userId || mention.username || mention.displayName)
                    : mention;
                return identifiers.includes(String(value || "").trim().toLowerCase());
            })) return true;

            const content = String(data?.content || "").toLowerCase();
            return identifiers.some(identifier =>
                content.includes(`@${identifier}`) || content.includes(identifier)
            );
        }

        function roomActivityClass(roomName) {
            return roomActivity.value[roomName] || "";
        }

        function clearRoomActivity(roomName) {
            if (!roomActivity.value[roomName]) return;
            const next = { ...roomActivity.value };
            delete next[roomName];
            roomActivity.value = next;
        }

        watch(currentRoom, roomName => {
            if (roomName) clearRoomActivity(roomName);
        });

        function profileImageKey(messageId, kind) {
            return `${messageId || "whois"}:${kind}`;
        }

        function isProfileImageLoaded(messageId, kind) {
            return profileImagesLoaded.value[profileImageKey(messageId, kind)] === true;
        }

        function markProfileImageLoaded(messageId, kind) {
            profileImagesLoaded.value = {
                ...profileImagesLoaded.value,
                [profileImageKey(messageId, kind)]: true
            };
        }

        function whoisRooms(profile) {
            const source = profile?.rooms
                || profile?.roomList
                || profile?.joinedRooms
                || profile?.roomMemberships
                || profile?.chats
                || profile?.rawData?.rooms
                || [];
            const list = Array.isArray(source)
                ? source
                : (source && typeof source === "object" ? Object.keys(source) : []);
            const names = list
                .map(room => typeof room === "string"
                    ? room
                    : (room?.name || room?.room || room?.roomName || room?.id))
                .map(value => String(value || "").trim())
                .filter(Boolean);
            return [...new Set(names)];
        }

        function joinWhoisRoom(roomName) {
            const target = String(roomName || "").trim();
            if (!target || !isSocketReady() || !currentRoom.value) return;

            if (isRoomJoined(target)) {
                const id = crypto.randomUUID();
                const notice = {
                    id,
                    user: "system",
                    message: `Already joined #${target}.`,
                    type: "system",
                    isSystem: true,
                    time: formatTime(new Date().toISOString())
                };
                messagesByRoom.value[currentRoom.value] ??= [];
                messagesByRoom.value[currentRoom.value].push(notice);
                scheduleSystemNoticeDismissal(currentRoom.value, id);
                return;
            }

            socket.emit("runCommand", {
                command: "sendMessage",
                data: {
                    recipientId: currentRoom.value,
                    content: `/join ${target}`,
                    userId: userId.value,
                    clientMessageId: crypto.randomUUID()
                }
            });
        }

        const userTyping = ref([]);
        const message = ref("");
        const messageBox = ref(null);
        const messageInput = ref(null);
        const showEmojiPicker = ref(false);
        const failedMessageImages = ref({});
        const multiline = ref(false);

        let socket = null;
        let typingTimeout = null;
        let discordPopup = null;
        let discordPopupTimer = null;
        let manageChannel = null;
        let reconnectRoomIds = new Set();
        let reconnectTargetRoom = null;
        const soundEnabled = ref((() => {
            try { return localStorage.getItem(SOUND_STORAGE_KEY) !== "off"; }
            catch (_) { return true; }
        })());
        let notificationAudio = null;
        let lastSoundAt = -Infinity;

        function unlockNotificationAudio() {
            if (!soundEnabled.value) return;
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                notificationAudio ??= new AudioContext();
                if (notificationAudio.state !== "running" && notificationAudio.state !== "closed") {
                    notificationAudio.resume().catch(() => {});
                }
            } catch (_) {
                // Audio support and autoplay permissions vary by browser.
            }
        }

        function toggleSound() {
            soundEnabled.value = !soundEnabled.value;
            try {
                localStorage.setItem(SOUND_STORAGE_KEY, soundEnabled.value ? "on" : "off");
            } catch (_) {}
            if (soundEnabled.value) unlockNotificationAudio();
        }

        function playMessageAlert() {
            if (!soundEnabled.value || !notificationAudio ||
                notificationAudio.state !== "running" ||
                (!document.hidden && document.hasFocus()) ||
                performance.now() - lastSoundAt < 1000) return;

            lastSoundAt = performance.now();
            try {
                const start = notificationAudio.currentTime;
                for (const [offset, frequency] of [[0, 660], [.12, 880]]) {
                    const tone = notificationAudio.createOscillator();
                    const volume = notificationAudio.createGain();
                    tone.frequency.value = frequency;
                    volume.gain.setValueAtTime(0, start + offset);
                    volume.gain.linearRampToValueAtTime(.12, start + offset + .01);
                    volume.gain.exponentialRampToValueAtTime(.001, start + offset + .16);
                    tone.connect(volume);
                    volume.connect(notificationAudio.destination);
                    tone.onended = () => { tone.disconnect(); volume.disconnect(); };
                    tone.start(start + offset);
                    tone.stop(start + offset + .18);
                }
            } catch (_) {
                // A suspended audio device must not interrupt message delivery.
            }
        }

        function isSocketReady() {
            return !!socket?.connected && connected.value;
        }

        const isBanned = computed(() =>
            currentRoomObj.value?.raw?.isBanned === true
        );

        /*
        |--------------------------------------------------------------------------
        | Permissions
        |--------------------------------------------------------------------------
        */

        const showPermissionsPanel = ref(false);
        const adminBusy = ref(false);
        const adminError = ref("");
        const newClassName = ref("");
        const newClassOrder = ref(0);

        const currentRoomObj = computed(() =>
            rooms.value.find(r => r.name === currentRoom.value) || null
        );

        function extractRoomTopic(...sources) {
            for (const source of sources.filter(Boolean)) {
                const candidates = [
                    source.topic,
                    source.chatTopic,
                    source.metadata?.topic,
                    source.raw?.topic,
                    source.raw?.chatTopic,
                    source.raw?.metadata?.topic,
                    source.rawData?.topic,
                    source.rawData?.chatTopic,
                    source.rawData?.metadata?.topic,
                    source.rawData?.rawData?.topic,
                    source.rawData?.rawData?.chatTopic
                ];
                const topic = candidates.find(value => value !== undefined && value !== null);
                if (topic !== undefined) return String(topic).trim();
            }
            return "";
        }

        const currentRoomTopic = computed(() => {
            const room = currentRoomObj.value;
            return room ? extractRoomTopic(room, room.raw) : "";
        });

        const isOwnerOfCurrentRoom = computed(() => {
            if (!currentRoomObj.value || !userId.value) return false;

            const ownerId =
                currentRoomObj.value.owner ??
                currentRoomObj.value.raw?.owner;

            return String(ownerId || "") === String(userId.value);
        });

        const isSiteStaff = computed(() =>
            normalizeClass(myClass.value) >= SITE_STAFF_LEVEL
        );

        const currentRoomCapabilities = computed(() =>
            currentRoomObj.value?.raw?.permissions ||
            currentRoomObj.value?.permissions ||
            {}
        );

        const canManageRoom = computed(() =>
            (
                isSiteStaff.value ||
                isOwnerOfCurrentRoom.value ||
                currentRoomCapabilities.value
                    .canManagePrivilegeClasses === true
            )
        );

        const canAssignRoomClasses = computed(() =>
            canManageRoom.value ||
            currentRoomCapabilities.value
                .canAssignPrivilegeClasses === true
        );

        const currentPrivilegeClasses = computed(() =>
            roomClassEntries(currentRoomObj.value)
        );

        function openChatSettings() {
            if (!canManageRoom.value || !currentRoomObj.value) return;

            const roomName = String(
                currentRoomObj.value.name ||
                currentRoomObj.value.roomName ||
                currentRoom.value ||
                ""
            ).trim();

            if (!roomName) return;

            // Keep the manager on the chat page's exact origin. postMessage
            // treats www/non-www hosts and different ports as different sites.
            const url =
                `${window.location.origin}/chat/` +
                `${encodeURIComponent(roomName)}/manage` +
                `?parentOrigin=${encodeURIComponent(window.location.origin)}`;
            const width = 980;
            const height = 760;
            const left = Math.max(0, window.screenX + (window.outerWidth - width) / 2);
            const top = Math.max(0, window.screenY + (window.outerHeight - height) / 2);

            const settingsWindow = window.open(
                url,
                `chat_settings_${roomName.replace(/[^a-z0-9]/gi, "_")}`,
                `popup=yes,width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
            );

            settingsWindow?.focus();
        }

        function processRoomManagerPayload(payload, reply) {
            if (!payload || !["ixeriox:manage-ready", "ixeriox:manage-command"].includes(payload.type)) return;

            const room = rooms.value.find(item => item.name === payload.room);
            const roomPermissions =
                room?.raw?.permissions ||
                room?.permissions ||
                {};
            const mayManageTargetRoom = Boolean(
                room &&
                (
                    roomPermissions.isOwner === true ||
                    roomPermissions.isStaff === true ||
                    roomPermissions
                        .canManagePrivilegeClasses === true ||
                    roomPermissions
                        .canAssignPrivilegeClasses === true ||
                    String(room.owner || "") ===
                    String(userId.value || "")
                )
            );

            if (
                !room ||
                !mayManageTargetRoom ||
                currentRoom.value !== room.name
            ) {
                reply({
                    type: "ixeriox:manage-result",
                    room: payload.room,
                    success: false,
                    message: "You no longer have permission to manage this room."
                });
                return;
            }

            if (payload.type === "ixeriox:manage-ready") {
                reply({
                    type: "ixeriox:manage-state",
                    room: room.name,
                    state: {
                        room: room.name,
                        owner: room.owner,
                        roomName: room.roomName || room.name,
                        topic: room.raw?.topic || "",
                        settings: room.raw?.settings || {},
                        members: (room.memberList || []).map(
                            member => ({
                                ...member,
                                privilegeClass:
                                    memberRoomClass(
                                        room,
                                        member
                                    ),
                                isOwner:
                                    String(member.id) ===
                                    String(room.owner)
                            })
                        ),
                        privilegeClasses:
                            roomPrivilegeClasses(room),
                        userPrivilegeClasses:
                            room.raw
                                ?.userPrivilegeClasses ||
                            room.userPrivilegeClasses ||
                            {},
                        defaultPrivilegeClass:
                            room.raw
                                ?.defaultPrivilegeClass ||
                            room.defaultPrivilegeClass ||
                            null,
                        permissions:
                            room.raw?.permissions ||
                            room.permissions ||
                            {}
                    }
                });
                return;
            }

            const allowedCommands = new Set([
                "updateRoomSettings",
                "setUserClass",
                "kickUser",
                "clearMessages",
                "deleteRoom",
                "adminCommand",
                "chatCommand"
            ]);

            if (!allowedCommands.has(payload.command) || !isSocketReady()) {
                reply({
                    type: "ixeriox:manage-result",
                    room: room.name,
                    success: false,
                    message: "That room action is unavailable."
                });
                return;
            }

            const commandPayload = [
                "adminCommand",
                "chatCommand"
            ].includes(payload.command)
                ? {
                    command: "sendMessage",
                    data: {
                        recipientId: room.name,
                        content:
                            payload.command ===
                            "adminCommand"
                                ? `/admin ${payload.data?.arguments || ""}`
                                : String(
                                    payload.data?.content ||
                                    ""
                                ),
                        clientMessageId:
                            crypto.randomUUID()
                    }
                }
                : {
                    command: payload.command,
                    data: {
                        recipientId: room.name,
                        userId: userId.value,
                        ...(payload.data || {})
                    }
                };

            if (
                payload.command === "adminCommand" ||
                payload.command === "chatCommand"
            ) {
                socket.emit("runCommand", commandPayload);
                reply({
                    type: "ixeriox:manage-result",
                    room: room.name,
                    success: true,
                    message: "Room update submitted."
                });
                return;
            }

            // manage.php does not create its own socket. Commands travel
            // through this already-authenticated chat connection, and the
            // server acknowledgement is posted back to the popup.
            socket.timeout(8000).emit("runCommand", commandPayload, (error, response = {}) => {
                if (error) {
                    reply({
                        type: "ixeriox:manage-result",
                        room: room.name,
                        success: false,
                        message: "The chat server did not confirm the update."
                    });
                    return;
                }

                const success =
                    response.success === true ||
                    response.status === "success";

                if (success && payload.command === "updateRoomSettings") {
                    const updates = payload.data || {};
                    if (Object.prototype.hasOwnProperty.call(updates, "topic")) {
                        room.topic = String(updates.topic ?? "").trim();
                    }
                    room.raw = {
                        ...(room.raw || {}),
                        ...updates,
                        metadata: {
                            ...(room.raw?.metadata || {}),
                            ...(updates.metadata || {})
                        }
                    };
                }

                reply({
                    type: "ixeriox:manage-result",
                    room: room.name,
                    success,
                    message:
                        response.message ||
                        response.error ||
                        (success ? "Room settings updated." : "The chat server rejected the update.")
                });
            });
        }

        function handleRoomManagerMessage(event) {
            if (!isTrustedRoomManagerOrigin(event.origin)) {
                return;
            }

            processRoomManagerPayload(
                event.data,
                message => event.source?.postMessage(JSON.parse(JSON.stringify(message)), event.origin)
            );
        }

        function isTrustedRoomManagerOrigin(origin) {
            try {
                const candidate = new URL(origin);
                const current = new URL(window.location.origin);

                if (
                    candidate.protocol !== "https:" &&
                    candidate.origin !== current.origin
                ) {
                    return false;
                }

                const isIXerioxHost = hostname =>
                    hostname === "ixeriox.dev" ||
                    hostname.endsWith(".ixeriox.dev");

                return (
                    candidate.origin === current.origin ||
                    (
                        isIXerioxHost(candidate.hostname) &&
                        isIXerioxHost(current.hostname)
                    )
                );
            } catch {
                return false;
            }
        }

        function handleRoomManagerBroadcast(event) {
            processRoomManagerPayload(
                event.data,
                message => manageChannel?.postMessage(JSON.parse(JSON.stringify(message)))
            );
        }

        function currentUserPrivilegeOrder() {
            const room = currentRoomObj.value;
            if (!room) return null;

            if (
                isSiteStaff.value ||
                isOwnerOfCurrentRoom.value
            ) {
                return Number.POSITIVE_INFINITY;
            }

            return Number(
                room.raw?.privilegeOrder ??
                room.privilegeOrder ??
                0
            );
        }

        function canManageUser(user) {
            if (!canAssignRoomClasses.value || !user) {
                return false;
            }

            if (isRoomOwner(currentRoomObj.value, user)) {
                return isOwnerOfCurrentRoom.value || isSiteStaff.value;
            }

            if (
                String(user.id) === String(userId.value) ||
                user.isStaff === true
            ) {
                return false;
            }

            if (
                isSiteStaff.value ||
                isOwnerOfCurrentRoom.value
            ) {
                return true;
            }

            return (
                currentUserPrivilegeOrder() >
                Number(user.privilegeOrder || 0)
            );
        }

        function canAssignClass(className) {
            if (
                isSiteStaff.value ||
                isOwnerOfCurrentRoom.value
            ) {
                return true;
            }

            const definition =
                roomPrivilegeClasses(
                    currentRoomObj.value
                )[className];

            return Boolean(
                definition &&
                Number(definition.order || 0) <
                currentUserPrivilegeOrder()
            );
        }

        // Applies a new class to a member and tells the server. This is
        // optimistic on the client — the server is expected to confirm (or
        // correct) the change via a later `updateMembers` / `usersStatus`
        // broadcast, which the existing listeners below already handle.
        function sendAdminCommand(argumentsText) {
            if (
                !isSocketReady() ||
                !currentRoom.value ||
                adminBusy.value
            ) {
                return false;
            }

            adminBusy.value = true;
            adminError.value = "";

            socket.emit("runCommand", {
                command: "sendMessage",
                data: {
                    recipientId: currentRoom.value,
                    content: `/admin ${argumentsText}`,
                    clientMessageId: crypto.randomUUID()
                }
            });

            window.setTimeout(() => {
                adminBusy.value = false;
            }, 10000);

            return true;
        }

        function createPrivilegeClass() {
            const name = newClassName.value.trim();
            const order = Number(newClassOrder.value);

            if (!name || !Number.isFinite(order)) {
                adminError.value =
                    "Enter a class name and numeric order.";
                return;
            }

            if (
                sendAdminCommand(
                    `class add ${name} | ${Math.trunc(order)}`
                )
            ) {
                newClassName.value = "";
                newClassOrder.value = 0;
            }
        }

        function renamePrivilegeClass(className) {
            const replacement = window.prompt(
                `Rename "${className}" to:`,
                className
            )?.trim();

            if (
                replacement &&
                replacement !== className
            ) {
                sendAdminCommand(
                    `class rename ${className} | ${replacement}`
                );
            }
        }

        function deletePrivilegeClass(className) {
            if (isRequiredPrivilegeClass(roomPrivilegeClasses(currentRoomObj.value)[className])) {
                adminError.value = "Classes at order -1 and 0 are required and cannot be deleted.";
                return;
            }
            if (
                window.confirm(
                    `Delete "${className}"? Assigned users will return to the default class.`
                )
            ) {
                sendAdminCommand(
                    `class delete ${className}`
                );
            }
        }

        function setPrivilegeClassOrder(
            className,
            order
        ) {
            const numericOrder = Number(order);
            if (!Number.isFinite(numericOrder)) return;

            sendAdminCommand(
                `class order ${className} | ${Math.trunc(numericOrder)}`
            );
        }

        function toggleClassPermission(
            className,
            permission,
            enabled
        ) {
            sendAdminCommand(
                `permission ${enabled ? "add" : "remove"} ` +
                `${className} | ${permission}`
            );
        }

        function setUserClass(user, className) {
            if (
                !canManageUser(user) ||
                !canAssignClass(className)
            ) {
                return;
            }

            sendAdminCommand(
                `assign ${user.id} | ${className}`
            );
        }

        function unassignUserClass(user) {
            if (canManageUser(user)) {
                sendAdminCommand(`unassign ${user.id}`);
            }
        }

        function setDefaultPrivilegeClass(className) {
            sendAdminCommand(`default ${className}`);
        }

        /*
        |--------------------------------------------------------------------------
        | Watch Party (opt-in synced media player)
        |--------------------------------------------------------------------------
        */

        // YouTube playback and queues are separate from the existing watch party.
        const youtubeByRoom = ref({});
        const youtubeState = computed(() => youtubeByRoom.value[currentRoom.value] || { current: null, queue: [] });
        const youtubeDesktop = ref(null);
        const youtubeMobile = ref(null);
        const youtubeError = ref("");
        let youtubePlayer = null;
        let youtubeGeneration = 0;
        let youtubeIdleTimer = null;
        let youtubePlaying = false;
        const YOUTUBE_IDLE_MS = 5 * 60 * 1000;

        function resetYoutubeIdle() {
            clearTimeout(youtubeIdleTimer);
            if (!youtubeState.value.current || youtubePlaying) return;
            const room = currentRoom.value;
            youtubeIdleTimer = setTimeout(() => {
                if (currentRoom.value === room && !youtubePlaying) closeYoutube();
            }, YOUTUBE_IDLE_MS);
        }

        function playYoutube(data) { handleYoutubeCommand("playYoutube", data); }
        function AddQueue(data) { handleYoutubeCommand("AddQueue", data); }

        function testYoutube(argument = "") {
            if (!currentRoom.value) return;
            const id = youtubeVideoId(argument || "M7lc1UVf-VE");
            if (!id) {
                youtubeError.value = "Use /testyoutube followed by a YouTube video ID or URL.";
                return;
            }
            playYoutube({ room: currentRoom.value, data: { id, title: "YouTube player test" } });
            AddQueue({ room: currentRoom.value, data: { id: "aqz-KE-bpKQ", title: "Queued video test" } });
        }

        function handleYoutubeCommand(command, payload) {
            const room = payload?.room ?? payload?.roomId ?? payload?.data?.room ?? payload?.data?.roomId ?? currentRoom.value;
            if (!room || !rooms.value.some(item => item.name === room)) return;
            const items = youtubeItems(payload);
            if (!items.length) return;
            const state = youtubeByRoom.value[room] || { current: null, queue: [] };
            youtubeByRoom.value = { ...youtubeByRoom.value, [room]: updateYoutubeQueue(state, items, command.toLowerCase() === "playyoutube") };
            if (room === currentRoom.value) resetYoutubeIdle();
        }

        function nextYoutube(room = currentRoom.value) {
            const state = youtubeByRoom.value[room];
            if (!state) return;
            const [current = null, ...queue] = state.queue;
            youtubeByRoom.value = { ...youtubeByRoom.value, [room]: { current, queue } };
        }

        function closeYoutube() {
            const updated = { ...youtubeByRoom.value };
            delete updated[currentRoom.value];
            youtubeByRoom.value = updated;
        }

        function destroyYoutubePlayer() {
            youtubeGeneration++;
            clearTimeout(youtubeIdleTimer);
            youtubePlaying = false;
            youtubePlayer?.destroy();
            youtubePlayer = null;
        }

        async function mountYoutubePlayer() {
            destroyYoutubePlayer();
            const generation = youtubeGeneration;
            const room = currentRoom.value;
            const video = youtubeState.value.current;
            youtubeError.value = "";
            if (!video) return;
            resetYoutubeIdle();
            await nextTick();
            try {
                const YT = await loadYoutubeApi();
                if (generation !== youtubeGeneration) return;
                const container = showMobileUsers.value ? youtubeMobile.value : youtubeDesktop.value;
                if (!container) return;
                // The API replaces its target; keep Vue's container intact.
                const target = document.createElement("div");
                container.replaceChildren(target);
                youtubePlayer = new YT.Player(target, {
                    videoId: video.id,
                    width: "100%", height: "100%",
                    playerVars: { autoplay: 1, playsinline: 1, origin: window.location.origin },
                    events: {
                        onStateChange(event) {
                            if (generation !== youtubeGeneration) return;
                            youtubePlaying = event.data === YT.PlayerState.PLAYING || event.data === YT.PlayerState.BUFFERING;
                            resetYoutubeIdle();
                            if (event.data === YT.PlayerState.ENDED && youtubeByRoom.value[room]?.queue.length) nextYoutube(room);
                        },
                        onError() {
                            if (generation !== youtubeGeneration) return;
                            youtubePlaying = false;
                            resetYoutubeIdle();
                            youtubeError.value = "This video cannot be played here. Skip to the next video.";
                        },
                        onAutoplayBlocked() {
                            if (generation === youtubeGeneration) youtubeError.value = "Press play in the video to start playback.";
                        }
                    }
                });
            } catch (error) {
                if (generation === youtubeGeneration) youtubeError.value = error.message;
            }
        }

        watch([() => youtubeState.value.current, currentRoom, showMobileUsers], mountYoutubePlayer);

        const mediaByRoom = ref({}); // { [roomId]: { url, playing, time, loadedBy } }
        const partyMembership = ref({}); // { [roomId]: boolean } — local opt-in state
        const mediaPanelExpanded = ref(false);
        const mediaUrlInput = ref("");
        const videoRef = ref(null);

        // Prevents the native <video> play/pause/seeked events from re-emitting
        // a command when we just applied one that came in from the server.
        let suppressMediaEvents = false;

        function toggleWatchParty() {
            if (!isSocketReady() || !currentRoom.value) return;

            const joining = !partyMembership.value[currentRoom.value];
            partyMembership.value = { ...partyMembership.value, [currentRoom.value]: joining };

            socket.emit("runCommand", {
                command: joining ? "joinWatchParty" : "leaveWatchParty",
                data: { recipientId: currentRoom.value, userId: userId.value }
            });
        }

        function loadMedia() {
            const url = mediaUrlInput.value.trim();
            if (!url || !canManageRoom.value || !isSocketReady() || !currentRoom.value) return;

            socket.emit("runCommand", {
                command: "mediaControl",
                data: { recipientId: currentRoom.value, action: "load", url, time: 0 }
            });

            mediaUrlInput.value = "";
        }

        function sendMediaControl(action) {
            if (!canManageRoom.value || !isSocketReady() || !currentRoom.value) return;

            const time = videoRef.value ? videoRef.value.currentTime : 0;

            socket.emit("runCommand", {
                command: "mediaControl",
                data: { recipientId: currentRoom.value, action, time }
            });
        }

        function onVideoPlay() {
            if (suppressMediaEvents) return;
            sendMediaControl("play");
        }

        function onVideoPause() {
            if (suppressMediaEvents) return;
            sendMediaControl("pause");
        }

        function onVideoSeeked() {
            if (suppressMediaEvents) return;
            sendMediaControl("seek");
        }

        function applyRemoteMediaUpdate(room, data) {
            const existing = mediaByRoom.value[room] || { url: "", playing: false, time: 0, loadedBy: null };
            const updated = { ...existing };

            if (data.action === "load") {
                updated.url = data.url;
                updated.playing = false;
                updated.time = 0;
                updated.loadedBy = data.userId || data.loadedBy || null;
            } else {
                if (typeof data.time === "number") updated.time = data.time;
                if (data.action === "play") updated.playing = true;
                if (data.action === "pause") updated.playing = false;
            }

            mediaByRoom.value = { ...mediaByRoom.value, [room]: updated };

            if (room !== currentRoom.value || !partyMembership.value[room]) return;

            nextTick(() => {
                const el = videoRef.value;
                if (!el) return;

                suppressMediaEvents = true;

                if (data.action === "load" && el.getAttribute("src") !== data.url) {
                    el.src = data.url;
                }

                if (typeof updated.time === "number" && Math.abs(el.currentTime - updated.time) > 1) {
                    el.currentTime = updated.time;
                }

                if (updated.playing) {
                    el.play().catch(() => {});
                } else {
                    el.pause();
                }

                setTimeout(() => { suppressMediaEvents = false; }, 250);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Login
        |--------------------------------------------------------------------------
        */

        async function checkUsernameRegistered(name) {
            try {
                const response = await fetch(`https://api.infini9.net/getUserInfo/${encodeURIComponent(name)}`);
                const data = await response.json();
                return data.success === true;
            } catch (error) {
                console.error("[CHAT] Username check failed:", error);
                return false;
            }
        }

        async function login() {
            let name = usernameInput.value.trim();

            const disallowedNames = [
                "admin", "administrator", "moderator", "owner", "root", "system",
                "bot", "support", "help", "mod", "staff", "manager", "supervisor",
                "sysadmin", "webmaster", "master", "superuser", "guest", "user",
                "official", "team", "service", "server", "automod", "helper"
            ];

            let wasRenamed = false;
            const attemptedName = name;

            if (!name || disallowedNames.includes(name.toLowerCase())) {
                name = createMockUserId(session.value);
                wasRenamed = true;
            } else {
                const isRegistered = await checkUsernameRegistered(name);
                if (isRegistered) {
                    name = "Guest_" + Math.floor(Math.random() * 1000000);
                    wasRenamed = true;
                }
            }

            if (wasRenamed && attemptedName) {
                // Store the rename notification to show after connection
                const renameMessage = {
                    id: crypto.randomUUID(),
                    user: "system",
                    message: `You were renamed to "${name}" because "${attemptedName}" is restricted or already registered.`,
                    type: "",
                    isSystem: true,
                    time: formatTime(Date.now()),
                    class: 0
                };

                // Add to the first room's messages once connected
                nextTick(() => {
                    const firstRoomId = PUBLIC_ROOM_ID;
                    if (!messagesByRoom.value[firstRoomId]) {
                        messagesByRoom.value[firstRoomId] = [];
                    }
                    messagesByRoom.value[firstRoomId].push(renameMessage);
                    scheduleSystemNoticeDismissal(firstRoomId, renameMessage.id);
                });
            }


            username.value = name;
            authMethod.value = "guest";
            discordIdentity.value = null;
            authError.value = "";
            localStorage.setItem(USERNAME_STORAGE_KEY, name);

            connectSocket();
        }

        function closeDiscordPopup() {
            if (discordPopupTimer) {
                window.clearInterval(discordPopupTimer);
                discordPopupTimer = null;
            }

            if (discordPopup && !discordPopup.closed) {
                discordPopup.close();
            }

            discordPopup = null;
        }

        function loginWithDiscord() {
            if (discordConnecting.value || connecting.value) return;

            authError.value = "";
            discordConnecting.value = true;

            const width = 520;
            const height = 720;
            const left = Math.max(0, window.screenX + (window.outerWidth - width) / 2);
            const top = Math.max(0, window.screenY + (window.outerHeight - height) / 2);
            const returnOrigin = encodeURIComponent(window.location.origin);
            const authUrl = `${DISCORD_AUTH_URL}?popup=1&return_origin=${returnOrigin}`;

            discordPopup = window.open(
                authUrl,
                "ixeriox_discord_login",
                `popup=yes,width=${width},height=${height},left=${left},top=${top}`
            );

            if (!discordPopup) {
                discordConnecting.value = false;
                authError.value = "The Discord popup was blocked. Allow popups and try again.";
                return;
            }

            discordPopup.focus();
            discordPopupTimer = window.setInterval(() => {
                if (!discordPopup || discordPopup.closed) {
                    closeDiscordPopup();

                    if (discordConnecting.value) {
                        discordConnecting.value = false;
                        authError.value = "Discord sign-in was cancelled.";
                    }
                }
            }, 400);
        }

        function handleDiscordAuthMessage(event) {
            if (event.origin !== window.location.origin) return;

            const payload = event.data;
            if (!payload || payload.type !== DISCORD_AUTH_MESSAGE) return;

            closeDiscordPopup();
            discordConnecting.value = false;

            if (!payload.success) {
                authError.value = payload.error || "Discord sign-in was not completed.";
                return;
            }

            const discordUser = payload.user || {};
            const discordId = String(discordUser.id || payload.discordId || "").trim();
            const displayName = String(
                discordUser.global_name ||
                discordUser.displayName ||
                discordUser.username ||
                payload.username ||
                ""
            ).trim();

            if (!discordId || !displayName) {
                authError.value = "Discord returned an incomplete profile. Please try again.";
                return;
            }

            discordIdentity.value = {
                id: discordId,
                username: displayName.slice(0, 24),
                avatar: discordUser.avatar || null,
                token: payload.token || payload.accessToken || null
            };
            authMethod.value = "discord";
            username.value = discordIdentity.value.username;
            usernameInput.value = discordIdentity.value.username;
            userId.value = discordIdentity.value.id;
            authError.value = "";

            connectSocket();
        }

        function logout() {
            clearTimeout(joinRoomTimer);
            clearTimeout(createChatTimer);
            joiningRoom.value = null;
            creatingChat.value = false;
            pendingCreateRoomId = null;
            chatDialogError.value = "";
            cleanupSocket();
            clearTimeout(typingTimeout);

            username.value = "";
            usernameInput.value = "";
            authMethod.value = "guest";
            discordIdentity.value = null;
            discordConnecting.value = false;
            authError.value = "";
            closeDiscordPopup();
            userId.value = null;
            myClass.value = 0;

            rooms.value = [];
            availableRooms.value = [];
            currentRoom.value = null;
            messagesByRoom.value = {};
            memberSnapshotVersions.clear();
            seenMessageIdsByRoom.clear();
            userTyping.value = [];
            connected.value = false;
            connecting.value = false;
            connectionError.value = "";
            reconnectAttempt.value = 0;
            reconnectRoomIds = new Set();
            reconnectTargetRoom = null;
            youtubeByRoom.value = {};
            mediaByRoom.value = {};
            partyMembership.value = {};
            showPermissionsPanel.value = false;
            mediaPanelExpanded.value = false;

            localStorage.removeItem(USERNAME_STORAGE_KEY);
            clearChatUserCookie();
        }

        /*
        |--------------------------------------------------------------------------
        | Socket Connection
        |--------------------------------------------------------------------------
        */

        function connectSocket() {
            if (socket) {
                if (!socket.connected) socket.connect();
                return;
            }

            connecting.value = true;
            connectionError.value = "";
            reconnectAttempt.value = 0;

            socket = io(SOCKET_URL, {
                transports: ["websocket", "polling"],
                tryAllTransports: true,
                closeOnBeforeunload: false,
                reconnection: true,
                reconnectionAttempts: Infinity,
                reconnectionDelay: 1000,
                reconnectionDelayMax: 10000,
                randomizationFactor: 0.5,
                timeout: 10000,
                withCredentials: true
            });

            socket.io.on("reconnect_attempt", attempt => {
                reconnectAttempt.value = attempt;
                connecting.value = true;
                connectionError.value = `Reconnecting… (attempt ${attempt})`;
            });

            socket.io.on("reconnect_error", () => {
                connected.value = false;
            });

            socket.on("connect", () => {
                console.log("[CHAT] Connected", socket.id);

                connected.value = true;
                connecting.value = false;
                connectionError.value = "";
                reconnectAttempt.value = 0;

                socket.emit("login", {
                    authMethod: authMethod.value,
                    discordId: discordIdentity.value?.id || username.value,
                    username: username.value,
                    session: session.value,
                    sessionToken: rememberedSessionToken.value,
                });
            });
            socket.on("changeUsername", data => {
                const canonicalName = String(data?.newDisplayName || "").trim();
                if (!canonicalName) return;

                console.log(`[CHAT] Username changed to ${canonicalName}`);
                username.value = canonicalName;
                usernameInput.value = canonicalName;
                localStorage.setItem(USERNAME_STORAGE_KEY, canonicalName);
            });
            socket.on('forceDisconnect', (reason) => {
                console.warn("[CHAT] Force disconnected:", reason);
                connectionError.value = `Disconnected by server: ${reason || "No reason provided"}`;
                logout();
            });
            socket.on("disconnect", reason => {
                console.warn("[CHAT] Disconnected:", reason);

                // Just reflect the dropped state here — socket.io is already
                // configured to retry the connection on its own (see
                // `reconnection` above). Calling logout() here would wipe the
                // session and kill the socket ourselves, which defeats those
                // retries and forces a fresh login on every network hiccup.
                connected.value = false;
                connectionError.value = reason ?? connectionError.value ?? "Disconnected";
                connecting.value = reason !== "io client disconnect";
                userTyping.value = [];
                clearTimeout(typingTimeout);

                reconnectTargetRoom = currentRoom.value;
                reconnectRoomIds = new Set(rooms.value.map(room => room.name));
            });

            socket.on("connect_error", error => {
                console.error("[CHAT] Socket error:", error);

                connected.value = false;
                connecting.value = true;
                connectionError.value = "Can't reach the chat server. Retrying...";
            });

            socket.on("login", data => {
                console.log("[CHAT] Login response:", data);

                if (data.status !== "success" && data.success !== true) {
                    console.warn("[CHAT] Login rejected", data);
                    connected.value = false;
                    connecting.value = false;
                    connectionError.value = "Login was rejected by the server.";
                    cleanupSocket();
                    return;
                }

                rememberedSessionToken.value =
                    data.sessionToken ||
                    data.authToken ||
                    rememberedSessionToken.value ||
                    null;

                setChatUserCookie({
                    username: username.value,
                    authMethod: authMethod.value,
                    discordId: discordIdentity.value?.id || null,
                    sessionToken: rememberedSessionToken.value
                });

                console.log("[CHAT] Authenticated as:", username.value, userId.value);

                // Restore every previously open room after a reconnect. For a
                // first-time login, open the public room as before.
                const roomIds = reconnectRoomIds.size
                    ? [...reconnectRoomIds]
                    : (rooms.value.length ? rooms.value.map(room => room.name) : [PUBLIC_ROOM_ID]);

                reconnectRoomIds = new Set(roomIds);
                roomIds.forEach(roomId => joinRoom(roomId, true));

                socket.emit("getRooms");
            });

            /*
            |--------------------------------------------------------------------------
            | Rooms
            |--------------------------------------------------------------------------
            */

            socket.on("createRoom", data => {
                const room = data?.room;
                if (data?.response !== "success" || !room?.name || room.name !== pendingCreateRoomId) return;
                clearTimeout(createChatTimer);
                pendingCreateRoomId = null;
                creatingChat.value = false;
                chatDialogError.value = "";
                createChatName.value = "";
                createChatId.value = "";
                createChatTopic.value = "";
                createChatVisibility.value = "public";
                showJoinDialog.value = false;
                const listing = normalizeRoomListing(room);
                availableRooms.value = [...availableRooms.value.filter(item => item.name !== room.name), listing];
                // V2 creates membership but does not emit joinRoom or its full
                // member snapshot. Fetch that through the normal join protocol.
                joinRoom(room.name, true);
                socket.emit("getRooms");
            });

            socket.on("roomCreated", () => {
                // V2 broadcasts creation globally. Refresh the access-filtered
                // list rather than treating that broadcast as an invitation.
                if (isSocketReady()) socket.emit("getRooms");
            });

            socket.on("error", data => {
                if (!creatingChat.value && !joiningRoom.value) return;
                if (data?.room && data.room !== pendingCreateRoomId && data.room !== joiningRoom.value) return;
                clearTimeout(createChatTimer);
                clearTimeout(joinRoomTimer);
                creatingChat.value = false;
                pendingCreateRoomId = null;
                joiningRoom.value = null;
                chatDialogError.value = String(data?.message || "The chat request failed. Please try again.");
            });

            socket.on("rooms", data => {
                const listings = Array.isArray(data) ? data : data?.rooms;
                availableRooms.value = (Array.isArray(listings) ? listings : []).map(normalizeRoomListing).filter(Boolean);
                console.log("[CHAT] Available rooms:", data);
            });
            // [
            //     "topicUpdated",
            //     {
            //         "room": "iXPublic",
            //         "newTopic": "test",
            //         "updatedBy": "375368296347729921",
            //         "timestamp": "2026-07-27T16:26:03.697Z"
            //     }
            // ]
            socket.on("topicUpdated", data => {
                if (!data?.room || !Object.prototype.hasOwnProperty.call(data, "newTopic")) return;

                const newTopic = String(data.newTopic ?? "").trim();
                const updateTopic = room => {
                    if (!room || room.name !== data.room) return room;
                    return {
                        ...room,
                        topic: newTopic,
                        chatTopic: newTopic,
                        metadata: { ...(room.metadata || {}), topic: newTopic },
                        raw: {
                            ...(room.raw || {}),
                            topic: newTopic,
                            chatTopic: newTopic,
                            metadata: { ...(room.raw?.metadata || {}), topic: newTopic },
                            rawData: {
                                ...(room.raw?.rawData || {}),
                                topic: newTopic,
                                chatTopic: newTopic,
                                rawData: {
                                    ...(room.raw?.rawData?.rawData || {}),
                                    topic: newTopic,
                                    chatTopic: newTopic
                                }
                            }
                        }
                    };
                };

                rooms.value = rooms.value.map(updateTopic);
                availableRooms.value = availableRooms.value.map(updateTopic);
            });

            socket.on(
                "roomAdministrationUpdated",
                data => {
                    if (!data?.room) return;

                    const applyAdministration = room => {
                        if (room.name !== data.room) {
                            return room;
                        }

                        const raw = {
                            ...(room.raw || {}),
                            privilegeClasses:
                                data.privilegeClasses ||
                                room.raw?.privilegeClasses ||
                                {},
                            defaultPrivilegeClass:
                                data.defaultPrivilegeClass ??
                                room.raw?.defaultPrivilegeClass ??
                                null,
                            userPrivilegeClasses:
                                data.userPrivilegeClasses ||
                                room.raw
                                    ?.userPrivilegeClasses ||
                                {}
                        };

                        const updatedRoom = {
                            ...room,
                            raw,
                            privilegeClasses:
                            raw.privilegeClasses,
                            defaultPrivilegeClass:
                            raw.defaultPrivilegeClass,
                            userPrivilegeClasses:
                            raw.userPrivilegeClasses
                        };

                        return {
                            ...updatedRoom,
                            memberList: (
                                updatedRoom.memberList || []
                            ).map(member => ({
                                ...member,
                                privilegeClass:
                                    memberRoomClass(
                                        updatedRoom,
                                        member
                                    ),
                                privilegeOrder:
                                    Number(
                                        roomPrivilegeClasses(
                                            updatedRoom
                                        )[
                                            memberRoomClass(
                                                updatedRoom,
                                                member
                                            )
                                            ]?.order || 0
                                    )
                            }))
                        };
                    };

                    rooms.value =
                        rooms.value.map(
                            applyAdministration
                        );
                    availableRooms.value =
                        availableRooms.value.map(
                            applyAdministration
                        );
                    adminBusy.value = false;
                    adminError.value = "";

                    const updatedRoom = rooms.value.find(
                        item => item.name === data.room
                    );

                    if (updatedRoom && manageChannel) {
                        manageChannel.postMessage(JSON.parse(JSON.stringify({
                            type: "ixeriox:manage-state",
                            room: updatedRoom.name,
                            state: {
                                room: updatedRoom.name,
                                owner: updatedRoom.owner,
                                roomName:
                                    updatedRoom.roomName ||
                                    updatedRoom.name,
                                topic:
                                    updatedRoom.topic || "",
                                settings:
                                    updatedRoom.raw
                                        ?.settings || {},
                                members:
                                    updatedRoom.memberList ||
                                    [],
                                privilegeClasses:
                                    roomPrivilegeClasses(
                                        updatedRoom
                                    ),
                                userPrivilegeClasses:
                                    updatedRoom.raw
                                        ?.userPrivilegeClasses ||
                                    {},
                                defaultPrivilegeClass:
                                    updatedRoom.raw
                                        ?.defaultPrivilegeClass ||
                                    null,
                                permissions:
                                    updatedRoom.raw
                                        ?.permissions || {}
                            }
                        })));
                    }
                }
            );

            socket.on("joinRoom", data => {
                if (data?.response === "error" || data?.success === false || !data?.room?.name) {
                    if (joiningRoom.value) {
                        clearTimeout(joinRoomTimer);
                        joiningRoom.value = null;
                        chatDialogError.value = String(data?.message || data?.error || "Unable to join this chat.");
                    }
                    return;
                }
                console.log("[CHAT] Joined room:", data.room);
                if (joiningRoom.value === data.room.name) {
                    clearTimeout(joinRoomTimer);
                    joiningRoom.value = null;
                    showJoinDialog.value = false;
                }

                const roomId = data.room.name;
                const alreadyJoined = rooms.value.some(r => r.name === roomId);
                const known = availableRooms.value.find(r => r.name === roomId);
                const mergedRaw = {
                    ...(known || {}),
                    ...data.room,
                    metadata: {
                        ...(known?.metadata || {}),
                        ...(data.room.metadata || {})
                    }
                };

                if (!alreadyJoined) {
                    rooms.value.push({
                        name: data.room.name,
                        roomName: data.room.chatTitle || data.room.roomName || known?.roomName || roomId,
                        owner: data.room.owner || known?.owner || null,
                        topic: extractRoomTopic(data.room, known),
                        raw: mergedRaw,
                        memberList: (
                            data.room.memberList ||
                            data.memberList ||
                            data.members ||
                            []
                        ).map(m => ({
                            ...m,
                            privilegeClass: memberRoomClass({ ...data.room, raw: mergedRaw }, m)
                        }))
                    });
                } else {
                    const room = rooms.value.find(r => r.name === roomId);
                    room.roomName = data.room.chatTitle || data.room.roomName || known?.roomName || room.roomName || roomId;
                    room.owner = data.room.owner || known?.owner || room.owner || null;
                    room.topic = extractRoomTopic(data.room, known, room);
                    room.raw = { ...(room.raw || {}), ...mergedRaw };
                }

                if (!messagesByRoom.value[roomId]) {
                    messagesByRoom.value[roomId] = [];
                }

                if (reconnectRoomIds.has(roomId)) {
                    reconnectRoomIds.delete(roomId);

                    if (reconnectRoomIds.size === 0 && reconnectTargetRoom) {
                        currentRoom.value = reconnectTargetRoom;
                        reconnectTargetRoom = null;
                    } else if (!currentRoom.value) {
                        currentRoom.value = roomId;
                    }
                } else {
                    currentRoom.value = roomId;
                }
            });

            /*
            |--------------------------------------------------------------------------
            | Typing
            |--------------------------------------------------------------------------
            */

            socket.on("userTyping", data => {
                if (!userTyping.value.includes(data.userId)) {
                    userTyping.value.push(data.userId);
                }
            });

            socket.on("userStopTyping", data => {
                userTyping.value = userTyping.value.filter(u => u !== data.userId);
            });

            /*
            |--------------------------------------------------------------------------
            | History
            |--------------------------------------------------------------------------
            */

            // socket.on("messageHistory", data => {
            //     const room = data.room;
            //
            //     messagesByRoom.value[room] = (data.messages || []).map(msg => {
            //         const receivedType = serverMessageType(msg);
            //         const messageType = String(receivedType ?? "").toLowerCase();
            //         const messageId = msg.clientMessageId || msg.messageId || msg.id;
            //         rememberMessageId(room, messageId);
            //         const isSystem = ["system", "systemmessage"].includes(messageType) ||
            //             [msg.senderId, msg.senderInfo?.id, msg.senderInfo?.displayName]
            //                 .some(value => String(value || "").toLowerCase() === "system");
            //
            //         return {
            //             id: messageId || crypto.randomUUID(),
            //             user: isSystem ? "system" : (msg.senderInfo?.displayName || msg.senderId || "Unknown"),
            //             message: msg.content || msg.message || "",
            //             ...(receivedType !== undefined ? { type: receivedType } : {}),
            //             whoisData: msg.whoisData || null,
            //             isSystem,
            //             time: formatTime(msg.timestamp),
            //             class: normalizeClass(msg.senderInfo?.class ?? msg.senderInfo?.privClass ?? 0)
            //         };
            //     });
            //
            //     messagesByRoom.value[room]
            //         .filter(isSystemNotice)
            //         .forEach(msg => scheduleSystemNoticeDismissal(room, msg.id));
            //
            //     if (room === currentRoom.value) scroll();
            // });

            /*
            |--------------------------------------------------------------------------
            | Live Messages
            |--------------------------------------------------------------------------
            */

            socket.on("roomMessage", data => {
                const room = data.room;
                if (joiningRoom.value === room && data.senderId === "system" && ["Room not found", "You do not have permission to join this room"].includes(data.content)) {
                    clearTimeout(joinRoomTimer);
                    joiningRoom.value = null;
                    chatDialogError.value = data.content;
                    return;
                }
                const messageId = data.clientMessageId || data.messageId || data.id;

                if (
                    String(data.content || "").startsWith(
                        "Admin command failed:"
                    )
                ) {
                    adminBusy.value = false;
                    adminError.value = String(data.content);
                }

                if (!rememberMessageId(room, messageId)) return;

                if (!messagesByRoom.value[room]) {
                    messagesByRoom.value[room] = [];
                }

                const receivedType = serverMessageType(data);
                const messageType = String(receivedType ?? "").toLowerCase();
                const isSystem = ["system", "systemmessage"].includes(messageType) ||
                    [data.senderId, data.senderInfo?.id, data.senderInfo?.displayName]
                        .some(value => String(value || "").toLowerCase() === "system");

                const incomingMessage = {
                    id: messageId || crypto.randomUUID(),
                    user: isSystem
                        ? "system"
                        : (data.senderInfo?.displayName || data.senderInfo?.id || data.senderId || "Unknown"),
                    message: data.content || "",
                    // Preserve the exact server value so typed system messages
                    // are routed to the correct client destination.
                    ...(receivedType !== undefined ? { type: receivedType } : {}),
                    whoisData: data.whoisData || null,
                    rawData: data,
                    isSystem,
                    time: formatTime(data.timestamp),
                    class: normalizeClass(data.senderInfo?.class ?? data.senderInfo?.privClass ?? 0)
                };

                messagesByRoom.value[room].push(incomingMessage);

                const senderId = String(data.senderId || data.senderInfo?.id || "");
                const isOwnMessage = senderId === String(userId.value || discordIdentity.value?.id || username.value) ||
                    (!userId.value && data.senderInfo?.displayName === username.value);
                if (!isSystem && senderId && !isOwnMessage &&
                    rooms.value.some(item => item.name === room)) {
                    playMessageAlert();
                }
                if (room !== currentRoom.value && senderId !== String(userId.value || "")) {
                    const nextState = messageMentionsCurrentUser(data) ? "mention" : "unread";
                    roomActivity.value = {
                        ...roomActivity.value,
                        [room]: roomActivity.value[room] === "mention" ? "mention" : nextState
                    };
                }

                if (isSystemNotice(incomingMessage)) {
                    scheduleSystemNoticeDismissal(room, incomingMessage.id);
                }

                if (room === currentRoom.value) scroll();
            });

            /*
            |--------------------------------------------------------------------------
            | Members / Presence
            |--------------------------------------------------------------------------
            */

            function handleMembersUpdate(data) {
                console.log("[CHAT] Updated members:", data.room);

                if (!isCurrentMemberSnapshot(data.room, data.version ?? data.revision)) return;

                const room = rooms.value.find(rm => rm.name === data.room);

                if (!room) return;

                const memberList = (data.memberList || data.members || []).map(m => ({
                    ...m,
                    privilegeClass:
                        m.privilegeClass ||
                        memberRoomClass(room, m),
                    privilegeOrder:
                        Number(
                            m.privilegeOrder ??
                            roomPrivilegeClasses(room)[
                            m.privilegeClass ||
                            memberRoomClass(room, m)
                                ]?.order ??
                            0
                        )
                }));

                // Replace both the room object and the containing array. This
                // gives Vue new references and forces every dependent computed
                // value and v-for list to update immediately.
                rooms.value = rooms.value.map(existingRoom =>
                    existingRoom.name === data.room
                        ? { ...existingRoom, memberList }
                        : existingRoom
                );

                console.log("[CHAT] Room members:", memberList);
            }

            // Support either event name used by the chat server.
            socket.on("updateMembers", handleMembersUpdate);
            socket.on("membersUpdate", handleMembersUpdate);

            socket.on("usersStatus", data => {
                const statuses = data.statuses || [];

                // Merge presence/class updates into every room's member list rather
                // than keeping a disconnected, unused global list.
                statuses.forEach(update => {
                    rooms.value.forEach(room => {
                        const member = room.memberList.find(m => m.id === update.userId);

                        if (member) {
                            member.status = update.status;
                            if (update.class !== undefined || update.privClass !== undefined) {
                                member.class = normalizeClass(update.class ?? update.privClass);
                            }
                        }
                    });

                    if (update.userId === userId.value && (update.class !== undefined || update.privClass !== undefined)) {
                        myClass.value = normalizeClass(update.class ?? update.privClass);
                    }
                });
            });

            /*
            |--------------------------------------------------------------------------
            | Watch Party — server relay of playback state
            |--------------------------------------------------------------------------
            | Expects the server to broadcast this event to every member of the
            | room (including whoever issued the command) whenever a mediaControl
            | runCommand is processed, with shape:
            |   { room: <roomId>, action: 'load'|'play'|'pause'|'seek', url?, time?, userId? }
            */

            // Named events: { room?, data: ID|URL|{id|videoId|url,title?}|[...] }.
            // Command envelopes use the same shape plus command: playYoutube/AddQueue.
            socket.on("playYoutube", playYoutube);
            socket.on("AddQueue", AddQueue);
            socket.on("addQueue", AddQueue);
            socket.on("runCommand", payload => {
                if (["playyoutube", "addqueue"].includes(String(payload?.command).toLowerCase())) {
                    handleYoutubeCommand(payload.command, payload);
                }
            });

            socket.on("mediaControl", data => {
                if (!data || !data.room) return;
                applyRemoteMediaUpdate(data.room, data);
            });
        }

        function cleanupSocket() {
            if (!socket) return;

            try {
                socket.removeAllListeners();
                socket.io?.removeAllListeners();
                socket.disconnect();
            } catch (error) {
                console.error("Socket cleanup failed:", error);
            }

            socket = null;
        }

        function handleBrowserOffline() {
            if (!username.value) return;

            connected.value = false;
            connecting.value = false;
            connectionError.value = "You're offline. Reconnect when your network returns.";
            userTyping.value = [];
            clearTimeout(typingTimeout);
        }

        function handleBrowserOnline() {
            recoverConnection();
        }

        function recoverConnection() {
            // Keep the existing socket/session. Do not revive rejected logins,
            // server-initiated disconnects, or deliberate logouts.
            if (!username.value || navigator.onLine === false || !socket?.active || socket.connected) return;
            connectionError.value = "Reconnecting…";
            connecting.value = true;
            socket.connect();
        }

        function handlePageResume() {
            if (document.hidden) return;
            if (notificationAudio) unlockNotificationAudio();
            recoverConnection();
        }

        /*
        |--------------------------------------------------------------------------
        | Rooms
        |--------------------------------------------------------------------------
        */

        function joinRoom(roomId, isInitial = false) {
            if (!isSocketReady() || !roomId) return;
            if (!isInitial && isRoomJoined(roomId)) {
                switchRoom(roomId);
                showJoinDialog.value = false;
                return;
            }
            if (!isInitial) {
                if (joiningRoom.value) return;
                chatDialogError.value = "";
                joiningRoom.value = roomId;
                clearTimeout(joinRoomTimer);
                joinRoomTimer = setTimeout(() => {
                    joiningRoom.value = null;
                    chatDialogError.value = "Joining took too long. Please try again.";
                }, 10000);
            }
            socket.emit("joinRoom", { userId: userId.value, room: roomId });
        }

        function switchRoom(roomName) {
            if (!isSocketReady()) return;

            currentRoom.value = roomName;
            clearRoomActivity(roomName);

            if (!messagesByRoom.value[roomName]) {
                messagesByRoom.value[roomName] = [];
            }

            scroll();
        }

        function closeRoom(roomName) {
            if (isSocketReady()) {
                socket.emit("leaveRoom", {
                    userId: userId.value,
                    room: roomName
                });
            }

            delete youtubeByRoom.value[roomName];
            delete messagesByRoom.value[roomName];
            clearRoomActivity(roomName);
            rooms.value = rooms.value.filter(r => r.name !== roomName);

            if (currentRoom.value === roomName) {
                currentRoom.value = rooms.value.length ? rooms.value[0].name : null;
            }

            return true;
        }

        function isRoomJoined(roomId) {
            return rooms.value.some(r => r.name === roomId);
        }

        const filteredRooms = computed(() => matchingRooms(availableRooms.value, roomSearch.value));

        /*
        |--------------------------------------------------------------------------
        | Members Helpers
        |--------------------------------------------------------------------------
        */

        function fetchGroupedUsersForRoom(roomName) {
            const room = rooms.value.find(r => r.name === roomName);
            return room
                ? groupUsersByPrivilegeClass(
                    room,
                    room.memberList
                )
                : [];
        }

        function roomMemberCount(roomName) {
            const room = rooms.value.find(r => r.name === roomName);
            return room ? room.memberList.length : 0;
        }

        function isUserTyping(id) {
            return userTyping.value.includes(id);
        }

        /*
        |--------------------------------------------------------------------------
        | Sending Messages / Typing
        |--------------------------------------------------------------------------
        */

        function insertEmoji(emoji) {
            if (isBanned.value || !connected.value) return;
            const input = messageInput.value;
            if (!input) return;
            const start = input.selectionStart;
            const end = input.selectionEnd;
            message.value = message.value.slice(0, start) + emoji + message.value.slice(end);
            showEmojiPicker.value = false;
            nextTick(() => {
                input.focus();
                input.setSelectionRange(start + emoji.length, start + emoji.length);
            });
            sendTyping();
        }

        function messageImageFailed(url) {
            failedMessageImages.value = { ...failedMessageImages.value, [url]: true };
        }

        function addressUser(user) {
            if (isBanned.value || !connected.value) return;
            const prefix = `${memberLabel(user)}: `;
            if (!message.value.startsWith(prefix)) message.value = prefix + message.value;
            showMobileUsers.value = false;
            nextTick(() => {
                messageInput.value?.focus();
                const end = message.value.length;
                messageInput.value?.setSelectionRange(end, end);
            });
        }

        function toggleMultiline() {
            if (isBanned.value || !connected.value) return;
            const start = messageInput.value?.selectionStart ?? message.value.length;
            const end = messageInput.value?.selectionEnd ?? start;
            multiline.value = !multiline.value;
            if (!multiline.value) message.value = message.value.replace(/\r?\n/g, " ");
            nextTick(() => {
                messageInput.value?.focus();
                messageInput.value?.setSelectionRange(start, end);
            });
        }

        function formatMessage(marker) {
            const input = messageInput.value;
            if (!input || isBanned.value || !connected.value) return;
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const selected = message.value.slice(start, end);
            const replacement = marker + selected + marker;
            message.value = message.value.slice(0, start) + replacement + message.value.slice(end);
            nextTick(() => {
                input.focus();
                const selectionStart = start + marker.length;
                input.setSelectionRange(selectionStart, selectionStart + selected.length);
            });
            sendTyping();
        }

        function messageKeydown(event) {
            if (event.isComposing || event.keyCode === 229) return;
            if (event.key === "Enter" && (!multiline.value || event.shiftKey)) {
                event.preventDefault();
                sendMessage();
            }
        }

        function sendMessage() {
            const text = message.value.trim();

            if (!text || !currentRoom.value || isBanned.value) return;
            if (/^\/testyoutube(?:\s|$)/i.test(text)) {
                testYoutube(text.replace(/^\/testyoutube\s*/i, ""));
                message.value = "";
                return;
            }
            if (!isSocketReady()) return;

            socket.emit("runCommand", {
                command: "sendMessage",
                data: {
                    recipientId: currentRoom.value,
                    content: text,
                    userId: userId.value,
                    clientMessageId: crypto.randomUUID()
                }
            });

            message.value = "";
        }

        function sendTyping() {
            if (!isSocketReady() || !currentRoom.value || isBanned.value) return;

            socket.emit("runCommand", {
                command: "typing",
                data: {
                    recipientId: currentRoom.value,
                    userId: userId.value
                }
            });

            // Let the server know we stopped typing after a pause, so the
            // indicator doesn't get stuck on for other users.
            clearTimeout(typingTimeout);
            typingTimeout = setTimeout(() => {
                if (!isSocketReady() || !currentRoom.value) return;

                socket.emit("runCommand", {
                    command: "stopTyping",
                    data: {
                        recipientId: currentRoom.value,
                        userId: userId.value
                    }
                });
            }, 2000);
        }

        function scroll() {
            nextTick(() => {
                if (messageBox.value) {
                    messageBox.value.scrollTop = messageBox.value.scrollHeight;
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Mount
        |--------------------------------------------------------------------------
        */

        onMounted(() => {
            document.addEventListener("pointerdown", unlockNotificationAudio);
            document.addEventListener("keydown", unlockNotificationAudio);
            document.addEventListener("visibilitychange", handlePageResume);
            window.addEventListener("focus", handlePageResume);
            window.addEventListener("pageshow", handlePageResume);
            window.addEventListener("resize", closeMobileUsersOnDesktop);
            window.addEventListener("offline", handleBrowserOffline);
            window.addEventListener("online", handleBrowserOnline);
            window.addEventListener("message", handleDiscordAuthMessage);
            window.addEventListener("message", handleRoomManagerMessage);

            /*
             * Same-origin manager bridge. The popup uses this directly when
             * available; postMessage/BroadcastChannel remain fallbacks.
             */
            window.ixerioxChatManagerBridge = payload =>
                new Promise(resolve => {
                    processRoomManagerPayload(
                        payload,
                        resolve
                    );
                });

            if ("BroadcastChannel" in window) {
                manageChannel = new BroadcastChannel("ixeriox-chat-manager");
                manageChannel.addEventListener("message", handleRoomManagerBroadcast);
            }

            const rememberedUser = getRememberedChatUser();
            const savedName = localStorage.getItem(USERNAME_STORAGE_KEY);

            if (rememberedUser?.username) {
                username.value = String(rememberedUser.username);
                usernameInput.value = username.value;
                userId.value = rememberedUser.discordId;
                authMethod.value = rememberedUser.authMethod === "discord" ? "discord" : "guest";
                rememberedSessionToken.value = rememberedUser.sessionToken || null;

                if (authMethod.value === "discord") {
                    discordIdentity.value = {
                        id: rememberedUser.discordId || null,
                        username: username.value,
                        token: null
                    };
                }

                connectSocket();
            } else if (savedName) {
                usernameInput.value = savedName;
            }
        });

        onBeforeUnmount(() => {
            destroyYoutubePlayer();
            document.removeEventListener("pointerdown", unlockNotificationAudio);
            document.removeEventListener("keydown", unlockNotificationAudio);
            document.removeEventListener("visibilitychange", handlePageResume);
            window.removeEventListener("focus", handlePageResume);
            window.removeEventListener("pageshow", handlePageResume);
            notificationAudio?.close().catch(() => {});
            window.removeEventListener("resize", closeMobileUsersOnDesktop);
            window.removeEventListener("offline", handleBrowserOffline);
            window.removeEventListener("online", handleBrowserOnline);
            window.removeEventListener("message", handleDiscordAuthMessage);
            window.removeEventListener("message", handleRoomManagerMessage);
            delete window.ixerioxChatManagerBridge;
            manageChannel?.removeEventListener("message", handleRoomManagerBroadcast);
            manageChannel?.close();
            manageChannel = null;
            closeDiscordPopup();
            clearTimeout(typingTimeout);
            clearTimeout(joinRoomTimer);
            clearTimeout(createChatTimer);
            cleanupSocket();
        });

        return {
            session,
            userId,
            myClass,
            isBanned,

            username,
            usernameInput,
            login,
            loginWithDiscord,
            discordConnecting,
            authError,
            logout,

            connected,
            soundEnabled,
            toggleSound,
            connecting,
            connectionError,

            rooms,
            availableRooms,
            currentRoom,
            roomActivityClass,
            isProfileImageLoaded,
            markProfileImageLoaded,
            whoisRooms,
            joinWhoisRoom,
            currentRoomObj,
            currentRoomTopic,
            inARoom,
            showJoinDialog,
            chatDialog,
            chatDialogTab,
            openChatDialog,
            roomAccessLabel,
            chatDialogError,
            joiningRoom,
            createChatName,
            createChatId,
            createChatIdSuggestion,
            creatingChat,
            createChat,
            createChatTopic,
            createChatVisibility,
            showMobileUsers,
            mobileUsersDialog,
            roomSearch,
            filteredRooms,
            joinRoom,
            switchRoom,
            closeRoom,
            isRoomJoined,

            messages: visibleMessages,
            systemNotices,
            dismissSystemNotice,
            messageBox,
            messageInput,
            multiline,
            toggleMultiline,
            addressUser,
            formatMessage,
            messageKeydown,
            sendMessage,
            message,
            sendTyping,
            isUserTyping,
            messageParts,
            messageLinkHref,
            CHAT_EMOJI,
            showEmojiPicker,
            insertEmoji,
            failedMessageImages,
            messageImageFailed,
            styledMessageParts,
            formatWhoisDate,
            decodeProfileText,

            fetchGroupedUsersForRoom,
            roomMemberCount,
            classMeta,
            memberLabel,
            isRoomOwner,

            // Permissions
            CHAT_PERMISSION_LIST,
            showPermissionsPanel,
            adminBusy,
            adminError,
            newClassName,
            newClassOrder,
            canManageRoom,
            canAssignRoomClasses,
            currentPrivilegeClasses,
            openChatSettings,
            canManageUser,
            canAssignClass,
            createPrivilegeClass,
            renamePrivilegeClass,
            deletePrivilegeClass,
            isRequiredPrivilegeClass,
            setPrivilegeClassOrder,
            toggleClassPermission,
            setUserClass,
            unassignUserClass,
            setDefaultPrivilegeClass,
            isOwnerOfCurrentRoom,
            memberRoomClass,
            roomClassTier,
            // Watch party
            playYoutube,
            AddQueue,
            resetYoutubeIdle,
            youtubeState,
            youtubeDesktop,
            youtubeMobile,
            youtubeError,
            nextYoutube,
            closeYoutube,
            mountYoutubePlayer,
            mediaByRoom,
            partyMembership,
            mediaPanelExpanded,
            mediaUrlInput,
            videoRef,
            toggleWatchParty,
            loadMedia,
            onVideoPlay,
            onVideoPause,
            onVideoSeeked
        };
    }
}).mount("#app");
