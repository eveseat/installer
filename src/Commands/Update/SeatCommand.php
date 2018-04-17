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
 * Class SeatCommand.
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
     * Setup the command.
     */
    protected function configure()
    {

        $this
            ->setName('update:seat')
            ->addOption(
                'seat-path', 's', InputOption::VALUE_OPTIONAL,
                'The SeAT path to update. If not specified, an autodetection attempt will be made.',
                null
            )
            ->addOption(
                'ignore-supervisor', 'is', InputOption::VALUE_NONE,
                'Do not restart supervisor'
            )
            ->addOption(
                'ignore-artisan', 'ia', InputOption::VALUE_NONE,
                'Ignore the artisan commands for database seeders, migrations and assets publishing.'
            )
            ->addOption(
                'include-dev', 'id', InputOption::VALUE_NONE,
                'Include development packages when updating composer packages.'
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

        if (! $this->confirmContinue()) {

            $this->io->text('Installer stopped via user cancel.');

            return;
        }

        $this->markSeatOffline();

        $this->checkComposer();
        $this->updatePackages($input->getOption('include-dev'));
        $this->runSeatArtisanCommands();

        if (! $input->getOption('ignore-supervisor'))
            $this->restartWorkers();

        $this->markSeatOnline();

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
        if (! is_null($input->getOption('seat-path'))) {

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
     * @return bool
     */
    protected function confirmContinue()
    {

        $this->io->text('This command will update SeAT on this server ' .
            'with hostname: ' . gethostname());
        $this->io->newline();

        $this->io->text('The following is a short summary of actions that ' .
            'will be performed:');
        $this->io->newline();
        $this->io->listing([
            'Mark SeAT as offline.',
            'Ensure Composer is ready to use.',
            'Update the SeAT packages as well as dependencies.',
            'Run the SeAT asset publisher, databasse migrations and seeders.',
            'Restart the Supervisor workers.',
            'Mark SeAT as online.',
        ]);

        if ($this->io->confirm('Would like to continue with the update?'))
            return true;

        return false;
    }

    /**
     * Mark SeAT as down.
     */
    public function markSeatOffline()
    {

        $seat = new Seat($this->io);
        $seat->setPath($this->seat_path);
        $seat->markApplicationDown();
    }

    /**
     * Ensures that composer is ready to use.
     */
    protected function checkComposer()
    {

        $this->io->text('Checking Composer installation');

        $composer = new Composer($this->io);

        // If we dont have composer, install it.
        if (! $composer->hasComposer())
            $composer->install();
        else
            $composer->update();

    }

    /**
     * Update the Composer packages in the SeAT path.
     *
     * @param $include_dev
     */
    protected function updatePackages($include_dev)
    {

        $composer = new Composer($this->io);
        $composer->updatePackages($this->seat_path, $include_dev);

    }

    /**
     * Run the migrations, seeds, publishers.
     */
    protected function runSeatArtisanCommands()
    {

        $seat = new Seat($this->io);
        $seat->setPath($this->seat_path);
        $seat->runArtisanCommands();
        $seat->updateConfigCache();

    }

    /**
     * Restart the supervisor workers.
     */
    protected function restartWorkers()
    {

        $supervisor = new Supervisor($this->io);
        $supervisor->restart();

    }

    /**
     * Mark SeAT as up.
     */
    public function markSeatOnline()
    {

        $seat = new Seat($this->io);
        $seat->setPath($this->seat_path);
        $seat->markApplicationUp();
    }
}
