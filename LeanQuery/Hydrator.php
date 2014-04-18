<?php

namespace LeanQuery;

use DibiRow;
use LeanMapper\Connection;
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
	 * @param array $tablesByPrefixes
	 * @param array $primaryKeysByTables
	 * @param array $relationships
	 * @return array
	 */
	public function buildResultsGraph(array $data, array $tablesByPrefixes, array $primaryKeysByTables, array $relationships = array())
	{
		$results = array_fill_keys(array_keys($tablesByPrefixes), array());

		foreach ($data as $row) {
			$currentPrimaryKeys = array();
			foreach ($tablesByPrefixes as $prefix => $table) {
				$alias = $prefix . QueryHelper::PREFIX_SEPARATOR . $primaryKeysByTables[$table];
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
			$results[$prefix] = Result::createInstance($rows, $tablesByPrefixes[$prefix], $this->connection, $this->mapper);
		}

		return $results;
	}
	
}
