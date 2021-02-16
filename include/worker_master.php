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
require_once('include/worker.php');
require_once('include/wikis.php');

class WorkerMaster
{
    var $status_file="ctrl/workers";
    var $workers_count=array();
    var $workers=array();

    function __construct()
    {
        date_default_timezone_set('GMT');
        if(!file_exists($this->status_file))
            file_put_contents($this->status_file, '');
    }
    function loop()
    {
        $stop=false;
        while(true){
            if(!$stop && file_exists('ctrl/stop')){
                echo "Stopping";
                $stop=true;
            }
            if(!$stop && !$this->run())
                sleep(1);
            if($stop)
                echo " ".count($this->workers);
            if(($pid=pcntl_wait($status, WNOHANG))>0){
                $exitCode = pcntl_wexitstatus($status);
                if(isset($this->workers[$pid])){
                    if($exitCode!=0){
                        $error=date('Y-m-d H:i:s').' '.$this->workers[$pid]['wiki'].' '.$this->workers[$pid]['type']." exitcode ($exitCode)\n";
                        echo $error;
                        print_r($this->workers[$pid]);
                        file_put_contents('ctrl/log/workers_error.log', $error, FILE_APPEND);
                    }
                    $this->workers_count[$this->workers[$pid]['type']][$this->workers[$pid]['size']]--;
                    $this->update_worker_status($pid, null);
                    unset($this->workers[$pid]);
                }else
                    echo "Worker not found [$pid] exitcode ($exitCode)\n";
            }elseif($stop)
                sleep(5);
            if($stop && empty($this->workers)){
                echo " Stop\n";
                break;
            }
        }
    }

    function run()
    {
        include('config/worker_config.php');
        $run=0;
        foreach($worker_units as $type=>$sizes)
            foreach($sizes as $size=>$n)
                if(!isset($this->workers_count[$type][$size]) || $this->workers_count[$type][$size]<$n){
                    $worker=new Worker($type, $size);
                    if($site=$worker->select_site()){
                        $run++;
                        $this->run_worker($type, $size, $site);
                        sleep(1);
                    }
                }
        return $run;
    }

    function run_worker($type, $size, $site)
    {
        $db=get_dbg();
        $db->close();//avoid child auto closing
        $pid = pcntl_fork();
        if ($pid == -1) {
            die("impossible de forker");
        }elseif($pid) {//parent
            $this->workers[$pid]=array('type'=>$type, 'size'=>$size, 'wiki'=>$site);
            if(!isset($this->workers_count[$type][$size]))
                $this->workers_count[$type][$size]=1;
            else
                $this->workers_count[$type][$size]++;
            $db->open();//reopen for parent
        }else{//child
            $pid=getmypid();
            $date=gmdate('Y-m-d H:i:s');
            $this->update_worker_status($pid, array('type'=>$type, 'size'=>$size, 'wiki'=>$site, 'start'=>$date));
            $cmd='/usr/bin/php ctrl/run.php worker_update '.escapeshellarg($type).' '.escapeshellarg($site).' >'.escapeshellarg("ctrl/log/workers/{$site}_{$type}.log").' 2>&1';
            echo "$pid $date $site $type ($size)\n";
            exec($cmd);
            exit;
        }
    }

    function view_status()
    {
        include('config/worker_config.php');
        $worker=new Worker();
        $data=$this->workers_status();
        $history=isset($data['history'])?$data['history']:array();
        unset($data['history']);
        $working=array();
        $working_count=0;
        $working_types=array();
        foreach($data as $v){
            $working[$v['type']][$v['size']][]=$v;
            $working_count++;
            @$working_types[$v['type']]++;
        }
        $o="<h1>Workers</h1>";
        $o.='<table class=workers_table>';
        $o.="<tr><th>Type</th><th>Size</th><th class=worker_spacer_col></th><th colspan=2>Nexts</th><th class=worker_spacer_col></th><th>Working ($working_count)</th><th class=worker_spacer_col><th colspan=3>Lasts</th><tr>";
        $o.='<tr><td class=worker_spacer_row></td></tr>';
        foreach($worker_units as $type=>$types){
            $type_units_real=$type_units_config=array_sum($types);
            if(isset($working_types[$type]) && $working_types[$type]>$type_units_config)
                $type_units_real=$working_types[$type];
            reset($types);
            $o.="<tr><th rowspan=$type_units_real>".msg("workers-type-$type")." ($type_units_config)</th>";
            $tr=true;
            for($j=1;$j<=count($types);$j++){
                if(!$tr){
                    $o.="<tr>";
                    $tr=true;
                }
                $size=key($types);
                $size_units_config=current($types);
                next($types);
                $size_units_real=$size_units_config;
                if(!empty($working[$type][$size]) && count($working[$type][$size])>$size_units_real)
                    $size_units_real=count($working[$type][$size]);
                $o.="<th rowspan=$size_units_real>$size ($size_units_config)</th>";
                $nexts=$worker->get_next($type, $size, $size_units_config*2);
                for($i=1;$i<=$size_units_real;$i++){
                    if(!$tr){
                        $o.="<tr>";
                        $tr=true;
                    }
                    $o.='<td class="worker_spacer_col"></td>';
                    for($k=1;$k<=2;$k++){
                        $o.='<td class="worker_next">';
                        if(!empty($nexts)){
                            $v=array_shift($nexts);
                            $o.=$this->wiki_link($v['site_global_key'], $type);
                            if($v['last']){
                                $t=strtotime("+".$worker->config[$size][$type], strtotime($v['last']));
                                $o.=' ('.format_time($t-time()).')';
                            }
                        }
                        $o.='</td>';
                    }
                    $o.='<td class="worker_spacer_col"></td>';
                    if(!empty($working[$type][$size])){
                        $o.='<td class="worker_working">';
                        $v=array_shift($working[$type][$size]);
                        $o.=$this->wiki_link($v['wiki'], $type).' ('.format_time(time()-strtotime($v['start'])).')';
                        $o.='</td>';
                    }else
                        $o.='<td></td>';
                    $o.='<td class="worker_spacer_col"></td>';
                    for($k=1;$k<=3;$k++){
                        $o.='<td class="worker_last">';
                        if(!empty($history[$type][$size])){
                            $v=array_pop($history[$type][$size]);
                            $o.=$this->wiki_link($v['wiki'], $type).' ('.format_time(strtotime($v['stop'])-strtotime($v['start'])).')';
                        }
                        $o.='</td>';
                    }
                    $o.='</tr>';
                    $tr=false;
                }
            }
            $o.='<tr><td class=worker_spacer_row></td></tr>';
        }
        $o.='</table>';
        return $o;
    }
    function wiki_link($wiki, $type)
    {
        if($type=='count')
            return $wiki;
        $url=Wikis::get_site_url($wiki);
        if($url=='')
            return $wiki;
        return "<a href=\"//$url\">$wiki</a>";
    }

    function update_worker_status($pid, $status)
    {
        if(!$f=fopen($this->status_file, "r+")){
            echo "Error status file\n";
            return false;
        }
        flock($f, LOCK_EX);
        $data=file_get_contents($this->status_file);
        if(!empty($data))
            $data=unserialize($data);
        else
            $data=array();
        if($status!==null)
            $data[$pid]=$status;
        elseif(isset($data[$pid])){
            $v=$data[$pid];
            unset($data[$pid]);
            $v['stop']=gmdate('Y-m-d H:i:s');
            $data['history'][$v['type']][$v['size']][$pid]=$v;
            if(count($data['history'][$v['type']][$v['size']])>10)
                array_shift($data['history'][$v['type']][$v['size']]);
        }
        file_put_contents($this->status_file, serialize($data));
        flock($f, LOCK_UN);
        fclose($f);
    }

    function workers_status()
    {
        if(!$f=fopen($this->status_file, "r")){
            echo "No status file\n";
            return false;
        }
        flock($f, LOCK_SH);
        $data=file_get_contents($this->status_file);
        flock($f, LOCK_UN);
        fclose($f);
        if(!empty($data))
            $data=unserialize($data);
        else
            $data=array();
        return $data;
    }

}

?>