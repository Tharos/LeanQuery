<?php

namespace LeanQuery;

use LeanMapper\Exception\InvalidArgumentException;
use LeanMapper\Reflection\EntityReflection;
use LeanMapper\Reflection\Property;

/**
 * @author Vojtěch Kohout
 */
class QueryHelper
{

	const PREFIX_SEPARATOR = '_';


	/**
	 * @param EntityReflection $entityReflection
	 * @param string $tableAlias
	 * @param string $prefix
	 * @internal param string $entityClass
	 * @return array
	 */
	public function formatSelect(EntityReflection $entityReflection, $tableAlias, $prefix = null)
	{
		isset($prefix) or $prefix = $tableAlias;
		$fields = array();

		foreach ($entityReflection->getEntityProperties() as $property) {
			if (($column = $property->getColumn()) === null) continue;
			$fields["$tableAlias.$column"] = $prefix . self::PREFIX_SEPARATOR . $column;
		}

		return $fields;
	}

	/**
	 * @param Property $property
	 * @param string $tableAlias
	 * @param string $prefix
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public function formatColumn(Property $property, $tableAlias, $prefix = null)
	{
		isset($prefix) or $prefix = $tableAlias;

		if (($column = $property->getColumn()) === null) {
			throw new InvalidArgumentException("Missing low-level column for property $property.");
		}

		return $prefix . self::PREFIX_SEPARATOR . $column;
	}

}
