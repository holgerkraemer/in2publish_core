<?php
namespace In2code\In2publishCore\Record\Backend\Adapter;

/***************************************************************
 * Copyright notice
 *
 * (c) 2017 in2code.de and the following authors:
 * Oliver Eglseder <oliver.eglseder@in2code.de>
 *
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use In2code\In2publishCore\Record\Query\BulkSelectQuery;
use In2code\In2publishCore\Record\Query\RecordSelectQuery;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Adapter for Doctrine DBAL
 */
class DbalAdapter implements BackendAdapterInterface
{
    /**
     * @var Connection
     */
    protected $connection = null;

    /**
     * @var LoggerInterface
     */
    protected $logger = null;

    /**
     * DbalAdapter constructor.
     *
     * @param Connection $connection
     */
    public function __construct($connection)
    {
        $this->connection = $connection;
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * @param RecordSelectQuery $recordSelectQuery
     * @return array
     */
    public function select(RecordSelectQuery $recordSelectQuery)
    {
        $statement = $this->connection->select(
            ['*'],
            $recordSelectQuery->getTable(),
            $recordSelectQuery->getIdentifiers()
        );
        $result = $this->fetchResults($statement);
        return $result;
    }

    public function selectBulk(BulkSelectQuery $query)
    {
        $queryBuilder = $this
            ->connection
            ->createQueryBuilder()
            ->select(['*'])
            ->from($query->getTable());

        $conditions = $query->getIdentifiers();
        foreach ($conditions as $field => $value) {
            $queryBuilder->orWhere($queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value)));
        }

        $statement = $queryBuilder->execute();
        $result = $this->fetchResults($statement);
        return $result;
    }

    /**
     * @param $statement
     * @return array
     */
    protected function fetchResults($statement): array
    {
        if (0 === $statement->rowCount()) {
            return [];
        }
        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
        if (false === $result) {
            return [];
        }
        return $result;
    }
}
