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

use In2code\In2publishCore\Domain\Model\Record;
use In2code\In2publishCore\Domain\Model\RecordInterface;
use In2code\In2publishCore\Record\Query\RecordSelectQuery;
use In2code\In2publishCore\Record\Query\RecordSelectResult;
use In2code\In2publishCore\Service\Configuration\TcaService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class RecordFactory
 */
class RecordFactory
{
    /**
     * @var TcaService
     */
    protected $tcaService = null;

    /**
     * RecordFactory constructor.
     */
    public function __construct()
    {
        $this->tcaService = GeneralUtility::makeInstance(TcaService::class);
    }

    /**
     * @param RecordSelectQuery $query
     * @param RecordSelectResult $result
     * @param int $depth
     * @return RecordInterface[]
     */
    public function makeInstance(RecordSelectQuery $query, RecordSelectResult $result, $depth = 0)
    {
        $info = ['depth' => $depth];
        $table = $query->getTable();
        $tca = $this->tcaService->getConfigurationArrayForTable($table);
        $records = [];

        foreach ($result as $identifier => $resultSet) {
            /** @var RecordInterface $record */
            $records[$identifier] = GeneralUtility::makeInstance(
                Record::class,
                $table,
                $resultSet['local'],
                $resultSet['foreign'],
                $tca,
                $info
            );
        }

        return $records;
    }
}
