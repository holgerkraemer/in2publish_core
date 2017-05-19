<?php
namespace In2code\In2publishCore\Controller;

/***************************************************************
 * Copyright notice
 *
 * (c) 2016 in2code.de and the following authors:
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

use In2code\In2publishCore\Record\RecordRepository;
use In2code\In2publishCore\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Class OverviewController
 */
class OverviewController extends ActionController
{
    /**
     *
     */
    public function showAction()
    {
        $recordRepository = GeneralUtility::makeInstance(RecordRepository::class);
        $records = $recordRepository->findRecords('pages', BackendUtility::getPageIdentifier());
        if (count($records) === 1) {
            $this->view->assign('record', reset($records));
        } else {
            throw new \Exception('The selected record could not be found', 1495183921);
        }
    }
}