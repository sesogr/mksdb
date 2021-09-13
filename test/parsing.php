<?php declare(strict_types=1);
require_once __DIR__ . '/../web/lib/testframeworkinatweet.inc.php';
require_once __DIR__ . '/../web/lib/functions.inc.php';

it('can split a search string into phrases and non-phrase particles', fn() => splitPhrases('abc -def -" ghi jkl" mno "pqr "') == ['abc -def mno', ['pqr'], ['ghi jkl']]);
it('can split a non-phrase search string into positive and excluded keywords', fn() => splitKeywords('abc -def mno') == [['abc', 'mno'], ['def']]);
it('can parse a search string without ranges', fn() => parseSearch('abc -def -" ghi jkl" mno "pqr "') == [['abc', 'mno'], ['pqr'], [], ['def'], ['ghi jkl'], []]);
it('can parse a simple phrase', fn() => parseSearch('"am Himmel"') == [[], ['am Himmel'], [], [], [], []]);
it('converts user wildcards (*) to SQL LIKE wildcards (%)', fn() => parseSearch('frühling -wien* -"100%"') == [['frühling'], [], ['wien%', '100%%'], []]);
