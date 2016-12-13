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

namespace Seat\Installer\Commands\Update;


use Seat\Installer\Exceptions\SeatNotFoundException;
use Seat\Installer\Traits\FindsSeatInstallations;
use Seat\Installer\Utils\Composer;
use Seat\Installer\Utils\Seat;
use Seat\Installer\Utils\Supervisor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class SeatCommand
 * @package Seat\Installer\Commands\Update
 */
class SeatCommand extends Command
{

    use FindsSeatInstallations;

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
            ->setName('update:seat')
            ->addOption(
                'seat-path',
                'p',
                InputOption::VALUE_OPTIONAL,
                'The SeAT path to update. If not specified, an autodetection attempt will be made.',
                null
            )
            ->setDescription('Update a SeAT Installation');

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
        $this->io->title('SeAT Installion Updater');

        // Start by ensuring that the SeAT path is ok.
        $this->findAndSetSeatPath($input);

        // TODO: Ask for update confirmation
        // TODO: Take Application Offline

        $this->checkComposer();
        $this->updatePackages();
        $this->runSeatArtisanCommands();
        $this->restartWorkers();

        // TODO: Bring Application back up

        $this->io->success('SeAT Update Complete!');

    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @throws \Seat\Installer\Exceptions\SeatNotFoundException
     */
    protected function findAndSetSeatPath(InputInterface $input)
    {

        // Check if we have a path to test, or should autodetect.
        if (!is_null($input->getOption('seat-path'))) {

            if ($this->isSeatInstallation($input->getOption('seat-path')))
                $this->seat_path = $input->getOption('seat-path');
            else
                throw new SeatNotFoundException('SeAT could not be found at: ' .
                    $input->getOption('seat-path'));

        } else {

            $this->seat_path = $this->findSeatInstallation();
        }

        $this->io->text('SeAT Path detected at: ' . $this->seat_path);

    }

    /**
     * Ensures that composer is ready to use.
     */
    protected function checkComposer()
    {

        $this->io->text('Checking Composer installation');

        $composer = new Composer($this->io);

        // If we dont have composer, install it.
        if (!$composer->hasComposer())
            $composer->install();
        else
            $composer->update();

    }

    /**
     * Update the Composer packages in the SeAT path.
     */
    protected function updatePackages()
    {

        $composer = new Composer($this->io);
        $composer->updatePackages($this->seat_path);

    }

    /**
     * Run the migrations, seeds, publishers
     */
    protected function runSeatArtisanCommands()
    {

        $seat = new Seat($this->io);
        $seat->setPath($this->seat_path);
        $seat->runArtisanCommands();
    }

    /**
     * Restart the supervisor workers.
     */
    protected function restartWorkers()
    {

        $supervisor = new Supervisor($this->io);
        $supervisor->restartSupervisor();

    }

}
