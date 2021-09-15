<?php declare(strict_types=1);
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

require_once __DIR__ . '/types.inc.php';

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

/**
 * @param array $words array of keywords
 * @param PDO $dbConn db connection
 * @return array array of all song-ids with one or more matching words;
 *      the key is the song-id, the value is the match-count
 */
function collectWordMatches(array $words, PDO $dbConn): array {
    $ret = [];
    $query = $dbConn->query(sprintf('SELECT song, COUNT(song) AS c FROM mks_word_index WHERE word IN (%s) GROUP BY song ORDER BY c DESC;',
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

/**
 * computes the score for a song and the requested query
 * @param int $songId
 * @param array $keywords
 * @param array $phrases
 * @param array $excludedKeywords
 * @param array $excludedPhrases
 * @param int $keywordMatches
 * @param PDO $dbConn
 * @return SearchResultScore
 */
function scoreSong(int $songId, array $keywords, array $phrases, array $excludedKeywords, array $excludedPhrases, int $keywordMatches, PDO $dbConn): SearchResultScore {
    $score = SearchResultScore::newEmpty();

    // count matching phrases and search for exclusions
    $songInfo = getSongFullInfo($songId, $dbConn);
    foreach($songInfo as $infoPart){
        foreach ($excludedKeywords as $excludedKeyword)
            if(mb_stripos($infoPart, $excludedKeyword, 0, 'UTF-8') !== false)
                return SearchResultScore::newExcluded();

        foreach($excludedPhrases as $excludedPhrase)
            if(mb_stripos($infoPart, $excludedPhrase, 0, 'UTF-8') !== false)
                return SearchResultScore::newExcluded();

        foreach($phrases as $phrase)
            if(mb_stripos($infoPart, $phrase, 0, 'UTF-8') !== false)
                $score->phraseMatchCount += 1;
    }

    $score->keywordMatchCount = $keywordMatches;

    // if all keywords or phrases were matched, the result should be scored higher
    if($keywordMatches === count($keywords))
        $score->fullKeywordsMatchCount = $keywordMatches;
    if($score->phraseMatchCount == count($phrases))
        $score->fullPhrasesMatchCount = $score->phraseMatchCount;

    return $score;
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

function trimResults(array $results, int $keywordCount, int $phraseCount, bool $preferFullMatches = true, int $maxAmount = 20): array {//TODO default value for maxAmount is just an example threshold
    arsort($results);

    /* compute minimum score:
        if searched with keywords -> 1 * KEYWORD_MULTIPLIER
        if searched only with phrases -> 1 * PHRASE_MULTIPLIER
        if one result has a full match (count of matched keywords == keywordCount) -> $keywordCount * KEYWORD_FULL_MATCH_MULTIPLIER
    */

    $onlyPhrasesUsed = $keywordCount === 0;
    $minScore = $onlyPhrasesUsed ? SearchResultScore::PHRASE_MULTIPLIER : SearchResultScore::KEYWORD_MULTIPLIER;

    if($preferFullMatches && !$onlyPhrasesUsed) {
        foreach($results as $id => $score) {
            if($score->fullPhrasesMatchCount > 0){
                $minScore = $phraseCount * SearchResultScore::FULL_MATCH_MULTIPLIER * SearchResultScore::PHRASE_MULTIPLIER;
                break;
            }
            if($score->fullKeywordsMatchCount > 0) {
                $minScore = $keywordCount * SearchResultScore::FULL_MATCH_MULTIPLIER * SearchResultScore::KEYWORD_MULTIPLIER;
                break;
            }
        }
    }

    foreach($results as $id => $score)
        if($score->totalScore() < $minScore)
            unset($results[$id]);

    if(count($results) > $maxAmount)
        $results = array_slice($results, 0, $maxAmount, true);

    return $results;
}

function gatherSearchResults(string $search, PDO $db): array
{
    [$keywords, $phrases, $ranges, $excludedKeywords, $excludedPhrases, $excludedRanges] = parseSearch($search);
    // phrases have to be included into keywords for collectWordMatches
    $keywordsWithPhrases = [...$keywords];
    foreach($phrases as $phrase)
        $keywordsWithPhrases = [...$keywordsWithPhrases, ...preg_split('[\s+]', $phrase)];

    $matchedSongs = collectWordMatches($keywordsWithPhrases, $db);

    $resultIds = [];
    foreach($matchedSongs as $song => $matchCount){
        $score = scoreSong($song, $keywords, $phrases, $excludedKeywords, $excludedPhrases, $matchCount, $db);
        if($score->totalScore() > 0)
            $resultIds[$song] = $score;
    }

    $resultIds = trimResults($resultIds, count($keywords), count($phrases));

    return getSongInfoMulti(array_keys($resultIds), $db);
}

/*
 * uses slow search-query (with 'LIKE') which supports wildcards
 */
function gatherSearchResultsWithWildcards(string $search, PDO $db): array {
    [$keywords, $phrases, $ranges, $excludedKeywords, $excludedPhrases, $excludedRanges] = parseSearch($search);
    // phrases have to be included into keywords for buildSongMatches
    $allKeywords = [...$keywords];
    foreach($phrases as $phrase)
        $allKeywords = [...$allKeywords, $phrase];
    $allExcludedKeywords = [...$excludedKeywords];
    foreach($excludedPhrases as $phrase)
        $allExcludedKeywords = [...$allExcludedKeywords, $phrase];

    $relevanceMap = buildSongMatches($db, $allKeywords);
    foreach (buildSongMatches($db, $allExcludedKeywords) as $songId => $exclusionMatches) {
        unset($relevanceMap[$songId]);
    }
    $andMatches = array_filter($relevanceMap, fn($r) => $r === count($keywords));

    return getSongInfoMulti(array_keys(count($andMatches) > 0 ? $andMatches : $relevanceMap), $db);
}

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
        array_values(array_filter($keywords, fn($s) => $s && $s[0] !== '-')),
        array_map(fn($s) => ltrim($s, '-'), array_values(array_filter($keywords, fn($s) => $s && $s[0] === '-'))),
    ];
}

/**
 * @param string $search
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
    if (isset($environment->search['q'])) {
        $engine = 'v2';
        if(isset($environment->search['engine'])){
            switch ($environment->search['engine']){
                case 'v1':
                    $engine = 'v1';
                    break;
            }
        }

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
            $config['password']
        );

        $searchResults = null;
        switch ($engine){
            case 'v1':
                $searchResults = gatherSearchResultsWithWildcards($environment->search['q'], $db);
                break;
            case 'v2':
                $searchResults = gatherSearchResults($environment->search['q'], $db);
                break;
        }

        $content = json_encode(
            ['records' => array_map(
                function ($x) {
                    $x->id = intval($x->id);
                    return $x;
                },
                $searchResults
            )],
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

//NOTE would the name 'is(Not)MutationOperation' fit better for this functions function
function preventMutationOperations(string $operation, string $tableName): bool
{
    return $operation === 'list'     // /records/{TABLE}
        || $operation === 'read'     // /records/{TABLE}/{ID}
        || $operation === 'document' // /openapi
        ;
}
