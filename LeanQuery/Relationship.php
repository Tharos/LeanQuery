<?php

namespace LeanQuery;

use LeanMapper\Exception\InvalidArgumentException;

/**
 * @author Vojtěch Kohout
 */
class Relationship
{

	const DIRECTION_REFERENCED = '=>';

	const DIRECTION_REFERENCING = '<=';

	const DIRECTION_BOTH = '<=>';

	const RE_IDENTIFIER = '[a-zA-Z0-9_-]+'; // TODO: move to separate class in Lean Mapper

	/** @var string */
	private $sourcePrefix;

	/** @var string */
	private $sourceTable;

	/** @var string */
	private $relationshipColumn;

	/** @var string */
	private $direction;

	/** @var string */
	private $targetPrefix;

	/** @var string */
	private $targetTable;

	/** @var string */
	private $primaryKeyColumn;


	/**
	 * @param string $sourcePrefix
	 * @param string $sourceTable
	 * @param string $relationshipColumn
	 * @param string $direction
	 * @param string $targetPrefix
	 * @param string $targetTable
	 * @param string $primaryKeyColumn
	 * @throws InvalidArgumentException
	 */
	public function __construct($sourcePrefix, $sourceTable, $relationshipColumn, $direction, $targetPrefix, $targetTable, $primaryKeyColumn)
	{
		if ($direction !== self::DIRECTION_REFERENCED and $direction !== self::DIRECTION_REFERENCING and $direction !== self::DIRECTION_BOTH) {
			throw new InvalidArgumentException("Invalid relationship direction given: $direction");
		}
		$this->sourcePrefix = $sourcePrefix;
		$this->sourceTable = $sourceTable;
		$this->relationshipColumn = $relationshipColumn;
		$this->direction = $direction;
		$this->targetPrefix = $targetPrefix;
		$this->targetTable = $targetTable;
		$this->primaryKeyColumn = $primaryKeyColumn;
	}

	/**
	 * @param string $definition
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public static function createFromString($definition)
	{
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
		return new self(
			$matches[1],
			$matches[2] !== '' ? $matches[2] : $matches[1],
			$matches[3],
			$direction,
			$matches[5],
			$matches[6] !== '' ? $matches[6] : $matches[5],
			$matches[7]
		);
	}

	/**
	 * @return string
	 */
	public function getSourcePrefix()
	{
		return $this->sourcePrefix;
	}

	/**
	 * @return string
	 */
	public function getSourceTable()
	{
		return $this->sourceTable;
	}

	/**
	 * @return string
	 */
	public function getRelationshipColumn()
	{
		return $this->relationshipColumn;
	}

	/**
	 * @return string
	 */
	public function getDirection()
	{
		return $this->direction;
	}

	/**
	 * @return string
	 */
	public function getTargetPrefix()
	{
		return $this->targetPrefix;
	}

	/**
	 * @return string
	 */
	public function getTargetTable()
	{
		return $this->targetTable;
	}

	/**
	 * @return string
	 */
	public function getPrimaryKeyColumn()
	{
		return $this->primaryKeyColumn;
	}

}
