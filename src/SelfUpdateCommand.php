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

namespace Seat\Installer\Console;

use Humbug\SelfUpdate\Updater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SelfUpdateCommand extends Command
{

    protected $phar_url = 'https://raw.githubusercontent.com/eveseat/installer/master/dist/seat.phar';
    protected $phar_ver = 'https://raw.githubusercontent.com/eveseat/installer/master/dist/seat.phar.version';

    /**
     * Setup the command
     */
    protected function configure()
    {

        $this
            ->setName('selfupdate')
            ->setAliases(['self-update'])
            ->setDescription('Update the SeAT Installer');

    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $updater = new Updater(null, false);
        $updater->getStrategy()->setPharUrl($this->phar_url);
        $updater->getStrategy()->setVersionUrl($this->phar_ver);

        try {

            $result = $updater->update();

            if ($result)
                $output->writeln('<info>Updated!</info>');
            else
                $output->writeln('<comment>No update needed!</comment>');

        } catch (\Exception $e) {

            $output->writeln('<comment>An update error occured!</comment>');
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }

    }

}
