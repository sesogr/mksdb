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

/**
 * @param iterable|CsvTableSong[] $songs
 * @param array $filters [topic:string => [keywords:string[], phrases:string[], excludedKeywords:string[],
 *     excludedPhrases:string[]]]
 *
 * @return Generator|array [:CsvTableSong, score:int]
 */
function filterAndScoreSongs(iterable $songs, array $filters): Generator
{
    $epsilon = 1e-5;
    foreach ($songs as $song) {
        $score = 1;
        foreach ($filters as $topic1 => [$keywords, $phrases, $excludedKeywords, $excludedPhrases]) {
            if ($topic1) {
                if (matchValue($song->$topic1, array_merge($excludedKeywords, $excludedPhrases)) > $epsilon) {
                    $score = 0;
                    break;
                } else {
                    $score *= matchValue($song->$topic1, array_merge($keywords, $phrases));
                }
            } else {
                foreach ($song as $topic2 => $value) {
                    if (matchValue($song->$topic2, array_merge($excludedKeywords, $excludedPhrases)) > $epsilon) {
                        $score = 0;
                        break;
                    } else {
                        $score *= matchValue($song->$topic2, array_merge($keywords, $phrases));
                    }
                }
            }
        }
        if ($score > $epsilon) {
            yield [$song, $score];
        }
    }
}

/**
 * @param PDO $db
 * @param array $fields [topic:string => search:string]
 * @param bool $expandToOr
 *
 * @return array|SearchResult[]
 * @throws PDOException
 */
function gatherSearchResultsV3(PDO $db, array $fields, bool $expandToOr): array
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
    // file_put_contents(__FILE__.'.log', json_encode($filters));
    $scoredSongs = iterator_to_array(
        filterAndScoreSongs(
            mapSongIdsToSongs(mergeAndSortIterators($iterators), $db, 256),
            $filters
        )
    );
    if ($expandToOr) {
        usort($scoredSongs, fn(array $a, array $b) => $b[1] - $a[1]); // descending
    } else {
        $scoredSongs = array_filter($scoredSongs, fn(array $songAndScore) => $songAndScore[1] > 1 - 1e-5);
    }
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
        $searchResults = gatherSearchResultsV3($db, $fields, ($environment->search['expandToOr'] ?? '0') === '1');
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
                                ? [
                                sprintf(
                                    'left(`reverse`, %d) = %s',
                                    $len - 1 - $pos,
                                    $db->quote(mbReverse(mb_substr($word, $pos + 1)))
                                ),
                            ]
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
 * @param iterable|string[] $songIds
 * @param PDO $db
 * @param int $clusterSize
 *
 * @return Generator|CsvTableSong[]
 * @throws PDOException
 */
function mapSongIdsToSongs(iterable $songIds, PDO $db, int $clusterSize = 256): Generator
{
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

function matchValue(?string $subject, array $searchPhrases): float
{
    $score = 0;
    if ($subject) {
        $normalized = stripPunctuationAndPad($subject);
        foreach ($searchPhrases as $phrase) {
            $pattern = sprintf(
                '<%s>u',
                str_replace(
                    '*',
                    '[\\pN\\pL]+',
                    str_replace(' * ', ' [\\pN\\pL ]+ ', stripPunctuationAndPad($phrase, true))
                )
            );
            if (preg_match($pattern, $normalized)) {
                $score++;
            }
        }
    }
    return $searchPhrases ? $score / count($searchPhrases) : 0;
}

function mbReverse(string $input): string
{
    return implode('', array_reverse(preg_split('<>u', $input)));
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
 * splits up the search-query into keywords, phrases, ranges and their excluded counterparts
 *
 * @param string $search
 *
 * @return array [keywords (without phrases), phrases, ranges, excludedKeywords (without phrases), excludedPhrases,
 *     excludedRanges]
 */
function parseSearchV3(string $search): array
{
    [$nonPhrases, $phrases, $excludedPhrases] = splitPhrases($search);
    [$keywords, $excludedKeywords] = splitKeywords($nonPhrases);
    return [$keywords, $phrases, $excludedKeywords, $excludedPhrases];
}

function stripPunctuationAndPad(string $text, bool $allowAsterisk = false): string
{
    $pattern = sprintf("<[^\\pN\\pL%s]+>u", $allowAsterisk ? '*' : '');
    return sprintf(' %s ', mb_strtolower(implode(' ', array_filter(preg_split($pattern, $text)))));
}

/**
 * @param string $search
 *
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
 *
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
