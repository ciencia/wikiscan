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
require_once('include/common/db.php');

function get_dbs($force_new=false, $allow_failure=false)
{
    global $dbs,$conf,$db_conf;
    if($force_new || !is_object($dbs)){
        $dbs=init_db('dbs', false);
        //$dbs->profile=true;
        $base=$db_conf['dbs']['database'];
        if(!$dbs->select_db($base)){
            if(@$conf['wiki_key']!=''){
                //autocreate database tables
                echo "\ncreate database '".htmlspecialchars($base)."'\n";
                $dbs->query('create database '.$dbs->escape($base));
                if($dbs->select_db($base)){
                    if(file_exists($conf['root_path'].'/ctrl/stats.sql')){
                        echo "import stats.sql\n";
                        exec('/usr/bin/mysql -h '.escapeshellarg($db_conf['dbs']['host']).' -u '.escapeshellarg($db_conf['dbs']['user']).' -p'.escapeshellarg($db_conf['dbs']['password']).' '.escapeshellarg($base).' < '.$conf['root_path'].'/ctrl/stats.sql');
                    }else
                        die("SQL file not found : ".$conf['root_path'].'/ctrl/stats.sql');
                    return $dbs;
                }
            }
            echo "Database error (dbs)";
            trigger_error(htmlspecialchars(date('Y-m-d H:i:s').' DB open failed for database '.htmlspecialchars($base).'. Error '.mysqli_connect_errno().' '.mysqli_connect_error()));
            if(!$allow_failure)
                exit;
            return false;
        }
    }
    return $dbs;
}

function get_db()
{
    global $db;
    if(!is_object($db)){
        if($db=init_db('db', true, false, true))
            $db->callback='log_sql';
    }
    return $db;
}
function get_db2()
{
    global $db2;
    if(!is_object($db2))
        $db2=init_db('db2', true, false, true);
    return $db2;
}

function get_dbg()
{
    global $dbg;
    if(!is_object($dbg))
        $dbg=init_db('dbg');
    return $dbg;
}


function init_db($conf_key, $open_database=true, $error_503=true, $quiet=false)
{
    global $db_conf;
    if(!isset($db_conf[$conf_key]))
        die("db conf not found for '$conf_key'\n");
    $conf=$db_conf[$conf_key];
    if(!isset($conf['host'])){
        echo "init_db no host";
        return false;
    }
    $db=new db($conf['host'], $conf['user'], $conf['password'], $open_database ? $conf['database'] : '', isset($conf['port'])?$conf['port']:3306, isset($conf['flags']) ? $conf['flags'] : false);
    if(isset($conf['charset']))
        $db->charset=$conf['charset'];
    if(!$db->open(false, $error_503)){
        if(!$quiet){
            echo "Database open error\n";
            trigger_error(htmlspecialchars(date('Y-m-d H:i:s')." DB open failed for database '$conf[database]'. Error ".mysqli_connect_errno().' '.mysqli_connect_error()));
        }
        return false;
    }
    return $db;
}

function log_sql($sql, $time, $rows, $db)
{
    global $conf;
    $dbg=get_dbg();
    $type='';
    if(preg_match('!/\*(.+)\*/!', $sql, $r))
        $type=trim(str_replace('SLOW_OK', '', $r[1]));
    $dbg->insert('query_log', [
        'base'=>$db->base,
        'type'=>$type,
        'query'=>$sql,
        'time'=>$time,
        'rows'=>$rows,
        'date'=>date('Y-m-d H:i:s'),
        ]);
}

function close_db()
{
    global $dbg, $db, $dbs;
    if(is_object($db))
        $db->close();
    if(is_object($dbg))
        $dbg->close();
    if(is_object($dbs))
        $dbs->close();
}

?>