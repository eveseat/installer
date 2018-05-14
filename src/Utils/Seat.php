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

namespace Seat\Installer\Utils;

use Seat\Installer\Exceptions\ArtisanCommandFailed;
use Seat\Installer\Exceptions\SeatDownloadFailedException;
use Seat\Installer\Traits\FindsExecutables;
use Seat\Installer\Utils\Abstracts\AbstractUtil;

/**
 * Class Seat.
 * @package Seat\Installer\Utils
 */
class Seat extends AbstractUtil
{
    use FindsExecutables;

    /**
     * @var
     */
    protected $path;

    /**
     * Install SeAT.
     */
    public function install($minimum_stability)
    {

        $this->io->newLine();
        $this->io->text('Installing SeAT. Go grab a coffee, this may take some time!');
        $this->io->newLine();

        // Prepare the command.
        $command = $this->findExecutable('composer') . ' create-project eveseat/seat ' .
            $this->getPath() . ' --stability ' . $minimum_stability . ' --no-dev --no-ansi --no-progress';

        // Start the installation.
        $success = $this->runCommandWithOutput($command, '');

        // Make sure SeAT installed fine.
        if (! $success)
            throw new SeatDownloadFailedException('SeAT download failed.');
        $this->io->success('SeAT Downloaded OK');

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

        $this->path = rtrim($path, '/') . '/';
    }

    /**
     * @param array $credentials
     */
    public function configure(array $credentials)
    {

        $this->addDbCredentialsToConfig($credentials);
        $this->runArtisanCommands();
        $this->updateSde();

        $this->io->success('SeAT configuration complete');

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
     * @throws \Seat\Installer\Exceptions\PackageInstallationFailedException
     */
    public function runArtisanCommands()
    {

        // Prep the path to php artisan
        $artisan = $this->getArtisan();

        // An array of commands that need to be run in order to setup SeAT
        $commands = [
            $artisan . ' vendor:publish --force --all',
            $artisan . ' migrate',
            $artisan . ' db:seed --class=Seat\\\Notifications\\\database\\\seeds\\\ScheduleSeeder',
            $artisan . ' db:seed --class=Seat\\\Services\\\database\\\seeds\\\NotificationTypesSeeder',
            $artisan . ' db:seed --class=Seat\\\Services\\\database\\\seeds\\\ScheduleSeeder',
        ];

        foreach ($commands as $command) {

            // Run the setup command
            $success = $this->runCommandWithOutput($command, 'SeAT Setup Command');

            // Make sure composer installed fine.
            if (! $success)
                throw new ArtisanCommandFailed('Setup failed.');
        }
    }

    /**
     * Return the artisan command relative to the SeAT path.
     */
    public function getArtisan(): string
    {

        return $this->findExecutable('php') . ' ' . $this->getPath() . 'artisan';
    }

    /**
     * @throws \Seat\Installer\Exceptions\PackageInstallationFailedException
     */
    protected function updateSde()
    {

        // Prep the path to php artisan
        $command = $this->getArtisan() . ' eve:update:sde -n';

        // Run the setup command
        $success = $this->runCommandWithOutput($command, '');

        // Make sure composer installed fine.
        if (! $success)
            throw new ArtisanCommandFailed('SDE Update failed.');
    }

    /**
     * Mark a SeAT instance as Up.
     */
    public function markApplicationUp()
    {

        return $this->setApplicationStatus('up');
    }

    /**
     * Put a SeAT instance into a state.
     *
     * @param $state
     *
     * @throws \Seat\Installer\Exceptions\ArtisanCommandFailed
     */
    public function setApplicationStatus($state)
    {

        $valid_states = ['up', 'down'];

        // Ensure we have a valid state.
        if (! in_array($state, $valid_states))
            throw new ArtisanCommandFailed('Invalid state: ' . $state);
        // Run the command
        $success = $this->runCommandWithOutput($this->getArtisan() . ' ' . $state, '');

        if (! $success)
            throw new ArtisanCommandFailed(
                'Unable to change application state to ' . $state);
    }

    /**
     * Mark a SeAT instance as Down.
     */
    public function markApplicationDown()
    {

        return $this->setApplicationStatus('down');
    }

    /**
     * @throws \Seat\Installer\Exceptions\ArtisanCommandFailed
     */
    public function updateConfigCache()
    {

        $success = $this->runCommandWithOutput($this->getArtisan() .
            ' config:clear');

        if (! $success)
            throw new ArtisanCommandFailed('Unable to update the config cache');
    }

    /**
     * @throws \Seat\Installer\Exceptions\ArtisanCommandFailed
     */
    public function terminateHorizon()
    {

        $success = $this->runCommandWithOutput($this->getArtisan() .
            ' horizon:terminate');

        if (! $success)
            throw new ArtisanCommandFailed('Unable to terminate Horizon');
    }
}
