<?php
namespace In2code\In2publishCore\Database\Backend\Factory;

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

use In2code\In2publishCore\Database\Backend\Adapter\DbalAdapter;
use In2code\In2publishCore\Database\Backend\Adapter\DbcAdapter;
use In2code\In2publishCore\Database\Backend\CompositeBackend;
use In2code\In2publishCore\Service\Environment\ForeignEnvironmentService;
use In2code\In2publishCore\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class BackendFactory
 */
class BackendFactory
{
    /**
     * @return CompositeBackend
     */
    public function instantiateBackend()
    {
        if (class_exists(ConnectionPool::class)) {
            $localAdapter = $this->getLocalDbalAdapter();
            $foreignAdapter = $this->getForeignDbalAdapter();
        } elseif (class_exists(DatabaseConnection::class)) {
            $localAdapter = $this->getLocalDbcAdapter();
            $foreignAdapter = $this->getForeignDbcAdapter();
        } else {
            throw new \LogicException('No backend could be chosen for your installation', 1495128577);
        }

        return GeneralUtility::makeInstance(CompositeBackend::class, $localAdapter, $foreignAdapter);
    }

    /**
     * @return DbalAdapter
     */
    protected function getLocalDbalAdapter()
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
        return GeneralUtility::makeInstance(DbalAdapter::class, $connection);
    }

    /**
     * @return DbalAdapter
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected function getForeignDbalAdapter()
    {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['DB']['TableMapping']['in2publish_foreign'])) {
            $databaseConfig = ConfigurationUtility::getConfiguration('database.foreign');

            $GLOBALS['TYPO3_CONF_VARS']['DB']['TableMapping']['in2publish_foreign'] = 'in2publish_foreign';
            $GLOBALS['TYPO3_CONF_VARS']['DB']['in2publish_foreign'] = [
                'charset' => 'utf8',
                'dbname' => $databaseConfig['name'],
                'driver' => 'mysqli',
                'host' => $databaseConfig['hostname'],
                'password' => $databaseConfig['password'],
                'user' => $databaseConfig['username'],
                'initCommands' => $this->getInitCommands(),
            ];
        } elseif (!isset($GLOBALS['TYPO3_CONF_VARS']['DB']['in2publish_foreign']['initCommands'])) {
            $GLOBALS['TYPO3_CONF_VARS']['DB']['in2publish_foreign']['initCommands'] = $this->getInitCommands();
        }

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('in2publish_foreign');
        return GeneralUtility::makeInstance(DbalAdapter::class, $connection);
    }

    /**
     * @return DbcAdapter
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected function getLocalDbcAdapter()
    {
        return GeneralUtility::makeInstance(DbcAdapter::class, $GLOBALS['TYPO3_DB']);
    }

    /**
     * @return DbcAdapter
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected function getForeignDbcAdapter()
    {
        $configuration = ConfigurationUtility::getConfiguration('database.foreign');

        $connection = GeneralUtility::makeInstance(DatabaseConnection::class);
        $connection->setDatabaseHost($configuration['hostname']);
        $connection->setDatabaseName($configuration['name']);
        $connection->setDatabasePassword($configuration['password']);
        $connection->setDatabaseUsername($configuration['username']);
        $connection->setDatabasePort($configuration['port']);
        $connection->setInitializeCommandsAfterConnect($this->getInitCommands());
        $connection->connectDB();

        return GeneralUtility::makeInstance(DbcAdapter::class, $connection);
    }

    /**
     * @return array
     */
    protected function getInitCommands()
    {
        return GeneralUtility::makeInstance(ForeignEnvironmentService::class)->getDatabaseInitializationCommands();
    }
}
