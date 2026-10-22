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

test('play replaces the list, AddQueue preserves order and starts an idle player', () => {
    const first = {id:'M7lc1UVf-VE'}, second = {id:'aqz-KE-bpKQ'};
    const state = {current:null,queue:[]};
    const started = context.updateYoutubeQueue(state,[first,second]);
    assert.equal(started.current, first);
    assert.deepEqual(json(started.queue),[second]);
    const appended = context.updateYoutubeQueue(started,[first]);
    assert.deepEqual(json(appended.queue),[second,first]);
    const replaced = context.updateYoutubeQueue(appended,[second],true);
    assert.equal(replaced.current,second);
    assert.equal(replaced.queue.length,0);
    assert.equal(state.current,null);
    assert.equal(context.updateYoutubeQueue(started,[]),started);
});

function roomPlayer() {
    let timer;
    const state = vm.createContext({
        ref:value=>({value}),
        computed:fn=>({get value(){return fn();}}),
        watch() {}, showMobileUsers:{value:false},
        rooms:{value:[{name:'one'},{name:'two'}]}, currentRoom:{value:'one'},
        youtubeItems:context.youtubeItems, updateYoutubeQueue:context.updateYoutubeQueue,
        setTimeout(fn,ms){timer={fn,ms};return 1;}, clearTimeout(){timer=null;}
    });
    const start=source.indexOf('        const youtubeByRoom = ref(');
    const end=source.indexOf('        const mediaByRoom = ref(',start);
    vm.runInContext(source.slice(start,end),state);
    return {state, get timer(){return timer;}, current:()=>vm.runInContext('youtubeState.value',state)};
}

test('server handlers isolate room queues and ignore rooms not joined', () => {
    const player=roomPlayer(), s=player.state;
    s.playYoutube({room:'one',data:'M7lc1UVf-VE'});
    s.AddQueue({room:'one',data:'aqz-KE-bpKQ'});
    s.playYoutube({room:'two',data:'aqz-KE-bpKQ'});
    assert.equal(player.current().current.id,'M7lc1UVf-VE');
    s.playYoutube({room:'unknown',data:'aqz-KE-bpKQ'});
    assert.equal(vm.runInContext('youtubeByRoom.value.unknown',s),undefined);
    s.nextYoutube();
    assert.equal(player.current().current.id,'aqz-KE-bpKQ');
    s.currentRoom.value='two';
    assert.equal(player.current().current.id,'aqz-KE-bpKQ');
});

test('idle closes after five minutes, interactions reset it, playing disables it', () => {
    const player=roomPlayer(),s=player.state;
    s.playYoutube('M7lc1UVf-VE');
    assert.equal(player.timer.ms,300000);
    const oldTimer=player.timer;
    s.resetYoutubeIdle();
    assert.notEqual(player.timer,oldTimer);
    vm.runInContext('youtubePlaying = true',s);
    s.resetYoutubeIdle();
    assert.equal(player.timer,null);
    assert(player.current().current);
    vm.runInContext('youtubePlaying = false',s);
    s.resetYoutubeIdle();
    player.timer.fn();
    assert.equal(player.current().current,null);
});
