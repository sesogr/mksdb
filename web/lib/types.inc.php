<?php declare(strict_types=1);

/**
 * @property-read string $id
 * @property-read string $song-name
 * @property-read string $composer
 * @property-read string $writer
 * @property-read string $song-cpr_y
 * @property-read string $song-cpr_remark
 * @property-read string $song-created
 * @property-read string $cover_artist
 * @property-read string $performer
 * @property-read string $song-label
 * @property-read string $publisher
 * @property-read string $city
 * @property-read string $song-pub_ser
 * @property-read string $song-pub_nr
 * @property-read string $song-rec_nr
 * @property-read string $song-origin
 * @property-read string $genre
 * @property-read string $song-dedication
 * @property-read string $collection
 * @property-read string $song-rev
 * @property-read string $song-addition
 * @property-read string $source
 */
class CsvTableSong {
}

/**
 * @property-read string $name
 */
class NamedItem {
}

/**
 * @property-read int $id
 * @property-read PersonRef[] $composers
 * @property-read PersonRef[] $writers
 * @property-read string $copyright_year
 * @property-read string $copyright_remark
 * @property-read string $created_on
 * @property-read PersonRef[] $coverArtists
 * @property-read PersonRef[] $performers
 * @property-read string $label
 * @property-read PublisherRef[] $publishers
 * @property-read CityRef[] $publicationPlaces
 * @property-read string $publisher_series
 * @property-read string $publisher_number
 * @property-read string $record_number
 * @property-read string $origin
 * @property-read GenreRef[] $genres
 * @property-read string $dedication
 * @property-read CollectionRef[] $collections
 * @property-read string $review
 * @property-read string $addition
 * @property-read SourceRef[] $sources
 * @property-read int $index_no
 */
class Song extends NamedItem
{
}

/**
 * @property-read int|null $position
 * @property-read string|null $annotation
 */
class RecordRef {
}

/**
 * @property-read NamedItem $person
 */
class PersonRef extends RecordRef {
}

/**
 * @property-read NamedItem $publisher
 */
class PublisherRef extends RecordRef {
}

/**
 * @property-read NamedItem $city
 */
class CityRef extends RecordRef {
}

/**
 * @property-read NamedItem $genre
 */
class GenreRef extends RecordRef {
}

/**
 * @property-read NamedItem $collection
 */
class CollectionRef extends RecordRef {
}

/**
 * @property-read NamedItem $source
 */
class SourceRef extends RecordRef {
}

/**
 * @property-read int $id
 * @property-read string|null $title
 * @property-read string|null $composer
 * @property-read string|null $writer
 * @property-read string|null $copyright_year
 * @property-read string|null $origin
 */
class SearchResult {
}

class SearchResultScore {

    public const KEYWORD_MULTIPLIER = 1;
    public const PHRASE_MULTIPLIER = 100;
    public const FULL_MATCH_MULTIPLIER = 10;
    public const EXCLUDED = -9999;
    public const INVALID = -1;

    public $keywordMatchCount;
    public $phraseMatchCount;
    public $fullKeywordsMatchCount;
    public $fullPhrasesMatchCount;

    public static function newEmpty(): SearchResultScore {
        $ret = new SearchResultScore();
        $ret->keywordMatchCount = self::INVALID;// at least this has to be set explicitly
        $ret->phraseMatchCount = 0;
        $ret->fullKeywordsMatchCount = 0;
        $ret->fullPhrasesMatchCount = 0;
        return $ret;
    }

    public static function newExcluded(): SearchResultScore {
        $ret = self::newEmpty();
        $ret->keywordMatchCount = self::EXCLUDED;
        $ret->phraseMatchCount = self::EXCLUDED;
        return $ret;
    }

    public function totalScore(): int {
        if($this->keywordMatchCount == self::INVALID
            or $this->phraseMatchCount == self::INVALID
            or $this->fullKeywordsMatchCount == self::INVALID)
            return self::INVALID;
        if($this->keywordMatchCount == self::EXCLUDED
            or $this->phraseMatchCount == self::EXCLUDED
            or $this->fullKeywordsMatchCount == self::EXCLUDED)
            return self::EXCLUDED;

        $score = 0;
        $score += $this->fullKeywordsMatchCount > 0
            ? $this->fullKeywordsMatchCount * self::KEYWORD_MULTIPLIER * self::FULL_MATCH_MULTIPLIER
            : $this->keywordMatchCount * self::KEYWORD_MULTIPLIER;
        $score += $this->fullPhrasesMatchCount > 0
            ? $this->fullPhrasesMatchCount * self::PHRASE_MULTIPLIER * self::FULL_MATCH_MULTIPLIER
            : $this->phraseMatchCount * self::PHRASE_MULTIPLIER;
        return $score;
    }

}
