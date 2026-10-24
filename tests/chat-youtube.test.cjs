const assert = require('node:assert/strict');
const { test } = require('node:test');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../chat/app.js'), 'utf8');
const context = vm.createContext({ URL, Vue: { createApp: () => ({mount() {}}) } });
vm.runInContext(source, context);
const json = value => JSON.parse(JSON.stringify(value));

test('accepts IDs and supported YouTube URLs, rejects unsafe and lookalike URLs', () => {
    for (const value of ['M7lc1UVf-VE', 'https://youtu.be/M7lc1UVf-VE?t=20', 'https://www.youtube.com/watch?v=M7lc1UVf-VE', 'https://youtube.com/shorts/M7lc1UVf-VE', 'https://youtube.com/embed/M7lc1UVf-VE']) {
        assert.equal(context.youtubeVideoId(value), 'M7lc1UVf-VE');
    }
    for (const value of ['bad', 'javascript:alert(1)', 'https://youtube.com.evil.test/watch?v=M7lc1UVf-VE', '<iframe>', null]) assert.equal(context.youtubeVideoId(value), null);
});

test('reads server data as IDs, URLs, objects or lists and filters invalid entries', () => {
    assert.deepEqual(json(context.youtubeItems({data:[{id:'M7lc1UVf-VE',title:'Demo'}, 'https://youtu.be/aqz-KE-bpKQ', 'invalid']})), [
        {id:'M7lc1UVf-VE',title:'Demo'}, {id:'aqz-KE-bpKQ',title:'aqz-KE-bpKQ'}
    ]);
    assert.equal(context.youtubeItems({data:{videoId:'M7lc1UVf-VE'}})[0].id, 'M7lc1UVf-VE');
});

function roomPlayer() {
    let timer;
    const state = vm.createContext({
        ref:value=>({value}), computed:fn=>({get value(){return fn();}}), watch() {},
        showMobileUsers:{value:false}, canControlYoutube:{value:true},
        rooms:{value:[{name:'one'},{name:'two'}]}, currentRoom:{value:'one'},
        youtubeItems:context.youtubeItems, youtubeVideoId:context.youtubeVideoId,
        nextTick:fn=>fn(), isSocketReady:()=>true, commands:[],
        setTimeout(fn,ms){timer={fn,ms};return 1;}, clearTimeout(){timer=null;}
    });
    state.socket={emit(event,data){state.commands.push({event,data});}};
    const start=source.indexOf('        const youtubeByRoom = ref(');
    const end=source.indexOf('        const mediaByRoom = ref(',start);
    vm.runInContext(source.slice(start,end),state);
    return {state, get timer(){return timer;}, current:()=>vm.runInContext('youtubeState.value',state)};
}
const snapshot=(room,token,revision=1)=>({room,sessionId:room+'-session',current:{id:'M7lc1UVf-VE',title:'Test',token},queue:[],position:12,playing:false,revision,epoch:'server',serverNow:Date.now()});

test('only server snapshots change playback; room state and revisions are isolated', () => {
    const player=roomPlayer(),s=player.state;
    s.playYoutube('M7lc1UVf-VE');
    assert.equal(s.commands[0].event,'youtubeControl');assert.equal(s.commands[0].data.action,'load');
    assert.equal(player.current().current,null);
    s.applyYoutubeSnapshot(snapshot('one','first',2));
    assert.equal(player.current().current,null);
    s.joinYoutubeListening();
    s.applyYoutubeSnapshot(snapshot('two','second'));
    assert.equal(player.current().current.token,'first');
    s.applyYoutubeSnapshot(snapshot('one','stale',1));
    assert.equal(player.current().current.token,'first');
    s.applyYoutubeSnapshot(snapshot('unknown','bad'));
    assert.equal(vm.runInContext('youtubeByRoom.value.unknown',s),undefined);
    s.currentRoom.value='two';assert.equal(player.current().current,null);s.joinYoutubeListening();assert.equal(player.current().current.token,'second');
});

test('listening requires consent; leaving stays opted out across tracks until a new session', () => {
    const player=roomPlayer(),s=player.state;
    s.applyYoutubeSnapshot(snapshot('one','first'));
    s.joinYoutubeListening();
    const sent=s.commands.length;
    s.resetYoutubeIdle();assert.equal(player.timer.ms,300000);
    vm.runInContext('youtubePlaying = true',s);s.resetYoutubeIdle();assert.equal(player.timer,null);
    vm.runInContext('youtubePlaying = false',s);s.resetYoutubeIdle();player.timer.fn();
    assert.equal(player.current().current,null);assert.equal(s.commands.length,sent);
    s.applyYoutubeSnapshot(snapshot('one','first',2));assert.equal(player.current().current,null);
    s.applyYoutubeSnapshot(snapshot('one','next',3));assert.equal(player.current().current,null);
    assert.equal(vm.runInContext('youtubeInvitation.value',s),false);
    s.applyYoutubeSnapshot({...snapshot('one','fresh',4),sessionId:'new-session'});
    assert.equal(vm.runInContext('youtubeInvitation.value',s),true);
    s.joinYoutubeListening();assert.equal(player.current().current.token,'fresh');
});

test('server timestamps account for playback age and local clock offset', () => {
    const state={position:10,playing:true,serverNow:10000,receivedAt:20000,current:{duration:50}};
    assert.equal(context.youtubePlaybackPosition(state,23000,null),13);
    assert.equal(context.youtubePlaybackPosition(state,23000,10000),13);
    assert.equal(context.youtubePlaybackPosition({...state,playing:false},23000,10000),10);
    assert.equal(context.youtubePlaybackPosition(state,99000,10000),50);
});
