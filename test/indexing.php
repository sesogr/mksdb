<?php declare(strict_types=1);
require_once __DIR__ . '/../web/lib/testframeworkinatweet.inc.php';
require_once __DIR__ . '/../helpers/lib/index_db.inc.php';

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

function myLog(string $level, string $msg): void {
    // NOP
}

it('lists all tables which should be indexed', fn() => listTables($db) == ['mks_city', 'mks_collection', 'mks_genre', 'mks_person', 'mks_publisher', 'mks_song', 'mks_source']);
