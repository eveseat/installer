<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015, 2016, 2017, 2018, 2019  Leon Jacobs
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

namespace Seat\Installer\Utils;

use Seat\Installer\Exceptions\CrontabFailedException;
use Seat\Installer\Traits\FindsExecutables;
use Seat\Installer\Utils\Abstracts\AbstractUtil;

/**
 * Class Crontab.
 * @package Seat\Installer\Utils
 */
class Crontab extends AbstractUtil
{
    use FindsExecutables;

    /**
     * Install the Crontab Entry.
     *
     * @param string $seat_path
     * @param string $user
     *
     * @throws \Seat\Installer\Exceptions\CrontabFailedException
     */
    public function install(string $seat_path, string $user)
    {

        // Prepare the values needed in the cron entry
        $crontab = $this->findExecutable('crontab');
        $php = $this->findExecutable('php');
        $path = rtrim($seat_path, '/');

        // Write the full crontab entry
        $cron = '* * * * * ' . $php . ' ' . $path . '/artisan schedule:run>> /dev/null 2>&1';

        // Get a temp file to dump the current crontab
        $tempfile = tempnam(sys_get_temp_dir(), 'cron');

        // Setup commands for the crontab configuration
        $commands = [
            $crontab . ' -u ' . $user . ' -l > ' . $tempfile,
            'echo "' . $cron . '" >> ' . $tempfile,
            $crontab . ' -u ' . $user . ' ' . $tempfile,
        ];

        // Run the commands to configure.
        foreach ($commands as $command) {

            // Run the command
            $success = $this->runCommand($command);

            // Make sure crontab command ran fine. Its ok for the `-l` command
            // to fail as this will exit with non zero when no crontab is present.
            if (! $success && ! strpos($command, '-l'))
                throw new CrontabFailedException('Crontab installation failed.');
        }

    }
}
