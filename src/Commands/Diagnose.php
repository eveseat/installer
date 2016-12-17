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


use Seat\Installer\Exceptions\SeatNotFoundException;
use Seat\Installer\Traits\DetectsWebserver;
use Seat\Installer\Traits\FindsExecutables;
use Seat\Installer\Traits\FindsSeatInstallations;
use Seat\Installer\Traits\RunsCommands;
use Seat\Installer\Utils\Apache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class Diagnose
 * @package Seat\Installer\Commands
 */
class Diagnose extends Command
{

    use DetectsWebserver, FindsSeatInstallations, FindsExecutables, RunsCommands;

    /**
     * @var SymfonyStyle
     */
    protected $io;


    /**
     * @var
     */
    protected $seat_path;

    /**
     * @var array
     */
    protected $webserver_classes = [
        'apache' => Apache::class,
    ];

    /**
     * Setup the command
     */
    protected function configure()
    {

        $this
            ->setName('diagnose:seat')
            ->setDescription('Diagnose a SeAT Instance')
            ->addOption('seat-path', 's', InputOption::VALUE_OPTIONAL,
                'Destination folder to install to', null)
            ->setHelp('This command allows you to install SeAT on your system');
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
        $this->io->title('SeAT Diagnostics');

        // Start by ensuring that the SeAT path is ok.
        $this->findAndSetSeatPath($input);

        $this->checkPermissions();

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
     * Checks folder permisions to ensure that the webserver
     * user has the required access to do things like write to
     * logfiles etc.
     */
    protected function checkPermissions()
    {

        if (is_null($webserver = $this->getWebserver())) {

            $this->io->warning('Unable to detect the webserver in use. Skipping permissions check.');

            return;
        }

        $this->io->text('Detected webserver in use as: ' . $webserver);
        $webserver = new $this->webserver_classes[$webserver]($this->io);
        $user = $webserver->getUser();
        $this->io->text('User for webserver detected as: ' . $user);

        // Posix info about the user that should own
        // and have permissions to the directories.
        $posix_user_info = posix_getpwnam($user);

        // Check folder access.
        $storage = $this->seat_path . 'storage';
        $this->io->text('Checking path: ' . $storage);
        $storage_permissions = alt_stat($storage);

        // Check the storage folders ownership
        if ($posix_user_info['uid'] != $storage_permissions['owner']['fileowner']) {

            $this->io->text('Webserver User UID: ' . $posix_user_info['uid']);
            $this->io->text('Storage folder owner UID: ' . $storage_permissions['owner']['fileowner']);

            $this->io->error('The storage directory is not owned by the webserver user.');
            $this->io->note('You can try and fix this with: ' .
                $this->findExecutable('chown') . ' -R ' . $user . ':' . $user . ' ' . $storage
            );

        } else {

            $this->io->success('Ownership OK for ' . $storage);
        }

        // Check the storage folders octal permissions
        if ($storage_permissions['perms']['octal1'] != '755') {

            $this->io->text('Permissions for ' . $storage . ': ' .
                $storage_permissions['perms']['octal1'] . ' ( ' .
                $storage_permissions['perms']['human'] . ' )');
            $this->io->error($storage . ' does not have the correct octal permissions.');
            $this->io->note('You can try and fix this with: ' .
                $this->findExecutable('chmod') . ' -R 755 ' . $storage
            );

        } else {

            $this->io->success('Permissions OK for ' . $storage);
        }

    }

}
