document.addEventListener('DOMContentLoaded', () => {
    const [input, url, path, apiUrl] = [
        'cku3chddh0000p386r3190y81',
        'cku3g67ql0008p386s8bc388w',
        'cku3clqhv0001p38631apyrki',
        'cku3cmhqv0002p386ifj66d5r',
    ].map(id=>document.getElementById(id));
    const updatePaths = () => {
        path.innerHTML = input.value ? input.value.replace(/^\/+|\/+$/g, '') + '/' : '';
        url.innerHTML = apiUrl.innerHTML = input.value ? encodeURI(input.value.replace(/^\/+|\/+$/g, '')) + '/' : '';
    };
    input.addEventListener('keyup', updatePaths);
    updatePaths();
});
