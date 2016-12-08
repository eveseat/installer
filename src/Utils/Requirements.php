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

namespace Seat\Installer\Console\Utils;


use Seat\Installer\Console\Traits\ChecksForRootUser;
use Seat\Installer\Console\Traits\DetectsOperatingSystem;
use Seat\Installer\Console\Traits\FindsExecutables;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class Requirements
 * @package Seat\Installer\Console\Utils
 */
class Requirements
{

    use DetectsOperatingSystem, ChecksForRootUser, FindsExecutables;

    /**
     * @var \Symfony\Component\Console\Style\SymfonyStyle
     */
    protected $io;

    /**
     * @var string
     */
    protected $phpversion = '7.0.0';

    /**
     * @var array
     */
    protected $supported_os = [
        'ubuntu' => ['16.04'],
        'centos' => ['7'],
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
    ];

    /**
     * @var bool
     */
    private $requirements_ok = true;

    /**
     * Requirements constructor.
     *
     * @param \Symfony\Component\Console\Style\SymfonyStyle     $io
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function __construct(
        SymfonyStyle $io = null, InputInterface $input = null, OutputInterface $output = null)
    {

        if ($io)
            $this->io = $io;
        else
            $this->io = new SymfonyStyle($input, $output);

    }

    /**
     * Check the requirements for SeAT
     */
    public function checkSoftwareRequirements()
    {

        if (!$this->hasMinimumPhpVersion()) {

            $this->requirements_ok = false;
            $this->io->error('PHP Version is not at least v' . $this->phpversion);
        }

        if (!$this->hasSupportedOs()) {

            $this->requirements_ok = false;
            $this->io->error('Operating System is not supported by this installer');
        }

    }

    /**
     * @return bool
     */
    private function hasMinimumPhpVersion(): bool
    {

        return version_compare(PHP_VERSION, $this->phpversion) >= 0;
    }

    /**
     * @return bool
     */
    private function hasSupportedOs(): bool
    {

        $os = $this->getOperatingSystem();

        return (
            array_key_exists($os['os'], $this->supported_os) &&
            in_array($os['version'], $this->supported_os[$os['os']])
        );

    }

    /**
     * Check that the core PHP requirements are met.
     */
    public function checkPhpRequirements()
    {

        if (!$this->hasAllPhpExtentions()) {

            $this->requirements_ok = false;

            // Ask if we can try and install the needed package for
            // the missing extension.
            foreach ($this->php_extentions as $name => $loaded) {

                if (!$loaded) {

                    $this->io->error('Extention ' . $name . ' not loaded');

                    if ($this->io->confirm('Would you like to try and install it?')) {

                        $installer = new PackageInstaller($this->io);
                        $installer->installNeededPackage($name);
                    }
                }
            }

            $this->io->success('PHP requirements check completed. You may need to rerun the script to continue.');
        }


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

        return !in_array(null, $this->php_extentions);

    }

    /**
     * Check if root access is granted.
     */
    public function checkAccessRequirements()
    {

        if (!$this->haveRootAccess()) {

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

        if (!$executables = $this->hasAllCommands()) {

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

        return !in_array(null, $this->executables);

    }

    /**
     * @return bool
     */
    public function hasAllRequirements(): bool
    {

        return $this->requirements_ok;
    }
}
