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
it('can find results for title => \'blume\'', function () use($db){
    foreach(gatherSearchResultsV3($db, ['song-name' => 'blume', 'composer' => '"Kunz Hans"']) as $res)
        if(mb_stripos($res->title, 'blume', 0, 'UTF-8') === false)
            return false;
    return true;
});
it('returns exactly one result for title => \'blume\', copyrightYear => \'1931\'', function () use($db){
    foreach(gatherSearchResultsV3($db, ['song-name' => 'blume', 'song-cpr_y' => '1931']) as $res)
        if(mb_stripos($res->title, 'blume', 0, 'UTF-8') === false
            and stripos($res->copyright_year, '1931') === false)
            return false;
    return true;
});
it('can find results for title => \'blume\', composer => \'"Kunz Hans"\'', function () use($db){
    return count(gatherSearchResultsV3($db, ['song-name' => 'blume', 'composer' => '"Kunz Hans"'])) === 1;
});
it('yields one single result for \'Wolfgangsee Rößl\' (KSD-T-6)', function () use ($db) {
    return count(gatherSearchResultsV3($db, ['' => 'Wolfgangsee Rößl'])) === 1;
});
it('can search for origin \'Himmelstür\' (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallback($db, 'Himmelstür', 1, function ($r) {
        return mb_stripos($r->origin, 'HIMMELSTÜR') !== false;
    });
});
it('can search for origin \'Viktoria\' (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallback($db, 'Viktoria', 1, function ($r) {
        return mb_stripos($r->origin, 'VIKTORIA') !== false;
    });
});
it('ignores letter-case for keywords like \'vIkToRiA\' (KSD-T-3)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallback($db, 'vIkToRiA', 1, function ($r) {
        return mb_strpos($r->origin, 'Viktoria') !== false;
    });
});
it('ignores letter-case for phrases like "NoCh EiNmAl DiE hÄnDe" (KSD-T-3)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallback($db, '"NoCh EiNmAl DiE hÄnDe"', 1, function ($r) {
        return mb_strpos($r->title, 'Hände') !== false;
    });
});
it('can search for origin \'Himmelstür\' without \'Kinostar\' (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallback($db, 'Himmelstür -Kinostar', 1,
        function ($r) {
            return mb_stripos($r->origin, 'HIMMELSTÜR') !== false && $r->title !== 'Kinostar';
        });
});
it('can search for origin \'Himmelstür\' without "Odeon 0-4756" (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallback($db, 'Himmelstür -"Odeon 0-4756', 1,
        function ($r) {
            return mb_stripos($r->origin, 'HIMMELSTÜR') !== false && $r->title !== 'Kinostar';
        });
});
//TODO add test for wildcards
