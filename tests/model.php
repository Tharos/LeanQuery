<?php

use LeanMapper\DefaultMapper;
use LeanMapper\Entity;

class Mapper extends DefaultMapper
{

	protected $defaultEntityNamespace = null;

}

/**
 * @property int $id
 */
abstract class IdentifiedEntity extends Entity
{
}

/**
 * @property Book[] $books m:belongsToMany
 *
 * @property string $name
 * @property string|null $web
 */
class Author extends IdentifiedEntity
{
}

/**
 * @property Author $author m:hasOne
 * @property Author|null $reviewer m:hasOne(reviewer_id)
 * @property Tag[] $tags m:hasMany
 *
 * @property string $name
 * @property string $pubdate
 * @property string|null $description
 * @property string|null $website
 * @property bool $available = true
 */
class Book extends IdentifiedEntity
{
}

/**
 * @property string $name
 */
class Tag extends IdentifiedEntity
{
}
