<?php declare(strict_types=1);
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use function Utils\arrayMergeWithCustomResolver;

require_once __DIR__ . '/types.inc.php';
require_once __DIR__ . '/utils.inc.php';
function mbReverse(string $input): string {
    return implode('', array_reverse(preg_split('<>u', $input)));
}

/**
 * @param PDO $db
 * @param string $keywordsAndPhrases A space-separated concatenation of all the words to match
 * @param string|null $topic
 *
 * @return Generator
 */
function iterateSongIdsForIndexMatches(PDO $db, string $keywordsAndPhrases, ?string $topic = null): Generator
{
    $wildcardWords = array_filter(preg_split('<[^\\pN\\pL*]+>u', str_replace(' * ', ' ', sprintf(' %s ', $keywordsAndPhrases))));
    if ($wildcardWords) {
        $conditions = array_map(
            function ($word) use ($db) {
                $pos = mb_strpos($word, '*');
                $len = mb_strlen($word);
                return $pos === false
                    ? sprintf('`word` = %s', $db->quote($word))
                    : implode(
                        ' and ',
                        array_merge(
                            $pos > 0
                                ? [sprintf('left(`word`, %d) = %s', $pos, $db->quote(mb_substr($word, 0, $pos)))]
                                : [],
                            $pos < $len - 1
                                ? [sprintf(
                                    'left(`reverse`, %d) = %s',
                                    $len - 1 - $pos,
                                    $db->quote(mbReverse(mb_substr($word, $pos + 1)))
                                )]
                                : [],
                        )
                    );
            },
            $wildcardWords
        );
        $query = sprintf(
            'select distinct `song` from `mks_word_index` where %s(%s) order by `song`',
            $topic ? sprintf('find_in_set(%s, `topics`) > 0 and ', $db->quote($topic)) : '',
            implode(' or ', $conditions)
        );
        yield from $db->query($query, PDO::FETCH_COLUMN, 0);
    }
}

/**
 * @param PDO $db
 * @param array $fields [topic:string => search:string]
 *
 * @return array|SearchResult[]
 */
function gatherSearchResultsV3(PDO $db, array $fields): array
{
    $iterators = [];
    $filters = [];
    foreach ($fields as $topic => $search) {
        if ($search) {
            [$keywords, $phrases, $excludedKeywords, $excludedPhrases] = $filters[$topic] = parseSearchV3($search);
            $iterators[$topic] = iterateSongIdsForIndexMatches(
                $db,
                implode(' ', array_merge($keywords, $phrases)),
                $topic ?: null
            );
        }
    }
    $scoredSongs = iterator_to_array(
        filterAndScoreSongs(
            mapSongIdsToSongs(mergeAndSortIterators($iterators), $db, 256),
            $filters
        )
    );
    usort($scoredSongs, fn(array $a, array $b) => $b[1] - $a[1]); // descending
    return array_map(
        fn(array $songAndScore) => (object)[
            'id' => intval($songAndScore[0]->id),
            'title' => $songAndScore[0]->{'song-name'},
            'composer' => $songAndScore[0]->composer,
            'writer' => $songAndScore[0]->writer,
            'copyright_year' => $songAndScore[0]->{'song-cpr_y'},
            'origin' => $songAndScore[0]->{'song-origin'},
        ],
        $scoredSongs
    );
}

/**
 * @param array|Iterator[] $iterators
 *
 * @return Generator that yields values from all iterators strictly in order and without duplicates.
 */
function mergeAndSortIterators(array $iterators): Generator
{
    $latestSongId = 0;
    array_walk($iterators, fn(Generator $g) => $g->rewind());
    do {
        uasort($iterators, fn(Generator $a, Generator $b) => intval($a->current()) - intval($b->current()));
        foreach ($iterators as $key => $iterator) {
            $current = intval($iterator->current());
            if ($current > $latestSongId) {
                $latestSongId = $current;
                yield $current;
            }
            $iterator->next();
            if (!$iterator->valid()) {
                unset($iterators[$key]);
            }
            break; // only process first iterator, then sort again
        }
    } while ($iterators);
}

/**
 * @param iterable|string[] $songIds
 * @param PDO $db
 * @param int $clusterSize
 *
 * @return Generator|CsvTableSong[]
 * @throws PDOException
 */
function mapSongIdsToSongs(iterable $songIds, PDO $db, int $clusterSize = 256): Generator {
    $bucket = [];
    $template = <<<'SQL'
        select
            `id`,
            `Titel` `song-name`,
            concat_ws('\n', `Komponist 1`, `Komponist 2`, `Komponist 3`, `Komponist 4`) `composer`,
            concat_ws('\n', `Texter 1`, `Texter 2`, `Texter 3`, `Texter 4`) `writer`,
            `Copyright` `song-cpr_y`,
            `Copyrightvermerk` `song-cpr_remark`,
            `Entstehung` `song-created`,
            `Graphiker` `cover_artist`,
            concat_ws('\n', `Interpreten`, `Interpret 2`, `Interpret 3`, `Interpret 4`, `Interpret 5`, `Interpret 6`) `performer`,
            `Label` `song-label`,
            `Verlag` `publisher`,
            `Verlagsort` `city`,
            `Verlagsreihe` `song-pub_ser`,
            `Verlagsnummer` `song-pub_nr`,
            `Plattennr.` `song-rec_nr`,
            `Herkunft` `song-origin`,
            `Gattung` `genre`,
            `Widmung` `song-dedication`,
            `Sammlungen` `collection`,
            `Kritik` `song-rev`,
            `Ergänzung` `song-addition`,
            `Quelle` `source`,
            null
        from `20201217-oeaw-schlager-db`
        where `id` in (%s)
        SQL;
    $query = $db->prepare(sprintf($template, implode(',', array_fill(0, $clusterSize, '?'))));
    foreach ($songIds as $id) {
        $bucket[] = intval($id);
        if (count($bucket) === $clusterSize) {
            $query->execute($bucket);
            $bucket = [];
            yield from $query;
        }
    }
    if ($bucket) {
        $query = $db->prepare(sprintf($template, implode(',', array_fill(0, count($bucket), '?'))));
        $query->execute($bucket);
        yield from $query;
    }
}

/**
 * @param iterable|CsvTableSong[] $songs
 * @param array $filters [topic:string => [keywords:string[], phrases:string[], excludedKeywords:string[], excludedPhrases:string[]]]
 *
 * @return Generator|array [:CsvTableSong, score:int]
 */
function filterAndScoreSongs(iterable $songs, array $filters): Generator {
    foreach ($songs as $song) {
        $score = 0;
        foreach ($filters as $topic1 => [$keywords, $phrases, $excludedKeywords, $excludedPhrases]) {
            if ($topic1) {
                if (matchValue($song->$topic1, array_merge($excludedKeywords, $excludedPhrases))) {
                    $score = 0;
                    break;
                }else if(matchValue($song->$topic1, array_merge($keywords, $phrases))){
                    $score++;
                }
            } else {
                foreach ($song as $topic2 => $value) {
                    if (matchValue($song->$topic2, array_merge($excludedKeywords, $excludedPhrases))) {
                        $score = 0;
                        break;
                    } elseif (matchValue($song->$topic2, array_merge($keywords, $phrases))) {
                        $score++;
                    }
                }
            }
        }
        if ($score) {
            yield [$song, $score];
        }
    }
}

function matchValue(?string $subject, array $searchPhrases): bool
{
    if ($subject) {
        foreach ($searchPhrases as $phrase) {
            $pattern = sprintf(
                '<%s>u',
                str_replace(
                    '*',
                    '[\\pN\\pL]+',
                    str_replace(' * ', ' [\\pN\\pL ]+ ', stripPunctuationAndPad($phrase, true))
                )
            );
            if (preg_match($pattern, stripPunctuationAndPad($subject))) {
                return true;
            }
        }
    }
    return false;
}

function stripPunctuationAndPad(string $text, bool $allowAsterisk = false): string
{
    $pattern = sprintf("<[^\\pN\\pL%s]+>u", $allowAsterisk ? '*' : '');
    return sprintf(' %s ', mb_strtolower(implode(' ', array_filter(preg_split($pattern, $text)))));
}

/**
 * splits up the search-query into keywords, phrases, ranges and their excluded counterparts
 * @param string $search
 * @return array [keywords (without phrases), phrases, ranges, excludedKeywords (without phrases), excludedPhrases, excludedRanges]
 */
function parseSearchV3(string $search): array
{
    [$nonPhrases, $phrases, $excludedPhrases] = splitPhrases($search);
    [$keywords, $excludedKeywords] = splitKeywords($nonPhrases);
    return [$keywords, $phrases, $excludedKeywords, $excludedPhrases];
}

// #### V1 ###
/*
 * uses slow search-query (with 'LIKE') which supports wildcards
 */
function gatherSearchResultsWithWildcards(string $search, PDO $db): array {
    [$keywords, $phrases, $ranges, $excludedKeywords, $excludedPhrases, $excludedRanges] = parseSearch($search);
    // phrases have to be included into keywords for buildSongMatches
    $allKeywords = $keywords;
    foreach($phrases as $phrase)
        array_push($allKeywords, $phrase);
    $allExcludedKeywords = $excludedKeywords;
    foreach($excludedPhrases as $phrase)
        array_push($allExcludedKeywords, $phrase);

    $relevanceMap = buildSongMatches($db, $allKeywords);
    foreach (buildSongMatches($db, $allExcludedKeywords) as $songId => $exclusionMatches) {
        unset($relevanceMap[$songId]);
    }
    $andMatches = array_filter($relevanceMap, function ($r) use ($keywords) {
        return $r === count($keywords);
    });

    return getSongInfoMulti(array_keys(count($andMatches) > 0 ? $andMatches : $relevanceMap), $db);
}

function addTablePrefixesToJoinParameter(string $query): string
{
    return implode(
        '&',
        array_map(
            function ($param) {
                return substr($param, 0, 5) === 'join='
                    ? preg_replace('<([=,])>', '\\1mks_', $param)
                    : $param;
            },
            explode('&', $query)
        )
    );
}

function addTablePrefixToRecordsPath(string $path)
{
    return preg_replace('<^/records/(\w+)>', '/records/mks_$1', $path);
}

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
                    function ($list) use ($xrefs) {
                        return sprintf(
                            "select a.song_id id, concat(' ', strip_punctuation(b.name), ' ') collate utf8mb4_unicode_ci name "
                            . 'from mks_x_%s_song a join mks_%s b on b.id = a.%1$s_id',
                            $list,
                            $xrefs[$list],
                        );
                    },
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

// #### V2 ####

function gatherSearchResults(string $search, PDO $db): array
{
    [$keywords, $phrases, $ranges, $excludedKeywords, $excludedPhrases, $excludedRanges] = parseSearch($search);
    // phrases have to be included into keywords for collectWordMatches
    $keywordsWithPhrases = $keywords;
    foreach($phrases as $phrase)
        $keywordsWithPhrases = array_merge($keywordsWithPhrases, preg_split('[\s+]', $phrase));

    $matchedSongs = collectWordMatches($keywordsWithPhrases, $db);

    $resultIds = [];
    foreach($matchedSongs as $song => $matchCount){
        $score = scoreSong($song, $keywords, $phrases, $excludedKeywords, $excludedPhrases, $db);
        if($score->totalScore() > 0)
            $resultIds[$song] = $score;
    }

    $resultIds = filterAndSortResults($resultIds, count($keywords), count($phrases));

    return getSongInfoMulti($resultIds, $db);
}

/**
 * @param array $words array of keywords
 * @param PDO $dbConn db connection
 * @return array array of all song-ids with one or more matching words;
 *      the key is the song-id, the value is the match-count
 */
function collectWordMatches(array $words, PDO $dbConn): array {
    $ret = [];
    //TODO this query should be tested
    $query = $dbConn->query(sprintf('SELECT song, COUNT(DISTINCT word) AS c FROM mks_word_index WHERE word IN (%s) GROUP BY song ORDER BY c DESC;',
        '\'' . implode('\', \'', $words) . '\''));
    foreach ($query->fetchAll(PDO::FETCH_NUM) as $row){
        $ret[$row[0]] = (int)$row[1];
    }
    return $ret;
}

/**
 * returns all strings (strip_punctuation applied) for the song (all columns of mks_song and all of its joins)
 * @param int $songId
 * @param PDO $dbConn
 * @return array array of all strings related to the song
 */
function getSongFullInfo(int $songId, PDO $dbConn): array {
    //TODO which columns are relevant for the search?; is my SQL syntax performant?
    //TODO maybe this could be cached for the request-time
    $stm = $dbConn->prepare("SELECT strip_punctuation(s.name), strip_punctuation(s.label), strip_punctuation(s.origin), strip_punctuation(s.dedication), strip_punctuation(s.review), strip_punctuation(s.addition),
       strip_punctuation(s.copyright_year), strip_punctuation(s.copyright_remark), strip_punctuation(s.created_on), strip_punctuation(s.publisher_series), strip_punctuation(s.publisher_number), strip_punctuation(s.record_number),
       strip_punctuation(coll.name), strip_punctuation(comp.name), strip_punctuation(cover.name), strip_punctuation(gen.name), strip_punctuation(perf.name), strip_punctuation(pubp.name), strip_punctuation(pub.name), strip_punctuation(src.name), strip_punctuation(wrt.name)
       FROM (mks_song s
            LEFT JOIN (mks_x_collection_song coll_x INNER JOIN mks_collection coll on coll_x.collection_id = coll.id) ON coll_x.song_id = s.id
            LEFT JOIN (mks_x_composer_song comp_x INNER JOIN mks_person comp on comp_x.composer_id = comp.id) ON comp_x.song_id = s.id
            LEFT JOIN (mks_x_cover_artist_song cover_x INNER JOIN mks_person cover on cover_x.cover_artist_id = cover.id) ON cover_x.song_id = s.id
            LEFT JOIN (mks_x_genre_song gen_x INNER JOIN mks_genre gen on gen_x.genre_id = gen.id) ON gen_x.song_id = s.id
            LEFT JOIN (mks_x_performer_song perf_x INNER JOIN mks_person perf on perf_x.performer_id = perf.id) ON perf_x.song_id = s.id
            LEFT JOIN (mks_x_publication_place_song pubp_x INNER JOIN mks_city pubp on pubp_x.publication_place_id = pubp.id) ON pubp_x.song_id = s.id
            LEFT JOIN (mks_x_publisher_song pub_x INNER JOIN mks_publisher pub on pub_x.publisher_id = pub.id) ON pub_x.song_id = s.id
            LEFT JOIN (mks_x_source_song src_x INNER JOIN mks_source src on src_x.source_id = src.id) ON src_x.song_id = s.id
            LEFT JOIN (mks_x_writer_song wrt_x INNER JOIN mks_person wrt on wrt_x.writer_id = wrt.id) ON wrt_x.song_id = s.id
       )
       WHERE s.id = ?;");
    $stm->execute([$songId]);

    $row = $stm->fetch(PDO::FETCH_NUM);
    $ret = [];
    if($row === false) {
        return $ret;
    }
    foreach($row as $col)
        if($col !== null and $col != '')
            $ret[] = $col;

    return $ret;
}

function getSongInfoMulti(array $songs, PDO $dbConn): array {
    if(count($songs) === 0) return [];

    $query = $dbConn->query(sprintf("
                select
                    a.id,
                    a.name title,
                    concat_ws('', d.name, b.annotation) composer,
                    concat_ws('', e.name, c.annotation) writer,
                    a.copyright_year,
                    a.origin
                from mks_song a
                left join mks_x_composer_song b on a.id = b.song_id and b.position = 1
                left join mks_x_writer_song c on a.id = c.song_id and c.position = 1
                left join mks_person d on d.id = b.composer_id
                left join mks_person e on e.id = c.writer_id
                where a.id in (%s)",
        '\'' . implode('\', \'', $songs) . '\''));
    return $query->fetchAll(PDO::FETCH_CLASS, SearchResult::class);
}

/**
 * computes the score for a song and the requested query
 * @param int $songId
 * @param array $keywords
 * @param array $phrases
 * @param array $excludedKeywords
 * @param array $excludedPhrases
 * @param PDO $dbConn
 * @return SearchResultScore
 */
function scoreSong(int $songId, array $keywords, array $phrases, array $excludedKeywords, array $excludedPhrases, PDO $dbConn): SearchResultScore {
    $score = SearchResultScore::newEmpty();
    $score->keywordMatchCount = 0;

    $matchedKeywords = [];
    $matchedPhrases = [];

    // count matching phrases and search for exclusions
    $songInfo = getSongFullInfo($songId, $dbConn);
    foreach($songInfo as $infoPart){
        foreach ($excludedKeywords as $excludedKeyword)
            if(mb_stripos($infoPart, $excludedKeyword, 0, 'UTF-8') !== false)
                return SearchResultScore::newExcluded();

        foreach($excludedPhrases as $excludedPhrase)
            if(mb_stripos($infoPart, $excludedPhrase, 0, 'UTF-8') !== false)
                return SearchResultScore::newExcluded();

        foreach($keywords as $keyword){
            if(mb_stripos($infoPart, $keyword, 0, 'UTF-8') !== false){
                array_push($matchedKeywords, $keyword);
            }
        }

        foreach($phrases as $phrase) {
            if (mb_stripos($infoPart, $phrase, 0, 'UTF-8') !== false) {
                array_push($matchedPhrases, $phrase);
            }
        }
    }

    $score->keywordMatchCount = count(array_unique($matchedKeywords));
    $score->phraseMatchCount = count(array_unique($matchedPhrases));

    // if all keywords or phrases were matched, the result should be scored higher
    if($score->keywordMatchCount === count($keywords))
        $score->fullKeywordsMatchCount = $score->keywordMatchCount;
    if($score->phraseMatchCount == count($phrases))
        $score->fullPhrasesMatchCount = $score->phraseMatchCount;

    return $score;
}

/**
 * @param array $results [songId => score]
 * @param int $keywordCount count of searched keywords
 * @param int $phraseCount count of searched phrases
 * @param bool $preferFullMatches if true and a result has the same keyword-match-count as keywordCount, all results scored lower will be excluded
 * @return array [songId] (sorted by score)
 */
function filterAndSortResults(array $results, int $keywordCount, int $phraseCount, bool $preferFullMatches = true): array {
    /* compute minimum score:
        if searched with keywords -> 1 * KEYWORD_MULTIPLIER
        if searched only with phrases -> 1 * PHRASE_MULTIPLIER
        if one result has a full match (count of matched keywords == keywordCount)
            -> $keywordCount * KEYWORD_FULL_MATCH_MULTIPLIER;
            same for phrases; if bother were matched full by one -> sum of both min-values
    */

    $onlyPhrasesUsed = $keywordCount === 0;
    $minScore = 0;
    if($preferFullMatches && !$onlyPhrasesUsed) {
        foreach($results as $id => $score) {
            if($score->fullPhrasesMatchCount > 0 and $score->fullKeywordsMatchCount > 0){
                $minScore = $phraseCount * SearchResultScore::FULL_MATCH_MULTIPLIER * SearchResultScore::PHRASE_MULTIPLIER
                    + $keywordCount * SearchResultScore::FULL_MATCH_MULTIPLIER * SearchResultScore::KEYWORD_MULTIPLIER;
                break;// highest possible value
            }else {
                if ($score->fullPhrasesMatchCount > 0) {
                    $minScore = max($minScore, $phraseCount * SearchResultScore::FULL_MATCH_MULTIPLIER * SearchResultScore::PHRASE_MULTIPLIER);
                }
                if (!$score->fullKeywordsMatchCount > 0) {
                    $minScore = max($minScore, $keywordCount * SearchResultScore::FULL_MATCH_MULTIPLIER * SearchResultScore::KEYWORD_MULTIPLIER);
                }
            }
        }
    }
    if($minScore === 0)
        $minScore = $onlyPhrasesUsed ? SearchResultScore::PHRASE_MULTIPLIER : SearchResultScore::KEYWORD_MULTIPLIER;

    foreach($results as $id => $score)
        if($score->totalScore() < $minScore)
            unset($results[$id]);

    // convert $results from [songId => score] to [[songId, score]], then sort by score, then map to [songId]
    $resultsT = [];
    foreach($results as $song => $score)
        array_push($resultsT, [$song, $score]);
    usort($resultsT, function($vA, $vB) {
        $sA = $vA[1]->totalScore();
        $sB = $vB[1]->totalScore();

        if($sA === $sB) return 0;
        return $sA < $sB ? -1 : 1;
    });

    return array_map(function($v){
        return $v[0];
    }, $resultsT);
}

// #### advanced search ####

function gatherSearchResultsByFields(array $searchFields, PDO $db): array {
    $includedSongs = [];
    $excludedSongs = [];
    $keywordsPerTopic = [];
    $excludedKeywordsPerTopic = [];
    $phrasesPerTopic = [];
    $excludedPhrasesPerTopic = [];

    foreach($searchFields as $field => $value){
        if ($value === null) continue;
        [$keywords, $phrases, $ranges, $excludedKeywords, $excludedPhrases, $excludedRanges] = parseSearch($value);

        $keywordsPerTopic[$field] = $keywords;
        $excludedKeywordsPerTopic[$field] = $excludedKeywords;
        $phrasesPerTopic[$field] = $phrases;
        $excludedPhrasesPerTopic[$field] = $excludedPhrases;

        // phrases have to be included into keywords for collectWordMatches
        $keywordsWithPhrases = $keywords;
        foreach($phrases as $phrase)
            $keywordsWithPhrases = array_merge($keywordsWithPhrases, preg_split('[\s+]', $phrase));

        $includedSongs = arrayMergeWithCustomResolver($includedSongs, collectWordMatchesWithTopic($keywordsWithPhrases, $field, $db),
            function($vA, $vB){
                // return max count (for later scoring)
                return max($vA, $vB);
        });

        // exclusions by keyword can be directly filtered out (phrases have to be analysed separately)
        $excludedSongs = array_merge($excludedSongs, array_keys(collectWordMatchesWithTopic($excludedKeywords, $field, $db)));
    }

    // filter out all (keyword) excluded songs
    $songs = array_filter($includedSongs, function ($song) use ($excludedSongs) {
        return !in_array($song, $excludedSongs);
    }, ARRAY_FILTER_USE_KEY);

    $results = [];
    foreach($songs as $song => $wordMatches){
        $score = scoreSongWithTopics($song, $keywordsPerTopic, $excludedKeywordsPerTopic,
            $phrasesPerTopic, $excludedPhrasesPerTopic, $db);
        if($score->totalScore() > 0)
            $results[$song] = $score;
    }

    $totalKeywordCount = array_reduce($keywordsPerTopic, function(int $sum, array $val){
        return $sum + count($val);
    }, 0);
    $totalPhraseCount = array_reduce($phrasesPerTopic, function(int $sum, array $val){
        return $sum + count($val);
    }, 0);

    $resultIds = filterAndSortResults($results, $totalKeywordCount, $totalPhraseCount);

    return getSongInfoMulti($resultIds, $db);
}

function collectWordMatchesWithTopic(array $words, string $topic, PDO $dbConn): array {
    $ret = [];
    $query = $dbConn->query(sprintf('SELECT song, COUNT(DISTINCT word) AS c FROM mks_word_index WHERE word IN (%s) AND find_in_set(\'%s\', topics) > 0 GROUP BY song ORDER BY c DESC;',
        ('\'' . implode('\', \'', $words) . '\''), $topic));
    foreach ($query->fetchAll(PDO::FETCH_NUM) as $row){
        $ret[$row[0]] = (int)$row[1];
    }
    return $ret;
}

/**
 * @param int $song the song id
 * @param array $keywords included keywords; format [topic => [keyword, ...], ...]
 * @param array $excludedKeywords excluded keywords; format [topic => [keyword, ...], ...]
 * @param array $phrases included phrases; format [topic => [phrase, ...], ...]
 * @param array $excludedPhrases excluded phrases; format [topic => [phrase, ...], ...]
 * @param PDO $dbConn
 * @return SearchResultScore
 */
function scoreSongWithTopics(int $song, array $keywords, array $excludedKeywords, array $phrases, array $excludedPhrases, PDO $dbConn): SearchResultScore{
    // map with functions which returns text for scoring for each possible topic
    $textQueries = [
        'city' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT c.name
                                            FROM (mks_city c INNER JOIN mks_x_publication_place_song x ON c.id = x.publication_place_id 
                                                INNER JOIN mks_song s ON s.id = x.song_id) 
                                            WHERE s.id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'collection' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT c.name
                                            FROM (mks_collection c INNER JOIN mks_x_collection_song x ON c.id = x.collection_id 
                                                INNER JOIN mks_song s ON s.id = x.song_id) 
                                            WHERE s.id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'genre' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT g.name
                                            FROM (mks_genre g INNER JOIN mks_x_genre_song x ON g.id = x.genre_id 
                                                INNER JOIN mks_song s ON s.id = x.song_id) 
                                            WHERE s.id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'composer' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT c.name
                                            FROM (mks_person c INNER JOIN mks_x_composer_song x ON c.id = x.composer_id 
                                                INNER JOIN mks_song s ON s.id = x.song_id) 
                                            WHERE s.id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'cover_artist' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT c.name
                                            FROM (mks_person c INNER JOIN mks_x_cover_artist_song x ON c.id = x.cover_artist_id
                                                INNER JOIN mks_song s ON s.id = x.song_id) 
                                            WHERE s.id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'performer' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT p.name
                                            FROM (mks_person p INNER JOIN mks_x_performer_song x ON p.id = x.performer_id 
                                                INNER JOIN mks_song s ON s.id = x.song_id) 
                                            WHERE s.id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'writer' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT w.name
                                            FROM (mks_person w INNER JOIN mks_x_writer_song x ON w.id = x.writer_id 
                                                INNER JOIN mks_song s ON s.id = x.song_id) 
                                            WHERE s.id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'publisher' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT p.name
                                            FROM (mks_publisher p INNER JOIN mks_x_publisher_song x ON p.id = x.publisher_id 
                                                INNER JOIN mks_song s ON s.id = x.song_id) 
                                            WHERE s.id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'source' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT s.name
                                            FROM (mks_source s INNER JOIN mks_x_source_song x ON s.id = x.source_id 
                                                INNER JOIN mks_song s ON s.id = x.song_id) 
                                            WHERE s.id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'song-name' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT name FROM mks_song WHERE id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'song-cpy_y' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT copyright_year FROM mks_song WHERE id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'song-cpr_remark' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT copyright_remark FROM mks_song WHERE id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'song-created' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT created_on FROM mks_song WHERE id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'song-label' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT label FROM mks_song WHERE id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'song-pub_ser' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT publisher_series FROM mks_song WHERE id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'song-pub_nr' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT publisher_number FROM mks_song WHERE id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'song-rec_nr' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT record_number FROM mks_song WHERE id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'song-origin' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT origin FROM mks_song WHERE id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'song-dedication' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT dedication FROM mks_song WHERE id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'song-rev' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT review FROM mks_song WHERE id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        },
        'song-addition' => function(int $id, PDO $dbConn){
            $stm = $dbConn->prepare('SELECT addition FROM mks_song WHERE id = ?;');
            $stm->execute([$id]);
            return $stm->fetch(PDO::FETCH_NUM)[0];
        }
    ];

    $score = SearchResultScore::newEmpty();
    $score->keywordMatchCount = 0;

    $matchedKeywords = [];
    $matchedPhrases = [];

    // accumulate all topic, so that their queries have to be executed only once
    $relevantTopics = array_unique(array_merge(array_keys($keywords), /*array_keys($excludedKeywords),*/ array_keys($phrases), array_keys($excludedPhrases)));
    foreach($relevantTopics as $topic){
        $text = $textQueries[$topic]($song, $dbConn);
        if($text === null) continue;

        /* already filtered out in gatherSearchResultsByFields()
        foreach(($excludedKeywords[$topic] ?? []) as $keyword)
            if(mb_stripos($text, $keyword, 0, 'UTF-8') !== false)
                return SearchResultScore::newExcluded();
        */

        foreach(($excludedPhrases[$topic] ?? []) as $phrase)
            if($phrase !== '')
                if(mb_stripos($text, $phrase, 0, 'UTF-8') !== false)
                    return SearchResultScore::newExcluded();

        foreach(($keywords[$topic] ?? []) as $keyword) {
            if ($keyword !== '') {
                if (mb_stripos($text, $keyword, 0, 'UTF-8') !== false) {
                    array_push($matchedKeywords, $keyword);
                }
            }
        }

        foreach(($phrases[$topic] ?? []) as $phrase) {
            if ($phrase !== '') {
                if(mb_stripos($text, $phrase, 0, 'UTF-8') !== false) {
                    array_push($matchedPhrases, $phrase);
                }
            }
        }
    }

    $score->keywordMatchCount = count(array_unique($matchedKeywords));
    $score->phraseMatchCount = count(array_unique($matchedPhrases));

    // check for full matches (accumulated over all fields)
    $totalKeywordCount = array_reduce($keywords, function(int $sum, array $val){
        return $sum + count($val);
    }, 0);
    $totalPhraseCount = array_reduce($phrases, function(int $sum, array $val){
        return $sum + count($val);
    }, 0);

    if($totalKeywordCount === $score->keywordMatchCount)
        $score->fullKeywordsMatchCount = $totalKeywordCount;
    if($totalPhraseCount === $score->phraseMatchCount)
        $score->fullPhrasesMatchCount = $totalPhraseCount;

    return $score;
}

// #### helpers ####

/**
 * splits up the search-query into keywords, phrases, ranges and their excluded counterparts
 * @param string $search
 * @return array [keywords (without phrases), phrases, ranges, excludedKeywords (without phrases), excludedPhrases, excludedRanges]
 */
function parseSearch(string $search): array
{
    $wildcardSearch = str_replace('*', '%', str_replace('%', '%%', $search));
    [$nonPhrases, $phrases, $excludedPhrases] = splitPhrases($wildcardSearch);
    [$nonRanges, $ranges, $excludedRanges] = splitRanges($nonPhrases);
    [$keywords, $excludedKeywords] = splitKeywords($nonRanges);
    return [
        $keywords,
        $phrases,
        $ranges,
        $excludedKeywords,
        $excludedPhrases,
        $excludedRanges,
    ];
}

/**
 * @param string $search
 * @return array [keywords, excluded keywords]
 */
function splitKeywords(string $search): array
{
    $keywords = preg_split('<\\s+>', $search);
    return [
        array_values(array_filter($keywords, function ($s) {
            return $s && $s[0] !== '-';
        })),
        array_map(function ($s) {
            return ltrim($s, '-');
        }, array_values(array_filter($keywords, function ($s) {
            return $s && $s[0] === '-';
        }))),
    ];
}

/**
 * @param string $search
 * @return array [non-phrase search, phrases, excluded phrases]
 */
function splitPhrases(string $search): array
{
    $particles = explode('"', $search);
    $nonPhrases = array_map('trim', array_values(array_filter($particles, function ($index) {
        return $index % 2 === 0;
    }, ARRAY_FILTER_USE_KEY)));
    $phrases = array_map('trim', array_values(array_filter($particles, function ($index) {
        return $index % 2 === 1;
    }, ARRAY_FILTER_USE_KEY)));
    return [
        implode(' ', array_filter(array_map(function ($s) {
            return rtrim($s, '- ');
        }, $nonPhrases))),
        array_values(array_filter($phrases, function ($key) use ($nonPhrases) {
            return !$nonPhrases[$key] || $nonPhrases[$key][-1] !== '-';
        }, ARRAY_FILTER_USE_KEY)),
        array_values(array_filter($phrases, function ($key) use ($nonPhrases) {
            return $nonPhrases[$key] && $nonPhrases[$key][-1] === '-';
        }, ARRAY_FILTER_USE_KEY)),
    ];
}

/**
 * @param string $search
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

// #### request handlers ####

function handleCustomRequest(string $operation, string $tableName, ServerRequestInterface $request, $environment): ?ServerRequestInterface
{
    $uri = $request->getUri();
    if (rtrim($uri->getPath(), '/') === '/search') {
        $environment->search = $request->getQueryParams();
        return $request;
    }
    return $request->withUri(
        $uri
            ->withPath(addTablePrefixToRecordsPath($uri->getPath()))
            ->withQuery(addTablePrefixesToJoinParameter($uri->getQuery()))
    );
}

function handleCustomResponse(string $operation, string $tableName, ResponseInterface $response, $environment): ?ResponseInterface
{
    if (isset($environment->search)) {
        $factory = new Psr17Factory();

        $config = include __DIR__ . '/../config.inc.php';
        $db = new PDO(
            sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $config['address'],
                $config['port'],
                $config['database'],
                'utf8mb4'
            ),
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]
        );
        $fields = isset($environment->search['q'])
            ? ['' => $environment->search['q']]
            : [
                'song-name' => $environment->search['title'] ?? null,
                'composer' => $environment->search['composer'] ?? null,
                'writer' => $environment->search['writer'] ?? null,
                'song-cpr_y' => $environment->search['copyrightYear'] ?? null,
                'publisher' => $environment->search['publisher'] ?? null,
                'song-origin' => $environment->search['origin'] ?? null,
                'performer' => $environment->search['performer'] ?? null,
            ];
        $searchResults = gatherSearchResultsV3($db, $fields);
        $content = json_encode(
            ['records' => $searchResults],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $stream = $factory->createStream($content);
        $stream->rewind();
        return $factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withHeader('Content-Length', strlen($content))
            ->withBody($stream);
    }

    return $response;
}

function preventMutationOperations(string $operation, string $tableName): bool
{
    return $operation === 'list'     // /records/{TABLE}
        || $operation === 'read'     // /records/{TABLE}/{ID}
        || $operation === 'document' // /openapi
        ;
}
