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
use Seat\Installer\Utils\Abstracts\AbstractUtil;
use Seat\Installer\Utils\Interfaces\WebServer;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class Apache
 * @package Seat\Installer\Utils
 */
class Apache extends AbstractUtil implements WebServer
{

    use DetectsOperatingSystem, DownloadsResources;

    /**
     * @var
     */
    protected $web_root;

    /**
     * @var array
     */
    protected $vhost_locations = [
        'ubuntu' => '/etc/apache2/sites-enabled/',
        'centos' => '/etc/httpd/conf.d/',
    ];

    /**
     * @var array
     */
    protected $webserver_users = [
        'ubuntu' => [
            '16.04' => 'www-data',
        ],
        'centos' => [
            '6' => 'apache',
            '7' => 'apache'
        ]
    ];

    /**
     * Install the binaries for Apache
     */
    public function install()
    {

        $installer = new PackageInstaller($this->io);
        $installer->installPackageGroup('apache');
    }

    /**
     * Configure a SeAT virtual host.
     *
     * @param string $path
     *
     * @return mixed|void
     */
    public function configure(string $path)
    {

        $this->io->text('Writing the Apache Virtual Host configuration');

        // Get the OS that will be used to determine where the config will be
        // written to.
        $os = $this->getOperatingSystem()['os'];
        $version = $this->getOperatingSystem()['version'];

        // Download the config and replace the seatpath
        if ($os == 'ubuntu')
            $vhost = $this->downloadResourceFile('apache-vhost-ubuntu.conf');
        elseif ($os == 'centos')
            $vhost = $this->downloadResourceFile('apache-vhost-centos.conf');

        $vhost = str_replace(':seatpath', '/var/www/html/seat.local', $vhost);

        // Write the vhost config
        file_put_contents($this->vhost_locations[$os] . '100-seat.local.conf', $vhost);

        // Symlink SeAT public directory to the DocumentRoot/public
        $this->io->text('Symlinking SeATs public directory and the Vhost config');

        // Trim the trailing / if there is one.
        $path = rtrim($path, '/');

        // Do the symlink
        $fs = new Filesystem();
        $fs->symlink($path . '/public', '/var/www/html/seat.local');

        // Enable mod_rewrite if this is ubuntu
        if ($os == 'ubuntu') {

            $this->io->text('Enabling mod_rewrite');
            $this->runCommand('a2enmod rewrite');
        }

        // Configure SELinux if this is CentOS
        if ($os == 'centos') {

            $this->io->text('Configuring SELinux');
            $this->runCommand('chcon -R --reference=/var/www ' . $path);
            $this->runCommand('setsebool -P httpd_can_network_connect 1');
            $this->runCommand('setsebool -P httpd_unified 1');
        }

        $this->io->text('Configuring permissions');
        $user = $this->webserver_users[$os][$version];
        $this->runCommand('chown -R ' . $user . ':' . $user . ' ' . $path);
        $this->runCommand('chmod -R guo+w ' . $path . '/storage/');

        // Restart Apache
        $this->io->text('Restarting Apache');
        $this->runCommand('apachectl restart');

    }

    /**
     * Harden the Apache Installation
     */
    public function harden()
    {

        $this->io->text('Hardening Apache');

        $os = $this->getOperatingSystem()['os'];

        if ($os == 'ubuntu')
            $this->hardenUbuntu();
        elseif ($os == 'centos')
            $this->hardenCentos();


    }

    /**
     * Harden the Ubuntu Apache webserver
     */
    protected function hardenUbuntu()
    {

        // Remove Default Website
        $this->io->text('Removing default website');
        $fs = new Filesystem();
        $fs->remove('/etc/apache2/sites-enabled/000-default.conf');

        // Disable Directory Indexing
        $this->io->text('Disabling directory indexing');
        $apache_conf = file_get_contents('/etc/apache2/apache2.conf');
        $apache_conf = str_replace('Options Indexes FollowSymLinks', 'Options FollowSymLinks', $apache_conf);
        file_put_contents('/etc/apache2/apache2.conf', $apache_conf);

        // Remove ServerSignature and ServerTokens
        $this->io->text('Removing server signature and tokens');
        $security_conf = file_get_contents('/etc/apache2/conf-enabled/security.conf');
        $security_conf = str_replace('ServerTokens OS', 'ServerTokens Prod', $security_conf);
        $security_conf = str_replace('ServerSignature On', 'ServerSignature Off', $security_conf);
        file_put_contents('/etc/apache2/conf-enabled/security.conf', $security_conf);

    }

    /**
     * Harden the CentOS Apache Webserver
     */
    protected function hardenCentos()
    {

        // Remove Default Website
        $this->io->text('Removing default website');
        $fs = new Filesystem();
        $fs->remove('/etc/httpd/conf.d/welcome.conf');

        // Disable Directory Indexing
        $this->io->text('Disabling directory indexing');
        $apache_conf = file_get_contents('/etc/httpd/conf/httpd.conf');
        $apache_conf = str_replace('Options Indexes FollowSymLinks', 'Options FollowSymLinks', $apache_conf);
        file_put_contents('/etc/httpd/conf/httpd.conf', $apache_conf);

        // Remove ServerSignature and ServerTokens
        $this->io->text('Removing server signature and tokens');
        $security_conf = file_get_contents('/etc/httpd/conf/httpd.conf');
        $security_conf .= PHP_EOL . 'ServerTokens Prod';
        $security_conf .= PHP_EOL . 'ServerSignature Off';
        file_put_contents('/etc/httpd/conf/httpd.conf', $security_conf);


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
            !array_key_exists($os, $this->webserver_users) ||
            !array_key_exists($ver, $this->webserver_users[$os])
        )
            return false;

        return $this->webserver_users[$os][$ver];
    }
}
