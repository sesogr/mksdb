<?php

namespace Utils;

/**
 * merges two arrays into one;
 * if both have a value for the same key, the resolver is called with both values and the returned value is used as the new array-value
 * @param array $a
 * @param array $b
 * @param callable(mixed, mixed):mixed $resolver called if there is a merge-conflict; returns value which should be used
 * @return array
 */
function arrayMergeWithCustomResolver(array $a, array $b, callable $resolver): array {
    $merged = $a;
    foreach($b as $kB => $vB){
        if(isset($merged[$kB])){
            $merged[$kB] = $resolver($merged[$kB], $vB);
        }else{
            $merged[$kB] = $vB;
        }
    }
    return $merged;
}
