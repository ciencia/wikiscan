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

if(!class_exists('mCache'))
    require_once('include/common/mCache.php');
if(!class_exists('Debug'))
    require_once('include/debug.php');
require_once('include/wiki_api.php');

define('NS_MEDIA', -2);
define('NS_SPECIAL', -1);
define('NS_MAIN', 0);
define('NS_TALK', 1);
define('NS_USER', 2);
define('NS_USER_TALK', 3);
define('NS_PROJECT', 4);
define('NS_PROJECT_TALK', 5);
define('NS_FILE', 6);
define('NS_FILE_TALK', 7);
define('NS_MEDIAWIKI', 8);
define('NS_MEDIAWIKI_TALK', 9);
define('NS_TEMPLATE', 10);
define('NS_TEMPLATE_TALK', 11);
define('NS_HELP', 12);
define('NS_HELP_TALK', 13);
define('NS_CATEGORY', 14);
define('NS_CATEGORY_TALK', 15);
//Scribunto
define('NS_MODULE', 828);
define('NS_MODULE_TALK', 829);
//fr
define('NS_PORTAL', 100);
define('NS_PORTAL_TALK', 101);

class mwns
{
    var $cache=true;
    var $file_cache=true;
    var $mem_cache=true;
    var $mem_cache_expire=3600;
    var $file_cache_expire=86400;
    var $ns_main_name='Article';
    var $namespaces = array(
        NS_MEDIA            => 'Média',
        NS_SPECIAL          => 'Spécial',
        NS_TALK             => 'Discussion',
        NS_USER             => 'Utilisateur',
        NS_USER_TALK        => 'Discussion utilisateur',
        NS_PROJECT          => 'Wikipédia',
        NS_PROJECT_TALK     => 'Discussion Wikipédia',
        NS_FILE             => 'Fichier',
        NS_FILE_TALK        => 'Discussion fichier',
        NS_MEDIAWIKI        => 'MediaWiki',
        NS_MEDIAWIKI_TALK   => 'Discussion MediaWiki',
        NS_TEMPLATE         => 'Modèle',
        NS_TEMPLATE_TALK    => 'Discussion modèle',
        NS_HELP             => 'Aide',
        NS_HELP_TALK        => 'Discussion aide',
        NS_CATEGORY         => 'Catégorie',
        NS_CATEGORY_TALK    => 'Discussion catégorie',
        NS_PORTAL=>'Portail',
        NS_PORTAL_TALK=>'Discussion Portail',
        102=>'Projet',
        103=>'Discussion Projet',
        104=>'Référence',
        105=>'Discussion Référence',
        NS_MODULE=>'Module',
        NS_MODULE_TALK=>'Discussion module',
        );
    var $namespaces_alias = array(
        'Special'=>NS_SPECIAL,
        'Discuter'=>NS_TALK,
        'WP'=>NS_PROJECT,
        'Wikipedia'=>NS_PROJECT,
        'Discussion Wikipedia'=>NS_PROJECT_TALK,
        'Image'=>NS_FILE,
        'File'=>NS_FILE,
        'Discussion image'=>NS_FILE_TALK,
        'Module talk'=>NS_MODULE_TALK,
        'Utilisatrice'=>NS_USER,
        'Discussion Utilisateur'=>NS_USER_TALK,
        'Discussion Utilisatrice'=>NS_USER_TALK,
        'Discussion utilisatrice'=>NS_USER_TALK,
        'Discussion Fichier'=>NS_FILE_TALK,
        'Discussion Image'=>NS_FILE_TALK,
        'Image talk'=>NS_FILE_TALK,
        'Discussion Modèle'=>NS_TEMPLATE_TALK,
        'Discussion Aide'=>NS_HELP_TALK,
        'Discussion Catégorie'=>NS_CATEGORY_TALK,
        'Category'=>NS_CATEGORY,
        );
    var $content_namespaces;
    
    function __construct()
    {
        $this->load();
    }
    
    function load()
    {
        $this->namespaces=array();
        $this->content_namespaces=array();
        if($this->cache && $this->load_cache()){
            if(!empty($this->namespaces))
                return true;
        }
        $api=wiki_api();
        $i=0;
        do{
            $ns=$api->namespaces();
            sleep($i*10);
        } while(empty($ns) && ++$i<=3);
        if(empty($ns)){
            trigger_error('mwns load : empty namespaces');
            return false;
        }
        $this->namespaces_alias=array();
        foreach($ns as $v){
            $this->namespaces[$v['id']]=$v['*'];
            if(isset($v['canonical']))
                $this->namespaces_alias[$v['canonical']]=$v['id'];
            if(isset($v['content']))
                $this->content_namespaces[$v['id']]=$v['id'];
        }
        foreach($api->namespaces_alias() as $v)
            $this->namespaces_alias[$v['*']]=$v['id'];
        if($this->cache)
            $this->save_cache();
    }
    static function get()
    {
        global $Mwns;
        if(!is_object($Mwns))
            $Mwns=new mwns();
        return $Mwns;
    }
    function cache_file()
    {
        global $conf;
        if(!is_array($conf) || !isset($conf['cache_path']))
            return false;
        return $conf['cache_path'].'/'.$conf['cache_key_global'].'_'.$conf['cache_key_site'].'_ns';
    }
    function cache_key()
    {
        return 'mwns';
    }
    function save_cache()
    {
        global $conf;
        if(empty($this->namespaces)){
            trigger_error('mwns save cache : empty namespaces');
            return false;
        }
        $data=array($this->namespaces, $this->namespaces_alias, $this->content_namespaces);
        if($this->mem_cache && function_exists('cache_key') && $Cache=mCache::get_cache()){
            $key=cache_key($this->cache_key());
            $Cache->set($key, $data, $this->mem_cache_expire);
            if(DEBUG)
                Debug::info('set ns cache mem',$key);
        }
        if($this->file_cache && ($file=$this->cache_file())!==false){
            if(!is_dir(dirname($file))){
                mkdir(dirname($file));
                chmod(dirname($file), 0770);
            }else
                chmod(dirname($file), 0770);//TEMP
            file_put_contents($file, serialize($data));
            if(!chmod($file, 0660))
                trigger_error("chmod fail $conf[wiki_key] $file");
            chgrp($file, 'www-data');
            if(DEBUG)
                Debug::info('set ns cache file',$file);
        }
    }
    function load_cache()
    {
        global $conf;
        $data=array();
        if($this->mem_cache && function_exists('cache_key') && $Cache=mCache::get_cache()){
            $key=cache_key($this->cache_key());
            $data=$Cache->get($key);
        }
        if(!empty($data)){
            if(DEBUG)
                Debug::info('ns cache hit mem',$key);
        }else{
            if(DEBUG)
                Debug::info('ns cache miss mem',$key);
            if($this->file_cache && ($file=$this->cache_file())!==false){
                if(file_exists($file) && filemtime($file)+$this->file_cache_expire > time()){
                    if(DEBUG)
                        Debug::info('ns cache hit file', $file);
                    $data=file_get_contents($file);
                    if($data!=''){
                        $data=unserialize($data);
                        if($this->mem_cache && $Cache=mCache::get_cache()){
                            if(DEBUG)
                                Debug::info('set ns cache mem',$key);
                            $Cache->set($key, $data, $this->mem_cache_expire);
                        }
                    }
                }elseif(DEBUG)
                    Debug::info('ns cache miss file', $file);
            }
        }
        if(is_array($data)){
            if(isset($data[0]))
                $this->namespaces=$data[0];
            if(isset($data[1]))
                $this->namespaces_alias=$data[1];
            if(isset($data[2]))
                $this->content_namespaces=$data[2];
            return true;
        }
        return false;
    }
    
    function namespaces()
    {
        return $this->namespaces;
    }
    
    function ns_string($ns)
    {
        if(isset($this->namespaces[$ns]))
            return $this->namespaces[$ns];
        return false;
    }
    function ns_strings($ns)
    {
        $res=array();
        if(isset($this->namespaces[$ns]))
            $res[]=$this->namespaces[$ns];
        foreach($this->namespaces_alias as $k=>$v)
            if($v===$ns)
                $res[]=$k;
        return $res;
    }
    function ns_name($ns)
    {
        if($ns==0)
            return $this->ns_main_name;
        if(isset($this->namespaces[$ns]))
            return $this->namespaces[$ns];
        return false;
    }
    function remove_ns($title)
    {
        if($this->get_ns_str($title)!=''){
            return mb_substr($title,mb_strpos($title,':')+1);
        }
        return $title;
    }
    function get_ns($title)
    {
        $ns_str=$this->get_ns_str($title);
        if($ns_str!=''){
            if(isset($this->namespaces_alias[$ns_str]))
                return $this->namespaces_alias[$ns_str];
            if(($ns=array_search($ns_str,$this->namespaces))!==false)
                return $ns;
        }
        return NS_MAIN;
    }
    function get_ns_str($title)
    {
        if(($p=mb_strpos($title,':'))!==false){
            $ns_str=mb_substr($title,0,$p);
            if(strpos($ns_str,'_')!==false)
                $ns_str=str_replace('_',' ',$ns_str);
            if(isset($this->namespaces_alias[$ns_str]) || array_search($ns_str,$this->namespaces))
                return $ns_str;
        }
        return '';
    }
    function ns_title($title,$ns)
    {
        if(isset($this->namespaces[$ns]) && $this->namespaces[$ns]!='')
            return $this->namespaces[$ns].':'.$title;
        return $title;
    }
    function remove_nsdb($title)
    {
        return $this->wtitle($this->remove_ns($title));
    }
    function is_content($ns)
    {
        return isset($this->content_namespaces[$ns]);
    }
}
?>