<?php declare(strict_types=1);

const ADDRESS = 'mksdb-mariadb';
const PORT = '3306';
const USERNAME = 'schlager';
const PASSWORD = 'zorofzoftumev';
const DATABASE = 'schlager';
const INDEX_NAME = "mks_word_index";

function clearIndex(PDO $dbConn){
    $dbConn->exec('TRUNCATE TABLE ' . INDEX_NAME . ';');
}

function listTables(PDO $dbConn): array {
    $JOIN_TABLE_PREFIX = 'mks_x_';
    $JOIN_TABLE_PREFIX_LEN = strlen($JOIN_TABLE_PREFIX);
    $EXCLUDE = ['20201217-oeaw-schlager-db'];

    $tables = [];
    $stm = $dbConn->query('SHOW TABLES;');
    foreach($stm->fetchAll(PDO::FETCH_NUM) as $row){
        $table = $row[0];

        // exclude index-table
        if($table === INDEX_NAME) continue;
        // exclude join-tables (-> if it starts with $JOIN_TABLE_PREFIX)
        if(substr($table, 0, $JOIN_TABLE_PREFIX_LEN) === $JOIN_TABLE_PREFIX) continue;
        // exclude others
        if(in_array($table, $EXCLUDE)) continue;

        array_push($tables, $table);
    }

    return $tables;
}

/**
 * creates index-data for each word in each column of the table;
 * index-format: [word => [song-id, ...], ...]
 * @param string $table
 * @param PDO $conn
 * @return false|mixed
 */
function indexTable(string $table, PDO $conn){
    // map functions to tables; function returns: [word => [song-id, ...], ...]
    $indexers = [
        'mks_city' => function() use ($conn) {
            $ret = [];
            $stm = $conn->query("SELECT strip_punctuation(city.name), song.id FROM (mks_city as city
                                            INNER JOIN mks_x_publication_place_song x on city.id = x.publication_place_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

            foreach($stm->fetchAll(PDO::FETCH_NUM) as $row){
                $text = $row[0];
                $song = $row[1];

                // split text and create mapping
                foreach(splitText($text) as $word){
                    mapPutOrAdd($ret, $word, $song);
                }
            }

            return $ret;
        },
        'mks_collection' => function() use ($conn) {
            $ret = [];
            $stm = $conn->query("SELECT strip_punctuation(coll.name), song.id FROM (mks_collection as coll
                                            INNER JOIN mks_x_collection_song x on coll.id = x.collection_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

            foreach($stm->fetchAll(PDO::FETCH_NUM) as $row){
                $text = $row[0];
                $song = $row[1];

                // split text and create mapping
                foreach(splitText($text) as $word){
                    mapPutOrAdd($ret, $word, $song);
                }
            }

            return $ret;
        },
        'mks_genre' => function() use ($conn) {
            $ret = [];
            $stm = $conn->query("SELECT strip_punctuation(genre.name), song.id FROM (mks_genre as genre
                                            INNER JOIN mks_x_genre_song x on genre.id = x.genre_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

            foreach($stm->fetchAll(PDO::FETCH_NUM) as $row){
                $text = $row[0];
                $song = $row[1];

                // split text and create mapping
                foreach(splitText($text) as $word){
                    mapPutOrAdd($ret, $word, $song);
                }
            }

            return $ret;
        },
        'mks_person' => function() use ($conn) {
            $ret = [];
            $selects = [
                "SELECT strip_punctuation(person.name), song.id FROM (mks_person as person
                                            INNER JOIN mks_x_composer_song x on person.id = x.composer_id
                                            INNER JOIN mks_song song on x.song_id = song.id)",
                "SELECT strip_punctuation(person.name), song.id FROM (mks_person as person
                                            INNER JOIN mks_x_cover_artist_song x on person.id = x.cover_artist_id
                                            INNER JOIN mks_song song on x.song_id = song.id)",
                "SELECT strip_punctuation(person.name), song.id FROM (mks_person as person
                                            INNER JOIN mks_x_performer_song x on person.id = x.performer_id
                                            INNER JOIN mks_song song on x.song_id = song.id)",
                "SELECT strip_punctuation(person.name), song.id FROM (mks_person as person
                                            INNER JOIN mks_x_writer_song x on person.id = x.writer_id
                                            INNER JOIN mks_song song on x.song_id = song.id)"
            ];

            foreach ($selects as $select) {
                foreach ($conn->query($select)->fetchAll(PDO::FETCH_NUM) as $row) {
                    $text = $row[0];
                    $song = $row[1];

                    // split text and create mapping
                    foreach (splitText($text) as $word) {
                        if($word === '') continue;
                        mapPutOrAdd($ret, $word, $song);
                    }
                }
            }

            return $ret;
        },
        'mks_publisher' => function() use ($conn) {
            $ret = [];
            $stm = $conn->query("SELECT strip_punctuation(publ.name), song.id FROM (mks_publisher as publ
                                            INNER JOIN mks_x_publisher_song x on publ.id = x.publisher_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

            foreach($stm->fetchAll(PDO::FETCH_NUM) as $row){
                $text = $row[0];
                $song = $row[1];

                // split text and create mapping
                foreach(splitText($text) as $word){
                    mapPutOrAdd($ret, $word, $song);
                }
            }

            return $ret;
        },
        'mks_source' => function() use ($conn) {
            $ret = [];
            $stm = $conn->query("SELECT strip_punctuation(source.name), song.id FROM (mks_source as source
                                            INNER JOIN mks_x_source_song x on source.id = x.source_id
                                            INNER JOIN mks_song song on x.song_id = song.id)");

            foreach($stm->fetchAll(PDO::FETCH_NUM) as $row){
                $text = $row[0];
                $song = $row[1];

                // split text and create mapping
                foreach(splitText($text) as $word){
                    mapPutOrAdd($ret, $word, $song);
                }
            }

            return $ret;
        },
        'mks_song' => function() use ($conn) {
            $ret = [];
            $stm = $conn->query("SELECT id,
                    strip_punctuation(name), strip_punctuation(label), strip_punctuation(origin),
                    strip_punctuation(dedication), strip_punctuation(review), strip_punctuation(addition),
                    strip_punctuation(copyright_year), strip_punctuation(copyright_remark), strip_punctuation(created_on),
                    strip_punctuation(publisher_series), strip_punctuation(publisher_number), strip_punctuation(record_number)
                    FROM mks_song");

            foreach($stm->fetchAll(PDO::FETCH_NUM) as $row){
                $song = $row[0];

                // split texts and create mapping ($i starts at 1 because id is at idx 0)
                for($i = 1; $i < count($row); $i += 1) {
                    $text = $row[$i];
                    if($text === null) continue;
                    foreach (splitText($text) as $word) {
                        mapPutOrAdd($ret, $word, $song);
                    }
                }
            }

            return $ret;
        }
    ];

    $indexer = $indexers[$table] ?? null;
    if($indexer != null){
        return $indexer();
    }else{
        myLog('warn', "no indexer found for $table");
        return false;
    }
}

function addIndex(array $indexData, PDO $conn){
    $stm = $conn->prepare('INSERT IGNORE INTO mks_word_index VALUES (?, ?)');
    foreach($indexData as $word => $songs) {
        foreach ($songs as $song) {
            if($word === '') continue;// skip empty results;
            $stm->execute([$word, $song]);
        }
    }
}

function createConnection(): PDO {
    return new PDO(
        sprintf("mysql:host=%s;port=%d;dbname=%s;charset=%s", ADDRESS, PORT, DATABASE, 'utf8'),
        USERNAME,
        PASSWORD
    );
}

/**
 * splits a text into its words by splitting at all whitespaces and newlines
 * @param string $text text to split
 * @return array the separate words
 */
function splitText(string $text): array {
    return preg_split('[\s+]', $text);
}

/**
 * adds the <code>value</code> to the array (makes no duplicates) for <code>key</code>
 * or creates a new array with the <code>value</code> if the map does not contain that key
 * @param array $map the map
 * @param string $key the key of the array
 * @param string $value the value to add
 */
function mapPutOrAdd(array &$map, string $key, string $value): void {
    if(array_key_exists($key, $map)){
        $array = $map[$key];
        if(!in_array($value, $array))
            array_push($array, $value);
    }else{
        $array = [$value];
    }
    $map[$key] = $array;
}

/**
 * merges the input-map into the destination map (array of existing keys will be merged; without duplicates);
 * both maps must be of the following format: [key => array[...]]
 * @param array $map the destination map
 * @param array $inp the input map
 */
function mergeMap(array &$map, array $inp) {
    foreach($inp as $key => $arr){
        if(array_key_exists($key, $map)){
            $destArr = $map[$key];
            foreach($arr as $value)
                if(!in_array($value, $destArr))
                    array_push($destArr, $value);
            $map[$key] = $destArr;
        }else{
            $map[$key] = $arr;
        }
    }
}
