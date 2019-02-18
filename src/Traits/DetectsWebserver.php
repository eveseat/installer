<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015, 2016, 2017, 2018, 2019  Leon Jacobs
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

use Symfony\Component\Filesystem\Filesystem;

/**
 * Class DetectsWebserver.
 * @package Seat\Installer\Traits
 */
trait DetectsWebserver
{
    /**
     * @var array
     */
    protected $webservers = [

        'apache' => [
            '/etc/apache2/apache2.conf',
            '/etc/httpd/conf/httpd.conf',
        ],
        'nginx'  => [

        ],
    ];

    /**
     * @return int|string|void
     */
    public function getWebserver()
    {

        $fs = new Filesystem();

        foreach ($this->webservers as $webserver => $files) {

            // Loop over the files and check for existence
            foreach ($files as $possible_file) {

                if ($fs->exists($possible_file))
                    return $webserver;
            }
        }

    }
}
