<?php

namespace ride\library\system\authentication;

use ride\library\system\exception\SshSystemException;

/**
 * Abstract implementation for a SSH authentication method
 */
abstract class AbstractSshAuthentication implements SshAuthentication {

    /**
     * Username to authenticate
     * @var string
     */
    protected $username;

    /**
     * Gets the username or user identifier of the client
     * @return string
     */
    public function getClient() {
        return $this->username;
    }

    /**
     * Sets the username
     * @param string $username
     * @return null
     */
    public function setUsername($username) {
        $this->username = $username;
    }

    /**
     * Gets the username
     * @return string
     */
    public function getUsername() {
        return $this->username;
    }

    /**
     * Authenticates the provided SSH session
     * @param resource $session SSH session
     * @return null
     */
    public function authenticate($session) {
        if (!$this->username) {
            throw new SshSystemException('Could not authenticate the SSH session: no username set');
        }

        return $this->authenticateUser($session, $this->username);
    }

    /**
     * Authenticates the user in the provided SSH session
     * @param resource $session
     * @param string $username
     * @return null
     */
    abstract protected function authenticateUser($session, $username);

}
