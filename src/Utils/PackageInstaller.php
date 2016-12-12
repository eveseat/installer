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


use Seat\Installer\Exceptions\PackageInstallationFailedException;
use Seat\Installer\Traits\DetectsOperatingSystem;
use Seat\Installer\Utils\Abstracts\AbstractUtil;

/**
 * Class PackageInstaller
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
        'centos' => 'yum install :package -y'
    ];

    /**
     * @var array
     */
    protected $command_packages = [
        'ubuntu' => [
            'unzip'     => 'unzip',
            'git'       => 'git',
            'pdo_mysql' => 'php-mysql',
        ],
        'centos' => [
            'unzip'     => 'unzip',
            'git'       => 'git',
            'pdo_mysql' => 'php-mysql'
        ]
    ];

    /**
     * @var array
     */
    protected $package_groups = [

        // Ubuntu
        'ubuntu' => [
            'php'        => [
                'php-cli', 'php-mcrypt', 'php-intl',
                'php-mysql', 'php-curl', 'php-gd',
                'php-mbstring', 'php-bz2', 'php-dom'
            ],
            'apache'     => [
                'apache2', 'libapache2-mod-php'
            ],
            'redis'      => [
                'redis-server'
            ],
            'supervisor' => [
                'supervisor'
            ]
        ],

        // CentOS
        'centos' => [
            'php' => []
        ],
    ];

    /**
     * Get the OS we are on.
     *
     * @return array
     */
    public function getOs()
    {

        if ($this->os)
            return $this->os;

        $this->os = $this->getOperatingSystem()['os'];

        return $this->os;


    }

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
        if ($this->getOs() == 'ubuntu' || $this->getOs() == 'debian')
            putenv('DEBIAN_FRONTEND=noninteractive');

        // Prepare the command to run.
        $command = str_replace(
            ':package', $package, $this->package_manager[$this->getOs()]);

        // Start the installation.
        $success = $this->runCommandWithOutput($command, 'Package Installation (' . $package . ')');

        // Make sure composer installed fine.
        if (!$success)
            throw new PackageInstallationFailedException($package . ' installation failed.');

    }

    /**
     * @param string $command
     */
    public function installNeededPackage(string $command)
    {

        $this->io->text('Attempting to install the package that provides \'' . $command . '\'');

        // Check if we know what package provides this command
        if (array_key_exists($command, $this->command_packages[$this->getOs()])) {

            $package = $this->command_packages[$this->getOs()][$command];
            $this->io->text('Installing package \'' . $package . '\' for the command');

            $this->installPackage($package);

            return;
        }

        $this->io->text('Not sure which package has the command. Going to try just installing the command.');
        $this->installPackage($command);

        return;

    }

    /**
     * @param string $group_name
     *
     * @throws \Seat\Installer\Exceptions\PackageInstallationFailedException
     */
    public function installPackageGroup(string $group_name)
    {

        $this->io->text('Installing packages for package group: \'' . $group_name . '\'.');

        if (!array_key_exists($group_name, $this->package_groups[$this->getOs()]))
            throw new PackageInstallationFailedException('Unknown package group: ' . $group_name);

        // Install the packages in the package group.
        foreach ($this->package_groups[$this->getOs()][$group_name] as $package)
            $this->installPackage($package);

    }

}
