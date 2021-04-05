<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to 2021 Leon Jacobs
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

namespace Seat\Installer\Traits;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;
use Seat\Installer\Exceptions\SeatNotFoundException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class FindsSeatInstallations.
 * @package Seat\Installer\Traits
 */
trait FindsSeatInstallations
{
    /**
     * Paths to use for automatic detection.
     *
     * @var array
     */
    protected $possible_path = [
        '/var/www/seat/',
        '/var/www/html/',
        '/var/seat/',
        '/usr/local/nginx/seat/',
        '/srv/http/seat/',
        '/srv/www/seat/',
        '/srv/seat/',
    ];

    /**
     * Files and directories to use as the 'signature'
     * that a SeAT installation exists at the path.
     *
     * @var array
     */
    protected $needed_files = [
        'directories' => [
            'app', 'changelogs', 'database',
        ],
        'files'       => [
            'artisan', 'composer.json', 'server.php',
        ],
    ];

    /**
     * Detect a SeAT installation and return the path where
     * it was found.
     *
     * @return string
     * @throws \Seat\Installer\Exceptions\SeatNotFoundException
     */
    public function findSeatInstallation(): string
    {

        $fs = new Filesystem();

        // First attempt will be to try and read the seat-tool.conf
        // to try and find SeAT.
        try {

            // Load values from the config file into the ENV
            (new Dotenv('/etc', 'seat-tool.conf'))->load();

            $config_path = getenv('SEAT_PATH');

            // Test seat-tool.conf first
            if ($config_path) {

                // Check if the path exists.
                if ($fs->exists($config_path)) {

                    if ($this->testPath($config_path)) {

                        // Looks good!
                        return $config_path;

                    } else {

                        // Warn that the value might be invalid
                        echo 'SeAT path in /etc/seat-tool.conf appears invalid!';
                    }

                } else {

                    echo 'SeAT path /etc/seat-tool.conf does not exist.';
                }
            }

        } catch (InvalidPathException $_) {
            // Ignore this exception. The config file is not readable or
            // not present.
        }

        // Config not set, or SEAT_PATH is not valid. Enumerate the filesystem
        // to try and find SeAT.
        foreach ($this->possible_path as $path) {

            // If the path does not exist, well just continue then
            if (! $fs->exists($path))
                continue;

            if ($this->testPath($path))
                return $path;

        }

        throw new SeatNotFoundException(
            'Unable to locate SeAT installation. You may have to specify it.');

    }

    /**
     * Check if a path is a SeAT installation.
     *
     * @param string $path
     *
     * @return bool
     */
    public function isSeatInstallation(string $path)
    {

        if ($this->testPath($path))
            return true;

        return false;

    }

    /**
     * Test a path to check if the 'signatures'
     * required for it to be considered a SeAT
     * installation match.
     *
     * @param string $path
     *
     * @return bool
     */
    private function testPath(string $path): bool
    {

        $fs = new Filesystem();

        // Prefix the directories with the path
        $directories = array_map(function ($value) use ($path) {

            return $path . $value;

        }, $this->needed_files['directories']);

        // Prefix files with the path
        $files = array_map(function ($value) use ($path) {

            return $path . $value;

        }, $this->needed_files['files']);

        // If both the files and directories exist, then we can
        // say this might be a SeAT installation
        if ($fs->exists($directories) && $fs->exists($files))
            return true;

        // Path is not valid. Return false.
        return false;

    }
}
