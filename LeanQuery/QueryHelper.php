<?php

namespace LeanQuery;

use LeanMapper\Exception\InvalidArgumentException;
use LeanMapper\IMapper;
use LeanMapper\Reflection\EntityReflection;
use LeanMapper\Relationship\HasOne;

/**
 * @author Vojtěch Kohout
 */
class QueryHelper
{

	const PREFIX_SEPARATOR = '_';

	/** @var IMapper */
	private $mapper;

	/** @var array */
	private $tables = array();


	/**
	 * @param IMapper $mapper
	 */
	public function __construct(IMapper $mapper)
	{
		$this->mapper = $mapper;
	}

	/**
	 * @param string $entityClass
	 * @param string $tableAlias
	 * @param string $prefix
	 * @return array
	 * @throws InvalidArgumentException
	 */
	public function formatSelect($entityClass, $tableAlias = null, $prefix = null)
	{
		$tableAlias !== null or $tableAlias = $this->getTable($entityClass);
		$prefix !== null or $prefix = $tableAlias;

		$fields = array();
		foreach ($this->getReflection($entityClass)->getEntityProperties() as $property) {
			if (($column = $property->getColumn()) === null) continue;
			$fields["$tableAlias.$column"] = $prefix . self::PREFIX_SEPARATOR . $column;
		}
		return $fields;
	}

	/**
	 * @param string $entityClass
	 * @param string $tableAlias
	 * @return array
	 */
	public function formatFrom($entityClass, $tableAlias = null)
	{
		$table = $this->getTable($entityClass);
		$tableAlias !== null or $tableAlias = $table;
		return array($table => $tableAlias);
	}

	/**
	 * @param string $entityClass
	 * @param string $tableAlias
	 * @return array
	 */
	public function formatJoin($entityClass, $tableAlias = null)
	{
		$table = $this->getTable($entityClass);
		$tableAlias !== null or $tableAlias = $table;
		return $table === $tableAlias ? $table : "[$table] [$tableAlias]";
	}

	/**
	 * @param string $entityClass
	 * @param string $property
	 * @param string $sourceTableAlias
	 * @param string $targetTableAlias
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public function formatOn($entityClass, $property, $sourceTableAlias = null, $targetTableAlias = null)
	{
		$property = $this->getReflection($entityClass)->getEntityProperty($property);
		if ($property === null) {
			throw new InvalidArgumentException("Entity $entityClass doesn't have property $property.");
		}
		if (!$property->hasRelationship() or !($relationship = $property->getRelationship()) instanceof HasOne) {
			throw new InvalidArgumentException('Only properties with HasOne relationship can be processed at this moment.'); // TODO: cancel restriction
		}
		$targetTable = $relationship->getTargetTable();
		$sourceTableAlias !== null or $sourceTableAlias = $this->getTable($entityClass);
		$targetTableAlias !== null or $targetTableAlias = $targetTable;
		$relationshipColumn = $relationship->getColumnReferencingTargetTable();
		$primaryKey = $this->mapper->getPrimaryKey($targetTable);

		return "[$sourceTableAlias.$relationshipColumn] = [$targetTableAlias.$primaryKey]";
	}

	/**
	 * @param string $entityClass
	 * @param string $property
	 * @param string $tableAlias
	 * @param string $prefix
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public function formatColumn($entityClass, $property, $tableAlias = null, $prefix = null)
	{
		if ($prefix === null) {
			$tableAlias !== null or $tableAlias = $this->getTable($entityClass);
			$prefix = $tableAlias;
		}
		$column = $this->getReflection($entityClass)->getEntityProperty($property)->getColumn();
		if ($column === null) {
			throw new InvalidArgumentException("Missing low-level column for property $property in entity $entityClass.");
		}
		return "[$prefix" . self::PREFIX_SEPARATOR . "$column]";
	}

	////////////////////
	////////////////////

	/**
	 * @param string $entityClass
	 * @return EntityReflection
	 * @throws InvalidArgumentException
	 */
	private function getReflection($entityClass)
	{
		if (!method_exists($entityClass, 'getReflection')) {
			throw new InvalidArgumentException("Class $entityClass doesn't have getReflection method.");
		}
		return $entityClass::getReflection($this->mapper);
	}

	/**
	 * @param string $entityClass
	 * @return string
	 */
	private function getTable($entityClass)
	{
		if (!isset($this->tables[$entityClass])) {
			$this->tables[$entityClass] = $this->mapper->getTable($entityClass);
		}
		return $this->tables[$entityClass];
	}

}
