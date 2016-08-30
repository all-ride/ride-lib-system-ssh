<?php

namespace ride\library\system\file;

use ride\library\system\exception\FileSystemException;

/**
 * SSH file object, facade for the file system library
 */
class SshFile extends File {

    /**
     * Construct a file object
     * @param \ride\library\system\file\FileSystem $fileSystem
     * @param string|File $path
     * @return null
     * @throws \ride\library\system\exception\FileSystemException when the
     * system is unsupported
     * @throws \ride\library\system\exception\FileSystemException when the
     * path is empty
     */
    public function __construct(FileSystem $fileSystem, $path) {
        if (!$fileSystem instanceof SshFileSystem) {
            throw new FileSystemException('Could not create SSH file: no SshFileSystem provided');
        }

        parent::__construct($fileSystem, $path);
    }

    /**
     * Sets the path from a string or File object
     * @param string|File $path
     * @return null
     */
    protected function setPath($path) {
        $path = str_replace($this->fs->getConnectionString(), '', $path);

        parent::setPath($path);
    }

    /**
     * Gets the path of the file as used locally on the remote system
     * @return string
     */
    public function getLocalPath() {
        return $this->fs->getLocalPath($this);
    }

}
