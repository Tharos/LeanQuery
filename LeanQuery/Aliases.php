<?php

namespace LeanQuery;

use LeanMapper\Exception\InvalidArgumentException;

/**
 * @author Vojtěch Kohout
 */
class Aliases
{

	/** @var array */
	private $aliases = array();

	/** @var array */
	private $index = array();


	/**
	 * @param string $alias
	 * @param string $entityClass
	 * @throws InvalidArgumentException
	 */
	public function addAlias($alias, $entityClass)
	{
		if (isset($this->aliases[$alias])) {
			throw new InvalidArgumentException("Alias $alias is already in use.");
		}
		$this->aliases[$alias] = $entityClass;
		if (!array_key_exists($entityClass, $this->index)) {
			$this->index[$entityClass] = $alias;
		}
	}

	/**
	 * @param string $alias
	 * @throws InvalidArgumentException
	 * @return string
	 */
	public function getEntityClass($alias)
	{
		if (!$this->hasAlias($alias)) {
			throw new InvalidArgumentException("Alias $alias was not found.");
		}
		return $this->aliases[$alias];
	}

	/**
	 * @param string $entityClass
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public function getAlias($entityClass)
	{
		if (!array_key_exists($entityClass, $this->index)) {
			throw new InvalidArgumentException("Alias for $entityClass was not found.");
		}
		return $this->index[$entityClass];
	}

	/**
	 * @param string $alias
	 * @return bool
	 */
	public function hasAlias($alias)
	{
		return array_key_exists($alias, $this->aliases);
	}

}
