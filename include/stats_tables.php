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
class StatsTables extends site_page
{
    function __construct()
    {
    }
    function cache_key()
    {
    }
    function load($date)
    {
        $this->data=UpdateStats::load_stat($date, 'stats');
    }
    function view($date)
    {
        $this->date=$date;
        $this->load($date);
        $s=$this->data;
        $o='<div class="stats_tables">';
        $o.=$this->view_block('Éditions', array('total'=>@$s['total']['edit'], 'user'=>$s['user']['edit'], 'ip'=>$s['ip']['edit'], 'bot'=>$s['bot']['edit']));
        $o.=$this->view_block('Temps', array('total'=>@$s['total']['tot_time2'], 'user'=>$s['user']['tot_time2'], 'ip'=>$s['ip']['tot_time2'], 'bot'=>$s['bot']['tot_time2']));
        $o.=$this->view_block('Diff', array('total'=>@$s['total']['diff'], 'user'=>$s['user']['diff'], 'ip'=>$s['ip']['diff'], 'bot'=>$s['bot']['diff']));
        $o.=$this->view_block("Créations d'articles", array('total'=>@$s['total']['new']['article'], 'user'=>$s['user']['new']['article'], 'ip'=>$s['ip']['new']['article'], 'bot'=>$s['bot']['new']['article']));
        $o.=$this->view_block("Répartition des éditions", $s['total']['nscateg']);
        $o.=$this->view_block("Répartition du temps", $s['total']['tot_time2_nscateg']);
        $o.='</div>';
        echo '<pre>';
        print_r($s);
        echo '</pre>';
        return $o;
    }
    function view_block($title, $data)
    {
        arsort($data);
        $o='<div class="st_block">';
        $o.='<h3>'.$title.'</h3>';
        $o.='<table class="st_table">';
        foreach($data as $k=>$v){
            $o.='<tr><td>'.$k.' :</td><td>'.fnum($v).'</td>';
            if(!isset($first)){
                $o.='<td rowspan=4>';
                $o.='<div class="st_graph">';
                $d=$data;
                unset($d['total']);
                $o.=$this->pie_graph($d, 100);
                $o.='</div>';
                $o.='</td>';
                $first=false;
            }
            $o.='</tr>';
        }
        $o.='</table>';
        $o.='</div>';
        return $o;
    }

    function pie_graph($data, $size)
    {
        $total=array_sum($data);
        $isize=400;
        $center=floor($isize/2);
        $o="<svg width='$size' height='$size' viewBox='0 0 $isize $isize' preserveAspectRatio='xMidYMax meet' xmlns='http://www.w3.org/2000/svg' version='1.1'>";
        foreach($data as $k=>$v)
            $angles[$k]=ceil(360*$v/$total);

        $end=270;
        foreach($angles as $k=>$angle){
            $start = $end;
            $end = $start + $angle;
            $x1 = intval($center + 180*cos(pi()*$start/180));
            $y1 = intval($center + 180*sin(pi()*$start/180));
            $x2 = intval($center + 180*cos(pi()*$end/180));
            $y2 = intval($center + 180*sin(pi()*$end/180));
            $large= $angle > 180 ? 1 : 0;
            $d = "M$center,$center  L$x1,$y1 A180,180 0 $large,1 $x2,$y2 z";
            $o.="<path class='graph_$k' d='$d'></path>\n";
        }
        $o.="</svg>";
        return $o;
    }

}

?>