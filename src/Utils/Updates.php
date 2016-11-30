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


use Seat\Installer\Console\Exceptions\OsUpdateFailedException;
use Seat\Installer\Console\Traits\DetectsOperatingSystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Class Updates
 * @package Seat\Installer\Console\Utils
 */
class Updates
{

    use DetectsOperatingSystem;

    /**
     * @var \Seat\Installer\Console\Utils\SymfonyStyle
     */
    protected $io;

    /**
     * @var array
     */
    protected $update_command = [
        'ubuntu' => 'apt-get update && sudo apt-get upgrade -y',
        'centos' => 'yum update -y',
    ];

    /**
     * Updates constructor.
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
    public function update()
    {

        $os = $this->getOperatingSystem();
        $this->updateOsBasedOnType($os['os']);

    }

    /**
     * @param string $os
     *
     * @throws \Seat\Installer\Console\Exceptions\OsUpdateFailedException
     */
    private function updateOsBasedOnType(string $os)
    {

        // If we are on a debian based system, let apt know we
        // dont want to do anything interactively.
        if ($os == 'ubuntu' || $os == 'debian')
            putenv('DEBIAN_FRONTEND=noninteractive');

        // Prepare the command
        $command = $this->update_command[$os];

        // Prepare and start the installation.
        $this->io->text('Running OS update with: ' . $command);
        $process = new Process($command);
        $process->setTimeout(3600);
        $process->start();

        // Output as it goes
        $process->wait(function ($type, $buffer) {

            // Echo if there is something in the buffer to echo.
            if (strlen($buffer) > 0)
                $this->io->write('OS Update> ' . $buffer);
        });

        // Make sure the update was ok
        if (!$process->isSuccessful())
            throw new OsUpdateFailedException('Failed to update the OS.');

    }

}
