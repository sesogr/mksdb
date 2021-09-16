<?php declare(strict_types=1);
require_once __DIR__ . '/../web/lib/testframeworkinatweet.inc.php';
require_once __DIR__ . '/../web/lib/functions.inc.php';
require_once __DIR__ . '/../web/lib/test-functions.inc.php';
require_once __DIR__ . '/../web/lib/types.inc.php';
$config = include __DIR__ . '/../web/config.inc.php';
$db = new PDO(
    sprintf(
        "mysql:host=%s;port=%d;dbname=%s;charset=%s",
        $config['address'],
        $config['port'],
        $config['database'],
        'utf8mb4'
    ),
    $config['username'],
    $config['password']
);

// for search v2
it('[V2] yields one single result for \'Wolfgangsee Rößl\' (KSD-T-6)', function () use ($db) {
    return count(gatherSearchResults('Wolfgangsee Rößl', $db)) === 1;
});
it('[V2] can search for origin \'Himmelstür\' (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV2($db, 'Himmelstür', 1, function (SearchResult $r) {
        return mb_stripos($r->origin, 'HIMMELSTÜR') !== false;
    });
});
it('[V2] can search for origin \'Viktoria\' (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV2($db, 'Viktoria', 1, function (SearchResult $r) {
        return mb_stripos($r->origin, 'VIKTORIA') !== false;
    });
});
it('[V2] ignores letter-case for keywords like \'vIkToRiA\' (KSD-T-3)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV2($db, 'vIkToRiA', 1, function (SearchResult $r) {
        return mb_strpos($r->origin, 'Viktoria') !== false;
    });
});
it('[V2] ignores letter-case for phrases like "NoCh EiNmAl DiE hÄnDe" (KSD-T-3)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV2($db, '"NoCh EiNmAl DiE hÄnDe"', 1, function (SearchResult $r) {
        return mb_strpos($r->title, 'Hände') !== false;
    });
});
it('[V2] can search for origin \'Himmelstür\' without \'Kinostar\' (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV2($db, 'Himmelstür -Kinostar', 1,
        function (SearchResult $r) {
            return mb_stripos($r->origin, 'HIMMELSTÜR') !== false && $r->title !== 'Kinostar';
        });
});
it('[V2] can search for origin \'Himmelstür\' without "Odeon 0-4756" (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV2($db, 'Himmelstür -"Odeon 0-4756', 1,
        function (SearchResult $r) {
            return mb_stripos($r->origin, 'HIMMELSTÜR') !== false && $r->title !== 'Kinostar';
        });
});

// for search v1
it('[V1] yields one single result for \'Wolfgangsee Rößl\' (KSD-T-6)', function () use ($db) {
    return count(gatherSearchResultsWithWildcards('Wolfgangsee Rößl', $db)) === 1;
});
it('[V1] can search for origin \'Himmelstür\' (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV1($db, 'Himmelstür', 1, function (SearchResult $r) {
        return mb_stripos($r->origin, 'HIMMELSTÜR') !== false;
    });
});
it('[V1] can search for origin \'Viktoria\' (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV1($db, 'Viktoria', 1, function (SearchResult $r) {
        return mb_stripos($r->origin, 'VIKTORIA') !== false;
    });
});
it('[V1] ignores letter-case for keywords like \'vIkToRiA\' (KSD-T-3)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV1($db, 'vIkToRiA', 1, function (SearchResult $r) {
        return mb_strpos($r->origin, 'Viktoria') !== false;
    });
});
it('[V1] ignores letter-case for phrases like "NoCh EiNmAl DiE hÄnDe" (KSD-T-3)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV1($db, '"NoCh EiNmAl DiE hÄnDe"', 1, function (SearchResult $r) {
        return mb_strpos($r->title, 'Hände') !== false;
    });
});
it('[V1] can search for origin \'Himmelstür\' without \'Kinostar\' (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV1($db, 'Himmelstür -Kinostar', 1,
        function (SearchResult $r) {
            return mb_stripos($r->origin, 'HIMMELSTÜR') !== false && $r->title !== 'Kinostar';
        });
});
it('[V2] can search for origin \'Himmelstür\' without "Odeon 0-4756" (KSD-T-1)', function () use ($db) {
    return hasAtLeastSoManyResultsWhichAllMatchCallbackV2($db, 'Himmelstür -"Odeon 0-4756', 1,
        function (SearchResult $r) {
            return mb_stripos($r->origin, 'HIMMELSTÜR') !== false && $r->title !== 'Kinostar';
        });
});
//TODO add test for wildcards
