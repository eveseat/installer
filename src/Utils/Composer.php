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


use GuzzleHttp\Client;
use Seat\Installer\Console\Exceptions\ComposerInstallException;
use Seat\Installer\Console\Traits\FindsExecutables;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Class Composer
 * @package Seat\Installer\Console\Utils
 */
class Composer
{

    use FindsExecutables;

    /**
     * @var string
     */
    protected $composer_sig = 'https://composer.github.io/installer.sig';

    /**
     * @var string
     */
    protected $composer_url = 'https://getcomposer.org/installer';

    /**
     * @var string
     */
    protected $temp_filestore = '/tmp/composer';

    /**
     * @var string
     */
    protected $executable_path = '/usr/local/bin';

    /**
     * Composer constructor.
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
     * Install composer.
     */
    public function install()
    {

        // Download composer
        $this->io->text('Downloading Composer from ' . $this->composer_url);
        $this->downloadComposer();

        // Get the signature that we should expect.
        $this->io->text('Downloading and verifying signatures');
        $signature = $this->getSignature();
        // Hash the downloaded file to match against.
        $downloaded_signature = hash_file('SHA384', $this->temp_filestore);

        // Check the signatures if the downloaded file and the sig file.
        if ($signature != $downloaded_signature)
            throw new ComposerInstallException('Signature mismatch');

        $this->io->text('Running Composer Installer');
        $this->runInstaller();

        $this->io->text('Moving the installed executable');
        $this->moveExecutable();

        $this->io->text('Checking that PATH is configured correctly');
        $this->checkPath();

        $this->io->text('Checking if composer can now be found.');
        if (!$this->hasComposer())
            throw new ComposerInstallException('Composer could not be found after installation');

        $this->io->success('Composer Installation Complete');

    }

    /**
     *
     */
    private function downloadComposer()
    {

        $client = new Client();
        $client->request('get', $this->composer_url, [
            'sink' => $this->temp_filestore
        ]);

    }

    /**
     *
     */
    private function getSignature(): string
    {

        // Perform the download
        $client = new Client();
        $response = $client->request('get', $this->composer_sig);

        return trim($response->getBody()->getContents());

    }

    /**
     * @throws \Seat\Installer\Console\Exceptions\ComposerInstallException
     */
    private function runInstaller()
    {

        // Prepare and start the installation.
        $process = new Process('php ' . $this->temp_filestore);
        $process->setTimeout(3600);
        $process->start();

        // Output as it goes
        $process->wait(function ($type, $buffer) {

            // Echo if there is something in the buffer to echo.
            if (strlen($buffer) > 0)
                $this->io->write('Composer Installation> ' . $buffer);
        });

        // Make sure composer installed fine.
        if (!$process->isSuccessful())
            throw new ComposerInstallException('Composer installation failed.');

    }

    /**
     *
     */
    private function moveExecutable()
    {

        $fs = new Filesystem();
        $fs->copy('composer.phar', $this->executable_path . '/composer');

        // Cleanup while we at it.
        $fs->remove([
            'composer.phar', $this->temp_filestore
        ]);

    }

    /**
     *
     */
    private function checkPath()
    {

        $path = getenv('PATH');

        if (!strpos($path, $this->executable_path))
            $this->io->warning('Installation path ' . $this->executable_path . ' is not ' .
                'in the PATH environment variable. This may cause tools to fail when ' .
                'they need to use composer.');

        $this->io->text('PATH containts the installation path of ' . $this->executable_path);
    }

    /**
     * @return bool
     */
    public function hasComposer(): bool
    {

        return $this->hasExecutable('composer');
    }

}
