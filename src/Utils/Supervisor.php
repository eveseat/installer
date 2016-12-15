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


use Seat\Installer\Traits\DetectsOperatingSystem;
use Seat\Installer\Traits\DownloadsResources;
use Seat\Installer\Traits\FindsExecutables;
use Seat\Installer\Utils\Abstracts\AbstractUtil;

/**
 * Class Supervisor
 * @package Seat\Installer\Utils
 */
class Supervisor extends AbstractUtil
{

    use DetectsOperatingSystem, DownloadsResources, FindsExecutables;

    /**
     * @var
     */
    protected $user;

    /**
     * @var
     */
    protected $path;

    /**
     * @var array
     */
    protected $restart_enable_commands = [
        'ubuntu' => [
            '16.04' => [
                'systemctl enable supervisor.service',
                'systemctl restart supervisor.service'
            ],
        ],
        'centos' => [
            '7' => [
                'systemctl enable supervisord',
                'systemctl restart supervisord'
            ]
        ]
    ];

    /**
     * @var array
     */
    protected $config_locations = [
        'ubuntu' => [
            '16.04' => '/etc/supervisor/conf.d/seat.conf'
        ],
        'centos' => [
            '7' => '/etc/supervisord.d/seat.ini'
        ]
    ];

    /**
     * Install the package needed for supervisor.
     */
    public function install()
    {

        $installer = new PackageInstaller($this->io);
        $installer->installPackageGroup('supervisor');
    }

    /**
     * @param string $user
     */
    public function setUser(string $user)
    {

        $this->user = $user;
    }

    /**
     * @return string
     */
    public function getUser(): string
    {

        return $this->user;
    }

    /**
     * @param string $path
     */
    public function setPath(string $path)
    {

        $this->path = rtrim($path, '/') . '/';
    }

    /**
     * @return mixed
     */
    public function getPath()
    {

        return $this->path;
    }

    /**
     * Setup Supervisor.
     */
    public function setup()
    {

        $this->writeConfig();
        $this->enable();
    }

    /**
     * @return string
     */
    public function getConfigLocation(): string
    {

        $os = $this->getOperatingSystem()['os'];
        $version = $this->getOperatingSystem()['version'];

        return $this->config_locations[$os][$version];
    }

    /**
     * Write the supervisor config to file.
     */
    protected function writeConfig()
    {

        $this->io->text('Writing the SeAT Supervisor configuration file');

        $ini = $this->downloadResourceFile('supervisor-seat.ini');

        // Replace some values in the INI
        $ini = str_replace(':php', $this->findExecutable('php'), $ini);
        $ini = str_replace(':artisan', $this->getPath() . 'artisan', $ini);
        $ini = str_replace(':seatdirectory', $this->getPath(), $ini);
        $ini = str_replace(':webuser', $this->getUser(), $ini);

        // Write the config file
        file_put_contents($this->getConfigLocation(), $ini);

    }

    /**
     * Restart and Enable Supervisor
     */
    public function enable()
    {

        $os = $this->getOperatingSystem()['os'];
        $version = $this->getOperatingSystem()['version'];

        foreach ($this->restart_enable_commands[$os][$version] as $command)
            $this->runCommandWithOutput($command, 'Supervisor Setup');
    }

}
