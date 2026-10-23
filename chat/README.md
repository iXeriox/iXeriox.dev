# YouTube chat player

In a joined room, send `/testyoutube` to test the local player and a queued
second video. `/testyoutube VIDEO_ID_OR_URL` chooses the first video. This
command is handled locally and is not sent to the chat server.

The chat client provides local `playYoutube(data)` and `AddQueue(data)` handlers.
The server can trigger them through Socket.IO:

```js
socket.emit('playYoutube', { room: 'ROOM_ID', data: 'M7lc1UVf-VE' });
socket.emit('AddQueue', {
    room: 'ROOM_ID',
    data: [
        { id: 'aqz-KE-bpKQ', title: 'Next video' },
        { url: 'https://youtu.be/M7lc1UVf-VE', title: 'Another video' }
    ]
});
```

`data` accepts an ID, YouTube URL, `{ id | videoId | url, title? }`, or a list.
`addQueue` is also accepted as an event name. A `runCommand` event with
`{ command: 'playYoutube' | 'AddQueue', room, data }` calls the same handlers.
An omitted room targets the currently selected room. Events for rooms the user
has not joined are ignored. Only these explicit handlers are callable; server
input is never evaluated as JavaScript.

`playYoutube` replaces current playback and its queue; `AddQueue` appends in
order and starts playback when idle. The local player advances when a video
ends. Queues are per room, local to each client, and cleared on logout or leaving
a room. These events do not synchronize playback timestamps between clients.

The player slides into the bottom of the users sidebar (or mobile users dialog),
reducing the scrollable users list. It closes after five minutes without
playback or panel interaction. Playing/buffering prevents idle closure. Close
clears the current room's list. A finished video with no next item remains until
the idle timeout. Unplayable videos show an error with Next/Retry controls.
Browsers may require the user to press Play before allowing playback.

YouTube's iframe API and embeds must be reachable (`www.youtube.com`,
`www.youtube.com/iframe_api`, and YouTube's media/CDN destinations). The queue and
layout browser checks use a simulated iframe API; live media playback requires
verification against YouTube in the target browser/network.

Run regression tests with `node --test tests/*.test.cjs` from the repository root.

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
