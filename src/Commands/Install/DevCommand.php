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

namespace Seat\Installer\Commands\Install;

use GitWrapper\GitWrapper;
use GuzzleHttp\Client;
use Seat\Installer\Exceptions\ComposerInstallException;
use Seat\Installer\Exceptions\ExecutableNotFoundException;
use Seat\Installer\Exceptions\MissingPhpExtentionExeption;
use Seat\Installer\Exceptions\NonEmptyDirectoryException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Class InstallDevCommand
 * @package Seat\Installer
 */
class DevCommand extends Command
{

    /**
     * @var
     */
    protected $input;

    /**
     * @var
     */
    protected $output;

    /**
     * Array of shell executable locations.
     *
     * @var array
     */
    protected $executables = [
        'git'      => null,
        'unzip'    => null,
        'composer' => null,
        'php'      => null,
    ];

    /**
     * @var null
     */
    protected $install_directory = null;

    /**
     * @var string
     */
    protected $packages_directory = '/packages/eveseat';

    /**
     * The Main Glue
     *
     * @var array
     */
    protected $repositories = [

        'seat' => 'https://github.com/eveseat/seat.git'
    ];

    /**
     * Default SeAT packages.
     *
     * @var array
     */
    protected $packages = [

        'api'           => 'https://github.com/eveseat/api.git',
        'console'       => 'https://github.com/eveseat/console.git',
        'eveapi'        => 'https://github.com/eveseat/eveapi.git',
        'installer'     => 'https://github.com/eveseat/installer.git',
        'notifications' => 'https://github.com/eveseat/notifications.git',
        'services'      => 'https://github.com/eveseat/services.git',
        'web'           => 'https://github.com/eveseat/web.git'
    ];

    /**
     * @var string
     */
    protected $composer_json = 'https://raw.githubusercontent.com/eveseat/scripts/master/development/composer.dev.json';

    /**
     * Setup the command
     */
    protected function configure()
    {

        $this
            ->setName('install:development')
            // Default the installation to seat-development
            ->addOption('seat-destination', 's', InputOption::VALUE_REQUIRED,
                'Destination folder to install to', 'seat-development')
            ->setDescription('Install a SeAT Development Instance')
            ->setHelp(
                'This command allows you to install SeAT on your system, ' .
                'ready to use for development purposes.');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        // Bind the input and output for later use in other methods.
        $this->input = $input;
        $this->output = $output;

        // Ensure that the packages, environment and paths are OK.
        $this->resolve_executables();
        $this->check_php_extensions();
        $this->resolve_paths($input->getOption('destination'));

        // Get the main repo and prep packages dir.
        $output->writeln('<info>Cloning Main SeAT repository to ' . $this->install_directory . '...</info>');
        $this->clone_repository($this->repositories['seat'], $this->install_directory);
        mkdir($this->packages_directory, 0777, true);

        // Clone seperate packages.
        $output->writeln('<info>Cloning Packages...</info>');
        foreach ($this->packages as $name => $repository) {

            $destination = $this->packages_directory . '/' . $name;
            $output->writeln('Processing ' . $name . ' to ' . $destination);
            $this->clone_repository($repository, $destination);
        }

        // Override composer.json with the one ready for dev.
        $output->writeln('<info>Downloading Development composer.json and installing dependencies...</info>');
        $this->install_dependencies();

        // Copy the .env.example file
        $output->writeln('<info>Preparing .env file...</info>');
        if (!file_exists($this->install_directory . '/.env'))
            copy($this->install_directory . '/.env.example', $this->install_directory . '/.env');

        // Enable debug
        $output->writeln('<info>Enabling debug mode...</info>');
        $this->enable_debug_mode();

        // Generate crypto key
        $output->writeln('<info>Generating Encrytion Key...</info>');
        $this->generate_encryption_key();

        $output->writeln('<info>Done! Remember to setup Redis and the DB</info>');

    }

    /**
     * Find executable programs for use in this Command.
     */
    protected function resolve_executables()
    {

        $finder = new ExecutableFinder();

        foreach ($this->executables as $exeutable => $path) {

            $path = $finder->find($exeutable);

            // Make sure we found the executable.
            if (is_null($path))
                throw new ExecutableNotFoundException('Cant find executable for ' . $exeutable);

            $this->output->writeln('Using ' . $path . ' for ' . $exeutable);
            $this->executables[$exeutable] = $path;
        }

        return;
    }

    /**
     * Check that certain PHP extentions are available.
     *
     * @throws \Seat\Installer\Exceptions\MissingPhpExtentionExeption
     */
    protected function check_php_extensions()
    {

        $required_ext = ['intl', 'gd', 'PDO', 'curl', 'mbstring', 'dom', 'xml', 'zip'];

        foreach ($required_ext as $extention)
            if (!extension_loaded($extention))
                throw new MissingPhpExtentionExeption(
                    'PHP Extention ' . $extention . ' not installed.');
    }

    /**
     * Make sure that the install path is not already taken.
     *
     * @param string $path
     *
     * @throws \Seat\Installer\Exceptions\NonEmptyDirectoryException
     */
    protected function resolve_paths(string $path)
    {

        // Extract the pathinfo() for the path
        $path_info = pathinfo($path);

        // If the dirname is the current dir, expand the current working directory
        $base_directory = $path_info['dirname'] == '.' ? getcwd() : $path_info['dirname'];
        $base_name = $path_info['basename'];
        $full_path = $base_directory . '/' . $base_name;

        // Make sure the path does not already exist
        if (file_exists($full_path))
            throw new NonEmptyDirectoryException($path . ' already exists');

        // Set the install path.
        $this->install_directory = $full_path;

        // Update the packages directory with one that is relative to the
        // installat path.
        $this->packages_directory = $this->install_directory .
            $this->packages_directory;

        return;
    }

    /**
     * Clone a git repository to a path.
     *
     * @param string $repository
     * @param string $path
     */
    protected function clone_repository(string $repository, string $path)
    {

        $git = new GitWrapper($this->executables['git']);
        $git->cloneRepository($repository, $path);

        return;

    }

    /**
     * Install composer dependencies by first getting the dev
     * composer.json and then running the install.
     */
    protected function install_dependencies()
    {

        chdir($this->install_directory);

        // Perform the download
        $client = new Client();
        $client->request('GET', $this->composer_json, [
            'sink' => 'composer.json'
        ]);

        // Run the installation
        $process = new Process($this->executables['composer'] .
            ' install --no-ansi --no-progress --no-suggest');
        $process->setTimeout(3600);
        $process->start();

        // Output as it goes
        $process->wait(function ($type, $buffer) {

            // Echo if there is something in the buffer to echo.
            if (strlen($buffer) > 0)
                $this->output->write('COMPOSER> ' . $buffer);
        });

        // Make sure composer installed fine.
        if (!$process->isSuccessful())
            throw new ComposerInstallException('Composer installation failed.');

        return;

    }

    /**
     * Enable the apps debug mode.
     */
    protected function enable_debug_mode()
    {

        $path_to_file = $this->install_directory . '/.env';
        $file_contents = file_get_contents($path_to_file);
        $file_contents = str_replace('APP_DEBUG=false', 'APP_DEBUG=true', $file_contents);
        file_put_contents($path_to_file, $file_contents);

        return;

    }

    /**
     * Generate the apps encryption key.
     */
    protected function generate_encryption_key()
    {

        chdir($this->install_directory);
        $process = new Process($this->executables['php'] . ' artisan key:generate');
        $process->mustRun();

        return;

    }

}
