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

namespace Seat\Installer\Commands\Make;


use GitWrapper\GitWrapper;
use Seat\Installer\Traits\FindsExecutables;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class Package
 * @package Seat\Installer\Commands\Make
 */
class Package extends Command
{

    use FindsExecutables;

    /**
     * @var
     */
    protected $io;

    /**
     * @var string
     */
    protected $repository = 'https://github.com/eveseat/package-example.git';

    /**
     * Setup the command
     */
    protected function configure()
    {

        $this
            ->setName('make:package')
            ->setDescription('Scaffold a blank SeAT package')
            ->addOption('folder', 'f', InputOption::VALUE_OPTIONAL,
                'Folder to dump the scaffold to', 'my-package')
            ->setHelp('This command allows you prepare a new, blank package for SeAT');
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

        $git = new GitWrapper($this->findExecutable('git'));
        $git->setTimeout(300);
        $git->cloneRepository($this->repository, $input->getOption('folder'));

        $this->io->success('Cloned ' . $this->repository . ' to ' . $input->getOption('folder'));

    }

}
