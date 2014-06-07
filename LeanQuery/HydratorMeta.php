<?php

namespace LeanQuery;

use LeanMapper\Exception\InvalidArgumentException;

/**
 * @author Vojtěch Kohout
 */
class HydratorMeta
{

	/** @var array */
	private $tablesByPrefixes = array();

	/** @var array */
	private $primaryKeysByTables = array();

	/** @var array */
	private $relationships = array();


	/**
	 * @param string $prefix
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public function getTableByPrefix($prefix)
	{
		if (!array_key_exists($prefix, $this->tablesByPrefixes)) {
			throw new InvalidArgumentException;
		}
		return $this->tablesByPrefixes[$prefix];
	}

	/**
	 * @return array
	 */
	public function getTablesByPrefixes()
	{
		return $this->tablesByPrefixes;
	}

	/**
	 * @param string $prefix
	 * @param string $table
	 * @throws InvalidArgumentException
	 */
	public function addTablePrefix($prefix, $table)
	{
		if (array_key_exists($prefix, $this->tablesByPrefixes)) {
			throw new InvalidArgumentException;
		}
		$this->tablesByPrefixes[$prefix] = $table;
	}

	/**
	 * @param string $table
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public function getPrimaryKeyByTable($table)
	{
		if (!array_key_exists($table, $this->primaryKeysByTables)) {
			throw new InvalidArgumentException;
		}
		return $this->primaryKeysByTables[$table];
	}

	/**
	 * @param string $table
	 * @param string $primaryKey
	 * @throws InvalidArgumentException
	 */
	public function addPrimaryKey($table, $primaryKey)
	{
		if (array_key_exists($table, $this->primaryKeysByTables)) {
			throw new InvalidArgumentException;
		}
		$this->primaryKeysByTables[$table] = $primaryKey;
	}

	/**
	 * @param array $filter
	 * @return array
	 */
	public function getRelationships(array $filter = null)
	{
		return $filter === null ? $this->relationships : array_intersect_key($this->relationships, array_fill_keys($filter, true));
	}

	/**
	 * @param string $alias
	 * @param Relationship|string $relationship
	 * @throws InvalidArgumentException
	 */
	public function addRelationship($alias, $relationship)
	{
		if (array_key_exists($alias, $this->relationships)) {
			throw new InvalidArgumentException;
		}
		$this->relationships[$alias] = $relationship instanceof Relationship ? $relationship : Relationship::createFromString($relationship);
	}

}
