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

namespace Seat\Installer\Commands\Update;

use Humbug\SelfUpdate\Updater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class UpdateSelfCommand.
 * @package Seat\Installer
 */
class SelfCommand extends Command
{
    protected $io;

    /**
     * @var string
     */
    protected $phar_url = 'https://raw.githubusercontent.com/eveseat/installer/master/dist/seat.phar';

    /**
     * @var string
     */
    protected $phar_ver = 'https://raw.githubusercontent.com/eveseat/installer/master/dist/seat.phar.version';

    /**
     * Setup the command.
     */
    protected function configure()
    {

        $this
            ->setName('update:self')
            ->setDescription('Update this SeAT Installer');

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
        $this->io->title('SeAT Installer Self Updater');

        $this->io->text('Checking for a newer version');
        $updater = new Updater(null, false);
        $updater->getStrategy()->setPharUrl($this->phar_url);
        $updater->getStrategy()->setVersionUrl($this->phar_ver);

        // Run hasUpdate. This will update the version number internally.
        $updater->hasUpdate();

        $this->io->text('Remote version: ' . substr($updater->getNewVersion(), 0, 7));

        try {

            $result = $updater->update();

            if ($result) {

                $new = $updater->getNewVersion();
                $old = $updater->getOldVersion();
                $output->writeln('<info>Updated from ' . substr($old, 0, 7) .
                    ' to ' . substr($new, 0, 7) . '!</info>');

            } else {

                $output->writeln('<comment>No update needed!</comment>');

            }

        } catch (\Exception $e) {

            $output->writeln('<comment>An update error occured!</comment>');
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }

    }
}
