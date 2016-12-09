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


use Seat\Installer\Console\Exceptions\PackageInstallationFailedException;
use Seat\Installer\Console\Exceptions\SeatDownloadFailedException;
use Seat\Installer\Console\Traits\FindsExecutables;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Class Seat
 * @package Seat\Installer\Console\Utils
 */
class Seat
{

    use FindsExecutables;

    /**
     * @var
     */
    protected $path;

    /**
     * Seat constructor.
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
     * Install SeAT
     */
    public function install()
    {

        $this->download();
        $this->setPermissions();

    }

    /**
     * @throws \Seat\Installer\Console\Exceptions\SeatDownloadFailedException
     */
    protected function download()
    {

        $this->io->newLine();
        $this->io->text('Installing SeAT. Go grab a coffee, this may take some time!');
        $this->io->newLine();

        // Prepare the command.
        $command = $this->findExecutable('composer') . ' create-project eveseat/seat ' .
            $this->getPath() . ' --no-dev --no-ansi --no-progress';

        // Prepare and start the installation.
        $this->io->text('Running SeAT installation with: ' . $command);
        $process = new Process($command);
        $process->setTimeout(3600);
        $process->start();

        // Output as it goes
        $process->wait(function ($type, $buffer) {

            // Echo if there is something in the buffer to echo.
            if (strlen($buffer) > 0)
                $this->io->write('SeAT Installation> ' . $buffer);
        });

        // Make sure composer installed fine.
        if (!$process->isSuccessful())
            throw new SeatDownloadFailedException('SeAT download failed.');

    }

    /**
     * @return mixed
     */
    public function getPath(): string
    {

        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath(string $path)
    {

        $this->path = $path;
    }

    /**
     * Configure filesystem permissions.
     */
    protected function setPermissions()
    {

        $this->io->text('Fixing up filesystem permissions for SeAT');

        $fs = new Filesystem();
        $fs->chown($this->getPath(), 'www-data', true);
        $fs->chmod($this->getPath() . '/storage', 0755, 0000, true);


    }

    /**
     * @param array $credentials
     */
    public function configure(array $credentials)
    {

        $this->addDbCredentialsToConfig($credentials);
        $this->runArtisanCommands();

    }

    /**
     * @param array $credentials
     */
    protected function addDbCredentialsToConfig(array $credentials)
    {

        // TODO: Validate that we have the needed keys.
        $config_location = $this->getPath() . '/.env';

        // Read the config file.
        $config_file = file_get_contents($config_location);

        // Replace the databse credentials.
        $config_file = str_replace('DB_DATABASE=seat', 'DB_DATABASE=' . $credentials['database'], $config_file);
        $config_file = str_replace('DB_USERNAME=seat', 'DB_USERNAME=' . $credentials['username'], $config_file);
        $config_file = str_replace('DB_PASSWORD=secret', 'DB_PASSWORD=' . $credentials['password'], $config_file);

        // Write the config file back.
        file_put_contents($config_location, $config_file);

    }

    /**
     * @throws \Seat\Installer\Console\Exceptions\PackageInstallationFailedException
     */
    protected function runArtisanCommands()
    {

        // Prep the path to php artisan
        $artisan = $this->findExecutable('php') . ' ' . $this->getPath() . '/artisan';

        $commands = [
            $artisan . ' vendor:publish',
            $artisan . ' migrate',
            $artisan . ' db:seed --class=Seat\\\Notifications\\\database\\\seeds\\\ScheduleSeeder',
            $artisan . ' db:seed --class=Seat\\\Services\\\database\\\seeds\\\NotificationTypesSeeder',
            $artisan . ' db:seed --class=Seat\\\Services\\\database\\\seeds\\\ScheduleSeeder',
            $artisan . ' eve:update-sde -n',
        ];

        foreach ($commands as $command) {

            // Prepare and start the installation.
            $this->io->text('Running SeAT command with: ' . $command);
            $process = new Process($command);
            $process->setTimeout(3600);
            $process->start();

            // Output as it goes
            $process->wait(function ($type, $buffer) {

                // Echo if there is something in the buffer to echo.
                if (strlen($buffer) > 0)
                    $this->io->write('SeAT Setup Command> ' . $buffer);
            });

            // Make sure composer installed fine.
            if (!$process->isSuccessful())
                throw new PackageInstallationFailedException('Setup failed.');

        }
    }


}
