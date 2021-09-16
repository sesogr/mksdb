<?php declare(strict_types=1);

function hasAtLeastSoManyResultsWhichAllMatchCallbackV2(PDO $db, string $search, int $minResults, callable $matcher): bool
{
    $stats = array_reduce(
        gatherSearchResults($search, $db),
        function ($accu, SearchResult $item) use ($matcher) {
            return [
                'total' => 1 + $accu['total'],
                'match' => ($matcher($item) ? 1 : 0) + $accu['match'],
            ];
        },
        ['total' => 0, 'match' => 0]
    );
    return $stats['total'] >= $minResults && $stats['match'] === $stats['total'];
}

function hasAtLeastSoManyResultsWhichAllMatchCallbackV1(PDO $db, string $search, int $minResults, callable $matcher): bool
{
    $stats = array_reduce(
        gatherSearchResultsWithWildcards($search, $db),
        function ($accu, SearchResult $item) use ($matcher) {
            return [
                'total' => 1 + $accu['total'],
                'match' => ($matcher($item) ? 1 : 0) + $accu['match'],
            ];
        },
        ['total' => 0, 'match' => 0]
    );
    return $stats['total'] >= $minResults && $stats['match'] === $stats['total'];
}

/**
 * test if the array is associative
 * @param array $arr
 * @return bool true if arr is associative, false if it is numerical indexed (without holes in keys)
 */
function isArrayAssociative(array $arr): bool {
    if (array() === $arr) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}

/**
 * checks if the two arrays have equal contents (same keys and values; without checking order of values)
 * @param array $a
 * @param array $b
 * @return bool
 */
function compareArrayDeep(array $a, array $b): bool {
    if(count($a) !== count($b)) return false;// if length is the same, we can compare by iterating ver only one array-key-set

    foreach($a as $kA => $vA){
        if(!isset($b[$kA]))
            return false;

        $vB = $b[$kA];

        if(gettype($vA) !== gettype($vB))
            return false;

        if(gettype($vA) === 'array'){// recursively compare arrays
            if(isArrayAssociative($vA)) {
                if (!isArrayAssociative($vB)) return false;

                if (!compareArrayDeep($vA, $vB))
                    return false;
            } else {
                if (isArrayAssociative($vB)) return false;

                // sort number-indexed arrays so that order does not matter
                $vACpy = $vA;
                asort($vACpy);
                $vBCpy = $vACpy;
                asort($vBCpy);

                if (!compareArrayDeep($vACpy, $vBCpy))
                    return false;
            }
        }else{// just use standard value comparison
            if($vA !== $vB)
                return false;
        }
    }

    return true;
}