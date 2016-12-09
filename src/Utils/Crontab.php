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

namespace Seat\Installer\Console\Utils;

use Seat\Installer\Console\Exceptions\CrontabFailedException;
use Seat\Installer\Console\Traits\FindsExecutables;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Class Crontab
 * @package Seat\Installer\Console\Utils
 */
class Crontab
{

    use FindsExecutables;

    /**
     * Crontab constructor.
     *
     * @param \Symfony\Component\Console\Style\SymfonyStyle|null     $io
     * @param \Symfony\Component\Console\Input\InputInterface|null   $input
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output
     */
    public function __construct(
        SymfonyStyle $io = null, InputInterface $input = null, OutputInterface $output = null)
    {

        if ($io)
            $this->io = $io;
        else
            $this->io = new SymfonyStyle($input, $output);

    }

    /**
     * @throws \Seat\Installer\Console\Exceptions\CrontabFailedException
     */
    public function install()
    {

        // Prepare to build a commands array,
        $crontab = $this->findExecutable('crontab');
        $tempfile = tempnam(sys_get_temp_dir(), 'cron');
        $cron = "* * * * * /usr/bin/php /var/www/seat/artisan schedule:run 1>> /dev/null 2>&1";

        // Setup commands for the crontab configuration
        $commands = [
            $crontab . ' -u www-data -l > ' . $tempfile,
            'echo "' . $cron . '" >> ' . $tempfile,
            $crontab . ' -u www-data ' . $tempfile,
        ];

        // Run the commands to configure.
        foreach ($commands as $command) {

            // Prepare and start the installation.
            $this->io->text('Running crontab setup command: ' . $command);
            $process = new Process($command);
            $process->setTimeout(3600);
            $process->run();

            // Make sure crontab command ran fine. Its ok for the `-l` command
            // to fail as this will exit with non zero when no crontab is present.
            if (!$process->isSuccessful() && !strpos($command, '-l'))
                throw new CrontabFailedException('Crontab installation failed.');
        }


    }

}
