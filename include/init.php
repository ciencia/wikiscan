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
include('config/conf.php');

require_once('include/debug.php');
if(DEBUG)
    Debug::create();

mb_internal_encoding('utf-8');
date_default_timezone_set('UTC');

require_once('include/functions.php');
require_once('include/init_db.php');

require_once('include/language.php');

$InterfaceLanguage=new Language();
if(isset($_GET['lang']) && $_GET['lang']!='' && $InterfaceLanguage->lang_exists($_GET['lang'])){
    $conf['interface_language']=$_GET['lang'];
    $conf['forced_interface_language']=true;
}
$InterfaceLanguage->load_messages($conf['interface_language']);
$SiteLanguage=new Language();
$SiteLanguage->load_messages($conf['site_language']);

// XXHACK to autoload from includes, some files seem to not have been included manually
spl_autoload_register(function($className)
{
    $class= sprintf("%s/%s.php", __DIR__, strtolower($className));
    include_once($class);
});
?>