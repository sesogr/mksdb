<?php declare(strict_types=1);

/**
 * @property-read string $name
 */
interface NamedItem {
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
interface Song extends NamedItem
{
}

/**
 * @property-read int|null $position
 * @property-read string|null $annotation
 */
interface RecordRef {
}

/**
 * @property-read NamedItem $person
 */
interface PersonRef extends RecordRef {
}

/**
 * @property-read NamedItem $publisher
 */
interface PublisherRef extends RecordRef {
}

/**
 * @property-read NamedItem $city
 */
interface CityRef extends RecordRef {
}

/**
 * @property-read NamedItem $genre
 */
interface GenreRef extends RecordRef {
}

/**
 * @property-read NamedItem $collection
 */
interface CollectionRef extends RecordRef {
}

/**
 * @property-read NamedItem $source
 */
interface SourceRef extends RecordRef {
}

/**
 * @property-read int $id
 * @property-read string|null $title
 * @property-read string|null $composer
 * @property-read string|null $created_on
 * @property-read string|null $performer
 * @property-read string|null $origin
 * @property-read int|null $index
 */
interface SearchResult {
}