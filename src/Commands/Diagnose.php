<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015, 2016, 2017  Leon Jacobs
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

use Dotenv\Dotenv;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use Predis\Client as RedisClient;
use Seat\Installer\Exceptions\SeatNotFoundException;
use Seat\Installer\Traits\DetectsWebserver;
use Seat\Installer\Traits\FindsExecutables;
use Seat\Installer\Traits\FindsSeatInstallations;
use Seat\Installer\Traits\RunsCommands;
use Seat\Installer\Utils\Apache;
use Seat\Installer\Utils\MySql;
use Seat\Installer\Utils\Requirements;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * Class Diagnose.
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
     * Setup the command.
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

        // Ensure that we have all the min requirements
        if (! $this->checkRequirements())
            return;

        // Start by ensuring that the SeAT path is ok.
        $this->findAndSetSeatPath($input);

        // Continue by running the diagnostics methods
        $this->checkPhpExtentions();
        $this->checkSeatConfiguration();
        $this->checkPermissions();
        $this->checkRedis();
        $this->checkMysql();
        $this->checkNetwork();

    }

    /**
     * Check some software requirements.
     *
     * @return bool
     */
    protected function checkRequirements(): bool
    {

        $this->io->text('Checking minumim software requirements');

        $check_ok = true;

        // Ensure that we are on a supported operating system
        $requirements = new Requirements($this->io);
        if (! $requirements->hasSupportedOs()) {

            $this->io->error('Sorry, this operating system is not yet supported.');
            $check_ok = false;
        }

        if (! $requirements->hasMinimumPhpVersion()) {

            $this->io->error('At least PHP7 is required for SeAT. Your version: ' . PHP_VERSION);
            $check_ok = false;
        }

        return $check_ok;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @throws \Seat\Installer\Exceptions\SeatNotFoundException
     */
    protected function findAndSetSeatPath(InputInterface $input)
    {

        $this->io->text('Locating SeAT');

        // Check if we have a path to test, or should autodetect.
        if (! is_null($input->getOption('seat-path'))) {

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
     * Check that the required PHP extentions are loaded.
     */
    protected function checkPhpExtentions()
    {

        $this->io->text('Checking PHP extentions');

        $check_ok = true;

        $extentions = [
            'mcrypt', 'intl', 'gd', 'PDO', 'curl', 'mbstring', 'dom',
        ];

        foreach ($extentions as $extention) {

            if (! extension_loaded($extention)) {

                $this->io->error('PHP Extention ' . $extention . ' not loaded.');
                $check_ok = false;

            }
        }

        if ($check_ok)
            $this->io->success('PHP Extention check passed');

    }

    /**
     * Get the SeAT configuration from its .env.
     */
    protected function checkSeatConfiguration()
    {

        $this->io->text('Checking SeAT configuration file');
        $config_ok = true;

        $env = new Dotenv($this->seat_path);
        $env->load();

        $required_values = [
            'APP_KEY', 'DB_CONNECTION', 'DB_HOST', 'DB_DATABASE', 'CACHE_DRIVER',
            'QUEUE_DRIVER', 'REDIS_HOST', 'MAIL_DRIVER',
        ];

        foreach ($required_values as $value) {

            if (! getenv($value)) {

                $this->io->error('The value for ' . $value . ' must be set in the ' .
                    'SeAT `.env` file.');
                $config_ok = false;
            }

        }

        if (getenv('APP_DEBUG') != 'false')
            $this->io->warning('SeAT is in DEBUG mode. This is dangerous as errors can be ' .
                'very verbose and reveal sensitive information.');

        if ($config_ok)
            $this->io->success('Configuration check passed');

    }

    /**
     * Checks folder permisions to ensure that the webserver
     * user has the required access to do things like write to
     * logfiles etc.
     */
    protected function checkPermissions()
    {

        $this->io->text('Checking filesystem permissions');

        if (is_null($webserver = $this->getWebserver())) {

            $this->io->warning('Unable to detect the webserver in use. Skipping permissions check.');

            return;
        }

        $this->io->text('Detected webserver in use as: ' . $webserver);
        $webserver = new $this->webserver_classes[$webserver]($this->io);
        if (! $user = $webserver->getUser()) {

            $this->io->warning('Unable to determine webserver user for your OS.' .
                'Skipping permissions check');

            return;
        }
        $this->io->text('User for webserver detected as: ' . $user);

        // Posix info about the user that should own
        // and have permissions to the directories.
        $posix_user_info = posix_getpwnam($user);
        $this->io->text('Webserver User UID: ' . $posix_user_info['uid']);

        // Check folder access.
        $storage = $this->seat_path . 'storage';
        $this->io->text('Checking path: ' . $storage);
        if ($this->checkFileOwnership($storage, $user))
            $this->io->success('Permission and ownership check for ' . $storage . ' passed');

        // Check that logfiles are also ok.
        $finder = new Finder();
        $files_ok = true;
        foreach ($finder->files()->name('*.log')->in($storage) as $file) {

            if (! $this->checkFileOwnership($file, $user))
                $files_ok = false;
        }

        if ($files_ok)
            $this->io->success('Files and folders in ' . $storage . ' check passed');

    }

    /**
     * @param string $directory
     * @param string $required_user
     * @param string $required_octal
     *
     * @return bool
     */
    private function checkFileOwnership(
        string $directory, string $required_user, string $required_octal = '755'): bool
    {

        $check_ok = true;

        // Get the directory permissions.
        $storage_permissions = alt_stat($directory);

        // Get the unix permissions info for the user.
        $posix_user_info = posix_getpwnam($required_user);

        // Compare the permissions of the user, to that of the file owner
        if ($posix_user_info['uid'] != $storage_permissions['owner']['fileowner']) {

            $this->io->error('The directory ' . $directory . ' is not owned by ' . $required_user);
            $this->io->note('You can try and fix this with: ' .
                $this->findExecutable('chown') . ' -R ' .
                $required_user . ':' . $required_user . ' ' . $directory
            );

            $check_ok = false;

        }

        // Check the storage folders octal permissions
        if ($storage_permissions['perms']['octal1'] != $required_octal) {

            $this->io->text('Permissions for ' . $directory . ': ' .
                $storage_permissions['perms']['octal1'] . ' ( ' .
                $storage_permissions['perms']['human'] . ' )');
            $this->io->error($directory . ' does not have the correct octal permissions.');
            $this->io->note('You can try and fix this with: ' .
                $this->findExecutable('chmod') . ' -R ' . $required_octal . ' ' . $directory
            );

            $check_ok = false;

        }

        return $check_ok;

    }

    /**
     * Check Redis connectivity.
     */
    public function checkRedis()
    {

        $this->io->text('Checking Redis status');

        $host = getenv('REDIS_HOST');
        $port = getenv('REDIS_PORT');

        if (! $host || ! $port) {

            $this->io->warning('The SeAT configuration does not have valid Redis settings. ' .
                'Going to try with defaults.');

            $host = '127.0.0.1';
            $port = 6379;
        }

        try {

            $redis = new RedisClient([
                'scheme' => 'tcp',
                'host'   => $host,
                'port'   => $port,
            ]);

            $redis->set('seat_diagnostics', true);

            if ($redis->get('seat_diagnostics'))
                return $this->io->success('Redis check passed');

        } catch (Exception $e) {

            return $this->io->error('Redis check failed with: ' . $e->getMessage());

        }

        return $this->io->error('Redis check failed. Unable to get diagnostics testing value.');

    }

    /**
     * Test a MySQL connection.
     */
    public function checkMysql()
    {

        $this->io->text('Testing MySQL authentication and connectivity');

        $username = getenv('DB_USERNAME');
        $password = getenv('DB_PASSWORD');
        $database = getenv('DB_DATABASE');

        if (! $username || ! $database) {

            $this->io->warning('SeAT configuration for the database appears incomplete. ' .
                'Going to try with some defaults.');

            $username = $database = 'seat';
        }

        $mysql = new MySql($this->io);
        $mysql->setCredentials([
            'username' => $username,
            'password' => $password,
            'database' => $database,
        ]);

        if ($mysql->testCredentails())
            return $this->io->success('MySQL connectivity check passed');

        return $this->io->error('Unable to succesfully connect and authenticate to the databse');

    }

    /**
     * Check the network connectivity to the EVE Online API Server.
     */
    public function checkNetwork()
    {

        $this->io->text('Checking connectivity to the EVE Online API server');

        $url = 'https://api.eveonline.com/server/serverstatus.xml.aspx';

        $client = new GuzzleClient();
        $response = $client->get($url);

        if ($response->getStatusCode() == 200)
            return $this->io->success('Connectivity check to EVE Online API server passed');

        return $this->io->error('Failed connectivity check to EVE Online API server');

    }
}
