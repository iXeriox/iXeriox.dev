const { createApp, ref, computed, watch, onMounted, onBeforeUnmount, nextTick } = Vue;

const SOCKET_URL = "https://api.infini9.net:3001";
const USERNAME_STORAGE_KEY = "ixeriox_username";
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

function memberRoomClass(room, member) {
    const memberId = String(member?.id || "");
    const ownerId = String(
        room?.owner ?? room?.raw?.owner ?? ""
    );

    if (memberId && memberId === ownerId) {
        return "Room Owner";
    }

    return (
        room?.raw?.userPrivilegeClasses?.[memberId] ??
        room?.userPrivilegeClasses?.[memberId] ??
        member?.privilegeClass ??
        member?.className ??
        room?.raw?.defaultPrivilegeClass ??
        room?.defaultPrivilegeClass ??
        "Unassigned"
    );
}

function roomClassTier(room, className) {
    if (className === "Room Owner") return "owner";

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
        const order = className === "Room Owner"
            ? Number.POSITIVE_INFINITY
            : Number(definition?.order || 0);

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
        const roomSearch = ref("");
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
            const text = String(value || "");
            const currentName = username.value.trim();
            if (!currentName) return [{ text, mention: false }];

            const escapedName = currentName.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
            return text
                .split(new RegExp(`(${escapedName})`, "gi"))
                .filter(part => part.length > 0)
                .map(part => ({
                    text: part,
                    mention: part.toLowerCase() === currentName.toLowerCase()
                }));
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

        let socket = null;
        let typingTimeout = null;
        let discordPopup = null;
        let discordPopupTimer = null;
        let manageChannel = null;
        let reconnectRoomIds = new Set();
        let reconnectTargetRoom = null;

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
                message => event.source?.postMessage(message, event.origin)
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
                message => manageChannel?.postMessage(message)
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

            if (
                String(user.id) === String(userId.value) ||
                memberRoomClass(
                    currentRoomObj.value,
                    user
                ) === "Room Owner" ||
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

            socket.on("rooms", data => {
                availableRooms.value = Array.isArray(data) ? data : (data.rooms || []);
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
                        manageChannel.postMessage({
                            type: "ixeriox:manage-state",
                            room: updatedRoom.name,
                            state: {
                                room: updatedRoom.name,
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
                        });
                    }
                }
            );

            socket.on("joinRoom", data => {
                console.log("[CHAT] Joined room:", data.room);

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
                        roomName: data.room.roomName || known?.roomName || roomId,
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
                            privilegeClass:
                                m.privilegeClass ||
                                (
                                    String(m.id) ===
                                    String(data.room.owner)
                                        ? "Room Owner"
                                        : null
                                )
                        }))
                    });
                } else {
                    const room = rooms.value.find(r => r.name === roomId);
                    room.roomName = data.room.roomName || known?.roomName || room.roomName || roomId;
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
            if (!username.value) return;

            connectionError.value = "Network restored. Reconnecting…";
            connecting.value = true;
            connectSocket();
        }

        /*
        |--------------------------------------------------------------------------
        | Rooms
        |--------------------------------------------------------------------------
        */

        function joinRoom(roomId, isInitial = false) {
            if (!isSocketReady() || !roomId) return;

            socket.emit("joinRoom", {
                userId: userId.value,
                room: roomId
            });

            if (!isInitial) {
                showJoinDialog.value = false;
            }
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

        const filteredRooms = computed(() => {
            const search = (roomSearch.value || "").toLowerCase();

            return availableRooms.value.filter(
                room => room.roomName && room.roomName.toLowerCase().includes(search)
            );
        });

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

        function sendMessage() {
            const text = message.value.trim();

            if (!text || !isSocketReady() || !currentRoom.value || isBanned.value) return;

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
            sendMessage,
            message,
            sendTyping,
            isUserTyping,
            messageParts,
            formatWhoisDate,
            decodeProfileText,

            fetchGroupedUsersForRoom,
            roomMemberCount,
            classMeta,
            memberLabel,

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
            setPrivilegeClassOrder,
            toggleClassPermission,
            setUserClass,
            unassignUserClass,
            setDefaultPrivilegeClass,
            isOwnerOfCurrentRoom,
            memberRoomClass,
            roomClassTier,
            // Watch party
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
