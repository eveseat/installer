<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to 2020 Leon Jacobs
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

use Seat\Installer\Exceptions\PackageInstallationFailedException;
use Seat\Installer\Traits\DetectsOperatingSystem;
use Seat\Installer\Utils\Abstracts\AbstractUtil;

/**
 * Class PackageInstaller.
 * @package Seat\Installer\Utils
 */
class PackageInstaller extends AbstractUtil
{
    use DetectsOperatingSystem;

    /**
     * @var array
     */
    protected $os;

    /**
     * @var array
     */
    protected $package_manager = [
        'ubuntu' => 'apt-get install :package -y',
        'centos' => 'yum install :package -y',
        'debian' => 'apt-get install :package -y',
    ];

    /**
     * @var array
     */
    protected $php_extention_packages = [
        'ubuntu' => [
            '16.04' => [
                'pdo_mysql' => 'php7.1-mysql',
                'posix'     => 'php7.1-common',
            ],
            '18.04' => [
                'pdo_mysql' => 'php7.1-mysql',
                'posix'     => 'php7.1-common',
            ],
        ],

        'centos' => [
            '7' => [
                'pdo_mysql' => 'php-mysql',
                'posix'     => 'php-posix',
            ],
            '6' => [
                'pdo_mysql' => 'php-mysql',
                'posix'     => 'php-posix',
            ],
        ],

        'debian' => [
            '8' => [
                'pdo_mysql' => 'php7.1-mysql',
                'posix'     => 'php7.1-common',
            ],
            '9' => [
                'pdo_mysql' => 'php7.1-mysql',
                'posix'     => 'php7.1-common',
            ],
        ],
    ];

    /**
     * @var array
     */
    protected $command_packages = [

        // Ubuntu
        'ubuntu' => [
            '16.04' => [
                'unzip'     => 'unzip',
                'git'       => 'git',
                'pdo_mysql' => 'php7.1-mysql',
            ],
            '18.04' => [
                'unzip'     => 'unzip',
                'git'       => 'git',
                'pdo_mysql' => 'php7.1-mysql',
            ],
        ],

        // CentOS
        'centos' => [
            '7' => [
                'unzip'     => 'unzip',
                'git'       => 'git',
                'pdo_mysql' => 'php-mysql',
            ],
            '6' => [
                'unzip'     => 'unzip',
                'git'       => 'git',
                'pdo_mysql' => 'php-mysql',
            ],
        ],

        // Debian
        'debian' => [
            '8' => [
                'unzip'     => 'unzip',
                'git'       => 'git',
                'pdo_mysql' => 'php7.1-mysql',
            ],
            '9' => [
                'unzip'     => 'unzip',
                'git'       => 'git',
                'pdo_mysql' => 'php7.1-mysql',
            ],
        ],
    ];

    /**
     * @var array
     */
    protected $package_groups = [

        // Ubuntu
        'ubuntu' => [
            // Ubuntu 16.04 LTS
            '16.04' => [
                'mysql'      => [
                    'mysql-server', 'expect',
                ],
                'php'        => [
                    'php7.1-cli', 'php7.1-mcrypt', 'php7.1-intl',
                    'php7.1-mysql', 'php7.1-curl', 'php7.1-gd',
                    'php7.1-mbstring', 'php7.1-bz2', 'php7.1-dom',
                ],
                'apache'     => [
                    'apache2', 'libapache2-mod-php7.1',
                ],
                'nginx'      => [
                    'nginx', 'php7.1-fpm',
                ],
                'redis'      => [
                    'redis-server',
                ],
                'supervisor' => [
                    'supervisor',
                ],
            ],
            // Ubuntu 18.04 LTS
            '18.04' => [
                'mysql'      => [
                    'mysql-server', 'expect',
                ],
                'php'        => [
                    'php7.1-cli', 'php7.1-mcrypt', 'php7.1-intl',
                    'php7.1-mysql', 'php7.1-curl', 'php7.1-gd',
                    'php7.1-mbstring', 'php7.1-bz2', 'php7.1-dom',
                ],
                'apache'     => [
                    'apache2', 'libapache2-mod-php7.1',
                ],
                'nginx'      => [
                    'nginx', 'php7.1-fpm',
                ],
                'redis'      => [
                    'redis-server',
                ],
                'supervisor' => [
                    'supervisor',
                ],
            ],
        ],

        // CentOS
        'centos' => [
            // CentOS 7
            '7' => [
                'mysql'      => [
                    'MariaDB-server', 'expect',
                ],
                'php'        => [
                    'php-mysql', 'php-cli', 'php-mcrypt', 'php-process',
                    'php-mbstring', 'php-intl', 'php-dom', 'php-gd',
                ],
                'apache'     => [
                    'httpd', 'php',
                ],
                'nginx'      => [
                    'nginx', 'php-fpm',
                ],
                'redis'      => [
                    'redis',
                ],
                'supervisor' => [
                    'supervisor',
                ],
            ],
            // CentOS 6
            '6' => [
                'mysql'      => [
                    'MariaDB-server', 'expect',
                ],
                'php'        => [
                    'php-mysql', 'php-cli', 'php-mcrypt', 'php-process',
                    'php-mbstring', 'php-intl', 'php-dom', 'php-gd',
                ],
                'apache'     => [
                    'httpd', 'php',
                ],
                'nginx'      => [
                    'nginx', 'php-fpm',
                ],
                'redis'      => [
                    'redis',
                ],
                // We want to enable gf-plus repo only for supervisor, otherwise
                // it starts updating things we do not want it to.
                'supervisor' => [
                    'supervisor --enablerepo=gf-plus',
                ],
            ],
        ],

        'debian' => [
            '8' => [
                'mysql'      => [
                    'mariadb-server', 'expect',
                ],
                'php'        => [
                    'php7.1-cli', 'php7.1-mcrypt', 'php7.1-intl',
                    'php7.1-mysql', 'php7.1-curl', 'php7.1-gd',
                    'php7.1-mbstring', 'php7.1-bz2', 'php7.1-xml',
                ],
                'apache'     => [
                    'apache2', 'libapache2-mod-php7.1',
                ],
                'nginx'      => [
                    'nginx', 'php7.1-fpm',
                ],
                'redis'      => [
                    'redis-server',
                ],
                'supervisor' => [
                    'supervisor',
                ],
            ],
            '9' => [
                'mysql'      => [
                    'mariadb-server', 'expect',
                ],
                'php'        => [
                    'php7.1-cli', 'php7.1-mcrypt', 'php7.1-intl',
                    'php7.1-mysql', 'php7.1-curl', 'php7.1-gd',
                    'php7.1-mbstring', 'php7.1-bz2', 'php7.1-xml',
                ],
                'apache'     => [
                    'apache2', 'libapache2-mod-php7.1',
                ],
                'nginx'      => [
                    'nginx', 'php7.1-fpm',
                ],
                'redis'      => [
                    'redis-server',
                ],
                'supervisor' => [
                    'supervisor',
                ],
            ],
        ],
    ];

    /**
     * Process an array of packages to install.
     *
     * @param array $packages
     */
    public function installPackages(array $packages)
    {

        foreach ($packages as $package)
            $this->installPackage($package);
    }

    /**
     * @param string $package
     *
     * @throws \Seat\Installer\Exceptions\PackageInstallationFailedException
     */
    public function installPackage(string $package)
    {

        // If we are on a debian based system, let apt know we
        // dont want to do anything interactively.
        if ($this->getOs()['os'] == 'ubuntu' || $this->getOs()['os'] == 'debian')
            putenv('DEBIAN_FRONTEND=noninteractive');

        // Prepare the command to run.
        $command = str_replace(
            ':package', $package, $this->package_manager[$this->getOs()['os']]);

        // Start the installation.
        $success = $this->runCommandWithOutput($command, 'Package Installation (' . $package . ')');

        // Make sure composer installed fine.
        if (! $success)
            throw new PackageInstallationFailedException($package . ' installation failed.');
        $this->io->success('Package ' . $package . ' installed OK');

    }

    /**
     * Get the OS we are on.
     *
     * @return array
     */
    public function getOs()
    {

        if ($this->os)
            return $this->os;

        $this->os = $this->getOperatingSystem();

        return $this->os;

    }

    /**
     * @param string $command
     */
    public function installNeededPackage(string $command)
    {

        $this->io->text('Attempting to install the package that provides \'' . $command . '\'');

        // Check if we know what package provides this command
        if (array_key_exists($command,
            $this->command_packages[$this->getOs()['os']][$this->getOs()['version']])
        ) {

            $package = $this->command_packages[$this->getOs()['os']][$this->getOs()['version']][$command];
            $this->io->text('Installing package \'' . $package . '\' for the command');

            $this->installPackage($package);

            return;
        }

        $this->io->text('Not sure which package has the command. Going to try just installing the command.');
        $this->installPackage($command);

    }

    /**
     * @param string $group_name
     *
     * @throws \Seat\Installer\Exceptions\PackageInstallationFailedException
     */
    public function installPackageGroup(string $group_name)
    {

        $this->io->text('Installing packages for package group: \'' . $group_name . '\'.');

        if (! array_key_exists($group_name,
            $this->package_groups[$this->getOs()['os']][$this->getOs()['version']])
        )
            throw new PackageInstallationFailedException('Unknown package group: ' . $group_name);
        // Install the packages in the package group.
        foreach ($this->package_groups[$this->getOs()['os']][$this->getOs()['version']][$group_name] as $package)
            $this->installPackage($package);

    }

    /**
     * @param string $extention
     *
     * @throws \Seat\Installer\Exceptions\PackageInstallationFailedException
     */
    public function installPackageForPhpExtention(string $extention)
    {

        $this->io->text('Attempting to install the package that provides ' .
            'PHP extention \'' . $extention . '\'');

        // Check if we know what package provides this command
        if (array_key_exists($extention,
            $this->php_extention_packages[$this->getOs()['os']][$this->getOs()['version']])
        ) {

            $package = $this->php_extention_packages[$this->getOs()['os']][$this->getOs()['version']][$extention];
            $this->io->text('Installing package \'' . $package . '\' for the command');

            $this->installPackage($package);

            return;
        }

        throw new PackageInstallationFailedException(
            'Unable to find package for PHP extention: ' . $extention);

    }
}
