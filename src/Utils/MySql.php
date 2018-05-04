<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015, 2016, 2017, 2018  Leon Jacobs
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Seat\Installer\Utils;

use PDO;
use PDOException;
use Seat\Installer\Exceptions\MySqlConfigurationException;
use Seat\Installer\Traits\DetectsOperatingSystem;
use Seat\Installer\Traits\DownloadsResources;
use Seat\Installer\Traits\FindsExecutables;
use Seat\Installer\Traits\GeneratesPasswords;
use Seat\Installer\Utils\Abstracts\AbstractUtil;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class MySql.
 * @package Seat\Installer\Utils
 */
class MySql extends AbstractUtil
{
    use DownloadsResources, DetectsOperatingSystem, FindsExecutables, GeneratesPasswords;

    /**
     * @var array
     */
    protected $credentials = [
        'username' => null,
        'password' => null,
        'database' => null,
    ];

    /**
     * @var string
     */
    protected $credentials_file = '/root/.seat-credentials';

    /**
     * @var array
     */
    protected $restart_enable_commands = [
        'ubuntu' => [
            '16.04' => [
                'systemctl enable mysql.service',
                'systemctl restart mysql.service',
            ],
        ],

        'centos' => [
            '7' => [
                'systemctl enable mariadb.service',
                'systemctl restart mariadb.service',
            ],
            '6' => [
                'chkconfig mysqld on',
                '/etc/init.d/mysqld restart',
            ],
        ],
        'debian' => [
            '8' => [
                'systemctl enable mariadb.service',
                'systemctl restart mariadb.service',
            ],
            '9' => [
                'systemctl enable mariadb.service',
                'systemctl restart mariadb.service',
            ],
        ],
    ];

    /**
     * @var array
     */
    protected $secure_install_scripts = [
        'ubuntu' => 'mysql_secure_installation.ubuntu.bash',
        'centos' => 'mysql_secure_installation.centos.bash',
        'debian' => 'mysql_secure_installation.debian.bash',
    ];

    /**
     * @return bool
     */
    public function isInstalled(): bool
    {

        if ($this->hasExecutable('mysqld_safe'))
            return true;

        return false;

    }

    /**
     * Install MySQL.
     */
    public function install()
    {

        $installer = new PackageInstaller($this->io);

        $installer->installPackageGroup('mysql');
        $this->restartAndEnable();
        $this->io->success('MySQL installation complete');

    }

    /**
     * @throws \Seat\Installer\Exceptions\MySqlConfigurationException
     */
    public function restartAndEnable()
    {

        $os = $this->getOperatingSystem();

        foreach ($this->restart_enable_commands[$os['os']][$os['version']] as $command)
            $success = $this->runCommandWithOutput($command, 'MySQL Restart');

        if (! $success)
            throw new MySqlConfigurationException('Unable to restart MySQL');
    }

    /**
     * @return array
     */
    public function getCredentials(): array
    {

        return $this->credentials;
    }

    /**
     * @param array $credentials
     */
    public function setCredentials(array $credentials)
    {

        $this->credentials = $credentials;

    }

    /**
     * Test that the current set of credentails work.
     *
     * @return bool
     */
    public function testCredentails()
    {

        try {

            new PDO('mysql:host=127.0.0.1;dbname=' . $this->credentials['database'],
                $this->credentials['username'],
                $this->credentials['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

        } catch (PDOException $e) {

            $this->io->error('Databse connection error. ' . $e->getMessage());

            return false;
        }

        return true;

    }

    /**
     * @return array|mixed
     */
    public function readCredentialsFile()
    {

        $credentials = file_get_contents($this->credentials_file);
        $this->credentials = json_decode($credentials);

        return $this->credentials;

    }

    /**
     * Configure a new installation of MySQL.
     */
    public function configure()
    {

        $this->io->text('Checking that MySQL is started');

        $this->io->text('Securing MySQL installation');
        $this->secureInstallation();

        $this->io->text('Creating Database and adding user for SeAT');
        $this->createUserAndDatabase();

    }

    /**
     * @throws \Seat\Installer\Exceptions\MySqlConfigurationException
     */
    private function secureInstallation()
    {

        // Generate passwords for the root MySQL user.
        $this->credentials['root_password'] = $this->generatePassword();
        $this->saveCredentials();

        // Get the script to use to secure the MySQL installation
        $script = $this->downloadResourceFile(
            $this->secure_install_scripts[$this->getOperatingSystem()['os']]);

        // Replace some values with the new root password
        $script = str_replace(':MYSQL_ROOT_PASS', $this->credentials['root_password'], $script);

        // Start the mysql_secure_installation command.
        $success = $this->runCommandWithOutput($script, 'MySQL Secure Installation');

        // Make sure it ran fine.
        if (! $success)
            throw new MySqlConfigurationException('MySQL configuration failed.');
    }

    /**
     * Save the current credentials to a json encoded file.
     */
    public function saveCredentials()
    {

        $fs = new Filesystem();
        $fs->dumpFile($this->credentials_file, json_encode($this->credentials));

    }

    /**
     * @throws \Seat\Installer\Exceptions\MySqlConfigurationException
     */
    private function createUserAndDatabase()
    {

        // Create a password for the SeAT user.
        $this->credentials['username'] = 'seat';
        $this->credentials['database'] = 'seat';
        $this->credentials['password'] = $this->generatePassword();
        $this->saveCredentials();

        try {

            // Login as root to create the new user.
            $handler = new PDO('mysql:host=localhost', 'root',
                $this->credentials['root_password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

            // Create the databse
            $handler->exec('create database ' . $this->credentials['database'] . ';');

            // Prepare the user.
            $handler->exec('GRANT ALL ON ' . $this->credentials['database'] .
                '.* to ' . $this->credentials['username'] . '@localhost IDENTIFIED BY \'' .
                $this->credentials['password'] . '\';');

        } catch (PDOException $e) {

            throw new MySqlConfigurationException($e->getMessage());
        }
    }
}
