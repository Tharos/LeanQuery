<?php

namespace LeanQuery;

use DibiRow;
use LeanMapper\Connection;
use LeanMapper\Exception\InvalidArgumentException;
use LeanMapper\IMapper;
use LeanMapper\Result;

/**
 * @author Vojtěch Kohout
 */
class Hydrator
{

	/** @var Connection */
	private $connection;

	/** @var IMapper */
	private $mapper;


	/**
	 * @param Connection $connection
	 * @param IMapper $mapper
	 */
	public function __construct(Connection $connection, IMapper $mapper)
	{
		$this->connection = $connection;
		$this->mapper = $mapper;
	}

	/**
	 * @param DibiRow[] $data
	 * @param HydratorMeta $hydratorMeta
	 * @param array|null $relationshipsFilter
	 * @return array
	 */
	public function buildResultsGraph(array $data, HydratorMeta $hydratorMeta, array $relationshipsFilter = null)
	{
		$results = array_fill_keys(array_keys($hydratorMeta->getTablesByPrefixes()), array());

		foreach ($data as $row) {
			$currentPrimaryKeys = array();
			foreach ($hydratorMeta->getTablesByPrefixes() as $prefix => $table) {
				$alias = $prefix . QueryHelper::PREFIX_SEPARATOR . $hydratorMeta->getPrimaryKeyByTable($table);
				if (isset($row[$alias])) {
					$currentPrimaryKeys[$prefix] = $row[$alias];
				}
			}
			foreach ($row as $field => $value) {
				list($prefix, $field) = explode(QueryHelper::PREFIX_SEPARATOR, $field, 2);
				if (
					!isset($results[$prefix]) or
					!isset($currentPrimaryKeys[$prefix]) or
					isset($results[$prefix][$currentPrimaryKeys[$prefix]][$field])
				) {
					continue;
				}
				if (!isset($results[$prefix][$currentPrimaryKeys[$prefix]])) {
					$results[$prefix][$currentPrimaryKeys[$prefix]] = new DibiRow(array());
				}
				$results[$prefix][$currentPrimaryKeys[$prefix]][$field] = $value;
			}
		}
		foreach ($results as $prefix => $rows) {
			$results[$prefix] = Result::createInstance($rows, $hydratorMeta->getTableByPrefix($prefix), $this->connection, $this->mapper);
		}
		$relationships = $hydratorMeta->getRelationships($relationshipsFilter);
		if (!empty($relationships)) {
			$this->linkResults($results, $relationships);
		}
		return $results;
	}

	////////////////////
	////////////////////

	/**
	 * @param array $results
	 * @param Relationship[] $relationships
	 * @return array
	 * @throws InvalidArgumentException
	 */
	private function linkResults(array $results, array $relationships)
	{
		foreach ($relationships as $relationship) {
			if (!isset($results[$relationship->getSourcePrefix()]) or !isset($results[$relationship->getTargetPrefix()])) {
				throw new InvalidArgumentException('Missing relationship identified by given prefix. Deal with it :-P.');
			}
			if ($relationship->getDirection() === Relationship::DIRECTION_REFERENCED or $relationship->getDirection() === Relationship::DIRECTION_BOTH) {
				$results[$relationship->getSourcePrefix()]->setReferencedResult($results[$relationship->getTargetPrefix()], $relationship->getTargetTable(), $relationship->getRelationshipColumn());
			}
			if ($relationship->getDirection() === Relationship::DIRECTION_REFERENCING or $relationship->getDirection() === Relationship::DIRECTION_BOTH) {
				$results[$relationship->getTargetPrefix()]->setReferencingResult($results[$relationship->getSourcePrefix()], $relationship->getSourceTable(), $relationship->getRelationshipColumn());
			}
		}
		return $results;
	}

}
