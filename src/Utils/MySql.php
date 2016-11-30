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


use Seat\Installer\Console\Traits\FindsExecutables;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Class MySql
 * @package Seat\Installer\Console\Utils
 */
class MySql
{

    use FindsExecutables;

    /**
     * @var array
     */
    protected $credentials = [
        'username' => null,
        'password' => null,
        'database' => null,
    ];

    /**
     * MySql constructor.
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
     * @return bool
     */
    public function isInstalled(): bool
    {

        if ($this->hasExecutable('mysql') && $this->hasExecutable('mysqld'))
            return true;

        return false;

    }

    /**
     * Install MySQL
     */
    public function install()
    {

        $installer = new PackageInstaller($this->io);

        $installer->installPackage('mysql-server');
        $installer->installPackage('expect');

    }

    /**
     * @param array $credentials
     */
    public function setCredentials(array $credentials)
    {

        $this->credentials = $credentials;

    }

    /**
     * TODO: Work out how to get this to work. Maybe use PDO.
     *
     * @return bool
     */
    public function testCredentails()
    {

        $command = 'mysql -u :username -p:password -e ";"';
        $command = str_replace(':username', $this->credentials['username'], $command);
        $command = str_replace(':password', $this->credentials['password'], $command);
        $command = str_replace(':database', $this->credentials['database'], $command);

        $process = new Process($command);
        $process->run();

        return $process->isSuccessful();

    }

}
