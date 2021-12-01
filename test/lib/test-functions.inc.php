<?php declare(strict_types=1);
function hasAtLeastSoManyResultsWhichAllMatchCallback(PDO $db, string $search, int $minResults, callable $matcher): bool
{
    $stats = array_reduce(
        gatherSearchResultsV3($db, ['' => $search], true),
        function ($accu, $item) use ($matcher) {
            return [
                'total' => 1 + $accu['total'],
                'match' => ($matcher($item) ? 1 : 0) + $accu['match'],
            ];
        },
        ['total' => 0, 'match' => 0]
    );
    return $stats['total'] >= $minResults && $stats['match'] === $stats['total'];
}

function hasSameElements(iterable $actual, iterable $expected): bool {
    $a = array_unique($actual instanceof Traversable ? iterator_to_array($actual) : $actual);
    $b = array_unique($expected instanceof Traversable ? iterator_to_array($expected) : $expected);
    sort($a);
    sort($b);
    return $a == $b;
}
