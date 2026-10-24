# Music listening sessions

Only the room owner controls shared playback, including through direct
`youtubeControl` requests. Other room members can opt in to listen.

```text
/music play https://youtu.be/VIDEO_ID | Optional track title
/music add VIDEO_ID | Optional title
/music pause
/music resume
/music skip
/music seek 90
/music stop
/music queue
/music help
```

`/music` also displays help. `/youtube` is an alias. Queue and help are available
to everyone; playback/queue mutations require room ownership. Skip with no next
track ends the session. Stop clears playback and its queue for everyone.

A new listening session shows a non-modal invitation in chat. No YouTube iframe
or API script is loaded until that viewer clicks Join listening (or uses
`/music join`). Not now dismisses the popup and leaves a small Join listening
button. Leave listening, `/music leave`, or five idle minutes opts out locally;
queued tracks do not force that viewer back in. Joining persists only for the
current browser session and listening session. Stop followed by Play creates a
new session and invitation. There is no automatic opt-in, including for owners.

Once opted in, viewers follow the server's video, queue, play/pause state and
position. Snapshots every three seconds correct drift; late joiners catch up.
The owner's YouTube player reports duration so the server can advance the queue;
the owner should join listening to host playback. Browsers that block autoplay
show a local Start playback button. Closing a player never stops other listeners.
Desktop and mobile players remain below the scrollable users list.

The internal protocol is `youtubeControl` with `room`, `action`, and the current
video `token` for play/pause/seek/next/duration/ended. Load/enqueue accept `data`
containing video IDs, YouTube URLs or `{id|url|videoId,title?}` objects. Stop clears
the session. `youtubeState` snapshots include a stable `sessionId` across tracks
and a new video token on every load/advance, preventing duplicate end reports.
State lives in V2 memory and resets on server restart. The old `/testyoutube`
demo still works for owners and still requires explicit listening consent.

Deploy both website and V2 updates. Tests use simulated YouTube players with the
real synchronization service; live playback needs verification on the deployment
network. Run `node --test tests/*.test.cjs` in the website checkout and
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
room's typing users are highlighted orange in the desktop and mobile users lists,
including their names and avatar/status indicators. No typing text is shown. The stop-typing debounce
retains the original room even when the sender switches tabs. V2 handles the
explicit `runCommand: stopTyping` action and retains its idle timeout fallback.

## Permission save confirmation

The manager submits changes through the chat page's authenticated
`roomAdminCommand` event with a unique `requestId`. V2 serializes administration
writes per room and sends `roomAdministrationResult` only after persistence
succeeds or rolls back. The popup locks permission controls while saving and
shows the actual outcome. Disconnects or missing confirmations are reported as
unconfirmed; they are never displayed as saved. Owner assignments are retained
on server startup, including metadata/admin-data fields returned as JSON strings.
Both website and V2 updates are required for this confirmation protocol.
