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

	const DIRECTION_REFERENCED = '=>';

	const DIRECTION_REFERENCING = '<=';

	const DIRECTION_BOTH = '<=>';

	const RE_IDENTIFIER = '[a-zA-Z0-9_-]+'; // TODO: move to separate class in Lean Mapper

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
		if (!empty($relationships)) {
			$this->linkResults($results, $relationships);
		}
		return $results;
	}

	////////////////////
	////////////////////

	/**
	 * @param array $results
	 * @param array $relationships
	 * @return array
	 * @throws InvalidArgumentException
	 */
	private function linkResults(array $results, array $relationships)
	{
		foreach ($this->parseRelationships($relationships) as $relationship) {
			if (!isset($results[$relationship['sourcePrefix']]) or !isset($results[$relationship['targetPrefix']])) {
				throw new InvalidArgumentException('Missing relationship identified by given prefix. Deal with it :-P.');
			}
			if ($relationship['direction'] === self::DIRECTION_REFERENCED or $relationship['direction'] === self::DIRECTION_BOTH) {
				$results[$relationship['sourcePrefix']]->setReferencedResult($results[$relationship['targetPrefix']], $relationship['targetTable'], $relationship['relationshipColumn']);
			}
			if ($relationship['direction'] === self::DIRECTION_REFERENCING or $relationship['direction'] === self::DIRECTION_BOTH) {
				$results[$relationship['targetPrefix']]->setReferencingResult($results[$relationship['sourcePrefix']], $relationship['sourceTable'], $relationship['relationshipColumn']);
			}
		}
		return $results;
	}

	/**
	 * @param array $relationships
	 * @return array
	 * @throws InvalidArgumentException
	 */
	private function parseRelationships(array $relationships)
	{
		$result = array();
		foreach ($relationships as $definition) {
			$matches = array();
			// brackets hell matching <sourcePrefix>[(<sourceTable)].<relationshipColumn><direction><targetPrefix>[(<targetTable)].<primaryKeyColumn>
			if (!preg_match('#^\s*(' . self::RE_IDENTIFIER . ')(?:\((' . self::RE_IDENTIFIER . ')\))?\.(' . self::RE_IDENTIFIER . ')\s*(' . self::DIRECTION_REFERENCED . '|' . self::DIRECTION_REFERENCING . '|' . self::DIRECTION_BOTH . ')\s*(' . self::RE_IDENTIFIER . ')(?:\((' . self::RE_IDENTIFIER . ')\))?\.(' . self::RE_IDENTIFIER . ')\s*$#', $definition, $matches)) {
				throw new InvalidArgumentException("Invalid relationships definition given: $definition");
			}
			if ($matches[4] === self::DIRECTION_REFERENCED) {
				$direction = self::DIRECTION_REFERENCED;
			} elseif ($matches[4] === self::DIRECTION_REFERENCING) {
				$direction = self::DIRECTION_REFERENCING;
			} else {
				$direction = self::DIRECTION_BOTH;
			}
			$result[] = array(
				'sourcePrefix' => $matches[1],
				'sourceTable' => $matches[2] !== '' ? $matches[2] : $matches[1],
				'relationshipColumn' => $matches[3],
				'targetPrefix' => $matches[5],
				'targetTable' => $matches[6] !== '' ? $matches[6] : $matches[5],
				'primaryKeyColumn' => $matches[7],
				'direction' => $direction,
			);
		}
		return $result;
	}

}
