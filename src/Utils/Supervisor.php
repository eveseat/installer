<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015, 2016, 2017  Leon Jacobs
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

use Seat\Installer\Traits\DetectsOperatingSystem;
use Seat\Installer\Traits\DownloadsResources;
use Seat\Installer\Traits\FindsExecutables;
use Seat\Installer\Traits\GeneratesPasswords;
use Seat\Installer\Utils\Abstracts\AbstractUtil;

/**
 * Class Supervisor.
 * @package Seat\Installer\Utils
 */
class Supervisor extends AbstractUtil
{
    use DetectsOperatingSystem, DownloadsResources, FindsExecutables, GeneratesPasswords;

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
    protected $enable_commands = [
        'ubuntu' => [
            '16.04' => [
                'systemctl enable supervisor.service',
            ],
            '16.10' => [
                'systemctl enable supervisor.service',
            ],
        ],
        'centos' => [
            '7' => [
                'systemctl enable supervisord',
            ],
            '6' => [
                'chkconfig supervisord on',
            ],
        ],
        'debian' => [
            '8' => [
                'systemctl enable supervisor.service',
            ],
        ],
    ];

    /**
     * @var array
     */
    protected $restart_commands = [
        'ubuntu' => [
            '16.04' => [
                'systemctl restart supervisor.service',
            ],
            '16.10' => [
                'systemctl restart supervisor.service',
            ],
        ],
        'centos' => [
            '7' => [
                'systemctl restart supervisord',
            ],
            '6' => [
                '/etc/init.d/supervisord restart',
            ],
        ],
        'debian' => [
            '8' => [
                'systemctl restart supervisor.service',
            ],
        ],
    ];

    /**
     * @var array
     */
    protected $seat_config_locations = [
        'ubuntu' => [
            '16.04' => '/etc/supervisor/conf.d/seat.conf',
            '16.10' => '/etc/supervisor/conf.d/seat.conf',
        ],
        'centos' => [
            '7' => '/etc/supervisord.d/seat.ini',
            '6' => '/etc/supervisord.d/seat.ini',
        ],
        'debian' => [
            '8' => '/etc/supervisor/conf.d/seat.conf',
        ],
    ];

    /**
     * @var array
     */
    protected $supervisor_config_locations = [
        'ubuntu' => [
            '16.04' => '/etc/supervisor/supervisord.conf',
            '16.10' => '/etc/supervisor/supervisord.conf',
        ],
        'centos' => [
            '7' => '/etc/supervisord.conf',
            '6' => '/etc/supervisord.conf',
        ],
        'debian' => [
            '8' => '/etc/supervisor/supervisord.conf',
        ],
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
     * Setup Supervisor.
     */
    public function setup()
    {

        $this->writeConfig();
        $this->enable();
        $this->restart();
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
        file_put_contents($this->getSeatConfigLocation(), $ini);

    }

    /**
     * @return mixed
     */
    public function getPath()
    {

        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath(string $path)
    {

        $this->path = rtrim($path, '/') . '/';
    }

    /**
     * @return string
     */
    public function getUser(): string
    {

        return $this->user;
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
    public function getSeatConfigLocation(): string
    {

        $os = $this->getOperatingSystem()['os'];
        $version = $this->getOperatingSystem()['version'];

        return $this->seat_config_locations[$os][$version];
    }

    /**
     * Enable Supervisor.
     */
    public function enable()
    {

        $os = $this->getOperatingSystem()['os'];
        $version = $this->getOperatingSystem()['version'];

        foreach ($this->enable_commands[$os][$version] as $command)
            $this->runCommandWithOutput($command, 'Supervisor Setup');
    }

    /**
     * Restart Supervisor.
     */
    public function restart()
    {

        $os = $this->getOperatingSystem()['os'];
        $version = $this->getOperatingSystem()['version'];

        foreach ($this->restart_commands[$os][$version] as $command)
            $this->runCommandWithOutput($command, 'Supervisor Setup');
    }

    /**
     * @param string $seat_path
     */
    public function setupIntegration(string $seat_path)
    {

        // Fix up the SeAT path
        $env_path = rtrim($seat_path, '/') . '/.env';

        $this->io->text('Configuring the SeAT / Supervisor integration');

        // Get the configuration block and update it with a password
        $inet_http = $this->downloadResourceFile('supervisor-inet-http-server.conf');
        $password = $this->generatePassword();
        $ini = str_replace(':password', $password, $inet_http);

        // Get the supervisor config and append the new config block to it.
        $supervisor_conf = file_get_contents($this->getSupervisorConfigLocation());
        $supervisor_conf = $supervisor_conf . $ini;

        // Write the new config file
        file_put_contents($this->getSupervisorConfigLocation(), $supervisor_conf);

        // Load up the SeAT env file, download the extra values and set
        // the new password we generated.
        $seat_env = file_get_contents($env_path);
        $new_values = $this->downloadResourceFile('seat-supervisor-env.conf');
        $new_values = str_replace(':password', $password, $new_values);

        // Add the new sections and write the new env.
        $seat_env = $seat_env . $new_values;
        file_put_contents($env_path, $seat_env);

    }

    /**
     * @return string
     */
    public function getSupervisorConfigLocation(): string
    {

        $os = $this->getOperatingSystem()['os'];
        $version = $this->getOperatingSystem()['version'];

        return $this->supervisor_config_locations[$os][$version];
    }
}
