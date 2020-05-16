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

use Seat\Installer\Exceptions\OperatingSystemNotSupportedException;
use Seat\Installer\Exceptions\UnsupportedPhpVersionException;
use Seat\Installer\Traits\ChecksForRootUser;
use Seat\Installer\Traits\DetectsOperatingSystem;
use Seat\Installer\Traits\FindsExecutables;
use Seat\Installer\Utils\Abstracts\AbstractUtil;

/**
 * Class Requirements.
 * @package Seat\Installer\Utils
 */
class Requirements extends AbstractUtil
{
    use DetectsOperatingSystem, ChecksForRootUser, FindsExecutables;

    /**
     * @var string
     */
    protected $phpversion = '7.0.0';

    /**
     * @var array
     */
    protected $supported_os = [
        'ubuntu' => ['16.04', '18.04', '20.04'],
        'centos' => ['6', '7'],
        'debian' => ['8', '9'],
    ];

    /**
     * @var array
     */
    protected $executables = [
        'git'   => null,
        'unzip' => null,
    ];

    /**
     * @var array
     */
    protected $php_extentions = [
        'pdo_mysql' => null,
        'posix'     => null,
    ];

    /**
     * @var bool
     */
    private $requirements_ok = true;

    /**
     * Check the requirements for SeAT.
     */
    public function checkSoftwareRequirements()
    {

        if (! $this->hasMinimumPhpVersion())
            throw new UnsupportedPhpVersionException(
                'PHP version ' . $this->phpversion . ' is not supported. ' .
                'Please install at least PHP version 7'
            );

        if (! $this->hasSupportedOs())
            throw new OperatingSystemNotSupportedException('Unsupported Operating System');
    }

    /**
     * @return bool
     */
    public function hasMinimumPhpVersion(): bool
    {

        return version_compare(PHP_VERSION, $this->phpversion) >= 0;
    }

    /**
     * @return bool
     */
    public function hasSupportedOs(): bool
    {

        $os = $this->getOperatingSystem();

        // Be a little verbose about the OS detection.
        if (array_key_exists($os['os'], $this->supported_os) &&
            in_array($os['version'], $this->supported_os[$os['os']])
        )
            $this->io->text('Operating system detected as: ' . $os['os'] . ' ' . $os['version']);
        else
            $this->io->note('Unable to determine Operating System');

        return
            array_key_exists($os['os'], $this->supported_os) &&
            in_array($os['version'], $this->supported_os[$os['os']]);

    }

    /**
     * Check that the core PHP requirements are met.
     */
    public function checkPhpRequirements(): bool
    {

        if (version_compare(phpversion(), '7.1', '<')) {

            $this->io->error('Current PHP version is not at least v7.1+');

            return $this->requirements_ok = false;
        }

        if (! $this->hasAllPhpExtentions()) {

            $this->requirements_ok = false;

            // Ask if we can try and install the needed package for
            // the missing extension.
            foreach ($this->php_extentions as $name => $loaded) {

                if (! $loaded) {

                    $this->io->error('PHP Extention ' . $name . ' not loaded');

                    if ($this->io->confirm('Would you like to try and install it?')) {

                        $installer = new PackageInstaller($this->io);
                        $installer->installPackageForPhpExtention($name);
                    }
                }
            }

            $this->io->success('PHP requirements check completed. You may need to rerun the script to continue.');
        }

        return $this->requirements_ok;

    }

    /**
     * Check for any missinh PHP extentions.
     *
     * @return bool
     */
    private function hasAllPhpExtentions()
    {

        foreach ($this->php_extentions as $name => $loaded) {

            if (extension_loaded($name))
                $this->php_extentions[$name] = true;
        }

        return ! in_array(null, $this->php_extentions);

    }

    /**
     * Check if root access is granted.
     */
    public function checkAccessRequirements()
    {

        if (! $this->haveRootAccess()) {

            $this->requirements_ok = false;
            $this->io->error('Not running as root');
        }
    }

    /**
     * @param array|null $commands
     */
    public function checkCommandRequirements(array $commands = null)
    {

        // Map the executables from the function argument if needed
        if ($commands)
            $this->executables = $commands;

        if (! $executables = $this->hasAllCommands()) {

            $this->requirements_ok = false;

            // Be verbose about which commands are missing.
            foreach ($this->executables as $name => $path) {

                if (is_null($path)) {

                    $this->io->error('Cant find executable for: ' . $name);

                    if ($this->io->confirm('Would you like to try and install it?')) {

                        $installer = new PackageInstaller($this->io);
                        $installer->installNeededPackage($name);
                    }
                }
            }

            $this->io->success('Commands check completed. You may need to rerun the script to continue.');
        }

    }

    /**
     * @return bool
     */
    private function hasAllCommands()
    {

        $this->executables = $this->findExecutables($this->executables);

        return ! in_array(null, $this->executables);

    }

    /**
     * @return bool
     */
    public function hasAllRequirements(): bool
    {

        return $this->requirements_ok;
    }
}
