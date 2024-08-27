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
require_once('common/Artichow/LinePlot.class.php');
require_once('include/dates.php');
require_once('include/update_stats.php');

class WsGraph
{
    var $width=600;
    var $height=370;
    var $size='';
    var $step=1;
    var $legend=true;
    var $title='';
    var $left_title='';
    var $cache=true;
    var $image_path='img/graphs';
    var $user='';
    var $user_id;
    var $ip=false;
    var $antialiasing=true;
    var $type;
    var $date_type;
    var $opts;
    var $live=false;
    var $hide_vertical=false;
    var $lines;
    static $dates_opts=array(
        1=>array('step'=>1,'5m'=>true),
        2=>array('step'=>1,'10m'=>true),
        3=>array('step'=>5,'step_big'=>1,'30m'=>true),
        6=>array('step'=>5,'step_big'=>2,'step_large'=>1,'30m'=>true),
    12=>array('step'=>10,'step_big'=>5,'step_large'=>1),
    24=>array('step'=>10,'step_big'=>5,'step_large'=>5),
    'default'=>array('step'=>10,'step_big'=>5,'step_large'=>5),
    'M'=>array('step'=>1,'step_big'=>1),
    'Y'=>array('step'=>1,'step_big'=>1),
    'T'=>array('step'=>1,'step_big'=>1),
    );
    static $author='wikiscan.org';
    static $copyright='CC BY-SA 3.0';
    var $date=false;

    function __construct($type,$date=false)
    {
        $this->type=$type;
        if($date!==false)
            $this->set_date($date);
    }
    function set_size()
    {
        $this->hide_vertical=true;
        switch($this->size){
            case 'small':
                $this->width=320;
                $this->height=195;
                $this->legend=false;
                break;
            case 'medium':
                $this->width=500;
                $this->height=270;
                break;
            case 'medium2':
                $this->width=800;
                $this->height=300;
                break;
            case 'big':
                $this->width=1100;
                $this->height=550;
                break;
            case 'large':
                $this->width=2000;
                $this->height=1000;
                if($this->date!=0)
                    $this->hide_vertical=false;
                break;
            case '1600':
                $this->width=1600;
                $this->height=800;
                break;
            default:
                $this->size='';
                break;
        }
    }
    function set_date($date=false)
    {
        if($date!==false)
            $this->date=$date;
        if($d=Dates::parse_date($this->date)){
            $this->date_type=$d['type'];
            $this->opts=self::date_options($this->date);
            if(isset($this->opts['step_'.$this->size]))
                $this->step=$this->opts['step_'.$this->size];
            else
                $this->step=$this->opts['step'];
            $this->live=@$d['live']==true;
            return true;
        }else{
            $this->date=false;
            return false;
        }
    }
    static function date_options($date)
    {
        if(isset(self::$dates_opts[$date]))
            return self::$dates_opts[$date];
        $type=Dates::type($date);
        if(isset(self::$dates_opts[$type]))
            return self::$dates_opts[$type];
        return self::$dates_opts['default'];
    }
    function graph()
    {
        header('Cache-Control: private, must-revalidate');
        $this->set_size();
        if($this->cache && (!isset($_GET['purge'])||!$_GET['purge'])){
            $f=$this->image_file();
            if(file_exists($f) && $this->check_cache($f)){
                if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == filemtime($f))
                    header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($f)).' GMT', true, 304);
                else{
                    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($f)) . ' GMT');
                    header('Content-Length: ' . filesize($f));
                    echo file_get_contents($f);
                }
                return true;
            }
        }
        if(!$this->build_graph())
            return false;
        if($this->cache){
            $f=$this->image_file(true);
            $this->graph->draw($f);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($f)) . ' GMT');
            header('Content-Length: ' . filesize($f));
            echo file_get_contents($f);
            return true;
        }
        $this->graph->draw();
    }
    function build_graph()
    {
        if(substr($this->type,0,7)=='months_'){
            $this->date_type='U';
            $this->configure_lines();
            $this->load_data_all_months();
            return $this->create();
        }elseif(substr($this->type,0,6)=='years_'){
            $this->date_type='U';
            $this->configure_lines();
            $this->load_data_comp_years();
            return $this->create();
        }elseif($this->user=='' && $this->set_date() && $this->configure_lines()){
            if($this->date==0||strlen($this->date)==4)
                $this->load_data_all_week();
            elseif(strlen($this->date)==6)
                $this->load_data_month();
            else
                $this->load_data();
            return $this->create();
        }elseif($this->user!=''){
            $this->date_type='U';
            $this->configure_lines();
            $this->load_data_user();
            return $this->create();
        }
        return false;
    }

    function image_file($create_dir=false)
    {
        if($this->user==''){
            if($this->size!='')
                $name=$this->type.'_'.$this->size;
            else
                $name=$this->type.'_'.$this->width.'x'.$this->height;
        }else{
            if($this->size!='')
                $name=$this->user.'_'.$this->size;
            else
                $name=$this->user.'_'.$this->width.'x'.$this->height;
        }
        return $this->image_path($create_dir)."/$name.png";
    }
    function image_path($create=false)
    {
        if($this->user=='')
            $path=$this->image_path.'/'.$this->date;
        else
            $path=$this->image_path.'/users';
        if($create && !is_dir($path))
            mkdir($path,0755,true);
        return $path;
    }
    function check_cache($file)
    {
        $fdate=gmdate('YmdHis',filemtime($file));
        if($this->user==''){
            $udate=Dates::get($this->date);
            $udate=$udate['last_update'];
            return $fdate>$udate;
        }else{
            require_once('include/userstats.php');
            $us=new userstats();
            return $us->valid_cache_date($fdate);
        }
        return false;
    }

    function load_data()
    {
        $uniques_list=array('list_user'=>'uuser','list_ip'=>'uip','list_bot'=>'ubot',''=>'utot');
        $s=UpdateStats::load_stat($this->date,'time');
        if(empty($s))
            return false;
        $this->yaxis=array();
        $this->xaxis=array();
        reset($s);
        $start=key($s);
        $min=(int)substr($start,2,2);
        $hour=(int)substr($start,0,2);
        $data=array();
        $cont=true;
        for($h=$hour;$h<24;$h++){
            $h=str_pad($h,2,'0',0);
            for($m=$min;$m<60;$m++){
                $key=$h.str_pad($m,2,'0',0);
                $ms=floor($m/$this->step)*$this->step;
                $ms=str_pad($ms,2,'0',0);
                if(isset($s[$key])){
                    foreach($s[$key] as $k=>$v)
                        if(!is_array($v))
                            @$data[$h.$ms][$k]+=$v;
                        elseif($k=='ns'||$k=='new')
                            foreach($v as $kk=>$vv)
                                @$data[$h.$ms][$k.'_'.$kk]+=$vv;
                        elseif(isset($uniques_list[$k]))
                            foreach($v as $kk=>$vv)
                                @$data[$h.$ms][$k][$kk]+=$vv;
                    unset($s[$key]);
                }else{
                    if(!isset($data[$h.$ms]))
                        $data[$h.$ms]=array();
                }
                if(empty($s))
                    break 2;
            }
            $min=0;
            if($cont && $h==23){
                $h=-1;
                $cont=false;
            }
        }
        foreach($data as $k=>$v){
            $data[$k]['utot']=0;
            foreach($uniques_list as $list=>$key_count)
                if($list!='' && isset($v[$list])){
                    $data[$k][$key_count]=count($data[$k][$list]);
                    $data[$k]['utot']+=count($data[$k][$list]);
                }
        }
        $tz= $this->live ? date('Z')/3600 : date('Z',strtotime($this->date))/3600;
        if($this->live)
            $date=date('Ymd',strtotime('-'.$this->date.' hours'));
        else
            $date=$this->date;
        $lastk=0;
        foreach($data as $k=>$v){
            foreach($this->lines as $line){
                $val=isset($v[$line]) && !is_array($v[$line]) ? $v[$line] : 0;
                if(!in_array($line,$uniques_list))
                    $this->yaxis[$line][]=$val/$this->step;
                else
                    $this->yaxis[$line][]=$val;
            }
            if($this->live && $k<$lastk)
                $date=date('Ymd',strtotime('+1 day',strtotime($date)));
            $zone=date_default_timezone_get();
            date_default_timezone_set('GMT');
            $t=strtotime("$date{$k}00");
            date_default_timezone_set($zone);
            $tz=date('Z',$t)/3600;
            $h=substr($k,0,2)+$tz;
            if($h>=24)
                $h-=24;
            $m=substr($k,2,2);
            if($m==0){
                if($this->size!='small' || $h%2==0 || $this->date<=3)
                    $this->xaxis[]=$h.' '.msg('hour-short');
                else
                    $this->xaxis[]='';
            }elseif($m%30==0 && @$this->opts['30m'])
                $this->xaxis[]=(int)$m;
            elseif($m%10==0 && @$this->opts['10m'])
                $this->xaxis[]=(int)$m;
            elseif($m%5==0 && @$this->opts['5m'])
                $this->xaxis[]=(int)$m;
            else
                $this->xaxis[]='';
            $lastk=$k;
        }
        return true;
    }
    function load_data_month()
    {
        $s=UpdateStats::load_stat($this->date,'time');
        if(empty($s))
            return false;
        $month=substr($this->date,0,6);
        $this->yaxis=array();
        $this->xaxis=array();
        end($s);
        $end=key($s);
        $max=(int)substr($end,6,2);
        $data=array();
        for($d=1;$d<=$max;$d++){
            $k=$month.($d<10?'0'.$d:$d);
            foreach($this->lines as $line){
                $val=isset($s[$k][$line]) && !is_array($s[$k][$line]) ? $s[$k][$line] : 0;
                $this->yaxis[$line][]=$val;
            }
            $this->xaxis[]=$d;
        }
        return true;
    }
    function load_data_all()
    {
        $s=UpdateStats::load_stat($this->date,'time');
        if(empty($s))
            return false;
        $viewyear=$this->date==0;
        $this->yaxis=array();
        $this->xaxis=array();
        reset($s);
        $first=key($s);
        end($s);
        $end=key($s);
        $miny=(int)substr($first,0,4);
        $minm=(int)substr($first,4,2);
        $maxy=(int)substr($end,0,4);
        $maxm=(int)substr($end,4,2);
        $data=array();
        for($y=$miny;$y<=$maxy;$y++){
            for($m=$minm;$m<=12;$m++){
                if($m<10)
                    $m='0'.$m;
                for($d=1;$d<=31;$d++){
                    if($d<10)
                        $d='0'.$d;
                    if(!checkdate($m,$d,$y))
                        continue;
                    $k=$y.$m.$d;
                    foreach($this->lines as $line){
                        $val=isset($s[$k][$line]) && !is_array($s[$k][$line]) ? $s[$k][$line] : 0;
                        $this->yaxis[$line][]=$val;
                    }
                    unset($s[$k][$line]);
                    if($viewyear && $m==1 && $d==1)
                        $this->xaxis[]=$y;
                    elseif($d==1)
                        $this->xaxis[]=(int)$m;
                    else
                        $this->xaxis[]='';
                }
            }
            $minm=1;
        }
        return true;
    }

    function load_data_all_week()
    {
        $s=UpdateStats::load_stat($this->date,'time');
        if(empty($s))
            return false;
        $this->yaxis=array();
        $this->xaxis=array();
        reset($s);
        $first=key($s);
        end($s);
        $end=key($s);
        $miny=(int)substr($first,0,4);
        $minm=(int)substr($first,4,2);
        $maxy=(int)substr($end,0,4);
        $maxm=(int)substr($end,4,2);
        $data=array();
        for($y=$miny;$y<=$maxy;$y++){
            for($m=$minm;$m<=12;$m++){
                if($m<10)
                    $m='0'.$m;
                for($d=1;$d<=31;$d++){
                    if($d<10)
                        $d='0'.$d;
                    if(!checkdate($m,$d,$y))
                        continue;
                    $k=$y.$m.$d;
                    if(strtotime($k)>time())
                        continue;
                    $w=date('W',strtotime("$y-$m-$d"));
                    $iso_y=date('o',strtotime("$y-$m-$d"));
                    foreach($this->lines as $line){
                        $val=isset($s[$k][$line]) && !is_array($s[$k][$line]) ? $s[$k][$line] : 0;
                        @$weeks[$iso_y][$w][$line]['days']++;
                        @$weeks[$iso_y][$w][$line]['val']+=$val;
                    }
                    unset($s[$k][$line]);
                }
            }
            $minm=1;
        }
        foreach($weeks as $year=>$wks)
            foreach($wks as $week=>$ls){
                foreach($ls as $line=>$v){
                    $val=round($v['val']/$v['days']);
                    $this->yaxis[$line][]=$val;
                }
                if($this->date==0)
                    $this->xaxis[]= $week==1 ? $year : '';
                elseif($this->size=='medium' || $this->size=='small')
                    $this->xaxis[]= $week%2==1 ? (int)$week : '';
                else
                    $this->xaxis[]= (int)$week ;
            }
        return true;
    }

    function load_data_user()
    {
        require_once('include/functions.php');
        require_once('include/userstats.php');
        $us=new Userstats(isset($_GET['ip']) && $_GET['ip']);
        $rows=$us->user_months($this->user, $this->user_id);
        if(empty($rows))
            return false;
        $this->yaxis=array();
        $this->xaxis=array();
        $date=$rows[0]['date'];
        $end=$rows[count($rows)-1]['date'];
        $dates=index($rows,'date');
        while($date<=$end){
            foreach($this->lines as $line){
                $val=isset($dates[$date][$line]) ? $dates[$date][$line] : 0;
                $this->yaxis[$line][]=$val;
            }
            if(substr($date,4,2)=='01')
                $this->xaxis[]=substr($date,0,4);
            else
                $this->xaxis[]='';
            $date++;
            if(substr($date,4,2)==13)
                $date=(substr($date,0,4)+1).'01';
        }
        return true;
    }
    function load_data_all_months()
    {
        $s=UpdateStats::stats_months();
        if(empty($s))
            return false;
        $this->date=0;
        $this->yaxis=array();
        $this->xaxis=array();
        reset($s);
        $first=key($s);
        end($s);
        $end=key($s);
        $miny=(int)substr($first,0,4);
        $minm=(int)substr($first,4,2);
        $maxy=(int)substr($end,0,4);
        $maxm=(int)substr($end,4,2);
        for($y=$miny;$y<=$maxy;$y++){
            for($m=$minm;$m<=12;$m++){
                if($y==$maxy && $m>$maxm)
                    break;
                if($m<10)
                    $m='0'.$m;
                $k=$y.$m;
                foreach($this->lines as $line){
                    if(is_array($line)){
                        $base=isset($s[$k]) ? $s[$k] : 0;
                        foreach($line as $key)
                            if(isset($base[$key]))
                                $base=$base[$key];
                            else{
                                $base=0;
                                break;
                            }
                        $line=implode('-',$line);
                        $val=$base;
                    }else
                        $val=isset($s[$k][$line]) && !is_array($s[$k][$line]) ? $s[$k][$line] : 0;
                    $this->yaxis[$line][]=$val;
                }
                if($m==1)
                    $this->xaxis[]=$y;
                else
                    $this->xaxis[]='';
            }
            $minm=1;
        }
        return true;
    }
    function load_data_comp_years()
    {
        $s=UpdateStats::stats_months();
        if(empty($s))
            return false;
        $this->date=0;
        $this->yaxis=array();
        $this->xaxis=array();
        reset($s);
        $first=key($s);
        end($s);
        $end=key($s);
        $miny=(int)substr($first,0,4);
        $minm=(int)substr($first,4,2);
        $maxy=(int)substr($end,0,4);
        $maxm=(int)substr($end,4,2);
        $miny=2006;
        $minm=1;
        for($m=1;$m<=12;$m++)
            $this->xaxis[]=$m;
        for($y=$miny;$y<=$maxy;$y++){
            for($m=1;$m<=12;$m++){
                if($y==$maxy && $m>$maxm)
                    break;
                if($m<10)
                    $m='0'.$m;
                $k=$y.$m;
                foreach($this->lines as $line){
                    if(is_array($line)){
                        $base=isset($s[$k]) ? $s[$k] : 0;
                        foreach($line as $key)
                            if(isset($base[$key]))
                                $base=$base[$key];
                            else{
                                $base=0;
                                break;
                            }
                        $line=implode('-',$line);
                        $val=$base;
                    }else
                        $val=isset($s[$k][$line]) && !is_array($s[$k][$line]) ? $s[$k][$line] : 0;
                    $this->yaxis[$y][]=$val;
                }
            }
            $minm=1;
        }
        return true;
    }

    function get_title()
    {
        $title=$this->title;
        if(isset($this->date)){
            $d=Dates::parse_date($this->date);
            if($this->date==0)
                $title.='';
            elseif($this->date<=48)
                $title.='  '.$this->date.msg('hour-short');
            elseif($this->date_type=='D')
                $title.='  '.date(msg('graph-day-date_format'), strtotime($this->date));
            elseif($this->date_type=='M')
                $title.='  '.msg("month-long-".$d['m']).' '.$d['y'];
            elseif($this->date_type=='Y')
                $title.='  '.$d['y'];
        }
        return $title;
    }

    function create()
    {
        if(empty($this->yaxis)||empty($this->yaxis)){
            //trigger_error("graph no data");
            //echo 'Error no data';
            return false;
        }
        $this->graph = new Graph($this->width, $this->height);
        if($this->antialiasing && function_exists('imageantialias'))
            $this->graph->setAntiAliasing(true);
        if($this->size!='small'){
            $this->graph->addAbsLabel(new Label(self::$author,new Tuffy(7)), new Point($this->width/2,$this->height-8));
            $this->graph->addAbsLabel(new Label(self::$copyright,new Tuffy(5)), new Point($this->width-strlen(self::$copyright)*3+5,$this->height-7));
        }
        $group = new PlotGroup();
        if($this->title!='' && $this->size!='small'){
            $title=$this->get_title();
            $group->title->setAlign(Positionable::LEFT,Positionable::TOP);
            $group->title->set($title);
            $group->title->move(-220, 0);
            $group->title->setFont(new TuffyBold(10));
            $group->title->setPadding(5, 5, 2, 2);
        }

        $group->setBackgroundColor(new Color(240, 240, 240));
        $group->grid->setBackgroundColor(new Color(0xec, 0xef, 0xf1, 60));
        $group->grid->hideVertical($this->hide_vertical);
        $group->axis->left->setTickStyle(Tick::IN);
        $group->axis->left->auto(false);
        if($this->size!='small')
            $group->axis->bottom->tick('major')->setSize(3);
        else
            $group->axis->bottom->tick('major')->setSize(2);
        if($this->size!='small'){
            $group->axis->left->title->set($this->left_title);
            $group->axis->left->title->setPadding(0,25,0,0);
        }
        $group->axis->bottom->setTickStyle(Tick::OUT);
        $group->axis->bottom->setLabelText($this->xaxis);
        unset($this->xaxis);
        $group->legend->setTextFont(new Tuffy(8));
        $group->legend->setPosition(0.99, 0.065);
        $group->legend->setRows(floor(count($this->yaxis)/6)+1);
        $group->legend->setSpace(1);
        $group->legend->setPadding(4,4,4,4);
        $group->legend->setTextMargin(1,1);
        if(!$this->legend){
            $group->legend->hide();
            if($this->size!='small')
                $group->setPadding(45, 10, 10);
            else
                $group->setPadding(21, 10, 10);
        }else{
            $group->setPadding(45, 10, 30);
            $group->setSpace(0,0,0);
        }
        $group->axis->left->setLabelPrecision(1);
        foreach($this->yaxis as $line=>$y){
            if(empty($y))
                continue;
            if(max($y)>10)
                $group->axis->left->setLabelPrecision(0);
            $plot = new LinePlot($y);
            unset($y);
            unset($this->yaxis[$line]);
            if($col=self::get_color($line))
                $plot->setColor($col);
            else
                $plot->setColor(0,0,0,0);
            switch($line){
                case 'edit' :
                case 'new_total':
                    $plot->setBackgroundColor(new Color(240, 240, 240));
                    $plot->setFillColor(new Color(100, 180, 100, 40));
                    $plot->grid->setBackgroundColor(new Color(235, 235, 180, 40));
                    break;
                case 'utot' :
                    $plot->setFillColor(new Color(100, 180, 240, 70));
                    break;
                case 'all' :
                case 'total' :
                    $plot->setFillColor(new Color(50, 250, 200, 90));
                    break;
                case 'log' :
                    $plot->setFillColor(new Color(100, 150, 220, 80));
                    $plot->grid->setBackgroundColor(new Color(235, 235, 180, 60));
                    break;
            }
            $plot->yAxis->auto(false);
            $group->add($plot);
            $group->legend->add($plot, utf8_decode(msg("graph-line-$line")), Legend::LINE);
        }

        $this->graph->add($group);
        return true;
    }

    function configure_lines()
    {
        switch($this->date_type){
            case 'U':
                $left_suff=msg('graph-by_month');
                break;
            case 'T':
            case 'Y':
            case 'M':
                $left_suff=msg('graph-by_day');
                break;
            case 'L':
            case 'D':
            default:
                $left_suff=msg('graph-by_minute');
                break;
        }
        switch($this->type){
            case 'edits':
                $this->title=msg('graph-title-edits');
                $this->lines=array('all','edit','user_edit','ip_edit','bot_edit','sysop','log_sysop','log');
                $this->left_title=msg('graph-yaxis-edits').$left_suff;
                break;
            case 'users':
                $this->title=msg('graph-title-users');
                $this->lines=array('utot','uuser','uip','ubot');
                if($this->step==1)
                    $this->left_title=msg('graph-yaxis-users').$left_suff;
                else
                    $this->left_title=msg('graph-yaxis-users')."/{$this->step} ".msg('graph-yaxis-users-minutes');
                break;
            case 'nstypes':
                $this->title=msg('graph-title-ns');
                $this->lines=array('edit','article','annexe','talk','meta','other');
                $this->left_title=msg('graph-yaxis-edits').$left_suff;
                break;
            case 'user':
                $this->title=$this->user;
                $this->lines=array('total','edit','main','talk','meta','annexe','other','log','log_sysop','revert');
                $this->left_title=msg('graph-yaxis-edits').$left_suff;
                break;
            default:
                $this->lines=array();
                return false;
        }
        return true;
    }
    function get_color($name)
    {
        if(isset($this->colors[$name]))
            return $this->colors[$name];
        $col=self::get_color_def($name);
        $this->colors[$name]=new Color($col[0],$col[1],$col[2],$col[3]);
        return $this->colors[$name];
    }

    static function get_color_def($name)
    {
        switch($name){
            case 'all'   : return array(50, 200, 200, 60);
            case 'total' : return array(50, 200, 200, 60);
            case 'edit' : return array(50, 180, 50, 45);

            case 'article' : return array(170, 255, 0, 30);
            case 'main'    : return array(170, 255, 0, 30);
            case 'annexe' : return array(255, 80, 0, 30);
            case 'talk' : return array(255, 200, 0, 30);
            case 'meta' : return array(50, 50, 50, 30);
            case 'other' : return array(120, 0, 255, 30);
            case 'revert' : return array(250, 20, 20, 20);

            case 'new_total' : return array(50, 180, 50, 45);
            case 'new_article' : return array(50, 220, 90, 30);
            case 'new_redir' : return array(210, 150, 50, 30);
            case 'new_other' : return array(90, 20, 90, 30);

            case 'user_edit' : return array(0, 0, 250, 30);
            case 'ip_edit' : return array(50, 50, 50, 10);
            case 'bot_edit' : return array(255, 140, 0, 20);
            case 'peon' : return array(50, 50, 180, 30);
            case 'privilegied' : return array(0, 0, 0, 30);
            case 'sysop' : return array(0, 200, 230, 30);

            case 'utot' : return array(50, 150, 250, 30);
            case 'uuser' : return array(0, 0, 250, 30);
            case 'uip' : return array(50, 50, 50, 10);
            case 'ubot' : return array(255, 140, 0, 20);
            case 'user-peon' : return array(50, 50, 180, 30);
            case 'user-sysop' : return array(0, 200, 230, 30);
            case 'new_user' : return array(250, 0, 150, 30);

            case 'log' : return array(50, 50, 255, 40);
            case 'log_sysop' : return array(200, 0, 255, 30);
            case 'newuser' : return array( 50, 255, 50, 20);
            case 'move' : return array( 0,0, 0, 20);

            case 'users_active-user' : return array(0, 0, 250, 30);
            case 'users_med_active-user' : return array(50, 50, 50, 10);
            case 'users_very_active-user' : return array(255, 140, 0, 20);

            default: return array(mt_rand(0,255),mt_rand(0,255),mt_rand(0,255),0);
        }
    }

}
?>
