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
use LeanMapper\Relationship\HasOne;
use LeanMapper\Row;

/**
 * @author Vojtěch Kohout
 */
class DomainQuery
{

	const PATTERN_IDENTIFIER = '[a-zA-Z0-9_\x7f-\xff]+'; // TODO: move to separate class in Lean Mapper

	const JOIN_TYPE_INNER = 'join';

	const JOIN_TYPE_LEFT = 'leftJoin';

	/** @var IEntityFactory */
	private $enityFactory;

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
	);

	/** @var array */
	private $hydratorMeta = array(
		'tablesByPrefixes' => array(),
		'primaryKeysByTables' => array(),
		'relationships' => array(),
	);

	/** @var EntityReflection[] */
	private $reflections = array();


	/**
	 * @param IEntityFactory $enityFactory
	 * @param Connection $connection
	 * @param IMapper $mapper
	 * @param Hydrator $hydrator
	 * @param QueryHelper $queryHelper
	 */
	public function __construct(IEntityFactory $enityFactory, Connection $connection, IMapper $mapper, Hydrator $hydrator, QueryHelper $queryHelper)
	{
		$this->enityFactory = $enityFactory;
		$this->connection = $connection;
		$this->mapper = $mapper;
		$this->hydrator = $hydrator;
		$this->queryHelper = $queryHelper;
		$this->aliases = new Aliases;
	}

	/**
	 * @param string $aliases
	 * @throws InvalidArgumentException
	 * @return self
	 */
	public function select($aliases)
	{
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
		if ($this->clauses['from'] !== null) {
			throw new InvalidMethodCallException;
		}
		$table = $this->mapper->getTable($entityClass);

		$this->aliases->addAlias($alias, $entityClass);
		$this->clauses['from'] = array($entityClass, $table, $alias);
		$this->hydratorMeta['tablesByPrefixes'][$alias] = $table;
		$this->hydratorMeta['primaryKeysByTables'][$table] = $this->mapper->getPrimaryKey($table);

		return $this;
	}

	/**
	 * @param string $definition
	 * @param string $alias
	 * @param string|null $fromAlias
	 * @return self
	 */
	public function join($definition, $alias, $fromAlias = null)
	{
		$this->joinByType($definition, $alias, self::JOIN_TYPE_INNER, $fromAlias);
		return $this;
	}

	/**
	 * @param string $definition
	 * @param string $alias
	 * @param string|null $fromAlias
	 * @return self
	 */
	public function leftJoin($definition, $alias, $fromAlias = null)
	{
		$this->joinByType($definition, $alias, self::JOIN_TYPE_LEFT, $fromAlias);
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
		foreach (array_keys($this->clauses['select']) as $alias) {
			$statement->select(
				$this->queryHelper->formatSelect(
					$this->getReflection($this->aliases->getEntityClass($alias)),
					$alias
				)
			);
		}
		$statement->from(array($this->clauses['from'][1] => $this->clauses['from'][2]));
		foreach ($this->clauses['join'] as $join) {
			call_user_func_array(
				array($statement, $join['type']),
				array_merge(array('%n AS %n'), $join['joinParameters'])
			);
			call_user_func_array(
				array($statement, 'on'),
				array_merge(array('%n.%n = %n.%n'), $join['onParameters'])
			);
		}

		return $statement;
	}

	/**
	 * @return Entity[]
	 */
	public function getEntities()
	{
		$relationships = array();
		foreach (array_keys($this->clauses['select']) as $alias) {
			if (array_key_exists($alias, $this->hydratorMeta['relationships'])) {
				$relationships[] = $this->hydratorMeta['relationships'][$alias];
			}
		}

		$results = $this->hydrator->buildResultsGraph(
			$this->createFluent()->fetchAll(),
			$this->hydratorMeta['tablesByPrefixes'],
			$this->hydratorMeta['primaryKeysByTables'],
			$relationships
		);

		$entities = array();
		$entityClass = $this->clauses['from'][0];
		foreach ($results[$this->clauses['from'][2]] as $key => $row) {
			$entities[] = $entity = $this->enityFactory->createEntity($entityClass, new Row($results[$this->clauses['from'][2]], $key));
			$entity->makeAlive($this->enityFactory, $this->connection, $this->mapper);
		}

		return $this->enityFactory->createCollection($entities);
	}

	////////////////////
	////////////////////

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
	 * @param string|null $fromAlias
	 * @throws InvalidArgumentException
	 */
	private function joinByType($definition, $alias, $type, $fromAlias)
	{
		$parsedJoin = $this->parseJoin($definition);
		$entityReflection = $this->getReflection(
			$fromEntity = $this->aliases->getEntityClass($parsedJoin[0])
		);
		$property = $entityReflection->getEntityProperty($parsedJoin[1]);
		if (!$property->hasRelationship()) {
			throw new InvalidArgumentException;
		}
		$relationship = $property->getRelationship();
		if ($fromAlias === null) {
			$fromAlias = $this->aliases->getAlias($fromEntity);
		}
		if ($relationship instanceof HasOne) {
			$this->clauses['join'][] = array(
				'type' => $type,
				'joinParameters' => array(
					$targetTable = $relationship->getTargetTable(),
					$alias,
				),
				'onParameters' => array(
					$fromAlias,
					$relationshipColumn = $relationship->getColumnReferencingTargetTable(),
					$alias,
					$primaryKey = $this->mapper->getPrimaryKey($targetTable),
				),
			);
		} else {
			throw new InvalidArgumentException; // TODO: implement another relationships
		}
		$this->aliases->addAlias($alias, $property->getType());
		$this->hydratorMeta['tablesByPrefixes'][$alias] = $targetTable;
		$this->hydratorMeta['primaryKeysByTables'][$targetTable] = $primaryKey;
		$this->hydratorMeta['relationships'][$alias] = "$fromAlias(" . $this->mapper->getTable($fromEntity) . ").$relationshipColumn => $alias($targetTable).$primaryKey";
	}

	/**
	 * @param string $definition
	 * @return array
	 * @throws InvalidArgumentException
	 */
	private function parseJoin($definition)
	{
		$matches = array();
		if (!preg_match('#^\s*(' . DomainQuery::PATTERN_IDENTIFIER . ')\.(' . DomainQuery::PATTERN_IDENTIFIER . ')\s*$#', $definition, $matches)) {
			throw new InvalidArgumentException;
		}
		return array($matches[1], $matches[2]);
	}

}