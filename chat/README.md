# YouTube chat player

Room managers (owners, site staff, or classes with `chat.settings` or
`chat.privilegeClasses.manage`) control a server-authoritative playlist. All
joined viewers follow the same video, play/pause state and position. Viewers
request a snapshot every three seconds; timestamps correct network delay and
clock differences. Rejoining or switching rooms fetches the current snapshot.

In a joined room, managers can send `/testyoutube` to start a shared demo with
one queued video. `/testyoutube VIDEO_ID_OR_URL` chooses the first video. This
local slash command sends shared playback requests to V2. Local `playYoutube`
and `AddQueue` helpers also submit shared load/enqueue requests.

```js
socket.emit('youtubeControl', {
    room: 'ROOM_ID', action: 'load',
    data: { id: 'M7lc1UVf-VE', title: 'Example' }
});
socket.emit('youtubeControl', {
    room: 'ROOM_ID', action: 'enqueue',
    data: [{ url: 'https://youtu.be/aqz-KE-bpKQ', title: 'Next' }]
});
```

V2 broadcasts `youtubeState` snapshots. `sync` requests are available to joined
viewers and echo `clientSentAt` for clock correction. Mutations require manager
permissions. `play`, `pause`, `seek`, `duration`, `ended` and `next` include the
current video `token`; play/pause/seek include `position` in seconds. Stale tokens
are ignored. Every load/queue advancement has a new token, including repeated
videos. The server advances the queue using duration reported by a manager's
YouTube player; end notifications cannot double-advance the playlist.

The player stays below the users list and also works in the mobile users dialog.
Closing it (or five idle minutes) hides that video only for the local viewer;
other viewers and the shared queue continue. A new video reopens the player.
Autoplay restrictions show a local Start playback button without pausing the
room. The existing direct-video watch-party code is separate.

Both website and V2 server updates must be deployed. Shared state lives in server
memory and resets on server restart. YouTube's iframe API, embeds and media/CDN
destinations must be reachable. Tests use simulated YouTube players with the real
sync service; live playback still needs verification on the deployment network.

Run client tests with `node --test tests/*.test.cjs`. Server tests use
`node --test tests/*.test.js` in i9ChatServer.

## Finding and creating chats

The plus button opens a chat finder. `rooms` listings use `chatTitle` for display,
`name` as the stable identifier, and `chatTopic` for topic searches. Opening the
finder requests a fresh access-filtered list with `getRooms`.

The creation form follows `v2/routes/socket.js` in iXeriox/i9ChatServer:

```js
socket.emit('createRoom', {
    name: 'weekend-gaming',
    chatTitle: 'Weekend gaming',
    chatTopic: '**Welcome**',
    visibility: 'private' // or 'public'
});
```

The address is generated from the display name unless overridden. It must use
1–64 letters, numbers, hyphens or underscores. Privilege classes and default
membership are supplied by the server. A successful `createRoom` response is
followed by `joinRoom` to obtain the normal member snapshot and open the chat.
Server `error` responses keep form values intact. A timeout keeps the requested
address so a late success can still be recognized. `roomCreated` broadcasts
trigger `getRooms`; they are not trusted as proof of access to a private chat.

## Images, emoji and links

Direct PNG, JPEG, GIF, WebP, AVIF and BMP links show inline previews, including
URLs with query strings. Up to four images are previewed per message; further
URLs remain links. Previews are lazy loaded without a referrer. If an image
cannot load, its clickable link remains. Web pages that contain images are
linked normally rather than scraped for previews.

Unicode emoji work as text. The composer emoji button inserts at the current
selection. Common shortcodes such as `:smile:`, `:thumbsup:`, `:heart:` and
`:fire:` are rendered as emoji; unknown shortcodes remain visible.

`https://infini9.net(infini9)` and `[infini9](https://infini9.net)` both display
`infini9`. Use Markdown syntax for labels on URLs with paths. Link destinations
are restricted to HTTP/HTTPS, and message text never becomes raw HTML. Links
open `/chat/link.php` in a new tab, showing the actual destination hostname and
URL before the user chooses Continue. No automatic redirect occurs.

Labelled links also accept spaces and apostrophes, for example
`https://infini9.net(Infini9's Chat Server)`. Topics use the same link and emoji
parser as messages, while retaining bold, italic, underline and line breaks.

The finder only shows rooms explicitly flagged `iXChat: true`. V2 exposes the
flag from room metadata without sending private metadata. Chats created through
this UI send `iXChat: true`. Open rooms display “Joined”; selecting one switches
to its existing tab. The V2 listing update must be deployed before unflagged
legacy payloads can participate in discovery.

## Typing indicators

Typing state is tracked per room with string-normalized user IDs. The current
room's other typists appear above the composer on desktop and mobile; the mobile
users popup also highlights typing avatars/status dots. The stop-typing debounce
retains the original room even when the sender switches tabs. V2 handles the
explicit `runCommand: stopTyping` action and retains its idle timeout fallback.
