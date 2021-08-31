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

it('yields one single result for "Wolfgangsee Rößl" (KSD-T-6)', fn() => count(gatherSearchResults('Wolfgangsee Rößl', $db)) === 1);
it('can search for origin "Himmelstür" (KSD-T-1)', fn() => hasAtLeastSoManyResultsWhichAllMatchCallback($db, 'Himmelstür', 1, fn(SearchResult $r) => mb_stripos($r->origin, 'HIMMELSTÜR') !== false));
it('can search for origin "Viktoria" (KSD-T-1)', fn() => hasAtLeastSoManyResultsWhichAllMatchCallback($db, 'Viktoria', 1, fn(SearchResult $r) => mb_stripos($r->origin, 'VIKTORIA') !== false));
it('ignores letter-case for keywords like "vIkToRiA" (KSD-T-3)', fn() => hasAtLeastSoManyResultsWhichAllMatchCallback($db, 'vIkToRiA', 1, fn(SearchResult $r) => mb_strpos($r->origin, 'Viktoria') !== false));
it('ignores letter-case for phrases like "NoCh EiNmAl DiE hÄnDe" (KSD-T-3)', fn() => hasAtLeastSoManyResultsWhichAllMatchCallback($db, '"NoCh EiNmAl DiE hÄnDe"', 1, fn(SearchResult $r) => mb_strpos($r->title, 'Hände') !== false));
