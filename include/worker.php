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
require_once('include/worker_master.php');
require_once('include/wikis.php');

class Worker
{
    var $config;
    var $lock_file="/tmp/worker_lock";
    var $count_after_stats=false;
    var $count_after_sum=false;
    var $userstats_months_graphs_expire="15 days";
    static $size_names=[
        0=>'none',
        1=>'small',
        2=>'medium',
        3=>'big',
        4=>'large',
    ];
    function __construct($type=false, $size=false)
    {
        $this->type=$type;
        $this->size=$size;
        include('config/worker_config.php');
        $this->config=$worker_config;
    }
    function select_site()
    {
        $res=false;
        if(!$this->get_lock())
            return false;
        $db=get_dbg();
        $rows=$this->get_next($this->type, $this->size, 1, strtotime("-".$this->config[$this->size][$this->type]));
        if(!empty($rows)){
            $res=$rows[0]['site_global_key'];
            $col="last_{$this->type}";
            $db->update("sites_stats", "site_global_key", $res, array($col=>gmdate('Y-m-d H:i:s')));
        }
        $this->release_lock();
        return $res;
    }
    function get_next($type, $size, $limit=1, $min_time=false)
    {
        $db=get_dbg();
        $col="last_{$type}";
        $where=array('disabled=0');
        if(($size_where=$this->size_where($size))!='')
            $where[]=$size_where;
        if($min_time!==false)
            $where[]="(`$col` is null or `$col`<'".date('Y-m-d H:i:s', $min_time)."')";
        $rows=$db->select("select site_global_key, `$col` `last` from sites_stats where ".implode(' and ', $where)." order by `$col` limit $limit");
        return $rows;
    }
    function size_where($size=false)
    {

        switch($size){
            case 'all':
                return '1';
            case 'small+':
                return 'size>=1';
            case 'medium+':
                return 'size>=2';
            case 'small':
                return 'size=1';
            case 'medium':
                return 'size=2';
            case 'big':
                return 'size=3';
            case 'large':
                return 'size=4';
        }
        die("size where size '$size' ??");
        /*
        switch($size){
            case 'all':
                return '1';
            case 'small+':
                return 'size>=1';
            case 'medium+':
                return 'size>=2';
            case 'verysmall':
            case 'small':
                return 'size=1';
            case 'medium':
                return '(size=2 or size=3)';
            case 'big':
                return 'size=4';
            case 'large':
                return 'size=5';
        }
        die("size where size '$size' ??");
        */
    }
    /*
    function get_size($edits, $users)
    {
        foreach($this->config as $size=>$v){
            if($size=='default')
                continue;
            if($v['min_edits']!==false && $edits<$v['min_edits'])
                continue;
            if($v['min_users']!==false && $users<$v['min_users'])
                continue;
            if(($v['max_edits']!==false || $v['max_users']!==false) && ($v['max_edits']===false || $edits>=$v['max_edits']) && ($v['max_users']===false || $users>=$v['max_users']))
                continue;
            return $size;
        }
        return false;
    }*/

    function update($site)
    {
        return call_user_func(array($this, "update_{$this->type}"), $site);
    }

    function update_count($site=null)
    {
        global $conf;
        $db=get_db();
        if($site!='' && !$db->select_db($site."_p"))
            return false;
        $db->query('SET SESSION net_read_timeout=10800');
        $db->query('SET SESSION net_write_timeout=10800');
        $db->query('SET SESSION wait_timeout=10800');
        //$stats=wikis::get_site_stats($site);
        $stats=[
            'total_rev'=>['table'=>'revision', 'id'=>'rev_id'],
            'total_log'=>['table'=>'logging', 'id'=>'log_id'],
            'total_archive'=>['table'=>'archive', 'id'=>'ar_id'],
            'total_user'=>['table'=>'user', 'id'=>'user_id'],
            'total_rev_user'=>['table'=>'user', 'id'=>'user_id', 'where'=>'user_editcount>=1'],
            //'total_page'=>['table'=>'page', 'id'=>'page_id'],
            'total_page'=>['local_stats_column' => 'ss_total_pages'],
            //'total_article'=>['table'=>'page', 'id'=>'page_id', 'where'=>'page_namespace=0 and page_is_redirect=0'],
            'total_article'=>['local_stats_column' => 'ss_good_articles'],
            'total_redirect'=>['table'=>'page', 'id'=>'page_id', 'where'=>'page_is_redirect=1'],
            //'total_file'=>['table'=>'page', 'id'=>'page_id', 'where'=>'page_namespace=6 and page_is_redirect=0'],
            'total_file'=>['local_stats_column' => 'ss_images'],
            ];
        $chunk=1000000;
        $maxs=[];
        foreach($stats as $stat=>$v){
            if(isset($v['local_stats_column'])){
                echo "$v[local_stats_column] ";
                $data[$stat]=$db->select_col("select $v[local_stats_column] from `site_stats`");
            }else{
                if(!isset($maxs[$v['table']])){
                    $max=$db->selectcol("select /*SLOW_OK count*/ `$v[id]` from `$v[table]` order by `$v[id]` desc limit 1");
                    $maxs[$v['table']]=$max;
                }else
                    $max=$maxs[$v['table']];
                echo "$v[table] $max ".ceil($max/$chunk)." ";
                $data[$stat]=null;
                for($i=0;$i<=$max;$i+=$chunk){
                    $data[$stat]+=$db->selectcol("select /*SLOW_OK count*/ count(*) from `$v[table]` where `$v[id]` between $i and ".($i+$chunk-1).(isset($v['where']) ? " and ".$v['where'] : ''));
                    echo '.';
                }
            }
            echo " ".$data[$stat]."\n";
        }
        $data['total_rev_log_user']=null;
        $data['last_count']=gmdate('Y-m-d H:i:s');
        if($conf['multi'])
            $data['size']=self::wiki_size($data);
        $total=$data['total_log']+$data['total_rev'];
        if($conf['multi'])
            $data['base_calc']=$total<=$conf['base_calc_max_month'] ? 'month' : 'day';

        print_r($data);
        if($conf['multi']){
            $dbg=get_dbg();
            $dbg->update('sites_stats', 'site_global_key', $site, $data);
            require_once('include/wikis.php');
            Wikis::update_score($site);
        } else {
            $dbs=get_dbs();
            $dbs->update('site_stats', $data, '1=1');
        }
    }

    function update_bench($site)
    {
        global $conf;
        $db=get_db();
        if(!$db->select_db($site."_p"))
            return false;
        $db->ping();
        $limit=50000;
        $s=[];

        $s['revision']=$this->bench($db, "select /* bench */ rev_id, rev_timestamp from revision order by rev_timestamp desc limit $limit");
        $s['logging']=$this->bench($db, "select /* bench */ log_id, log_timestamp from logging order by log_timestamp desc limit $limit");

        $limit=20000;
        $min=date('YmdHis', strtotime('-1 day'));
        $max=date('YmdHis');

        $s['logging_join']=$this->bench($db, "select /* bench */ logging.*, actor_user, actor_name from logging left join actor on log_actor=actor_id and log_deleted&4 = 0 where log_type!='patrol' and log_timestamp between '$min' and '$max' order by log_timestamp, log_id limit $limit");

        $q = "select /* bench */ r.*, page_id, page_title, page_namespace, page_is_redirect, rp.rev_len parent_len, comment_text, actor_user, actor_name ";
        $q .= " from revision r left join page on r.rev_page=page_id left join revision rp on r.rev_parent_id=rp.rev_id ";
        if($conf['stats_join_comment']) {
            if($conf['stats_join_revision_comment_temp'])
                $q.=" left join revision_comment_temp on revcomment_rev = r.rev_id and r.rev_deleted&2 = 0 left join comment on revcomment_comment_id=comment_id ";
            else
                $q.=" left join comment on r.rev_comment_id=comment_id and r.rev_deleted&2 = 0 ";
        }
        if($conf['stats_join_revision_actor_temp'])
            $q .= " left join revision_actor_temp on revactor_rev = r.rev_id and r.rev_deleted&4 = 0 left join actor on revactor_actor=actor_id ";
        else
            $q .= " left join actor on r.rev_actor=actor_id and r.rev_deleted&4 = 0 ";
        $q .= " where r.rev_timestamp between '$min' and '$max' order by r.rev_timestamp, r.rev_id limit $limit";
        
        $s['revision_join']=$this->bench($db, $q);

        pr($s);
    }

    function bench($db, $q)
    {
        $t=microtime(true);
        $rows=$db->select($q);
        $n=0;
        if(!empty($rows))
            $n=count($rows);
        $t=microtime(true)-$t;
        $err=$db->error_no();
        if($err==0){
            $db->ping();
            $err=$db->error_no();
        }
        $r=['count'=>$n, 'time'=>$t, 'speed'=>$n/$t, 'error'=>$err!=0];
        pr($r);
        return $r;
    }

    function wiki_size($s)
    {
        global $conf;
        if(!isset($conf['wiki_sizes'])){
            echo "error missing conf wiki_sizes\n";
            return 0;
        }
        $sizes=$conf['wiki_sizes'];
        krsort($sizes);
        foreach($sizes as $size=>$min)
            if($s['total_rev']>=$min)
                return $size;
        return 0;
    }

    function setup_multi($site)
    {
        require_once('include/wikis.php');
        $wikis=new Wikis();
        $wikis->init($site);
    }

    function update_stats($site)
    {
        global $conf;
        $this->setup_multi($site);

        require_once('include/runner.php');
        $t=time();
        $run=new Runner();
        if($conf['base_calc']=='month')
            $run->run_args(array('', 'fullupdate_months'));
        else
            $run->run_args(array('', 'fullupdate'));
        $t=time()-$t;
        $db=get_dbg();
        $db->update("sites_stats", "site_global_key", $site, array("duration_stats"=>$t));
        if($this->count_after_stats)
            $this->update_count($site);
        echo "end $site\n";
    }

    function update_live($site)
    {
        global $conf;
        $this->setup_multi($site);
        require_once('include/update_stats.php');
        ini_set('memory_limit', '7000M');
        $t=time();
        $up=new UpdateStats();
        foreach($conf['live_hours'] as $h){
            $last=Dates::get($h);
            $key='live_expire_'.$h.'h';
            if($h==48){
                $last24=Dates::get(24);
                if(!isset($last24['edits']) || $last24['edits']>$conf['live_max24h_edits_for48h'])
                    continue;
            }
            if(!isset($last['last_update']) || !isset($conf[$key]) || strtotime($conf[$key], strtotime($last['last_update']))<=time()){
                $up->update_last_hours($h);
            }
        }
        $curd=gmdate('Ymd');
        for($i=7; $i>=1; $i--){
            $lastd=gmdate('Ymd',strtotime("-$i day"));
            $nextd=gmdate('Ymd',strtotime("-".($i-1)." day"));
            $last=Dates::get($lastd);
            if((!isset($last['last_update']) || strtotime($conf['live_expire_other_days'], strtotime($last['last_update']))<=time()) && $last['last_update']<=$nextd.'080000'/*refresh for daily pageviews, available at about 4-6 am*/)
                $up->update_date($lastd);
        }
        $last=Dates::get($curd);
        if(!isset($last['last_update']) || strtotime($conf['live_expire_curent_day'], strtotime($last['last_update']))<=time())
            $up->update_date($curd);
        $t=time()-$t;
        $db=get_dbg();
        $db->update("sites_stats", "site_global_key", $site, array("duration_live"=>$t));
    }

    function update_sum($site)
    {
        global $conf;
        require_once('include/update_stats.php');
        require_once('include/userstats.php');
        $this->setup_multi($site);
        date_default_timezone_set('GMT');
        ini_set('memory_limit', '6000M');
        $t=time();
        $ip=false;
        $up=new UpdateStats();
        $us=new UserStats();
        $curm=gmdate('Ym');
        $lastm=gmdate('Ym',strtotime('-1 month'));
        $cury=substr($curm,0,4);
        $lasty=substr($lastm,0,4);
        $d=Dates::get($lastm);
        if($d['last_update']<$curm.'01000000'){
            $up->update_date($lastm);
            $us->update($lastm, false);
            if($lasty!=$cury){
                $up->update_date($lasty);
                $us->sum($lasty);
            }
            if($ip){
                $us->ip_mode(true);
                $us->update($lastm, false);
                if($lasty!=$cury){
                    $up->update_date($lasty);
                    $us->sum($lasty);
                }
                $us->ip_mode(false);
            }
        }
        $stats=Wikis::get_site_stats(Wikis::current_site());
        $limit= $conf['recent_sum_limit_min_pages'] && isset($stats['total_page']) && $stats['total_page']>=$conf['recent_sum_limit_min_pages'];
        if($limit)
            echo " [total page limit]";
        $last_pages=$up->get_stat_time($curm, 'pages');
        if(!$limit || $last_pages==null || strtotime($conf['recent_sum_expire_month'], $last_pages)<=time())
            $stats=array('time','users','pages','stats');
        else
            $stats=array('time','users','stats');
        $up->update_date($curm, $stats);

        $last_pages=$up->get_stat_time($cury, 'pages');
        if(!$limit || $last_pages==null || strtotime($conf['recent_sum_expire_year'], $last_pages)<=time())
            $stats=array('time','users','pages','stats');
        else
            $stats=array('users','stats');
        $up->update_date($cury, $stats);

        $last_pages=$up->get_stat_time(0, 'pages');
        if(!$limit || $last_pages==null || strtotime($conf['recent_sum_expire_total'], $last_pages)<=time())
            $stats=array('time','users','pages','stats');
        else
            $stats=array('users','stats');
        $up->update_date(0, $stats);
        unset($up);

        $us->update($curm, false);
        $us->sum($cury);
        $us->sum(0);
        if($ip){
            $us->ip_mode(true);
            $us->update($curm, false);
            $us->sum($cury);
            $us->sum(0);
            $us->ip_mode(false);
        }
        unset($us);

        $t=time()-$t;
        $db=get_dbg();
        $db->update("sites_stats", "site_global_key", $site, array("duration_sum"=>$t));
        echo "$site done $t s\n";
        if($this->count_after_sum){
            $t=time();
            $this->update_count($site);
            $t=time()-$t;
            echo "count $t s\n";
        }
    }

    function update_misc($site=false)
    {
        $this->update_userstats_month_graphs($site);
    }

    function update_userstats_month_graphs($site=false)
    {
        if($site!==false){
            $this->setup_multi($site);
            ini_set('memory_limit', '4000M');
        }
        require_once('include/userstats.php');
        $us=new UserStats();
        if($us->months_graphs_data_exists()){
            $time=filemtime($us->months_graphs_file());
            $month_start=strtotime(date('Ym').'01000000');
            if($time>$month_start && $time>strtotime("-".$this->userstats_months_graphs_expire))
                return false;
        }
        $us->months_graphs_data_update();
    }

    function update_groups($site)
    {
        $this->setup_multi($site);
        $api=wiki_api('https://www.wikidata.org/w/api.php');
        $data=$api->entity('Q3681760');
        $groups=array();
        $db=get_db();
        $rows=$db->select('select /*SLOW_OK update_groups*/ user_id, user_name, ug_group from user_groups,user where ug_user=user_id');
        foreach($rows as $v)
            $groups[$v['ug_group']][$v['user_id']]=$v['user_name'];
        $categ_bots=array();
        if(isset($data['sitelinks'][$site])){
            $cat=$data['sitelinks'][$site]['title'];
            $cat=preg_replace('!^.+?:!', '', $cat);
            echo "Category load : $cat\n";
            $cat=str_replace(' ', '_', $cat);
            $cats=array($cat);
            foreach($db->select("select /*SLOW_OK update_groups*/ page_title from categorylinks,page where cl_to='".$db->escape($cat)."' and cl_from=page_id and page_namespace=14") as $v)
                $cats[]=$v['page_title'];
            print_r($cats);
            foreach($cats as $v){
                $rows=$db->select("select /*SLOW_OK update_groups*/ user_name, user_id from categorylinks,page,user where cl_to='".$db->escape($v)."' and cl_from=page_id and page_namespace=2 and user_name=replace(page_title,'_',' ')");
                foreach($rows as $v)
                    $categ_bots[$v['user_id']]=$v['user_name'];
            }
            echo count($categ_bots)." categ bots\n";
            foreach($categ_bots as $id=>$name)
                $groups['bot'][$id]=$name;
        }else
            echo "No Wikidata sitelinks for Category:Wikimedia bots Q3681760\n";
        $db=get_dbs();
        $db->query('start transaction');
        $db->query('delete from user_groups');
        foreach($groups as $group=>$users){
            echo " $group ";
            echo count($users);
            foreach($users as $id=>$name)
                $db->insert('user_groups', array('ug_user'=>$id,'user_name'=>$name,'ug_group'=>$group));
        }
        $db->query('commit');
        echo "\n";
        $this->update_global_bots($site, isset($groups['bot'])?$groups['bot']:array());
        echo "Done\n";
    }
    function update_global_bots($site, $bots)
    {
        $bots=array_flip($bots);
        $n=count($bots);
        $db=get_dbg();
        $rows=$db->select("select user_name from wiki_bots where wiki='".$db->escape($site)."'");
        foreach($rows as $k=>$v)
            if(isset($bots[$v['user_name']]))
                unset($rows[$k], $bots[$v['user_name']]);
        echo "global bots $n (".count($rows)." old ".count($bots)." new)\n";
        foreach($rows as $v)
            $db->delete('wiki_bots', "wiki='".$db->escape($site)."' and user_name='".$db->escape($v['user_name'])."'");
        foreach($bots as $name=>$id)
            $db->insert('wiki_bots', array('wiki'=>$site, 'user_name'=>$name));
    }

    function get_lock()
    {
        $this->lock_handle = fopen($this->lock_file, "w");
        return flock($this->lock_handle, LOCK_EX);
    }
    function release_lock()
    {
        return flock($this->lock_handle, LOCK_UN);
    }

    function refresh_time()
    {
        return $this->config[$this->size][$this->type];
    }

    function stats()
    {
        include('config/worker_config.php');
        $db=get_dbg();
        $q="SELECT size, count(*), sum(duration_live)/60, sum(duration_sum)/60, sum(duration_stats)/86400 FROM `sites_stats` group by 1";
        $rows=$db->select($q);
        echo "$q\n";
        foreach(['size', 'count', 'live', 'sum', 'stats'] as $k)
            echo str_pad($k, 7, ' ', STR_PAD_LEFT).' ';
        echo "\n";
        foreach($rows as $v){
            foreach($v as $kk=>$vv)
                echo str_pad(round($vv), 7, ' ', STR_PAD_LEFT).' ';
            echo "\n";
        }
    }

    function stats_old($save_data=false)
    {
        include('config/worker_config.php');
        $db=get_dbg();
        $rows=$db->select("select size, count(*) n, sum(total_rev+total_log) tot, sum(total_rev) revs from sites_stats group by size");
        echo "new sizes\n";
        foreach($rows as $v)
            echo "$v[size] ".str_pad($v['n'],3,' ', STR_PAD_LEFT)." wikis ".str_pad(round($v['tot']/1000),8,' ', STR_PAD_LEFT)."k tot ".str_pad(round($v['revs']/1000),8,' ', STR_PAD_LEFT)."k revs\n";
        $types=array();
        $sizes_count=array();
        foreach(array_keys($worker_config) as $size){
            if($size==='default')
                continue;
            $size_where=$this->size_where($size);
            if($size_where!='')
                $size_where=" where $size_where";
            $rows=$db->select("select * from sites_stats $size_where");
            $wiki_count=count($rows);
            $sizes_count[$size]=count($rows);
            foreach($rows as $v)
                foreach(array_keys($worker_units) as $type){
                    $k="duration_{$type}";
                    if(isset($v[$k]) && !is_null($v[$k]))
                        $types[$type][$size][]=$v[$k];
                }
        }
        echo "\n";
        foreach($sizes_count as $k=>$v)
            echo "$k : $v\n";
        echo "\n";
        $stats=array();
        foreach($types as $type=>$sizes){
            echo "$type :\n";
            foreach($sizes as $size=>$rows){
                $total=array_sum($rows);
                $expiry=$this->config[$size][$type];
                $expiry_sec=strtotime($expiry,0);
                $units=isset($worker_units[$type][$size]) ? $worker_units[$type][$size] : 0;
                $s=array('count'=>count($rows), 'total_duration'=>$total, 'avg'=>$total/count($rows), 'expiry'=>$expiry_sec, 'units_needed'=>$total/$expiry_sec, 'units_conf'=>$units);
                echo "  ".str_pad("$size ($s[count]) : ", 17, ' ');
                echo str_pad(format_time($s['avg'], ' ')." avg ", 13, ' ');
                echo round($s['units_needed'],1)." units for $expiry ($units)\n";
                $stats[$type][$size]=$s;
            }
        }
        if($save_data){
            $f='ctrl/out/worker_stats';
            if(file_exists($f))
                $data=unserialize(file_get_contents($f));
            else
                $data=array();
            $data[gmdate('Y-m-d H:i:s')]=$stats;
            file_put_contents($f, serialize($data));
        }
    }
    function export_stats()
    {
        include('config/worker_config.php');
        $f='ctrl/out/worker_stats';
        $data=array();
        if(file_exists($f))
            $data=unserialize(file_get_contents($f));
        foreach($data as $time=>$types)
            foreach($types as $type=>$sizes)
                foreach(array_keys($sizes) as $size)
                    $all_sizes[$size]=1;
        foreach($data as $time=>$types){
            foreach($types as $type=>$sizes){
                $row=array($time);
                foreach(array_keys($all_sizes) as $size)
                    $row[]=isset($sizes[$size]['avg']) ? str_replace('.',',',round($sizes[$size]['avg'],2)) : 0;
                $res[$type][]=$row;
            }
        }
        foreach($res as $type=>$rows){
            $f="ctrl/out/worker_stats_export_$type.csv";
            $o='time;'.implode(';', array_keys($all_sizes))."\n";
            foreach($rows as $v)
                $o.=implode(';',$v)."\n";
            file_put_contents($f, $o);
        }
    }
}

?>