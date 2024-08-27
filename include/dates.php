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
require_once('include/functions.php');

class Dates
{
    static $cache=true;
    static $cache_expire=3600;

    var $date;
    var $year;
    var $month;
    var $day;
    var $live;

    function __construct()
    {
    }

    static function get($date)
    {
        if($date!==0 && $date=='')
            return false;
        if(self::$cache){
            $Cache=get_cache();
            $data=$Cache->get(cache_key('date:row:'.$date));
            if($data!==false)
                return $data;
        }
        $dbs=get_dbs();
        $rows=$dbs->select('select * from dates where date=\''.(int)$date.'\'');
        if(isset($rows[0])){
            if(self::$cache)
                $Cache->set(cache_key('date:row:'.$rows[0]['date']),$rows[0],self::$cache_expire);
            return $rows[0];
        }
        return false;
    }

    static function update($row, $cache=true)
    {
        $dbs=get_dbs();
        if($date=self::parse_date($row['date'])){
            if(!isset($row['last_update']))
                $row['last_update']=gmdate('YmdHis');
            if(!isset($row['type']))
                $row['type']=$date['type'];
            if(self::$cache && $cache){
                $Cache=get_cache();
                $Cache->set(cache_key('date:row:'.$row['date']),$row,self::$cache_expire);
                if($date['type']!='L')
                    $Cache->delete(cache_key('date:rows'));
            }
            return $dbs->insert('dates',$row,false,true);
        }
        return false;
    }
    static function update_refresh($timestamp)
    {
        $t=strtotime($timestamp);
        $dbs=get_dbs();
        $dbs->query('START TRANSACTION');
        $dbs->update('dates','date',gmdate('Ymd',$t),array('refresh'=>1));
        $dbs->update('dates','date',gmdate('Ym',$t),array('refresh'=>1));
        $dbs->update('dates','date',gmdate('Y',$t),array('refresh'=>1));
        $dbs->query('commit');
    }
    static function list_refresh()
    {
        $dbs=get_dbs();
        $rows=$dbs->select('select * from dates where refresh=1 order by date');
        $res=array();
        foreach($rows as $v){
            $date=$v['date'];
            // sort day, month, year
            switch(strlen($date)){
                case 8 : $k=$date; break;
                case 6 : $k=$date.'32'; break;
                case 4 : $k=$date.'1332'; break;
            }
            $res[$k]=$v['date'];
        }
        ksort($res);
        return $res;
    }
    function load($no_cache=false)
    {
        $rows=false;
        if(!$no_cache && self::$cache){
            $Cache=get_cache();
            $rows=$Cache->get(cache_key('date:rows'));
        }
        if($rows===false){
            $dbs=get_dbs();
            $rows=$dbs->select('select * from dates order by date');
            if(!$no_cache && self::$cache)
                $Cache->set(cache_key('date:rows'),$rows,self::$cache_expire);
        }
        $this->date=array();
        $this->year=array();
        $this->month=array();
        $this->day=array();
        foreach($rows as $v){
            $this->dates[$v['date']]=$v;
            $date=$this->parse_date($v['date']);
            switch($v['type']){
                case 'D' : $this->day[$date['y']][$date['m']][$date['d']]=$v; break;
                case 'M' : $this->month[$date['y']][$date['m']]=$v; break;
                case 'Y' : $this->year[$date['y']]=$v; break;
                case 'L' : $this->live[$v['date']]=$v; break;
            }
        }
    }
    static function type($date)
    {
        if(!is_numeric($date))
            return false;
        if($date===0)
            return 'T';
        switch(strlen($date)){
            case 8: return 'D';
            case 6: return 'M';
            case 4: return 'Y';
            case 2:
            case 1: return 'L';
        }
        return false;
    }
    /**
     * Formats a date. Returns unescaped HTML
     * 
     * @param string $date Date with year, month and/or day, or 24/48
     * @return string|false
     */
    static function format($date)
    {
        if(strlen($date)==4)
            $d=$date.'0101';
        elseif(strlen($date)==6)
            $d=$date.'01';
        else
            $d=$date;
        $time=strtotime(str_pad($d,14,'0'));
        switch(self::type($date)){
            case 'D': return date('j',$time).' '.msg('month-long-'.date('n',$time)).' '.date('Y',$time);
            case 'M'; return msg('month-long-'.date('n',$time)).' '.date('Y',$time);
            case 'Y'; return $date;
            case 'L'; return $date.' h';
            case 'T'; return 'Total';
        }
        return false;
    }
    static function valid_date($date)
    {
        if(is_numeric($date)){
            if($date==0)
                return 0;
            switch(strlen($date)){
                case 8: if($date>=20010101 && $date<=gmdate('Ymd')) return $date; break;
                case 6: if($date>=200101 && $date<=gmdate('Ym')) return $date; break;
                case 4: if($date>=2001 && $date<=gmdate('Y')) return $date; break;
                case 2:
                case 1: if($date<=48) return $date; break;
            }
        }
        return false;
    }
    static function parse_date($date)
    {
        if(is_numeric($date)){
            $type=self::type($date);
            if($date==0)
                return array('type'=>$type,'date'=>(int)$date,'all'=>true);
            switch(strlen($date)){
                case 8: return array('type'=>$type,'date'=>(int)$date,'y'=>(int)substr($date,0,4),'m'=>(int)substr($date,4,2),'d'=>(int)substr($date,6,2));
                case 6: return array('type'=>$type,'date'=>(int)$date,'y'=>(int)substr($date,0,4),'m'=>(int)substr($date,4,2),'d'=>0);
                case 4: return array('type'=>$type,'date'=>(int)$date,'y'=>(int)$date,'m'=>0,'d'=>0);
                case 2:
                case 1: return array('type'=>$type,'date'=>(int)$date,'h'=>(int)$date,'live'=>true);
            }
        }
        return false;
    }
    function current()
    {
        $date=isset($_GET['date'])?$_GET['date']:'24';
        if($this->parse_date($date))
            return $date;
        return false;
    }
    function menu($date, $list)
    {
        global $conf;
        if(!$d=$this->parse_date($date))
            return false;
        if(empty($this->year))
            return false;
        $o='<div class="dates_menu">';
        for($y=min(array_keys($this->year));$y<=max(array_keys($this->year));$y++){
            $o.= @$d['date']==$y || @$d['y']==$y ? '<div class="sel">' :'<div>';
            $m=isset($this->year[$y]['pages']) && $this->year[$y]['pages']>0 ? false : 1; //display year if pages are not truncated, else display first month
            $o.=lnk(substr($y,-2), array('menu'=>'dates','date'=>$this->format_date($y, $m)),array('list','filter','sort')).'</div>';
        }
        $o.='</div>';

        $o.='<div class="dates_menu">';
        for($m=1;$m<=12;$m++){
            $o.= isset($d['m']) && $d['m']==$m ? '<div class="sel">' :'<div>';
            if(@$this->month[$d['y']][$m]['edits']>0)
                $o.=lnk(htmlspecialchars(msg("month-long-$m")), array('menu'=>'dates','date'=>$this->format_date($d['y'],$m)),array('list','filter','sort')).'</div>';
            else
                $o.=htmlspecialchars(msg("month-long-$m")).'</div>';
        }
        $o.='</div>';
        if($conf['base_calc']=='day' && isset($d['m'])&&$d['m']!=0){
            $o.='<div class="dates_menu">';
            $nbd=date('t',mktime(0,0,0,$d['m'],1,$d['y']));
            for($v=1;$v<=$nbd;$v++){
                $o.= isset($d['d']) && $d['d']==$v ? '<div class="sel">' :'<div>';
                if(@$this->day[$d['y']][$d['m']][$v]['edits']>0)
                    $o.=lnk($v,array('menu'=>'dates','date'=>$this->format_date($d['y'],$d['m'],$v)),array('list','filter','sort')).'</div>';
                else
                    $o.=$v.'</div>';
            }
            $o.='</div>';
        }
        if($date!=0){
            switch($d['type']){
                case 'D':
                    if(checkdate($d['m'],$d['d'],$d['y'])){
                        $t=mktime(0,0,0,$d['m'],$d['d'],$d['y']);
                        $title=/*self::$intl['day'][date('w',$t)].' '.*/$d['d'].' '.msg("month-long-".$d['m']).' '.$d['y'];
                        $t_prev=date('Ymd',strtotime('-1 day',$t));
                        $t_next=date('Ymd',strtotime('+1 day',$t));
                    }
                    break;
                case 'M':
                    if(checkdate($d['m'],1,$d['y'])){
                        $t=mktime(0,0,0,$d['m'],1,$d['y']);
                        $title=msg("month-long-".$d['m']).' '.$d['y'];
                        $t_prev=date('Ym',strtotime('-1 month',$t));
                        $t_next=date('Ym',strtotime('+1 month',$t));
                    }
                    break;
                case 'Y':
                    $t=mktime(0,0,0,1,1,$d['y']);
                    $title=$d['y'];
                    $t_prev=date('Y',strtotime('-1 year',$t));
                    $t_next=date('Y',strtotime('+1 year',$t));
                    break;
                case 'L':
                    return false;
            }
            if(self::valid_date($t_prev)!==false)
                $prev=lnk('<img class="date_prev" src="/imgi/icons/prevb.png"/>',array('menu'=>'dates','date'=>$t_prev),array('list','filter','sort'));
            else
                $prev='';
            if(self::valid_date($t_next)!==false)
                $next=lnk('<img class="date_next" src="/imgi/icons/nextb.png"/>',array('menu'=>'dates','date'=>$t_next),array('list','filter','sort'));
            else
                $next='';
            $o.='<div class="date_title">';
            $o.="<h1>".htmlspecialchars(msg("toplist-dates-title-$list"))."<br>$prev<span class=date_block>".htmlspecialchars($title)."</span>$next</h1>";
            $o.="</div>";
        }
        return $o;
    }
    function menu2($date, $list)
    {
        global $conf;
        if(!$d=$this->parse_date($date))
            return false;
        $o='<table class="dates_menu" cellspacing="0"><tr>';
        $years=array_keys($this->month);
        $miny=min($years);
        $maxy=max($years);
        for($y=$miny;$y<=$maxy; $y++){
            $o.= @$d['date']==$y || @$d['y']==$y ? '<td class="sel">' :'<td>';
            $o.=lnk($y,array('menu'=>'dates','date'=>$this->format_date($y,1)),array('list','filter','sort')).'!</td>';
        }
        $o.='</tr><tr>';
        for($y=$miny;$y<=$maxy; $y++){
            $o.='<td>';
            for($m=1;$m<=12;$m++){
                $o.='.';
            }
            $o.='</td>';
        }
        $o.='</tr></table>';

        return $o;
    }

    static function menu_live($date, $list)
    {
        global $conf;
        $dbs=get_dbs();
        $rows=$dbs->select("select * from dates where type='L'");
        $o='<div class="live_menu">';
        foreach($rows as $v){
            if(time()-strtotime($v['last_update'])>86400)
                continue;
            $o.= '<div class="live_menu_item'.($v['date']==$date?'_sel':'').'">';
            $o.=lnk($v['date'].'&nbsp;h', array('menu'=>'live','date'=>$v['date']),array('list','sort','filter')).'</div>';
        }
        $o.='</div>';
        return $o;
    }

    function format_date($y,$m=false,$d=false)
    {
        $o=$y;
        if($m!==false)
            $o.= $m>=10 ? $m : '0'.$m;
        if($d!==false)
            $o.= $d>=10 ? $d : '0'.$d;
        return $o;
    }
    function view($date)
    {
        global $conf;
        $d=$this->parse_date($date);
        $menu='alldates';
        $col='edits';
        $o='<div class=main_title><h1>Statistiques par date</h1></div>';
        $o.='<div class=main_contents>';
        $o.='<table class="mep"><tr><td>';
        $o.='<table class="alldates" cellspacing="0"><tr>';
        $o.='<tr><td></td><td>Total</td>';
        for($m=1;$m<=12;$m++){
            $o.='<td>';
            $o.=msg("month-long-$m").'</td>';
            $o.='</td>';
        }
        $o.='</tr><tr>';
        $max=0;
        foreach(array_keys($this->month) as $y)
            foreach($this->month[$y] as $v)
                if($v[$col]>$max)
                    $max=$v[$col];
        foreach(array_reverse(array_keys($this->month)) as $y){
            $v=isset($this->year[$y])?$this->year[$y]:null;
            $o.='<tr><td>'.lnk($y,array('menu'=>$menu,'date'=>$this->format_date($y))).'</td>';
            $o.='<td class="'.(@$v['refresh']?'refresh':'').'" style="background-color:'.$this->last_update_color(@$v['last_update']).'">';
            $o.=lnk(isset($v[$col]) ? $this->fnum($v[$col]) : '?' ,array('menu'=>$menu,'date'=>$this->format_date($y)));
            $o.='</td>';
            for($m=1;$m<=12;$m++){
                $o.='<td class="'.(@$this->month[$y][$m]['refresh']?'refresh':'').'"  style="background-color:'.$this->last_update_color(@$this->month[$y][$m]['last_update']).'">';
                if(isset($this->month[$y][$m]))
                    $o.=lnk($this->fnum((int)@$this->month[$y][$m][$col]) ,array('menu'=>$menu,'date'=>$this->format_date($y,$m)));
                else
                    $o.='&nbsp;';
                $o.='</td>';
            }
            $o.='</tr>';
        }
        $o.='</table>';
        if($d && isset($d['y']) && $d['y']!=0){
            $o.='<table class="alldates alldates_year" cellspacing="0">';
            $y=$d['y'];
            $o.="<tr><td colspan=32><h2>$y</h2></td></tr>";
            $o.='<tr><td></td>';
            for($v=1;$v<=31;$v++){
                $o.= isset($d['d']) && $d['d']==$v ? '<td class="sel">' :'<td>';
                $o.=$v.'</td>';
            }
            $max=0;
            for($m=1;$m<=12;$m++){
                $nbd=date('t',mktime(0,0,0,$m,1,$y));
                for($v=1;$v<=$nbd;$v++)
                    if(@$this->day[$y][$m][$v][$col]>$max)
                        $max=$this->day[$y][$m][$v][$col];
            }
            $o.='</tr>';
            for($m=12;$m>=1;$m--){
                $nbd=date('t',mktime(0,0,0,$m,1,$y));
                $o.='<tr>';
                $o.='<td>'.msg("month-long-$m").'</td>';
                for($v=1;$v<=$nbd;$v++){
                    $o.='<td class="'.(@$this->day[$y][$m][$v]['refresh']?'refresh':'').'" style="background-color:'.$this->last_update_color(@$this->day[$y][$m][$v]['last_update']).'">';
                    if(isset($this->day[$y][$m][$v]))
                        $o.=lnk($this->fnum((int)@$this->day[$d['y']][$m][$v][$col]) ,array('menu'=>$menu,'date'=>$this->format_date($d['y'],$m,$v)));
                    else
                        $o.='&nbsp;';
                    $o.='</td>';
                }
                $o.='</tr>';
            }
            $o.='</table>';
        }
        $o.='</td></tr>';
        if($d){
            $o.='<tr><td>';
            $o.="<div class=all_dates_contents>";
            require_once('include/toplist_stats.php');
            $tl=new TopListStats('',$date);
            $tl->load_params();
            $o.=$tl->view();
            $o.="</div>";
            $o.='</td></tr>';
        }
        $o.='</table></div>';
        return $o;
    }
    function box($n, $max)
    {
        $height=25;
        $h=round($n*$height/$max);
        return '<div class="gbox" style="height:'.$h.'px" title="'.fnum($n).'">'.$this->fnum($n).'</div>';
    }
    function fnum($n)
    {
        if($n<1000)
            return fnum($n);
        if($n<10000)
            return fnum($n/1000, 1).' k';
        return fnum($n/1000).' k';
    }
    function last_update_color($last_update)
    {
        if($last_update==0)
            return "#f0f0f0";
        $diff=(time()-strtotime($last_update))/3600;
        $c=round(0xfa-$diff*1.5);
        if($c<0x70)
            $c=0x70;
        $c2=0xc0-round($diff*1.5);
        if($c2<=0x50)
            $c2=0x50;
        return "#".dechex($c2).dechex($c).dechex($c2);

        $diff=time()-strtotime($last_update);
        if($diff<=86400)
            return "#a0ffa0";
        if($diff<=86400*2)
            return "#90ef90";
        if($diff<=86400*5)
            return "#80e080";
        if($diff<=86400*15)
            return "#60af60";
        if($diff<=86400*30)
            return "#509f50";
        return "#e0e0e0";
    }
}

?>
