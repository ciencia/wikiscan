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
if(!defined('DEBUG'))
    define('DEBUG',false);

class Debug
{
    var $infos;

    function __construct()
    {
        $this->set_info('start',microtime(true));
    }
    static function create()
    {
        global $Debug;
        $Debug=new Debug();
        return $Debug;
    }
    static function info($k,$v)
    {
        global $Debug;
        if(is_object($Debug))
            $Debug->set_info($k,$v);
    }
    function set_info($k,$v)
    {
        $this->infos[$k]=$v;
    }
    static function mem($label)
    {
        self::info($label,'mem: '.number_format(memory_get_usage()/1024,0,'.',' ').' k peak: '.number_format(memory_get_peak_usage()/1024,0,'.',' ').' k');
    }
    function view($raw=false)
    {
        global $db,$dbs;
        if(isset($this->infos['start']))
            $this->set_info('length',round((microtime(true)-$this->infos['start'])*1000).' ms');
        $this->set_info('mem',number_format(memory_get_usage(),0,'.',' '));
        $this->set_info('mem peak',number_format(memory_get_peak_usage(),0,'.',' '));
        $this->set_info('db',is_object($db)?1:0);
        $this->set_info('dbs',is_object($dbs)?1:0);
        if($raw){
            print_r($this->infos);
            return;
        }
        return $this->view_table($this->infos);
    }
    function view_table($table,$lvl=1)
    {
        $o='<table class="debug">'."\n";
        foreach($table as $k=>$v){
            $o.="<tr><td>$k</td><td>";
            if(is_null($v))
                $o.='Null';
            elseif(is_bool($v))
                $o.= $v ? 'True' : 'False' ;
            elseif(!is_array($v))
                $o.=$v;
            elseif($lvl<=2)
                $o.=$this->view_table($v,$lvl+1);
            else
                $o.='(...)';
            $o.="</td></tr>\n";
        }
        $o.="</table>\n";
        return $o;
    }

}

?>