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


use Symfony\Component\Process\ExecutableFinder;

/**
 * Class FindsExecutables
 * @package Seat\Installer\Console\Traits
 */
trait FindsExecutables
{

    /**
     * @param array $executables
     *
     * @return array
     */
    public function findExecutables(array $executables)
    {

        foreach ($executables as $exeutable => $path) {

            $path = $this->findExecutable($exeutable);
            $executables[$exeutable] = $path;
        }

        return $executables;
    }

    /**
     * @param string $executable
     *
     * @return string
     */
    private function findExecutable(string $executable)
    {

        $finder = new ExecutableFinder();

        return $finder->find($executable);
    }

    /**
     * @param string $executable
     *
     * @return bool
     */
    public function hasExecutable(string $executable): bool
    {

        if (!is_null($this->findExecutable($executable)))
            return true;

        return false;

    }

}
