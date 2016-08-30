# Ride: SSH System Library

SSH system abstraction library of the PHP Ride framework.

## Code Sample

Check this code sample to see the possibilities of this library:

```php
<?php

use ride\library\system\System;
use ride\library\system\SshSystem;

// password authentication
$authentication = new PasswordSshAuthentication();
$authentication->setUsername('username');
$authentication->setPasswword('password');

// public key authentication
$authentication = new PublicKeySshAuthentication();
$authentication->setUsername('username');
$authentication->setPublicKeyFile('/path/to/public-key');
$authentication->setPrivateKeyFile('/path/to/private-key');
$authentication->setPrivateKeyPassphrase('passphrase'); // optional

// create the ssh system
$remoteSystem = new SshSystem($authentication, 'my-ssh-host.com', 22);

// optional host key verifycation
$remoteSystem->setHostKeys(array(
    'host:port' => 'fingerprint',
));

// optional connect and disconnect
$remoteSystem->connect();
$remoteSystem->disconnect();

// check the client
$remoteSystem->getClient(); // username

// execute a command
$output = $remoteSystem->execute('whoami');

$code = null;
$output = $remoteSystem->execute('crontab -l', $code);

// file system abstraction
$remoteFileSystem = $remoteSystem->getFileSystem();

$dir = $remoteFileSystem->getFile('path/to/dir');
$dir->isDirectory();
$dir->isReadable();
$files = $dir->read();

$file = $remoteFileSystem->getFile('path/to/file');
$file->exists();
$file->getModificationTime();
$content = $file->read();

// remote copy
$destination = $dir->getChild($file->getName());
$destination = $destination->getCopyFile();

$file->copy($destination);

// download a file
$localSystem = new System();
$localFileSystem = $localSystem->getFileSystem();
$localFile = $localFileSystem->getFile('path/to/download');

$file->copy($localFile);
