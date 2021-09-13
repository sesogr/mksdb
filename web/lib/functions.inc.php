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
 * @param array $excludedWords array of keywords, if match the song will be excluded
 * @param PDO $dbConn db connection
 * @return array array of all song-ids with one or more matching words;
 *      the key is the song-id, the value is the match-count
 */
function collectWordMatches(array $words, array $excludedWords, PDO $dbConn): array {
    $ret = [];
    $qIncStr = sprintf("word IN (%s)", '\'' . implode('\', \'', $words) . '\'');
    $qExStr = sprintf("word NOT IN (%s)", '\'' . implode('\', \'', $excludedWords) . '\'');
    $queryStr = "SELECT song, COUNT(song) AS c FROM mks_word_index" . " WHERE " . $qIncStr . (count($excludedWords) > 0 ? " AND " .$qExStr : "") . " GROUP BY song ORDER BY c DESC;";
    $query = $dbConn->query($queryStr);
    foreach ($query->fetchAll(PDO::FETCH_NUM) as $row){
        $ret[$row[0]] = (int)$row[1];
    }
    return $ret;
}

/**
 * returns all strings for the song (all columns of mks_song and all of its joins)
 * @param int $songId
 * @param PDO $dbConn
 * @return array array of all strings related to the song
 */
function getSongFullInfo(int $songId, PDO $dbConn): array {
    //TODO which columns are relevant for the search?; is my SQL syntax performant?
    //TODO maybe this could be cached for the request-time
    $stm = $dbConn->prepare("SELECT s.name, s.label, s.origin, s.dedication, s.review, s.addition,
       s.copyright_year, s.copyright_remark, s.created_on, s.publisher_series, s.publisher_number, s.record_number,
       coll.name, comp.name, cover.name, gen.name, perf.name, pubp.name, pub.name, src.name, wrt.name
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
        print "error in query ::getSongFullInfo: for item $songId ; " . implode(', ', $stm->errorInfo()) . "\n";
        return $ret;
    }
    foreach($row as $col)
        if($col !== null and $col != '')
            $ret[] = $col;

    return $ret;
}

/*
function filterExclusions(array $songs, array $excludedKeywords, array $excludedPhrases, PDO $dbConn): array {
    //$exclusionList = array_merge($excludedKeywords, $excludedPhrases);
    //NOTE skipped checking for excluded keywords because this is done in collectWordMatches
    $exclusionList = $excludedPhrases;

    $filtered = [];
    foreach($songs as $song){
        $songInfo = getSongFullInfo($song, $dbConn);
        foreach($exclusionList as $exclude)
            foreach($songInfo as $songInfoPart)
                if (strpos($songInfoPart, $exclude) !== false)
                    continue 3;// skip song

        $filtered[] = $song;
    }
    return $filtered;
}
*/

function scoreSong(int $songId, array $keywords, array $phrases, array $excludedKeywords, array $excludedPhrases, int $keywordMatches, PDO $dbConn): int {
    //TODO count keyword and phrases occurrences in full song info
    // (keywords => 1, full match keywords => 10, phrases => 100; with excluded => -9999)
    // (TODO adjust score weight)
    $score = 0;

    //NOTE skipped checking for excluded keywords because this is done in collectWordMatches

    // if all keywords were matched, the result should be scored higher
    //  (treat it like a lite version of a phrase)
    if($keywordMatches === count($keywords))
        $score += 10 * $keywordMatches;
    else
        $score += $keywordMatches;

    // count matching phrases (scores the most)
    $songInfo = getSongFullInfo($songId, $dbConn);
    $i = 0;
    foreach($songInfo as $infoPart){
        if($i === 0){//if($infoPart === 'Reich mir noch einmal die Hände'){
            print "it should match ($infoPart)\n";
            $a = mb_stripos(mb_strtolower($infoPart, 'UTF-8'), mb_strtolower("NoCh EiNmAl DiE hÄnDe", 'UTF-8'), 0, 'UTF-8');
            print "pos with lc: $a \n";
            $a = mb_stripos($infoPart, "NoCh EiNmAl DiE hÄnDe", 0, 'UTF-8');
            print "pos without lc: $a \n";
        }
        $i += 1;

        foreach($excludedPhrases as $excludedPhrase)
            if(mb_stripos($infoPart, $excludedPhrase, 0, 'UTF-8') !== false)
                return -9999;
        foreach($phrases as $phrase)
            if(mb_stripos($infoPart, $phrase, 0, 'UTF-8') !== false)
                $score += 100;
    }

    return $score;
}

function getSongInfoMulti(array $songs, PDO $dbConn): array {
    $query = $dbConn->query(sprintf("
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
                where a.id in (%s)",
        '\'' . implode('\', \'', $songs) . '\''));
    return $query->fetchAll(PDO::FETCH_CLASS, SearchResult::class);
}

function trimResults(array $results, int $keywordCount, int $phraseCount, int $maxAmount): array {
    arsort($results);

    // compute minimum score: if searched with keywords => 1, if searched only with phrases => $phraseCount * phraseMultiplier
    $minScore = $keywordCount > 0 ? 1 : $phraseCount * 100;

    foreach($results as $id => $score)
        if($score < $minScore)
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

    $matchedSongs = collectWordMatches($keywordsWithPhrases , $excludedKeywords, $db);
    print "search words: " . implode(', ', $keywordsWithPhrases) . "\n";
    print "possible results for query $search : " . implode(', ', array_keys($matchedSongs)) . "\n";
    //$possibleSongs = filterExclusions($possibleSongs, $excludedKeywords, $excludedPhrases, $db);

    $resultIds = [];
    foreach($matchedSongs as $song => $matchCount){
        $score = scoreSong($song, $keywords, $phrases, $excludedKeywords, $excludedPhrases, $matchCount, $db);
        //print "score for item $song : $score \n";
        if($score > 0)
            $resultIds[$song] = $score;
    }

    $resultIds = trimResults($resultIds, count($keywords), count($phrases), 20);//TODO this is just an example threshold

    print "results for query $search : " . implode(', ', array_keys($resultIds)) . "\n";
    return getSongInfoMulti(array_keys($resultIds), $db);



//    $relevanceMap = buildSongMatches($db, $keywords);
//    foreach (buildSongMatches($db, $excludedKeywords) as $songId => $exclusionMatches) {
//        unset($relevanceMap[$songId]);
//    }
//    $andMatches = array_filter($relevanceMap, fn($r) => $r === count($keywords));
//    return count($relevanceMap) > 0
//        ? $db->query(
//            sprintf(<<<'SQL'
//                select
//                    a.id,
//                    a.name title,
//                    concat(d.name, b.annotation) composer,
//                    concat(e.name, c.annotation) writer,
//                    a.copyright_year,
//                    a.origin
//                from mks_song a
//                left join mks_x_composer_song b on a.id = b.song_id and b.position = 1
//                left join mks_x_writer_song c on a.id = c.song_id and c.position = 1
//                left join mks_person d on d.id = b.composer_id
//                left join mks_person e on e.id = c.writer_id
//                where a.id in (%s)
//                SQL,
//                implode(',', array_keys(count($andMatches) > 0 ? $andMatches : $relevanceMap))
//            )
//        )->fetchAll(PDO::FETCH_CLASS, SearchResult::class)
//        : [];
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
        $content = json_encode(
            ['records' => array_map(
                function ($x) {
                    $x->id = intval($x->id);
                    return $x;
                },
                gatherSearchResults($environment->search['q'], $db)
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
