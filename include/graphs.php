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

class Graphs
{

    static function pie($data, $size=false, $text=false)
    {
        $total=array_sum($data);
        $isize=400;
        $center=floor($isize/2);
        $esize=$size ? "width='$size' height='$size'" : "";
        $o="<svg $esize viewBox='0 0 $isize $isize' preserveAspectRatio='xMidYMax meet' xmlns='http://www.w3.org/2000/svg' version='1.1'>";
        foreach($data as $k=>$v)
            $angles[$k]=ceil(360*$v/$total);
        $end=270;
        $i=0;
        foreach($angles as $k=>$angle){
            $start = $end;
            $end = $start + $angle;
            $x1 = intval($center + 180*cos(pi()*$start/180));
            $y1 = intval($center + 180*sin(pi()*$start/180));
            $x2 = intval($center + 180*cos(pi()*$end/180));
            $y2 = intval($center + 180*sin(pi()*$end/180));
            $large= $angle > 180 ? 1 : 0;
            $d = "M$center,$center  L$x1,$y1 A180,180 0 $large,1 $x2,$y2 z";
            $i++;
            $o.="<path class='graph_c$i' d='$d'></path>\n";
            if($text){
                $mid=intval($start+ $angle/2);
                $x_mid = intval($center + 180*cos(pi()*$mid/180)*0.2);
                $y_mid = intval($center + 180*sin(pi()*$mid/180)*0.2);
                $o.="<text x='".($x_mid)."' y='".($y_mid)."' transform='rotate($mid $x_mid,$y_mid)' class='text_pie'>$k</text>\n";
            }
        }
        $o.="</svg>";
        return $o;
    }

    static function graph_path($data, $allkeys, $height, $max, $xinc=1, $class='')
    {
        $o='';
        $x=0;
        $o.="<path class='$class' d='M0,$height";
        foreach($allkeys as $key){
            $v=isset($data[$key]) ? $data[$key] : 0;
            $y=$height-($max!=0 ? round($height*$v/$max) : 0);
            $o.=" $x,$y";
            $x+=$xinc;
        }
        $x-=$xinc;
        $o.=" $x,$height Z'></path>\n";
        return $o;
    }
    static function graph_line($data, $allkeys, $height, $max, $xinc=1, $class='')
    {
        $o='';
        $x=0;
        $o.="<polyline class='$class' points='";
        foreach($allkeys as $key){
            if(isset($data[$key])){
                $v=$data[$key];
                $y=$height-($max!=0 ? round($height*$v/$max) : 0);
                $o.=" $x,$y";
            }
            $x+=$xinc;
        }
        $o.="'/>\n";
        return $o;
    }
    static function graph_axes($allkeys, $height, $max, $max_average, $xinc=1, $class='')
    {
        $o='';
        $x=0;
        foreach($allkeys as $key){
            $y=substr($key,0,4);
            $m=substr($key,4,2);
            if($y!=date('Y'))
                $months=12;
            else
                $months=date('m')-1;
            if($m==1){
                $o.="<line x1='$x' y1='0' x2='$x' y2='$height' class='$class'/>\n";
                if($months>=6)
                    $o.="<text x='".($x+$xinc*ceil($months/2))."' y='".($height-5)."' class='{$class}_text_x'>$y</text>\n";
            }
            $x+=$xinc;
        }
        $x-=$xinc;
        $y=self::roundy($max);
        if($height-@round($height*$y/$max)>=10)
            $o.="<text x='4' y='12' class='{$class}_text_y'>".number_format($max,0,0,' ')."</text>\n";
        $ytext='';
        if($y!=0){
            $ytext=$y;
            $y=$height-round($height*$y/$max);
            $o.="<line x1='0' y1='$y' x2='$x' y2='$y' class='$class'/>\n";
            $o.="<text x='4' y='".($y+12)."' class='{$class}_text_y'>".number_format($ytext,0,0,' ')."</text>\n";
        }
        if($max_average){
            $line_y=$ytext;
            $y=self::roundy($max_average);
            if($y!=0 && $y!=$line_y){
                $ytext=$y;
                $y=$height-round($height*$y/$max);
                $o.="<line x1='0' y1='$y' x2='$x' y2='$y' class='$class'/>\n";
                $o.="<text x='4' y='".($y+12)."' class='{$class}_text_y'>".number_format($ytext,0,0,' ')."</text>\n";
            }
        }
        return $o;
    }
    static function roundy($max)
    {
        $max=floor($max);
        $len=strlen($max);
        $y=0;
        if($len==2){
            if($max>55)
                $y=50;
        }else{
            $y=floor($max/(pow(10,$len-1)))*(pow(10,$len-1));
        }
        return $y;
    }

    static function data_average($data, $average=3, $round=0)
    {
        $lasts=array();
        $res=array();
        foreach($data as $k=>$v){
            $lasts[]=$v;
            $keys[]=$k;
            if(count($lasts)<$average)
                continue;
            $i=0;
            foreach($keys as $key)
                if(++$i>=$average/2)
                    break;
            $res[$key]=round(array_sum($lasts)/count($lasts), $round);
            array_shift($lasts);
            array_shift($keys);
        }
        $i=0;
        foreach($keys as $key){
            if(++$i<=$average/2-1 && count($data)>$average/2-1)
                continue;
            $res[$key]=round(array_sum($lasts)/count($lasts), $round);
            array_shift($lasts);
            array_shift($keys);
            if(count($keys)<=$average/2)
                break;
        }
        return $res;
    }

}


?>