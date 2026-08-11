<?php
declare(strict_types=1);

$room = trim((string)($_GET['room'] ?? ''));

if ($room === '' || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $room)) {
    http_response_code(400);
    $room = '';
}

$encodedRoom = json_encode(
    $room,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $room ? htmlspecialchars($room, ENT_QUOTES, 'UTF-8') . ' settings' : 'Invalid room' ?> | iXeriox.dev</title>
    <style>
        :root{color-scheme:dark;--bg:#06080d;--panel:#0d121b;--panel-2:#111927;--border:#1d2a38;--text:#edf6f7;--muted:#8995a8;--cyan:#36e0d0;--cyan-2:#72f5e9;--amber:#ffad55;--red:#ff6262;--green:#65dc91;--purple:#a37bff;--mono:ui-monospace,SFMono-Regular,Consolas,monospace}
        *{box-sizing:border-box}
        html,body{min-height:100%;margin:0}
        body{background:radial-gradient(circle at 10% 0,rgba(54,224,208,.11),transparent 30%),radial-gradient(circle at 100% 100%,rgba(163,123,255,.08),transparent 32%),var(--bg);color:var(--text);font-family:Inter,"Segoe UI",system-ui,sans-serif}
        button,input,textarea,select{font:inherit}
        button{cursor:pointer}
        .shell{width:min(1180px,100%);margin:auto;padding:28px}
        .topbar{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:24px}
        .brand{color:var(--text);font:800 18px var(--mono);text-decoration:none}.brand span{color:var(--cyan)}
        .close{padding:10px 14px;border:1px solid var(--border);border-radius:10px;background:rgba(255,255,255,.025);color:var(--muted)}
        .close:hover{border-color:var(--cyan);color:var(--text)}
        .hero{position:relative;overflow:hidden;padding:28px;margin-bottom:18px;border:1px solid rgba(255,255,255,.09);border-radius:20px;background:linear-gradient(120deg,rgba(54,224,208,.08),rgba(255,173,85,.035)),var(--panel);box-shadow:0 24px 70px rgba(0,0,0,.36)}
        .hero::before{content:"";position:absolute;inset:0 0 auto;height:3px;background:linear-gradient(90deg,var(--cyan),var(--amber))}
        .eyebrow{margin:0 0 8px;color:var(--cyan);font:800 11px var(--mono);letter-spacing:.13em}
        h1{margin:0 0 8px;font-size:clamp(25px,4vw,38px);letter-spacing:-.04em}
        .hero p{margin:0;color:var(--muted);line-height:1.6}
        .connection{display:inline-flex;align-items:center;gap:7px;margin-top:18px;padding:6px 10px;border:1px solid var(--border);border-radius:999px;color:var(--muted);font:700 10px var(--mono)}
        .connection::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--amber);box-shadow:0 0 10px var(--amber)}
        .connection.ready{color:var(--green);border-color:rgba(101,220,145,.25)}.connection.ready::before{background:var(--green);box-shadow:0 0 10px var(--green)}
        .connection.error{color:var(--red);border-color:rgba(255,98,98,.3)}.connection.error::before{background:var(--red);box-shadow:0 0 10px var(--red)}
        .standalone-warning{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:18px;padding:17px 18px;border:1px solid rgba(255,98,98,.28);border-radius:14px;background:rgba(255,98,98,.07)}
        .standalone-warning[hidden]{display:none}.standalone-warning strong{display:block;margin-bottom:5px;color:var(--red);font:800 12px var(--mono);letter-spacing:.04em;text-transform:uppercase}.standalone-warning p{margin:0;color:var(--muted);font-size:12px;line-height:1.6}
        .standalone-warning a{flex-shrink:0;padding:10px 13px;border:1px solid rgba(54,224,208,.28);border-radius:9px;background:rgba(54,224,208,.08);color:var(--cyan);font:800 11px var(--mono);text-decoration:none}
        .workspace{display:grid;grid-template-columns:230px minmax(0,1fr);gap:18px}
        .tabs{align-self:start;position:sticky;top:18px;padding:10px;border:1px solid var(--border);border-radius:16px;background:rgba(13,18,27,.9)}
        .tab{display:flex;align-items:center;gap:10px;width:100%;padding:12px;border:0;border-radius:10px;background:transparent;color:var(--muted);text-align:left;font:700 12px var(--mono)}
        .tab:hover{background:rgba(255,255,255,.035);color:var(--text)}.tab.active{background:rgba(54,224,208,.1);color:var(--cyan)}
        .tab-icon{display:grid;place-items:center;width:25px;height:25px;border-radius:7px;background:rgba(255,255,255,.045)}
        .panel{padding:clamp(20px,4vw,32px);border:1px solid var(--border);border-radius:18px;background:rgba(13,18,27,.94);box-shadow:0 20px 55px rgba(0,0,0,.25)}
        .panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:26px;padding-bottom:20px;border-bottom:1px solid var(--border)}
        .panel h2{margin:0 0 6px;font-size:21px}.panel-head p{margin:0;color:var(--muted);font-size:13px;line-height:1.55}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
        .field{display:flex;flex-direction:column;gap:8px}.field.full{grid-column:1/-1}
        label,.label{color:var(--text);font:700 11px var(--mono);letter-spacing:.05em;text-transform:uppercase}
        input,textarea,select{width:100%;min-height:48px;padding:12px 14px;border:1px solid rgba(255,255,255,.1);border-radius:11px;outline:0;background:#080c13;color:var(--text)}
        textarea{min-height:120px;resize:vertical;line-height:1.6}
        input:focus,textarea:focus,select:focus{border-color:var(--cyan);box-shadow:0 0 0 4px rgba(54,224,208,.09)}
        .hint{margin:0;color:var(--muted);font-size:11px;line-height:1.5}
        .actions{display:flex;justify-content:flex-end;gap:10px;margin-top:24px;padding-top:20px;border-top:1px solid var(--border)}
        .primary,.secondary,.danger{min-height:42px;padding:0 16px;border-radius:10px;font-weight:800}
        .primary{border:0;background:linear-gradient(100deg,var(--cyan),#73f0c5);color:#061312}.primary:hover{filter:brightness(1.08);transform:translateY(-1px)}
        .secondary{border:1px solid var(--border);background:#111824;color:var(--text)}.secondary:hover{border-color:var(--cyan)}
        .danger{border:1px solid rgba(255,98,98,.3);background:rgba(255,98,98,.08);color:var(--red)}
        .member-list{display:flex;flex-direction:column;gap:9px}
        .member{display:grid;grid-template-columns:minmax(0,1fr) 150px auto;align-items:center;gap:12px;padding:12px;border:1px solid rgba(255,255,255,.07);border-radius:12px;background:rgba(255,255,255,.018)}
        .member-info{display:flex;align-items:center;min-width:0;gap:10px}.avatar{display:grid;place-items:center;flex:0 0 36px;width:36px;height:36px;border-radius:10px;background:rgba(54,224,208,.1);color:var(--cyan);font:800 13px var(--mono)}
        .member-name{overflow:hidden;font:700 13px var(--mono);text-overflow:ellipsis;white-space:nowrap}.member-id{display:block;margin-top:3px;color:var(--muted);font:10px var(--mono)}
        .member select{min-height:38px;padding:7px 9px;font-size:12px}
        .class-list{display:flex;flex-direction:column;gap:12px;margin-top:20px}
        .class-card{padding:16px;border:1px solid rgba(255,255,255,.08);border-radius:13px;background:rgba(255,255,255,.018)}
        .class-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:13px}.class-title{display:flex;align-items:center;gap:8px}.class-title strong{font:800 14px var(--mono)}.class-order{padding:3px 7px;border:1px solid var(--border);border-radius:999px;color:var(--muted);font:700 9px var(--mono)}
        .class-actions{display:flex;flex-wrap:wrap;gap:7px}.class-actions button{padding:7px 9px;border:1px solid var(--border);border-radius:8px;background:#111824;color:var(--text);font-size:10px}.class-actions button.danger{color:var(--red)}
        .permission-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}.permission-item{display:flex;align-items:center;gap:8px;padding:7px 8px;border-radius:8px;background:rgba(255,255,255,.025);color:var(--muted);font:10px var(--mono);text-transform:none;letter-spacing:0}.permission-item input{width:17px;min-height:17px}
        .default-badge{padding:3px 7px;border:1px solid rgba(54,224,208,.3);border-radius:999px;background:rgba(54,224,208,.08);color:var(--cyan);font:800 9px var(--mono)}
        .icon-btn{display:grid;place-items:center;width:38px;height:38px;border:1px solid var(--border);border-radius:9px;background:#111824;color:var(--muted)}.icon-btn:hover{border-color:var(--red);color:var(--red)}
        .empty{padding:45px 20px;border:1px dashed var(--border);border-radius:13px;color:var(--muted);text-align:center}
        .toggle-row{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:15px 0;border-bottom:1px solid rgba(255,255,255,.055)}.toggle-row:last-child{border:0}.toggle-row strong{display:block;font-size:13px}.toggle-row small{display:block;margin-top:4px;color:var(--muted);line-height:1.5}
        .switch{position:relative;flex:0 0 44px;width:44px;height:24px}.switch input{position:absolute;opacity:0}.switch span{position:absolute;inset:0;border:1px solid var(--border);border-radius:999px;background:#080c13}.switch span::after{content:"";position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:var(--muted);transition:.2s}.switch input:checked+span{border-color:var(--cyan);background:rgba(54,224,208,.15)}.switch input:checked+span::after{left:23px;background:var(--cyan)}
        .danger-card{padding:18px;border:1px solid rgba(255,98,98,.23);border-radius:13px;background:rgba(255,98,98,.045)}.danger-card+.danger-card{margin-top:12px}.danger-card h3{margin:0 0 5px;font-size:14px}.danger-card p{margin:0 0 15px;color:var(--muted);font-size:12px;line-height:1.55}
        .toast{position:fixed;right:20px;bottom:20px;max-width:360px;padding:13px 16px;border:1px solid rgba(54,224,208,.25);border-radius:11px;background:#101923;color:var(--text);box-shadow:0 18px 55px rgba(0,0,0,.5);font-size:13px}
        .invalid{display:grid;place-items:center;min-height:70vh;text-align:center}.invalid h1{color:var(--red)}
        .gate{position:fixed;inset:0;z-index:100;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 20% 10%,rgba(54,224,208,.14),transparent 34%),radial-gradient(circle at 85% 85%,rgba(163,123,255,.1),transparent 32%),var(--bg)}
        .gate[hidden]{display:none}.gate-card{position:relative;width:min(460px,100%);padding:38px;overflow:hidden;border:1px solid rgba(255,255,255,.1);border-radius:22px;background:linear-gradient(145deg,rgba(255,255,255,.04),transparent 40%),var(--panel);box-shadow:0 30px 85px rgba(0,0,0,.6);text-align:center}.gate-card::before{content:"";position:absolute;inset:0 0 auto;height:3px;background:linear-gradient(90deg,var(--cyan),var(--amber))}
        .loader{width:54px;height:54px;margin:0 auto 22px;border:3px solid rgba(54,224,208,.15);border-top-color:var(--cyan);border-radius:50%;animation:spin .85s linear infinite}.gate-icon{display:grid;place-items:center;width:58px;height:58px;margin:0 auto 22px;border:1px solid rgba(255,98,98,.3);border-radius:17px;background:rgba(255,98,98,.08);color:var(--red);font-size:27px;font-weight:900}.gate h2{margin:0 0 10px;font-size:24px}.gate p{margin:0;color:var(--muted);font-size:13px;line-height:1.65}.gate-actions{display:flex;justify-content:center;gap:10px;margin-top:24px}.gate-actions a,.gate-actions button{padding:11px 15px;border:1px solid var(--border);border-radius:10px;background:#111824;color:var(--text);font-weight:800;text-decoration:none}.gate-actions a{border-color:rgba(54,224,208,.25);color:var(--cyan)}
        @keyframes spin{to{transform:rotate(360deg)}}
        @media(max-width:760px){.shell{padding:16px}.workspace{grid-template-columns:1fr}.tabs{position:static;display:flex;overflow:auto}.tab{width:auto;white-space:nowrap}.tab span:last-child{display:none}.grid{grid-template-columns:1fr}.field.full{grid-column:1}.member{grid-template-columns:minmax(0,1fr) 120px}.member .icon-btn{display:none}.permission-grid{grid-template-columns:1fr}.class-head{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<?php if ($room === ''): ?>
    <main class="invalid">
        <div><p class="eyebrow">INVALID REQUEST</p><h1>Room not found</h1><p>Open settings from inside an active chat room.</p></div>
    </main>
<?php else: ?>
    <section class="gate" data-loading>
        <div class="gate-card">
            <div class="loader" aria-hidden="true"></div>
            <p class="eyebrow">VERIFYING ROOM ACCESS</p>
            <h2>Loading chat settings</h2>
            <p>Checking your remembered Discord identity against this room…</p>
        </div>
    </section>

    <section class="gate" data-denied hidden>
        <div class="gate-card">
            <div class="gate-icon" aria-hidden="true">!</div>
            <p class="eyebrow" style="color:var(--red)">ACCESS REFUSED</p>
            <h2 data-denied-title>Unable to manage room</h2>
            <p data-denied-message>You do not have access to this room.</p>
            <div class="gate-actions">
<button class="close" type="button" data-close-window>Close</button>
                <button type="button" data-retry>Try again</button>
            </div>
        </div>
    </section>

    <main class="shell" id="manager" hidden>
        <header class="topbar">
            <a class="brand" href="/chat/">iXeriox<span>.dev/chat</span></a>
            <button class="close" type="button" data-close-window>Close</button>
        </header>

        <section class="hero">
            <p class="eyebrow">ROOM MANAGEMENT</p>
            <h1>#<span data-room-title><?= htmlspecialchars($room, ENT_QUOTES, 'UTF-8') ?></span></h1>
            <p>Configure the room, manage access, and keep the conversation healthy.</p>
            <span class="connection" data-connection>Connecting to chat…</span>
        </section>

        <aside class="standalone-warning" data-standalone hidden role="alert">
            <div>
                <strong>No active chat connection</strong>
                <p>This manager must be opened using the settings cog inside a connected chat room. A directly opened page cannot manage the room.</p>
            </div>
            <a href="/chat/">Open chat</a>
        </aside>

        <div class="workspace">
            <nav class="tabs" aria-label="Room settings">
                <button class="tab active" data-tab="overview"><span class="tab-icon">#</span><span>Overview</span></button>
                <button class="tab" data-tab="permissions"><span class="tab-icon">◇</span><span>Permissions</span></button>
                <button class="tab" data-tab="moderation"><span class="tab-icon">!</span><span>Moderation</span></button>
                <button class="tab" data-tab="appearance"><span class="tab-icon">◐</span><span>Appearance</span></button>
                <button class="tab" data-tab="danger"><span class="tab-icon">×</span><span>Danger zone</span></button>
            </nav>

            <section class="panel" data-panel="overview">
                <div class="panel-head"><div><h2>Overview</h2><p>The essentials people see when they join this room.</p></div></div>
                <div class="grid">
                    <div class="field"><label for="room-name">Display name</label><input id="room-name" maxlength="64"><p class="hint">Changing this does not alter the room’s internal ID.</p></div>
                    <div class="field"><label for="room-type">Room visibility</label><select id="room-type"><option value="public">Public</option><option value="private">Private</option><option value="unlisted">Unlisted</option></select></div>
                    <div class="field full"><label for="room-topic">Topic</label><textarea id="room-topic" maxlength="500" placeholder="What is this room for?"></textarea><p class="hint"><span data-topic-count>0</span>/500 characters</p></div>
                </div>
                <div class="actions"><button class="primary" type="button" data-save-overview>Save changes</button></div>
            </section>

            <section class="panel" data-panel="permissions" hidden>
                <div class="panel-head"><div><h2>Privilege classes</h2><p>Create room-specific classes, order them, and assign fixed chat permissions.</p></div><button class="secondary" type="button" data-refresh>Refresh</button></div>
                <div class="grid" style="margin-bottom:22px">
                    <div class="field"><label for="new-class-name">Class name</label><input id="new-class-name" maxlength="64" placeholder="e.g. Race Marshals"></div>
                    <div class="field"><label for="new-class-order">Order</label><input id="new-class-order" type="number" value="10"></div>
                </div>
                <div class="actions" style="margin-top:0;padding-top:0;border-top:0"><button class="primary" type="button" data-add-class>Add class</button></div>
                <div class="class-list" data-classes><div class="empty">Waiting for privilege classes...</div></div>
                <div class="panel-head" style="margin-top:28px"><div><h2>Member assignments</h2><p>Assign members to one of this room's custom classes.</p></div></div>
                <div class="member-list" data-members><div class="empty">Waiting for room members…</div></div>
            </section>

            <section class="panel" data-panel="moderation" hidden>
                <div class="panel-head"><div><h2>Moderation</h2><p>Control how messages and new members are handled.</p></div></div>
                <div class="toggle-row"><div><strong>Slow mode</strong><small>Limit how quickly members can send messages.</small></div><select id="slow-mode" style="width:150px"><option value="0">Off</option><option value="5">5 seconds</option><option value="10">10 seconds</option><option value="30">30 seconds</option><option value="60">1 minute</option></select></div>
                <div class="toggle-row"><div><strong>Links</strong><small>Allow members to include links in messages.</small></div><label class="switch"><input id="allow-links" type="checkbox" checked><span></span></label></div>
                <div class="toggle-row"><div><strong>Guest messages</strong><small>Allow unauthenticated guests to speak.</small></div><label class="switch"><input id="guest-messages" type="checkbox" checked><span></span></label></div>
                <div class="actions"><button class="primary" type="button" data-save-moderation>Save moderation</button></div>
            </section>

            <section class="panel" data-panel="appearance" hidden>
                <div class="panel-head"><div><h2>Appearance</h2><p>Give this room a recognisable accent and welcome message.</p></div></div>
                <div class="grid">
                    <div class="field"><label for="accent">Accent colour</label><input id="accent" type="color" value="#36e0d0" style="padding:7px"></div>
                    <div class="field"><label for="room-icon">Room icon</label><input id="room-icon" maxlength="4" placeholder="#" value="#"></div>
                    <div class="field full"><label for="welcome">Welcome message</label><textarea id="welcome" maxlength="300" placeholder="Shown when someone joins the room."></textarea></div>
                </div>
                <div class="actions"><button class="primary" type="button" data-save-appearance>Save appearance</button></div>
            </section>

            <section class="panel" data-panel="danger" hidden>
                <div class="panel-head"><div><h2>Danger zone</h2><p>These actions affect everyone in the room.</p></div></div>
                <div class="danger-card"><h3>Clear message history</h3><p>Permanently remove the room’s existing messages.</p><button class="danger" type="button" data-clear>Clear history</button></div>
                <div class="danger-card"><h3>Delete room</h3><p>Permanently delete this room and disconnect its members.</p><button class="danger" type="button" data-delete>Delete room</button></div>
            </section>
        </div>
    </main>
    <div class="toast" data-toast hidden></div>
    <script>
        (() => {
            const room = <?= $encodedRoom ?>;
            const requestedParentOrigin =
                new URLSearchParams(window.location.search)
                    .get("parentOrigin");
            const referrerOrigin = (() => {
                try {
                    return document.referrer
                        ? new URL(document.referrer).origin
                        : null;
                } catch {
                    return null;
                }
            })();
            const parentOrigin =
                requestedParentOrigin ||
                referrerOrigin ||
                window.location.origin;
            const origin = window.location.origin;
            const API_URL = "https://api.infini9.net/updateChat/";
            const CHAT_DATA_URL = `https://api.infini9.net/getChatData/${encodeURIComponent(room)}`;
            const CHAT_PERMISSIONS = [
                "chat.view",
                "chat.join",
                "chat.send",
                "chat.invite",
                "chat.kick",
                "chat.ban",
                "chat.unban",
                "chat.title",
                "chat.topic",
                "chat.settings",
                "chat.privilegeClasses.manage",
                "chat.privilegeClasses.assign",
                "chat.owner.transfer",
                "chat.delete"
            ];
            let state = {
                room,
                roomName: room,
                topic: "",
                members: [],
                settings: {},
                privilegeClasses: {},
                userPrivilegeClasses: {},
                defaultPrivilegeClass: null,
                permissions: {}
            };
            let managerConnected = false;
            let connectionTimer = null;
            let handshakeTimer = null;
            let handshakeAttempts = 0;
            const manageChannel = "BroadcastChannel" in window
                ? new BroadcastChannel("ixeriox-chat-manager")
                : null;
            const $ = selector => document.querySelector(selector);
            const $$ = selector => [...document.querySelectorAll(selector)];

            function notify(message) {
                const toast = $("[data-toast]");
                toast.textContent = message;
                toast.hidden = false;
                clearTimeout(notify.timer);
                notify.timer = setTimeout(() => { toast.hidden = true; }, 3200);
            }

            async function callParentBridge(payload) {
                if (!window.opener || window.opener.closed) {
                    return false;
                }

                try {
                    const bridge =
                        window.opener
                            .ixerioxChatManagerBridge;

                    if (typeof bridge !== "function") {
                        return false;
                    }

                    const response = await bridge(payload);
                    if (response) {
                        receiveManagerMessage(response);
                    }
                    return true;
                } catch {
                    return false;
                }
            }

            function getRememberedUser() {
                const match = document.cookie
                    .split("; ")
                    .find(item => item.startsWith("ixeriox_chat_user="));

                if (!match) return null;

                try {
                    return JSON.parse(decodeURIComponent(match.split("=").slice(1).join("=")));
                } catch {
                    return null;
                }
            }

            async function apiRequest(action, data = {}) {
                const rememberedUser = getRememberedUser();
                const headers = { "Content-Type": "application/json" };

                if (rememberedUser?.sessionToken) {
                    headers.Authorization = `Bearer ${rememberedUser.sessionToken}`;
                }

                const response = await fetch(API_URL, {
                    method: "POST",
                    credentials: "include",
                    headers,
                    body: JSON.stringify({ action, room, data })
                });

                let result = {};
                try {
                    result = await response.json();
                } catch {
                    throw new Error("The chat API returned an invalid response.");
                }

                if (!response.ok || result.success === false) {
                    throw new Error(result.error || result.message || `Chat API error (${response.status})`);
                }

                return result;
            }

            function send(command, data = {}) {
                if (!managerConnected) {
                    notify("The connected chat session is unavailable.");
                    return;
                }

                const payload = {
                    type: "ixeriox:manage-command",
                    room,
                    command,
                    data
                };

                callParentBridge(payload).then(handled => {
                    if (handled) return;

                    manageChannel?.postMessage(payload);

                    if (
                        window.opener &&
                        !window.opener.closed
                    ) {
                        window.opener.postMessage(
                            payload,
                            parentOrigin
                        );
                    }
                });

                notify("Update sent to the chat server.");
            }

            function populate(next) {
                managerConnected = true;
                clearTimeout(connectionTimer);
                clearInterval(handshakeTimer);
                $("[data-loading]").hidden = true;
                $("[data-denied]").hidden = true;
                $("#manager").hidden = false;
                $("[data-standalone]").hidden = true;
                state = { ...state, ...next };
                $("[data-connection]").textContent = "Connected to chat API";
                $("[data-connection]").classList.remove("error");
                $("[data-connection]").classList.add("ready");
                $("[data-room-title]").textContent = state.roomName || room;
                $("#room-name").value = state.roomName || room;
                $("#room-topic").value = state.topic || state.settings?.topic || "";
                $("#room-type").value = state.settings?.visibility || "public";
                $("#slow-mode").value = String(state.settings?.slowMode || 0);
                $("#allow-links").checked = state.settings?.allowLinks !== false;
                $("#guest-messages").checked = state.settings?.guestMessages !== false;
                $("#accent").value = state.settings?.accent || "#36e0d0";
                $("#room-icon").value = state.settings?.icon || "#";
                $("#welcome").value = state.settings?.welcome || "";
                $("[data-topic-count]").textContent = $("#room-topic").value.length;
                renderClasses();
                renderMembers();
            }

            function showConnectionWarning() {
                if (managerConnected) return;
                refuseAccess(
                    "Unable to contact the active chat",
                    "Open this manager from the settings cog in the room you are currently viewing. Also ensure the updated app.js is loaded without an old browser cache."
                );
            }

            function refuseAccess(title, message) {
                managerConnected = false;
                $("#manager").hidden = true;
                $("[data-loading]").hidden = true;
                $("[data-denied-title]").textContent = title;
                $("[data-denied-message]").textContent = message;
                $("[data-denied]").hidden = false;
            }

            function renderMembers() {
                const target = $("[data-members]");
                const members = Array.isArray(state.members) ? state.members : [];
                if (!members.length) {
                    target.innerHTML = '<div class="empty">No members were returned for this room.</div>';
                    return;
                }
                target.replaceChildren(...members.map(member => {
                    const row = document.createElement("div");
                    row.className = "member";
                    const info = document.createElement("div");
                    info.className = "member-info";
                    const avatar = document.createElement("span");
                    avatar.className = "avatar";
                    avatar.textContent = String(member.displayName || member.id || "?").slice(0, 1).toUpperCase();
                    const text = document.createElement("span");
                    const name = document.createElement("span");
                    name.className = "member-name";
                    name.textContent = member.displayName || member.id || "Unknown";
                    const id = document.createElement("small");
                    id.className = "member-id";
                    id.textContent = member.id || "";
                    text.append(name, id);
                    info.append(avatar, text);
                    const select = document.createElement("select");
                    const assignedClass =
                        state.userPrivilegeClasses?.[member.id] ||
                        member.privilegeClass ||
                        state.defaultPrivilegeClass ||
                        "";
                    Object.entries(state.privilegeClasses || {})
                        .sort(([, a], [, b]) =>
                            Number(b.order || 0) -
                            Number(a.order || 0)
                        )
                        .forEach(([className, definition]) => {
                            const option = new Option(
                                `${className} (${definition.order})`,
                                className,
                                false,
                                assignedClass === className
                            );
                            select.add(option);
                        });
                    select.disabled =
                        member.isStaff === true ||
                        member.isOwner === true;
                    select.addEventListener("change", () =>
                        send("adminCommand", {
                            arguments:
                                `assign ${member.id} | ${select.value}`
                        })
                    );
                    const kick = document.createElement("button");
                    kick.className = "icon-btn";
                    kick.type = "button";
                    kick.title = "Remove member";
                    kick.textContent = "×";
                    kick.addEventListener("click", () =>
                        confirm(`Remove ${name.textContent} from this room?`) &&
                        send("chatCommand", {
                            content: `/kick ${member.id}`
                        })
                    );
                    row.append(info, select, kick);
                    return row;
                }));
            }

            function renderClasses() {
                const target = $("[data-classes]");
                const classes = Object.entries(
                    state.privilegeClasses || {}
                ).sort(([, first], [, second]) =>
                    Number(second.order || 0) -
                    Number(first.order || 0)
                );

                if (!classes.length) {
                    target.innerHTML =
                        '<div class="empty">No privilege classes were returned.</div>';
                    return;
                }

                target.replaceChildren(
                    ...classes.map(([className, definition]) => {
                        const card = document.createElement("article");
                        card.className = "class-card";

                        const head = document.createElement("div");
                        head.className = "class-head";
                        const title = document.createElement("div");
                        title.className = "class-title";
                        const strong = document.createElement("strong");
                        strong.textContent = className;
                        const order = document.createElement("span");
                        order.className = "class-order";
                        order.textContent = `Order ${definition.order}`;
                        title.append(strong, order);

                        if (state.defaultPrivilegeClass === className) {
                            const badge = document.createElement("span");
                            badge.className = "default-badge";
                            badge.textContent = "Default";
                            title.append(badge);
                        }

                        const actions = document.createElement("div");
                        actions.className = "class-actions";

                        const makeButton = (label, handler, danger = false) => {
                            const button = document.createElement("button");
                            button.type = "button";
                            button.textContent = label;
                            if (danger) button.className = "danger";
                            button.addEventListener("click", handler);
                            return button;
                        };

                        actions.append(
                            makeButton("Default", () =>
                                send("adminCommand", {
                                    arguments: `default ${className}`
                                })
                            ),
                            makeButton("Rename", () => {
                                const nextName = prompt(
                                    `Rename "${className}" to:`,
                                    className
                                )?.trim();
                                if (nextName && nextName !== className) {
                                    send("adminCommand", {
                                        arguments:
                                            `class rename ${className} | ${nextName}`
                                    });
                                }
                            }),
                            makeButton("Order", () => {
                                const nextOrder = prompt(
                                    `New order for "${className}":`,
                                    String(definition.order)
                                );
                                if (nextOrder !== null && Number.isFinite(Number(nextOrder))) {
                                    send("adminCommand", {
                                        arguments:
                                            `class order ${className} | ${Math.trunc(Number(nextOrder))}`
                                    });
                                }
                            }),
                            makeButton("Delete", () => {
                                if (confirm(`Delete "${className}"?`)) {
                                    send("adminCommand", {
                                        arguments:
                                            `class delete ${className}`
                                    });
                                }
                            }, true)
                        );

                        head.append(title, actions);

                        const grid = document.createElement("div");
                        grid.className = "permission-grid";

                        for (const permission of CHAT_PERMISSIONS) {
                            const label = document.createElement("label");
                            label.className = "permission-item";
                            const checkbox = document.createElement("input");
                            checkbox.type = "checkbox";
                            checkbox.checked = Array.isArray(
                                definition.permissions
                            ) && definition.permissions.includes(permission);
                            checkbox.addEventListener("change", () =>
                                send("adminCommand", {
                                    arguments:
                                        `permission ${checkbox.checked ? "add" : "remove"} ` +
                                        `${className} | ${permission}`
                                })
                            );
                            const text = document.createElement("span");
                            text.textContent = permission;
                            label.append(checkbox, text);
                            grid.append(label);
                        }

                        card.append(head, grid);
                        return card;
                    })
                );
            }

            $$("[data-tab]").forEach(tab => tab.addEventListener("click", () => {
                $$("[data-tab]").forEach(item => item.classList.toggle("active", item === tab));
                $$("[data-panel]").forEach(panel => { panel.hidden = panel.dataset.panel !== tab.dataset.tab; });
            }));
            $("#room-topic").addEventListener("input", event => { $("[data-topic-count]").textContent = event.target.value.length; });
            $("[data-close-window]").addEventListener("click", () => window.close());
            $("[data-retry]").addEventListener("click", () => {
                $("[data-denied]").hidden = true;
                $("[data-loading]").hidden = false;
                loadRoomSettings();
            });
            $("[data-refresh]").addEventListener("click", async () => {
                managerConnected = false;
                requestChatConnection();
                notify("Refreshing room data...");
            });
            $("[data-add-class]").addEventListener("click", () => {
                const className = $("#new-class-name").value.trim();
                const order = Number($("#new-class-order").value);

                if (!className || !Number.isFinite(order)) {
                    notify("Enter a class name and numeric order.");
                    return;
                }

                send("adminCommand", {
                    arguments:
                        `class add ${className} | ${Math.trunc(order)}`
                });
                $("#new-class-name").value = "";
            });
            $("[data-save-overview]").addEventListener("click", () => {
                send("updateRoomSettings", { roomName: $("#room-name").value.trim(), topic: $("#room-topic").value.trim(), visibility: $("#room-type").value });
                notify("Overview update sent.");
            });
            $("[data-save-moderation]").addEventListener("click", () => {
                send("updateRoomSettings", { slowMode: Number($("#slow-mode").value), allowLinks: $("#allow-links").checked, guestMessages: $("#guest-messages").checked });
                notify("Moderation update sent.");
            });
            $("[data-save-appearance]").addEventListener("click", () => {
                send("updateRoomSettings", { accent: $("#accent").value, icon: $("#room-icon").value.trim(), welcome: $("#welcome").value.trim() });
                notify("Appearance update sent.");
            });
            $("[data-clear]").addEventListener("click", () => {
                if (confirm("Permanently clear this room's message history?")) send("clearMessages");
            });
            $("[data-delete]").addEventListener("click", () => {
                const typed = prompt(`Type ${room} to permanently delete this room.`);
                if (typed === room) send("deleteRoom");
            });
            function receiveManagerMessage(data) {
                if (data?.type === "ixeriox:manage-state" && data.room === room) {
                    populate(data.state || {});
                }

                if (data?.type === "ixeriox:manage-result" && data.room === room) {
                    clearTimeout(connectionTimer);

                    if (!managerConnected && data.success === false) {
                        refuseAccess(
                            "Management access unavailable",
                            data.message ||
                            "The chat is connected, but it did not grant access to this room."
                        );
                    }

                    notify(data.message || (data.success ? "Saved." : "The update failed."));

                    if (data.success) {
                        managerConnected = false;
                        window.setTimeout(
                            requestChatConnection,
                            350
                        );
                    }
                }
            }

            window.addEventListener("message", event => {
                if (
                    event.origin !== parentOrigin ||
                    event.source !== window.opener
                ) {
                    return;
                }
                receiveManagerMessage(event.data);
            });

            manageChannel?.addEventListener("message", event => {
                receiveManagerMessage(event.data);
            });

            function requestChatConnection() {
                if (managerConnected) return;

                handshakeAttempts += 1;
                const readyMessage = {
                    type: "ixeriox:manage-ready",
                    room,
                    attempt: handshakeAttempts,
                    sentAt: Date.now()
                };

                callParentBridge(readyMessage).then(
                    handled => {
                        if (handled) return;

                        if (manageChannel) {
                            manageChannel.postMessage(
                                readyMessage
                            );
                        }

                        if (
                            window.opener &&
                            !window.opener.closed
                        ) {
                            window.opener.postMessage(
                                readyMessage,
                                parentOrigin
                            );
                        }
                    }
                );
            }

            async function loadRoomSettings() {
                $("[data-loading]").hidden = false;
                $("[data-denied]").hidden = true;
                requestChatConnection();

                clearInterval(handshakeTimer);
                handshakeTimer = setInterval(
                    requestChatConnection,
                    800
                );

                clearTimeout(connectionTimer);
                connectionTimer = setTimeout(
                    showConnectionWarning,
                    8000
                );

                return;

                const rememberedUser = getRememberedUser();

                if (!rememberedUser) {
                    refuseAccess(
                        "Chat login required",
                        "The remembered chat cookie is missing. Return to chat and sign in before managing a room."
                    );
                    return;
                }

                const discordId = String(rememberedUser.discordId || "").trim();

                if (!discordId) {
                    refuseAccess(
                        "Discord login required",
                        "This room can only be managed with a remembered Discord login. Guest identities are not accepted."
                    );
                    return;
                }

                try {
                    const headers = { Accept: "application/json" };
                    if (rememberedUser.sessionToken) {
                        headers.Authorization = `Bearer ${rememberedUser.sessionToken}`;
                    }

                    // This is deliberately the first API request made by the
                    // manager. No update endpoint is contacted before the room
                    // owner has been checked.
                    const response = await fetch(CHAT_DATA_URL, {
                        method: "GET",
                        credentials: "include",
                        headers
                    });

                    let result;
                    try {
                        result = await response.json();
                    } catch {
                        throw new Error("The chat API returned an invalid response.");
                    }

                    if (!response.ok || result?.success === false) {
                        throw new Error(result?.error || result?.message || `Chat API error (${response.status})`);
                    }

                    const chatData = result.chat || result.data?.chat || result.data || result;
                    const chatUserId = String(
                        chatData.userId ??
                        chatData.ownerId ??
                        chatData.owner ??
                        chatData.discordId ??
                        ""
                    ).trim();

                    if (!chatUserId) {
                        refuseAccess(
                            "Room owner unavailable",
                            "The chat API did not return a user ID for this room, so ownership could not be verified."
                        );
                        return;
                    }

                    if (discordId !== chatUserId) {
                        refuseAccess(
                            "You are not the room owner",
                            "The Discord account remembered by this browser does not match the user ID assigned to this room."
                        );
                        return;
                    }

                    $("[data-loading]").hidden = true;
                    $("[data-denied]").hidden = true;
                    $("#manager").hidden = false;

                    populate({
                        ...chatData,
                        room: chatData.name || room,
                        roomName: chatData.roomName || chatData.displayName || chatData.name || room,
                        topic: chatData.topic || "",
                        members: chatData.memberList || chatData.members || [],
                        settings: chatData.settings || {}
                    });
                } catch (error) {
                    refuseAccess(
                        "Unable to load room",
                        error.message || "The room data could not be loaded from the chat API."
                    );
                }
            }

            loadRoomSettings();
        })();
    </script>
<?php endif; ?>
</body>
</html>
