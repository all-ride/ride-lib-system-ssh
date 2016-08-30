<?php

namespace ride\library\system\authentication;

use ride\library\system\exception\SshSystemException;

/**
 * Implementation for the password SSH authentication method
 */
class PasswordSshAuthentication extends AbstractSshAuthentication {

    /**
     * Password to authenticate with
     * @var string
     */
    protected $password;

    /**
     * Sets the password
     * @param string $password
     * @return null
     */
    public function setPassword($password) {
        $this->password = $password;
    }

    /**
     * Gets the password
     * @return string
     */
    public function getPassword() {
        return $this->password;
    }

    /**
     * Authenticates the user in the provided SSH session
     * @param resource $session
     * @param string $username
     * @return null
     */
    protected function authenticateUser($session, $username) {
        if (!$this->password) {
            throw new SshSystemException('Could not authenticate the SSH session: no password set');
        }

        if (!@ssh2_auth_password($session, $username, $this->password)) {
            throw new SshSystemException('Could not authenticate the SSH session: invalid username or password');
        }

        return true;
    }

}
