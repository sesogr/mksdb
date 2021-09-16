<?php declare(strict_types=1);

include_once __DIR__ . "/SubscribableLogger.inc.php";

class DbIndexer
{

    private const INDEX_NAME = "mks_word_index";

    private $dbConn;
    private $logger;

    public function __construct(PDO $pdo, SubscribableLogger $logger){
        $this->dbConn = $pdo;
        $this->logger = $logger;
    }

    public function clearIndex()
    {
        $this->dbConn->exec('DROP TABLE IF EXISTS ' . self::INDEX_NAME);
        $this->dbConn->exec(sprintf('CREATE TABLE %s (
                                word text         not null,
                                song int unsigned not null,
                                topic text        not null,
                                unique (word, song, topic),
                                constraint %1$s_ibfk_1
                                foreign key (song) references mks_song (id)
                                on update cascade on delete cascade
                            );', self::INDEX_NAME));
    }

    public function listTables(): array
    {
        $JOIN_TABLE_PREFIX = 'mks_x_';
        $JOIN_TABLE_PREFIX_LEN = strlen($JOIN_TABLE_PREFIX);
        $EXCLUDE = ['20201217-oeaw-schlager-db', self::INDEX_NAME];

        $tables = [];
        $stm = $this->dbConn->query('SHOW TABLES;');
        foreach ($stm->fetchAll(PDO::FETCH_NUM) as $row) {
            $table = $row[0];

            // exclude join-tables (-> if it starts with $JOIN_TABLE_PREFIX)
            if (substr($table, 0, $JOIN_TABLE_PREFIX_LEN) === $JOIN_TABLE_PREFIX) continue;
            // exclude others
            if (in_array($table, $EXCLUDE)) continue;

            array_push($tables, $table);
        }

        return $tables;
    }

    /**
     * creates index-data for each word in each column of the table;
     * index-format: [word => [song-id => topic, ...], ...]
     * @param string $table
     * @return false|mixed
     */
    function indexTable(string $table)
    {
        /*
         * possible topics: city, collection, genre, composer, cover_artist, performer, writer, publisher, source,
         *      song-name, song-cpr_y, song-cpr_remark, song-created, song-label, song-pub_ser, song-pub_nr, song-rec_nr,
         *      song-origin, song-dedication, song-rev, song-addition
         */

        // map functions to tables; function returns: [word => [song-id => topic, ...], ...]
        $indexers = [
            'mks_city' => function () {
                $ret = [];
                $stm = $this->dbConn->query("SELECT strip_punctuation(city.name), song.id FROM (mks_city as city
                                            INNER JOIN mks_x_publication_place_song x on city.id = x.publication_place_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

                foreach ($stm->fetchAll(PDO::FETCH_NUM) as $row) {
                    $text = $row[0];
                    $song = $row[1];

                    // split text and create mapping
                    foreach ($this->splitText($text) as $word)
                        if($word !== '')
                            $this->mapDeepPutOrAdd($ret, 'city', $word, $song);
                }

                return $ret;
            },
            'mks_collection' => function () {
                $ret = [];
                $stm = $this->dbConn->query("SELECT strip_punctuation(coll.name), song.id FROM (mks_collection as coll
                                            INNER JOIN mks_x_collection_song x on coll.id = x.collection_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

                foreach ($stm->fetchAll(PDO::FETCH_NUM) as $row) {
                    $text = $row[0];
                    $song = $row[1];

                    // split text and create mapping
                    foreach ($this->splitText($text) as $word)
                        if($word !== '')
                            $this->mapDeepPutOrAdd($ret, 'collection', $word, $song);
                }

                return $ret;
            },
            'mks_genre' => function () {
                $ret = [];
                $stm = $this->dbConn->query("SELECT strip_punctuation(genre.name), song.id FROM (mks_genre as genre
                                            INNER JOIN mks_x_genre_song x on genre.id = x.genre_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

                foreach ($stm->fetchAll(PDO::FETCH_NUM) as $row) {
                    $text = $row[0];
                    $song = $row[1];

                    // split text and create mapping
                    foreach ($this->splitText($text) as $word)
                        if($word !== '')
                            $this->mapDeepPutOrAdd($ret, 'genre', $word, $song);
                }

                return $ret;
            },
            'mks_person' => function () {
                $ret = [];
                $selects = [
                    'composer' => "SELECT strip_punctuation(person.name), song.id FROM (mks_person as person
                                            INNER JOIN mks_x_composer_song x on person.id = x.composer_id
                                            INNER JOIN mks_song song on x.song_id = song.id)",
                    'cover_artist' => "SELECT strip_punctuation(person.name), song.id FROM (mks_person as person
                                            INNER JOIN mks_x_cover_artist_song x on person.id = x.cover_artist_id
                                            INNER JOIN mks_song song on x.song_id = song.id)",
                    'performer' => "SELECT strip_punctuation(person.name), song.id FROM (mks_person as person
                                            INNER JOIN mks_x_performer_song x on person.id = x.performer_id
                                            INNER JOIN mks_song song on x.song_id = song.id)",
                    'writer' => "SELECT strip_punctuation(person.name), song.id FROM (mks_person as person
                                            INNER JOIN mks_x_writer_song x on person.id = x.writer_id
                                            INNER JOIN mks_song song on x.song_id = song.id)"
                ];

                foreach ($selects as $topic => $select) {
                    foreach ($this->dbConn->query($select)->fetchAll(PDO::FETCH_NUM) as $row) {
                        $text = $row[0];
                        $song = $row[1];

                        // split text and create mapping
                        foreach ($this->splitText($text) as $word)
                            if($word !== '')
                                $this->mapDeepPutOrAdd($ret, $topic, $word, $song);
                    }
                }

                return $ret;
            },
            'mks_publisher' => function () {
                $ret = [];
                $stm = $this->dbConn->query("SELECT strip_punctuation(publ.name), song.id FROM (mks_publisher as publ
                                            INNER JOIN mks_x_publisher_song x on publ.id = x.publisher_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

                foreach ($stm->fetchAll(PDO::FETCH_NUM) as $row) {
                    $text = $row[0];
                    $song = $row[1];

                    // split text and create mapping
                    foreach ($this->splitText($text) as $word)
                        if($word !== '')
                            $this->mapDeepPutOrAdd($ret, 'publisher', $word, $song);
                }

                return $ret;
            },
            'mks_source' => function () {
                $ret = [];
                $stm = $this->dbConn->query("SELECT strip_punctuation(source.name), song.id FROM (mks_source as source
                                            INNER JOIN mks_x_source_song x on source.id = x.source_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

                foreach ($stm->fetchAll(PDO::FETCH_NUM) as $row) {
                    $text = $row[0];
                    $song = $row[1];

                    // split text and create mapping
                    foreach ($this->splitText($text) as $word)
                        if($word !== '')
                            $this->mapDeepPutOrAdd($ret, 'source', $word, $song);
                }

                return $ret;
            },
            'mks_song' => function () {
                $ret = [];
                $stm = $this->dbConn->query("SELECT id,
                    strip_punctuation(name) as 'song-name', strip_punctuation(label) as 'song-label',
                    strip_punctuation(origin) as 'song-origin', strip_punctuation(dedication) as 'song-dedication',
                    strip_punctuation(review) as 'song-rev', strip_punctuation(addition) as 'song-addition',
                    strip_punctuation(copyright_year) as 'song-cpr_y', strip_punctuation(copyright_remark) as 'song-cpr_remark',
                    strip_punctuation(created_on) as 'song-created', strip_punctuation(publisher_series) as 'song-pub_ser',
                    strip_punctuation(publisher_number) as 'song-pub_nr', strip_punctuation(record_number) as 'song-rec_nr'
                    FROM mks_song");

                foreach ($stm->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $song = $row['id'];

                    foreach($row as $col => $text){
                        if($text === null or $col === 'id') continue;
                        // split text and create mapping
                        foreach ($this->splitText($text) as $word)
                            if($word !== '')
                                $this->mapDeepPutOrAdd($ret, $col, $word, $song);
                    }
                }

                return $ret;
            }
        ];

        $indexer = $indexers[$table] ?? null;
        if ($indexer != null) {
            return $indexer();
        } else {
            $this->logger->log('warn', "no indexer found for $table");
            return false;
        }
    }

    /**
     * writes the index-data (created by indexTable) into the db
     * @param array $indexData array of the format [word => [song-id => topic, ...], ...]
     */
    public function writeIndex(array $indexData)
    {
        $stm = $this->dbConn->prepare('INSERT IGNORE INTO mks_word_index VALUES (?, ?, ?)');

        foreach ($indexData as $word => $songs)
            foreach ($songs as $song => $topics)
                foreach($topics as $topic)
                    $stm->execute([$word, $song, $topic]);
    }

    /**
     * splits a text into its words by splitting at all whitespaces and newlines; trims string before splitting
     * @param string $text text to split
     * @return array the separate words
     */
    public function splitText(string $text): array
    {
        return preg_split('[\s+]', trim($text));
    }

    /**
     * adds the <code>value</code> to the bottom-level array (makes no duplicates) for <code>keys</code>
     * or creates a new array with the <code>value</code> if the map does not contain that key (also creates whole sub-path);
     * the array is located by the <code>keys</code> from top- to bottom-level
     * @param array $map the map (may be nested)
     * @param string $value the value to add
     * @param string ...$keys the keys to locate the array
     */
    public function mapDeepPutOrAdd(array &$map, string $value, string ...$keys): void
    {
        if(count($keys) > 1){
            $key = array_shift($keys);

            // if map does not contain sub-path -> create it (level per level)
            if(!array_key_exists($key, $map))
                $map[$key] = [];

            // go to next level
            $this->mapDeepPutOrAdd($map[$key], $value, ...$keys);// keys were shifted
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
    public function mapDeepMerge(array &$map, array $inp)
    {
        foreach($inp as $iKey => $iVal){
            if(gettype($iVal) === 'array'){
                // merge sub-map
                if(isset($map[$iKey])){
                    $mVal = &$map[$iKey];
                    if(gettype($mVal) !== 'array')
                        throw new UnexpectedValueException('array-value can not be merged into non-array-value in map');

                    $this->mapDeepMerge($mVal, $iVal);
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
}
