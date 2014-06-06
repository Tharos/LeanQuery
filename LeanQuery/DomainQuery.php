<?php

namespace LeanQuery;

use LeanMapper\Connection;
use LeanMapper\Entity;
use LeanMapper\Exception\InvalidArgumentException;
use LeanMapper\Exception\InvalidMethodCallException;
use LeanMapper\Exception\InvalidStateException;
use LeanMapper\Fluent;
use LeanMapper\IEntityFactory;
use LeanMapper\IMapper;
use LeanMapper\Reflection\EntityReflection;
use LeanMapper\Relationship\BelongsTo;
use LeanMapper\Relationship\HasMany;
use LeanMapper\Relationship\HasOne;
use LeanMapper\Result;
use LeanMapper\Row;

/**
 * @author Vojtěch Kohout
 */
class DomainQuery
{

	const PATTERN_IDENTIFIER = '[a-zA-Z0-9_\x7f-\xff]+'; // TODO: move to separate class in Lean Mapper

	const JOIN_TYPE_INNER = 'join';

	const JOIN_TYPE_LEFT = 'leftJoin';

	const ORDER_ASC = 'ASC';

	const ORDER_DESC = 'DESC';

	/** @var IEntityFactory */
	private $entityFactory;

	/** @var Connection */
	private $connection;

	/** @var IMapper */
	private $mapper;

	/** @var Hydrator */
	private $hydrator;

	/** @var QueryHelper */
	private $queryHelper;

	/** @var Aliases */
	private $aliases;

	/** @var array */
	private $clauses = array(
		'select' => array(),
		'from' => null,
		'join' => array(),
		'where' => array(),
		'orderBy' => array(),
	);

	/** @var HydratorMeta */
	private $hydratorMeta;

	/** @var EntityReflection[] */
	private $reflections = array();

	/** @var int */
	private $indexer = 1;

	/** @var array */
	private $relationshipTables = array();

	/** @var array */
	private $results;

	/** @var Entity[] */
	private $entities;


	/**
	 * @param IEntityFactory $entityFactory
	 * @param Connection $connection
	 * @param IMapper $mapper
	 * @param Hydrator $hydrator
	 * @param QueryHelper $queryHelper
	 */
	public function __construct(IEntityFactory $entityFactory, Connection $connection, IMapper $mapper, Hydrator $hydrator, QueryHelper $queryHelper)
	{
		$this->entityFactory = $entityFactory;
		$this->connection = $connection;
		$this->mapper = $mapper;
		$this->hydrator = $hydrator;
		$this->queryHelper = $queryHelper;

		$this->aliases = new Aliases;
		$this->hydratorMeta = new HydratorMeta;
	}

	/**
	 * @param string $aliases
	 * @throws InvalidArgumentException
	 * @return self
	 */
	public function select($aliases)
	{
		$this->cleanCache();

		if (!preg_match('#^\s*(' . self::PATTERN_IDENTIFIER . '\s*,\s*)*(' . self::PATTERN_IDENTIFIER . ')\s*$#', $aliases)) {
			throw new InvalidArgumentException;
		}
		$this->clauses['select'] += array_fill_keys(preg_split('#\s*,\s*#', trim($aliases)), true);

		return $this;
	}

	/**
	 * @param string $entityClass
	 * @param string $alias
	 * @throws InvalidMethodCallException
	 * @return self
	 */
	public function from($entityClass, $alias)
	{
		$this->cleanCache();

		if ($this->clauses['from'] !== null) {
			throw new InvalidMethodCallException;
		}
		$table = $this->mapper->getTable($entityClass);

		$this->aliases->addAlias($alias, $entityClass);
		$this->clauses['from'] = array($entityClass, $table, $alias);

		$this->hydratorMeta->addTablePrefix($alias, $table);
		$this->hydratorMeta->addPrimaryKey($table, $this->mapper->getPrimaryKey($table));

		return $this;
	}

	/**
	 * @param string $definition
	 * @param string $alias
	 * @return self
	 */
	public function join($definition, $alias)
	{
		$this->cleanCache();

		$this->joinByType($definition, $alias, self::JOIN_TYPE_INNER);
		return $this;
	}

	/**
	 * @param string $definition
	 * @param string $alias
	 * @return self
	 */
	public function leftJoin($definition, $alias)
	{
		$this->cleanCache();

		$this->joinByType($definition, $alias, self::JOIN_TYPE_LEFT);
		return $this;
	}

	/**
	 * @param string $property
	 * @param string $direction
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public function orderBy($property, $direction = self::ORDER_ASC)
    {
	    $this->cleanCache();

        list($alias, $property) = $this->parseDotNotation($property);
	    $entityReflection = $this->getReflection(
		    $this->aliases->getEntityClass($alias)
	    );
        $property = $entityReflection->getEntityProperty($property);

	    if ($property->hasRelationship()) {
		    throw new InvalidArgumentException;
	    }
        $this->clauses['orderBy'][] = array($alias, $property->getColumn(), $direction);

	    return $this;
    }

	/**
	 * @return Fluent
	 * @throws InvalidStateException
	 */
	public function createFluent()
	{
		if ($this->clauses['from'] === null or empty($this->clauses['select'])) {
			throw new InvalidStateException;
		}
		$statement = $this->connection->command();

		foreach (array_keys($this->clauses['select']) as $alias) { // SELECT
			$statement->select(
				$this->queryHelper->formatSelect(
					$this->getReflection($this->aliases->getEntityClass($alias)),
					$alias
				)
			);
			if (array_key_exists($alias, $this->relationshipTables)) {
				call_user_func_array(
					array($statement, 'select'),
					array_merge(array('%n.%n AS %n, %n.%n AS %n, %n.%n AS %n'), $this->relationshipTables[$alias])
				);
			}
		}

		$statement->from(array($this->clauses['from'][1] => $this->clauses['from'][2])); // FROM

		foreach ($this->clauses['join'] as $join) { // JOIN
			call_user_func_array(
				array($statement, $join['type']),
				array_merge(array('%n AS %n'), $join['joinParameters'])
			);
			call_user_func_array(
				array($statement, 'on'),
				array_merge(array('%n.%n = %n.%n'), $join['onParameters'])
			);
		}

		foreach ($this->clauses['orderBy'] as $orderBy) { // ORDER BY
			$statement->orderBy('%n.%n', $orderBy[0], $orderBy[1]);
			if ($orderBy[2] === self::ORDER_DESC) {
				$statement->desc();
			}
		}

		return $statement;
	}

	/**
	 * @param string $alias
	 * @throws InvalidArgumentException
	 * @return Result
	 */
	public function getResult($alias)
	{
		if ($this->results === NULL) {
			$relationshipFilter = array_keys($this->clauses['select']);
			foreach ($relationshipFilter as $filteredAlias) {
				if (array_key_exists($filteredAlias, $this->relationshipTables)) {
					$relationshipFilter[] = $this->relationshipTables[$filteredAlias][0];
				}
			}
			$this->results = $this->hydrator->buildResultsGraph($this->createFluent()->fetchAll(), $this->hydratorMeta, $relationshipFilter);
		}
		if (!array_key_exists($alias, $this->results)) {
			throw new InvalidArgumentException;
		}
		return $this->results[$alias];
	}

	/**
	 * @return Entity[]
	 */
	public function getEntities()
	{
		if ($this->entities === NULL) {
			$entities = array();
			$entityClass = $this->clauses['from'][0];
			$result = $this->getResult($this->clauses['from'][2]);
			foreach ($result as $key => $row) {
				$entities[] = $entity = $this->entityFactory->createEntity($entityClass, new Row($result, $key));
				$entity->makeAlive($this->entityFactory, $this->connection, $this->mapper);
			}
			$this->entities = $this->entityFactory->createCollection($entities);
		}
		return $this->entities;
	}

	////////////////////
	////////////////////

	private function cleanCache()
	{
		$this->results = $this->entities = NULL;
	}

	/**
	 * @param string $entityClass
	 * @throws InvalidArgumentException
	 * @return EntityReflection
	 */
	private function getReflection($entityClass)
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
	 * @param string $definition
	 * @param string $alias
	 * @param string $type
	 * @throws InvalidArgumentException
	 */
	private function joinByType($definition, $alias, $type)
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
			$this->clauses['join'][] = array(
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
			$this->hydratorMeta->addRelationship($relTableAlias, "$relTableAlias($relationshipTable).$columnReferencingSourceTable " . Hydrator::DIRECTION_REFERENCING . " $fromAlias($fromTable).$primaryKey");

			$this->clauses['join'][] = array(
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
			$this->hydratorMeta->addRelationship($alias, "$relTableAlias($relationshipTable).$columnReferencingTargetTable " . Hydrator::DIRECTION_REFERENCED . " $alias($targetTable).$primaryKey");

			$this->relationshipTables[$alias] = array(
				$relTableAlias, $relTablePrimaryKey, $relTableAlias . QueryHelper::PREFIX_SEPARATOR . $relTablePrimaryKey,
				$relTableAlias, $columnReferencingSourceTable, $relTableAlias . QueryHelper::PREFIX_SEPARATOR . $columnReferencingSourceTable,
				$relTableAlias, $columnReferencingTargetTable, $relTableAlias . QueryHelper::PREFIX_SEPARATOR . $columnReferencingTargetTable
			);

			$this->indexer++;
		} else {
			$this->clauses['join'][] = array(
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
				$this->hydratorMeta->addRelationship($alias, "$fromAlias(" . $this->mapper->getTable($fromEntity) . ").$relationshipColumn " . Hydrator::DIRECTION_REFERENCED . " $alias($targetTable).$primaryKey");
			} else {
				$this->hydratorMeta->addPrimaryKey($targetTable, $targetTablePrimaryKey = $this->mapper->getPrimaryKey($targetTable));
				$this->hydratorMeta->addRelationship($alias, "$fromAlias($fromTable).$columnReferencingSourceTable " . Hydrator::DIRECTION_REFERENCED . " $fromAlias($fromTable).$primaryKey");
			}
		}
	}

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

}
