<?php
/**
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License along
* with this program; if not, write to the Free Software Foundation, Inc.,
* 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
* http://www.gnu.org/copyleft/gpl.html
*
* @file
* @author Akeron
**/

header('Content-Type: text/html; charset=utf-8');


if(strpos(@$_SERVER['HTTP_USER_AGENT'], 'SemrushBot')!==false){
    header($_SERVER["SERVER_PROTOCOL"]." 429 Too Many Requests");
    echo "Error HTTP 429 Too Many Requests";
    exit;
}

include('init_multi.php');
ini_set('memory_limit','64M');
ini_set('max_execution_time','60');
require_once('include/site.php');


$Site=new Site();
$Site->init();
$Site->show();

include('include/end.php');

?>