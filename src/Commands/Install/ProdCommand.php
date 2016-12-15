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

use Seat\Installer\Utils\Apache;
use Seat\Installer\Utils\Composer;
use Seat\Installer\Utils\Crontab;
use Seat\Installer\Utils\MySql;
use Seat\Installer\Utils\OsUpdates;
use Seat\Installer\Utils\PackageInstaller;
use Seat\Installer\Utils\Redis;
use Seat\Installer\Utils\Requirements;
use Seat\Installer\Utils\Seat;
use Seat\Installer\Utils\Supervisor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class InstallProdCommand
 * @package Seat\Installer
 */
class ProdCommand extends Command
{

    /**
     * @var
     */
    protected $io;

    /**
     * @var
     */
    protected $mysql_credentials;

    /**
     * @var
     */
    protected $webserver_choice;

    /**
     * @var
     */
    protected $seat_destination;

    /**
     * @var array
     */
    protected $webserver_info = [
        'apache' => [
            'installer' => Apache::class,
        ]
    ];

    /**
     * Setup the command
     */
    protected function configure()
    {

        $this
            ->setName('install:production')
            ->setDescription('Install a SeAT Production Instance')
            ->addOption('seat-destination', 's', InputOption::VALUE_OPTIONAL,
                'Destination folder to install to', '/var/www/seat')
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
        $this->io->title('SeAT Installer');

        // Start by figuring out where we are going to install SeAT
        $this->seat_destination = $input->getOption('seat-destination');

        // Ensure that we should continue.
        if (!$this->confirmContinue()) {

            $this->io->text('Installer stopped via user cancel.');

            return;
        }

        // Which webserver are we going to use?
        $this->webserver_choice = $this->io->choice('Which webserver do you want to use?', [
            'apache', 'nginx'
        ], 'apache');

        // Prepare the SeAT installation directory
        $this->createInstallDirectory();

        // Process requirements
        if (!$this->checkRequirements())
            return;

        $this->checkComposer();

        $this->updateOs();

        $this->configureMySql();

        $this->configureRedis();

        $this->installPhpPackages();

        $this->installSeat();

        $this->setupSupervisor();

        $this->setupCrontab();

        $this->installWebserver();

        $this->io->success('Installation complete!');
        $this->io->text('Remember to set an admin password with \'php artisan seat:admin:reset\'');

    }

    /**
     * @return bool
     */
    protected function confirmContinue()
    {

        $this->io->text('This installer will install SeAT on this server ' .
            'with hostname: ' . gethostname());
        $this->io->newline();

        $this->io->text('SeAT will be installed at: ' . $this->seat_destination .
            '. If the directory does not exist, it will be created.');
        $this->io->newline();

        $this->io->text('The following is a short summary of actions that ' .
            'will be performed:');
        $this->io->newline();
        $this->io->listing([
            'Check the needed software depedencies.',
            'Ensure Composer is available for use.',
            'Update the Operating System.',
            'Configure / Install MySQL.',
            'Install OS Dependencies.',
            'Install SeAT.',
            'Install & Configure supervisor.',
            'Setup the crontab for SeAT.',
            'Install and configure a Webserver.'
        ]);

        $this->io->text('It may be needed to restart the installer sometimes to continue.');

        if ($this->io->confirm('Would like to continue with the installation?'))
            return true;

        return false;
    }

    /**
     * Creates the installation directory
     */
    protected function createInstallDirectory()
    {

        $fs = new Filesystem();
        $fs->mkdir($this->seat_destination, 0755);
        $this->io->success('Directory ' . $this->seat_destination . ' created.');

    }

    /**
     * @return bool
     */
    protected function checkRequirements(): bool
    {

        // Requirements
        $this->io->text('Checking Requirements');

        $requirements = new Requirements($this->io);

        $requirements->checkSoftwareRequirements();

        // Some PHP dependencies can break this tool. Force
        // a rerun of they are not met.
        if (!$requirements->checkPhpRequirements())
            return false;

        $requirements->checkAccessRequirements();
        $requirements->checkCommandRequirements();

        if (!$requirements->hasAllRequirements())
            return false;

        $this->io->success('Passed requirements check');

        return true;

    }

    /**
     * Ensures that composer is ready to use.
     */
    protected function checkComposer()
    {

        $this->io->text('Checking Composer installation');

        $composer = new Composer($this->io);

        // If we dont have composer, install it.
        if (!$composer->hasComposer())
            $composer->install();
        else
            $composer->update();
    }

    /**
     * Ensure the Operating System is up to date.
     */
    protected function updateOs()
    {

        $this->io->text('Updating Operating System');
        $updates = new OsUpdates($this->io);
        $updates->update();

        $this->io->success('Operating System Update Complete');

    }

    /**
     * Configure MySQL for use.
     */
    protected function configureMySql()
    {

        $mysql = new MySql($this->io);

        // Check if MySQL is already installed. If so, prompt for
        // credentials to use.
        if ($mysql->isInstalled()) {

            $this->io->warning('MySQL appears to already be installed.');
            $this->io->text('Entering mode to get access details for SeAT to use. ' .
                'It is recommended that you create a *new* database and MySQL user ' .
                'for SeAT. The user must have the following MySQL privileges on the ' .
                'SeAT database:');
            $this->io->text('CREATE, LOCK TABLES, INDEX, INSERT, SELECT, UPDATE, DELETE, DROP, ALTER');
            $this->io->text('A user can be created with the following SQL statement:');
            $this->io->text('grant all on seat.* to seat@localhost identified by \'password\';');
            $this->io->newLine();

            $connected = false;

            while (!$connected) {

                $this->io->text('If you are back here after a failed intall run, check ' .
                    'the file at ~/.seat-credentials for the auto-generated seat users password.');

                $this->io->text('Please provide database details:');
                $username = $this->io->ask('Username');
                $password = $this->io->askHidden('Password', function ($input) {

                    return $input;
                });
                $databse = $this->io->ask('Database');

                $mysql->setCredentials([
                    'username' => $username,
                    'password' => $password,
                    'database' => $databse,
                ]);

                $connected = $mysql->testCredentails();

                if (!$connected)
                    $this->io->error('Unable to connect to MySql. Please retry.');

            }

            $this->io->success('Database connected!');

            // Save the credentials that worked
            $mysql->saveCredentials();

            // Get the creds from the mysql Object
            $this->mysql_credentials = $mysql->getCredentials();


        } else {

            // MySQL is not installed. Do the installation.
            $mysql->install();

            // Configuration
            $mysql->configure();

            // And save the credentials.
            $mysql->saveCredentials();

            // Get the creds from the mysql Object
            $this->mysql_credentials = $mysql->getCredentials();

        }


    }

    protected function configureRedis()
    {

        $redis = new Redis($this->io);
        $redis->install();
        $redis->enable();

        $this->io->success('Redis configuration compelte');
    }

    /**
     * Install the OS packages needed for SeAT
     */
    protected function installPhpPackages()
    {

        $installer = new PackageInstaller($this->io);
        $installer->installPackageGroup('php');

    }

    /**
     * Install SeAT
     */
    protected function installSeat()
    {

        $seat = new Seat($this->io);
        $seat->setPath($this->seat_destination);
        $seat->install();
        $seat->configure($this->mysql_credentials);

    }

    /**
     * Setup Supervisor
     */
    protected function setupSupervisor()
    {

        // We need to ask the webserver class which user we need
        // to use for the supervisor job.
        $webserver = $this->webserver_info[$this->webserver_choice]['installer'];
        $webserver = new $webserver($this->io);

        // Setup a Supervisor instance.
        $supervisor = new Supervisor($this->io);
        $supervisor->setUser($webserver->getUser());
        $supervisor->setPath($this->seat_destination);
        $supervisor->install();
        $supervisor->setup();

    }

    /**
     * Setup the Crontab
     */
    protected function setupCrontab()
    {

        // We need to ask the webserver class which user we need
        // to use for the crontab entry.
        $webserver = $this->webserver_info[$this->webserver_choice]['installer'];
        $webserver = new $webserver($this->io);

        $crontab = new Crontab($this->io);
        $crontab->install($this->seat_destination, $webserver->getUser());

    }

    /**
     * Install and configure the chosen webserver.
     */
    protected function installWebserver()
    {

        $installer = $this->webserver_info[$this->webserver_choice]['installer'];
        $installer = new $installer($this->io);
        $installer->install();
        $installer->harden();
        $installer->configure($this->seat_destination);

    }

}
