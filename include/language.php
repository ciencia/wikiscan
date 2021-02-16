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

function msg($key, $param=false)
{
    global $InterfaceLanguage;
    if(!is_object($InterfaceLanguage)){
        trigger_error("InterfaceLanguage class not found", E_USER_ERROR);
        return false;
    }
    return $InterfaceLanguage->message($key, $param);
}
function msg_exists($key)
{
    global $InterfaceLanguage;
    if(!is_object($InterfaceLanguage)){
        trigger_error("InterfaceLanguage class not found", E_USER_ERROR);
        return false;
    }
    return $InterfaceLanguage->key_exists($key);
}
function msg_site($key, $param=false)
{
    global $SiteLanguage;
    if(!is_object($SiteLanguage)){
        trigger_error("SiteLanguage class not found", E_USER_ERROR);
        return false;
    }
    return $SiteLanguage->message($key, $param);
}
function msg_site_exists($key)
{
    global $SiteLanguage;
    if(!is_object($SiteLanguage)){
        trigger_error("InterfaceLanguage class not found", E_USER_ERROR);
        return false;
    }
    return $SiteLanguage->key_exists($key);
}

class Language
{
    var $lang;
    var $messages_dir='include/languages';
    var $messages=array();

    function __construct($lang=false)
    {
        $this->lang=$lang;
    }
    function get_lang()
    {
        return $this->lang;
    }
    function lang_file($lang)
    {
        global $conf;
        return $conf['root_path'].'/'.$this->messages_dir.'/'.$lang.'.php';
    }
    function lang_exists($lang)
    {
        if($lang==='raw')
            return true;
        if(!preg_match('!^[a-z]+$!i', $lang))
            return false;
        return file_exists($this->lang_file($lang));
    }
    function load_messages($lang=false)
    {
        if($lang!==false)
            $this->lang=$lang;
        if($this->lang==='raw')
            return;
        if(!$this->lang_exists($this->lang)){
            trigger_error("Language file not found for ".htmlspecialchars($this->lang), E_USER_ERROR);
            return false;
        }
        include($this->lang_file($this->lang));
        $this->messages=$messages;
    }
    function key_exists($key)
    {
        if($this->lang==='raw')
            return true;
        return array_key_exists($key, $this->messages);
    }
    function message($key, $param=false)
    {
        if($this->lang=='raw')
            return $key;
        if(!$this->key_exists($key))
            return $key;
        $text=$this->parse_message($this->messages[$key]);
        if($param!==false)
            $text=$this->parse_param($text, $param);
        return $text;
    }
    function parse_message($text)
    {
        return $text;
    }
    function parse_param($text, $param)
    {
        return str_replace('$1', $param, $text);
    }
    function list_messages($start_with=false)
    {
        if($start_with===false)
            return $this->messages;
        $res=array();
        $len=strlen($start_with);
        foreach($this->messages as $k=>$v)
            if(strncmp($start_with, $k, $len)===0)
                $res[$k]=$v;
        return $res;
    }
    function list_langs()
    {
        global $conf;
        $path=$conf['root_path'].'/'.$this->messages_dir;
        $res=array();
        foreach(scandir($path) as $f)
            if(is_file($path.'/'.$f) && preg_match('!^([a-z]+)\.php$!', $f, $r))
                $res[]=$r[1];
        return $res;
    }
}