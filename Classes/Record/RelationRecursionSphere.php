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
use In2code\In2publishCore\Record\Query\RecordSelectDemand;
use In2code\In2publishCore\Record\Query\RecordSelectQuery;

/**
 * Class RelationRecursionSphere
 */
class RelationRecursionSphere
{
    /**
     * @var RecordSelectDemand[]
     */
    protected $demand = [];

    /**
     * @param $records RecordInterface[]
     */
    public function recurse(array $records)
    {
        foreach ($records as $record) {
            $this->registerRelationDemand($record);
        }
        $this->aggregateDemand();
        $this->fulfilRelationDemand();
        $this->recurse($records);
    }

    /**
     * @param RecordInterface $record
     */
    protected function registerRelationDemand(RecordInterface $record)
    {
        $this->demand = $record;
    }

    /**
     *
     */
    protected function aggregateDemand()
    {
    }

    public function fulfilRelationDemand()
    {
        foreach ($this->demand as $demand) {
            $records = $this->getRecords($demand);
            $demand->fulfil($records);
        }
    }

    /**
     * @return RecordInterface[]
     */
    protected function getRecords(RecordSelectDemand $demand)
    {
        $query = new RecordSelectQuery($demand->getTable(), $demand->getWhere());
        $records = $recordFactory->makeInstances();
        return $records;
    }
}
