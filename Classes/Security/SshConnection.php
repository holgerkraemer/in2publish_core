<?php
namespace In2code\In2publishCore\Security;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 in2code.de
 *  Alex Kellner <alexander.kellner@in2code.de>,
 *  Oliver Eglseder <oliver.eglseder@in2code.de>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use In2code\In2publishCore\Command\PublishTasksRunnerCommandController;
use In2code\In2publishCore\Command\RpcCommandController;
use In2code\In2publishCore\Command\StatusCommandController;
use In2code\In2publishCore\Command\TableCommandController;
use In2code\In2publishCore\In2publishCoreException;
use In2code\In2publishCore\Service\Context\ContextService;
use In2code\In2publishCore\Service\Environment\ForeignEnvironmentService;
use In2code\In2publishCore\Utility\ConfigurationUtility;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class SshConnection
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SshConnection
{
    const SSH2_WRAPPER = 'ssh2.sftp://';
    const TYPO3_CLI_EXTBASE_DISPATCHER = './typo3/cli_dispatch.phpsh extbase ';
    const CREATE_MASKS_CACHE_KEY = 'create_masks';

    /**
     * @var array
     */
    protected $supportedFingerprintMethods = [
        'SSH2_FINGERPRINT_MD5',
        'SSH2_FINGERPRINT_SHA1',
    ];

    /**
     * Indicates if ssh2_sftp_chmod is available.
     * This function was introduced in ssh2 extension version 0.12
     *
     * @var bool
     */
    protected $chmodEnabled = false;

    /**
     * @var LoggerInterface
     */
    protected $logger = null;

    /**
     * @param string $tableName
     * @return array
     * @throws \Exception
     * @deprecated This method has been deprecated in favour of the RemoteCommandDispatcher and will be deleted in 6.0.
     */
    public function backupRemoteTable($tableName)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->stopIfArgumentContainsCommand($tableName);
        $command =
            'cd ' . $this->foreignRootPath . ' && '
            . $this->pathToPhp . ' '
            . static::TYPO3_CLI_EXTBASE_DISPATCHER
            . sprintf(TableCommandController::BACKUP_COMMAND, $tableName);
        return $this->executeRemoteCommand($command);
    }

    /**
     * @return string
     * @throws \Exception
     * @deprecated This method has been deprecated in favour of the RemoteCommandDispatcher and will be deleted in 6.0.
     */
    public function getForeignIn2publishVersion()
    {
        GeneralUtility::logDeprecatedFunction();
        $command =
            'cd ' . $this->foreignRootPath . ' && '
            . $this->pathToPhp . ' '
            . static::TYPO3_CLI_EXTBASE_DISPATCHER . StatusCommandController::VERSION_COMMAND;
        $remoteVersion = $this->executeRemoteCommand($command);
        return reset($remoteVersion);
    }

    /**
     * @return array
     * @throws \Exception
     * @deprecated This method has been deprecated in favour of the RemoteCommandDispatcher and will be deleted in 6.0.
     */
    public function getForeignGlobalConfiguration()
    {
        GeneralUtility::logDeprecatedFunction();
        $command =
            'cd ' . $this->foreignRootPath . ' && '
            . $this->pathToPhp . ' '
            . static::TYPO3_CLI_EXTBASE_DISPATCHER . StatusCommandController::GLOBAL_CONFIGURATION;
        $configurationValues = $this->executeRemoteCommand($command);
        $configurationArray = [];
        foreach ($configurationValues as $line) {
            list($key, $value) = explode(': ', $line, 2);
            $configurationArray[$key] = $value;
        }
        return $configurationArray;
    }

    /**
     * @return string
     * @throws \Exception
     * @deprecated This method has been deprecated in favour of the RemoteCommandDispatcher and will be deleted in 6.0.
     */
    public function getForeignTypo3Version()
    {
        GeneralUtility::logDeprecatedFunction();
        $command =
            'cd ' . $this->foreignRootPath . ' && '
            . $this->pathToPhp . ' '
            . static::TYPO3_CLI_EXTBASE_DISPATCHER . StatusCommandController::TYPO3_VERSION;
        $remoteVersion = $this->executeRemoteCommand($command);
        return reset($remoteVersion);
    }

    /**
     * @return string
     * @throws \Exception
     * @deprecated This method has been deprecated in favour of the RemoteCommandDispatcher and will be deleted in 6.0.
     */
    public function getForeignConfigurationState()
    {
        GeneralUtility::logDeprecatedFunction();
        $command =
            'cd ' . $this->foreignRootPath . ' && '
            . $this->pathToPhp . ' '
            . static::TYPO3_CLI_EXTBASE_DISPATCHER . StatusCommandController::CONFIGURATION_RAW_COMMAND;
        $result = $this->executeRemoteCommand($command);
        return reset($result);
    }

    /**
     * @return array
     * @deprecated This method has been deprecated in favour of the RemoteCommandDispatcher and will be deleted in 6.0.
     */
    public function runForeignTasksCommandController()
    {
        GeneralUtility::logDeprecatedFunction();
        $command =
            'cd ' . $this->foreignRootPath . ' && '
            . $this->pathToPhp . ' '
            . static::TYPO3_CLI_EXTBASE_DISPATCHER . PublishTasksRunnerCommandController::RUN_TASKS_COMMAND;
        $returnValues = $this->executeRemoteCommand($command);
        $result = json_decode(reset($returnValues), true);
        if ($result === null) {
            return (array)$returnValues;
        }
        return $result;
    }

    /**
     * @return array
     * @throws \Exception
     * @internal USE ONLY FOR TESTING!
     * @deprecated This method has been deprecated in favour of the RemoteCommandDispatcher and will be deleted in 6.0.
     */
    public function callForeignCliDispatcherCallable()
    {
        GeneralUtility::logDeprecatedFunction();
        $command = 'cd ' . $this->foreignRootPath
                   . ' && ' . $this->pathToPhp . ' ' . static::TYPO3_CLI_EXTBASE_DISPATCHER;
        return $this->executeRawCommand($command);
    }

    /**
     * @return array
     * @throws \Exception
     * @internal USE ONLY FOR TESTING!
     * @deprecated This method has been deprecated in favour of the RemoteCommandDispatcher and will be deleted in 6.0.
     */
    public function testConnection()
    {
        GeneralUtility::logDeprecatedFunction();
        return $this->executeRawCommand($this->pathToPhp . ' -v');
    }

    /**
     * @return array
     * @internal USE ONLY FOR TESTING!
     * @deprecated This method has been deprecated in favour of the RemoteCommandDispatcher and will be deleted in 6.0.
     */
    public function validateForeignDocumentRoot()
    {
        GeneralUtility::logDeprecatedFunction();
        $command = 'cd ' . $this->foreignRootPath . ' && ls';
        return $this->executeRawCommand($command);
    }

    /**
     * @param int $uid Envelope uid
     * @return array|mixed
     * @deprecated This method has been deprecated in favour of the RemoteCommandDispatcher and will be deleted in 6.0.
     */
    public function executeRpc($uid)
    {
        GeneralUtility::logDeprecatedFunction();
        $command =
            'cd ' . $this->foreignRootPath . ' && '
            . $this->pathToPhp . ' '
            . static::TYPO3_CLI_EXTBASE_DISPATCHER . RpcCommandController::EXECUTE_COMMAND . ' '
            . (int)$uid;
        $returnValues = $this->executeRemoteCommand($command);
        $result = json_decode(reset($returnValues), true);
        if ($result === null) {
            return (array)$returnValues;
        }
        return $result;
    }

    /**
     * @param string $absoluteSourceFile
     * @return string
     */
    public function transferTemporaryFile($absoluteSourceFile)
    {
        if (is_file($absoluteSourceFile)) {
            $temporaryIdentifier = $this->foreignRootPath . 'typo3temp/' . uniqid('tx_in2publish_temp_');
            if ($this->writeRemoteFile($absoluteSourceFile, $temporaryIdentifier)) {
                return $temporaryIdentifier;
            } else {
                throw new \RuntimeException(
                    'Could not transfer ' . $absoluteSourceFile . ' to the foreign system',
                    1476272902
                );
            }
        } else {
            throw new \InvalidArgumentException(
                'The source file ' . $absoluteSourceFile . ' does not exist',
                1476272905
            );
        }
    }

    /***************************************************
     *                                                 *
     *                 INTERNAL METHODS                *
     *                                                 *
     ***************************************************/

    /**
     * @param string $argument
     * @return void
     * @throws \Exception
     */
    private function stopIfArgumentContainsCommand($argument)
    {
        if (preg_replace('~[^a-zA-Z0-9._\-\/]~', '', $argument) !== $argument) {
            throw new In2publishCoreException(
                'Argument "' . htmlspecialchars($argument) . '" contains not allowed characters. ' .
                'Note: Umlauts in file names or folder names are not allowed',
                1454944102
            );
        }
        if (escapeshellcmd($argument) !== $argument) {
            throw new In2publishCoreException(
                'The given argument "' . htmlspecialchars($argument) . '" may contain a malicious command',
                1440758497
            );
        }
    }

    /**
     * @param resource $handle
     * @return array
     */
    private function getHandleResponse($handle)
    {
        $stdOutStream = ssh2_fetch_stream($handle, SSH2_STREAM_STDIO);
        $stdErrStream = ssh2_fetch_stream($handle, SSH2_STREAM_STDERR);
        stream_set_blocking($stdOutStream, true);
        stream_set_blocking($stdErrStream, true);
        $stdOut = stream_get_contents($stdOutStream);
        $stdErr = stream_get_contents($stdErrStream);
        fclose($stdOutStream);
        fclose($stdErrStream);
        return array_filter(explode(PHP_EOL, $stdOut . $stdErr));
    }

    /**
     * Creates a folder on the foreign server and sets permissions
     *
     * @param string $folder
     * @return bool
     * @throws \Exception
     */
    private function createRemoteFolder($folder)
    {
        $this->connectIfNecessary();
        if (PHP_MAJOR_VERSION >= 7) {
            $command = 'test -d ' . escapeshellarg($folder) . ';echo -e "\n$?"';
            $result = $this->executeRemoteCommand($command);
            if (is_array($result) && array_key_exists(1, $result)) {
                if ('1' === $result[1]) {
                    $result = $this->createFolder($folder);
                }
            }
        } else {
            if (!($result = is_dir(static::SSH2_WRAPPER . ((int)$this->sftpSubSystem) . $folder))) {
                $result = $this->createFolder($folder);
            }
        }
        if ($result === false) {
            throw new In2publishCoreException('Could not create remote directory "' . $folder . '"', 1425477874);
        }
        $this->setRemoteFolderPermission($folder);
        return $result;
    }

    /**
     * @param string $folder
     * @return bool success?
     */
    private function createFolder($folder)
    {
        return ssh2_sftp_mkdir($this->sftpSubSystem, $folder, $this->folderMode, true);
    }

    /**
     * Apply permissions to a path
     *
     * @param string $folder
     * @return void
     * @throws \Exception
     */
    private function setRemoteFolderPermission($folder)
    {
        if ($this->chmodEnabled && PHP_MAJOR_VERSION < 7) {
            if (!ssh2_sftp_chmod($this->sftpSubSystem, $folder, $this->folderMode) && !$this->ignoreChmodFail) {
                throw new In2publishCoreException('Failed to set permissions for folder "' . $folder . '"', 1425482252);
            }
        } else {
            $this->setPermissionPerCommand($folder, $this->rawFolderMode);
        }
    }

    /**
     * Apply permissions to a path
     *
     * @param string $file
     * @return void
     * @throws \Exception
     */
    private function setRemoteFilePermission($file)
    {
        if ($this->chmodEnabled && PHP_MAJOR_VERSION < 7) {
            if (!ssh2_sftp_chmod($this->sftpSubSystem, $file, $this->fileMode) && !$this->ignoreChmodFail) {
                throw new In2publishCoreException('Failed to set permissions for file "' . $file . '"', 1440748670);
            }
        } else {
            $this->setPermissionPerCommand($file, $this->rawFileMode);
        }
    }

    /**
     * @param $fileOrFolder
     * @param $permission
     * @return void
     */
    private function setPermissionPerCommand($fileOrFolder, $permission)
    {
        $this->executeRemoteCommand('chmod ' . $permission . ' "' . $fileOrFolder . '"');
    }

    /**
     * @param string $command
     * @return array of result lines
     * @throws \Exception
     */
    private function executeRemoteCommand($command)
    {
        $this->connectIfNecessary();
        $commandParts = explode('&&', $command);
        $lastCommandPart = array_pop($commandParts);
        $lastCommandPart = ContextService::ENV_VAR_NAME . '=' . ContextService::FOREIGN . ' ' . $lastCommandPart;
        $commandParts[] = $lastCommandPart;
        $command = implode(' && ', $commandParts);
        $handle = ssh2_exec($this->session, $command);
        $result = $this->getHandleResponse($handle);
        if (!fclose($handle)) {
            throw new In2publishCoreException(
                'Command:"' . htmlspecialchars($command) . '" returned an error! '
                . 'Message: "' . htmlspecialchars(implode('', $result)) . '"',
                1425408542
            );
        }
        unset($handle);
        return $result;
    }

    /**
     * @internal DO NOT USE THIS METHOD FOR PRODUCTIVE TASKS!
     *
     * @param string $command
     * @return array
     */
    private function executeRawCommand($command)
    {
        if (!is_resource($this->session)) {
            $this->connect(false);
        }

        $command .= '; echo -en CODE"\n$?"CODE;';

        $handle = ssh2_exec($this->session, $command);

        if (false === $handle) {
            return [
                'stdOut' => '',
                'stdErr' => 'EXECUTION FAILED',
                'code' => 1470246119,
            ];
        }

        $stdOutStream = ssh2_fetch_stream($handle, SSH2_STREAM_STDIO);
        $stdErrStream = ssh2_fetch_stream($handle, SSH2_STREAM_STDERR);
        stream_set_blocking($stdOutStream, true);
        stream_set_blocking($stdErrStream, true);
        $stdOut = stream_get_contents($stdOutStream);
        $stdErr = stream_get_contents($stdErrStream);
        fclose($stdOutStream);
        fclose($stdErrStream);

        fclose($handle);

        if (1 === preg_match('~(?P<complete>CODE\s?(?P<code>\d+)CODE)~', $stdOut, $matches)) {
            $stdOut = str_replace($matches['complete'], '', $stdOut);
            $code = $matches['code'];
        } else {
            $code = 1;
        }

        $this->disconnect();

        return [
            'stdOut' => $stdOut,
            'stdErr' => $stdErr,
            'code' => $code,
        ];
    }

    /**
     * Creates a file on the given absolute foreign location and copies any
     * content from the absolute local file to the foreign.
     * Sets permissions after the file has been created
     *
     * @param string $localFileLocation
     * @param string $foreignFileLocation
     * @return bool
     * @throws \Exception
     */
    private function writeRemoteFile($localFileLocation, $foreignFileLocation)
    {
        $this->connectIfNecessary();
        $this->createRemoteFolder(dirname($foreignFileLocation));
        $bytesToWrite = filesize($localFileLocation);
        $localFileStream = fopen($localFileLocation, 'r');
        if (!is_resource($localFileStream)) {
            throw new In2publishCoreException(
                'Could not create a stream for local file "' . $localFileLocation . '"',
                1425466802
            );
        }
        try {
            /**
             * typecast resource for PHP 7.0
             *
             * @see http://paul-m-jones.com/archives/6439
             */
            $foreignFileStream = fopen(static::SSH2_WRAPPER . (int)$this->sftpSubSystem . $foreignFileLocation, 'w');
        } catch (\Exception $e) {
            $exceptionMessage = $e->getMessage();
            if (strpos($exceptionMessage, 'failed to open stream: operation failed')) {
                $this->logger->alert(
                    'PHP SSH2 fopen stream wrapper failure.',
                    [
                        'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION,
                        'file' => $foreignFileLocation,
                    ]
                );
                throw new In2publishCoreException(
                    'Could not write remote file "' . $foreignFileLocation . '" because PHP failed to open a stream.'
                    . ' This might be a problem of your PHP version or php-ssh2 extension',
                    1487588970
                );
            } elseif (false !== strpos($exceptionMessage, 'server configuration by allow_url_fopen=0')) {
                $this->logger->alert('PHP setting allow_url_fopen is false.');
                throw new In2publishCoreException(
                    'PHP setting allow_url_fopen is disabled: ' . $exceptionMessage,
                    1496999878
                );
            } else {
                $this->logger->alert('Caught unknown error while fopen(ssh2.sftp://)', ['exception' => $e]);
                throw new In2publishCoreException(
                    'An unknown error occurred or you have insufficient write permission: ' . $exceptionMessage,
                    1425467980
                );
            }
        }

        if (!is_resource($localFileStream)) {
            throw new In2publishCoreException(
                'Could not create a stream for foreign file "' . $foreignFileLocation . '"',
                1425466826
            );
        }

        $bytesWritten = stream_copy_to_stream($localFileStream, $foreignFileStream);

        if ($bytesToWrite !== $bytesWritten) {
            throw new In2publishCoreException('Could not write remote file "' . $foreignFileLocation . '"', 1425467808);
        }

        $this->setRemoteFilePermission($foreignFileLocation);
        return true;
    }

    /***************************************************
     *                                                 *
     *                INITIALIZING STUFF               *
     *                                                 *
     ***************************************************/

    /**
     * @var resource
     */
    private $session = null;

    /**
     * @var resource
     */
    private $sftpSubSystem = null;

    /**
     * @var string
     */
    private $host = '';

    /**
     * @var string
     */
    private $port = '';

    /**
     * @var string
     */
    private $username = '';

    /**
     * @var string
     */
    private $privateKeyFileAndPathName = '';

    /**
     * @var string
     */
    private $publicKeyFileAndPathName = '';

    /**
     * @var string
     */
    private $privateKeyPassphrase = null;

    /**
     * @var string
     */
    private $foreignKeyFingerprint = '';

    /**
     * @var string
     */
    private $foreignKeyFingerprintHashingMethod = '';

    /**
     * @var int
     */
    private $folderMode = 0000;

    /**
     * @var int
     */
    private $fileMode = 0000;

    /**
     * @var int
     */
    private $rawFolderMode = 0000;

    /**
     * @var int
     */
    private $rawFileMode = 0000;

    /**
     * @var int
     */
    private $pathToPhp = '/usr/bin/env php';

    /**
     * @var string
     */
    private $foreignRootPath = '/';

    /**
     * @var bool
     */
    private $ignoreChmodFail = false;

    /**
     * Validates the configuration for the SSH connection
     *
     * @param $configuration
     * @return void
     * @throws \Exception
     */
    private function validateConfiguration(array &$configuration)
    {
        if (empty($configuration['host'])) {
            throw new In2publishCoreException('SSH Connection: Option host is empty', 1425400317);
        }
        if (empty($configuration['port'])) {
            $configuration['port'] = 22;
        }
        if (empty($configuration['username'])) {
            throw new In2publishCoreException('SSH Connection: Option username is empty', 1425400379);
        }
        foreach (['privateKeyFileAndPathName', 'publicKeyFileAndPathName'] as $requiredFileKey) {
            if (empty($configuration[$requiredFileKey])) {
                throw new In2publishCoreException(
                    'SSH Connection: Option ' . $requiredFileKey . ' is empty',
                    1425400434
                );
            } elseif (!file_exists($configuration[$requiredFileKey])) {
                throw new In2publishCoreException(
                    'SSH Connection: The File defined in ' . $requiredFileKey . ' does not exist',
                    1425400440
                );
            } elseif (!is_readable($configuration[$requiredFileKey])) {
                throw new In2publishCoreException(
                    'SSH Connection: The File defined in ' . $requiredFileKey . ' is not readable',
                    1425400444
                );
            }
        }
        if ($configuration['privateKeyPassphrase'] === '') {
            $configuration['privateKeyPassphrase'] = $this->privateKeyPassphrase;
        }
        if (empty($configuration['foreignKeyFingerprint'])) {
            throw new In2publishCoreException('SSH Connection: Option foreignKeyFingerprint is empty', 1425400689);
        } elseif (strpos($configuration['foreignKeyFingerprint'], ':') !== false) {
            $configuration['foreignKeyFingerprint'] = strtoupper(
                str_replace(':', '', $configuration['foreignKeyFingerprint'])
            );
        }
        if (empty($configuration['foreignRootPath'])) {
            throw new In2publishCoreException('SSH Connection: Option foreignRootPath is empty', 1498495128);
        } else {
            $configuration['foreignRootPath'] = rtrim($configuration['foreignRootPath'], '/') . '/';
        }
        if (empty($configuration['foreignKeyFingerprintHashingMethod'])) {
            $configuration['foreignKeyFingerprintHashingMethod'] = SSH2_FINGERPRINT_MD5 | SSH2_FINGERPRINT_HEX;
        } else {
            if (!in_array($configuration['foreignKeyFingerprintHashingMethod'], $this->supportedFingerprintMethods)) {
                throw new In2publishCoreException(
                    'SSH Connection: The first part of foreignKeyFingerprintHashingMethod '
                    . 'must either be "SSH2_FINGERPRINT_MD5" or "SSH2_FINGERPRINT_SHA1"',
                    1498495123
                );
            }
            $configuration['foreignKeyFingerprintHashingMethod'] =
                constant($configuration['foreignKeyFingerprintHashingMethod']) | SSH2_FINGERPRINT_HEX;
        }
        if (!empty($configuration['pathToPhp'])) {
            if (strpos($configuration['pathToPhp'], '/') !== 0) {
                throw new In2publishCoreException(
                    'SSH Connection: The first part of pathToPhp must begin with a slash',
                    1498495117
                );
            }
        } else {
            $configuration['pathToPhp'] = '/usr/bin/env php';
        }
        if (empty($configuration['ignoreChmodFail']) || $configuration['ignoreChmodFail'] !== true) {
            $configuration['ignoreChmodFail'] = false;
        }
    }

    /**
     * sets all required internal member variables
     *
     * @param array $configuration
     * @return void
     */
    private function applyConfiguration(array $configuration)
    {
        $this->host = $configuration['host'];
        $this->port = $configuration['port'];
        $this->username = $configuration['username'];
        $this->privateKeyFileAndPathName = $configuration['privateKeyFileAndPathName'];
        $this->publicKeyFileAndPathName = $configuration['publicKeyFileAndPathName'];
        $this->privateKeyPassphrase = $configuration['privateKeyPassphrase'];
        $this->foreignKeyFingerprint = $configuration['foreignKeyFingerprint'];
        $this->foreignKeyFingerprintHashingMethod = $configuration['foreignKeyFingerprintHashingMethod'];
        $this->foreignRootPath = $configuration['foreignRootPath'];
        $this->pathToPhp = $configuration['pathToPhp'];
        $this->ignoreChmodFail = $configuration['ignoreChmodFail'];
    }

    /**
     * establishes a SSH connection to the remote host
     * This connection will stay alive as long as this object stays in RAM
     *
     * @param bool $setCreateMasks
     * @return void
     * @throws \Exception
     */
    private function connect($setCreateMasks = true)
    {
        $this->session = @ssh2_connect($this->host, $this->port);
        if (!is_resource($this->session)) {
            throw new In2publishCoreException(
                'Could not establish a SSH connection to "' . $this->host . ':' . $this->port . '"',
                1425401287
            );
        }
        $keyFingerPrint = ssh2_fingerprint($this->session, $this->foreignKeyFingerprintHashingMethod);
        if ($keyFingerPrint !== $this->foreignKeyFingerprint) {
            if (ConfigurationUtility::getConfiguration('debug.showForeignKeyFingerprint') === true) {
                throw new In2publishCoreException(
                    'Identification of foreign host failed, SSH Key Fingerprint mismatch. Foreign Key Fingerprint: "'
                    . $keyFingerPrint . '"; Configured Key Fingerprint: "' . $this->foreignKeyFingerprint . '"',
                    1426868565
                );
            } else {
                throw new In2publishCoreException(
                    'Identification of foreign host failed, SSH Key Fingerprint mismatch!!!',
                    1425401452
                );
            }
        }
        if (!@ssh2_auth_pubkey_file(
            $this->session,
            $this->username,
            $this->publicKeyFileAndPathName,
            $this->privateKeyFileAndPathName,
            $this->privateKeyPassphrase
        )
        ) {
            throw new In2publishCoreException(
                'Could not authenticate the SSH connection for "'
                . $this->username . '@' . $this->host . ':' . $this->port . '" with the given ssh key pair',
                1425401293
            );
        }
        $this->sftpSubSystem = ssh2_sftp($this->session);
        if (!is_resource($this->sftpSubSystem)) {
            throw new In2publishCoreException('Could not initialize the SFTP Subsystem', 1425466318);
        }
        if ($setCreateMasks) {
            $this->setCreateMasks();
        }
    }

    /**
     * Do not offer to not set create masks, because if this is
     * called in first place all other requests, even if they
     * need it, will not have the createMasks set and fail.
     *
     * @throws \Exception
     */
    private function connectIfNecessary()
    {
        if (!is_resource($this->session)) {
            $this->connect(true);
        }
    }

    /**
     * @throws \Exception
     */
    private function setCreateMasks()
    {
        $createMasks = GeneralUtility::makeInstance(ForeignEnvironmentService::class)->getCreateMasks();

        $this->rawFileMode = $createMasks['file'];
        $this->rawFolderMode = $createMasks['folder'];
        $this->fileMode = octdec($createMasks['file']);
        $this->folderMode = octdec($createMasks['folder']);
    }

    /**
     * @return void
     */
    private function disconnect()
    {
        unset($this->session);
    }

    /***************************************************
     *                                                 *
     *                 SINGLETON STUFF                 *
     *                                                 *
     ***************************************************/

    /**
     * This classes instance
     *
     * @var SshConnection
     */
    private static $instance = null;

    /**
     * Prohibit the use of "new" to create an instance.
     * If you need an instance of this class you have to use
     * SshConnection::makeInstance()
     *
     * @throws \Exception
     */
    final protected function __construct()
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(static::class);
        if (function_exists('ssh2_sftp_chmod')) {
            $this->chmodEnabled = true;
        }

        $configuration = ConfigurationUtility::getConfiguration('sshConnection');
        if (!empty($configuration)) {
            $this->validateConfiguration($configuration);
            $this->applyConfiguration($configuration);
        } else {
            throw new In2publishCoreException(
                'The configuration for SshConnection is invalid: "'
                . LocalizationUtility::translate(ConfigurationUtility::getLoadingState(), 'in2publish_core') . '"',
                1428492639
            );
        }
    }

    /**
     * creates an instance of this class and saves
     * and returns the reference to the created object.
     * if already instantiated only the reference is returned
     *
     * @return SshConnection
     */
    public static function makeInstance()
    {
        if (static::$instance === null) {
            static::$instance = new self;
        }
        return static::$instance;
    }

    /**
     * Prohibit cloning
     *
     * @return void
     */
    final protected function __clone()
    {
    }

    /**
     */
    final public function __destruct()
    {
        $this->disconnect();
    }
}
