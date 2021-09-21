<?php declare(strict_types=1);

include_once __DIR__ . "/SubscribableLogger.inc.php";
require_once __DIR__ . '/../../web/lib/utils.inc.php';

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
        $this->dbConn->exec(
            sprintf(
                <<<SQL
                    CREATE TABLE %s (
                        word varchar(255) not null,
                        reverse varchar(255) not null,
                        song int unsigned not null,
                        topics set(
                            'city', 'publisher', 'song-name', 'source', 'genre', 'song-addition', 'song-origin', 'collection',
                            'performer', 'song-rev', 'song-cpr_remark', 'song-rec_nr', 'song-dedication', 'composer', 'writer',
                            'cover_artist', 'song-label', 'song-created', 'song-pub_ser', 'song-pub_nr', 'song-cpr_y'
                            ) not null,
                        index (word),
                        index (reverse),
                        unique (word, song),
                        foreign key (song) references mks_song (id)
                        on update cascade on delete cascade
                    );
                    SQL,
                self::INDEX_NAME
            )
        );
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
    function indexTable(string $table, array &$index)
    {
        /*
         * possible topics: city, collection, genre, composer, cover_artist, performer, writer, publisher, source,
         *      song-name, song-cpr_y, song-cpr_remark, song-created, song-label, song-pub_ser, song-pub_nr, song-rec_nr,
         *      song-origin, song-dedication, song-rev, song-addition
         */

        // map functions to tables; function returns: [word => [song-id => topic, ...], ...]
        $indexers = [
            'mks_city' => function () use (&$index) {
                $stm = $this->dbConn->query("SELECT city.name, song.id FROM (mks_city as city
                                            INNER JOIN mks_x_publication_place_song x on city.id = x.publication_place_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

                foreach ($stm->fetchAll(PDO::FETCH_NUM) as $row) {
                    $text = $row[0];
                    $song = $row[1];

                    // split text and create mapping
                    foreach ($this->splitText($text) as $word)
                        if($word !== '')
                            $index[mb_strtolower($word)][$song]['city'] = true;
                }

                return $index;
            },
            'mks_collection' => function () use (&$index) {
                $stm = $this->dbConn->query("SELECT coll.name, song.id FROM (mks_collection as coll
                                            INNER JOIN mks_x_collection_song x on coll.id = x.collection_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

                foreach ($stm->fetchAll(PDO::FETCH_NUM) as $row) {
                    $text = $row[0];
                    $song = $row[1];

                    // split text and create mapping
                    foreach ($this->splitText($text) as $word)
                        if($word !== '')
                            $index[mb_strtolower($word)][$song]['collection'] = true;
                }

                return $index;
            },
            'mks_genre' => function () use (&$index) {
                $stm = $this->dbConn->query("SELECT genre.name, song.id FROM (mks_genre as genre
                                            INNER JOIN mks_x_genre_song x on genre.id = x.genre_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

                foreach ($stm->fetchAll(PDO::FETCH_NUM) as $row) {
                    $text = $row[0];
                    $song = $row[1];

                    // split text and create mapping
                    foreach ($this->splitText($text) as $word)
                        if($word !== '')
                            $index[mb_strtolower($word)][$song]['genre'] = true;
                }

                return $index;
            },
            'mks_person' => function () use (&$index) {
                $selects = [
                    'composer' => "SELECT person.name, song.id FROM (mks_person as person
                                            INNER JOIN mks_x_composer_song x on person.id = x.composer_id
                                            INNER JOIN mks_song song on x.song_id = song.id)",
                    'cover_artist' => "SELECT person.name, song.id FROM (mks_person as person
                                            INNER JOIN mks_x_cover_artist_song x on person.id = x.cover_artist_id
                                            INNER JOIN mks_song song on x.song_id = song.id)",
                    'performer' => "SELECT person.name, song.id FROM (mks_person as person
                                            INNER JOIN mks_x_performer_song x on person.id = x.performer_id
                                            INNER JOIN mks_song song on x.song_id = song.id)",
                    'writer' => "SELECT person.name, song.id FROM (mks_person as person
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
                                $index[mb_strtolower($word)][$song][$topic] = true;
                    }
                }

                return $index;
            },
            'mks_publisher' => function () use (&$index) {
                $stm = $this->dbConn->query("SELECT publ.name, song.id FROM (mks_publisher as publ
                                            INNER JOIN mks_x_publisher_song x on publ.id = x.publisher_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

                foreach ($stm->fetchAll(PDO::FETCH_NUM) as $row) {
                    $text = $row[0];
                    $song = $row[1];

                    // split text and create mapping
                    foreach ($this->splitText($text) as $word)
                        if($word !== '')
                            $index[mb_strtolower($word)][$song]['publisher'] = true;
                }

                return $index;
            },
            'mks_source' => function () use (&$index) {
                $stm = $this->dbConn->query("SELECT source.name, song.id FROM (mks_source as source
                                            INNER JOIN mks_x_source_song x on source.id = x.source_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

                foreach ($stm->fetchAll(PDO::FETCH_NUM) as $row) {
                    $text = $row[0];
                    $song = $row[1];

                    // split text and create mapping
                    foreach ($this->splitText($text) as $word)
                        if($word !== '')
                            $index[mb_strtolower($word)][$song]['source'] = true;
                }

                return $index;
            },
            'mks_song' => function () use (&$index) {
                $stm = $this->dbConn->query("SELECT id,
                    name as 'song-name', label as 'song-label',
                    origin as 'song-origin', dedication as 'song-dedication',
                    review as 'song-rev', addition as 'song-addition',
                    copyright_year as 'song-cpr_y', copyright_remark as 'song-cpr_remark',
                    created_on as 'song-created', publisher_series as 'song-pub_ser',
                    publisher_number as 'song-pub_nr', record_number as 'song-rec_nr'
                    FROM mks_song");

                foreach ($stm->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $song = $row['id'];

                    foreach($row as $col => $text){
                        if($text === null or $col === 'id') continue;
                        // split text and create mapping
                        foreach ($this->splitText($text) as $word)
                            if($word !== '')
                                $index[mb_strtolower($word)][$song][$col] = true;
                    }
                }

                return $index;
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
        $stm = $this->dbConn->prepare(
            'INSERT IGNORE INTO mks_word_index (word, reverse, song, topics) VALUES (?, ?, ?, ?)'
        );
        $count = count($indexData);
        foreach (array_keys($indexData) as $k => $word) {
            $reverse = implode('', array_reverse(preg_split('<>u', $word)));
            foreach ($indexData[$word] as $song => $topics) {
                $stm->execute([$word, $reverse, $song, implode(',', array_keys($topics))]);
            }
            $this->logger->log('info', sprintf("%3.1f%% Finished writing “%s”", $k / $count * 100, $word));
        }
    }

    /**
     * splits a text into its words by splitting at all whitespaces and newlines; trims string before splitting
     * @param string $text text to split
     * @return array the separate words
     */
    public function splitText(string $text): array
    {
        return array_values(array_filter(preg_split('<\\PL+>u', $text), function($s) {
            return strlen($s) > 2;
        }));
    }
}
