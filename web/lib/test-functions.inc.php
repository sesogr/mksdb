<?php declare(strict_types=1);

function hasAtLeastSoManyResultsWhichAllMatchCallback(PDO $db, string $search, int $minResults, callable $matcher): bool
{
    $stats = array_reduce(
        gatherSearchResults($search, $db),
        fn($accu, SearchResult $item) => [
            'total' => 1 + $accu['total'],
            'match' => ($matcher($item) ? 1 : 0) + $accu['match'],
        ],
        ['total' => 0, 'match' => 0]
    );
    return $stats['total'] >= $minResults && $stats['match'] === $stats['total'];
}
