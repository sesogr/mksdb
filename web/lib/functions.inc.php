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

function displayNotFound(): void {
    include __DIR__ . '/../pages/not-found.php';
}

function displayRecordDetails(PDO $db, int $id): void
{
    $getSong = $db->prepare('select * from mks_song where id = ?');
    $listCollectionRecords = $db->prepare('select * from mks_x_collection_song a join mks_collection b on b.id = a.collection_id where song_id = ? order by a.position');
    $listComposerRecords = $db->prepare('select * from mks_x_composer_song a join mks_person b on b.id = a.composer_id where song_id = ? order by a.position');
    $listCoverArtistRecords = $db->prepare('select * from mks_x_cover_artist_song a join mks_person b on b.id = a.cover_artist_id where song_id = ? order by a.position');
    $listGenreRecords = $db->prepare('select * from mks_x_genre_song a join mks_genre b on b.id = a.genre_id where song_id = ? order by a.position');
    $listPerformerRecords = $db->prepare('select * from mks_x_performer_song a join mks_person b on b.id = a.performer_id where song_id = ? order by a.position');
    $listPublicationPlaceRecords = $db->prepare('select * from mks_x_publication_place_song a join mks_city b on b.id = a.publication_place_id where song_id = ? order by a.position');
    $listPublisherRecords = $db->prepare('select * from mks_x_publisher_song a join mks_publisher b on b.id = a.publisher_id where song_id = ? order by a.position');
    $listSourceRecords = $db->prepare('select * from mks_x_source_song a join mks_source b on b.id = a.source_id where song_id = ? order by a.position');
    $listWriterRecords = $db->prepare('select * from mks_x_writer_song a join mks_person b on b.id = a.writer_id where song_id = ? order by a.position');
    $getSong->execute([$id]);
    foreach ($getSong->fetchAll(PDO::FETCH_OBJ) as $song) {
        $song->composers = [];
        $song->writers = [];
        $song->coverArtists = [];
        $song->performers = [];
        $song->publishers = [];
        $song->publicationPlaces = [];
        $song->genres = [];
        $song->collections = [];
        $song->sources = [];
        $listCollectionRecords->execute([$id]);
        $listComposerRecords->execute([$id]);
        $listCoverArtistRecords->execute([$id]);
        $listGenreRecords->execute([$id]);
        $listPerformerRecords->execute([$id]);
        $listPublicationPlaceRecords->execute([$id]);
        $listPublisherRecords->execute([$id]);
        $listSourceRecords->execute([$id]);
        $listWriterRecords->execute([$id]);
        foreach ($listCollectionRecords->fetchAll(PDO::FETCH_OBJ) as $collection) {
            $song->collections[] = (object)[
                'position' => $collection->position,
                'annotation' => $collection->annotation,
                'collection' => (object)['name' => $collection->name],
            ];
        }
        foreach ($listComposerRecords->fetchAll(PDO::FETCH_OBJ) as $composer) {
            $song->composers[] = (object)[
                'position' => $composer->position,
                'annotation' => $composer->annotation,
                'person' => (object)['name' => $composer->name],
            ];
        }
        foreach ($listCoverArtistRecords->fetchAll(PDO::FETCH_OBJ) as $coverArtist) {
            $song->coverArtists[] = (object)[
                'position' => $coverArtist->position,
                'annotation' => $coverArtist->annotation,
                'person' => (object)['name' => $coverArtist->name],
            ];
        }
        foreach ($listGenreRecords->fetchAll(PDO::FETCH_OBJ) as $genre) {
            $song->genres[] = (object)[
                'position' => $genre->position,
                'annotation' => $genre->annotation,
                'genre' => (object)['name' => $genre->name],
            ];
        }
        foreach ($listPerformerRecords->fetchAll(PDO::FETCH_OBJ) as $performer) {
            $song->performers[] = (object)[
                'position' => $performer->position,
                'annotation' => $performer->annotation,
                'person' => (object)['name' => $performer->name],
            ];
        }
        foreach ($listPublicationPlaceRecords->fetchAll(PDO::FETCH_OBJ) as $publicationPlace) {
            $song->publicationPlaces[] = (object)[
                'position' => $publicationPlace->position,
                'annotation' => $publicationPlace->annotation,
                'city' => (object)['name' => $publicationPlace->name],
            ];
        }
        foreach ($listPublisherRecords->fetchAll(PDO::FETCH_OBJ) as $publisher) {
            $song->publishers[] = (object)[
                'position' => $publisher->position,
                'annotation' => $publisher->annotation,
                'publisher' => (object)['name' => $publisher->name],
            ];
        }
        foreach ($listSourceRecords->fetchAll(PDO::FETCH_OBJ) as $source) {
            $song->sources[] = (object)[
                'position' => $source->position,
                'annotation' => $source->annotation,
                'source' => (object)['name' => $source->name],
            ];
        }
        foreach ($listWriterRecords->fetchAll(PDO::FETCH_OBJ) as $writer) {
            $song->writers[] = (object)[
                'position' => $writer->position,
                'annotation' => $writer->annotation,
                'person' => (object)['name' => $writer->name],
            ];
        }
        include __DIR__ . '/../pages/detail.php';
        return;
    }
    displayNotFound();
}

function displaySearchForm (): void {
    include __DIR__ . '/../pages/search.php';
}

function displaySearchResults (PDO $db, string $search): void {
    [$keywords, $ranges, $excludedKeywords, $excludedRanges] = parseSearch($search);
    $relevanceMap = buildSongMatches($db, $keywords);
    foreach (buildSongMatches($db, $excludedKeywords) as $songId => $exclusionMatches) {
        unset($relevanceMap[$songId]);
    }
    $andMatches = array_filter($relevanceMap, fn($r) => $r === count($keywords));
    $results = count($relevanceMap) > 0
        ? $db->query(
            sprintf(<<<'SQL'
                select
                    a.id,
                    a.name title,
                    concat(d.name, b.annotation) composer,
                    a.created_on,
                    concat(e.name, c.annotation) performer,
                    a.origin,
                    a.index_no `index`
                from mks_song a
                left join mks_x_composer_song b on a.id = b.song_id and b.position = 1
                left join mks_x_performer_song c on a.id = c.song_id and c.position = 1
                left join mks_person d on d.id = b.composer_id
                left join mks_person e on e.id = c.performer_id
                where a.id in (%s)
                SQL,
                implode(',', array_keys(count($andMatches) > 0 ? $andMatches : $relevanceMap))
            )
        )->fetchAll(PDO::FETCH_OBJ)
        : [];
    include __DIR__ . '/../pages/results.php';
}

function handleRequest(array $get, array $post, array $server, PDO $db): void
{
    if ($get['id'] ?? null) {
        if (is_numeric($get['id']) && $get['id'] > 0) {
            displayRecordDetails($db, intval($get['id']));
        } else {
            displayNotFound();
        }
    } elseif ($post['ce1a388c'] ?? null) {
        displaySearchResults($db, $post['ce1a388c']);
    } else {
        displaySearchForm();
    }
}

function out($template, ...$args): void
{
    echo $template ? htmlspecialchars(count($args) > 0 ? sprintf($template, ...$args) : $template) : '';
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
