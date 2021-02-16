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
require_once('include/mw/mwtools.php');
require_once('include/dates.php');
require_once('include/pageview.php');
require_once('include/pageview_data.php');
require_once('include/debug.php');

class UpdateStats
{
    var $nb_user=0;
    var $nb_rev=0;
    var $s;
    var $total_time_base=300;
    var $total_time_max=600;
    var $total_time_max2=900;
    var $total_time_max3=1200;
    var $redit_user_max=180;
    var $redit_page_max=1200;
    var $chain_max=70;
    var $merge_log=true;
    var $max_save_row=100000;
    var $users;
    var $add_pageview=true;
    var $add_archive=false;
    var $day;
    var $reduce=true;
    var $reduce_load_limit_sum=10000;
    var $reduce_min_sum=12;
    var $weight_last_edit=false;
    const data_path='wpstats';
    static $compress=true;
    var $single_user=false;
    var $groups=array();
    var $missing_user_name='__MISSING__';
    var $sum_total_edits=false;
    var $limit_sum_edits=2000000;
    var $limit_sum=false;
    var $last_sum=false;
    static $separate_ip=true;
    var $limit_sum_ip=false;
    var $unbuffered_sql_rev=true;
    var $double_db=false;
    var $partial_pages_data;

    function __construct($stats=false)
    {
        if(is_array($stats))
            $this->stats=$stats;
        else
            $this->stats=array('time','users','pages','stats');//stats last
    }
    function reset()
    {
        $this->s=array();
        $this->nb_user=0;
        $this->nb_rev=0;
    }
    function load_groups()
    {
        $this->groups=mwTools::user_groups();
    }

    function update_last_hours($hours)
    {
        if($hours==0)
            return;
        date_default_timezone_set('GMT');
        if(empty($this->groups))
            $this->load_groups();
        $end=time();
        $start=strtotime("-$hours hours", $end);
        $this->weight_last_edit=true;
        echo "$hours hours";
        $t=microtime(true);
        $this->update_time($start,$end);
        $this->save($hours);
        $t=microtime(true)-$t;
        echo " ".flength($t)."\n";
    }
    function update_days($start=false,$end=false,$sleep=false)
    {
        $db=get_db();
        $this->load_groups();
        if($start==false)
            $start=$db->selectcol('revision','min(rev_timestamp)');
        if(!$start||strlen($start)<4)
            return false;
        $date=strtotime(date('Ymd',strtotime($start)));
        if($end!==false)
            $end=strtotime($end);
        if($this->double_db){
            $this->db2=clone($db);
            $this->db2->open();
        }else
            $this->db2=$db;
        echo "updatestats days from ".date('Ymd',$date)."\n";
        while($date<=time() && ($end===false || $date<=$end)){
            $this->update_day(date('Ymd',$date),$sleep);
            $date=strtotime('+1 day',$date);
        }
        if($this->double_db)
            $this->db2->close();
    }
    function update_day($date,$sleep=false)
    {
        if(strlen($date)!=8){
            echo "Error date format $date\n";
            return false;
        }
        if($date<20000101){
            echo "Error date $date\n";
            return false;
        }
        echo "$date";
        $t=microtime(true);
        $this->time_key='Hi';
        $this->day=$date;
        $start=date('Ymd',strtotime($date)).'000000';
        $end=date('Ymd',strtotime('+1 day',strtotime($start))).'000000';
        if(!$this->update($start,$end))
            return false;
        $this->save($date);
        $t=microtime(true)-$t;
        echo " ".flength($t)."\n";
        if($sleep)
            sleep(round($t/5));
    }
    function update_months($start=false,$end=false,$sleep=false)
    {
        $db=get_db();
        $this->load_groups();
        if($start==false)
            $start=$db->selectcol('revision','min(rev_timestamp)');
        if(!$start||strlen($start)<4)
            return false;
        $date=strtotime(date('Ymd',strtotime($start.'01')));
        if($end!==false)
            $end=strtotime(date('Ymt',strtotime($end.'01')));
        echo "updatestats months from ".date('Ym',$date)."\n";
        while(($end===false && $date<=time()) || ($end!==false && $date<=$end)){
            $this->update_month(date('Ym',$date),$sleep);
            $date=strtotime('+1 month',$date);
        }
    }
    function update_month($date,$sleep=false)
    {
        if(strlen($date)!=6){
            echo "Error date format $date\n";
            return false;
        }
        if($date<200001){
            echo "Error date $date\n";
            return false;
        }
        echo "$date";
        $t=time();
        $db=get_db();
        $this->time_key='Ymd';
        $this->sum_type='months';
        $this->month=$date;
        $start=date('Ymd',strtotime($date.'01')).'000000';
        $end=date('Ymd',strtotime('+1 month',strtotime($start))).'000000';
        if(!$this->update($start,$end))
            return false;
        $this->save($date);
        $t=time()-$t;
        echo " ".flength($t)."\n";
        if($sleep)
            sleep(round($t/5));
    }
    function update_time($start,$end)
    {
        return $this->update(date('YmdHis',$start),date('YmdHis',$end));
    }
    function update($start, $end)
    {
        if(strlen($start)!=14||strlen($end)!=14){
            echo "Error date format $start $end\n";
            return false;
        }
        $zone=date_default_timezone_get();
        date_default_timezone_set('GMT');
        $this->start=$start;
        $this->end=$end;
        if(!isset($this->time_key))
            $this->time_key='Hi';
        $this->s=array();
        $this->saved_stats=array();
        if($this->merge_log)
            $this->load_logs($start,$end);
        else
            $this->update_logs($start,$end);
        if(!$this->update_revs($start,$end)){
            echo "\nabort\n\n";
            return false;
        }
        if($this->merge_log && !empty($this->cur_log)){
            $this->update_log($this->cur_log);
            while($this->cur_log=next($this->logs)){
                $this->update_log($this->cur_log);
            }
        }
        if($this->add_pageview)
            $this->update_views();
        if($this->add_archive)
            $this->update_archives($start,$end);
        $this->finish();
        echo ' mem:'.round(memory_get_usage(true)/1048576).'/'.round(memory_get_peak_usage(true)/1048576).'Mb';
        date_default_timezone_set($zone);
        return true;
    }
    function update_user($user)
    {
        if($user=='')
            return false;
        $this->s=array();
        $this->saved_stats=array();
        $this->single_user=true;
        $this->update_revs('','',$user);
    }
    function update_date($date, $stats=false)
    {
        global $conf;
        if($stats!==false)
            $this->stats=$stats;
        $this->sum_total_edits=false;
        $this->limit_sum=false;
        $this->limit_sum_ip=false;
        if(strlen($date)==8){
            if(empty($this->groups))
                $this->load_groups();
            $this->update_day($date);
        }elseif(strlen($date)==6 && $conf['base_calc']=='month'){
            if(empty($this->groups))
                $this->load_groups();
            $this->update_month($date);
        }else{
            $s=$this->stats;
            $this->sum($date,array('stats'),true);
            echo "[".(int)@$this->s['stats']['total']['edit']." edits] ";
            $this->sum_total_edits=(int)@$this->s['stats']['total']['edit'];
            if($this->sum_total_edits>$this->limit_sum_edits && strlen($date)<6){
                echo "[sum limit] ";
                $this->limit_sum=true;
            }
            if(strlen($date)<6 && $conf['stats_sum_users_edits_limit'] && @$this->s['stats']['total']['edit']>=$conf['stats_sum_users_edits_limit']){
                echo "[users limit] ";
                if(($k=array_search('users', $s))!==false)
                    unset($s[$k]);
            }elseif(strlen($date)==6 && !$conf['stats_sum_ip_month']){
                echo "[IP month limit] ";
                $this->limit_sum_ip=true;
            }
            if(strlen($date)<6 && $conf['stats_sum_pages_edits_limit'] && @$this->s['stats']['total']['edit']>=$conf['stats_sum_pages_edits_limit']){
                echo "[pages limit] ";
                if(($k=array_search('pages', $s))!==false)
                    unset($s[$k]);
            }
            if($conf['stats_sum_time_edits_limit'] && @$this->s['stats']['total']['edit']>=$conf['stats_sum_time_edits_limit']){
                echo "[time limit] ";
                if(($k=array_search('time', $s))!==false)
                    unset($s[$k]);
            }
            $this->sum($date,$s);
        }
    }
    function update_views()
    {
        echo ' view:';
        $this->saved_stats['views']['hits']=0;
        $this->saved_stats['views']['hits_unknown_title']=0;
        $this->saved_stats['views']['hits_notedited']=0;
        $this->saved_stats['views']['hits_notedited_ignored']=0;
        if(isset($this->s['pages'])){
            if($this->day)
                pageview_data::get_day($this->day, array($this,'update_view'));
            elseif(@$this->month){
                pageview_data::get_month($this->month, array($this,'update_view'));
            }else
                pageview_data::get_hours(substr($this->start,0,10).'0000', substr($this->end,0,10).'0000', array($this,'update_view'));
            echo $this->saved_stats['views']['hits'];
        }
    }
    function update_view($v)
    {
        $this->saved_stats['views']['hits']+=$v['hits'];
        if($v['title']=='-'){
            $this->saved_stats['views']['hits_unknown_title']+=$v['hits'];
            return;
        }
        if(isset($this->s['pages'][$v['title']])){
            @$this->s['pages'][$v['title']]['hits']+=$v['hits'];
        }elseif($v['hits']>=50){
            $this->s['pages'][$v['title']]=array(
                'ns'=>mwns::get()->get_ns($v['title']),
                'hits'=>$v['hits'],
                );
            $this->saved_stats['views']['hits_notedited']+=$v['hits'];
        }else{
            $this->saved_stats['views']['hits_notedited_ignored']+=$v['hits'];
        }
    }

    function update_revs_query($start,$end,$user='')
    {
        global $conf;

        $q="select /*SLOW_OK updatestats*/ r.*, page_id, page_title, page_namespace, page_is_redirect, rp.rev_len parent_len";
        if($conf['stats_join_rev_sha'])
            $q.=", rp.rev_sha1 parent_sha1, rv.rev_id rv_id, rv.rev_timestamp rv_timestamp";
        if($conf['stats_join_comment'])
            $q.=", comment_text";
        $q.=", actor_user, actor_name";
        $q.=" from revision r left join page on r.rev_page=page_id left join revision rp on r.rev_parent_id=rp.rev_id";
        if($conf['stats_join_rev_sha'])
            $q.=" left join revision rv on r.rev_page=rv.rev_page and r.rev_sha1=rv.rev_sha1 and r.rev_id!=rv.rev_id and r.rev_parent_id!=0 and r.rev_parent_id!=rv.rev_id and rv.rev_timestamp<r.rev_timestamp";
        if($conf['stats_join_comment'])
            $q.=" left join comment_revision on r.rev_comment_id=comment_id";
        $q.=" left join actor_revision on r.rev_actor=actor_id";
        $q.=" where ";
        if($user!='')
            $q.="actor_name='".$this->db2->escape($user)."' and ";//TODO vérifier que l'index est utilisé avec cette jointure
        if($start!='')
            $q.="r.rev_timestamp>='".$this->db2->escape($start)."' and ";
        if($end!='')
            $q.="r.rev_timestamp<'".$this->db2->escape($end)."' and ";
        $q.="1";
        if($conf['stats_join_rev_sha'])
            $q.=" group by r.rev_id";
        $q.=" order by r.rev_timestamp, r.rev_id";
        //echo "\n$q\n";
        return $q;
    }
    function update_revs($start,$end,$user='')
    {
        global $conf;
        $this->last_sum=false;
        $db=get_db();
        if($this->double_db && $this->unbuffered_sql_rev){
            if(!isset($this->db2) || !is_object($this->db2)){
                $this->db2=clone($db);
                $this->db2->open();
                $this->db2->query('SET SESSION net_read_timeout=3600');
                $this->db2->query('SET SESSION net_write_timeout=3600');
            }
        }else
            $this->db2=$db;

        if($start!='' && $end!=''){
            $row=$db->select1("select /*SLOW_OK updatestats count*/ count(*) n, min(rev_timestamp) min, max(rev_timestamp) max from revision where rev_timestamp>='".$db->escape($start)."' and rev_timestamp<'".$db->escape($end)."'");
            $count=$row['n'];
            echo " revs ($count) ";
            if($count==0)
                return true;
            if(!$conf['stats_allow_chunks'] || $count<=$conf['rev_chunk_rows']){
                $q=$this->update_revs_query($start, $end, $user);
                $res=$this->do_update_revs($q);
            }else{
                $start=$row['min'];
                $end=date('YmdHis', strtotime($row['max'])+1);
                $length=strtotime($end)-strtotime($start);
                $chunks=ceil($count/$conf['rev_chunk_rows']);
                $step=ceil($length/$chunks);
                echo " ".round($length/3600)."h $chunks chunks\n";
                $s=$start;
                for($i=0;$i<$chunks;$i++){
                    $e=date('YmdHis', strtotime($s)+$step);
                    if($e>$end)
                        $e=$end;
                    if($e==$s)
                        break;
                    echo "chunk $i ".date('H:i:s', strtotime($s))." ".date('H:i:s', strtotime($e))." : ";
                    $q=$this->update_revs_query($s, $e, $user);
                    $res=$this->do_update_revs($q);
                    $s=$e;
                    echo "\n";
                }
                echo "total revs ".(int)@$this->s['stats']['total']['edit']."\n";
            }
        }else{//probably not used, remove ?
            echo " revs ";
            $q=$this->update_revs_query($start, $end, $user);
            $res=$this->do_update_revs($q);
        }
        return $res;
    }
    function do_update_revs($q)
    {
        global $conf;
        $old_s=$this->s;
        $old_sv=$this->saved_stats;
        $retry=0;
        do{
            $last_count=(int)@$this->s['stats']['total']['edit'];
            if($retry>0)
                echo "\n$retry) $q\n\n";
            $t=microtime(true);
            if($this->unbuffered_sql_rev)
                $this->db2->select_walk($q, array($this,'update_rev'));
            else{
                $rows=$this->db2->select($q);
                if(!empty($rows)){
                    foreach($rows as $v)
                        $this->update_rev($v);
                    $rows=null;
                }
            }
            $t=microtime(true)-$t;
            $count=@$this->s['stats']['total']['edit']-$last_count;
            echo "$count ".flength($t).' '.round($count/$t)."/s";
            /*if(!empty($s=$this->db2->stats()))
                echo ' ('.round($s['bytes_sent']/1024).'ko/'.round($s['bytes_received']/1024).'ko) ';*/
            echo ' '.(isset($this->s['users']) ? count($this->s['users']) : 0).' users '.(isset($this->s['pages']) ? count($this->s['pages']) : 0).' pages';// mem:'.round(memory_get_usage(true)/1048576).'/'.round(memory_get_peak_usage(true)/1048576).'Mb';
            $err=$this->db2->error_no();
            if($err==0){
                $this->db2->ping();
                $err=$this->db2->error_no();
            }
            if($err!=0){
                echo "\nERROR $err\n\n";
                $this->s=$old_s;
                $this->saved_stats=$old_sv;
                $retry++;
                if(isset($conf['stats_update_revs_max_retries']) && $retry>$conf['stats_update_revs_max_retries']){
                    echo "\nerror too many retries\n";
                    return false;
                }
            }
        }while($err!=0);
        return true;
    }

    function update_logs($start,$end)
    {
        //TODO chunks or remove
        $db=get_db();
        $q="select /*SLOW_OK updatestats*/ logging.*, actor_user, actor_name from logging left join actor_logging on log_actor=actor_id where log_type!='patrol' and log_timestamp>='".$db->escape($start)."' and log_timestamp<'".$db->escape($end)."' order by log_timestamp,log_id";
        //todo join user
        echo " logs:";
        $t=microtime(true);
        $db->select_walk($q,array($this,'update_log'));
        $t=microtime(true)-$t;
        echo (int)@$this->s['stats']['total']['log'].' '.flength($t).' '.round((@$this->s['stats']['total']['log']/1000)/$t,2).' k/s';
    }

    function load_logs_query($start, $end)
    {
        $db=get_db();
        return "select /*SLOW_OK updatestats*/ logging.*, actor_user, actor_name from logging left join actor_logging on log_actor=actor_id where log_type!='patrol' and log_timestamp>='".$db->escape($start)."' and log_timestamp<'".$db->escape($end)."' order by log_timestamp,log_id";
    }
    function load_logs($start, $end)
    {
        global $conf;
        $db=get_db();
        unset($this->cur_log);
        $this->logs=[];
        $row=$db->select1("select /*SLOW_OK updatestats count*/ count(*) n, min(log_timestamp) min, max(log_timestamp) max from logging where log_timestamp>='".$db->escape($start)."' and log_timestamp<'".$db->escape($end)."' and log_type!='patrol'");
        $count=$row['n'];
        echo " logs ($count) ";
        if($count==0)
            return;
        if(!$conf['stats_allow_chunks'] || $count<=$conf['log_chunk_rows']){
            $q=$this->load_logs_query($start, $end);
            $res=$this->do_update_logs($q);
        }else{
            $start=$row['min'];
            $end=date('YmdHis', strtotime($row['max'])+1);
            $length=strtotime($end)-strtotime($start);
            $chunks=ceil($count/$conf['log_chunk_rows']);
            $step=ceil($length/$chunks);
            echo " ".round($length/3600)."h $chunks chunks\n";
            $s=$start;
            for($i=0;$i<$chunks;$i++){
                $e=date('YmdHis', strtotime($s)+$step);
                if($e>$end)
                    $e=$end;
                if($e==$s)
                    break;
                echo "chunk $i ".date('H:i:s', strtotime($s))." ".date('H:i:s', strtotime($e))." : ";
                $q=$this->load_logs_query($s, $e);
                $res=$this->do_update_logs($q);
                $s=$e;
                echo "\n";
            }
            echo "total logs ".count($this->logs)."\n";
        }
        reset($this->logs);
    }

    function do_update_logs($q)
    {
        $db=get_db();
        $t=microtime(true);
        $rows=$db->select($q);
        $t=microtime(true)-$t;
        if(empty($this->logs))
            $this->logs=$rows;
        else
            foreach($rows as $v)
                $this->logs[]=$v;
        $count=count($rows);
        $rows=null;
        echo "$count ".flength($t).' '.round($count/$t).'/s';
        return $this->logs!==false;
    }

    function update_archives($start,$end,$user='')
    {
        $db=get_db();
        if($this->double_db){
            $db2=clone($db);
            $db2->open();
        }else
            $db2=$db;
        $q="select * from archive where ";
        if($user!='')
            $q.="ar_user_text='".$db2->escape($user)."' and ";
        if($start!='')
            $q.="ar_timestamp>='".$db2->escape($start)."' and ";
        if($end!='')
            $q.="ar_timestamp<'".$db2->escape($end)."' and ";
        $q.="1 order by ar_timestamp,ar_rev_id";
        $t=microtime(true);
        $db2->select_walk($q,array($this,'update_archive'));
        $t=microtime(true)-$t;
        echo ' '.(int)@$this->s['stats']['total']['archive_edit'].' arch '. round((@$this->s['stats']['total']['archive_edit']/1000)/$t,1)."k/s ";
        if($this->double_db)
            $db2->close();
    }

    function sum_all($date,$sumtotal=false,$stats=false)
    {
        if($stats!==false)
            $this->stats=$stats;
        echo "Sum all date:$date stats:".implode(',',$this->stats)."\n";
        $dirs=$this->subdirs($date);
        if($date==0){
            foreach($dirs as $dir)
                $this->sum_all($dir,$sumtotal);
            if($sumtotal)
                $this->sum(0);
        }elseif(strlen($date)==4){
            foreach($dirs as $dir){
                $this->sum($date.$dir);
                unset($this->s);
            }
            if($sumtotal)
                $this->sum($date);
        }elseif(strlen($date)==6){
            $this->sum($date);
            if($sumtotal)
                $this->sum(substr($date,0,4));
        }else
            echo "Error date\n";
    }
    function sum_years($years='',$sumtotal=false,$stats=false)
    {
        if($stats!==false)
            $this->stats=$stats;
        echo "Sum years $years stats:".implode(',',$this->stats)."\n";
        if($years==''|$years==0)
            $years=$this->subdirs();
        elseif(!is_array($years))
            $years=explode(',',$years);
        foreach($years as $y)
            if(strlen($y)==4)
                $this->sum($y);
        if($sumtotal)
            $this->sum(0);
    }
    function sum($date, $stats=false, $calc_only=false)
    {
        if($date==0)
            $this->last_sum=true;
        else
            $this->last_sum=false;
        if($stats=='')
            $stats=$this->stats;
        switch(strlen($date)){
            case 1: if($date==0)$this->sub_type='years';$this->sum_type='';break; // sum all
            case 4: $this->sub_type='months';$this->sum_type='years';break; // sum year
            case 6: $this->sub_type='days';$this->sum_type='months';break; // sum month
            default:
                echo "Error date (YYYY/YYYYMM): $date\n";
                return false;
        }
        if(!$calc_only)
            echo "$date sum : ";
        $this->saved_stats=array();
        $this->partial_pages_data=false;
        $dirs=$this->subdirs($date);
        foreach($stats as $stat){
            if(!$calc_only)
                echo $stat;
            if(self::$separate_ip && $stat=='users'){
                if(!$this->limit_sum_ip)
                    $sub_stats=array('user', 'ip');
                else
                    $sub_stats=array('user');
            }else
                $sub_stats=array(false);
            foreach($sub_stats as $sub_stat){
                $this->s=array();
                foreach($dirs as $dir){
                    if(!$calc_only)
                        echo '.';
                    switch($this->sub_type){
                        case 'days' :
                            $date_stat=$date.$dir;
                            $this->time_key_base=$date.$dir;
                            $this->time_key_len=0;
                            break;
                        case 'months' :
                            $date_stat=$date.$dir;
                            $this->time_key_base='';
                            $this->time_key_len=8;
                            break;
                        case 'years' :
                            $date_stat=$dir;
                            $this->time_key_base='';
                            $this->time_key_len=8;
                            break;
                    }
                    if($data=$this->load_stat($date_stat, $stat, $sub_stat)){
                        call_user_func(array($this,'sum_'.$stat), $data, $sub_stat);
                        unset($data);
                    }
                }
                if(!$calc_only && isset($this->s[$stat]))
                    echo ' '.count($this->s[$stat]);
                $this->finish();
                if(!$calc_only){
                    echo ' ';
                    $this->save_stat($date, $stat, $sub_stat);
                    echo ' ';
                }
            }
        }
        if(!$calc_only)
            echo ' revs:'.(int)@$this->s['stats']['total']['edit'].'  mem:'.round(memory_get_usage(true)/1048576).'/'.round(memory_get_peak_usage(true)/1048576)."Mb\n";
    }
    function user_type($user,$id)
    {
        if(isset($this->s['users'][$user]['type']))
            return $this->s['users'][$user]['type'];
        if($id==0){
            return 'ip';
        }elseif(isset($this->groups[$user]['bot'])||preg_match('/bot$/i',$user))
            return 'bot';
        else
            return 'user';
    }
    function update_archive($v)
    {
        global $conf;
        $time=strtotime($v['ar_timestamp']);
        $s=&$this->s;
        $tk=date($this->time_key,$time);
        $user=$v['ar_user_text'];
        $user_type=$this->user_type($user,$v['ar_user']);
        $ns=$v['ar_namespace'];
        $nscateg=mwTools::ns_categ($ns);

        @$s['stats']['archive_edit']++;
        @$s['stats']['archive_size']+=$v['ar_len'];
        @$s['stats']['archive_editors'][$user_type]++;

        if($v['ar_text_id']==0)
            @$s['stats']['archive_no_text']++;
        if($v['ar_sha1']=='')
            @$s['stats']['archive_no_sha1']++;
        @$s['stats']['archive_ns'][$ns]++;
        @$s['stats']['archive_ns_type'][$ns][$user_type]++;
        if(in_array('users',$this->stats)){
            if(!isset($s['users'][$user]['id']))
                $s['users'][$user]['id']=$v['ar_user'];
            if(!isset($s['users'][$user]['type']))
                $s['users'][$user]['type']=$user_type;
            @$s['users'][$user]['archive_edit']++;
            @$s['users'][$user]['archive_nscateg'][$nscateg]++;
            @$s['users'][$user]['archive_ns'][$ns]++;
        }
        if(in_array('time',$this->stats)){
            @$s['time'][$tk]['archive_edit']++;
            @$s['time'][$tk]['archive_ns'][$ns]++;
            @$s['time'][$tk]["archive_$nscateg"]++;
            @$s['time'][$tk]["archive_edit_$user_type"]++;
        }
    }
    function update_rev($v)
    {
        global $conf;
        $time=strtotime($v['rev_timestamp']);
        if($this->single_user){
            $ti=date('Ym',$time);
            if(!isset($this->dates[$ti]))
                $this->dates[$ti]=array();
            $this->s=&$this->dates[$ti];
        }
        $s=&$this->s;
        if($this->merge_log){
            if(!isset($this->cur_log) && !empty($this->logs)){
                $this->cur_log=reset($this->logs);
                if(isset($this->cur_log['log_timestamp']))
                    $this->cur_log['time']=strtotime($this->cur_log['log_timestamp']);
            }
            while(isset($this->cur_log) && is_array($this->cur_log) && $this->cur_log['time']<$time){
                $this->update_log($this->cur_log);
                $this->cur_log=next($this->logs);
                if(is_array($this->cur_log) && isset($this->cur_log['log_timestamp']))
                    $this->cur_log['time']=strtotime($this->cur_log['log_timestamp']);
            }
        }
        $tk=date($this->time_key,$time);
        $user=isset($v['user_name']) ? $v['user_name'] : (isset($v['actor_name']) ? $v['actor_name'] : null);
        $user_id=isset($v['actor_user']) ? $v['actor_user'] : 0;
        if($user==''){
            if($user_id!=0)
                echo " [no user name  ? user id $user_id ]";
            $user=$this->missing_user_name;
        }
        $user_type=$this->user_type($user, $user_id);
        $ipv6= $user_type=='ip' && preg_match('/([A-Fa-f0-9]{1,4}:){7}[A-Fa-f0-9]{1,4}/',$user);
        if(in_array('users',$this->stats)){
            if(!isset($s['users'][$user]['id']))
                $s['users'][$user]['id']=$user_id;
            if(!isset($s['users'][$user]['type'])){
                $s['users'][$user]['type']=$user_type;
                if($ipv6)
                    $s['users'][$user]['ipv6']=true;
            }
        }
        $comment = isset($v['comment_text']) ? $v['comment_text'] : (isset($v['rev_comment'])?$v['rev_comment']:null);//use comment_text if new table comment is used
        if($user_type=='user' && mwtools::is_bot_comment($comment))
            $user_type='bot';
        $revert=mwTools::is_revert($comment);
        $revert_sha=isset($v['rv_timestamp']) && isset($v['parent_sha1']) && $v['rv_id']!='' && $v['rev_sha1']!=$v['parent_sha1'];
        $no_page=array_key_exists('page_id', $v) && is_null($v['page_id']);
        $new=$v['rev_parent_id']==0;
        if($new && $conf['stat_confirm_new_page']){
            if(!$this->double_db && $this->unbuffered_sql_rev)
                echo "error unbuffered_sql_rev without double_db, stat_confirm_new_page not possible\n";
            else
                $new=$this->confirm_new_page($v);
        }
        $page=mwns::get()->ns_title($v['page_title'], $v['page_namespace']);
        if(!array_key_exists('rev_diff', $v) && array_key_exists('parent_len', $v))
            $v['rev_diff']=$v['rev_len']-$v['parent_len'];
        $diffabs=abs(@$v['rev_diff']);
        $ns=$v['page_namespace'];
        $nscateg=mwTools::ns_categ($ns);
        $redir=$v['page_is_redirect'];
        if($no_page)
            $redir=true;
        if($new)
            $newkey= $redir ? 'redirect' : $nscateg ;
        $difft=$time-@$s['users'][$user]['last_common'];
        if($difft<0)
            echo "[Error difft neg rev $difft]";

        $difft1=$difft > $this->total_time_max ? $this->total_time_base : $difft;
        $difft2=$difft > $this->total_time_max2 ? $this->total_time_base : $difft;
        $difft3=$difft > $this->total_time_max3 ? $this->total_time_base : $difft;

        if(!isset($s['stats']['update']['first']))
            $s['stats']['update']['first']=$v['rev_timestamp'];
        $s['stats']['update']['last']=$v['rev_timestamp'];

        foreach(array('total', $user_type) as $k){
            @$s['stats'][$k]['total']++;
            @$s['stats'][$k]['edit']++;
            @$s['stats'][$k]['ns'][$ns]++;
            @$s['stats'][$k]['nscateg'][$nscateg]++;
            if($nscateg=='article' && !$redir)
                @$s['stats'][$k]['article_edits']++;
            @$s['stats'][$k]['diff']+=$v['rev_diff'];
            if($v['rev_diff']>0)
                @$s['stats'][$k]['diff_add']+=$v['rev_diff'];
            elseif($v['rev_diff']<0)
                @$s['stats'][$k]['diff_sub']+=$v['rev_diff'];
            @$s['stats'][$k]['diff_tot']+=$diffabs;
            @$s['stats'][$k]['tot_size']+=$v['rev_len'];
            @$s['stats'][$k]['diff_ns'][$nscateg]+=$v['rev_diff'];
            @$s['stats'][$k]['diff_tot_ns'][$nscateg]+=$diffabs;
            @$s['stats'][$k]['tot_size_ns'][$nscateg]+=$v['rev_len'];
            if($new){
                @$s['stats'][$k]['new']['total']++;
                @$s['stats'][$k]['new'][$newkey]++;
                @$s['stats'][$k]['new_ns'][$ns]++;
            }
            if($revert)
                @$s['stats'][$k]['revert']++;
            if($revert_sha){
                @$s['stats'][$k]['revert_sha']++;
            }
            if($v['rev_minor_edit'])
                @$s['stats'][$k]['minor']++;
            if($v['rev_deleted'])
                @$s['stats'][$k]['deleted_rev']++;
            if($v['rev_len']==0)
                @$s['stats'][$k]['empty_rev']++;
            @$s['stats'][$k]['tot_time']+=$difft1;
            @$s['stats'][$k]['tot_time2']+=$difft2;
            @$s['stats'][$k]['tot_time3']+=$difft3;
            @$s['stats'][$k]['tot_time2_nscateg'][$nscateg]+=$difft2;
            @$s['stats'][$k]['tot_time2_ns'][$ns]+=$difft2;
        }
        $k='misc';
        if($ipv6)
            @$s['stats'][$k]['ipv6_edits']++;
        if($v['rev_len']===null)
            @$s['stats'][$k]['no_len']++;
        if(is_null(@$v['rev_diff']))
            @$s['stats'][$k]['no_diff']++;
        if($v['rev_sha1']=='')
            @$s['stats'][$k]['no_sha1']++;
        if($user_type!='ip' && $user_id==0)
            @$s['stats'][$k]['no_user_id']++;
        if($no_page)
            @$s['stats'][$k]['no_page']++;

        if(in_array('users',$this->stats)){
            @$s['users'][$user]['edit']++;
            if($user_type=='bot')
                @$s['users'][$user]['bot_edit']++;
            @$s['users'][$user]['diff']+=$v['rev_diff'];
            if($v['rev_diff']>0)
                @$s['users'][$user]['diff_add']+=$v['rev_diff'];
            elseif($v['rev_diff']<0)
                @$s['users'][$user]['diff_sub']+=$v['rev_diff'];
            @$s['users'][$user]['diff_ns'][$nscateg]+=$v['rev_diff'];
            if($revert){
                @$s['users'][$user]['diff_rv']+=$v['rev_diff'];
                if($nscateg=='article')
                    @$s['users'][$user]['diff_rv_article']+=$v['rev_diff'];
            }
            @$s['users'][$user]['diff_tot']+=$diffabs;
            @$s['users'][$user]['tot_size']+=$v['rev_len'];
            $dsize=(abs(@$v['rev_diff']) < 100) ? 'small' : (abs(@$v['rev_diff']) < 1000 ? 'medium' : 'big');
            if($nscateg=='article' && !$revert){
                @$s['users'][$user]['diffs'][$dsize]++;
            }
            @$s['users'][$user]['nscateg'][$nscateg]++;
            @$s['users'][$user]['ns'][$ns]++;
            if($no_page)
                @$s['users'][$user]['no_page']++;
            if($nscateg=='article' && !$redir)
                @$s['users'][$user]['edit_article']++;
            if($revert)
                @$s['users'][$user]['revert']++;
            if($new){
                @$s['users'][$user]['new']['total']++;
                @$s['users'][$user]['new'][$newkey]++;
            }

            $diffte=$time-@$s['users'][$user]['last_edit'];
            if($page!='' && $user!=''){
                if(isset($s['users'][$user]['last_page']) && $s['users'][$user]['last_page']!=$page && $diffte<=$this->chain_max){
                    @$s['users'][$user]['edit_chain']++;
                    if($new){
                        @$s['users'][$user]['new_chain']['total']++;
                        @$s['users'][$user]['new_chain'][$newkey]++;
                    }
                    @$s['users'][$user]['chain_ns'][$nscateg]++;
                }elseif((isset($s['users'][$user]['last_page']) && $s['users'][$user]['last_page']==$page && $diffte<=$this->redit_user_max)
                ||(isset($s['pages'][$page]['last_edit']) && @$s['pages'][$page]['last_user']==$user && ($time-@$s['pages'][$page]['last_edit']<=$this->redit_page_max))){
                    @$s['users'][$user]['redit']++;
                    @$s['users'][$user]['redit_ns'][$nscateg]++;
                    if(in_array('pages',$this->stats))
                        @$s['pages'][$page]['redit']++;
                }
            }
            @$s['users'][$user]['tot_time']+=$difft1;
            @$s['users'][$user]['tot_time2']+=$difft2;
            @$s['users'][$user]['tot_time2_ns'][$ns]+=$difft2;
            @$s['users'][$user]['tot_time3']+=$difft3;
            $s['users'][$user]['last_edit']=$time;
            $s['users'][$user]['last_page']=$page;
            $s['users'][$user]['last_common']=$time;
        }

        if(in_array('pages',$this->stats)){
            if(!isset($s['pages'][$page]['ns']))
                $s['pages'][$page]['ns']=$ns;
            @$s['pages'][$page]['edit']++;
            @$s['pages'][$page]['edit_'.$user_type]++;
            @$s['pages'][$page]['diff']+=$v['rev_diff'];
            @$s['pages'][$page]['diff_tot']+=$diffabs;
            @$s['pages'][$page]['tot_size']+=$v['rev_len'];
            $s['pages'][$page]['size']=$v['rev_len'];
            if($new)
                $s['pages'][$page]['new']=true;
            if($revert){
                @$s['pages'][$page]['revert']++;
                $s['pages'][$page]['last_revert']=$time;
            }
            @$s['pages'][$page]['list_'.$user_type][$user]++;
            @$s['pages'][$page]['last_user']=$user;
            $s['pages'][$page]['last_edit']=$time;
        }

        if(in_array('time',$this->stats)){
            @$s['time'][$tk]['all']++;
            @$s['time'][$tk]['edit']++;
            if($new){
                @$s['time'][$tk]['new']['total']++;
                @$s['time'][$tk]['new'][$newkey]++;
                @$s['time'][$tk][$user_type.'_new'][$newkey]++;
            }
            if($revert)
                @$s['time'][$tk]['revert']++;
            @$s['time'][$tk]['ns'][$ns]++;
            @$s['time'][$tk][$nscateg]++;
            @$s['time'][$tk][$user_type.'_edit']++;
            @$s['time'][$tk][$user_type.'_edits'][$nscateg]++;
            @$s['time'][$tk]['diff'][$nscateg]+=$v['rev_diff'];
            if($user_id>0)
                @$s['time'][$tk]['list_'.$user_type][$user_id]=true;
            else
                @$s['time'][$tk]['list_'.$user_type][$user]=true;
            if($user_type=='user'){
                if(isset($this->groups[$user])){
                    foreach(array_keys($this->groups[$user]) as $k)
                        @$s['stats']['groups'][$k]++;
                    if(isset($this->groups[$user]['sysop']))
                        @$s['time'][$tk]['sysop']++;
                }else{
                    @$s['stats']['groups']['peon']++;
                    @$s['time'][$tk]['peon']++;
                }
            }
        }
        /*
        if($s['stats']['total']['edit']%10000==0){
            $db=get_db();
            $db->ping();
        }
        */
    }
    function confirm_new_page($rev)
    {
        $db=get_db();
        $rows=$db->select('select rev_id from revision where rev_page='.(int)$rev['rev_page'].' order by rev_timestamp, rev_id limit 1');
        if(isset($rows[0]['rev_id']) && $rows[0]['rev_id']==$rev['rev_id'])
            return true;
        return false;
    }

    function update_log($v)
    {
        global $conf;
        $s=&$this->s;
        $time=strtotime($v['log_timestamp']);
        $tk=date($this->time_key,$time);
        $user=isset($v['user_name']) ? $v['user_name'] : $v['actor_name'];
        $user_id=isset($v['actor_user']) ? $v['actor_user'] : 0;
        if($user==''){
            if($user_id!=0)
                echo " [no log username for id $user_id]";
            $user=$this->missing_user_name;
        }
        $user_type=$this->user_type($user, $user_id);
        if(isset($v['log_comment']) && $user_type=='user' && mwtools::is_bot_comment($v['log_comment']))//log_comment manquant ?
            $user_type='bot';
        if(!isset($s['stats']['update']['first']))
            $s['stats']['update']['first']=$v['log_timestamp'];
        $s['stats']['update']['last']=$v['log_timestamp'];

        @$s['time'][$tk]['all']++;
        @$s['time'][$tk]['log']++;
        @$s['time'][$tk][$user_type.'_logs'][$v['log_type']]++;

        $difft=$time-@$s['users'][$user]['last_common'];
        if($difft<0)
            echo "[Error difft neg log $difft]";
        $difft1=$difft > $this->total_time_max ? $this->total_time_base : $difft;
        $difft2=$difft > $this->total_time_max2 ? $this->total_time_base : $difft;
        $difft3=$difft > $this->total_time_max3 ? $this->total_time_base : $difft;

        foreach(array('total', $user_type) as $k){
            @$s['stats'][$k]['total']++;
            @$s['stats'][$k]['log']++;
            @$s['stats'][$k]['logs'][$v['log_type']][$v['log_action']]++;
            @$s['stats'][$k]['logs_ns'][$v['log_type']][$v['log_action']][$v['log_namespace']]++;
            @$s['stats'][$k]['tot_time']+=$difft1;
            @$s['stats'][$k]['tot_time2']+=$difft2;
            @$s['stats'][$k]['tot_time3']+=$difft3;
            @$s['stats'][$k]['tot_time_log']+=$difft1;
            @$s['stats'][$k]['tot_time2_log']+=$difft2;
            @$s['stats'][$k]['tot_time3_log']+=$difft3;
            @$s['stats'][$k]['tot_time2_log_types'][$v['log_type']]+=$difft2;
        }

        $logsysop=false;
        switch($v['log_type']){
            case 'protect' :
                if($v['log_action']=='move_prot')
                    break;
            case 'delete' :
                if($v['log_action']=='delete_redir')
                    break;
                    // https://phabricator.wikimedia.org/T145991
                    // select min(log_timestamp) from logging where log_type="delete" and log_action="delete_redir" : 20161202022103
            case 'block' :
            case 'gblblock' :
            case 'merge' :
                $logsysop=true;
                break;
            case 'renameuser':
            case 'rights':
                if(in_array('users',$this->stats))
                    @$s['users'][$user]['log_bubu']++;
                break;
            case 'newusers':
                @$s['time'][$tk]['new_user']++;
                break;
            default:
        }
        if($logsysop){
            @$s['time'][$tk]['log_sysop']++;
            if(in_array('users',$this->stats))
                @$s['users'][$user]['log_sysop']++;
            @$s['stats']['total']['log_sysop']++;
            @$s['stats'][$user_type]['log_sysop']++;
        }
        if(in_array('users',$this->stats)){
            if(!isset($s['users'][$user]['id']))
                $s['users'][$user]['id']=$user_id;
            if(!isset($s['users'][$user]['type']))
                $s['users'][$user]['type']=$user_type;
            @$s['users'][$user]['log']++;
            @$s['users'][$user]['logs'][$v['log_type']][$v['log_action']]++;
            $difft=$time-@$s['users'][$user]['last_log'];
            if(isset($s['users'][$user]['last_log']) && $difft<=$this->chain_max){
                @$s['users'][$user]['log_chain']++;
                if($logsysop)
                    @$s['users'][$user]['log_sysop_chain']++;
            }
        }
        if(in_array('users',$this->stats)){
            @$s['users'][$user]['tot_time']+=$difft1;
            @$s['users'][$user]['tot_time2']+=$difft2;
            @$s['users'][$user]['tot_time3']+=$difft3;
            @$s['users'][$user]['tot_time2_logs'][$v['log_type']]+=$difft2;
            $s['users'][$user]['last_log']=$time;
            $s['users'][$user]['last_common']=$time;
        }
    }
    function finish()
    {
        global $conf;
        echo " F";
        if(isset($this->s['pages'])){
            $this->saved_stats['pages']['total']=0;
            $this->saved_stats['pages']['edited']=0;
            $this->saved_stats['pages']['tot_size_latest']=0;
            $weights=array();
            foreach($this->s['pages'] as $k=>$v){
                $this->saved_stats['pages']['total']++;
                if(@$v['edit']>0)
                    $this->saved_stats['pages']['edited']++;
                foreach(array('user', 'ip', 'bot') as $uk){
                    if(isset($v["list_$uk"]))
                        $this->s['pages'][$k]["u$uk"]=count($v["list_$uk"]);
                    if($conf['stats_limit_users_per_page'] && isset($v["list_$uk"]) && count($v["list_$uk"])>50){
                        echo "[stats_limit_users_per_page ".count($v["list_$uk"])."]";
                        arsort($this->s['pages'][$k]["list_$uk"]);
                        $i=0;
                        foreach(array_keys($this->s['pages'][$k]["list_$uk"]) as $key)
                            if(++$i>50)
                                unset($this->s['pages'][$k]["list_$uk"][$key]);
                    }
                }
                $this->s['pages'][$k]['utot']=@$this->s['pages'][$k]['uuser']+@$this->s['pages'][$k]['uip']+@$this->s['pages'][$k]['ubot'];
                if(isset($v['size'])){
                    $this->saved_stats['pages']['tot_size_latest']+=$v['size'];
                    $nscateg=mwTools::ns_categ($v['ns']);
                    @$this->saved_stats['pages']['tot_size_latest_ns'][$nscateg]+=$v['size'];
                }
                $this->s['pages'][$k]['diff_abs']=abs(@$v['diff']);
                $weight=$this->page_weight($this->s['pages'][$k]);
                $this->s['pages'][$k]['weight']=round($weight,2);
                $weights[$k]=round($weight, 6);
            }
            if($this->saved_stats['pages']['total']>$conf['stats_max_pages']){
                echo " [evict ";
                $evicted=$this->saved_stats['pages']['total']-$conf['stats_max_pages'];
                if($evicted>$conf['stats_max_pages']){
                    $new=array();
                    $i=0;
                    arsort($weights);
                    foreach(array_keys($weights) as $k){
                        $new[$k]=$this->s['pages'][$k];
                        unset($this->s['pages'][$k]);
                        if(++$i==$conf['stats_max_pages'])
                            break;
                    }
                    $this->s['pages']=$new;
                }else{
                    asort($weights);
                    $i=0;
                    foreach(array_keys($weights) as $k){
                        unset($this->s['pages'][$k]);
                        if(++$i==$evicted)
                            break;
                    }
                }
                echo "$evicted]";
                $this->saved_stats['pages']['notsaved']=$evicted;
                $this->saved_stats['pages']['saved']=$conf['stats_max_pages'];
            }
            echo ' '.$this->saved_stats['pages']['total'].' total pages';
            if(@$this->saved_stats['pages']['saved']!=0)
                echo ' '.@$this->saved_stats['pages']['saved'].' saved';
            if(@$this->saved_stats['pages']['notsaved']!=0)
                echo ' '.@$this->saved_stats['pages']['notsaved'].' not saved';
        }
        if(isset($this->s['users'])){
            @$this->saved_stats['users']['total']=count($this->s['users']);
            foreach($this->s['users'] as $k=>$v){
                @$this->saved_stats['users'][$v['type']]['total']++;
                if(@$v['ipv6'])
                    @$this->saved_stats['users']['ipv6']++;
                $newusers=0;
                if(isset($v['logs']['newusers']) && is_array($v['logs']['newusers']))
                    $newusers=array_sum($v['logs']['newusers']);
                $log_ignore=@$v['log']-@$v['logs']['patrol']['patrol']-$newusers-@$v['logs']['spamblacklist']['hit'];
                if(@$v['edit']==0 && $log_ignore==0){
                    unset($this->s['users'][$k]);
                    continue;
                }
                $this->s['users'][$k]['weight']=$this->user_weight($v);
                $this->s['users'][$k]['diff_abs']=abs(@$v['diff']);
                $this->s['users'][$k]['total']=@$v['edit']+@$v['log']-@$v['logs']['patrol']['patrol'];
                $this->s['users'][$k]['total_sysop']=@$v['log_sysop'] + @$v['ns'][NS_MEDIAWIKI];
                foreach(array(1,5,20,100) as $threshold){
                    if(@$v['edit']>=$threshold)
                        @$this->saved_stats['users'][$v['type']]['threshold_edits'][$threshold]++;
                    if(@$v['total_sysop']>=$threshold)
                        @$this->saved_stats['users'][$v['type']]['threshold_sysop'][$threshold]++;
                    if(isset($v['nscateg']))
                        foreach($v['nscateg'] as $nscateg=>$n)
                            if($n>=$threshold)
                                @$this->saved_stats['users'][$v['type']]['threshold_ns'][$nscateg][$threshold]++;
                }
                if(isset($this->sum_type) && $this->sum_type!='')
                    $this->s['users'][$k][$this->sum_type]=1;//months, (years)
            }
        }
        if(isset($this->s['stats'])){
            if(!empty($this->saved_stats))
                foreach($this->saved_stats as $k=>$v)
                    $this->s['stats'][$k]=$v;
            if($this->partial_pages_data)
                $this->s['stats']['pages']['partial_data']=true;
            $this->s['stats']['update']['last_update']=gmdate('YmdHis');
        }
    }
    function page_weight($v)
    {
        $weight=8*@$v['uuser']+5*@$v['uip']+3*@$v['ubot'];
        $weight+=@$v['edit']+@$v['revert']*0.33-@$v['redit']*0.75-@$v['edit_bot']*0.66;
        if(@$v['edit']>=2)
            $weight+=2;
        if(@$v['diff_tot']<=10000)
            $wd=@$v['diff_tot']/1000;
        elseif(@$v['diff_tot']<=100000)
            $wd=10+@$v['diff_tot']/10000;
        else
            $wd=20+@$v['diff_tot']/100000;
        $diffabs=abs(@$v['diff']);
        if($diffabs<=10 && @$v['diff_tot']>=100000)
            $wd*=0.3;
        elseif($diffabs<=10 && @$v['diff_tot']>=1000)
            $wd*=0.5;
        $weight+=$wd;
        if($diffabs<=1000)
            $weight+=$diffabs/200;
        elseif($diffabs<=10000)
            $weight+=5+$diffabs/2000;
        else
            $weight+=10+$diffabs/100000;
        $b=10;
        if(isset($this->s['stats']['update']['first'])){
            if(date('Ymd',strtotime($this->s['stats']['update']['first']))>=20150501)
                $b=10;//new pageview dataset with spider ua filtering
            else
                $b=1.5;
        }
        if(@$v['hits']<=100)
            $weight+=$b*@$v['hits']/100;
        elseif(@$v['hits']<=500)
            $weight+=$b*@$v['hits']/100+1;
        elseif(@$v['hits']<=1000)
            $weight+=$b*@$v['hits']/100+2;
        elseif(@$v['hits']<=10000)
            $weight+=$b*(10+@$v['hits']/1000)+4;
        elseif(@$v['hits']<=100000)
            $weight+=$b*(20+@$v['hits']/10000)+6;
        elseif(@$v['hits']<=1000000)
            $weight+=$b*(30+@$v['hits']/100000)+8;
        elseif(@$v['hits']<=10000000)
            $weight+=$b*(40+@$v['hits']/1000000)+10;
        else
            $weight+=$b*(50+@$v['hits']/10000000)+15;
        if(@$v['hits']>0 && @$v['hits']<750){
            if(@$v['hits']<=50)
                $weight*=0.75;
            elseif(@$v['hits']<=100)
                $weight*=0.80;
            elseif(@$v['hits']<=250)
                $weight*=0.85;
            elseif(@$v['hits']<=500)
                $weight*=0.9;
            else
                $weight*=0.95;
        }
        if($v['utot']<=1)
            $weight*=0.9;
        if(@$v['edit']>0){
            if(@$v['revert']>0){
                $pr=@$v['revert']/@$v['edit'];
                if($diffabs<=3){
                    if($pr>=0.70)
                        $weight*=0.6;
                    elseif($pr>=0.5)
                        $weight*=0.7;
                    elseif($pr>=0.33)
                        $weight*=0.8;
                }else{
                    if($pr>=0.70)
                        $weight*=0.75;
                    elseif($pr>=0.6)
                        $weight*=0.8;
                    elseif($pr>=0.5)
                        $weight*=0.85;
                    elseif($pr>=0.33)
                        $weight*=0.9;
                }
            }elseif($diffabs<=5)
                $weight*=0.9;
        }
        if($this->weight_last_edit && isset($v['last_edit'])){
            $diff=date('U')-date('Z')-$v['last_edit'];
            if($diff>=64800)//18h
                $weight*=0.70;
            elseif($diff>=43200)//12h
                $weight*=0.75;
            elseif($diff>=32400)//9h
                $weight*=0.80;
            elseif($diff>=21600)//6
                $weight*=0.85;
            elseif($diff>=10800)//3
                $weight*=0.90;
            elseif($diff>=3600)
                $weight*=1;
            elseif($diff>=1800)
                $weight*=1.05;
            else
                $weight*=1.1;
        }
        return $weight;
    }
    function user_weight($v)
    {
        $weight=@$v['edit']+(@$v['log']-@$v['logs']['patrol']['patrol'])+@$v['revert'];
        if(@$v['tot_time']<=3600)
            $weight+=@$v['tot_time']/600;
        else
            $weight+=6+@$v['tot_time']/3600;

        if(@$v['diff_tot']<=10000)
            $weight+=@$v['diff_tot']/1000;
        elseif(@$v['diff_tot']<=100000)
            $weight+=10+@$v['diff_tot']/10000;
        else
            $weight+=20+@$v['diff_tot']/100000;

        if(@$v['tot_size']<=100000)
            $weight+=@$v['tot_size']/20000;
        elseif(@$v['tot_size']<=1000000)
            $weight+=5+@$v['tot_size']/200000;
        else
            $weight+=10+@$v['tot_size']/2000000;
        return $weight;
    }
    function sum_stats($data)
    {
        if(!isset($this->s['stats']))
            $this->s['stats']=array();
        foreach($data as $k=>$v){
            switch($k){
                case 'users':
                case 'pages':
                    break;
                case 'update':
                    if(!isset($this->s['stats'][$k]['first'])||$this->s['stats'][$k]['first']>$v['first'])
                        $this->s['stats'][$k]['first']=$v['first'];
                    if(!isset($this->s['stats'][$k]['last'])||$this->s['stats'][$k]['last']<$v['last'])
                        $this->s['stats'][$k]['last']=$v['last'];
                    break;
                default:
                    if(!isset($this->s['stats'][$k]))
                        $this->s['stats'][$k]=$v;
                    elseif(!is_array($v)){
                        if(!is_array($this->s['stats'][$k]))
                            $this->s['stats'][$k]+=$v;
                        else
                            echo "Error sum_stats array mismatch '$k' is array v:'$v'\n";
                    }else{
                        if(is_array($this->s['stats'][$k]))
                            $this->s['stats'][$k]=array_sum_recursive($this->s['stats'][$k], $v);
                        else{
                            echo "Error sum_stats array mismatch '$k' is not an array\n";
                            $this->s['stats'][$k]=$v;
                        }
                    }
            }
        }
    }
    function sum_users($data)
    {
        foreach($data as $user=>$stats){
            if($this->limit_sum && $stats['id']==0)
                continue;
            if(!isset($this->s['users'][$user])){
                $this->s['users'][$user]=$stats;
            }else{
                foreach($stats as $k=>$v){
                    switch($k){
                        case 'type':
                        case 'id':
                            break;
                        case 'last_edit':
                            if(!isset($this->s['users'][$user]['last_edit'])||$this->s['users'][$user]['last_edit']<$v)
                                $this->s['users'][$user]['last_edit']=$v;
                            break;
                        case 'logs':
                            foreach($v as $type=>$actions)
                                foreach($actions as $action=>$vv)
                                    @$this->s['users'][$user][$k][$type][$action]+=$vv;
                            break;
                        default:
                            if(is_array($v)) {
                                foreach($v as $kk=>$vv)
                                    @$this->s['users'][$user][$k][$kk]+=$vv;
                            }else{
                                @$this->s['users'][$user][$k]+=$v;
                            }
                    }
                }
            }
            @$this->s['users'][$user][$this->sub_type]++;//days/months
        }
    }
    function sum_pages($data)
    {
        global $conf;
        $reduce=$this->reduce && count($data)>=$this->reduce_load_limit_sum;
        if($conf['stats_max_pages'] && count($data)>=$conf['stats_max_pages'])
            $this->partial_pages_data=true;
        foreach($data as $page=>$stats){
            if(isset($stats['title'])){//TEMP old format compat
                $page=mwns::get()->ns_title($stats['title'], $stats['ns']);
                unset($stats['title']);
            }
            if(!isset($this->s['pages'][$page])){
                if($reduce && $stats['weight']<=$this->reduce_min_sum){
                    @$this->saved_stats['pages']['reduced_sum']++;
                    if(!$this->partial_pages_data)
                        $this->partial_pages_data=true;
                    continue;
                }
                $this->s['pages'][$page]=$stats;
            }else{
                foreach($stats as $k=>$v){
                    switch($k){
                        case 'ns':
                        case 'title':
                        case 'uuser':
                        case 'uip':
                        case 'ubot':
                        case 'utot':
                            break;
                        case 'size':
                            $this->s['pages'][$page]['size']=$v;
                            break;
                        case 'list_ip':
                            if($this->limit_sum)
                                break;
                        case 'list_user':
                        case 'list_bot':
                            foreach($v as $kk=>$vv)
                                @$this->s['pages'][$page][$k][$kk]+=$vv;
                            break;
                        default:
                            if(is_array($v))
                                foreach($v as $kk=>$vv)
                                    @$this->s['pages'][$page][$k][$kk]+=$vv;
                            else
                                @$this->s['pages'][$page][$k]+=$v;
                    }
                }
            }
            @$this->s['pages'][$page][$this->sub_type]++;
        }
    }
    function sum_time($data)
    {
        foreach($data as $tk=>$stats){
            $key=$this->time_key_base.substr($tk,0,$this->time_key_len);
            if(isset($last_key) && $last_key!=$key)
                $this->finish_time_key($last_key);
            foreach($stats as $k=>$v){
                switch($k){
                    case 'uuser':
                    case 'uip':
                    case 'ubot':
                    case 'utot':
                        break;
                    case 'list_ip':
                    case 'list_user':
                    case 'list_bot':
                        foreach($v as $kk=>$vv)
                            @$this->s['time'][$key][$k][$kk]+=$vv;
                        break;
                    default:
                        if(is_array($v))
                            foreach($v as $kk=>$vv)
                                @$this->s['time'][$key][$k][$kk]+=$vv;
                        else
                            @$this->s['time'][$key][$k]+=$v;
                }
            }
            $last_key=$key;
        }
        $this->finish_time_key($last_key);
    }
    function finish_time_key($tk)
    {
        foreach(array('user', 'ip', 'bot') as $k){
            $this->s['time'][$tk]["u$k"]=isset($this->s['time'][$tk]["list_$k"]) ? count($this->s['time'][$tk]["list_$k"]) : 0;
            if($this->last_sum){
                $this->s['time'][$tk]["list_$k"]=null;
                unset($this->s['time'][$tk]["list_$k"]);
            }
        }
        $this->s['time'][$tk]['utot']=@$this->s['time'][$tk]['uuser']+@$this->s['time'][$tk]['uip']+@$this->s['time'][$tk]['ubot'];
    }

    static function export_stats($date)
    {
        $keys=array();
        if($date!='months'){
            if($date=='')
                $file='years';
            else
                $file=$date;
            $dirs=self::subdirs($date);
            foreach($dirs as $dir){
                $date_stat=$date.$dir;
                if($data=self::load_stat($date_stat,'stats')){
                    $keys=array_merge_recursive2($keys,$data);
                    $dates[]=$date_stat;
                }
            }
        }else{
            $date='';
            $file='months';
            $dirs=self::subdirs($date);
            foreach($dirs as $dir){
                $months=self::subdirs($dir);
                foreach($months as $month){
                    $date_stat=$dir.$month;
                    if($date_stat==date('Ym'))
                        continue;
                    if($data=self::load_stat($date_stat,'stats')){
                        $keys=array_merge_recursive2($keys,$data);
                        $dates[]=$date_stat;
                    }
                }
            }
        }
        $keyrow=array();
        foreach($keys as $k=>$v)
            if(!is_array($v))
                $keyrow[]=$k;
            else
                foreach($v as $kk=>$vv)
                    if(!is_array($vv))
                        $keyrow[]="$k//$kk";
                    else
                        foreach($vv as $kkk=>$vvv)
                            if(!is_array($vvv))
                                $keyrow[]="$k//$kk//$kkk";
                            else
                                echo "$k-$kk-$kkk is array\n";
        natsort($keyrow);
        $s=' ';
        $o='#1';
        $i=1;
        foreach($keyrow as $v)
            $o.=$s. ++$i;
        $o.="\n";
        $o.='#Date';
        $o.=$s.str_replace('//', '-', implode($s,$keyrow))."\n";
        foreach($dates as $d){
            $o.=$d;
            $data=self::load_stat($d,'stats');
            foreach($keyrow as $k){
                $kk=explode('//',$k);
                $o.=$s;
                switch(count($kk)){
                    case 1 :
                        $o.=(int)@$data[$kk[0]];
                        break;
                    case 2 :
                        $o.=(int)@$data[$kk[0]][$kk[1]];
                        break;
                    case 3 :
                        $o.=(int)@$data[$kk[0]][$kk[1]][$kk[2]];
                        break;
                    default:
                        echo "Error $k\n";
                        $o.='-';
                }
            }
            $o.="\n";
        }
        file_put_contents("ctrl/out/stats_$file.txt",$o);
        return $o;
    }
    static function export_years()
    {
        self::export_stats('months');
        $data=file("ctrl/out/stats_months.txt");
        $head=$data[0].$data[1];
        unset($data[0]);
        unset($data[1]);
        foreach($data as $v){
            $v=explode(' ',rtrim($v));
            $y=substr($v[0],0,4);
            unset($v[0]);
            foreach($v as $kk=>$vv)
                $dates[$y][$kk][]=$vv;
        }
        $avg=$sum=$head;
        foreach($dates as $year=>$values){
            $avg.="$year";
            $sum.="$year";
            foreach($values as $v){
                $avg.=" ".round(array_sum($v)/count($v));
                $sum.=" ".round(array_sum($v));
            }
            $avg.="\n";
            $sum.="\n";
        }
        file_put_contents("ctrl/out/stats_years_avg.txt",$avg);
        file_put_contents("ctrl/out/stats_years_sum.txt",$sum);
    }

    static function stats_months()
    {
        $res=array();
        $dirs=self::subdirs('');
        foreach($dirs as $dir){
            $months=self::subdirs($dir);
            foreach($months as $month){
                $date=$dir.$month;
                if($date==date('Ym'))
                    continue;
                $res[$date]=array();
                if($data=self::load_stat($date,'stats')){
                    $res[$date]=$data;
                }
            }
        }
        return $res;
    }
    static function stats_months_keys()
    {
        $s=self::stats_months();
        $res=array();
        foreach($s as $k=>$v)
            $res=array_merge($res,self::stats_months_keys_sub('',$v));
        natsort($res);
        return $res;
    }
    static function stats_months_keys_sub($pre='', $s=array())
    {
        $res=array();
        foreach($s as $k=>$v){
            $k=$pre==''?$k:"$pre-$k";
            if(!is_array($v))
                $res[$k]=$k;
            elseif(!empty($v))
                $res=array_merge($res,self::stats_months_keys_sub($k,$v));
        }
        return $res;
    }
    static function export_pages_rv()
    {
        $pages=array();
        $pages_edits=array();
        $dirs=self::subdirs('');
        foreach($dirs as $dir){
            $months=self::subdirs($dir);
            foreach($months as $month){
                $days=self::subdirs($dir.$month);
                foreach($days as $day){
                    $date_stat=$dir.$month.$day;
                    if($date_stat>date('Ymd'))
                        break;
                    echo "$date_stat ";
                    if($data=self::load_stat($date_stat,'pages')){
                        foreach($data as $k=>$v){
                            if(!isset($v['ns']) || $v['ns']!=0 || !isset($v['edit']) || $v['edit']<1)
                                continue;
                            @$pages_edits[$k]+=$v['edit'];
                            if(!isset($v['revert']) || $v['revert']<1)
                                continue;
                            @$pages[$k]+=$v['revert'];
                        }
                    }
                    echo count($pages)." ".count($pages_edits)."\n";
                }
            }
        }
        arsort($pages);
        $i=0;
        $o="<table class='pages_reverts sortable'>\n";
        $o.="<thead><tr><th></th><th>Titre</th><th>Révocations</th><th>%</th><th>Éditions</th></tr></thead><tbody>\n";
        foreach($pages as $k=>$v){
            if(++$i==1001)
                break;
            $title=mwtools::rtitle($k);
            $r=fnum(round(100*$v/$pages_edits[$k],1), 1)." %";
            $link='<a href="https://fr.wikipedia.org/wiki/'.str_replace(' ','_',$title).'">'.$title.'</a>';
            $o.="<tr><td>$i</td><td style='text-align:left'>$link</td><td>".fnum($v)."</td><td>$r</td><td>".fnum($pages_edits[$k])."</td></tr>\n";
            echo "$i $title $v $r ".fnum($pages_edits[$k])."\n";
        }
        $o.="</tbody></table>\n";
        file_put_contents(self::data_path.'/export_pages_rv.txt', $o);
    }
    static function read_export_pages_rv()
    {
        $f=self::data_path.'/export_pages_rv.txt';
        if(file_exists($f))
            return file_get_contents($f);
        return "file not found";
    }

    static function path($date=0)
    {
        global $conf;
        $path=self::data_path;
        if($date!=0){
            $date=(int)$date;
            $len=strlen($date);
            if($len==8)
                $path=$path.'/'.substr($date,0,4).'/'.substr($date,4,2).'/'.substr($date,6,2);
            if($len==6)
                $path=$path.'/'.substr($date,0,4).'/'.substr($date,4,2);
            if($len==4)
                $path=$path.'/'.substr($date,0,4);
            if($len<=2)
                $path=$path.'/live/'.$date;
        }
        return $path;
    }

    static function subdirs($date='')
    {
        $path=self::path($date);
        $dirs=array();
        if(is_dir($path)){
            foreach(scandir($path) as $v){
                if(substr($v,0,1)!='.' && is_numeric($v) && is_dir($path.'/'.$v))
                    $dirs[]=$v;
            }
        }
        return $dirs;
    }
    function save($date)
    {
        echo " S";
        foreach($this->stats as $stat)
            $this->save_stat($date,$stat);
    }
    function save_stat($date, $stat, $sub_stat=false)
    {
        foreach(self::get_stat_files($date, $stat, $sub_stat) as $f)
            unlink($f);
        if(!isset($this->s[$stat]))
            $this->s[$stat]=array();
        $path=$this->path($date);
        if(!is_dir($path)){
            mkdir($path, 0770, true);
            chgrp($path, 'www-data');
        }
        if(!is_dir($path)){
            debug_print_backtrace();
            die("invalid path '$path' for date '$date'\n");
        }
        $file=$path.'/'.$stat;
        if($sub_stat!==false){
            $file.="_".$sub_stat;
            foreach($this->s[$stat] as $k=>$v)
                if(($sub_stat=='ip' && $v['type']!='ip') || ($sub_stat!='ip' && $v['type']=='ip'))
                    unset($this->s[$stat][$k]);
        }
        if(count($this->s[$stat])<$this->max_save_row)
            $this->write($file,$this->s[$stat]);
        else{
            $i=0;
            $fi=0;
            $data=array();
            foreach($this->s[$stat] as $k=>$v){
                if(++$i<=$this->max_save_row){
                    $data[$k]=$v;
                    unset($this->s[$stat][$k]);
                }else{
                    echo '+';
                    $this->write($file.$fi,$data);
                    $data=array();
                    $fi++;
                    $i=0;
                }
            }
            if(!empty($data))
                $this->write($file.$fi,$data);
        }
        if($stat=='stats')
            $this->save_date($date);
        if($stat=='stats'||$stat=='time')
            $this->save_global_stats($date);
    }

    function save_date($date)
    {
        $data=array(
            'date'=>$date,
            'edits'=>(int)@$this->s['stats']['total']['edit'],
            'logs'=>(int)@$this->s['stats']['total']['log'],
            'pages'=>(int)@$this->s['stats']['pages']['total'],
            'refresh'=>0,
            );
        if(isset($this->s['stats']['users'])){
            $data['users']=@$this->s['stats']['users']['user']['total']+@$this->s['stats']['users']['bot']['total'];
            $data['users_edit']=@$this->s['stats']['users']['user']['threshold_edits'][1]+@$this->s['stats']['users']['bot']['threshold_edits'][1];
            if(isset($this->s['stats']['users']['ip']))
                $data['ip']=$this->s['stats']['users']['ip']['total'];
        }
        Dates::update($data);
    }
    function save_global_stats($date)
    {
        global $conf;
        if(!$conf['multi'])
            return;
        require_once('include/wikis.php');
        if($date==0)
            $key='total';
        elseif($date==24)
            $key='live';
        else
            return;
        Wikis::update_global_stats(function($data) use($key){
            if(isset($this->s['stats']))
                $data[$key]['stats']=$this->s['stats'];
            if(isset($this->s['time']) && $key=='total')
                $data[$key]['time']=self::average_time_months($this->s['time'], 4);
            return $data;
        });
    }

    static function average_time_months($time, $round=false)
    {
        $months=array();
        foreach($time as $k=>$v)
            $months[substr($k,0,6)][]=$v;
        foreach($months as $month=>$vals){
            $total=array();
            $count=count($vals);
            foreach($vals as $stats)
                foreach($stats as $key=>$val)
                    if(!is_array($val))
                        @$total[$key]+=$val/$count;
                    else
                        foreach($val as $kk=>$vv)
                            @$total[$key][$kk]+=$vv/$count;
            $months[$month]=$total;
        }
        if($round!==false){
            foreach($months as $month=>$stats){
                foreach($stats as $key=>$val)
                    if(!is_array($val))
                        $months[$month][$key]=round($val, $round);
                    else
                        foreach($val as $kk=>$vv)
                            $months[$month][$key][$kk]=round($vv, $round);
            }
        }
        return $months;
    }
    static function reduce_time_months($source_months, $reduce=2, $average=false)
    {
        $months=array();
        $count=0;
        foreach($source_months as $month=>$stats){
            $count++;
            if($count==1){
                $months[$month]=$stats;
                $last_month=$month;
                continue;
            }
            foreach($stats as $key=>$val)
                if(!is_array($val))
                    @$months[$last_month][$key]+=$val;
                else
                    foreach($val as $kk=>$vv)
                        @$months[$last_month][$key][$kk]+=$vv;
            if($count==$reduce){
                if($average)
                    foreach($months[$last_month] as $key=>$val)
                        if(!is_array($val))
                            @$months[$last_month][$key]/=$count;
                        else
                            foreach($val as $kk=>$vv)
                                @$months[$last_month][$key][$kk]/=$count;
                $count=0;
            }
        }
        return $months;
    }

    static function load_stat($date, $stat, $sub_stat=false)
    {
        $file=self::path($date).'/'.$stat;
        if(file_exists($file)){
            $data=self::read($file);
            if($data!==false && $sub_stat!==false)
                foreach($data as $k=>$v)
                    if(($sub_stat=='ip' && $v['type']!='ip') || ($sub_stat!='ip' && $v['type']=='ip'))
                        unset($data[$k]);
            return $data;
        }
        $res=array();
        foreach(self::get_stat_files($date, $stat, $sub_stat) as $f)
            if(file_exists($f)){
                $data=self::read($f);
                if($data!==false){
                    if($sub_stat===false){
                        foreach($data as $k=>$v)
                            $res[$k]=$v;
                    }else{
                        foreach($data as $k=>$v)
                            if(($sub_stat=='ip' && $v['type']=='ip') || ($sub_stat!='ip' && $v['type']!='ip'))
                                $res[$k]=$v;
                    }
                }
                $data=null;
            }else
                break;
        return $res;
    }
    static function get_stat_files($date, $stat, $sub_stat=false)
    {
        $file=self::path($date).'/'.$stat;
        if(file_exists($file))
            return array($file);
        $res=array();
        for($i=0;$i<50;$i++){
            $f=$file.$i;
            if(file_exists($f))
                $res[]=$f;
            else
                break;
        }
        if(empty($res)){
            if($sub_stat!==false)
                return self::get_stat_files($date, $stat."_".$sub_stat);
            if(file_exists(self::path($date).'/'.$stat."_user") || file_exists(self::path($date).'/'.$stat."_user0"))
                $res=array_merge($res, self::get_stat_files($date, $stat."_user"));
            if(file_exists(self::path($date).'/'.$stat."_ip") || file_exists(self::path($date).'/'.$stat."_ip0"))
                $res=array_merge($res, self::get_stat_files($date, $stat."_ip"));
        }
        return $res;
    }
    static function get_stat_time($date, $stat, $sub_stat=false)
    {
        $files=self::get_stat_files($date, $stat);
        if(empty($files))
            return null;
        list($res)=$files;
        return filemtime($res);
    }
    static function write($file,$data)
    {
        $data=serialize($data);
        if(self::$compress){
            $data=gzcompress($data);
        }
        if(!file_exists($file)){
            if($fp=fopen($file, "w"))
                fclose($fp);
        }
        if($fp = fopen($file, "r+")){
            if (flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                fwrite($fp, $data);
                flock($fp, LOCK_UN);
            } else {
                echo "Couldn't get the lock!";
                return false;
            }
            fclose($fp);
            chgrp($file, 'www-data');
            chmod($file, 0660);
        }
        return false;
    }
    static function read($file)
    {
        $data=false;
        if(DEBUG) Debug::mem('read file');
        if($fp = fopen($file, "r")){
            if (flock($fp, LOCK_SH)) {
                $data=fread($fp, filesize($file));
                flock($fp, LOCK_UN);
                if(self::$compress){
                    if(DEBUG) Debug::mem('decompress');
                    if(ord($data[0]) == 0x78 && in_array(ord($data[1]),array(0x01,0x5e,0x9c,0xda))){
                        $t=microtime(true);
                        $data= gzuncompress($data);
                        if(DEBUG) Debug::info('decompress time',round(1000*(microtime(true)-$t)).' ms');
                    }
                }
                $data=unserialize($data);
            } else {
                echo "Couldn't get the lock!";
            }
            fclose($fp);
            return $data;
        }
        return false;
    }


}

?>