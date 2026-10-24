const assert=require('node:assert/strict');
const {test}=require('node:test');
const fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../chat/app.js'),'utf8');
test('typing events are room-scoped, normalize user IDs, and ignore unknown rooms',()=>{
 const handlers={};const state=vm.createContext({socket:{on(name,fn){handlers[name]=fn}},rooms:{value:[{name:'one'},{name:'two'}]},typingByRoom:{value:{}}});
 const start=source.indexOf('            socket.on("userTyping",');
 vm.runInContext(source.slice(start,source.indexOf('            /*',start)),state);
 handlers.userTyping({room:'one',userId:123});handlers.userTyping({room:'one',userId:'123'});handlers.userTyping({room:'two',userId:'other'});handlers.userTyping({room:'unjoined',userId:'hidden'});
 assert.equal(state.typingByRoom.value.one.length,1);assert.equal(state.typingByRoom.value.one[0],'123');assert.equal(state.typingByRoom.value.unjoined,undefined);
 handlers.userStopTyping({room:'two',userId:123});assert.equal(state.typingByRoom.value.one.length,1);
 handlers.userStopTyping({room:'one',userId:'123'});assert.equal(state.typingByRoom.value.one.length,0);assert.equal(state.typingByRoom.value.two[0],'other');
});
test('stop-typing debounce targets the original room after switching tabs',()=>{
 const emitted=[];let timeout;
 const state=vm.createContext({isSocketReady:()=>true,currentRoom:{value:'one'},isBanned:{value:false},userId:{value:'user'},socket:{emit(event,data){emitted.push(data)}},clearTimeout(){},setTimeout(fn){timeout=fn;return 1},typingTimeout:null});
 const start=source.indexOf('        function sendTyping()');vm.runInContext(source.slice(start,source.indexOf('        function scroll()',start)),state);
 state.sendTyping();state.currentRoom.value='two';timeout();
 assert.equal(emitted[0].command,'typing');assert.equal(emitted[1].command,'stopTyping');assert.equal(emitted[1].data.recipientId,'one');
});
