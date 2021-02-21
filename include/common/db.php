<?php

class db
{
    var $charset = 'utf8';
    var $port=3306;
    var $auto_reconnect=false;
    var $link = null ;
    var $opened=false;
    var $profile=false;
    var $profile_file='/tmp/db.profile';
    var $profile_slow_file='/tmp/db.slow.profile';
    var $profile_very_slow_file='/tmp/db.very.slow.profile';
    var $debug=false;
    var $flags=false;
    var $ssl_key;
    var $ssl_cert;
    var $ssl_ca;
    var $connect_errno;
    var $connect_error;
    var $query_time=0;
    var $query_count=0;
    var $callback;

    function __construct($host='localhost', $user='root', $pass='', $base = '', $port=false, $flags=false)
    {
        $this->host = $host ;
        $this->user = $user ;
        $this->pass = $pass ;
        $this->base = $base ;
        $this->flags = $flags ;
        if($port!==false)
            $this->port = $port;
        $this->start_time=microtime(true);
    }

    static function get($force_new=false, $database=false)
    {
        global $db_global, $conf;
        if(!is_object($db_global) || $force_new){
            if(is_object($db_global))
                $db_global->close();
            $db_global=new db($conf['mysql']['host'], $conf['mysql']['user'], $conf['mysql']['password']);
            $db_global->charset=$conf['mysql']['charset'];
            if(isset($conf['mysql']['debug']))
                $db_global->debug=$conf['mysql']['debug'];
            if(isset($conf['mysql']['profile']))
                $db_global->profile=$conf['mysql']['profile'];
            if(isset($conf['mysql']['auto_reconnect']))
                $db_global->auto_reconnect=$conf['mysql']['auto_reconnect'];
            if($database===false)
                $database=$conf['mysql']['database'];
            if(!$db_global->open($database)){
                die("Error db open '$database'\n");
            }
        }
        return $db_global;
    }

    static function end()
    {
        global $db_global;
        if(is_object($db_global))
            $db_global->close();
    }


    function open($base = false, $error_503=true)
    {
        if($base!==false)
            $this->base=$base;
        $this->connect();
        if(mysqli_connect_errno()){
            $reco=$this->auto_reconnect;
            if(defined('DB_ALWAYS_RECONNECT'))
                $reco=DB_ALWAYS_RECONNECT;
            if(!$reco){
                if(!$error_503)
                    return false;
                if(isset($_SERVER["SERVER_PROTOCOL"])){
                    header($_SERVER["SERVER_PROTOCOL"].' 503 Service Temporarily Unavailable');
                    header('Status: 503 Service Temporarily Unavailable');
                    echo "<h1>Error 503 Service Temporarily Unavailable</h1>";
                    echo "<p>Unable to connect to the database.</p>";
                }
                trigger_error("Database error ({$this->base})");
                trigger_error(htmlspecialchars(date('Y-m-d H:i:s').' DB Connect failed. Error '.mysqli_connect_errno().' '.mysqli_connect_error()));
                //debug_print_backtrace();
                exit;
            }
            do{
                //debug_print_backtrace();
                @$retry++;
                trigger_error(date('Y-m-d H:i:s')." DB Connect failed ($retry): Error ".htmlspecialchars(mysqli_connect_errno().' '.mysqli_connect_error())."\n");
                sleep($retry<10 ? 3 : ($retry<50 ? 5 : 20));
                $this->connect();
            }while(mysqli_connect_errno());
        }
        if(!mysqli_set_charset($this->link, $this->charset)){
            trigger_error(htmlspecialchars("Error loading character set ".$this->charset." : ". mysqli_error($this->link)."\n"));
        }
        $this->opened=true;
        if($this->base!='')
            return $this->select_db();
        return true;
    }
    function connect()
    {
        if(!$this->flags)
            $this->link = @mysqli_connect($this->host, $this->user, $this->pass, null, $this->port);
        else{
            $this->link = mysqli_init();
            if($this->flags & MYSQLI_CLIENT_SSL)
                mysqli_ssl_set($this->link, $this->ssl_key, $this->ssl_cert, $this->ssl_ca, null, null);
            @mysqli_real_connect($this->link, $this->host, $this->user, $this->pass, null, $this->port, null, $this->flags);
            $this->connect_errno=mysqli_connect_errno();
            $this->connect_error=mysqli_connect_error();
        }
        return $this->link;
    }
    function ssl_set ($key, $cert, $ca)
    {
        $this->ssl_key=$key;
        $this->ssl_cert=$cert;
        $this->ssl_ca=$ca;
    }
    function autocommit(bool $mode)
    {
        return mysqli_autocommit($this->link, $mode);
    }
    function reconnect()
    {
        trigger_error("Reconnecting");
        $this->close();
        $this->open();
    }
    
    function select_db($base=false)
    {
        if($base!==false)
            $this->base=$base;
        if(!$this->link){
            trigger_error(htmlspecialchars("Not connected, can't select db '{$this->base}\n"));
            return false;
        }
        if($this->base!='')
            return mysqli_select_db($this->link, $this->base) ;
        return false;
    }
    function close()
    {
        if($this->link)
            mysqli_close($this->link);
        $this->link = null ;
        $this->opened=false;
    }

    function insert( $table, $row, $delayed = false, $update=false, $ignore=false)
    {
        if ( $table == '' )
            return false;
        if ( !is_array($row) )
            return false;
        $sql = 'INSERT'.($delayed ? ' DELAYED' : '').($ignore ? ' IGNORE' : '').' INTO `'.$table.'` ' ;
        reset($row);
        $k=key($row);
        if(!is_numeric( $k ))
            $sql .= '(`'.implode( '`,`', array_keys($row) ). '`) ' ;
        $sql .= 'VALUES ' ;
        foreach( $row as $k => $v ) {
            if(is_null($v))
                $row[$k] = 'NULL' ;
            elseif(is_int($v))
                $row[$k] = (int)$v ;
            elseif(is_array($v) && count($v)==2 && isset($v[0]) && isset($v[1]) && is_numeric($v[0]) && is_numeric($v[1]))
                $row[$k] = "GeomFromText('POINT($v[0] $v[1])')" ;
            else
                $row[$k] = "'".mysqli_real_escape_string( $this->link, $v )."'" ;
        }
        $sql .= '('.implode( ",", $row ). ')' ;
        if($update){
            $sql.=' ON DUPLICATE KEY UPDATE ';
            if($update===true||$update===1){
                $a = array() ;
                foreach($row as $k => $v)
                    $a[] = '`'.$k.'`='.$v ;
                $sql .= implode(',',$a);
            }else
                $sql.=$update;
        }
        return $this->query($sql) ;
    }
    function insert_update($table, $row, $update=true)
    {
        return $this->insert($table, $row, false, $update);
    }
    function insert_ignore($table, $row)
    {
        return $this->insert($table, $row, false, false, true);
    }
    function insert_multi($table,$row,$ignore=false)
    {
        if ( $table == '' )
            return false;
        if ( !is_array($row)||empty($row) )
            return false;
        $sql = 'INSERT '.($ignore?'IGNORE ':'').'INTO `'.$table.'` ' ;
        $r=reset($row);
        //reset($r);
        $k=key($r);
        if ( !is_numeric( $k ) ) {
            $sql .= '(`'.implode( '`,`', array_keys($r) ). '`) ' ;
        }
        $sql .= 'VALUES ' ;
        foreach($row as $multi){
            $vm=array();
            foreach($multi as $k => $v ) {
                if(is_null($v))
                    $vm[] = 'NULL' ;
                elseif(is_int($v))
                    $vm[] = (int)$v ;
                elseif(is_array($v) && count($v)==2 && isset($v[0]) && isset($v[1]) && is_numeric($v[0]) && is_numeric($v[1]))
                    $vm[] = "GeomFromText('POINT($v[0] $v[1])')" ;
                else
                    $vm[] = "'".mysqli_real_escape_string( $this->link, $v )."'" ;
            }
            $m[]='('.implode( ",", $vm ). ')' ;
        }
        $sql.=implode(',',$m);
        return $this->query($sql) ;
    }

    function insert_update_multi($table,$row,$ignore=false)
    {
        if ( $table == '' )
            return false;
        if ( !is_array($row)||empty($row) )
            return false;
        $sql = 'INSERT '.($ignore?'IGNORE ':'').'INTO `'.$table.'` ' ;
        $first=reset($row);
        //reset($first);
        $k=key($first);
        if ( !is_numeric( $k ) ) {
            $sql .= '(`'.implode( '`,`', array_keys($first) ). '`) ' ;
        }
        $sql .= 'VALUES ' ;
        foreach($row as $multi){
            $vm=array();
            foreach($multi as $k => $v ) {
                if(is_null($v))
                    $vm[] = 'NULL' ;
                elseif(is_int($v))
                    $vm[] = (int)$v ;
                elseif(is_array($v) && count($v)==2 && isset($v[0]) && isset($v[1]) && is_numeric($v[0]) && is_numeric($v[1]))
                    $vm[] = "GeomFromText('POINT($v[0] $v[1])')" ;
                else
                    $vm[] = "'".mysqli_real_escape_string( $this->link, $v )."'" ;
            }
            $m[]='('.implode( ",", $vm ). ')' ;
        }
        $sql.=implode(',',$m);
        $sql.=' ON DUPLICATE KEY UPDATE';
        $m=[];
        foreach(array_keys($first) as $k)
            $m[]= "`$k` = VALUES(`$k`)";
        $sql.=implode(', ', $m);
        return $this->query($sql) ;
    }

    function replace( $table = '', $row = array(), $delayed = false, $cache = false, $finish = false )
    {
        $delay = $delayed ? ' DELAYED ' : '' ;
        $sql = 'REPLACE'.$delay.' INTO `'.$table.'` ';
        reset($row);
        $k=key($row);
        if ( !is_numeric( $k ) ) {
            $sql .= '(`'.implode( '`,`', array_keys($row) ). '`) ' ;
        }
        $sql .= 'VALUES ' ;
        foreach( $row as $k => $v )
            $row[$k] = mysqli_real_escape_string( $this->link, $v ) ;
        $val = '(\''.implode( "','", $row ). '\')' ;
        if ( !$cache ) {
            return $this->query( $sql.$val ) ;
        } else {
            static $nb ;
            static $cache ;
            if ( $table != '' )
            $cache[$table][] = $val ;
            $nb++ ;
            if ( ($nb >= 16 || $finish) && is_array($cache)) {
                foreach( $cache as $t => $v ) {
                    $this->query( 'REPLACE INTO `'.$t.'` VALUES '.implode(',',$v) );
                }
                $nb = 0 ;
                $cache = array() ;
            }
        }
    }

    function update($table, $index, $id=null, $row=null )
    //             ($table, $row,   $where)
    {
        if($row===null && is_array($index)){
            $row=$index;
            $where=$id;
        }else{
            $where='`'.$index.'`=\''.mysqli_real_escape_string($this->link,$id).'\'' ;
        }
        $sql = 'UPDATE `'.$table.'` SET ' ;
        /*$a = array() ;
        foreach( $row as $k => $v ) {
            if ( is_null( $v ) ) {
                $a[] = '`'.$k.'`=NULL' ;
            } else {
                $a[] = '`'.$k.'`=\''.mysqli_real_escape_string($this->link,$v).'\'' ;
            }
        }*/
        $sql .= $this->update_string($row);
        $sql .= ' WHERE '.$where;
        return $this->query($sql);
    }
    function update_inc($table, $conds, $incs)
    {
        $where='';
        if(!empty($conds))
            $where="where ".$this->where_string($conds);
        $update=$this->update_inc_string($incs);
        return $this->query("update `$table` set $update $where");
    }
    function insert_update_inc($table, $data, $incs)
    {
        return $this->insert_update($table, $data, $this->update_inc_string($incs));
    }

    function delete( $table, $where, $not_used=false )
    {
        if($not_used!==false){
            trigger_error(htmlspecialchars("Error db->delete($table, $where)\n"));
            return false;
        }
        $sql = 'DELETE FROM `'.$table.'`' ;
        $sql .= ' WHERE '.$where ;
        return $this->query($sql);
    }
    function delete_id($table, $id)
    {
        return $this->delete_col($table, 'id', $id);
    }
    function delete_col($table, $col, $val)
    {
        return $this->query("delete from `$table` where `$col`='".$this->escape($val)."'");
    }

    function last_id()
    {
        return mysqli_insert_id($this->link) ;
    }

    function affected_rows()
    {
        return mysqli_affected_rows($this->link);
    }
    function info()
    {
        $inf=mysqli_info($this->link);
        $inf=explode(' ',$inf);
        return array('matched'=>$inf[2],'changed'=>$inf[5],'warning'=>$inf[8]);
    }
    function select($q,$debug=false)
    {
        $res = array() ;
        if($debug)
            echo htmlspecialchars($q."\n");
        $result = $this->query( $q ) ;
        if ( $result ) {
            while ($row = mysqli_fetch_assoc($result)) {
                $res[] = $row ;
            }
            return $res ;
        }
        return false;
    }
    function select_index($q,$index,$debug=false)
    {
        $res = array() ;
        if($debug)
            echo htmlspecialchars($q."\n");
        $result = $this->query( $q ) ;
        if ( $result ) {
            while ($row = mysqli_fetch_assoc($result)) {
                $res[$row[$index]] = $row ;
            }
            return $res ;
        }
        return false;
    }
    function select_walk($q,$func,$param=null,$param2=null)
    {
        if($this->profile)
            $this->start_profile($q);
        if($this->profile)
            $this->end_profile();
        $t=microtime(true);
        if(!mysqli_real_query($this->link, $q)){
            if($this->auto_retry_error()){
                do{
                    trigger_error($this->error($q));
                    if($this->profile)
                        $this->end_profile();
                    $t=microtime(true)-$t;
                    if($this->callback!='')
                        call_user_func($this->callback, $q, $t, 0, $this);
                    $this->reconnect();
                    if($this->profile)
                        $this->start_profile($q);
                    $t=microtime(true);
                    $res=mysqli_real_query($this->link, $q);
                }while($res===false && $this->auto_retry_error());
            }else{
                trigger_error($this->error($q));
                if($this->profile)
                    $this->end_profile();
                $t=microtime(true)-$t;
                if($this->callback!='')
                    call_user_func($this->callback, $q, $t, 0, $this);
                return false;
            }
        }
        $c=0;
        if ($result = mysqli_use_result($this->link)) {
        //if ($result = mysqli_store_result($this->link)) {
            while ($row = mysqli_fetch_assoc($result)) {
                call_user_func($func,$row,$param,$param2);
                $c++;
            }
            mysqli_free_result($result);
            $res=true;
        }else{
            trigger_error($this->error($q));
            $res=false;
        }
        if($this->profile)
            $this->end_profile();
        $t=microtime(true)-$t;
        if($this->callback!='')
            call_user_func($this->callback, $q, $t, $c, $this);
        return $res;
    }
    function select_walk_block($q,$func,$size,$param=null,$param2=null)
    {
        if($this->profile)
            $this->start_profile($q);
        $t=microtime(true);
        if(!mysqli_real_query($this->link, $q)){
            if($this->auto_retry_error()){
                do{
                    trigger_error($this->error($q));
                    if($this->profile)
                        $this->end_profile();
                    $t=microtime(true)-$t;
                    if($this->callback!='')
                        call_user_func($this->callback, $q, $t, 0, $this);
                    $this->reconnect();
                    if($this->profile)
                        $this->start_profile($q);
                    $t=microtime(true);
                    $res=mysqli_real_query($this->link, $q);
                }while($res===false && $this->auto_retry_error());
            }else{
                trigger_error($this->error($q));
                if($this->profile)
                    $this->end_profile();
                $t=microtime(true)-$t;
                if($this->callback!='')
                    call_user_func($this->callback, $q, $t, 0, $this);
                return false;
            }
        }
        $c=0;
        if ($result = mysqli_use_result($this->link)) {
        //if ($result = mysqli_store_result($this->link)) {
            $rows=array();
            $i=0;
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[]=$row;
                if(++$i>=$size){
                    call_user_func($func,$rows,$param,$param2);
                    $rows=array();
                    $i=0;
                    $c++;
                }
            }
            if(!empty($rows))
                call_user_func($func,$rows,$param,$param2);
            mysqli_free_result($result);
            if($this->profile)
                $this->end_profile();
            $res=true;
        }else{
            trigger_error($this->error($q));
            $res=false;
        }
        if($this->profile)
            $this->end_profile();
        $t=microtime(true)-$t;
        if($this->callback!='')
            call_user_func($this->callback, $q, $t, $c, $this);
        return $res;
    }
    function select1($q,$debug=false)
    {
        $res=$this->select($q,$debug);
        if(!isset($res[0]))
            return null;
        return $res[0];
    }
    function select_col($table,$col=false,$where='1',$debug=false)
    {
        if($col===false){
            if($res=$this->select($table)){
                if(isset($res[0])){
                    return reset($res[0]);
                }
            }
            return false;
        }
        $q="select $col from $table where $where";
        $res=$this->select($q,$debug);
        if(!isset($res[0][$col]))
            return false;
        return $res[0][$col];
    }
    function selectcol($table,$col=false,$where='1',$debug=false)
    {
        //deprecated
        return $this->select_col($table,$col,$where,$debug);
    }

    function select_row($table, $id_col, $id, $cols='*')
    {
        return $this->select1("select $cols from `$table` where `$id_col`='".$this->escape($id)."' limit 1");
    }

    function fast_count($q)
    {
        $q="explain $q";
        $res=$this->select1($q);
        if(isset($res['rows']))
            return $res['rows'];
        print_r($res);
        return false;
    }
    function count($table, $conds=false)
    {
        if(!empty($conds))
            return (int)$this->selectcol("select count(*) from `$table` where ".(is_array($conds) ? $this->where_string($conds) : $conds));
        return (int)$this->selectcol("select count(*) from `$table`");
    }
    function where_string($conds, $op='and')
    {
        $a = array() ;
        foreach($conds as $k=>$v){
            if(is_object($v)){
                echo "error db::where_string '$k' is object<pre>";
                debug_print_backtrace();
                echo '</pre>';
                exit;
            }
            if(strpos($k, '(')===false)
                $k='`'.$k.'`';
            if(is_null($v))
                $a[] = "$k is NULL" ;
            elseif(is_int($v))
                $a[] = "$k=".(int)$v ;
            elseif(is_array($v) && count($v)==2 && isset($v[0]) && isset($v[1]) && is_numeric($v[0]) && is_numeric($v[1]))
                $a[] = "$k=GeomFromText('POINT($v[0] $v[1])')" ;
            else
                $a[] = "$k='".mysqli_real_escape_string($this->link, $v)."'" ;
        }
        return implode(" $op ", $a);
    }
    function update_string($conds)
    {
        $a = array() ;
        foreach($conds as $k=>$v){
            if(is_null($v))
                $a[] = '`'.$k.'`=NULL' ;
            elseif(is_int($v))
                $a[] = '`'.$k.'`='.(int)$v ;
            elseif(is_array($v) && count($v)==2 && isset($v[0]) && isset($v[1]) && is_numeric($v[0]) && is_numeric($v[1]))
                $a[] = "`$k`=GeomFromText('POINT($v[0] $v[1])')" ;
            else
                $a[] = '`'.$k.'`=\''.mysqli_real_escape_string($this->link, $v).'\'' ;
        }
        return implode(', ', $a);
    }
    function update_inc_string($incs)
    {
        $update=array();
        foreach($incs as $c=>$inc)
            if($inc!=0)
                $update[]="`$c`=`$c` ".($inc>=0 ? "+ $inc" : $inc);
        return implode(', ', $update);
    }

    function error_no()
    {
        return mysqli_errno($this->link);
    }
    
    function auto_retry_error()
    {
        $error=$this->error_no();
        return $error == 2006 // MySQL server has gone away
            || $error == 2013 // Lost connection to MySQL server during query
            || $error == 1213 // deadlock
            || $error == 1205 // Lock wait timeout exceeded
            ;
    }

    function error($sql='')
    {
        $sql=htmlspecialchars(preg_replace('/[^[:print:]]/', '',$sql));
        return date('Y-m-d H:i:s').' Error '.$this->error_no().' '.mysqli_error($this->link).' sql : '.($sql!='' ? '"'.((strlen($sql)>300)?substr($sql,0,500).'...':$sql).'"' : '' );
    }

    function query( $sql )
    {
        if($this->debug)
            echo htmlspecialchars($sql)."\n";
        if(function_exists('p_in'))
            $p=p_in(__method__);
        if($this->profile)
            $this->start_profile($sql);
        $t=microtime(true);
        $res = mysqli_query( $this->link, $sql ) ;
        $t=microtime(true)-$t;
        $this->query_time+=$t;
        $this->query_count++;
        if($this->profile)
            $this->end_profile();
        if($this->callback!='')
            call_user_func($this->callback, $sql, $t, is_bool($res) ? 0 : mysqli_num_rows($res), $this);
        if($res===false){
            $i=0;
            //debug_print_backtrace();
            if($this->auto_retry_error()){
                //debug_print_backtrace();
                do{
                    $i++;
                    trigger_error($this->error($sql)." (retrying $i)");
                    if($this->error_no()==2006 || $this->error_no()==2013)
                        $this->reconnect();
                    if($this->profile)
                        $this->start_profile($sql);
                    $t=microtime(true);
                    $res = mysqli_query($this->link, $sql) ;
                    $t=microtime(true)-$t;
                    if($this->profile)
                        $this->end_profile();
                    if($this->callback!='')
                        call_user_func($this->callback, $sql, $t, is_bool($res) ? 0 : mysqli_num_rows($res), $this);
                    if($res===false && $i>=5){
                        if($i>=30)
                            usleep($i*10000+mt_rand(10000,200000));
                        elseif($i>=10)
                            usleep($i*10000);
                        else
                            usleep(10000);
                    }
                }while($res===false && $this->auto_retry_error());
            }else
                trigger_error($this->error($sql).' (no retry)');
        }
        if(function_exists('p_out'))
            p_out($p);
        return $res ;
    }
    
    function multi_query($sql)
    {

        $res=mysqli_multi_query($this->link, $sql);
        if($res===false)
            trigger_error($this->error($sql));
        return $res;
    }

    function tables()
    {
        $tables=array();
        foreach($this->select('show tables') as $v){
            $table=reset($v);
            $tables[$table]=$table;
        }
        return $tables;
    }

    function table_def($table)
    {
        $rows=$this->select("SELECT * FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA`='".$this->escape($this->base)."' AND `TABLE_NAME`='".$this->escape($table)."'");
        $res=[];
        foreach($rows as $v)
            $res[$v['COLUMN_NAME']]=$v;
        $rows=$this->select("SELECT * FROM information_schema.`KEY_COLUMN_USAGE` WHERE `TABLE_SCHEMA`='".$this->escape($this->base)."' AND `TABLE_NAME`='".$this->escape($table)."'");
        foreach($rows as $v)
            foreach(['REFERENCED_TABLE_SCHEMA', 'REFERENCED_TABLE_NAME', 'REFERENCED_COLUMN_NAME'] as $k)
                $res[$v['COLUMN_NAME']][$k]=$v[$k];
        return $res;
    }

    
    function options($k,$v)
    {
        return mysqli_options($this->link,$k,$v);
    }
    function escape($str)
    {
        return mysqli_real_escape_string($this->link, $str);
    }
    function ping()
    {
        return mysqli_ping($this->link);
    }
    function stats()
    {
        if(!function_exists('mysqli_get_connection_stats'))
            return false;
        return mysqli_get_connection_stats($this->link);
    }
    function start_profile($q)
    {
        $this->profile_start=microtime(true);
        $this->profile_query=$q;
    }
    function end_profile()
    {
        $t=microtime(true)-$this->profile_start;
        $gt=microtime(true)-$this->start_time;
        $txt=round(1000*$gt)." ms | ".round(1000*$t,1)." ms [{$this->base}] : {$this->profile_query}\n";
        if($this->profile_file!=''){
            file_put_contents($this->profile_file, $txt, FILE_APPEND);
            if(1000*$t>=1)
                file_put_contents($this->profile_slow_file, $txt, FILE_APPEND);
            if($t>=1)
                file_put_contents($this->profile_very_slow_file, $txt, FILE_APPEND);
        }else
            echo htmlspecialchars($txt);
    }
    
    function check_ssl()
    {
        $res=$this->select("SHOW STATUS LIKE 'ssl_cipher'");
        foreach($res as $v)
            if(isset($v['Variable_name']) && $v['Variable_name']=='Ssl_cipher' && isset($v['Value']) && $v['Value']!='')
                return true;
        return false;
    }
}
?>