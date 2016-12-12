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


use Seat\Installer\Exceptions\OsUpdateFailedException;
use Seat\Installer\Traits\DetectsOperatingSystem;
use Seat\Installer\Utils\Abstracts\AbstractUtil;

/**
 * Class Updates
 * @package Seat\Installer\Utils
 */
class Updates extends AbstractUtil
{

    use DetectsOperatingSystem;

    /**
     * @var array
     */
    protected $update_command = [
        'ubuntu' => 'apt-get update && apt-get upgrade -y',
        'centos' => 'yum update -y',
    ];

    /**
     * Update the Operating System
     */
    public function update()
    {

        $os = $this->getOperatingSystem();
        $this->updateOsBasedOnType($os['os']);

    }

    /**
     * @param string $os
     *
     * @throws \Seat\Installer\Exceptions\OsUpdateFailedException
     */
    private function updateOsBasedOnType(string $os)
    {

        // If we are on a debian based system, let apt know we
        // dont want to do anything interactively.
        if ($os == 'ubuntu' || $os == 'debian')
            putenv('DEBIAN_FRONTEND=noninteractive');

        // Prepare the command
        $command = $this->update_command[$os];

        // Start the installation.
        $success = $this->runCommandWithOutput($command, 'OS Update');

        // Make sure the update was ok
        if (!$success)
            throw new OsUpdateFailedException('Failed to update the OS.');

    }

}
