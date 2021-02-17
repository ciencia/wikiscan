<?php

class mCache
{
    var $host='';
    var $port=0;
    var $m;
    static $stats;
    
    static function get_cache()
    {
        return self::cache();
    }
    static function cache()
    {
        global $Cache;
        if(!is_object($Cache)){
            $Cache=new mCache();
            $Cache->host='127.0.0.1';
            $Cache->port=11211;
            $Cache->init();
        }
        return $Cache;
    }
    static function close_global()
    {
        global $Cache;
        if(is_object($Cache))
            $Cache->close();
        $Cache=null;
    }

    function init()
    {
        if($this->host==''){
            echo "Error no memcached host\n";
            return false;
        }
        if(!class_exists('Memcached')){
            echo "Memcached class not found\n";
            return false;
        }
        $this->m = new Memcached();
        $this->m->addServer($this->host,$this->port);
    }
    function close()
    {
        if($this->m)
            $this->m->quit();
    }
    function set($key,$val,$expire=0,$compress=false)
    {
        if(!$this->m)
            return false;
        //expire unix timestamp or max 2592000 s (1 month)
        return $this->m->set($key, $val, $expire);
    }
    function get($key)
    {
        if(!$this->m)
            return false;
        return $this->m->get($key);
    }
    function delete($key)
    {
        if(!$this->m)
            return false;
        return $this->m->delete($key);
    }
    function info()
    {
        if(!$this->m)
            return false;
        $s=$this->m->getStats();
        print_r($s);
        list(,$v)=each($s);
        echo $v['curr_connections']." connections\n";
        echo $v['curr_items'].' / '.$v['total_items']." items\n";
        echo $v['cmd_get'].' gets  '.$v['cmd_set']." sets\n";
        echo round(100*$v['get_hits']/($v['get_hits']+$v['get_misses']))."% cache hits\n";
        echo round(100*($v['limit_maxbytes']-$v['bytes'])/$v['limit_maxbytes'])."% bytes free\n";
    }
    function detailed_info()
    {
        if(!$this->m)
            return false;
        // FIXME: This no longer works on the memcached extension, only on the legacy memcache extension
        $slabs=$this->m->getExtendedStats('slabs');
        foreach($slabs as $srv=>$u){
            echo "serveur : $srv\n";
            foreach($u as $k=>$v){
                if(!is_array($v)||!is_numeric($k))
                    continue;
                echo "slab $k\n";
                // FIXME: This no longer works on the memcached extension, only on the legacy memcache extension
                $keys=$this->m->getExtendedStats('cachedump',$k);
                foreach($keys as $s=>$srv_keys){
                    foreach($srv_keys as $kk=>$vv)
                        echo $kk.' : '.$vv[0].' '.date('Y-m-d H:i:s',$vv[1])."\n";
                }
            }
        }
    }
    function test()
    {
        $v=mt_rand(1,999999);
        $k='test:'.mt_rand(1,999999);
        $this->set($k,$v,60);
        usleep(mt_rand(1,10)*10000);
        if($this->get($k)==$v){
            echo "Test OK\n";
            return true;
        }
        echo "Test FAILED\n";
        return false;
    }
    static function inc_stat_hits($func)
    {
        self::inc_stat($func, 'hits');
    }
    static function inc_stat_miss($func)
    {
        self::inc_stat($func, 'miss');
    }
    static function inc_stat($func, $type)
    {
        if(!isset(self::$stats[$func][$type]))
            self::$stats[$func][$type]=1;
        else
            self::$stats[$func][$type]++;
    }
    static function view_stats()
    {
        if(empty(self::$stats))
            return;
        $o='<table class=cache_stats><tr><td>Key</td><td>Hits</td><td>Hits %</td><td>Miss</td></tr>';
        foreach(self::$stats as $k=>$v){
            $t=@$v['hits']+@$v['miss'];
            $o.="<tr><td>$k</td><td>".(int)@$v['hits']."</td><td>".round(100*@$v['hits']/$t)."&nbsp;%</td><td>".(int)@$v['miss']."</td></tr>";
        }
        $o.='</table>';
        return $o;
    }
}

/*
class rpCache
{

    function init()
    {
        $this->m = new Memcached();
        $this->m->addServer('localhost', 11211);
    }
    function set($key,$val)
    {
        //$this->set++;
        return $this->m->set($key, $val);
    }
    function get($key)
    {
        //$this->req++;
        $res=$this->m->get($key);
        if($this->m->getResultCode()==Memcached::RES_SUCCESS){
            //$this->hit++;
            return $res;
        }
        return false;
    }
    function info()
    {
        print_r($this->m->getStats());
    }
}
*/
?>