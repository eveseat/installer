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

namespace Seat\Installer\Commands;

use Seat\Installer\Exceptions\SeatNotFoundException;
use Seat\Installer\Traits\FindsExecutables;
use Seat\Installer\Traits\FindsSeatInstallations;
use Seat\Installer\Traits\RunsCommands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class Run.
 * @package Seat\Installer\Commands
 */
class Run extends Command
{
    use FindsSeatInstallations, FindsExecutables, RunsCommands;

    /**
     * @var
     */
    protected $io;

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
            ->setName('run')
            ->setDescription('Run a SeAT command')
            ->addOption('seat-path', 's', InputOption::VALUE_OPTIONAL,
                'SeAT folder to run from', null)
            ->addArgument('cmd', InputArgument::REQUIRED, 'The command to execute')
            ->setHelp('This command allows you to run commands from your SeAT installation');
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
        $this->findAndSetSeatPath($input);

        // Prepare the command to run.
        $command = $this->findExecutable('php') . ' ' . $this->seat_path . 'artisan ' .
            $input->getArgument('cmd');

        // Run the command
        $this->runCommandWithOutput($command, '');

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
}
