<?php
/*
    GOGGames
    Copyright (C) 2018  GoodOldDownloads

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

try {
    if ($CONFIG['DEV']) {
        // Use Memcache on windows cause i'm (still) too lazy to set up a VM for this project
        if (PHP_OS === "WINNT") {
            class MemcacheWrap extends Memcache {
                public function set($key, $val, $expire = 0) {
                    return parent::set($key, $val, 0, $expire);
                }
            }
            $Memcached = new MemcacheWrap();
            $Memcached->connect($CONFIG["MEMCACHED"]["SERVER"], $CONFIG["MEMCACHED"]["PORT"]);
        } else {
            $Memcached = new Memcached();
            $Memcached->addServer($CONFIG["MEMCACHED"]["SERVER"], $CONFIG["MEMCACHED"]["PORT"]);
        }
    } else {
        $Memcached = new Memcached();
        $Memcached->addServer($CONFIG["MEMCACHED"]["SERVER"], $CONFIG["MEMCACHED"]["PORT"]);
    }
} catch (Exception $e) {
    echo "Shit's fuxked yoo";
    die;
} catch (Error $e) {
    echo "Shit's fuxked yoooooo";
    die;
}