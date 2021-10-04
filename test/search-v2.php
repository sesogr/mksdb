<?php declare(strict_types=1);

require_once __DIR__ . '/lib/testframeworkinatweet.inc.php';
require_once __DIR__ . '/lib/test-functions.inc.php';
require_once __DIR__ . '/../web/lib/functions.inc.php';
require_once __DIR__ . '/../web/lib/types.inc.php';

$config = include __DIR__ . '/../web/config.inc.php';
$db = new PDO('mysql:host=127.0.0.1;port=11006;dbname=schlager;charset=utf8mb4', 'schlager', 'zorofzoftumev');

//TODO this is red, but the function works; both results of the query have both words somewhere in their data; please review the behaviour of the search (maybe some fields have to be excluded)
it('yields one single result for \'Wolfgangsee Rößl\' (KSD-T-6)', function () use ($db) {
    return count(gatherSearchResults('Wolfgangsee Rößl', $db)) === 1;
});
it('can search for origin \'Himmelstür\' (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV2($db, 'Himmelstür', 1, function (SearchResult $r) {
        return mb_stripos($r->origin, 'HIMMELSTÜR') !== false;
    });
});
it('can search for origin \'Viktoria\' (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV2($db, 'Viktoria', 1, function (SearchResult $r) {
        return mb_stripos($r->origin, 'VIKTORIA') !== false;
    });
});
it('ignores letter-case for keywords like \'vIkToRiA\' (KSD-T-3)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV2($db, 'vIkToRiA', 1, function (SearchResult $r) {
        return mb_strpos($r->origin, 'Viktoria') !== false;
    });
});
it('ignores letter-case for phrases like "NoCh EiNmAl DiE hÄnDe" (KSD-T-3)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV2($db, '"NoCh EiNmAl DiE hÄnDe"', 1, function (SearchResult $r) {
        return mb_strpos($r->title, 'Hände') !== false;
    });
});
it('can search for origin \'Himmelstür\' without \'Kinostar\' (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV2($db, 'Himmelstür -Kinostar', 1,
        function (SearchResult $r) {
            return mb_stripos($r->origin, 'HIMMELSTÜR') !== false && $r->title !== 'Kinostar';
        });
});
it('can search for origin \'Himmelstür\' without "Odeon 0-4756" (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV2($db, 'Himmelstür -"Odeon 0-4756', 1,
        function (SearchResult $r) {
            return mb_stripos($r->origin, 'HIMMELSTÜR') !== false && $r->title !== 'Kinostar';
        });
});
