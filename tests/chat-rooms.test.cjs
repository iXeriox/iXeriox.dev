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
