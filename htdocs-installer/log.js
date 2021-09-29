function clear() {
    const blocks = document.body.getElementsByTagName('div');
    document.body.removeChild(blocks[blocks.length - 1]);
    persist();
}
function persist() {
    document.body.appendChild(document.createElement('div'));
}
function write(text) {
    const blocks = document.body.getElementsByTagName('div');
    blocks[blocks.length - 1].appendChild(document.createTextNode(text));
    document.documentElement.scrollTop = document.documentElement.scrollHeight;
}
