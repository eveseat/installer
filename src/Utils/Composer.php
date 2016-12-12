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


use GuzzleHttp\Client;
use Seat\Installer\Exceptions\ComposerInstallException;
use Seat\Installer\Traits\FindsExecutables;
use Seat\Installer\Utils\Abstracts\AbstractUtil;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class Composer
 * @package Seat\Installer\Utils
 */
class Composer extends AbstractUtil
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

        $this->io->text('Checking that PATH is configured correctly');
        $this->checkPath();

        $this->io->text('Checking if composer can now be found.');
        if (!$this->hasComposer())
            throw new ComposerInstallException('Composer could not be found after installation');

        $this->io->success('Composer Installation Complete');

    }

    /**
     * Download Composer Installer
     */
    private function downloadComposer()
    {

        $client = new Client();
        $client->request('get', $this->composer_url, [
            'sink' => $this->temp_filestore
        ]);

    }

    /**
     * Get the latest signature for verification.
     */
    private function getSignature(): string
    {

        // Perform the download
        $client = new Client();
        $response = $client->request('get', $this->composer_sig);

        return trim($response->getBody()->getContents());

    }

    /**
     * Run the Composer Installer.
     *
     * @throws \Seat\Installer\Exceptions\ComposerInstallException
     */
    private function runInstaller()
    {

        // Prepare the installation command.
        $command = $this->findExecutable('php') . ' ' . $this->temp_filestore .
            ' --install-dir=' . $this->executable_path . ' --filename=composer';

        // Run the install.
        $success = $this->runCommandWithOutput($command, 'Composer Installation');

        // Make sure composer installed fine.
        if (!$success)
            throw new ComposerInstallException('Composer installation failed.');

        // Cleanup the tempfile
        $fs = new Filesystem();
        $fs->remove($this->temp_filestore);

    }

    /**
     * Check if the composer installation directory is in
     * the users PATH.
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
     * Check if composer binary can be found.
     *
     * @return bool
     */
    public function hasComposer(): bool
    {

        return $this->hasExecutable('composer');
    }

}
