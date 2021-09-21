<?php declare(strict_types=1);

require_once __DIR__ . '/lib/testframeworkinatweet.inc.php';
require_once __DIR__ . '/lib/test-functions.inc.php';
require_once __DIR__ . '/../web/lib/utils.inc.php';

use function Utils\arrayMergeWithCustomResolver;

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