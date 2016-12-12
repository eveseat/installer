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

namespace Seat\Installer\Utils;


use Seat\Installer\Exceptions\SupervisorFailedException;
use Seat\Installer\Utils\Abstracts\AbstractUtil;
use Symfony\Component\Process\Process;

/**
 * Class Supervisor
 * @package Seat\Installer\Utils
 */
class Supervisor extends AbstractUtil
{

    /**
     * Setup Supervisor.
     */
    public function setup()
    {

        $this->writeConfig();
        $this->restartSupervisor();
        $this->autoStartSupervisorOnBoot();

    }

    /**
     * Write the supervisor config to file.
     */
    protected function writeConfig()
    {

        $this->io->text('Writing the SeAT Supervisor configuration file');

        $config = <<<EOF
[program:seat]
command=/usr/bin/php /var/www/seat/artisan queue:work --queue=high,medium,low,default --tries 1 --timeout=86100
process_name = %(program_name)s-80%(process_num)02d
stdout_logfile = /var/log/seat-80%(process_num)02d.log
stdout_logfile_maxbytes=100MB
stdout_logfile_backups=10
numprocs=4
directory=/var/www/seat
stopwaitsecs=600
user=www-data
EOF;

        file_put_contents('/etc/supervisor/conf.d/seat.conf', $config);


    }

    /**
     * @throws \Seat\Installer\Exceptions\SupervisorFailedException
     */
    protected function restartSupervisor()
    {

        // Prepare the command.
        $command = 'systemctl restart supervisor.service';

        // Prepare and start the installation.
        $this->io->text('Restarting supervisor with: ' . $command);
        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        // Make sure composer installed fine.
        if (!$process->isSuccessful())
            throw new SupervisorFailedException('Supervisor restart failed.');

    }

    /**
     * @throws \Seat\Installer\Exceptions\SupervisorFailedException
     */
    protected function autoStartSupervisorOnBoot()
    {

        // Prepare the command.
        $command = 'systemctl enable supervisor.service';

        // Prepare and start the installation.
        $this->io->text('Ensuring supervisor autostarts on boot with: ' . $command);
        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        // Make sure composer installed fine.
        if (!$process->isSuccessful())
            throw new SupervisorFailedException('Supervisor autostart setup failed.');
    }

}
