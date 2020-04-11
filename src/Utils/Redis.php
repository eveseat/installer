<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to 2020 Leon Jacobs
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

namespace Seat\Installer\Utils;

use Seat\Installer\Traits\DetectsOperatingSystem;
use Seat\Installer\Utils\Abstracts\AbstractUtil;

/**
 * Class Redis.
 * @package Seat\Installer\Utils
 */
class Redis extends AbstractUtil
{
    use DetectsOperatingSystem;

    /**
     * @var array
     */
    protected $enable_restart_commands = [
        'ubunutu' => [
            '16.04' => [
                'systemctl enable redis-server.service',
                'systemctl restart redis-server.service',
            ],
        ],

        'centos' => [
            '7' => [
                'systemctl enable redis.service',
                'systemctl restart redis.service',
            ],
            '6' => [
                'chkconfig redis on',
                '/etc/init.d/redis restart',
            ],
        ],

        'debian' => [
            '8' => [
                'systemctl enable redis-server.service',
                'systemctl restart redis-server.service',
            ],
            '9' => [
                'systemctl enable redis-server.service',
                'systemctl restart redis-server.service',
            ],
        ],
    ];

    /**
     * Install Redis.
     */
    public function install()
    {

        $installer = new PackageInstaller($this->io);
        $installer->installPackageGroup('redis');

    }

    /**
     * Enable the redis service.
     */
    public function enable()
    {

        $distribution = $this->getOperatingSystem()['os'];
        $version = $this->getOperatingSystem()['version'];

        foreach ($this->enable_restart_commands[$distribution][$version] as $command)
            $this->runCommandWithOutput($command, 'Redis Enable');

    }
}
