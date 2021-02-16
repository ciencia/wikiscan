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
error_reporting(E_ALL);
if(isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST']=='wikiscan.org' || $_SERVER['HTTP_HOST']=='wikiscan')){
    header('Status: 301 Moved Permanently', false, 301);
    $http=isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ? 'https' : 'http';
    header("Location: $http://fr.".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
    exit;
}
if(strpos(@$_SERVER['HTTP_USER_AGENT'], 'YandexBot')!==false){
    header($_SERVER["SERVER_PROTOCOL"]." 429 Too Many Requests");
    echo "Error HTTP 429 Too Many Requests";
    exit;
}
include('init_multi.php');
require_once('include/graph.php');
ini_set('memory_limit','256M');
ini_set('max_execution_time',60);
define('Nerf',false);

if(isset($_GET['type']) && (isset($_GET['user'])
        || substr($_GET['type'],0,7)=='months_'
        || substr($_GET['type'],0,6)=='years_'
        || (isset($_GET['date'])&& ($date=Dates::valid_date($_GET['date']))!==false))){
    $type=$_GET['type'];
    $graph=new WsGraph($type);
    if(isset($_GET['date'])){
        $filename="$date-$type.png";
        if($date==0){
            ini_set('memory_limit','3000M');
            ini_set('max_execution_time',1800);
            $filename="all-$type.png";
        }elseif(strlen($date)==4){
            ini_set('memory_limit','512M');
            ini_set('max_execution_time',200);
        }
        $graph->set_date($date);
    }elseif(isset($_GET['user'])){
        $filename=$_GET['user'].".png";
        $graph->user=$_GET['user'];
        $graph->ip=isset($_GET['ip'])&&$_GET['ip'];
        $graph->user_id=isset($_GET['user_id']) ? $_GET['user_id'] : null;
    }else{
        $filename=$type.".png";
    }

    if(isset($_GET['size'])){
        $graph->size=$_GET['size'];
    }
    if(!Nerf && isset($_GET['width'])){
        $v=(int)$_GET['width'];
        if($v>0 && $v<=5000)
            $graph->width=$v;
    }
    if(!Nerf && isset($_GET['height'])){
        $v=(int)$_GET['height'];
        if($v>0 && $v<=5000)
            $graph->height=$v;
    }
    if(!Nerf && isset($_GET['step'])&&$_GET['step']!=0)
        $graph->step=(int)$_GET['step'];
    if(isset($_GET['legend']))
        $graph->legend= $_GET['legend']==1;
    header('Content-Type: image/png');
    header("Content-Disposition: inline; filename=\"$filename\"");
    $graph->graph();
}else{
    echo 'Error missing params';
}

?>