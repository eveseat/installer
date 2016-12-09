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


use Seat\Installer\Console\Utils\Interfaces\WebServer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Class Apache
 * @package Seat\Installer\Console\Utils
 */
class Apache implements WebServer
{

    /**
     * Apache constructor.
     *
     * @param \Symfony\Component\Console\Style\SymfonyStyle|null     $io
     * @param \Symfony\Component\Console\Input\InputInterface|null   $input
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output
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
     *
     */
    public function install()
    {

        $installer = new PackageInstaller($this->io);
        $installer->installPackageGroup('apache');

    }

    /**
     *
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
        $process = new Process('a2enmod rewrite');
        $process->run();

        $this->io->text('Restarting Apache');
        $process = new Process('apachectl restart');
        $process->run();


    }

    /**
     *
     */
    public function harden()
    {

        $this->io->text('Hardening Apache');

        $this->io->text('Removing default website');
        $fs = new Filesystem();
        $fs->remove('/etc/apache2/sites-enabled/000-default.conf');

        $this->io->text('Disabling directory indexing');
        $apache_conf = file_get_contents('/etc/apache2/apache2.conf');
        $apache_conf = str_replace('Options Indexes FollowSymLinks', 'Options FollowSymLinks', $apache_conf);
        file_put_contents('/etc/apache2/apache2.conf', $apache_conf);

        $this->io->text('Removing server signature and tokens');
        $security_conf = file_get_contents('/etc/apache2/conf-enabled/security.conf');
        $security_conf = str_replace('ServerTokens OS', 'ServerTokens Prod', $security_conf);
        $security_conf = str_replace('ServerSignature On', 'ServerSignature Off', $security_conf);
        file_put_contents('/etc/apache2/conf-enabled/security.conf', $security_conf);


    }

}
