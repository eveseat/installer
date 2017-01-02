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
use Seat\Installer\Utils\Abstracts\AbstractUtil;
use Seat\Installer\Utils\Interfaces\WebServer;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class Nginx.
 * @package Seat\Installer\Utils
 */
class Nginx extends AbstractUtil implements WebServer
{
    use DetectsOperatingSystem, DownloadsResources;

    /**
     * @var array
     */
    protected $serverblock_locations = [
        'ubuntu' => '/etc/nginx/sites-enabled/100-seat.conf',
        'centos' => '/etc/nginx/nginx.conf',
        'debian' => '/etc/nginx/sites-enabled/100-seat.conf',
    ];

    /**
     * @var array
     */
    protected $webserver_users = [
        'ubuntu' => [
            '16.04' => 'www-data',
            '16.10' => 'www-data',
        ],
        'centos' => [
            '6' => 'nginx',
            '7' => 'apache',
        ],
        'debian' => [
            '8' => 'www-data',
        ],
    ];

    /**
     * @var array
     */
    protected $fpm_sockets = [
        'ubuntu' => '/var/run/php/php7.0-fpm.sock',
        'centos' => '/var/run/php-fpm/php-fpm.sock',
        'debian' => '/var/run/php/php7.0-fpm.sock',
    ];

    /**
     * @var array
     */
    protected $phpini_locations = [
        'ubuntu' => '/etc/php/7.0/fpm/php.ini',
        'centos' => '/etc/php.ini',
        'debian' => '/etc/php/7.0/fpm/php.ini',
    ];

    /**
     * @var array
     */
    protected $restart_commands = [
        'ubuntu' => [
            '16.04' => [
                'systemctl restart nginx.service',
                'systemctl restart php7.0-fpm.service',
            ],
            '16.10' => [
                'systemctl restart nginx.service',
                'systemctl restart php7.0-fpm.service',
            ],
        ],
        'centos' => [
            '6' => [],
            '7' => [
                'systemctl restart nginx.service',
                'systemctl restart php-fpm.service',
            ],
        ],
        'debian' => [
            '8' => [
                'systemctl restart nginx.service',
                'systemctl restart php7.0-fpm.service',
            ],
        ],
    ];

    /**
     * Install the webserver software.
     *
     * @return mixed
     */
    public function install()
    {

        $installer = new PackageInstaller($this->io);
        $installer->installPackageGroup('nginx');
    }

    /**
     * Configure the webserver to serve SeAT.
     *
     * @param string $path
     *
     * @return mixed
     */
    public function configure(string $path)
    {

        $this->io->text('Writing the Nginx Server block configuration');

        // Get the OS that will be used to determine where the config will be
        // written to.
        $os = $this->getOperatingSystem()['os'];
        $version = $this->getOperatingSystem()['version'];

        // Download the config and replace the seatpath
        if ($os == 'ubuntu')
            $server_block = $this->downloadResourceFile('nginx-server-block-ubuntu.conf');
        elseif ($os == 'debian')
            $server_block = $this->downloadResourceFile('nginx-server-block-ubuntu.conf');
        elseif ($os == 'centos')
            $server_block = $this->downloadResourceFile('nginx-server-block-centos.conf');

        // Set the path and the fpm socket location
        $server_block = str_replace(':seatpath:', rtrim($path, '/'), $server_block);
        $server_block = str_replace('#socket', $this->fpm_sockets[$os], $server_block);

        // Write the serverblock config
        file_put_contents($this->serverblock_locations[$os], $server_block);

        // Remove the default server block if we are on a deb based os
        if ($os == 'ubuntu' || $os == 'debian') {

            $this->io->text('Removing default nginx server block');
            $fs = new Filesystem();
            $fs->remove('/etc/nginx/sites-enabled/default');
        }

        // Configure SELinux if this is CentOS
        if ($os == 'centos') {

            $this->io->text('Configuring SELinux');
            $this->runCommand('chcon -R --reference=/var/www ' . $path);
            $this->runCommand('setsebool -P httpd_can_network_connect 1');
            $this->runCommand('setsebool -P httpd_unified 1');
        }

        // Configure some more things.
        $this->fixPermissions($path);
        $this->fixCgiPath();

        // Restart Nginx
        $this->io->text('Restarting Nginx');
        foreach ($this->restart_commands[$os][$version] as $command)
            $this->runCommandWithOutput($command, 'Nginx Restart');
    }

    /**
     * @param string $path
     */
    protected function fixPermissions(string $path)
    {

        $this->io->text('Configuring permissions');
        $user = $this->getuser();
        $this->runCommand('chown -R ' . $user . ':' . $user . ' ' . $path);
        $this->runCommand('chmod -R guo+w ' . $path . '/storage/');

    }

    /**
     * Get the user as which the webserver will run.
     *
     * @return mixed
     */
    public function getuser()
    {

        $os = $this->getOperatingSystem()['os'];
        $ver = $this->getOperatingSystem()['version'];

        if (
            ! array_key_exists($os, $this->webserver_users) ||
            ! array_key_exists($ver, $this->webserver_users[$os])
        )
            return false;

        return $this->webserver_users[$os][$ver];
    }

    /**
     * Apply the cgi.fix_pathinfo configuration change.
     */
    protected function fixCgiPath()
    {

        $this->io->text('Configuring php-fpm cgi.fix_pathinfo');
        $file = $this->phpini_locations[$this->getOperatingSystem()['os']];
        $php_ini = file_get_contents($file);
        $php_ini = str_replace(';cgi.fix_pathinfo=1', 'cgi.fix_pathinfo=0', $php_ini);
        file_put_contents($file, $php_ini);

    }

    /**
     * Perform security hardening of the webserver.
     *
     * @return mixed
     */
    public function harden()
    {
        // TODO: Implement harden() method.
    }
}
