<?php
/*
This file is part of SeAT

Copyright (C) 2015, 2016  Leon Jacobs

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

namespace Seat\Installer\Utils;


use PDO;
use PDOException;
use Seat\Installer\Exceptions\MySqlConfigurationException;
use Seat\Installer\Traits\FindsExecutables;
use Seat\Installer\Traits\GeneratesPasswords;
use Seat\Installer\Utils\Abstracts\AbstractUtil;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Class MySql
 * @package Seat\Installer\Utils
 */
class MySql extends AbstractUtil
{

    use FindsExecutables, GeneratesPasswords;

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
     * @return bool
     */
    public function isInstalled(): bool
    {

        if ($this->hasExecutable('mysql') && $this->hasExecutable('mysqld'))
            return true;

        return false;

    }

    /**
     * Install MySQL
     */
    public function install()
    {

        $installer = new PackageInstaller($this->io);

        $installer->installPackage('mysql-server');
        $installer->installPackage('expect');

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
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
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

        // The epect command to run:
        $expect = <<<EOF
SECURE_MYSQL=$(expect -c "
set timeout 10
spawn mysql_secure_installation

expect \"Press y|Y for Yes, any other key for No:\"
send \"n\r\"

expect \"New password:\"
send \"{$this->credentials['root_password']}\r\"
expect \"Re-enter new password:\"
send \"{$this->credentials['root_password']}\r\"

expect \"Remove anonymous users? (Press y|Y for Yes, any other key for No) :\"
send \"y\r\"

expect \"Disallow root login remotely? (Press y|Y for Yes, any other key for No) :\"
send \"y\r\"

expect \"Remove test database and access to it? (Press y|Y for Yes, any other key for No) :\"
send \"y\r\"

expect \"Reload privilege tables now? (Press y|Y for Yes, any other key for No) :\"
send \"y\r\"

expect eof
"); echo "\$SECURE_MYSQL"
EOF;

        // Prepare and start the mysql_secure_installation command.
        $process = new Process($expect);
        $process->setTimeout(3600);
        $process->run();

        // Make sure it ran fine.
        if (!$process->isSuccessful())
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
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

            echo "connected";

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
