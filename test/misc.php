<?php declare(strict_types=1);

require_once __DIR__ . '/lib/testframeworkinatweet.inc.php';
require_once __DIR__ . '/lib/test-functions.inc.php';
require_once __DIR__ . '/../web/lib/utils.inc.php';

use function Utils\mapDeepMerge;
use function Utils\mapDeepPutOrAdd;
use function Utils\arrayMergeWithCustomResolver;

it('can put data into a map without duplicates', function() {
    $map = [
        'a' => ['aa', 'aaaa'],
        'c' => ['c0' => ['c01']]
    ];

    mapDeepPutOrAdd($map, 'aaa', 'a');
    mapDeepPutOrAdd($map, 'aa', 'a');
    mapDeepPutOrAdd($map, 'c01', 'c', 'c0');
    mapDeepPutOrAdd($map, 'c02', 'c', 'c0');
    mapDeepPutOrAdd($map, 'c11', 'c', 'c1');
    mapDeepPutOrAdd($map, 'bx', 'b', 'b_1', 'b_2', 'b_3');

    $expectation = [
        'a' => ['aa', 'aaa', 'aaaa'],
        'b' => ['b_1' => ['b_2' => ['b_3' => ['bx']]]],
        'c' => ['c0' => ['c01', 'c02'], 'c1' => ['c11']]
    ];
    return compareArrayDeep($map, $expectation);
});

it('can merge a map without duplicates', function() {
    $map = [
        'a' => ['a1' => ['a1a'], 'a3' => ['a3a']],
        'b' => ['b1' => ['b1a', 'b1b']]
    ];
    $mapB = [
        'a' => ['a2' => ['a2a'], 'a1' => ['a1a']],
        'c' => ['cc', 'ccc']
    ];

    mapDeepMerge($map, $mapB);

    $expectation = [
        'a' => ['a2' => ['a2a'], 'a1' => ['a1a'], 'a3' => ['a3a']],
        'b' => ['b1' => ['b1a', 'b1b']],
        'c' => ['cc', 'ccc']
    ];
    return compareArrayDeep($map, $expectation);
});

it('can merge two array with conflict-resolution', function(){
    $a = [
        'a' => 0,
        'b' => 1,
        'c' => 5,
    ];
    $b = [
        'c' => 1,
        'd' => 2
    ];

    $merged = arrayMergeWithCustomResolver($a, $b, function($vA, $vB){
        return max($vA, $vB);
    });

    $expectation = [
        'a' => 0,
        'b' => 1,
        'c' => 5,
        'd' => 2
    ];
    return $merged === $expectation;
});