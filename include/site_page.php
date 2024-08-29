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

class site_page
{
    var $cache=false;
    var $cache_expire=0;
    var $loaded=false;

    function valid_cache_date($cache_date)
    {
        trigger_error('default valid_cache_date');
        return true;
    }
    function get_cache()
    {
        $Cache=get_cache();
        if(!$this->cache || !$Cache)
            return false;
        $key=cache_key($this->cache_key());
        if(DEBUG)
            Debug::info('cache key',$key);
        $data=$Cache->get($key);
        if(is_array($data)){
            if($this->valid_cache_date($data[0])){
                if(DEBUG)
                    Debug::info('cache','hit');
                $this->loaded=true;
                return $data[1];
            }
            $Cache->delete($key);
            if(DEBUG)
                Debug::info('cache','outdated');
        }else{
            if(DEBUG)
                Debug::info('cache','miss');
        }
        return false;
    }
    function set_cache($data)
    {
        $Cache=get_cache();
        if(!$this->cache || !$Cache ||$data==''){
            if(DEBUG)
                Debug::info('set cache','no cache');
            return false;
        }
        $data.="<!-- Cached ".date('Y-m-d H:i:s').'-->';
        $key=cache_key($this->cache_key());
        if(DEBUG)
            Debug::info('set cache',$key);
        return $Cache->set($key,array(gmdate('YmdHis'),$data),$this->cache_expire);
    }
    function cache_key()
    {
        return 'default';
    }
    function cache_key_from_get($prefix, $keys)
    {
        foreach($keys as $k)
            $prefix.=":".(isset($_GET[$k]) ? $_GET[$k] : "");
        return $prefix;
    }

}
?>
