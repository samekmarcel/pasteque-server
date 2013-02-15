<?php
//    Pastèque Web back office
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

namespace Pasteque;

$action = $_GET['action'];
$ret = NULL;

switch ($action) {
case 'get':
    if (!isset($_GET['id'])) {
       $ret	= FALSE;
       break;
    }
    $ret = UsersService::get($_GET['id']);
    break;
case 'getAll':
    $ret = UsersService::getAll();
    break;
}

echo(json_encode($ret));

?>
