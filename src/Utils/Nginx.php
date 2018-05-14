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
            '18.04' => 'www-data',
        ],
        'centos' => [
            '6' => 'nginx',
            '7' => 'nginx',
        ],
        'debian' => [
            '8' => 'www-data',
            '9' => 'www-data',
        ],
    ];

    /**
     * @var array
     */
    protected $fpm_sockets = [
        'ubuntu' => [
            '16.04' => '/var/run/php/php7.1-fpm.sock',
            '18.04' => '/var/run/php/php7.1-fpm.sock',
        ],
        'centos' => [
            '6' => '/var/run/php-fpm/php-fpm.sock',
            '7' => '/var/run/php-fpm/php-fpm.sock',
        ],
        'debian' => [
            '8' => '/var/run/php/php7.1-fpm.sock',
            '9' => '/var/run/php/php7.1-fpm.sock',
        ],
    ];

    /**
     * @var array
     */
    protected $phpini_locations = [
        'ubuntu' => [
            '16.04' => '/etc/php/7.1/fpm/php.ini',
            '18.04' => '/etc/php/7.1/fpm/php.ini',
        ],
        'centos' => [
            '6' => '/etc/php.ini',
            '7' => '/etc/php.ini',
        ],
        'debian' => [
            '8' => '/etc/php/7.1/fpm/php.ini',
            '9' => '/etc/php/7.1/fpm/php.ini',
        ],
    ];

    /**
     * @var array
     */
    protected $restart_commands = [
        'ubuntu' => [
            '16.04' => [
                'systemctl restart nginx.service',
                'systemctl restart php7.1-fpm.service',
            ],
            '18.04' => [
                'systemctl restart nginx.service',
                'systemctl restart php7.1-fpm.service',
            ],
        ],
        'centos' => [
            '6' => [
                '/etc/init.d/nginx restart',
                '/etc/init.d/php-fpm restart',
            ],
            '7' => [
                'systemctl restart nginx.service',
                'systemctl restart php-fpm.service',
            ],
        ],
        'debian' => [
            '8' => [
                'systemctl restart nginx.service',
                'systemctl restart php7.1-fpm.service',
            ],
            '9' => [
                'systemctl restart nginx.service',
                'systemctl restart php7.1-fpm.service',
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
        $server_block = str_replace('#socket', $this->fpm_sockets[$os][$version], $server_block);

        // Write the serverblock config
        file_put_contents($this->serverblock_locations[$os], $server_block);

        // Remove the default server block if we are on a deb based os
        if ($os == 'ubuntu' || $os == 'debian') {
            $this->io->text('Removing default nginx server block');
            $fs = new Filesystem();
            $fs->remove('/etc/nginx/sites-enabled/default');
        }

        // Configure SELinux & Fix FPM User if this is CentOS
        if ($os == 'centos') {
            $this->io->text('Configuring SELinux');
            $this->runCommand('chcon -R --reference=/var/www ' . $path);
            $this->runCommand('setsebool -P httpd_can_network_connect 1');
            $this->runCommand('setsebool -P httpd_unified 1');
            $this->fixFpmUser();
        }

        if ($os == 'centos' && $version == '6') {
            $this->io->text('Removing default nginx server block');
            $fs = new Filesystem();
            $fs->remove('/etc/nginx/conf.d/default.conf');
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
        $os = $this->getOperatingSystem()['os'];
        $version = $this->getOperatingSystem()['version'];

        $this->io->text('Configuring php-fpm cgi.fix_pathinfo');
        $file = $this->phpini_locations[$os][$version];
        $php_ini = file_get_contents($file);
        $php_ini = str_replace(';cgi.fix_pathinfo=1', 'cgi.fix_pathinfo=0', $php_ini);
        file_put_contents($file, $php_ini);
    }

    /**
     * Apply the FPM user fix.
     */
    protected function fixFpmUser()
    {
        $this->io->text('Updating PHP-FPM user');
        $fpm_file = '/etc/php-fpm.d/www.conf';
        $phpfpm_ini = file_get_contents($fpm_file);
        $phpfpm_ini = str_replace('user = apache', 'user = nginx', $phpfpm_ini);
        $phpfpm_ini = str_replace('group = apache', 'group = nginx', $phpfpm_ini);
        file_put_contents($fpm_file, $phpfpm_ini);
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
