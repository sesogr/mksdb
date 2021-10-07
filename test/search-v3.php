<?php declare(strict_types=1);
require_once __DIR__ . '/lib/testframeworkinatweet.inc.php';
require_once __DIR__ . '/lib/test-functions.inc.php';
require_once __DIR__ . '/../web/lib/functions.inc.php';
require_once __DIR__ . '/../web/lib/types.inc.php';
$config = include __DIR__ . '/../web/config.inc.php';
$db = new PDO(
    'mysql:host=127.0.0.1;port=11006;dbname=schlager;charset=utf8mb4',
    'schlager',
    'zorofzoftumev',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]
);
$punct = "!#$%&'()*+,-./:;<=>?@[\]^_`{|}~";
//
it('can parse any printable ASCII as long as it\'s double-quoted properly', function () use ($punct) {
    return parseSearchV3(sprintf('abc "def" -ghi -"jkl" m%s "n%1$s" -o%1$s -"p%1$s"', $punct))
        == [['abc', 'm' . $punct], ['def', 'n' . $punct], ['ghi', 'o' . $punct], ['jkl', 'p' . $punct]];
});
it('can parse wildcards at any position in a keyword or phrase', function () {
    return parseSearchV3('abcd* ef*gh *ijkl -mnop* -qr*st -*uvwx "*ab cd" "e*f gh" "ij* kl" "mn *op" "qr s*t" "uv wx*" -"*yz"')
        == [['abcd*', 'ef*gh', '*ijkl'], ['*ab cd', 'e*f gh', 'ij* kl', 'mn *op', 'qr s*t', 'uv wx*'], ['mnop*', 'qr*st', '*uvwx'], ['*yz']];
});
it('can locate a name anywhere in the song details', function () use ($db) {
    return hasSameElements(
        iterateSongIdsForIndexMatches($db, 'myers'),
        ['3780', '4628', '5018', '5180', '10058', '11093', '13380']
    );
});
it('can wildcard-match against all song details', function () use ($db) {
    return hasSameElements(
        iterateSongIdsForIndexMatches($db, 'kä*tner'),
        ['575', '1655', '1695', '2246', '3963', '5180', '5467', '6534', '6803', '6819', '9882', '9908', '10062', '11093', '12659', '14290', '14528', '14616', '12487']
    );
});
it('can wildcard-match a specific detail', function () use ($db) {
    return hasSameElements(
        iterateSongIdsForIndexMatches($db, 'kä*tner', 'writer'),
        ['1655', '2246', '3963', '5467', '11093', '12487']
    );
});
it('can iterate over multiple iterators strictly in order',
    fn() => iterator_to_array(mergeAndSortIterators([
            (fn() => yield from [21, 34])(),
            (fn() => yield from [3])(),
            (fn() => yield from [8, 8, 8])(),
            (fn() => yield from [1, 2, 3, 5, 8, 21])(),
            (fn() => yield from [1, 3, 13])(),
        ])) == [1, 2, 3, 5, 8, 13, 21, 34]
);
