<?php
namespace In2code\In2publishCore\Record\Backend;

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

use In2code\In2publishCore\Record\Backend\Adapter\BackendAdapterInterface;
use In2code\In2publishCore\Record\Query\RecordSelectQuery;
use In2code\In2publishCore\Record\Query\RecordSelectResult;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CompositeBackend
 */
class CompositeBackend
{
    /**
     * @var BackendAdapterInterface
     */
    protected $localAdapter = null;

    /**
     * @var BackendAdapterInterface
     */
    protected $foreignAdapter = null;

    /**
     * CompositeBackend constructor.
     *
     * @param BackendAdapterInterface $localAdapter
     * @param BackendAdapterInterface $foreignAdapter
     */
    public function __construct(BackendAdapterInterface $localAdapter, BackendAdapterInterface $foreignAdapter)
    {
        $this->localAdapter = $localAdapter;
        $this->foreignAdapter = $foreignAdapter;
    }

    /**
     * @param RecordSelectQuery $recordSelectQuery
     * @return RecordSelectResult
     */
    public function select(RecordSelectQuery $recordSelectQuery)
    {
        $localProperties = $this->localAdapter->select($recordSelectQuery);
        $foreignProperties = $this->foreignAdapter->select($recordSelectQuery);
        $result = GeneralUtility::makeInstance(
            RecordSelectResult::class,
            $localProperties,
            $foreignProperties
        );
        return $result;
    }
}
