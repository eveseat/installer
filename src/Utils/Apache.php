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


use Seat\Installer\Utils\Abstracts\AbstractUtil;
use Seat\Installer\Utils\Interfaces\WebServer;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class Apache
 * @package Seat\Installer\Utils
 */
class Apache extends AbstractUtil implements WebServer
{

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
     */
    public function configure()
    {

        $this->io->text('Writing the Apache Virtual Host configuration');

        // The default, example Vhost
        $vhost = <<<EOF
<VirtualHost *:80>
    ServerAdmin webmaster@your.domain
    DocumentRoot "/var/www/html/seat.local"
    ServerName seat.local
    ServerAlias www.seat.local
    ErrorLog \${APACHE_LOG_DIR}/seat-error.log
    CustomLog \${APACHE_LOG_DIR}/seat-access.log combined
    <Directory "/var/www/html/seat.local">
        AllowOverride All
        Order allow,deny
        Allow from all
    </Directory>
</VirtualHost>
EOF;

        // Write the Vhost
        file_put_contents('/etc/apache2/sites-available/100-seat.local.conf', $vhost);

        // Symlink SeAT public directory to the DocumentRoot/public
        $this->io->text('Symlinking SeATs public directory and the Vhost config');
        $fs = new Filesystem();
        $fs->symlink('/var/www/seat/public', '/var/www/html/seat.local');

        // Symlink the Vhost config to sites-enabled.
        $fs->symlink(
            '/etc/apache2/sites-available/100-seat.local.conf',
            '/etc/apache2/sites-enabled/100-seat.local.conf');

        // Enable mod_rewrite
        $this->io->text('Enabling mod_rewrite');
        $this->runCommand('a2enmod rewrite');

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

}
