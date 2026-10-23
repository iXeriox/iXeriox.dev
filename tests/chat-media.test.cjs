const assert=require('node:assert/strict');
const {test}=require('node:test');
const fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../chat/app.js'),'utf8');
const context=vm.createContext({URL,Vue:{createApp:()=>({mount(){}})}});
vm.runInContext(source,context);
const parts=value=>JSON.parse(JSON.stringify(context.messageContentParts(value)));

test('direct image links support query strings, GIFs, and formatting without altering URLs',()=>{
 const p=parts('**https://cdn.example.com/my__image__.PNG?token=a__**');
 assert.equal(p[0].href,'https://cdn.example.com/my__image__.PNG?token=a__');assert.equal(p[0].image,true);assert.equal(p[0].bold,true);
 assert.equal(parts('https://example.com/animated.gif')[0].image,true);
 assert.equal(parts('https://example.com/photo.png?token=ending__')[0].href,'https://example.com/photo.png?token=ending__');
 assert.equal(parts('https://example.com/page')[0].image,false);
 assert.equal(parts('https://example.com/path/:smile:.png')[0].href,'https://example.com/path/:smile:.png');
});

test('labelled URL syntax and Markdown links preserve label and destination',()=>{
 for(const text of ['https://infini9.net(infini9)','[infini9](https://infini9.net)','[https://infini9.net(infini9)](https://infini9.net\\(infini9\\))']){
  const p=parts(text);assert.equal(p[0].text,'infini9');assert.equal(p[0].href,'https://infini9.net/');
 }
 assert.equal(parts('https://en.wikipedia.org/wiki/Example_(test)')[0].href,'https://en.wikipedia.org/wiki/Example_(test)');
 assert.equal(parts('Visit https://example.com.')[2].text,'.');
});

test('unsafe schemes and HTML stay as text; previews are bounded and placeholder spoofing fails',()=>{
 for(const value of ['javascript:alert(1)','data:image/png;base64,anything','<script>alert(1)</script>'])assert(!parts(value).some(part=>part.href));
 assert(!parts('https://name:password@example.com/x.png').some(part=>part.href));
 const images=parts(Array(6).fill('https://example.com/x.png').join(' '));assert.equal(images.filter(part=>part.image).length,4);assert.equal(images.filter(part=>part.href).length,6);
 const spoof=parts('\uE000link0\uE001 https://example.com/a.png');assert(spoof[0].text.includes('\uE000link0\uE001'));assert.equal(spoof.filter(p=>p.href).length,1);
});

test('Unicode emoji and known shortcodes render with existing text styles',()=>{
 const p=parts('**:smile:** :thumbsup: 👩🏽‍💻 :unknown: :constructor:');assert.equal(p[0].text,'😄');assert.equal(p[0].bold,true);assert(p[1].text.includes('👍 👩🏽‍💻 :unknown: :constructor:'));
 assert.equal(context.messageLinkHref('https://example.com/?a=1&b=2'),'/chat/link.php?url=https%3A%2F%2Fexample.com%2F%3Fa%3D1%26b%3D2');
});

test('emoji picker replaces selection and restores focus at Unicode cursor position',()=>{
 let focused=false;
 const state=vm.createContext({isBanned:{value:false},connected:{value:true},message:{value:'hi there'},messageInput:{value:{selectionStart:3,selectionEnd:8,focus(){focused=true},setSelectionRange(a,b){state.selection=[a,b]}}},showEmojiPicker:{value:true},nextTick:fn=>fn(),sendTyping(){}});
 const start=source.indexOf('        function insertEmoji(');vm.runInContext(source.slice(start,source.indexOf('        function messageImageFailed(',start)),state);
 state.insertEmoji('👩🏽‍💻');assert.equal(state.message.value,'hi 👩🏽‍💻');assert(focused);assert.equal(state.showEmojiPicker.value,false);assert.equal(state.selection[0],state.message.value.length);
});
