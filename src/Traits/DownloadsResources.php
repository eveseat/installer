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

namespace Seat\Installer\Traits;


use GuzzleHttp\Client;
use Seat\Installer\Exceptions\InvalidResourceException;

/**
 * Class DownloadsResources
 * @package Seat\Installer\Traits
 */
trait DownloadsResources
{

    /**
     * @var string
     */
    protected $base_uri = 'https://raw.githubusercontent.com/eveseat/installer/master/resources/';

    /**
     * @var array
     */
    protected $resources = [
        'mysql_secure_installation.ubuntu.bash',
        'mysql_secure_installation.centos.bash',
        'supervisor-seat.ini',
        'apache-vhost-ubuntu.conf',
        'apache-vhost-centos.conf',
    ];

    /**
     * Download and return a resource file.
     *
     * @param $resource
     *
     * @return string
     * @throws \Seat\Installer\Exceptions\InvalidResourceException
     */
    public function downloadResourceFile($resource)
    {

        // Make sure we got a valid resource
        if (!in_array($resource, $this->resources))
            throw new InvalidResourceException(
                'The resource ' . $resource . ' is not valid');

        // Download and return the resource contents
        return (new Client())->get($this->base_uri . $resource)
            ->getBody()->getContents();
    }

}
