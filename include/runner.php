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

class Runner
{
    var $silent=false;
    var $high_mem='4000M';
    var $max_mem='8000M';
    var $fullupdate_lock='fullupdate';

    function run_args($argv)
    {
        $params=array();
        $action=@$argv[1];
        $start=2;
        if($action=='silent'){
            $action=$argv[2];
            $this->silent=true;
            $start++;
        }
        for($i=$start;$i<=100;$i++)
            if(isset($argv[$i]))
                $params[]=$argv[$i];
            else
                break;
        $this->action($action,$params);
    }
    function action($action,$params=array())
    {
        if(!$this->silent){
            echo "\n".date('Y-m-d H:i:s')." : $action\n";
            echo str_repeat('-',30)."\n";
        }
        $start_time=time();
        if(!method_exists($this,'ac_'.$action)){
            echo "Action not found\n";
            return false;
        }
        $res=call_user_func(array($this,'ac_'.$action),$params);
        $time=time()-$start_time;
        if(!$this->silent){
            echo "\n".str_repeat('-',30)."\n";
            echo 'End '.date('Y-m-d H:i:s')." $action ". ($time>3600?round($time/3600,1).'h':round($time/60,1).'m')." ($time s)\n";
        }
    }

    function lock_file($name)
    {
        global $conf;
        return '/tmp/'.$name.$conf['cache_key_site'].'.lock';
    }

    function ac_htaccess()
    {
        require_once('include/site.php');
        $site=new Site();
        $site->generate_htaccess();
    }

    //Stats
    function ac_userstats($p)
    {
        require_once('include/userstats.php');
        ini_set('memory_limit', $this->max_mem);
        $us=new UserStats();
        $us->update(@$p[0],!isset($p[1])||$p[1]);
    }
    function ac_userstats_sum($p)
    {
        require_once('include/userstats.php');
        ini_set('memory_limit', '1500M');
        $us=new UserStats();
        $us->sum(@$p[0]);
    }
    function ac_userstatsip($p)
    {
        require_once('include/userstats.php');
        ini_set('memory_limit', '1500M');
        $us=new UserStats(true);
        $us->update(@$p[0],!isset($p[1])||$p[1]);
    }
    function ac_userstatsip_sum($p)
    {
        require_once('include/userstats.php');
        ini_set('memory_limit', '1500M');
        $us=new UserStats(true);
        $us->sum(@$p[0]);
    }
    function ac_user_cubes($p)
    {
        require_once('include/userstats.php');
        $us=new UserStats();
        $us->cubes(@$p[0]);
    }

    function ac_updatestats($p)
    {
        require_once('include/update_stats.php');
        ini_set('memory_limit', '3000M');
        $start=isset($p[0])?$p[0]:false;
        $end=isset($p[1])?$p[1]:false;
        $obj=new UpdateStats();
        $obj->update_days($start,$end);
    }
    function ac_updatestats_months($p)
    {
        require_once('include/update_stats.php');
        ini_set('memory_limit', '3000M');
        $start=isset($p[0])?$p[0]:false;
        $end=isset($p[1])?$p[1]:false;
        $obj=new UpdateStats();
        $obj->update_months($start,$end);
    }
    function ac_update_hours($p)
    {
        require_once('include/update_stats.php');
        ini_set('memory_limit', $this->max_mem);
        $obj=new UpdateStats();
        $obj->update_last_hours($p[0]);
    }

    function ac_sumall($p)
    {
        require_once('include/update_stats.php');
        ini_set('memory_limit', $this->max_mem);
        $date=isset($p[0])?$p[0]:false;
        $sumtotal=isset($p[1]) && $p[1];
        $stats=isset($p[2])?explode(',',$p[2]):false;
        $obj=new UpdateStats();
        $obj->sum_all($date,$sumtotal,$stats);
    }
    function ac_sum($p)
    {
        require_once('include/update_stats.php');
        ini_set('memory_limit', '12000M');
        $obj=new UpdateStats();
        $stats=isset($p[1])?explode(',',$p[1]):false;
        $obj->sum($p[0],$stats);
    }
    function ac_sumyears($p)
    {
        require_once('include/update_stats.php');
        ini_set('memory_limit', $this->max_mem);
        $years=isset($p[0])?$p[0]:false;
        $sumtotal=isset($p[1]) && $p[1];
        $stats=isset($p[2])?explode(',',$p[2]):false;
        $obj=new UpdateStats();
        $obj->sum_years($years,$sumtotal,$stats);
    }

    function ac_update_date($p)
    {
        require_once('include/update_stats.php');
        ini_set('memory_limit', $this->max_mem);
        $up=new UpdateStats();
        $up->update_date($p[0], isset($p[1]) ? explode(',', $p[1]) : false);
    }
    function ac_fullupdate_old($p)
    {
        $this->action('updatestats');
        $this->action('sumall');
        $this->action('userstats');
    }
    function ac_fullupdate($p)
    {
        global $conf;
        require_once('include/update_stats.php');
        require_once('include/userstats.php');
        ini_set('memory_limit', $this->max_mem);
        if(isset($p[0]))
            $start=strtotime($p[0]);
        else{
            $db=get_db();
            $start=$db->selectcol('revision','min(rev_timestamp)');
            if($start==''){
                $start=$db->selectcol('revision','min(rev_timestamp)', "rev_timestamp!=''");
                if($start==''){
                    echo "Erro no revision\n";
                    return false;
                }
            }
            $start=strtotime(date('Ymd',strtotime($start)));
        }
        $lasty=date('Y',$start);
        $stats= isset($p[1]) ? explode(',',$p[1]) : false;
        $slow=@$p[2];

        $site=$conf['wiki_key'];
        $s=Wikis::get_site_stats($site);
        echo "$site total revs ".$s['total_rev']."\n";

        echo 'Memory : '.round(memory_get_usage(true)/1048576).'/'.round(memory_get_peak_usage(true)/1048576)."Mb\n";
        $t=time();
        do{
            $up=new UpdateStats();
            if($stats)
                $up->stats=$stats;
            $up->update_days(date('Ymd',$start),date('Ymt',$start),$slow);
            if($slow)
                sleep(5);
            $up->update_date(date('Ym',$start));
            $us=new UserStats();
            $us->update(date('Ym',$start),false);
            $start=strtotime('+1 month', strtotime(date('Ym',$start).'01'));
            if($lasty!=date('Y',$start) || date('Ym',$start)>date('Ym')){
                if($slow)
                    sleep(30);
                $up->update_date($lasty);
                unset($up);
                $us->sum($lasty);
                unset($us);
                $lasty=date('Y',$start);
                $t=time()-$t;
                echo $t." s ".round($t/60)." m";
                if($slow && $t>60){
                    $sleep=round($t/10);
                    echo " sleep $sleep";
                    sleep($sleep);
                }
                $t=time();
                echo "\n";
            }
            unset($up);
            unset($us);
            if($slow)
                sleep(($lasty-2000)*2);
            echo "\n";
        }while(date('Ym',$start)<=date('Ym'));
        echo 'Memory : '.round(memory_get_usage(true)/1048576).'/'.round(memory_get_peak_usage(true)/1048576)."Mb\n";
        if($slow)
            sleep(120);
        $us=new UserStats();
        $us->sum(0);
        $us->set_last_update();
        unset($us);
        echo 'Memory : '.round(memory_get_usage(true)/1048576).'/'.round(memory_get_peak_usage(true)/1048576)."Mb\n";
        if($slow)
            sleep(120);
        $up=new UpdateStats();
        $up->sum(0,array('stats'));
        unset($up);
        echo 'Memory : '.round(memory_get_usage(true)/1048576).'/'.round(memory_get_peak_usage(true)/1048576)."Mb\n";
    }
    function ac_fullupdate_months($p)
    {
        require_once('include/update_stats.php');
        require_once('include/userstats.php');
        ini_set('memory_limit', $this->high_mem);
        if(!isset($p[0])){
            $db=get_db();
            $rows=$db->select('select min(rev_timestamp) min from revision');
            if(isset($rows[0]))
                $start=strtotime(substr($rows[0]['min'],0,6).'01');
            else
                $start='20010101';
        }else
            $start=strtotime($p[0]);
        $lasty=date('Y',$start);
        $stats= isset($p[1]) ? explode(',',$p[1]) : false;
        $slow=@$p[2];
        echo 'Memory : '.round(memory_get_usage(true)/1048576).'/'.round(memory_get_peak_usage(true)/1048576)."Mb\n";
        $t=time();
        do{
            $obj=new UpdateStats();
            if($stats)
                $obj->stats=$stats;
            $end=date('Y',$start).'12';
            if($end>date('Ym'))
                $end=date('Ym');
            $obj->update_months(date('Ym',$start),$end,$slow);
            if($slow)
                sleep(5);
            $obj->sum(date('Y',$start),$stats);
            $us=new UserStats();
            $us->update(date('Y',$start),false);
            $us->sum(date('Y',$start));
            unset($obj);
            unset($us);
            $start=strtotime('+1 year',strtotime(date('Y',$start).'0101'));
            if($slow)
                sleep(($lasty-2000)*2);
            echo "\n";
        }while(date('Ym',$start)<=date('Ym'));
        echo 'Memory : '.round(memory_get_usage(true)/1048576).'/'.round(memory_get_peak_usage(true)/1048576)."Mb\n";
        if($slow)
            sleep(120);
        $us=new UserStats();
        $us->sum(0);
        $us->set_last_update();
        unset($us);
        echo 'Memory : '.round(memory_get_usage(true)/1048576).'/'.round(memory_get_peak_usage(true)/1048576)."Mb\n";
        if($slow)
            sleep(120);
        $obj=new UpdateStats();
        $obj->sum(0,array('time','stats'));
        unset($obj);
        echo 'Memory : '.round(memory_get_usage(true)/1048576).'/'.round(memory_get_peak_usage(true)/1048576)."Mb\n";
    }
    function ac_fullupdate_refresh($p)
    {
        require_once('include/update_stats.php');
        require_once('include/userstats.php');
        require_once('include/dates.php');
        ini_set('memory_limit', $this->high_mem);
        if(file_exists($lock=$this->lock_file($this->fullupdate_lock))){
            echo "$lock exists\n";
            return false;
        }
        file_put_contents($lock,date('Y-m-d H:i:s'));
        $dates=Dates::list_refresh();
        echo count($dates)." dates\n";
        if(empty($dates))
            return false;
        $stats= isset($p[0]) ? explode(',',$p[0]) : false;
        $months=array();
        $years=array();
        foreach($dates as $date){
            $up=new UpdateStats($stats);
            $up->update_date($date);
            if(strlen($date)==8)
                $tosum[substr($date,0,6)]=0;
            else
                unset($tosum[$date]);
            unset($up);
            $us=new UserStats();
            $us->update_date($date);
            unset($us);
        }
        if(!empty($tosum)){
            foreach($tosum as $date=>$v){
                $up=new UpdateStats($stats);
                $up->update_date($date);
                unset($up);
                $us=new UserStats();
                $us->update_date($date);
                unset($us);
            }
        }
        $us=new UserStats();
        $us->sum(0);
        $us->set_last_update();
        unset($us);
        $up=new UpdateStats();
        $up->sum(0,array('stats'));
        unlink($lock);
    }

    function ac_export_stats($p)
    {
        require_once('include/update_stats.php');
        $obj=new UpdateStats();
        echo $obj->export_stats(@$p[0]);
    }
    function ac_export_years($p)
    {
        require_once('include/update_stats.php');
        $obj=new UpdateStats();
        echo $obj->export_years();
    }
    function ac_stats_months($p)
    {
        require_once('include/update_stats.php');
        $obj=new UpdateStats();
        $data=$obj->stats_months();
        file_put_contents('ctrl/out/stats_months', serialize($data));
    }
    function ac_stats_months_keys($p)
    {
        require_once('include/update_stats.php');
        $obj=new UpdateStats();
        print_r($obj->stats_months_keys());
    }
    function ac_export_pages_rv($p)
    {
        ini_set('memory_limit', '2000M');
        require_once('include/update_stats.php');
        $obj=new UpdateStats();
        print_r($obj->export_pages_rv());
    }

    function ac_timeline_users($p)
    {
        require_once('include/timeline_users.php');
        ini_set('memory_limit','4000M');
        $obj=new Timeline_users();
        if(isset($p[1]))
            $obj->highlight=$p[1];
        if(isset($p[0]))
            $obj->gen($p[0]);
        else
            $obj->gen();
    }

    function ac_update_ranges($p)
    {
        global $conf;
        //mem prob with both
        if(file_exists($lock=$this->lock_file($this->fullupdate_lock))){
            echo "$lock exists\n";
            return false;
        }
        ini_set('memory_limit','3500M');
        require_once('include/ranges.php');
        $obj=new Ranges();
        if(isset($p[0]))
            $obj->update(explode(',',$p[0]));
        else
            $obj->update();
    }
    function ac_update_ranges_whois($p)
    {
        require_once('include/ranges.php');
        $obj=new Ranges();
        $obj->whois_rand_src_ip=false;
        if(isset($p[0]))
            $obj->update_whois_loop($p[0]);
        else
            $obj->update_whois_loop();
    }
    function ac_update_subranges_whois($p)
    {
        require_once('include/ranges.php');
        $obj=new Ranges();
        $obj->update_whois_subranges($p[0]);
    }
    function ac_update_whois_owner($p)
    {
        require_once('include/ranges.php');
        $obj=new Ranges();
        $obj->update_whois_owner($p[0]);
    }
    function ac_import_whois($p)
    {
        require_once('include/ranges.php');
        $obj=new Ranges();
        $obj->import_whois($p[0]);//ripe.db.gz
    }
    function ac_ranges_whois($p)
    {
        require_once('include/ranges.php');
        $obj=new Ranges();
        $obj->update_whois_range($p[0]);
    }
    function ac_fix_whois($p)
    {
        require_once('include/ranges.php');
        $obj=new Ranges();
        if(isset($p[0]))
            $obj->fix_whois(explode(',',$p[0]));
        else
            $obj->fix_whois();
    }


    function ac_cache($p)
    {
        $Cache=get_cache();
        $Cache->info();
    }

    function ac_import_wikis()
    {
        require_once('include/wikis.php');
        wikis::import_wikis();
    }
    function ac_export_wikis()
    {
        require_once('include/wikis.php');
        wikis::export();
    }
    function ac_export_db_list()
    {
        require_once('include/wikis.php');
        wikis::export_db_list();
    }

    function ac_query_all($p)
    {
        require_once('include/wikis.php');
        if($p[0]=='')
            exit;
        wikis::query_all($p[0]);
    }
    function ac_update_score($p)
    {
        require_once('include/wikis.php');
        Wikis::update_score(isset($p[0]) ? $p[0] : false);
    }

    function ac_worker_master($p)
    {
        require_once('include/worker_master.php');
        $master=new WorkerMaster();
        $master->loop();
    }
    function ac_worker_update($p)
    {
        require_once('include/worker.php');
        $worker=new Worker($p[0]);
        $worker->update($p[1]);
    }
    function ac_worker_update_all($p)
    {
        require_once('include/worker.php');
        $worker=new Worker($p[0]);
        foreach(wikis::list_all() as $site){
            echo "$site\n";
            $worker->update($site);
        }
    }


    function ac_worker_stats($p)
    {
        ini_set('memory_limit', '512M');
        require_once('include/worker.php');
        $worker=new Worker();
        $worker->stats(isset($p[0]) && $p[0]);
    }
    function ac_worker_stats_export($p)
    {
        require_once('include/worker.php');
        $worker=new Worker();
        $worker->export_stats();
    }

    function ac_pv_extract_day($p)
    {
        ini_set('memory_limit', '512M');
        require_once('include/pageview_data.php');
        $pv=new pageview_data();
        $pv->extract_day($p[0]);
    }
    function ac_pv_update_all_days($p)
    {
        ini_set('memory_limit', '512M');
        require_once('include/pageview_data.php');
        $pv=new pageview_data();
        if(isset($p[0]))
            $pv->update_all_days($p[0]);
        else
            $pv->update_all_days();
    }
    function ac_pv_update_last_days($p)
    {
        ini_set('memory_limit', '512M');
        require_once('include/pageview_data.php');
        $pv=new pageview_data();
        if(isset($p[0]))
            $pv->update_last_days($p[0]);
        else
            $pv->update_last_days();
    }
    function ac_pv_extract_hour($p)
    {
        ini_set('memory_limit', '512M');
        require_once('include/pageview_data.php');
        $pv=new pageview_data();
        $pv->extract_hour($p[0]);
    }
    function ac_pv_update_hour($p)
    {
        ini_set('memory_limit', '512M');
        require_once('include/pageview_data.php');
        $pv=new pageview_data();
        $pv->update_hour($p[0]);
    }
    function ac_pv_update_last_hours($p)
    {
        ini_set('memory_limit', '512M');
        require_once('include/pageview_data.php');
        $pv=new pageview_data();
        $pv->update_last_hours();
    }

    function ac_optimize_db($p)
    {
        $db=get_dbs();
        foreach($db->select('show tables') as $v)
            $tables[]=reset($v);
        foreach($tables as $table){
            echo "$table\n";
            $db->query("optimize table `$table`");
        }
    }

    function ac_view_stat($p)
    {
        ini_set('memory_limit', $this->high_mem);
        require_once('include/update_stats.php');
        $data=UpdateStats::load_stat($p[0], $p[1]);
        echo count($data)."rows\n";
        print_r($data);
        echo count($data)."rows\n";
    }

    function ac_list_wikis($p)
    {
        require_once('include/userstats.php');
        Userstats::list_all_wikis($p[0]);


    }

}
?>