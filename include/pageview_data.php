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

class pageview_data
{
    var $view_projects=false;
    var $group_code=array(
        'b'=>'wikibooks',
        'd'=>'wiktionary',
        'n'=>'wikinews',
        'q'=>'wikiquote',
        's'=>'wikisource',
        'v'=>'wikiversity',
        'voy'=>'wikivoyage',
        'z'=>'wikipedia',
        'wd'=>'wikidata',
        'w'=>'mediawiki',
        'f'=>'foundation',
        );
    var $special_project=array(
        'commons'=>'',
        'meta'=>'',
        'incubator'=>'',
        'species'=>'',
        'zero'=>'',
        'outreach'=>'',
        'nostalgia'=>'',
        'ten'=>'',
        'wg-en'=>'',
        'beta'=>'betawikiversity',
        'quality'=>'',
        'strategy'=>'',
        'usability'=>'',
        'test'=>'',
        'test2'=>'',
        );

    function __construct()
    {
        $this->path=getcwd().'/ctrl';
    }

    function load_projects()
    {
        $dbg=get_dbg();
        $rows=$dbg->select("select site_global_key, site_group, site_language from sites");
        $this->projects_key=array();
        foreach($rows as $v)
            $this->projects_key[$v['site_group']][$v['site_language']]=$v['site_global_key'];
    }
    function get_global_key($project)
    {
        list($group, $lang, $type)=$this->read_project_key($project);
        if(isset($this->group_code[$group]))
            $group=$this->group_code[$group];
        if(!isset($this->projects_key[$group][$lang])){
            return false;
        }
        return $this->projects_key[$group][$lang];
    }
    function read_project_key($key)
    {
        $v=explode('.', strtolower($key));
        $lang=$v[0];
        $type='desktop';
        if(!isset($v[1]))
            $group='z';
        elseif($v[1]=='m' || $v[1]=='zero'){
            $type=$v[1];
            if(isset($v[2]))
                $group=$v[2];
            else
                $group='z';
        }elseif(!isset($v[2]))
            $group=$v[1];
        else{
            echo "error\n";
            print_r($v);
            return;
        }
        if(isset($this->special_project[$lang])){//commons, meta, etc.
            $group=$this->special_project[$lang] !='' ? $this->special_project[$lang] : $lang;
            $lang='en';
        }
        if($lang=='www' || $lang=='m' || $lang=='zero'){
            $lang='en';
            if($group=='s')
                $group='sources';
        }
        if($lang=='be-tarask'){
            $lang='be-x-old';
            $group='wikipedia';
        }
        return array($group, $lang, $type);
    }

    function write_cache($global_key)
    {
        file_put_contents($this->project_file[$global_key], $this->project_cache[$global_key], FILE_APPEND);
        $this->project_cache[$global_key]='';
        $this->project_cache_count[$global_key]=0;
    }

    static function read_file($file, $callback)
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if($ext=='bz2')
            $f = popen('bzip2 -cdk "'.$file.'"', 'r');
        elseif($ext=='gz')
            $f = popen('gzip -cdk "'.$file.'"', 'r');
        else{
            echo "wrong extension for '$file'\n";
            return false;
        }
        if(!$f){
            echo "Error popen for '$file' failed\n";
            return false;
        }
        $rows=0;
        $o='';
        while(!feof($f)){
            $o.=fread($f,16384);
            while(($pos=strpos($o,"\n"))!==false){
                $rows++;
                $row=substr($o,0,$pos);
                $o=substr($o,$pos+1);
                if($row=='')
                    continue;
                $v=explode(' ', $row);
                if(count($v)!==2){
                    echo "Error wrong format for '$file' : '$row'\n";
                    continue;
                }
                $title=mb_convert_encoding(urldecode($v[1]), "UTF-8", "UTF-8,ISO-8859-1");
                call_user_func($callback, array('hits'=>$v[0], 'title'=>$title));
            }
        }
        pclose($f);
    }

    static function download($url, $file)
    {
        global $conf;
        $file=$conf['pageview_download_path']."/$file";
        $cmd="wget -nv -O $file $url";
        echo "$cmd\n";
        exec($cmd);
        if(!file_exists($file)){
            echo "error file '$file' not found\n";
            return false;
        }
        if(filesize($file)<5000000){
            echo "error file '$file' too small (".filesize($file).")\n";
            return false;
        }
        return $file;
    }



    static function get_month($date, $callback=false)
    {
        global $conf;
        if(!isset($conf['wiki_key']) || $conf['wiki_key']=='')
            return false;;
        $global_key=$conf['wiki_key'];
        if(strlen($date)!=6)
            return false;
        $month=$date;
        $date=$month.'01';
        $last=date('Ymt', strtotime($date));
        do{
            $file=self::get_file_day($global_key, $date);
            if($file!='')
                $file="$file.bz2";
            if($file!='' && file_exists($file))
                self::read_file($file, $callback);
            $date=date('Ymd', strtotime('+1 day', strtotime($date)));
        }while($date<=$last);
    }

    // DAYS

    static function get_day($date, $callback=false)
    {
        global $conf;
        if(!isset($conf['wiki_key']) || $conf['wiki_key']=='')
            return false;;
        $global_key=$conf['wiki_key'];
        if(strlen($date)!=8)
            return false;
        $file=self::get_file_day($global_key, $date);
        if($file!='')
            $file="$file.bz2";
        if($file=='' || !file_exists($file))
            return false;
        self::read_file($file, $callback);
    }

    function update_all_days($start='20120101')
    {
        $time=strtotime($start);
        do{
            $this->update_day(date('Ymd', $time));
            $time=strtotime('+1 day', $time);
        }while($time<time()-86400);
        echo "done\n";
    }
    function update_last_days($start='')
    {
        $f='ctrl/pageview_last';
        $time=0;
        if($start==''){
            if(file_exists($f))
                $time=strtotime('+1 day', strtotime(file_get_contents($f)));
        }else
            $time=strtotime($start);
        if($time==0)
            die("No start date\n");
        do{
            if($this->update_day(date('Ymd', $time)))
                file_put_contents($f, date('Ymd', $time));
            $time=strtotime('+1 day', $time);
        }while($time<time()-86400);
        echo "done\n";
    }
    function update_day($day)
    {
        global $conf;
        echo "$day\n";
        if(strlen($day)!=8)
            return false;
        $t=strtotime($day);
        $url=$conf['pageview_day_url'].'/'.date('Y', $t).'/'.date('Y-m', $t).'/pagecounts-'.date('Y-m-d', $t).'.bz2';
        if(!$file=self::download($url, "$day.bz2"))
            return false;
        $this->extract_day($file);
        unlink($file);
        return true;
    }

    function extract_day($file)
    {
        $this->load_projects();
        $this->project_file=array();
        $this->project_cache=array();
        $this->project_cache_count=array();
        $this->errors=array();
        $f = popen('bzip2 -cdk "'.$file.'"', 'r');
        if(!$f){
            echo "Error popen for '$file' failed\n";
            return false;
        }
        $tot=0;
        $rows=0;
        $o='';
        $t=time();
        while(!feof($f)){
            $o.=fread($f,16384);
            while(($pos=strpos($o,"\n"))!==false){
                $rows++;
                $row=substr($o,0,$pos);
                $o=substr($o,$pos+1);
                $tot+=strlen($row)+1;
                if($rows%1000000==0)
                    echo number_format($rows,0,'',' ')." rows ".number_format($tot,0,'',' ')." b ".round($rows/(time()-$t))." rows/s \n";
                if($rows===1){
                    if(preg_match('!^#.*\b(\d\d)/(\d\d)/(\d{4})!', $row, $r)){
                        $this->extracted_day="$r[3]$r[2]$r[1]";
                        echo $this->extracted_day."\n";
                    }else{
                        echo "Day not found\n";
                        return false;
                    }
                }
                if($row=='' || substr($row,0,1)=='#')
                    continue;
                $this->process_row_day($row);
            }
        }
        pclose($f);
        foreach($this->project_cache_count as $global_key=>$count)
            if($count>0){
                file_put_contents($this->project_file[$global_key], $this->project_cache[$global_key], FILE_APPEND);
                $this->project_cache[$global_key]='';
            }
        $this->compress_all_day();
        if(isset($this->projects)){
            echo "\nProjects:\n";
            foreach($this->projects as $k=>$v)
                echo "$k $v\n";
            echo "\n";
        }
        if(!empty($this->errors)){
            echo "errors:\n";
            print_r($this->errors);
        }
    }

    function process_row_day($row)
    {
        $cols=explode(' ', $row);
        if(count($cols)!=4){
            print_r($cols);
            return;
        }
        list($project, $title, $total)=$cols;
        if($this->view_projects)
            @$this->projects[$project]++;
        $global_key=$this->get_global_key($project);
        if($global_key===false){
            @$this->errors[$project]++;
            return;
        }
        if($total>=2)
            $this->save_row_day($global_key, $title, $total);
    }
    static function get_file_day($global_key, $day, $create=false)
    {
        global $conf;
        $path=$conf['sites_path'].'/'.$global_key;
        if(!is_dir($path))
            return false;
        $path.='/pageview/'.substr($day,0,4);
        if($create && !is_dir($path))
            mkdir($path, 0755, true);
        return $path.'/'.$day;
    }
    function save_row_day($global_key, $title, $total)
    {
        global $conf;
        static $last=null;
        if($last!==null && $last!=$global_key && isset($this->project_file[$last]) && $this->project_cache_count[$last]>=1)
            $this->write_cache($last);
        $last=$global_key;
        if(!isset($this->project_file[$global_key])){
            $file=self::get_file_day($global_key, $this->extracted_day, true);
            if($file===false)
                return false;
            if(file_exists($file))
                unlink($file);
            $this->project_file[$global_key]=$file;
            $this->project_cache[$global_key]='';
            $this->project_cache_count[$global_key]=0;
        }
        $row="$total $title\n";
        $this->project_cache[$global_key].=$row;
        $this->project_cache_count[$global_key]++;
        if($this->project_cache_count[$global_key]>50000)
            $this->write_cache($global_key);
    }

    function compress_all_day()
    {
        echo "compress ";
        foreach($this->project_file as $global_key=>$file){
            $file_comp="$file.bz2";
            if(!file_exists($file)){
                if(file_exists($file_comp))
                    unlink($file_comp);
                echo '-';
                continue;
            }
            echo '.';
            exec("awk -F ' ' '{a[$2] += $1} END{for (i in a) print a[i], i}' $file | bzip2 -c >$file_comp");
            unlink($file);
        }
        echo "\n";
    }


    // HOURS

    static function get_hours($min, $max, $callback=false)
    {
        global $conf;
        if(!isset($conf['wiki_key']) || $conf['wiki_key']=='')
            return false;;
        $global_key=$conf['wiki_key'];
        $date=$min;
        if(strlen($date)!=14)
            die("wrong date format '$date'\n");
        do{
            $file=self::get_file_hour($global_key, $date);
            if($file!='')
                $file="$file.gz";
            if($file!='' && file_exists($file)){
                echo '.';
                self::read_file($file, $callback);
            }else
                echo "x";
            $date=gmdate('YmdH', strtotime('+1 hour', strtotime($date))).'0000';
        }while($date<$max);
    }

    function update_last_hours()
    {
        $f='ctrl/pageview_last_hours';
        $time=0;
        if(file_exists($f))
            $time=strtotime('+1 hour', strtotime(file_get_contents($f)));
        else
            $time=strtotime('-24 hours');
        if($time==0)
            die("No start date\n");
        do{
            if($this->update_hour(date('YmdH', $time).'0000'))
                file_put_contents($f, date('YmdH', $time).'0000');
            $time=strtotime('+1 hour', $time);
        }while($time<time());
        echo "done\n";
        $this->prune_hours();
    }
    function prune_hours($max_hours=48)
    {
        global $conf;
        $min_date=gmdate('YmdHis', strtotime("-$max_hours hours"));
        $path=$conf['sites_path'];
        echo "prune";
        foreach(scandir($path) as $f){
            if($f=='.' || $f=='..' || !is_dir("$path/$f"))
                continue;
            $dir=$path.'/'.$f.'/pageview/hours';
            if(!is_dir($dir))
                continue;
            echo ".";
            foreach(scandir($dir) as $f){
                if($f=='.' || $f=='..' || !is_file("$dir/$f"))
                    continue;
                $file=pathinfo($f, PATHINFO_FILENAME);
                if($file<$min_date){
                    echo "x";
                    unlink("$dir/$f");
                }
            }
        }
        echo "\n";
    }
    function update_hour($date)
    {
        global $conf;
        echo "$date\n";
        if(strlen($date)!=14)
            return false;
        $t=strtotime($date);
        $url=$conf['pageview_hour_url'].'/'.date('Y', $t).'/'.date('Y-m', $t).'/pageviews-'.date('Ymd-H', $t).'0000.gz';
        if(!$file=self::download($url, "$date.gz"))
            return false;
        $this->extract_hour($file);
        unlink($file);
        return true;
    }

    function extract_hour($file)
    {
        $this->load_projects();
        $this->project_file=array();
        $this->project_cache=array();
        $this->project_cache_count=array();
        $this->errors=array();
        if(preg_match('!pageviews-(\d+)-(\d+)\.gz$!i', $file, $r))
            $this->extracted_date=$r[1].$r[2];
        elseif(preg_match('!/(\d{14})\.gz$!i', $file, $r))
            $this->extracted_date=$r[1];
        else
            die("wrong file name '$file'\n");
        $f = popen('gzip -cdk "'.$file.'"', 'r');
        if(!$f){
            echo "Error popen for '$file' failed\n";
            return false;
        }
        $tot=0;
        $rows=0;
        $o='';
        $t=time();
        while(!feof($f)){
            $o.=fread($f,16384);
            while(($pos=strpos($o,"\n"))!==false){
                $rows++;
                $row=substr($o,0,$pos);
                $o=substr($o,$pos+1);
                $tot+=strlen($row)+1;
                if($rows%1000000==0)
                    echo number_format($rows,0,'',' ')." rows ".number_format($tot,0,'',' ')." b ".round($rows/(time()-$t))." rows/s \n";
                if($row=='' || substr($row,0,1)=='#')
                    continue;
                $this->process_row_hour($row);
            }
        }
        pclose($f);
        foreach($this->project_cache_count as $global_key=>$count)
            if($count>0){
                file_put_contents($this->project_file[$global_key], $this->project_cache[$global_key], FILE_APPEND);
                $this->project_cache[$global_key]='';
            }
        $this->compress_all_hour();
        if(isset($this->projects)){
            echo "\nProjects:\n";
            foreach($this->projects as $k=>$v)
                echo "$k $v\n";
            echo "\n";
        }
        if(!empty($this->errors)){
            echo "errors:\n";
            print_r($this->errors);
        }
    }
    function process_row_hour($row)
    {
        $cols=explode(' ', $row);
        if(count($cols)!=4){
            print_r($cols);
            return;
        }
        list($project, $title, $total)=$cols;
        if($this->view_projects)
            @$this->projects[$project]++;
        $global_key=$this->get_global_key($project);
        if($global_key===false){
            @$this->errors[$project]++;
            return;
        }
        $this->save_row_hour($global_key, $title, $total);
    }
    function save_row_hour($global_key, $title, $total)
    {
        global $conf;
        static $last=null;
        if($last!==null && $last!=$global_key && isset($this->project_file[$last]) && $this->project_cache_count[$last]>=1)
            $this->write_cache($last);
        $last=$global_key;
        if(!isset($this->project_file[$global_key])){
            $file=self::get_file_hour($global_key, $this->extracted_date, true);
            if($file===false)
                return false;
            if(file_exists($file))
                unlink($file);
            $this->project_file[$global_key]=$file;
            $this->project_cache[$global_key]='';
            $this->project_cache_count[$global_key]=0;
        }
        $row="$total $title\n";
        $this->project_cache[$global_key].=$row;
        $this->project_cache_count[$global_key]++;
        if($this->project_cache_count[$global_key]>50000)
            $this->write_cache($global_key);
    }
    static function get_file_hour($global_key, $date, $create=false)
    {
        global $conf;
        $path=$conf['sites_path'].'/'.$global_key;
        if(!is_dir($path))
            return false;
        $path.='/pageview/hours';
        if($create && !is_dir($path))
            mkdir($path, 0755, true);
        return $path.'/'.$date;
    }
    function compress_all_hour()
    {
        echo "compress ";
        foreach($this->project_file as $global_key=>$file){
            $file_comp="$file.gz";
            if(!file_exists($file)){
                if(file_exists($file_comp))
                    unlink($file_comp);
                echo '-';
                continue;
            }
            echo '.';
            exec("gzip -f $file");
        }
        echo "\n";
    }
}


?>