<?php

namespace LeanQuery;

use ArrayObject;
use LeanMapper\Exception\InvalidArgumentException;
use LeanMapper\IMapper;
use LeanMapper\Reflection\EntityReflection;
use LeanMapper\Relationship\HasMany;
use LeanMapper\Relationship\HasOne;
use stdClass;

/**
 * @author Vojtěch Kohout
 */
class DomainQueryHelper
{

	/** @var IMapper */
	private $mapper;

	/** @var Aliases */
	private $aliases;

	/** @var HydratorMeta */
	private $hydratorMeta;

	/** @var stdClass */
	private $clauses;

	/** @var EntityReflection[] */
	private $reflections = array();

	/** @var int */
	private $indexer = 1;

	/** @var ArrayObject */
	private $relationshipTables;

	/** @var int */
	private $awaitedParameters;


	/**
	 * @param IMapper $mapper
	 * @param Aliases $aliases
	 * @param HydratorMeta $hydratorMeta
	 * @param stdClass $clauses
	 * @param ArrayObject $relationshipTables
	 */
	public function __construct(IMapper $mapper, Aliases $aliases, HydratorMeta $hydratorMeta, stdClass $clauses, ArrayObject $relationshipTables)
	{
		$this->mapper = $mapper;
		$this->aliases = $aliases;
		$this->hydratorMeta = $hydratorMeta;
		$this->clauses = $clauses;
		$this->relationshipTables = $relationshipTables;
	}

	/**
	 * @param string $entityClass
	 * @throws InvalidArgumentException
	 * @return EntityReflection
	 */
	public function getReflection($entityClass)
	{
		if (!is_subclass_of($entityClass, 'LeanMapper\Entity')) {
			throw new InvalidArgumentException;
		}
		if (!array_key_exists($entityClass, $this->reflections)) {
			$this->reflections[$entityClass] = $entityClass::getReflection($this->mapper);
		}
		return $this->reflections[$entityClass];
	}

	/**
	 * @param string $entityClass
	 * @param string $alias
	 */
	public function setFrom($entityClass, $alias)
	{
		$table = $this->mapper->getTable($entityClass);

		$this->aliases->addAlias($alias, $entityClass);
		$this->clauses->from = array(
			'entityClass' => $entityClass,
			'table' => $table,
			'alias' => $alias
		);

		$this->hydratorMeta->addTablePrefix($alias, $table);
		$this->hydratorMeta->addPrimaryKey($table, $this->mapper->getPrimaryKey($table));
	}

	/**
	 * @param string $definition
	 * @param string $alias
	 * @param string $type
	 * @throws InvalidArgumentException
	 */
	public function addJoinByType($definition, $alias, $type)
	{
		list($fromAlias, $viaProperty) = $this->parseDotNotation($definition);
		$entityReflection = $this->getReflection(
			$fromEntity = $this->aliases->getEntityClass($fromAlias)
		);
		$property = $entityReflection->getEntityProperty($viaProperty);
		if (!$property->hasRelationship()) {
			throw new InvalidArgumentException;
		}
		$relationship = $property->getRelationship();

		if ($relationship instanceof HasMany) {
			$this->clauses->join[] = array(
				'type' => $type,
				'joinParameters' => array(
					$relationshipTable = $relationship->getRelationshipTable(),
					$relTableAlias = $relationshipTable . $this->indexer,
				),
				'onParameters' => array(
					$fromAlias,
					$primaryKey = $this->mapper->getPrimaryKey(
						$fromTable = $this->mapper->getTable($fromEntity)
					),
					$relTableAlias,
					$columnReferencingSourceTable = $relationship->getColumnReferencingSourceTable(),
				),
			);
			$this->hydratorMeta->addTablePrefix($relTableAlias, $relationshipTable);
			$this->hydratorMeta->addPrimaryKey($relationshipTable, $relTablePrimaryKey = $this->mapper->getPrimaryKey($relationshipTable));
			$this->hydratorMeta->addRelationship(
				$relTableAlias,
				new Relationship($relTableAlias, $relationshipTable, $columnReferencingSourceTable, Relationship::DIRECTION_REFERENCING, $fromAlias, $fromTable, $primaryKey)
			);

			$this->clauses->join[] = array(
				'type' => $type,
				'joinParameters' => array(
					$targetTable = $relationship->getTargetTable(),
					$alias,
				),
				'onParameters' => array(
					$relTableAlias,
					$columnReferencingTargetTable = $relationship->getColumnReferencingTargetTable(),
					$alias,
					$primaryKey = $this->mapper->getPrimaryKey($targetTable),
				),
			);
			$this->aliases->addAlias($alias, $property->getType());

			$this->hydratorMeta->addTablePrefix($alias, $targetTable);
			$this->hydratorMeta->addPrimaryKey($targetTable, $primaryKey);
			$this->hydratorMeta->addRelationship(
				$alias,
				new Relationship($relTableAlias, $relationshipTable, $columnReferencingTargetTable, Relationship::DIRECTION_REFERENCED, $alias, $targetTable, $primaryKey)
			);

			$this->relationshipTables[$alias] = array(
				$relTableAlias, $relTablePrimaryKey, $relTableAlias . QueryHelper::PREFIX_SEPARATOR . $relTablePrimaryKey,
				$relTableAlias, $columnReferencingSourceTable, $relTableAlias . QueryHelper::PREFIX_SEPARATOR . $columnReferencingSourceTable,
				$relTableAlias, $columnReferencingTargetTable, $relTableAlias . QueryHelper::PREFIX_SEPARATOR . $columnReferencingTargetTable
			);

			$this->indexer++;
		} else {
			$this->clauses->join[] = array(
				'type' => $type,
				'joinParameters' => array(
					$targetTable = $relationship->getTargetTable(),
					$alias,
				),
				'onParameters' => $relationship instanceof HasOne ?
					array(
						$fromAlias,
						$relationshipColumn = $relationship->getColumnReferencingTargetTable(),
						$alias,
						$primaryKey = $this->mapper->getPrimaryKey($targetTable),
					) :
					array(
						$fromAlias,
						$primaryKey = $this->mapper->getPrimaryKey(
							$fromTable = $this->mapper->getTable($fromEntity)
						),
						$alias,
						$columnReferencingSourceTable = $relationship->getColumnReferencingSourceTable(),
					),
			);
			$this->aliases->addAlias($alias, $property->getType());

			$this->hydratorMeta->addTablePrefix($alias, $targetTable);
			if ($relationship instanceof HasOne) {
				$this->hydratorMeta->addPrimaryKey($targetTable, $primaryKey);
				$this->hydratorMeta->addRelationship(
					$alias,
					new Relationship($fromAlias, $this->mapper->getTable($fromEntity), $relationshipColumn, Relationship::DIRECTION_REFERENCED, $alias, $targetTable, $primaryKey)
				);
			} else {
				$this->hydratorMeta->addPrimaryKey($targetTable, $targetTablePrimaryKey = $this->mapper->getPrimaryKey($targetTable));
				$this->hydratorMeta->addRelationship(
					$alias,
					new Relationship($fromAlias, $fromTable, $columnReferencingSourceTable, Relationship::DIRECTION_REFERENCED, $fromAlias, $fromTable, $primaryKey)
				);
			}
		}
	}

	/**
	 * @param array $arguments
	 */
	public function addWhere(array $arguments)
	{
		$pattern = '/
			(\'(?:\'\'|[^\'])*\'|"(?:""|[^"])*")| # string
			%([a-zA-Z~][a-zA-Z0-9~]{0,5})| # modifier
			(\?) | # placeholder
			(' . DomainQuery::PATTERN_IDENTIFIER . ')\.(' . DomainQuery::PATTERN_IDENTIFIER . ') # alias.property
		/xs';

		$this->awaitedParameters = 0;
		foreach ($arguments as $argument) {
			if ($this->awaitedParameters > 0) {
				$this->clauses->where[] = $argument;
				$this->awaitedParameters--;
			} else {
				$this->clauses->where[] = preg_replace_callback($pattern, array($this, 'translateMatches'), $argument);
			}
		}
		$this->awaitedParameters = null;
	}

	/**
	 * @param string $property
	 * @param string $direction
	 * @throws InvalidArgumentException
	 */
	public function addOrderBy($property, $direction)
	{
		list($alias, $property) = $this->parseDotNotation($property);
		$entityReflection = $this->getReflection(
			$this->aliases->getEntityClass($alias)
		);
		$property = $entityReflection->getEntityProperty($property);

		if ($property->hasRelationship()) {
			throw new InvalidArgumentException;
		}
		$this->clauses->orderBy[] = array($alias, $property->getColumn(), $direction);
	}

	////////////////////
	////////////////////

	/**
	 * @param string $definition
	 * @return array
	 * @throws InvalidArgumentException
	 */
	private function parseDotNotation($definition)
	{
		$matches = array();
		if (!preg_match('#^\s*(' . DomainQuery::PATTERN_IDENTIFIER . ')\.(' . DomainQuery::PATTERN_IDENTIFIER . ')\s*$#', $definition, $matches)) {
			throw new InvalidArgumentException;
		}
		return array($matches[1], $matches[2]);
	}

	/**
	 * @param array $matches
	 * @return string
	 * @throws InvalidArgumentException
	 */
	private function translateMatches(array $matches)
	{
		if (!empty($matches[1])) { // quoted string
			return $matches[0];
		}
		if (!empty($matches[2]) or !empty($matches[3])) { // modifier or placeholder
			if ($matches[2] !== 'else' and $matches[2] !== 'end') {
				$this->awaitedParameters++;
			}
			return $matches[0];
		}

		$alias = $matches[4];
		$property = $matches[5];

		$entityClass = $this->aliases->getEntityClass($alias);
		$property = $this->getReflection($entityClass)->getEntityProperty($property);
		if ($property === null) {
			throw new InvalidArgumentException;
		}

		$column = $property->getColumn();
		if ($column === null) {
			throw new InvalidArgumentException;
		}

		return "[$alias.$column]";
	}

}
