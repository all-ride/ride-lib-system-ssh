<?php

namespace ride\library\system;

use ride\library\log\Log;
use ride\library\system\authentication\SshAuthentication;
use ride\library\system\exception\AuthenticationSshSystemException;
use ride\library\system\exception\SshSystemException;
use ride\library\system\file\SshFileSystem;

use \Exception;

include __DIR__ . '/phpseclib.php';

/**
 * SSH system
 */
class SshSystem {

    /**
     * Name of the log source
     * @var string
     */
    const LOG_SOURCE = 'ssh';

    /**
     * Implementation of the authentication
     * @var ride\library\system\authentication\Authentication
     */
    protected $authentication;

    /**
     * Host of the current connection
     * @var string
     */
    protected $host;

    /**
     * Port of the current connection
     * @var integer
     */
    protected $port;

    /**
     * Fingerprint of the connection
     * @var string
     */
    protected $fingerprint;

    /**
     * Flag to see if host key verification is enabled
     * @var boolean
     */
    protected $useHostKeyVerification;

    /**
     * Verified host keys
     * @var array
     */
    protected $hostKeys;

    /**
     * SSH connection
     * @var resource
     */
    protected $connection;

    /**
     * File system of the SSH connection
     * @var \ride\library\system\file\SshFileSystem
     */
    protected $fs;

    /**
     * Constructs a new SSH client
     * @throws zibo\library\ssh\exception\SshException when the SSH bindings
     * are not installed
     */
    public function __construct(SshAuthentication $authentication, $host, $port = 22) {
        if (!function_exists('ssh2_connect')) {
            throw new SshSystemException('Could not initialize the SSH protocol: install the SSH2 PHP bindings');
        }

        $this->authentication = $authentication;
        $this->host = $host;
        $this->port = $port;
        $this->fingerprint = null;
        $this->useHostKeyVerification = true;
        $this->hostKeys = array();
        $this->connection = null;
        $this->fs = null;
    }

    /**
     * Destructs the client
     * @return null
     */
    public function __destruct() {
        $this->disconnect();
    }

    /**
     * Gets a string representation of this server
     * @return string
     */
    public function __toString() {
        return $this->host . ($this->port != 22 ? ':' . $this->port : '');
    }

    /**
     * Sets a log to this system
     * @param \ride\library\log\Log $log
     * @return null
     */
    public function setLog(Log $log) {
        $this->log = $log;
    }

    /**
     * Gets the authentication
     * @return zibo\library\ssh\authentication\SshAuthentication
     */
    public function getAuthentication() {
        return $this->authentication;
    }

    /**
     * Gets the hostname or IP address of the current connection
     * @return string
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * Gets the port of the current connection
     * @return integer
     */
    public function getPort() {
        return $this->port;
    }

    /**
     * Enables or disabled the host key verification
     * @param boolean $useHostKeyVerification
     * @return null
     */
    public function setHostKeyVerification($useHostKeyVerification) {
        $this->useHostKeyVerification = $useHostKeyVerification;
    }

    /**
     * Gets whether host key verification is enabled
     * @return boolean
     */
    public function useHostKeyVerification() {
        return $this->useHostKeyVerification;
    }

    /**
     * Sets the verified host keys
     * @param array $hostKeys Array with the host(:port) as key and the
     * fingerprint as value
     * @return null
     */
    public function setHostKeys(array $hostKeys) {
        $this->hostKeys = $hostKeys;
    }

    /**
     * Gets the verified host keys
     * @return array Array with the host(:port) as key and the fingerprint as
     * value
     */
    public function getHostKeys() {
        return $this->hostKeys;
    }

    /**
     * Connects to the SSH server
     * @param string $host Hostname or IP address of the server
     * @param integer $port Port to connect to
     * @return null
     * @throws zibo\library\ssh\exception\SshException when the connection
     * could not be established
     */
    public function connect() {
        if (!$this->authentication) {
            throw new AuthenticationSshSystemException('Could not connect to ' . $this . ': no authentication set');
        }

        if ($this->log) {
            $this->log->logDebug('Connecting to ' . $this->host . ':' . $this->port, null, self::LOG_SOURCE);
        }

        $this->connection = @ssh2_connect($this->host, $this->port);
        if (!$this->connection) {
            $error = error_get_last();

            $description = 'Could not connect to ' . $this;
            $description .= ': ' . $error['message'];

            throw new SshSystemException($description);
        }

        if ($this->useHostKeyVerification) {
            if ($this->log) {
                $this->log->logDebug('Verifying fingerprint of ' . $this->host . ':' . $this->port, null, self::LOG_SOURCE);
            }

            $this->fingerprint = ssh2_fingerprint($this->connection);

            $hostKey = $this->host . ':' . $this->port;
            if (
                (isset($this->hostKeys[$hostKey]) && $this->hostKeys[$hostKey] != $this->fingerprint) ||
                (isset($this->hostKeys[$this->host]) && $this->hostKeys[$this->host] != $this->fingerprint)
            ) {
                throw new SshSystemException('Could not connect to ' . $this . ': host key mismatch');
            }

            $this->hostKeys[$hostKey] = $this->fingerprint;
        }

        if ($this->log) {
            $this->log->logDebug('Authenticating with ' . $this->host . ':' . $this->port, null, self::LOG_SOURCE);
        }

        try {
            $this->authentication->authenticate($this->connection);
        } catch (Exception $exception) {
            $this->connection = null;

            throw new AuthenticationSshSystemException('Could not connect to ' . $this . ': connection could not be authenticated', 0, $exception);
        }

        if ($this->log) {
            $this->log->logDebug('Connected to ' . $this->host . ':' . $this->port . ' as ' . $this->authentication->getClient(), null, self::LOG_SOURCE);
        }
    }

    /**
     * Gets the connection status of this system
     * @return boolean
     */
    public function isConnected() {
        return $this->connection ? true : false;
    }

    /**
     * Gets the connection resource
     * @return resource|null
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Disconnects from the SSH server
     * @return null
     */
    public function disconnect() {
        if (!$this->connection) {
            return;
        }

        ssh2_exec($this->connection, 'exit');

        unset($this->fs);
        unset($this->connection);

        $this->fs = null;
        $this->connection = null;

        if ($this->log) {
            $this->log->logDebug('Disconnected from ' . $this->host . ':' . $this->port, null, self::LOG_SOURCE);
        }
    }

    /**
     * Gets the fingerprint of the last connection
     * @return string
     */
    public function getFingerprint() {
        return $this->fingerprint;
    }

    /**
     * Gets the file system
     * @return \ride\library\system\file\FileSystem
     * @throws \ride\library\system\exception\Exception when the file
     * system is not supported
     */
    public function getFileSystem() {
        if ($this->fs) {
            return $this->fs;
        }

        $this->fs = new SshFileSystem($this);

        return $this->fs;
    }

    /**
     * Gets the client who is using the system. When invoked through cli, the
     * user of the system is returned, an ip when invoked through the web
     * @return string
     */
    public function getClient() {
        return $this->authentication->getClient();
    }

    /**
     * Executes a command or multiple commands on the system
     * @param string|array $command Command string or array of command strings
     * @return array Output of the command(s)
     * @throws \ride\library\system\exception\SystemException when the command
     * could not be executed
     */
    public function execute($command, &$code = null) {
        if (is_array($command)) {
            return $this->executeCommands($command, $code);
        } else {
            return $this->executeCommand($command, $code);
        }
    }

    /**
     * Executes a command on the remote server
     * @param string $command Command to execute
     * @return string output of the command
     * @throws zibo\library\ssh\exception\SshException when the command could not executed
     */
    protected function executeCommand($command, &$code = null, $pty = false, $env = null, $width = 120, $height = 25, $widthHeightType = SSH2_TERM_UNIT_CHARS) {
        if (!$this->isConnected()) {
            $this->connect();
        }

        if ($this->log) {
            $this->log->logDebug('Executing command on ' . $this, $command, self::LOG_SOURCE);
        }

        if ($code !== false) {
            $command .= '; echo $?';
        }

        $stdout = ssh2_exec($this->connection, $command, $pty, $env, $width, $height, $widthHeightType);
        $stderr = ssh2_fetch_stream($stdout, SSH2_STREAM_STDERR);

        stream_set_blocking($stdout, true);
        stream_set_blocking($stderr, true);

        $error = stream_get_contents($stderr);
        if ($error) {
            throw new SshSystemException('Could not execute command: ' . $error);
        }

        $output = stream_get_contents($stdout);

        fclose($stderr);
        fclose($stdout);

        if ($this->log) {
            $this->log->logDebug('Executed command on ' . $this, $output, self::LOG_SOURCE);
        }

        $output = explode("\n", trim($output));
        foreach ($output as $lineIndex => $line) {
            $output[$lineIndex] = trim($line);
        }

        if ($code !== false) {
            $code = array_pop($output);
        }

        return $output;
    }

    protected function executeCommands(array $commands, &$code = null) {
        $output = array();

        foreach ($commands as $index => $command) {
            try {
                $output[$index] = $this->executeCommand($command);
            } catch (RuntimeSshException $exception) {
                $output[$index] = $exception;
            }
        }

        return $output;
    }

}
