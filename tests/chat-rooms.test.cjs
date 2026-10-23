const assert=require('node:assert/strict');
const {test}=require('node:test');
const fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../chat/app.js'),'utf8');
const context=vm.createContext({Vue:{createApp:()=>({mount(){}})}});
vm.runInContext(source,context);

test('server chatTitle and chatTopic become searchable display fields',()=>{
 const room=context.normalizeRoomListing({name:'internal-id',chatTitle:'Development',chatTopic:'Build and test',memberCount:3,visibility:'private_users'});
 assert.equal(room.roomName,'Development');assert.equal(room.topic,'Build and test');assert.equal(room.name,'internal-id');assert.equal(room.memberCount,3);
 for(const query of ['development','TEST','internal-id','  Build  ',''])assert.equal(context.matchingRooms([room],query).length,1);
 assert.equal(context.matchingRooms([room],'missing').length,0);
 assert.equal(room.visibility,'private_users');
});

test('legacy listings fall back safely; malformed rooms are discarded',()=>{
 assert.equal(context.normalizeRoomListing({name:'one',roomName:'Legacy'}).roomName,'Legacy');
 assert.equal(context.normalizeRoomListing({name:'two'}).roomName,'two');
 assert.equal(context.normalizeRoomListing({name:'two',memberCount:-1}).memberCount,0);
 assert.equal(context.normalizeRoomListing({chatTitle:'Missing ID'}),null);
 assert.equal(context.normalizeRoomListing(null),null);
});

test('creation payload matches V2 fields and keeps display name separate from address',()=>{
 const request=context.createRoomRequest('  Weekend gaming  ','','**Welcome**','private');
 assert.deepEqual(JSON.parse(JSON.stringify(request)),{name:'weekend-gaming',chatTitle:'Weekend gaming',chatTopic:'**Welcome**',visibility:'private'});
 assert.equal(context.createRoomRequest('Café chat','Custom_123','','public').name,'Custom_123');
 assert.equal(context.suggestedRoomId('Café chat'),'cafe-chat');
 assert.equal(context.suggestedRoomId('a'.repeat(100)).length,64);
});

test('invalid creation requests are rejected before emission',()=>{
 for(const args of [['','','','public'],['Valid','space in id','','public'],['Valid','valid','','private_users'],['Valid','valid','x'.repeat(501),'public'],['a'.repeat(65),'valid','','public']]){
  assert.throws(()=>context.createRoomRequest(...args));
 }
 assert.equal(context.createRoomRequest('Chat','chat','','invite').visibility,'invite');
});
