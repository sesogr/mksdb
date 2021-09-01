<?php declare(strict_types=1);

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
