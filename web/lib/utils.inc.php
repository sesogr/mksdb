<?php

namespace Utils;

use UnexpectedValueException;

/**
 * adds the <code>value</code> to the bottom-level array (makes no duplicates) for <code>keys</code>
 * or creates a new array with the <code>value</code> if the map does not contain that key (also creates whole sub-path);
 * the array is located by the <code>keys</code> from top- to bottom-level
 * @param array $map the map (may be nested)
 * @param string $value the value to add
 * @param string ...$keys the keys to locate the array
 */
function mapDeepPutOrAdd(array &$map, string $value, string ...$keys): void
{
    if(count($keys) > 1){
        $key = array_shift($keys);

        // if map does not contain sub-path -> create it (level per level)
        if(!array_key_exists($key, $map))
            $map[$key] = [];

        // go to next level
        mapDeepPutOrAdd($map[$key], $value, ...$keys);// keys were shifted
    }else{
        // bottom-level
        $key = array_shift($keys);
        if (array_key_exists($key, $map)) {
            $array = $map[$key];
            if (!in_array($value, $array))
                array_push($array, $value);
        } else {
            $array = [$value];
        }
        $map[$key] = $array;
    }
}

/**
 * merges the input-map into the destination map (array of existing keys will be merged; without duplicates);
 * the bottom-level arrays of both maps must be number-indexed
 * @param array $map the destination map
 * @param array $inp the input map
 */
function mapDeepMerge(array &$map, array $inp)
{
    foreach($inp as $iKey => $iVal){
        if(gettype($iVal) === 'array'){
            // merge sub-map
            if(isset($map[$iKey])){
                $mVal = &$map[$iKey];
                if(gettype($mVal) !== 'array')
                    throw new UnexpectedValueException('array-value can not be merged into non-array-value in map');

                mapDeepMerge($mVal, $iVal);
            }else{
                $map[$iKey] = $iVal;
            }
        }else{
            // add value
            if(!in_array($iVal, $map))
                array_push($map, $iVal);
        }
    }
}

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