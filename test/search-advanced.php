<?php declare(strict_types=1);

require_once __DIR__ . '/lib/testframeworkinatweet.inc.php';
require_once __DIR__ . '/lib/test-functions.inc.php';
require_once __DIR__ . '/../web/lib/functions.inc.php';
require_once __DIR__ . '/../web/lib/types.inc.php';

$config = include __DIR__ . '/../web/config.inc.php';
$db = new PDO('mysql:host=127.0.0.1;port=11006;dbname=schlager;charset=utf8mb4', 'schlager', 'zorofzoftumev');

it('can find results for title => \'blume\'', function () use($db){
    foreach(gatherSearchResultsByFields(['song-name' => 'blume', 'composer' => '"Kunz Hans"'], $db) as $res)
        if(mb_stripos($res->title, 'blume', 0, 'UTF-8') === false)
            return false;
    return true;
});
it('returns exactly one result for title => \'blume\', copyrightYear => \'1931\'', function () use($db){
    foreach(gatherSearchResultsByFields(['song-name' => 'blume', 'song-cpy_y' => '1931'], $db) as $res)
        if(mb_stripos($res->title, 'blume', 0, 'UTF-8') === false
            and stripos($res->copyright_year, '1931') === false)
            return false;
    return true;
});
it('can find results for title => \'blume\', composer => \'"Kunz Hans"\'', function () use($db){
    return count(gatherSearchResultsByFields(['song-name' => 'blume', 'composer' => '"Kunz Hans"'], $db)) === 1;
});
