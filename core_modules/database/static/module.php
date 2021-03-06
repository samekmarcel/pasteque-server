<?php
//    Pastèque Web back office, Static database module
//
//    Copyright (C) 2013 Scil (http://scil.coop)
//
//    This file is part of Pastèque.
//
//    Pastèque is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.
//
//    Pastèque is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with Pastèque.  If not, see <http://www.gnu.org/licenses/>.

namespace StaticDB {
    require_once(dirname(__FILE__) . "/config.php");
    function type() { global $config; return $config['type']; }
    function host() { global $config; return $config['host']; }
    function port() { global $config; return $config['port']; }
    function name() { global $config; return $config['name']; }
    function user() { global $config; return $config['user']; }
    function passwd() { global $config; return $config['password']; }
}

namespace Pasteque {
    function get_db_type($user_id) {
        return \StaticDB\type();
    }
    function get_db_host($user_id) {
        return \StaticDB\host();
    }

    function get_db_port($user_id) {
        return \StaticDB\port();
    }

    function get_db_name($user_id) {
        return \StaticDB\name();
    }

    function get_db_user($user_id) {
        return \StaticDB\user();
    }

    function get_db_password($user_id) {
        return \StaticDB\passwd();
    }
}
?>
