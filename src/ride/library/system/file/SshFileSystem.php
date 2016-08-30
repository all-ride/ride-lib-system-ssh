<?php

namespace ride\library\system\file;

use ride\library\system\exception\FileSystemException;
use ride\library\system\SshSystem;

/**
 * SSH file system implementation
 */
class SshFileSystem extends AbstractFileSystem {

    /**
     * Instance of the underlying SSH system
     * @var \ride\library\system\SshSystem
     */
    private $system;

    /**
     * SFTP connection from the SSH system
     * @var resource
     */
    private $connection;

    /**
     * Constructs a new SSH file system
     * @param \ride\library\system\SshSystem $sshSystem
     * @return null
     */
    public function __construct(SshSystem $sshSystem) {
        $this->system = $sshSystem;
        $this->connection = null;
    }

    /**
     * Destructs this file system
     * @return null
     */
    public function __destruct() {
        $this->disconnect();
    }

    /**
     * Connects to the SFTP subsystem
     * @return null
     */
    public function connect() {
        if (!$this->system->isConnected()) {
            $this->system->connect();
        }

        $this->connection = @ssh2_sftp($this->system->getConnection());
        if (!$this->connection) {
            $error = error_get_last();

            throw new SshException('Could not open SFTP to ' . $this->system . ': ' . $error['message']);
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
    private function getConnection() {
        if (!$this->isConnected()) {
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * Disconnects the SFTP connection from the SSH server
     * @return null
     */
    public function disconnect() {
        if (!$this->connection) {
            return;
        }

        unset($this->connection);
    }

    /**
     * Gets the internal prefix for paths on this SSH file system
     * @return string
     */
    public function getConnectionString() {
        return 'ssh2.sftp://' . $this->getConnection();
    }

    /**
     * Gets a instance of a file
     * @param string $path
     * @return File
     */
    public function getFile($path) {
        return new SshFile($this, $path);
    }

    /**
     * Gets the current working directory
     * @return string
     */
    public function getCurrentWorkingDirectory() {
        if (!isset($this->cwd)) {
            $output = $this->system->execute('pwd');

            $this->cwd = array_shift($output);
        }

        return $this->cwd;
    }

    /**
     * Gets the path of the file as used locally on the system
     * @param File $file
     * @return string
     */
    public function getLocalPath(File $file) {
        $path = $this->getAbsolutePath($file);
        $path = str_replace($this->getConnectionString(), '', $path);

        return $path;
    }

    /**
     * Get the absolute path for a file
     * @param File $file
     * @return string
     */
    public function getAbsolutePath(File $file) {
        $path = $file->getPath();

        if (!$this->isAbsolute($file)) {
            $path = $this->getCurrentWorkingDirectory() . File::DIRECTORY_SEPARATOR . $path;
        }

        $absolutePath = array();

        $parts = explode(File::DIRECTORY_SEPARATOR, $path);
        foreach ($parts as $part) {
            if ($part == '' || $part == '.') {
                continue;
            }

            if ($part == '..') {
                array_pop($absolutePath);

                continue;
            }

            array_push($absolutePath, $part);
        }

        $absolutePath = File::DIRECTORY_SEPARATOR . implode(File::DIRECTORY_SEPARATOR, $absolutePath);

        return $this->getConnectionString() . $absolutePath;
    }

    /**
     * Check whether a file has an absolute path
     * @param File $file
     * @return boolean True when the file has an absolute path
     */
    public function isAbsolute(File $file) {
        $path = $file->getPath();
        $path = str_replace($this->getConnectionString(), '', $path);

        return $path{0} == File::DIRECTORY_SEPARATOR;
    }

    /**
     * Check whether a path is a root path (/, c:/, //server)
     * @param string $path
     * @return boolean True when the file is a root path, false otherwise
     */
    public function isRootPath($path) {
        return strlen($path) == 1 && $path == File::DIRECTORY_SEPARATOR;
    }

    /**
     * Get the parent of the provided file
     *
     * If you provide a path like /var/www/site, the parent will be /var/www
     * @param File $file
     * @return File Parent of the file
     */
    public function getParent(File $file) {
        $path = $file->getPath();

        if (strpos($path, File::DIRECTORY_SEPARATOR) === false) {
            $parent = $this->getFile('.');

            return $this->getFile($this->getAbsolutePath($parent));
        }

        $name = $file->getName();
        $nameLength = strlen($name);

        $parent = substr($path, 0, ($nameLength + 1) * -1);
        if (!$parent) {
            return $this->getFile(File::DIRECTORY_SEPARATOR);
        }

        return $this->getFile($parent);
    }

    /**
     * Checks whether a file exists
     * @param File $file
     * @return boolean True when the file exists, false otherwise
     */
    public function exists(File $file) {
        $stat = $this->getStat($file);
        if (!$stat) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether a file is a directory
     * @param File $file
     * @return boolean True when the file is a directory, false otherwise
     */
    public function isDirectory(File $file) {
        $stat = $this->getStat($file);
        if (!$stat) {
            return false;
        }

        return $stat['mode'] & 040000 ? true : false;
    }

    /**
     * Checks whether a file is readable
     * @param File $file
     * @return boolean True when the file is readable, false otherwise
     */
    public function isReadable(File $file) {
        $stat = $this->getStat($file);
        if (!$stat) {
            return false;
        }

        return $stat['mode'] & 0400 ? true : false;
    }

    /**
     * Checks whether a file is writable.
     *
     * When the file exists, the file itself will be checked. If not, the
     * parent directory will be checked
     * @param File $file
     * @return boolean true when the file is writable, false otherwise
     */
    public function isWritable(File $file) {
        $stat = $this->getStat($file);
        if (!$stat) {
            return false;
        }

        return $stat['mode'] & 0200 ? true : false;
    }

    /**
     * Checks whether a file is hidden.
     * @param File $file
     * @return boolean true when the file is hidden, false otherwise
     */
    public function isHidden(File $file) {
        return substr($file->getName(), 0, 1) == '.';
    }

    /**
     * Get the timestamp of the last write to the file
     * @param File $file
     * @return int timestamp of the last modification
     * @throws \ride\library\system\exception\FileSystemException when the
     * file does not exist
     * @throws \ride\library\system\exception\FileSystemException when the
     * modification time could not be read
     */
    public function getModificationTime(File $file) {
        $stat = $this->getStat($file);

        if (!$stat) {
            throw new FileSystemException('Could not get modification time of ' . $file . ': file does not exist');
        } elseif (!isset($stat['mtime'])) {
            throw new FileSystemException('Could not get modification time of ' . $file . ': no time received');
        }

        return $stat['mtime'];
    }

    /**
     * Get the size of a file
     * @param File $file
     * @return int size of the file in bytes
     * @throws \ride\library\system\exception\FileSystemException when the
     * file is a directory
     * @throws \ride\library\system\exception\FileSystemException when the
     * file size could not be read
     */
    public function getSize(File $file) {
        $stat = $this->getStat($file);

        if (!$stat) {
            throw new FileSystemException('Could not get size of ' . $file . ': file does not exist');
        } elseif (!isset($stat['size'])) {
            throw new FileSystemException('Could not get size of ' . $file . ': no size received');
        }

        return $stat['size'];
    }

    /**
     * Get the permissions of a file or directory
     * @param File $file
     * @return int an octal value of the permissions. eg. 0755
     * @throws \ride\library\system\exception\FileSystemException when the
     * file or directory does not exist
     * @throws \ride\library\system\exception\FileSystemException when the
     * permissions could not be read
     */
    public function getPermissions(File $file) {
        $stat = $this->getStat($file);
        if (!$stat) {
            throw new FileSystemException('Could not get the permissions of ' . $file . ': file does not exist');
        } elseif (!isset($stat['mode'])) {
            throw new FileSystemException('Could not get the permissions of ' . $file . ': no permissions retrieved');
        }

        return $stat['mode'] & 0777;
    }

    /**
     * Set the permissions of a file or directory
     * @param File $file
     * @param int $permissions an octal value of the permissions, so strings
     * (such as "g+w") will not work properly. To ensure expected operation,
     * you need to prefix mode with a zero (0). eg. 0755
     * @return null
     * @throws \ride\library\system\exception\FileSystemException when the
     * file or directory does not exist
     * @throws \ride\library\system\exception\FileSystemException when the
     * permissions could not be set
     */
    public function setPermissions(File $file, $permissions) {
        if (!$this->exists($file)) {
            throw new FileSystemException('Could not set the permissions of ' . $file . ' on ' . $this->system . ' to ' . $permissions . ': file does not exist');
        }

        $path = $this->getLocalPath($file);

        try {
            $code = null;
            $output = $this->system->execute('chmod ' . decoct($permissions) . ' ' . $path, $code);

            if ($code != 0) {
                throw new RuntimeSshException(implode("\n", $output));
            }
        } catch (RuntimeSshException $exception) {
            throw new FileSystemException('Could not set the permissions of ' . $path . ' on ' . $this->system . ' to ' . $permissions, 0, $exception);
        }
    }

    /**
     * Create a directory
     * @param File $dir
     * @return null
     * @throws \ride\library\system\exception\FileSystemException when the
     * directory could not be created
     */
    public function create(File $dir) {
        if ($this->exists($dir)) {
            return;
        }

        $path = $this->getLocalPath($dir);

        $result = @ssh2_sftp_mkdir($this->getConnection(), $path, 0755, true);
        if ($result === false) {
            $error = error_get_last();

            throw new FileSystemException('Could not create ' . $path . ' on ' . $this->system . ': ' . $error['message']);
        }
    }

    /**
     * Read a directory
     * @param File $dir directory to read
     * @param boolean $recursive true to read the subdirectories, false
     * (default) to only read the given directory
     * @return array Array with a File object as value and it's path as key
     * @throws \ride\library\system\exception\FileSystemException when the
     * directory could not be read
     */
    protected function readDirectory(File $dir, $recursive = false) {
        $code = null;
        $output = $this->system->execute('ls -1 ' . $dir->getLocalPath(), $code);

        if ($code != '0') {
            throw new FileSystemException('Could not read directory ' . $dir->getLocalPath());
        }

        $files = array();

        foreach ($output as $f) {
            $f = trim($f);

            $file = $dir->getChild($f);

            $files[$file->getLocalPath()] = $file;

            if ($recursive && $file->isDirectory()) {
                $tmp = $this->readDirectory($file, true);
                foreach ($tmp as $k => $v) {
                    $files[$k] = $v;
                }
            }
        }

        return $files;
    }

    /**
     * Delete a file or directory
     * @param File $file
     * @return null
     * @throws \ride\library\system\exception\FileSystemException when the
     * file or directory could not be deleted
     */
    public function delete(File $file) {
        if (!$file->exists()) {
            return;
        }

        $code = 0;
        $output = $this->system->execute('rm -rf ' . $this->getLocalPath($file));

        if ($code != 0) {
            throw new FileSystemException('Could not delete ' . $file . ': ' . implode("\n", $output));
        }
    }

    /**
     * Gets the stat of a file
     * @param File $file
     * @return array
     */
    private function getStat(File $file) {
        clearstatcache();

        $path = $this->getLocalPath($file);

        return @ssh2_sftp_lstat($this->getConnection(), $path);
    }

}
