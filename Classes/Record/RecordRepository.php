<?php
namespace In2code\In2publishCore\Record;

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

use In2code\In2publishCore\Domain\Model\RecordInterface;
use In2code\In2publishCore\Domain\Service\TcaService as TcaProcessingService;
use In2code\In2publishCore\Record\Backend\CompositeBackend;
use In2code\In2publishCore\Record\Backend\Factory\BackendFactory;
use In2code\In2publishCore\Record\Query\RecordSelectQuery;
use In2code\In2publishCore\Service\Configuration\TcaService;
use In2code\In2publishCore\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class RecordRepository
 */
class RecordRepository
{
    /**
     * @var CompositeBackend
     */
    protected $backend = null;

    /**
     * @var RecordFactory
     */
    protected $recordFactory = null;

    /**
     * @var TcaService
     */
    protected $tcaService = null;

    /**
     * @var TcaProcessingService
     */
    protected $tcaProcessingService = null;

    /**
     * @var array
     */
    protected $excludedTables = [];

    /**
     * @var array
     */
    protected $recursionValues = [
        'maxPage' => 1,
        'maxContent' => 6,
        'maxOverall' => 8,
        'currentPage' => 0,
        'currentContent' => 0,
        'currentOverall' => 0,
    ];

    /**
     * @var bool
     */
    protected $resolvePageRelations = true;

    /**
     * @var array
     */
    protected $delayedQueries = [];

    /**
     * RecordRepository constructor.
     */
    public function __construct()
    {
        $this->backend = GeneralUtility::makeInstance(BackendFactory::class)->instantiateBackend();
        $this->recordFactory = GeneralUtility::makeInstance(RecordFactory::class);
        $this->tcaService = GeneralUtility::makeInstance(TcaService::class);
        $this->tcaProcessingService = GeneralUtility::makeInstance(TcaProcessingService::class);
        $this->excludedTables = ConfigurationUtility::getConfiguration('excludeRelatedTables');

        $factorySettings = ConfigurationUtility::getConfiguration('factory');
        $this->recursionValues['maxPage'] = $factorySettings['maximumPageRecursion'];
        $this->recursionValues['maxContent'] = $factorySettings['maximumContentRecursion'];
        $this->recursionValues['maxOverall'] = $factorySettings['maximumOverallRecursion'];

        $minRecursionDepth = $this->recursionValues['maxPage'] + $this->recursionValues['maxContent'];
        if ($this->recursionValues['maxOverall'] < $minRecursionDepth) {
            $this->recursionValues['maxOverall'] = $minRecursionDepth;
        }

        $this->resolvePageRelations = $factorySettings['resolvePageRelations'];
    }

    /**
     * @param string $table
     * @param string $identifier
     * @return RecordInterface[]
     */
    public function findRecords($table, $identifier)
    {
        $query = GeneralUtility::makeInstance(RecordSelectQuery::class, $table, ['uid' => $identifier]);
        $rootRecords = $this->executeSelectQuery($query);
        foreach ($rootRecords as $rootRecord) {
            $this->recurseRelations($rootRecord);
        }
        return $rootRecords;
    }

    /**
     * @param RecordInterface $record
     */
    protected function recurseRelations(RecordInterface $record)
    {
        if ($record->getTableName() === 'pages') {
            $this->findRelatedRecordsForPageRecord($record);
        } else {
            $this->findRelatedRecordsForContentRecord($record);
        }
    }

    /**
     * @param RecordInterface $record
     * @return RecordInterface
     */
    protected function findRelatedRecordsForPageRecord(RecordInterface $record)
    {
        $identifier = $record->getIdentifier();

        if ($identifier === 0) {
            $tableNamesToExclude =
                array_merge(
                    array_diff(
                        $this->tcaService->getAllTableNames(),
                        $this->tcaService->getAllTableNamesAllowedOnRootLevel()
                    ),
                    $this->excludedTables,
                    ['sys_file', 'sys_file_metadata']
                );
        } else {
            $tableNamesToExclude = $this->excludedTables;
        }
        // if page recursion depth reached
        if ($this->recursionValues['currentPage'] < $this->recursionValues['maxPage'] && $this->resolvePageRelations) {
            $this->recursionValues['currentPage']++;

            // TODO
            $commonRepository->enrichPageRecord($record, $tableNamesToExclude);
            $this->recursionValues['currentPage']--;
        } else {
            // get related records without table pages
            $tableNamesToExclude[] = 'pages';

            // TODO
            $identifier = $record->getIdentifier();
            $tables = $this->tcaService->getAllTableNames($tableNamesToExclude);
            foreach ($tables as $table) {
                $query = GeneralUtility::makeInstance(RecordSelectQuery::class, $table, ['pid' => $identifier]);
                $records = $this->executeSelectQuery($query);
                $record->addRelatedRecords($records);
            }
        }

        // The content recursion begins at 0 when the root record is a page
        $relatedRecordsDepth = $this->recursionValues['currentContent'];
        $this->recursionValues['currentContent'] = 0;
        $this->findRelatedRecordsForContentRecord($record);
        $this->recursionValues['currentContent'] = $relatedRecordsDepth;
    }

    /**
     * @param RecordSelectQuery $query
     * @return RecordInterface[]
     */
    protected function executeSelectQuery(RecordSelectQuery $query)
    {
        $result = $this->backend->select($query);
        $records = $this->recordFactory->makeInstance($query, $result);
        return $records;
    }

    /**
     * @param RecordInterface $record
     * @return RecordInterface
     */
    protected function findRelatedRecordsForContentRecord(RecordInterface $record)
    {
        $columns = $record->getColumnsTca();
        foreach ($columns as $column) {
            $processor = $this->tcaProcessingService->getProcessor($column['type']);
            $processor->createQuery($record, $column);
        }
        \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump(
            $columns,
            __FILE__ . '@' . __LINE__,
            20,
            false,
            true,
            false,
            array()
        );
        die;

        $this->registerDelayedQuery($record);
        if ($this->recursionValues['currentContent'] < $this->recursionValues['maxContent']) {
            $this->recursionValues['currentContent']++;
            $excludedTableNames = $this->excludedTables;
            if (false === $this->resolvePageRelations) {
                $excludedTableNames[] = 'pages';
            }
            // Register record query demand

            $this->recursionValues['currentContent']--;
        }
        return $record;
    }

    /**
     * @param RecordInterface $parent
     * @param RecordSelectQuery $query
     */
    protected function registerDelayedQuery(RecordInterface $parent, RecordSelectQuery $query)
    {
        $this->delayedQueries = [$parent, $query];
    }
}
