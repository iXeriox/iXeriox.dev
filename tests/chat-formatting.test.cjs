const assert = require('node:assert/strict');
const { test } = require('node:test');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../chat/app.js'), 'utf8');
const context = vm.createContext({ Vue: { createApp: () => ({ mount() {} }) } });
vm.runInContext(source, context);
const parts = value => JSON.parse(JSON.stringify(context.styledMessageParts(value)));

test('incoming bold, italic, underline and nested styles', () => {
    assert.deepEqual(parts('**bold** *italic* __under__'), [
        {text:'bold', bold:true}, {text:' '}, {text:'italic', italic:true},
        {text:' '}, {text:'under', underline:true}
    ]);
    assert.deepEqual(parts('**__both__**'), [{text:'both', bold:true, underline:true}]);
});

test('newlines and untrusted HTML remain text; unmatched markers stay visible', () => {
    const text = '<img src=x onerror=alert(1)>\n<script>alert(1)</script>';
    assert.deepEqual(parts(text), [{text}]);
    assert.deepEqual(parts('**unclosed'), [{text:'**unclosed'}]);
    assert.deepEqual(parts('**first\nsecond**'), [{text:'first\nsecond', bold:true}]);
    assert.deepEqual(parts(''), []);
});

// Exercise the actual editor handlers with a textarea selection and Vue refs.
const editorSource = source.slice(source.indexOf('        function toggleMultiline('), source.indexOf('        function sendMessage() {', source.indexOf('        function toggleMultiline(')));
function editor(value, start, end) {
    const input = { selectionStart:start, selectionEnd:end, focus() {}, setSelectionRange(a,b) { this.selectionStart=a; this.selectionEnd=b; } };
    const state = {multiline:{value:false}, message:{value}, messageInput:{value:input}, isBanned:{value:false}, connected:{value:true}, nextTick:fn=>fn(), sendTyping() {}, sent:0};
    state.sendMessage=()=>state.sent++;
    vm.createContext(state);
    vm.runInContext(editorSource, state);
    return state;
}

test('toolbar wraps selection and restores selection inside markers', () => {
    const state = editor('hello world',6,11);
    state.formatMessage('**');
    assert.equal(state.message.value,'hello **world**');
    assert.equal(state.messageInput.value.selectionStart,8);
    assert.equal(state.messageInput.value.selectionEnd,13);
    state.formatMessage('__');
    assert.equal(state.message.value,'hello **__world__**');
});

test('empty selection and disabled editor', () => {
    const state = editor('ab',1,1);
    state.formatMessage('*');
    assert.equal(state.message.value,'a**b');
    assert.equal(state.messageInput.value.selectionStart,2);
    state.isBanned.value=true;
    state.formatMessage('**');
    assert.equal(state.message.value,'a**b');
});

test('multiline Enter stays native, Shift+Enter sends, IME Enter never sends', () => {
    const state = editor('',0,0);
    state.multiline.value=true;
    let prevented=0;
    const event={key:'Enter',preventDefault(){prevented++}};
    state.messageKeydown(event);
    state.messageKeydown({...event,shiftKey:true,isComposing:true});
    assert.equal(state.sent,0);
    assert.equal(prevented,0);
    state.messageKeydown({...event,shiftKey:true});
    assert.equal(state.sent,1);
    assert.equal(prevented,1);
});


test('multiline toggles the field mode, retains selection and flattens lines on return', () => {
    const state = editor('hello world', 6, 11);
    assert.equal(state.multiline.value, false);
    state.toggleMultiline();
    assert.equal(state.multiline.value, true);
    assert.equal(state.message.value, 'hello world');
    assert.equal(state.messageInput.value.selectionStart, 6);
    assert.equal(state.messageInput.value.selectionEnd, 11);
    state.message.value = 'hello\nworld';
    state.toggleMultiline();
    assert.equal(state.multiline.value, false);
    assert.equal(state.message.value, 'hello world');
    state.connected.value = false;
    state.toggleMultiline();
    assert.equal(state.multiline.value, false);
});

test('single-line Enter sends even with Shift held', () => {
    const state = editor('hello', 5, 5);
    let prevented = false;
    state.messageKeydown({key:'Enter', shiftKey:true, preventDefault() { prevented=true; }});
    assert.equal(state.sent, 1);
    assert.equal(prevented, true);
});

test('clicking a user prepends their name, preserves draft and focuses input', () => {
    let focused=false;
    const state=vm.createContext({
        isBanned:{value:false}, connected:{value:true}, message:{value:'hello'},
        showMobileUsers:{value:true}, memberLabel:user=>user.displayName,
        nextTick:fn=>fn(),messageInput:{value:{focus(){focused=true;},setSelectionRange(a,b){state.selection=[a,b];}}}
    });
    const start=source.indexOf('        function addressUser(');
    vm.runInContext(source.slice(start,source.indexOf('        function toggleMultiline(',start)),state);
    state.addressUser({displayName:'iXeriox'});
    assert.equal(state.message.value,'iXeriox: hello');
    assert.equal(state.showMobileUsers.value,false);
    assert(focused);
    assert.deepEqual(state.selection,[14,14]);
    state.addressUser({displayName:'iXeriox'});
    assert.equal(state.message.value,'iXeriox: hello');
    state.isBanned.value=true;
    state.addressUser({displayName:'Other'});
    assert.equal(state.message.value,'iXeriox: hello');
});
