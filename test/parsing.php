<?php declare(strict_types=1);

require_once __DIR__ . '/lib/testframeworkinatweet.inc.php';
require_once __DIR__ . '/../web/lib/functions.inc.php';

it('can split a search string into phrases and non-phrase particles', function () {
    return splitPhrases('abc -def -" ghi jkl" mno "pqr "') == ['abc -def mno', ['pqr'], ['ghi jkl']];
});
it('can split a non-phrase search string into positive and excluded keywords', function () {
    return splitKeywords('abc -def mno') == [['abc', 'mno'], ['def']];
});
it('can parse a search string without ranges', function () {
    return parseSearchV3('abc -def -" ghi jkl" mno "pqr "') == [['abc', 'mno'], ['pqr'], ['def'], ['ghi jkl']];
});
it('can parse a simple phrase', function () {
    return parseSearchV3('"am Himmel"') == [[], ['am Himmel'], [], []];
});
