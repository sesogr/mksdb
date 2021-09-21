<?php declare(strict_types=1);

require_once __DIR__ . '/lib/testframeworkinatweet.inc.php';
require_once __DIR__ . '/lib/test-functions.inc.php';
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
it('splits words Unicode-aware', function() use($indexer){
    return $indexer->splitText("— foo bar   \n„baz”") === ['foo', 'bar', 'baz'];
});

it('lists all tables which should be indexed', function () use ($indexer) {
    return $indexer->listTables() == ['mks_city', 'mks_collection', 'mks_genre', 'mks_person', 'mks_publisher', 'mks_song', 'mks_source'];
});
