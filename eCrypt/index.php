<?php
declare(strict_types=1);

/**
 * eCrypt: private, single-consumption encrypted inbox.
 * Run php eCrypt/setup.php once, or supply the ECRYPT_* environment variables.
 */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(self), geolocation=()');
header("Content-Security-Policy: default-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' blob: data:; media-src 'self' blob:; connect-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    // Trust only a directly secured request, or Cloudflare's explicit HTTPS indication.
    if (strtolower((string)($_SERVER['HTTP_CF_VISITOR'] ?? '')) !== '{"scheme":"https"}') {
        http_response_code(403);
        exit('HTTPS required.');
    }
}

session_name('ecrypt_session');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/eCrypt/', 'secure' => true,
    'httponly' => true, 'samesite' => 'Strict'
]);
session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));

function respond(int $status, array $data): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
}
/**
 * Explicit environment settings take precedence; otherwise use the private
 * file created by "php eCrypt/setup.php". The directory is a sibling of the
 * website directory, never inside the served eCrypt tree.
 */
function settings(): array {
    static $settings = null;
    if ($settings !== null) return $settings;
    $file = dirname(__DIR__, 2) . '/ecrypt-private/config.json';
    $saved = [];
    if (is_file($file)) {
        $raw = file_get_contents($file);
        $decoded = $raw === false ? null : json_decode($raw, true);
        if (is_array($decoded)) $saved = $decoded;
    }
    $settings = [
        'password_hash' => getenv('ECRYPT_PASSWORD_HASH') ?: ($saved['password_hash'] ?? ''),
        'salt' => settings()['salt'] ?: ($saved['salt'] ?? ''),
        'db_path' => getenv('ECRYPT_DB_PATH') ?: ($saved['db_path'] ?? ''),
    ];
    return $settings;
}
function configured(): bool {
    $s = settings();
    return is_string($s['password_hash']) && $s['password_hash'] !== ''
        && is_string($s['salt']) && strlen(base64_decode($s['salt'], true) ?: '') >= 16
        && is_string($s['db_path']) && $s['db_path'] !== '';
}
function store(): PDO {
    $path = (string)settings()['db_path'];
    if (!$path || !str_starts_with($path, '/') || str_contains($path, "\0")) {
        throw new RuntimeException('ECRYPT_DB_PATH must be an absolute path outside the web root');
    }
    $dir = dirname($path);
    if (!is_dir($dir) || !is_writable($dir)) throw new RuntimeException('Storage directory missing or unwritable');
    $db = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    $db->exec('CREATE TABLE IF NOT EXISTS messages (
        id TEXT PRIMARY KEY, sender TEXT NOT NULL, sender_session TEXT NOT NULL,
        kind TEXT NOT NULL, ciphertext TEXT NOT NULL, iv TEXT NOT NULL,
        created INTEGER NOT NULL, expires INTEGER NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS messages_expires ON messages(expires)');
    $db->prepare('DELETE FROM messages WHERE expires <= ?')->execute([time()]);
    return $db;
}
function body(): array {
    $raw = file_get_contents('php://input', false, null, 0, 13500000);
    $decoded = json_decode($raw ?: '', true);
    return is_array($decoded) ? $decoded : [];
}
function validOrigin(): bool {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return is_string($origin) && $origin !== '' &&
        hash_equals('https://' . $host, $origin);
}
function limitedAttempts(): bool {
    $now = time();
    $_SESSION['attempts'] = array_values(array_filter($_SESSION['attempts'] ?? [], fn($t) => $t > $now - 900));
    return count($_SESSION['attempts']) < 8;
}

$action = (string)($_GET['action'] ?? '');
if ($action !== '') {
    if (!configured()) respond(503, ['error' => 'eCrypt is not configured on the server.']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validOrigin()) respond(403, ['error' => 'Invalid origin']);
    $data = $_SERVER['REQUEST_METHOD'] === 'POST' ? body() : [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        !hash_equals((string)$_SESSION['csrf'], (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        respond(403, ['error' => 'Invalid session token']);
    }
    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!limitedAttempts()) respond(429, ['error' => 'Too many attempts. Try again later.']);
        $password = (string)($data['password'] ?? '');
        $hash = (string)settings()['password_hash'];
        if (strlen($password) > 256 || !password_verify($password, $hash)) {
            $_SESSION['attempts'][] = time();
            respond(401, ['error' => 'Incorrect access password']);
        }
        session_regenerate_id(true);
        $_SESSION['allowed'] = true;
        $_SESSION['attempts'] = [];
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
        respond(200, ['ok' => true, 'csrf' => $_SESSION['csrf'], 'salt' => getenv('ECRYPT_KDF_SALT')]);
    }
    if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        respond(200, ['ok' => !empty($_SESSION['allowed']), 'csrf' => $_SESSION['csrf'],
            'salt' => !empty($_SESSION['allowed']) ? settings()['salt'] : null]);
    }
    if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION = [];
        session_destroy();
        respond(200, ['ok' => true]);
    }
    if (empty($_SESSION['allowed'])) respond(401, ['error' => 'Unlock eCrypt first']);
    try {
        if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $db = store();
            $stmt = $db->prepare('SELECT id, sender, kind, created FROM messages WHERE sender_session != ? ORDER BY created ASC LIMIT 60');
            $stmt->execute([session_id()]);
            respond(200, ['messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim((string)($data['sender'] ?? ''));
            $kind = (string)($data['kind'] ?? '');
            $cipher = (string)($data['ciphertext'] ?? '');
            $iv = (string)($data['iv'] ?? '');
            if (!preg_match('/^[\p{L}\p{N} _.-]{1,32}$/u', $name) ||
                !in_array($kind, ['text', 'image', 'video', 'audio'], true) ||
                strlen($cipher) < 20 || strlen($cipher) > 12000000 ||
                !preg_match('/^[A-Za-z0-9+\/]+={0,2}$/D', $cipher) ||
                !preg_match('/^[A-Za-z0-9+\/]{16}$/D', $iv)) {
                respond(422, ['error' => 'Invalid message or attachment (maximum approximately 8 MB)']);
            }
            $db = store();
            $count = (int)$db->query('SELECT COUNT(*) FROM messages')->fetchColumn();
            if ($count >= 200) respond(429, ['error' => 'Inbox is full']);
            $stmt = $db->prepare('INSERT INTO messages VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([bin2hex(random_bytes(16)), $name, session_id(), $kind, $cipher, $iv, time(), time() + 86400]);
            respond(200, ['ok' => true]);
        }
        if ($action === 'open' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (string)($data['id'] ?? '');
            if (!preg_match('/^[a-f0-9]{32}$/D', $id)) respond(422, ['error' => 'Invalid message']);
            $db = store();
            $db->exec('BEGIN IMMEDIATE TRANSACTION');
            $stmt = $db->prepare('SELECT * FROM messages WHERE id = ? AND sender_session != ? AND expires > ?');
            $stmt->execute([$id, session_id(), time()]);
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($message) {
                $db->prepare('DELETE FROM messages WHERE id = ?')->execute([$id]);
            }
            $db->commit();
            if (!$message) respond(410, ['error' => 'Already opened or expired']);
            respond(200, ['ciphertext' => $message['ciphertext'], 'iv' => $message['iv'],
                'sender' => $message['sender'], 'kind' => $message['kind']]);
        }
    } catch (Throwable $e) {
        error_log('eCrypt storage error: ' . $e->getMessage());
        respond(503, ['error' => 'Chat storage unavailable']);
    }
    respond(404, ['error' => 'Unknown action']);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#07090d">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>eCrypt // iXeriox.dev</title>
<style>
:root{--bg:#07090d;--panel:#0d1118;--panel2:#111823;--border:#202936;--text:#e8edf3;--muted:#8893a3;--amber:#ffad55;--cyan:#36e0d0;--green:#65ff9a;--mono:"JetBrains Mono","Fira Code",Consolas,monos}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,system-ui,sans-serif;min-height:100vh}
body:before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.2;background-image:linear-gradient(#ffffff12 1px,transparent 1px),linear-gradient(90deg,#ffffff12 1px,transparent 1px);background-size:45px 45px}
main{position:relative;max-width:900px;margin:auto;padding:24px 18px}
header{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:20px;gap:10px}
.logo{font:700 22px var(--mono)}.logo b{color:var(--amber)}a{color:var(--cyan);text-decoration:none}
.mono,small,label,button,.tag{font-family:var(--mono)}
.tag{color:var(--green);font-size:12px}.card{background:var(--panel);border:1px solid var(--border);border-radius:14px;padding:24px;margin-top:24px}
h1{font:700 clamp(27px,5vw,40px) var(--mono);margin:0 0 12px}h1 span,h2{color:var(--cyan)}h2{font:600 17px var(--mono)}
p,small{color:var(--muted);line-height:1.6}input,textarea{width:100%;padding:12px 14px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:8px;font:15px var(--mono)}
textarea{min-height:90px;resize:vertical}input:focus,textarea:focus{outline:1px solid var(--cyan)}
label{display:block;color:var(--muted);font-size:12px;margin:14px 0 7px}button{border:1px solid var(--cyan);background:transparent;color:var(--cyan);padding:11px 16px;border-radius:8px;cursor:pointer;font-size:13px}
button:hover:not(:disabled){background:#36e0d019}button:disabled{opacity:.4;cursor:not-allowed}
.primary{background:var(--cyan);color:var(--bg);font-weight:700}.primary:hover:not(:disabled){background:#81fff2}
.danger{border-color:var(--amber);color:var(--amber)}.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.row>*{min-width:0}
.hidden{display:none!important}.note{border-left:2px solid var(--amber);background:#ffad550a;padding:12px 14px;margin-top:18px;font-size:13px}
.inbox{display:grid;gap:10px;max-height:320px;overflow:auto}.message{display:flex;gap:12px;justify-content:space-between;align-items:center;padding:13px;background:var(--panel2);border:1px solid var(--border);border-radius:9px}
.message strong{font-family:var(--mono);font-size:13px}.message small{display:block;font-size:11px}
.preview{background:var(--bg);border:1px solid var(--border);padding:15px;border-radius:10px;overflow-wrap:anywhere}
.preview img,.preview video{max-width:100%;max-height:60vh;border-radius:8px}.preview audio{width:100%}
#opened{margin-top:18px}#status{min-height:25px;color:var(--amber);font:13px var(--mono);margin-top:12px}

/* Touch-friendly mobile layout, preserving the main site's palette */
html{-webkit-text-size-adjust:100%}body{overscroll-behavior-y:contain}
button,input,textarea{touch-action:manipulation}
button{min-height:44px;min-width:44px}
button:focus-visible,a:focus-visible{outline:2px solid var(--amber);outline-offset:3px}
input,textarea{font-size:16px;min-height:46px}
input[type="file"]{min-height:54px;padding:12px}
input[type="file"]::file-selector-button{font:inherit;color:var(--cyan);background:var(--panel2);border:1px solid var(--border);border-radius:8px;padding:10px;margin-right:10px}
#status{position:sticky;bottom:env(safe-area-inset-bottom,0px);z-index:5;background:var(--panel);border-radius:8px;padding:8px 0}
#chat h2{margin:24px 0 12px}
.compose-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px}
.compose-actions button{width:100%;white-space:normal}
.media-actions{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:12px 0}
.media-actions button{padding:10px 6px;white-space:normal}
#unlock{margin-top:10px}
#record.recording{border-color:var(--amber);color:var(--amber);background:#ffad5515;animation:recordGlow 1.2s ease-in-out infinite alternate}
@keyframes recordGlow{to{box-shadow:0 0 14px #ffad5550}}
@media(max-width:600px){
 main{padding:14px 12px calc(24px + env(safe-area-inset-bottom,0px))}
 .card{padding:18px 14px;margin-top:16px}header{align-items:center;gap:10px}
 header .logo{font-size:16px}header a{font-size:13px;padding:10px 0}
 h1{font-size:29px}.message{align-items:stretch;flex-direction:column}
 .message button{width:100%}
 #unlock,#logout,#closePreview,#sendFile{width:100%}
 #recordState{width:100%;text-align:center}
 .inbox{max-height:min(40dvh,350px)}
 .preview{padding:10px}.preview img,.preview video{max-height:55dvh}
 .note{font-size:12px}
}
@media(max-width:380px){.media-actions{grid-template-columns:1fr 1fr}}
@media(prefers-reduced-motion:reduce){#record.recording{animation:none}}

</style>
</head>
<body>
<main>
<header><div class="logo">iX<b>eriox</b> / <span style="color:var(--cyan)">eCrypt</span></div><a href="/">← back to site</a></header>
<div class="card">
<h1><span>./</span> eCrypt</h1>
<p>Private, encrypted, one-time chat. Text, pictures, videos and voice notes disappear from the server as soon as somebody opens them.</p>
<div id="login">
<label for="access">Access password</label><input id="access" type="password" autocomplete="off" placeholder="Private room access">
<label for="phrase">Encryption phrase (never sent to the server)</label><input id="phrase" type="password" autocomplete="off" placeholder="Shared secret with the other participant">
<p><small>Everyone in this room must use the same encryption phrase to read messages. Choose a separate, strong phrase from your access password.</small></p>
<button class="primary" id="unlock" disabled>Unlock private room →</button>
</div>
<div id="chat" class="hidden">
<div class="row" style="justify-content:space-between"><div class="tag">● ENCRYPTION ACTIVE · READ ONCE</div><button class="danger" id="logout">Lock room</button></div>
<label for="nickname">Display name</label><input id="nickname" maxlength="32" placeholder="Your name">
<h2>./ incoming</h2>
<div id="inbox" class="inbox"><small>Checking encrypted inbox…</small></div>
<div id="opened" class="hidden">
<h2>./ opened once</h2><div class="preview" id="preview"></div>
<p><small>This item was deleted from the server on opening. Close this preview to remove it from this page.</small></p>
<button id="closePreview">Close &amp; clear preview</button>
</div>
<h2>./ compose</h2>
<label for="message">One-time message</label><textarea id="message" placeholder="Your message…"></textarea>
<div class="compose-actions"><button class="primary" id="sendText">Send text ↗</button><button id="record" aria-pressed="false">● Record voice</button></div>
<label for="attachment">Share a photo, video or audio file (maximum 6 MB)</label>
<div class="media-actions"><button type="button" id="pickImage">▧ Photos</button><button type="button" id="pickVideo">▶ Video</button><button type="button" id="pickAudio">♫ Audio</button></div>
<input id="attachment" type="file" accept="image/*,video/*,audio/*" aria-label="Selected media file">
<small id="selectedFile" aria-live="polite">No attachment selected</small>
<div class="row" style="margin-top:12px"><button id="sendFile">Encrypt &amp; send attachment</button><small id="recordState"></small></div>
</div>
<div class="note">Read-once means the first successful server retrieval permanently consumes the message, even if decryption or playback fails. Messages not opened expire after 24 hours. This cannot prevent screenshots, recordings, browser caches, server backups, or copies made before deletion. This is a shared inbox, not individual direct messages.</div>
<div id="status" role="status" aria-live="polite"></div>
</div>
</main>
<script>
(function(){
'use strict';
const $ = id => document.getElementById(id);
const enc = new TextEncoder(), dec = new TextDecoder();
let key = null, csrf = '', salt = '', polling = null, recorder = null, chunks = [], currentUrl = null;
const b64 = bytes => { let s=''; for(let i=0;i<bytes.length;i+=8192)s+=String.fromCharCode(...bytes.subarray(i,i+8192));return btoa(s); };
const unb64 = s => Uint8Array.from(atob(s), c => c.charCodeAt(0));
function status(s){$('status').textContent=s;}
async function api(action, data){
  const post = data !== undefined;
  const response = await fetch('./index.php?action='+action,{
    method:post?'POST':'GET',credentials:'same-origin',
    headers:post?{'Content-Type':'application/json','X-CSRF-Token':csrf}:{},
    body:post?JSON.stringify(data):undefined,cache:'no-store'
  });
  const value=await response.json();
  if(!response.ok)throw Error(value.error||'Request failed');
  return value;
}
async function derive(phrase,s){
  const base=await crypto.subtle.importKey('raw',enc.encode(phrase),'PBKDF2',false,['deriveKey']);
  return crypto.subtle.deriveKey({name:'PBKDF2',salt:unb64(s),iterations:310000,hash:'SHA-256'},base,
    {name:'AES-GCM',length:256},false,['encrypt','decrypt']);
}
async function encrypt(bytes){
  const iv=crypto.getRandomValues(new Uint8Array(12));
  const ciphertext=new Uint8Array(await crypto.subtle.encrypt({name:'AES-GCM',iv},key,bytes));
  return {iv:b64(iv),ciphertext:b64(ciphertext)};
}
async function decrypt(payload){
  return new Uint8Array(await crypto.subtle.decrypt({name:'AES-GCM',iv:unb64(payload.iv)},key,unb64(payload.ciphertext)));
}
function clearPreview(){
  if(currentUrl){URL.revokeObjectURL(currentUrl);currentUrl=null;}
  $('preview').replaceChildren();$('opened').classList.add('hidden');
}
async function lock(){
  clearPreview();key=null;clearInterval(polling);polling=null;
  try{await api('logout',{});}catch(e){}
  $('chat').classList.add('hidden');$('login').classList.remove('hidden');
  $('access').value='';$('phrase').value='';status('Room locked.');
}
async function unlock(){
  const unlockButton=$('unlock');unlockButton.disabled=true;
  try{
    if(!$('access').value||!$('phrase').value)throw Error('Enter both passwords.');
    if(!window.isSecureContext || !crypto.subtle)throw Error('HTTPS and Web Crypto are required.');
    const result=await api('login',{password:$('access').value});
    csrf=result.csrf;salt=result.salt;
    const derived=await derive($('phrase').value,salt);
    key=derived;$('access').value='';$('phrase').value='';
    $('login').classList.add('hidden');$('chat').classList.remove('hidden');
    status('Room unlocked. Keep the encryption phrase private.');
    await list();
    polling=setInterval(()=>{list().catch(e=>status(e.message));},3500);
  }catch(e){status(e.message);}finally{unlockButton.disabled=false;}
}
async function list(){
  if(!key)return;
  const data=await api('list');const box=$('inbox');box.replaceChildren();
  if(!data.messages.length){const empty=document.createElement('small');empty.textContent='No unread messages.';box.append(empty);return;}
  for(const m of data.messages){
    const row=document.createElement('div');row.className='message';
    const meta=document.createElement('div'),strong=document.createElement('strong'),sub=document.createElement('small');
    strong.textContent=m.sender+' · '+m.kind;sub.textContent=new Date(Number(m.created)*1000).toLocaleString();
    meta.append(strong,sub);
    const btn=document.createElement('button');btn.textContent='Open once';
    btn.addEventListener('click',()=>openMessage(m.id,btn));row.append(meta,btn);box.append(row);
  }
}
async function openMessage(id,button){
  button.disabled=true;clearPreview();
  try{
    const data=await api('open',{id});
    const bytes=await decrypt(data);
    const out=$('preview');out.replaceChildren();
    if(data.kind==='text'){const p=document.createElement('p');p.style.whiteSpace='pre-wrap';p.textContent=dec.decode(bytes);out.append(p);}
    else {
      const wrapper=JSON.parse(dec.decode(bytes));
      if(!wrapper || !['image','video','audio'].includes(wrapper.kind) || typeof wrapper.mime!=='string' || !wrapper.mime.startsWith(wrapper.kind+'/'))throw Error('Invalid attachment format');
      const binary=unb64(wrapper.body);
      if(binary.byteLength>8*1024*1024)throw Error('Attachment too large');
      const mediaBlob=new Blob([binary],{type:wrapper.mime});currentUrl=URL.createObjectURL(mediaBlob);
      const el=document.createElement(wrapper.kind==='image'?'img':wrapper.kind==='video'?'video':'audio');
      el.src=currentUrl;if(el.tagName!=='IMG'){el.controls=true;el.playsInline=true;}el.alt='One-time attachment';out.append(el);
    }
    $('opened').classList.remove('hidden');$('opened').scrollIntoView({behavior:'smooth',block:'start'});status('Message consumed and deleted from server.');list().catch(()=>{});
  }catch(e){status('Message consumed/unavailable: '+e.message);await list().catch(()=>{});}
}
async function send(kind,bytes){
  const name=$('nickname').value.trim();
  if(!/^[\p{L}\p{N} _.-]{1,32}$/u.test(name))throw Error('Enter a display name (1–32 letters/numbers).');
  if(!key)throw Error('Room locked');
  const payload=await encrypt(bytes);
  await api('send',{sender:name,kind,...payload});status('Encrypted item queued for one-time opening.');
}
$('unlock').addEventListener('click',unlock);$('logout').addEventListener('click',lock);
$('closePreview').addEventListener('click',clearPreview);
$('sendText').addEventListener('click',async()=>{
  const btn=$('sendText');btn.disabled=true;
  try{const text=$('message').value;if(!text.trim()||enc.encode(text).length>10000)throw Error('Enter 1–10 KB of text.');
    await send('text',enc.encode(text));$('message').value='';
  }catch(e){status(e.message);}finally{btn.disabled=false;}
});
async function sendAttachment(file){
  if(!file || file.size>6*1024*1024 || !file.size)throw Error('Choose a file under 6 MB.');
  const kind=file.type.startsWith('image/')?'image':file.type.startsWith('video/')?'video':file.type.startsWith('audio/')?'audio':null;
  if(!kind)throw Error('Only image, video and audio attachments are supported.');
  const bytes=new Uint8Array(await file.arrayBuffer());
  const payload={kind,mime:file.type,body:b64(bytes)};
  await send(kind,enc.encode(JSON.stringify(payload)));
}
$('sendFile').addEventListener('click',async()=>{
  const btn=$('sendFile');btn.disabled=true;
  try{await sendAttachment($('attachment').files[0]);$('attachment').value='';$('selectedFile').textContent='No attachment selected';}
  catch(e){status(e.message);}finally{btn.disabled=false;}
});
function chooseFile(accept){const input=$('attachment');input.value='';input.accept=accept;input.click();}
$('pickImage').addEventListener('click',()=>chooseFile('image/*'));
$('pickVideo').addEventListener('click',()=>chooseFile('video/*'));
$('pickAudio').addEventListener('click',()=>chooseFile('audio/*'));
$('attachment').addEventListener('change',()=>{
 const file=$('attachment').files[0];$('selectedFile').textContent=file?file.name+' · '+(file.size/1048576).toFixed(1)+' MB':'No attachment selected';
});
$('record').addEventListener('click',async()=>{
  try{
    if(recorder?.state==='recording'){recorder.stop();$('record').textContent='● Record voice';$('record').classList.remove('recording');$('record').setAttribute('aria-pressed','false');return;}
    if(!navigator.mediaDevices?.getUserMedia||!window.MediaRecorder)throw Error('Voice recording unsupported in this browser.');
    const stream=await navigator.mediaDevices.getUserMedia({audio:true});
    const mime=['audio/webm;codecs=opus','audio/mp4','audio/webm'].find(x=>MediaRecorder.isTypeSupported(x));
    recorder=new MediaRecorder(stream,mime?{mimeType:mime}:{});chunks=[];
    recorder.ondataavailable=e=>{if(e.data.size)chunks.push(e.data);};
    recorder.onstop=async()=>{
      stream.getTracks().forEach(t=>t.stop());$('recordState').textContent='';
      try{const blob=new Blob(chunks,{type:recorder.mimeType||'audio/webm'});
        await sendAttachment(new File([blob],'voice-note',{type:blob.type}));}
      catch(e){status(e.message);}
    };
    recorder.start();$('record').textContent='■ Stop & send';$('record').classList.add('recording');$('record').setAttribute('aria-pressed','true');
    $('recordState').textContent='Recording… maximum 60 seconds';
    const activeRecorder=recorder;setTimeout(()=>{if(activeRecorder.state==='recording'){activeRecorder.stop();$('record').textContent='● Record voice';$('record').classList.remove('recording');$('record').setAttribute('aria-pressed','false');}},60000);
  }catch(e){status(e.message);}
});
window.addEventListener('pagehide',clearPreview);
api('status').then(r=>{csrf=r.csrf;$('unlock').disabled=false;if(r.ok)status('Session active. Re-enter access password and encryption phrase to unlock.');})
.catch(()=>status('Server requires configuration or is unavailable.'));
})();
</script>
</body>
</html>
