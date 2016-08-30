<?php

namespace ride\library\system\authentication;

/**
 * Interface for a SSH authentication method
 */
interface SshAuthentication {

    /**
     * Gets the username or user identifier of the client
     * @return string
     */
    public function getClient();

    /**
     * Authenticates the provided SSH session
     * @param resource $session SSH session
     * @return null
     */
    public function authenticate($session);

}
