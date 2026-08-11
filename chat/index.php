<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>iXeriox.dev | Chat</title>

<!-- Production Vue -->
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="https://cdn.socket.io/4.8.1/socket.io.min.js"></script>

<link rel="stylesheet" href="style.css">
</head>

<body>

<div id="app">

    <!-- ========================================================= -->
    <!-- LOGIN -->
    <!-- ========================================================= -->
    <div class="build-banner">
        ⚠️ Don't expect this to be convenient, It's currently in the building stages! Check back soon!
    </div>

    <div
        v-if="!username"
        class="login-screen"
    >

        <div class="login-box auth-card">

            <a href="/" class="logo">
                iXeriox<span>.dev/chat</span>
            </a>

            <h1>Join the conversation</h1>
            <p class="auth-intro">Choose how you would like to connect.</p>

            <form class="guest-login" @submit.prevent="login">
                <label for="guest-name">Continue as guest</label>
                <div class="guest-login-row">
                    <input
                        id="guest-name"
                        v-model="usernameInput"
                        placeholder="Choose a display name"
                        autocomplete="nickname"
                        maxlength="24"
                    >

                    <button type="submit" :disabled="connecting || discordConnecting">
                        {{ connecting ? "Connecting..." : "Connect" }}
                    </button>
                </div>
                <small>No account required. Your guest name is stored on this device.</small>
            </form>

            <div class="auth-divider"><span>or</span></div>

            <button
                type="button"
                class="discord-login-button"
                :disabled="discordConnecting || connecting"
                @click="loginWithDiscord"
            >
                <span class="discord-mark" aria-hidden="true">Discord</span>
                <span>{{ discordConnecting ? "Waiting for Discord..." : "Continue with Discord" }}</span>
                <span v-if="discordConnecting" class="auth-spinner" aria-hidden="true"></span>
            </button>

            <p v-if="authError" class="auth-error" role="alert">{{ authError }}</p>

            <p class="auth-terms">
                Discord sign-in opens in a secure popup. This site never receives your Discord password.
            </p>

        </div>

    </div>



    <!-- ========================================================= -->
    <!-- CHAT -->
    <!-- ========================================================= -->

    <div
        v-else
        class="container"
    >

        <!-- HEADER -->

        <header>

            <a href="/" class="logo">
                iXeriox<span>.dev/chat</span>
            </a>

            <div class="status">

                <span :class="connected ? 'online' : 'offline'"></span>

                <span v-if="connected">Connected as: {{ username }}</span>
                <span v-else-if="connectionError">{{ connectionError }}</span>
                <span v-else>Connecting...</span>

                <span v-if="!isBanned && myClass > 0" class="my-class-tag" :class="'class-' + classMeta(myClass).tier">
                    {{ classMeta(myClass).label }}
                </span>

                <button
                    class="disconnect-btn"
                    @click="logout"
                >
                    Disconnect
                </button>

            </div>

        </header>

        <div v-if="isBanned" class="banned-banner">
            Your account is banned from sending messages in this chat.
        </div>

        <div class="chat-panel">

            <!-- ================================================= -->
            <!-- ROOM BAR -->
            <!-- ================================================= -->

            <nav v-if="inARoom" class="rooms">
                <button
                    v-for="room in rooms"
                    :key="room.name"
                    class="room-tab"
                    :class="[
                        { active: currentRoom === room.name },
                        currentRoom !== room.name ? roomActivityClass(room.name) : ''
                    ]"
                    @click="switchRoom(room.name)"
                >

                    #{{ room.roomName }}

                    <span
                        class="close-btn"
                        @click.stop="closeRoom(room.name)"
                    >
                        ×
                    </span>

                </button>
                <button
                    class="join-room-button"
                    @click="showJoinDialog = true"
                >
                    +
                </button>
            </nav>

            <!-- ================================================= -->
            <!-- WATCH PARTY BAR -->
            <!-- ================================================= -->
<!--        <div v-if="inARoom" class="watch-party-bar">

                <button class="watch-party-toggle" @click="mediaPanelExpanded = !mediaPanelExpanded">
                    🎬 Watch Party
                    <span v-if="partyMembership[currentRoom]" class="party-joined-dot"></span>
                    <span class="chevron">{{ mediaPanelExpanded ? '▴' : '▾' }}</span>
                </button>

                <button
                    class="party-join-btn"
                    :class="{joined: partyMembership[currentRoom]}"
                    @click="toggleWatchParty"
                >
                    {{ partyMembership[currentRoom] ? 'Leave Party' : 'Join Party' }}
                </button>

            </div>
-->

            <div v-if="inARoom && mediaPanelExpanded" class="watch-party-panel">

                <div v-if="canManageRoom" class="watch-party-load">

                    <input
                        v-model="mediaUrlInput"
                        @keyup.enter="loadMedia"
                        placeholder="Paste a direct video URL (.mp4, .webm)..."
                        autocomplete="off"
                    >

                    <button @click="loadMedia">Load</button>

                </div>

                <video
                    v-if="mediaByRoom[currentRoom]?.url"
                    ref="videoRef"
                    :src="mediaByRoom[currentRoom].url"
                    class="watch-party-video"
                    :controls="canManageRoom"
                    @play="onVideoPlay"
                    @pause="onVideoPause"
                    @seeked="onVideoSeeked"
                ></video>

                <div v-else class="watch-party-empty">
                    No video loaded yet.
                    <span v-if="canManageRoom">Paste a link above to start one for the room.</span>
                </div>

                <p v-if="!partyMembership[currentRoom]" class="watch-party-hint">
                    Join the party to sync playback with everyone else watching.
                </p>

            </div>

            <!-- ================================================= -->
            <!-- MAIN -->
            <!-- ================================================= -->

            <div class="chat-area">

                <!-- NO ROOM OPEN -->

                <section
                    v-if="!inARoom"
                    class="no-room"
                >

                    <div class="welcome-card">

                        <h2>iXeriox.dev/chat</h2>

                        <p>

                            You're currently not inside a chat room.

                        </p>

                        <button
                            class="big-join-button"
                            @click="showJoinDialog = true"
                        >
                            Join a Chat
                        </button>

                    </div>

                </section>



                <!-- ROOM OPEN -->

                <template v-else>

                    <div class="messages-wrapper">
                        <section
                            class="messages"
                            ref="messageBox"
                        >

                        <div
                            v-for="(msg,index) in messages"
                            :key="index"
                            class="message"
                            :class="{'system-message': msg.user === 'system'}"
                        >
                            <article v-if="msg.type === 'whois' && msg.whoisData" class="whois-card">
                                <div
                                    v-if="msg.whoisData.meta?.coverPhoto"
                                    class="whois-cover"
                                >
                                    <span
                                        v-if="!isProfileImageLoaded(msg.id, 'cover')"
                                        class="whois-image-loader"
                                    ></span>
                                    <img
                                        :src="msg.whoisData.meta.coverPhoto"
                                        alt=""
                                        @load="markProfileImageLoaded(msg.id, 'cover')"
                                        @error="markProfileImageLoaded(msg.id, 'cover')"
                                    >
                                </div>
                                <div
                                    class="whois-body"
                                    :class="{ 'without-cover': !msg.whoisData.meta?.coverPhoto }"
                                >
                                    <div
                                        v-if="msg.whoisData.avatar || msg.whoisData.meta?.avatar"
                                        class="whois-avatar"
                                    >
                                        <span
                                            v-if="!isProfileImageLoaded(msg.id, 'avatar')"
                                            class="whois-image-loader"
                                        ></span>
                                        <img
                                            :src="msg.whoisData.avatar || msg.whoisData.meta?.avatar"
                                            :alt="`${msg.whoisData.displayName || msg.whoisData.username || 'User'} avatar`"
                                            @load="markProfileImageLoaded(msg.id, 'avatar')"
                                            @error="markProfileImageLoaded(msg.id, 'avatar')"
                                        >
                                    </div>
                                    <div class="whois-main">
                                        <div class="whois-heading">
                                            <div>
                                                <span class="whois-kicker">WHOIS RESULT · {{ msg.time }}</span>
                                                <h4>{{ msg.whoisData.displayName || msg.whoisData.meta?.displayName || msg.whoisData.username || 'Unknown user' }}</h4>
                                                <span class="whois-username">@{{ msg.whoisData.username || msg.whoisData.meta?.username || 'unknown' }}</span>
                                            </div>
                                            <span class="whois-status" :class="`is-${msg.whoisData.status || 'unknown'}`">
                                                {{ msg.whoisData.status || 'unknown' }}
                                            </span>
                                        </div>

                                        <dl class="whois-facts">
                                            <div><dt>Discord ID</dt><dd>{{ msg.whoisData.discord_id || msg.whoisData.id || 'Unknown' }}</dd></div>
                                            <div><dt>Permission</dt><dd>{{ msg.whoisData.permissionLevel ?? 'Unknown' }}</dd></div>
                                            <div><dt>Pronouns</dt><dd>{{ msg.whoisData.meta?.pronouns || 'Not provided' }}</dd></div>
                                            <div><dt>Last seen</dt><dd>{{ formatWhoisDate(msg.whoisData.lastSeen) }}</dd></div>
                                        </dl>

                                        <div class="whois-room-section">
                                            <span class="whois-room-label">ROOMS</span>
                                            <div v-if="whoisRooms(msg.whoisData).length" class="whois-room-list">
                                                <button
                                                    v-for="roomName in whoisRooms(msg.whoisData)"
                                                    :key="roomName"
                                                    type="button"
                                                    class="whois-room"
                                                    :class="{ joined: isRoomJoined(roomName) }"
                                                    @click="joinWhoisRoom(roomName)"
                                                    :title="isRoomJoined(roomName) ? `Already joined #${roomName}` : `Send /join ${roomName}`"
                                                >
                                                    #{{ roomName }}
                                                </button>
                                            </div>
                                            <span v-else class="whois-no-rooms">No visible rooms</span>
                                        </div>

                                        <div v-if="msg.whoisData.roles?.length" class="whois-roles">
                                            <span
                                                v-for="role in msg.whoisData.roles"
                                                :key="role.id"
                                                class="whois-role"
                                                :style="{ '--role-colour': role.color || '#7289da' }"
                                            >
                                                {{ role.name }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <template v-else>
                                <span class="msg-time">[{{ msg.time }}]</span>

                                <span v-if="msg.type !== 'action' && msg.user !== 'system'" class="msg-user" :class="'class-' + classMeta(msg.class).tier">{{ msg.user }}</span>
                                <span v-else-if="msg.type === 'action'"> ** {{ msg?.rawData?.senderName || msg.user }}</span>
                                <span v-else-if="msg.user === 'system'" class="msg-system">SYSTEM >> </span>
                                <small
                                    v-if="msg.class && msg.user !== 'system'"
                                    class="class-badge"
                                    :class="'class-' + classMeta(msg.class).tier"
                                >
                                    {{ classMeta(msg.class).label }}
                                </small>

                                <span class="msg-text">
                                    <template v-for="(part, partIndex) in messageParts(msg.message)" :key="`${msg.id || index}-${partIndex}`">
                                        <mark v-if="part.mention" class="message-mention">{{ part.text }}</mark>
                                        <template v-else>{{ part.text }}</template>
                                    </template>
                                </span>
                            </template>

                        </div>

                        <div
                            v-if="messages.length===0"
                            class="empty"
                        >
                            No messages yet.
                        </div>

                        </section>

                    </div>

                    <TransitionGroup name="system-notice" tag="aside" class="system-notice-tray">
                        <button
                            v-for="notice in systemNotices"
                            :key="notice.id"
                            class="system-notice"
                            type="button"
                            @click="dismissSystemNotice(currentRoom, notice.id)"
                            title="Dismiss"
                        >
                            <span class="system-notice-label">System</span>
                            <span>{{ notice.message }}</span>
                        </button>
                    </TransitionGroup>



                    <!-- ================================================= -->
                    <!-- USERS — grouped by privilege class -->
                    <!-- ================================================= -->

                    <aside class="users">

                        <h3>
                            USERS
                            <span class="users-total">{{ roomMemberCount(currentRoom) }}</span>
                        </h3>

                        <template
                            v-for="group in fetchGroupedUsersForRoom(currentRoom)"
                            :key="group.name"
                        >

                            <div class="user-group">

                                <div class="user-group-header" :class="'class-' + group.tier">
                                    <span>{{ group.label }}</span>
                                    <span class="user-group-count">{{ group.users.length }}</span>
                                </div>

                                <div
                                    v-for="user in group.users"
                                    :key="user.id"
                                    class="user"
                                    :class="'tier-' + group.tier"
                                >

                                    <span
                                        v-if="!user.avatar"
                                        :class="user.status"
                                        :style="isUserTyping(user.id) ? 'background:#ff9800' : ''"
                                    ></span>
                                    <span
                                        v-else
                                        class="user-avatar"
                                        :style="{
                                            backgroundImage: user.avatar ? `url(${user.avatar})` : 'none',
                                            backgroundSize: 'cover',
                                            backgroundPosition: 'center',
                                            boxShadow: isUserTyping(user.id) ? '0 0 10px #ff9800, 0 0 20px #ff9800' : (user.status === 'online' ? '0 0 8px #4caf50' : 'none'),
                                            border: isUserTyping(user.id) ? '2px solid #ff9800' : (user.status === 'online' ? '2px solid #4caf50' : '2px solid #555'),
                                            width: '28px',
                                            height: '28px'
                                        }"
                                    ></span>

                                    <span class="user-name">{{ memberLabel(user) }}</span>

                                </div>

                            </div>

                        </template>

                        <div v-if="fetchGroupedUsersForRoom(currentRoom).length === 0" class="empty-search">
                            No one else is here yet.
                        </div>

                    </aside>

                </template>

            </div>

                    <!-- ================================================= -->
                    <!-- MESSAGE INPUT -->
                    <!-- ================================================= -->

                    <form
                        v-if="inARoom"
                        class="controls"
                        @submit.prevent="sendMessage"
                    >

                        <button
                            v-if="canManageRoom"
                            type="button"
                            class="chat-settings-cog"
                            @click="openChatSettings"
                            :aria-label="'Manage ' + (currentRoomObj?.roomName || currentRoom)"
                            :title="'Manage #' + (currentRoomObj?.roomName || currentRoom)"
                        >
                            <span aria-hidden="true">⚙</span>
                        </button>

                        <input
                            v-model="message"
                            @input="sendTyping"
                            :placeholder="isBanned ? 'You cannot send messages' : (!connected ? 'Reconnecting…' : 'Type your message...')"
                            :disabled="isBanned || !connected"
                            autocomplete="off"
                        >

                        <button :disabled="isBanned || !connected">
                            SEND &gt;
                        </button>

                    </form>

                </div>
            </div>



            <!-- ========================================================= -->
            <!-- JOIN CHAT MODAL -->
            <!-- ========================================================= -->

            <div
                v-if="showJoinDialog"
                class="join-overlay"
                @click.self="showJoinDialog = false"
            >

<div class="join-modal">

    <div class="join-header">

        <div>

            <h2>Join a Chat</h2>

            <p>Browse and join public chat rooms.</p>

        </div>

        <button
            class="modal-close"
            @click="showJoinDialog = false"
        >
            ×
        </button>

    </div>

    <div class="join-search">

        <input
            v-model="roomSearch"
            placeholder="Search rooms..."
            autocomplete="off"
        >

    </div>

    <div class="join-room-list">

        <div
            v-for="room in filteredRooms"
            :key="room.name"
            class="join-room-card"
        >

            <div class="join-room-info">

                <h3>#{{ room.roomName }}</h3>

                <small>
                    {{ room.chatType || "Public Room" }}
                </small>

            </div>

            <button
                class="join-button"
                :class="{joined: isRoomJoined(room.name)}"
                :disabled="isRoomJoined(room.name)"
                @click="joinRoom(room.name)"
            >
                {{ isRoomJoined(room.name) ? "Joined" : "Join" }}
            </button>

        </div>

        <div
            v-if="filteredRooms.length===0"
            class="empty-search"
        >

            No rooms found.

        </div>

    </div>

            <!-- ========================================================= -->
            <!-- PERMISSIONS PANEL -->
            <!-- ========================================================= -->

            <div
                v-if="showPermissionsPanel"
                class="perm-overlay"
                @click.self="showPermissionsPanel = false"
            >

                <div class="perm-modal">

                    <div class="join-header">

                        <div>
                            <h2>Room Permissions</h2>
                            <p>Manage privilege classes for #{{ currentRoomObj?.roomName }}</p>
                        </div>

                        <button
                            class="modal-close"
                            @click="showPermissionsPanel = false"
                        >
                            ×
                        </button>

                    </div>

                    <div class="perm-toolbar">
                        <div>
                            <strong>Custom privilege classes</strong>
                            <small>Higher order means greater authority.</small>
                        </div>
                        <form class="perm-create" @submit.prevent="createPrivilegeClass">
                            <input v-model.trim="newClassName" maxlength="64" placeholder="New class name" :disabled="adminBusy">
                            <input v-model.number="newClassOrder" type="number" placeholder="Order" :disabled="adminBusy">
                            <button type="submit" :disabled="adminBusy">Add class</button>
                        </form>
                    </div>

                    <p v-if="adminError" class="perm-error">{{ adminError }}</p>

                    <div class="perm-legend">
                        <span
                            v-for="privilegeClass in currentPrivilegeClasses"
                            :key="privilegeClass.name"
                            class="class-badge"
                            :class="'class-' + roomClassTier(currentRoomObj, privilegeClass.name)"
                        >
                            {{ privilegeClass.name }} · {{ privilegeClass.order }}
                        </span>
                    </div>

                    <div class="perm-class-editor">
                        <article
                            v-for="privilegeClass in currentPrivilegeClasses"
                            :key="`editor-${privilegeClass.name}`"
                            class="perm-class-card"
                        >
                            <header>
                                <strong>{{ privilegeClass.name }}</strong>
                                <span v-if="currentRoomObj?.raw?.defaultPrivilegeClass === privilegeClass.name" class="perm-default-badge">Default</span>
                            </header>

                            <div class="perm-class-actions">
                                <label>
                                    Order
                                    <input
                                        type="number"
                                        :value="privilegeClass.order"
                                        :disabled="adminBusy"
                                        @change="setPrivilegeClassOrder(privilegeClass.name, $event.target.value)"
                                    >
                                </label>
                                <button type="button" :disabled="adminBusy" @click="setDefaultPrivilegeClass(privilegeClass.name)">Default</button>
                                <button type="button" :disabled="adminBusy" @click="renamePrivilegeClass(privilegeClass.name)">Rename</button>
                                <button type="button" class="danger" :disabled="adminBusy || currentPrivilegeClasses.length <= 1" @click="deletePrivilegeClass(privilegeClass.name)">Delete</button>
                            </div>

                            <div class="perm-permission-grid">
                                <label
                                    v-for="permission in CHAT_PERMISSION_LIST"
                                    :key="permission"
                                    class="perm-permission"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="privilegeClass.permissions.includes(permission)"
                                        :disabled="adminBusy"
                                        @change="toggleClassPermission(privilegeClass.name, permission, $event.target.checked)"
                                    >
                                    <span>{{ permission }}</span>
                                </label>
                            </div>
                        </article>
                    </div>

                    <div class="perm-list">

                        <template
                            v-for="group in fetchGroupedUsersForRoom(currentRoom)"
                            :key="group.tier"
                        >

                            <div class="perm-group">

                                <div class="user-group-header" :class="'class-' + group.tier">
                                    <span>{{ group.label }}</span>
                                    <span class="user-group-count">{{ group.users.length }}</span>
                                </div>

                                <div
                                    v-for="user in group.users"
                                    :key="user.id"
                                    class="perm-row"
                                >

                                    <span class="user-name">{{ memberLabel(user) }}</span>

                                    <span class="class-badge" :class="'class-' + group.tier">
                                        {{ user.privilegeClass }}
                                    </span>

                                    <select
                                        v-if="canManageUser(user)"
                                        class="perm-select"
                                        :value="user.privilegeClass"
                                        :disabled="adminBusy"
                                        @change="setUserClass(user, $event.target.value)"
                                    >
                                        <option
                                            v-for="privilegeClass in currentPrivilegeClasses"
                                            :key="privilegeClass.name"
                                            :value="privilegeClass.name"
                                            :disabled="!canAssignClass(privilegeClass.name)"
                                        >
                                            {{ privilegeClass.name }} ({{ privilegeClass.order }})
                                        </option>
                                    </select>

                                    <button
                                        v-if="canManageUser(user)"
                                        type="button"
                                        class="perm-reset"
                                        :disabled="adminBusy"
                                        @click="unassignUserClass(user)"
                                    >
                                        Reset
                                    </button>

                                    <span v-else class="perm-locked" title="You don't have permission to manage this user">🔒</span>

                                </div>

                            </div>

                        </template>

                        <div v-if="fetchGroupedUsersForRoom(currentRoom).length === 0" class="empty-search">
                            No one to manage yet.
                        </div>

                    </div>

                </div>

            </div>
</div>                </div>

            </div>




        </div>

        <script src="app.js?v=<?= rawurlencode((string)(@filemtime(__DIR__ . '/app.js') ?: time())) ?>"></script>

        </body>
        </html>
