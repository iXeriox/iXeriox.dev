const assert = require('node:assert/strict');
const { test } = require('node:test');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../chat/app.js'), 'utf8');
const context = vm.createContext({ Vue: {createApp:()=>({mount(){}})} });
vm.runInContext(source, context);
const owner = {id:'owner',displayName:'Owner',privilegeClass:'Room Owner',isStaff:true};
const room = () => ({owner:'owner',raw:{privilegeClasses:{Admins:{order:80},Members:{order:10},Guests:{order:0}},defaultPrivilegeClass:'Members',userPrivilegeClasses:{owner:'Guests'}}});

test('owner appears in assigned class with its actual order', () => {
    const groups = context.groupUsersByPrivilegeClass(room(),[owner,{id:'admin',privilegeClass:'Admins'}]);
    assert.equal(groups[0].name,'Admins');
    assert.equal(groups[1].name,'Guests');
    assert.equal(groups[1].order,0);
    assert(context.isRoomOwner(room(),groups[1].users[0]));
    assert.equal(groups[1].users[0].privilegeClass,'Guests');
});

test('synthetic owner labels fall back to room default, including nested payloads', () => {
    const r=room();delete r.raw.userPrivilegeClasses.owner;
    assert.equal(context.memberRoomClass(r,owner),'Members');
    assert.equal(context.memberRoomClass({raw:{rawData:{defaultPrivilegeClass:'Guests',userPrivilegeClasses:{owner:'Admins'}}}},owner),'Admins');
    assert.equal(context.memberRoomClass({raw:{rawData:{defaultPrivilegeClass:'Guests'}}},owner),'Guests');
});

test('ownership follows owner ID independently of class name', () => {
    const r=room();
    assert(!context.isRoomOwner(r,{id:'other',privilegeClass:'Room Owner'}));
    assert(context.isRoomOwner(r,{id:'owner',privilegeClass:'Guests'}));
    assert(!context.isRoomOwner({},{}));
    r.raw.privilegeClasses['Room Owner']={order:5};
    delete r.raw.userPrivilegeClasses.owner;
    assert.equal(context.memberRoomClass(r,owner),'Room Owner');
    assert.equal(context.groupUsersByPrivilegeClass(r,[owner])[0].order,5);
});

test('owner can change own class, moderators cannot assign owner class', () => {
    const state=vm.createContext({canAssignRoomClasses:{value:true},currentRoomObj:{value:room()},isOwnerOfCurrentRoom:{value:true},isSiteStaff:{value:false},userId:{value:'owner'},isRoomOwner:context.isRoomOwner});
    const start=source.indexOf('        function canManageUser(');
    vm.runInContext(source.slice(start,source.indexOf('        function canAssignClass(',start)),state);
    assert.equal(state.canManageUser(owner),true);
    state.isOwnerOfCurrentRoom.value=false;state.userId.value='moderator';
    assert.equal(state.canManageUser(owner),false);
    state.isSiteStaff.value=true;
    assert.equal(state.canManageUser(owner),true);
    state.canAssignRoomClasses.value=false;
    assert.equal(state.canManageUser(owner),false);
});

test('required orders -1 and 0 are protected independent of class name', () => {
    for (const order of [-1,0,'-1','0']) assert(context.isRequiredPrivilegeClass({order}));
    for (const order of [-2,1,10,99,undefined,null,'']) assert(!context.isRequiredPrivilegeClass({order}));
    assert(!context.isRequiredPrivilegeClass(undefined));
});

test('delete handler blocks required classes before confirmation or server commands', () => {
    let confirmed=0,sent=0;
    const state=vm.createContext({currentRoomObj:{value:{raw:{privilegeClasses:{Guests:{order:0},Banned:{order:-1},Members:{order:10}}}}},roomPrivilegeClasses:context.roomPrivilegeClasses,isRequiredPrivilegeClass:context.isRequiredPrivilegeClass,adminError:{value:''},window:{confirm(){confirmed++;return true}},sendAdminCommand(){sent++}});
    const start=source.indexOf('        function deletePrivilegeClass(');
    vm.runInContext(source.slice(start,source.indexOf('        function setPrivilegeClassOrder(',start)),state);
    state.deletePrivilegeClass('Guests');state.deletePrivilegeClass('Banned');
    assert.equal(confirmed,0);assert.equal(sent,0);assert.match(state.adminError.value,/required/);
    state.deletePrivilegeClass('Members');assert.equal(confirmed,1);assert.equal(sent,1);
});
