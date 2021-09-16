<?php declare(strict_types=1);

require_once __DIR__ . '/../web/lib/testframeworkinatweet.inc.php';
require_once __DIR__ . '/../web/lib/test-functions.inc.php';
require_once __DIR__ . '/../helpers/lib/index_db.inc.php';

function createIndexer(): DbIndexer {
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

    return new DbIndexer($db, new SubscribableLogger());
}


$indexer = createIndexer();

it('splits words correctly', function() use($indexer){
    $result = $indexer->splitText("foo bar   \n baz ");
    $expectation = ['foo', 'bar', 'baz'];
    return $result === $expectation;
});

it('can put data into a map without duplicates', function() use($indexer) {
    $map = [
        'a' => ['aa', 'aaaa'],
        'c' => ['c0' => ['c01']]
    ];

    $indexer->mapDeepPutOrAdd($map, 'aaa', 'a');
    $indexer->mapDeepPutOrAdd($map, 'aa', 'a');
    $indexer->mapDeepPutOrAdd($map, 'c01', 'c', 'c0');
    $indexer->mapDeepPutOrAdd($map, 'c02', 'c', 'c0');
    $indexer->mapDeepPutOrAdd($map, 'c11', 'c', 'c1');
    $indexer->mapDeepPutOrAdd($map, 'bx', 'b', 'b_1', 'b_2', 'b_3');

    $expectation = [
        'a' => ['aa', 'aaa', 'aaaa'],
        'b' => ['b_1' => ['b_2' => ['b_3' => ['bx']]]],
        'c' => ['c0' => ['c01', 'c02'], 'c1' => ['c11']]
    ];
    return compareArrayDeep($map, $expectation);
});

it('can merge a map without duplicates', function() use($indexer) {
    $map = [
        'a' => ['a1' => ['a1a'], 'a3' => ['a3a']],
        'b' => ['b1' => ['b1a', 'b1b']]
    ];
    $mapB = [
        'a' => ['a2' => ['a2a'], 'a1' => ['a1a']],
        'c' => ['cc', 'ccc']
    ];

    $indexer->mapDeepMerge($map, $mapB);

    $expectation = [
        'a' => ['a2' => ['a2a'], 'a1' => ['a1a'], 'a3' => ['a3a']],
        'b' => ['b1' => ['b1a', 'b1b']],
        'c' => ['cc', 'ccc']
    ];
    return compareArrayDeep($map, $expectation);
});

it('lists all tables which should be indexed', function () use ($indexer) {
    return $indexer->listTables() == ['mks_city', 'mks_collection', 'mks_genre', 'mks_person', 'mks_publisher', 'mks_song', 'mks_source'];
});

