<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to 2020 Leon Jacobs
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

use Seat\Installer\Traits\FindsSeatInstallations;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class Where.
 * @package Seat\Installer\Commands
 */
class Where extends Command
{
    use FindsSeatInstallations;

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
            ->setName('where')
            ->setDescription('Shows where is the SeAT directory')
            ->addOption('script', '', InputOption::VALUE_NONE, 'Display the path in a script friendly way')
            ->setHelp('This command allows you to locate your SeAT installation');
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

        // If we should be script friendly, only output the path it found
        if ($input->getOption('script')) {

            echo $this->findSeatInstallation() . PHP_EOL;

            return;
        }

        // Find and print the directory
        $this->io->success('SeAT is at: ' . $this->findSeatInstallation());

    }
}
