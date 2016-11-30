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

namespace Seat\Installer\Console\Traits;


use Symfony\Component\Filesystem\Filesystem;

/**
 * Class DetectsOperatingSystem
 * @package Seat\Installer\Console\Traits
 */
trait DetectsOperatingSystem
{

    /**
     * @var array
     */
    protected $release_files = [
        'ubuntu' => 'lsb-release',
        'centos' => 'centos-release'
    ];

    /**
     * @var array
     */
    protected $release_versions = [
        'ubuntu' => ['16.04']
    ];

    /**
     * @var array
     */
    protected $os_version = [
        'os'      => null,
        'version' => null
    ];

    /**
     * @var string
     */
    protected $basepath = '/etc';

    /**
     * @return array
     */
    public function getOperatingSystem(): array
    {

        $fs = new Filesystem();

        foreach ($this->release_files as $distro => $file) {

            $filename = $this->basepath . '/' . $file;

            if ($fs->exists($filename)) {

                // Update the distro
                $this->os_version['os'] = $distro;

                // Read the release file to try and determine the version.
                $contents = file_get_contents($filename);

                foreach ($this->release_versions[$distro] as $version) {

                    if (strpos($contents, $version)) {

                        $this->os_version['version'] = $version;

                    }
                }
            }

        }

        return $this->os_version;

    }

}
