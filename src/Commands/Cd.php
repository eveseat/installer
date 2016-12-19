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

namespace Seat\Installer\Commands;


use Seat\Installer\Traits\FindsExecutables;
use Seat\Installer\Traits\FindsSeatInstallations;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class Cd
 * @package Seat\Installer\Commands
 */
class Cd extends Command
{

    use FindsExecutables, FindsSeatInstallations;

    /**
     * @var
     */
    protected $io;

    /**
     * @var
     */
    protected $seat_path;

    /**
     * Setup the command
     */
    protected function configure()
    {

        $this
            ->setName('cd')
            ->setDescription('Cd to the SeAT directory')
            ->setHelp('This command allows you to change directories to you SeAT installation');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->io = new SymfonyStyle($input, $output);

        // Prepare the command to run.
        $command = $this->findExecutable('cd') . ' ' . $this->findSeatInstallation();

        // Run the command
        $this->runCommandWithOutput($command, '');

        return;

    }

}
