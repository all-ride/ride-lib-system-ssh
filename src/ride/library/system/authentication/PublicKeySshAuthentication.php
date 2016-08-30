<?php

namespace ride\library\system\authentication;

use ride\library\system\exception\SshSystemException;

/**
 * Implementation for the public key SSH authentication method
 */
class PublicKeySshAuthentication extends AbstractSshAuthentication {

    /**
     * Path to the public key
     * @var string
     */
    protected $publicKeyFile;

    /**
     * Path to the private key
     * @var string
     */
    protected $privateKeyFile;

    /**
     * Path to the private passphrase
     * @var string
     */
    protected $privateKeyPassphrase;

    /**
     * Sets the SSH public key
     * @param string $publicKeyFile Path to the public key file
     * @return null
     */
    public function setPublicKeyFile($publicKeyFile) {
        $this->publicKeyFile = $publicKeyFile;
    }

    /**
     * Gets the SSH public key
     * @return string Path to the public key file
     */
    public function getPublicKeyFile() {
        return $this->publicKeyFile;
    }

    /**
     * Sets the SSH private key
     * @param string $privateKeyFile Path to the private key file
     * @return null
     */
    public function setPrivateKeyFile($privateKeyFile) {
        $this->privateKeyFile = $privateKeyFile;
    }

    /**
     * Gets the SSH private key
     * @return string Path to the private key file
     */
    public function getPrivateKeyFile() {
        return $this->privateKeyFile;
    }

    /**
     * Sets the SSH private key passphrase
     * @param string $passphrase Passphrase of the private key
     * @return null
     */
    public function setPrivateKeyPassphrase($privateKeyPassphrase) {
        $this->privateKeyPassphrase  = $privateKeyPassphrase;
    }

    /**
     * Gets the SSH private key passphrase
     * @return string Passphrase of the private key
     */
    public function getPrivateKeyPassphrase() {
        return $this->privateKeyPassphrase;
    }

    /**
     * Authenticates the user in the provided SSH session
     * @param resource $session
     * @param string $username
     * @return null
     */
    protected function authenticateUser($session, $username) {
        if (!$this->publicKeyFile) {
            throw new SshSystemException('Could not authenticate the SSH session: no public key file set');
        }

        if (!$this->privateKeyFile) {
            throw new SshSystemException('Could not authenticate the SSH session: no private key file set');
        }

        if (!$this->privateKeyPassphrase) {
            $passphrase = null;
        }

        if (!@ssh2_auth_pubkey_file($session, $username, $this->publicKeyFile, $this->privateKeyFile, $passphrase)) {
            throw new SshSystemException('Could not authenticate the SSH session: invalid username or key');
        }

        return true;
    }

}
