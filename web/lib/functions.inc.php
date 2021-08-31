<?php declare(strict_types=1);

function buildQuery(PDO $db): PDOStatement
{
    $songColumns = [
        'name',
        'copyright_year',
        'copyright_remark',
        'created_on',
        'label',
        'publisher_series',
        'publisher_number',
        'record_number',
        'origin',
        'dedication',
        'review',
        'addition',
        'index_no',
    ];
    $xrefs = [
        'collection' => 'collection',
        'composer' => 'person',
        'cover_artist' => 'person',
        'genre' => 'genre',
        'performer' => 'person',
        'publication_place' => 'city',
        'publisher' => 'publisher',
        'source' => 'source',
        'writer' => 'person',
    ];
    return $db->prepare(
        sprintf(
            "(select id from (%s) a where name like concat('%% ', ?, ' %%') collate utf8mb4_unicode_ci) "
            . "union (select id from (select concat('%% ', ?, ' %%') collate utf8mb4_unicode_ci pattern) a "
            . "join mks_song b where concat(' ', strip_punctuation(b.%s), ' ') collate utf8mb4_unicode_ci like a.pattern)",
            implode(
                " union ",
                array_map(
                    fn($list) => sprintf(
                        "select a.song_id id, concat(' ', strip_punctuation(b.name), ' ') collate utf8mb4_unicode_ci name "
                        . 'from mks_x_%s_song a join mks_%s b on b.id = a.%1$s_id',
                        $list,
                        $xrefs[$list],
                    ),
                    array_keys($xrefs)
                )
            ),
            implode("), ' ') collate utf8mb4_unicode_ci like a.pattern or concat(' ', strip_punctuation(b.", $songColumns)
        )
    );
}

/**
 * @param PDO $db
 * @param string[] $keywords
 *
 * @return array A map of absolute relevance values (match count) with song IDs as key
 * @throws PDOException
 */
function buildSongMatches(PDO $db, array $keywords): array
{
    $matches = [];
    $findSongIds = buildQuery($db);
    foreach ($keywords as $keyword) {
        $findSongIds->execute([$keyword, $keyword]);
        foreach ($findSongIds->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $matches[$id] = 1 + ($matches[$id] ?? 0);
        }
    }
    return $matches;
}

function gatherSearchResults(string $search, PDO $db): array
{
    [$keywords, $ranges, $excludedKeywords, $excludedRanges] = parseSearch($search);
    $relevanceMap = buildSongMatches($db, $keywords);
    foreach (buildSongMatches($db, $excludedKeywords) as $songId => $exclusionMatches) {
        unset($relevanceMap[$songId]);
    }
    $andMatches = array_filter($relevanceMap, fn($r) => $r === count($keywords));
    return count($relevanceMap) > 0
        ? $db->query(
            sprintf(<<<'SQL'
                select
                    a.id,
                    a.name title,
                    concat(d.name, b.annotation) composer,
                    concat(e.name, c.annotation) writer,
                    a.copyright_year,
                    a.origin
                from mks_song a
                left join mks_x_composer_song b on a.id = b.song_id and b.position = 1
                left join mks_x_writer_song c on a.id = c.song_id and c.position = 1
                left join mks_person d on d.id = b.composer_id
                left join mks_person e on e.id = c.writer_id
                where a.id in (%s)
                SQL,
                implode(',', array_keys(count($andMatches) > 0 ? $andMatches : $relevanceMap))
            )
        )->fetchAll(PDO::FETCH_CLASS, SearchResult::class)
        : [];
}

/**
 * @param string $search
 *
 * @return array [keywords, ranges, excludedKeywords, excludedRanges]
 */
function parseSearch(string $search): array {
    $wildcardSearch = str_replace('*', '%', str_replace('%', '%%', $search));
    [$nonPhrases, $phrases, $excludedPhrases] = splitPhrases($wildcardSearch);
    [$nonRanges, $ranges, $excludedRanges] = splitRanges($nonPhrases);
    [$keywords, $excludedKeywords] = splitKeywords($nonRanges);
    return [
        array_merge($keywords, $phrases),
        $ranges,
        array_merge($excludedKeywords, $excludedPhrases),
        $excludedRanges,
    ];
}

/**
 * @param $search
 *
 * @return array
 */
function splitKeywords(string $search): array
{
    $keywords = preg_split('<\\s+>', $search);
    return [
        array_values(array_filter($keywords, fn($s) => $s && $s[0] !== '-')),
        array_map(fn($s) => ltrim($s, '-'), array_values(array_filter($keywords, fn($s) => $s && $s[0] === '-'))),
    ];
}

/**
 * @param string $search
 *
 * @return array [non-phrase search, phrases, excluded phrases]
 */
function splitPhrases(string $search): array
{
    $particles = explode('"', $search);
    $nonPhrases = array_map('trim', array_values(array_filter($particles, fn($index) => $index % 2 === 0, ARRAY_FILTER_USE_KEY)));
    $phrases = array_map('trim', array_values(array_filter($particles, fn($index) => $index % 2 === 1, ARRAY_FILTER_USE_KEY)));
    return [
        implode(' ', array_filter(array_map(fn($s) => rtrim($s, '- '), $nonPhrases))),
        array_values(array_filter($phrases, fn($key) => !$nonPhrases[$key] || $nonPhrases[$key][-1] !== '-', ARRAY_FILTER_USE_KEY)),
        array_values(array_filter($phrases, fn($key) => $nonPhrases[$key] && $nonPhrases[$key][-1] === '-', ARRAY_FILTER_USE_KEY)),
    ];
}

/**
 * @param string $search
 *
 * @return array [non-range search, ranges, excluded ranges]
 */
function splitRanges(string $search): array
{
    // TODO not yet implemented
    // $particles = explode('..', sprintf('# %s #', $search));
    // array_map(fn($s) => trim(ltrim($s, '.')), $particles);
    return [
        $search,
        [],
        [],
    ];
}
