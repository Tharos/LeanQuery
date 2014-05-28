<?php

namespace LeanQuery;

use LeanMapper\Connection;
use LeanMapper\IEntityFactory;
use LeanMapper\IMapper;

/**
 * @author Vojtěch Kohout
 */
class DomainQueryFactory
{

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
	}

	/**
	 * @return DomainQuery
	 */
	public function createQuery()
	{
		return new DomainQuery($this->enityFactory, $this->connection, $this->mapper, $this->hydrator, $this->queryHelper);
	}

}
