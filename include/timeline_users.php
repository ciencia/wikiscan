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

class Timeline_users
{
    var $height=5000;
    var $width=5500;
    var $border=10;
    var $author_size=10;
    var $ybase;
    var $xbase;
    var $add_ip=true;
    var $remove_bots=true;
    var $add_legend=true;
    var $legend_size=50;
    var $legend_bar_size=10;
    var $auto_crop=true;
    var $pixel_edits=10;
    var $pixel_users=2;
    var $color_inc=1;//1.333;
    var $color_startR=0xff;
    var $color_startG=0x00;
    var $color_startB=0x00;
    var $color_start_level=6;
    var $last;
    var $dates;
    var $types=array('users','edits_noip','edits');
    var $max_value;
    var $highlight=false;
    var $preview=true;
    var $author='wikiscan.org';
    var $copyright='CC BY-SA 3.0';
    var $view_blank=false;
    var $week=false;
    var $average_num=7;

    function __construct()
    {
        $this->dates=array();
        $this->ybase=$this->height-$this->legend_size-$this->border-$this->author_size-1;
        $this->xbase=$this->border;
        $this->miny=$this->ybase;
        $this->maxx=0;
    }

    function gen($type=false)
    {
        $db=get_db();
        if($type!==false)
            $this->type=$type;
        if($this->remove_bots){
            $this->groups=mwTools::user_groups();
        }
        $this->pos=0;
        $this->set_colors();
        $db->query('start transaction');
        $db->query('SET SESSION net_write_timeout=3600');
        $db->query('SET SESSION net_read_timeout=3600');
        $db->select_walk("select rev_timestamp,rev_user,rev_user_text from revision force index (rev_timestamp) order by rev_timestamp,rev_id",array($this,'data_walk'));
        $db->query('commit');
        $this->draw_types();
        echo "Done\n";
    }
    function draw_types()
    {
        foreach($this->types as $v){
            $this->type=$v;
            $this->draw($v);
            $this->save($v);
        }
        echo 'mem peak: '.round(memory_get_peak_usage(true)/1048576)."Mb\n";
    }
    function data_walk($v)
    {
        if($this->week){
            $time=date('YW',strtotime($v['rev_timestamp']));
            $preview=50;
        }else{
            $time=substr($v['rev_timestamp'],0,8);
            $preview=300;
        }
        if($this->remove_bots && isset($this->groups[$v['rev_user_text']]['bot']))
            return;
        if(!isset($this->dates[$time])){
            if(!$this->view_blank)
                $this->pos++;
            else{
                if(!isset($this->last_time) || $time-$this->last_time==1)
                    $this->pos++;
                else
                    $this->pos+=round((strtotime($time)-strtotime($this->last_time))/86400);
                $this->last_time=$time;
            }
            echo $time.' '.$this->pos."\n";
            if($this->preview && !empty($this->dates) && count($this->dates)%$preview==0)
                $this->draw_types();
        }
        if($v['rev_user']!=0){
            $user=$v['rev_user_text'];
            if(!isset($this->users[$user])){
                $this->users[$user]=$this->pos;
            }
            $pos=$this->users[$user];
        }else{
            $user='IP';
            $pos=0;
        }
        @$this->dates[$time][$pos][$user]++;
    }
    function draw($type=false)
    {
        if($type===false)
            $type=$this->type;
        else
            $this->type=$type;
        echo "Draw $type\n";
        $this->miny=$this->ybase;
        $this->maxx=0;
        $this->img = imagecreatetruecolor($this->width, $this->height);
        $res=false;
        if(!method_exists($this,'draw_line_'.$type))
            return false;
        $this->x=$this->xbase;
        if($this->week)
            $max=gmdate('YW');
        else
            $max=gmdate('Ymd');
        $this->average=array();
        $date=reset($this->dates);
        $year='';
        while($date<=$max){
            $y=substr($date,0,4);
            if($y!=$year){
                echo "$y\n";
                $year=$y;
            }
            if(isset($this->dates[$date]))
                call_user_func(array($this,'draw_line_'.$type),$this->dates[$date]);
            elseif($this->view_blank)
                $this->x++;
            if($this->week){
                $s=substr($date,4,2);
                $s++;
                if($s>=52){
                    $y++;
                    $s='01';
                }
                $date="$y$s";
            }else
                $date=date('Ymd',strtotime("+1 day $date"));
        }
        $this->average=array();
        $this->maxx=$this->x;
        if($this->add_legend)
            $this->draw_legend();
        $this->draw_author();
        if($this->auto_crop){
            $w=$this->maxx+$this->border;
            $h=$this->height-$this->miny;
            $img=imagecreatetruecolor($w, $h);
            imagecopy($img, $this->img, 0, 0, 0, $this->miny-$this->border, $w, $h);
            imagedestroy($this->img);
            $this->img=$img;
        }
        return $res;
    }
    function draw_line_users($values)
    {
        $y=$this->ybase;
        ksort($values);
        foreach($values as $k=>$v)
            $values[$k]=count($v);
        if($this->average_num){
            $this->average[]=$values;
            if(count($this->average)<$this->average_num){
                return;
            }
            if(count($this->average)>$this->average_num)
                array_shift($this->average);
            $values=array();
            foreach($this->average as $vals)
                foreach($vals as $k=>$v)
                    if(isset($values[$k]))
                        $values[$k]+=$v;
                    else
                        $values[$k]=$v;
            foreach($values as $k=>$v)
                $values[$k]/=$this->average_num;
            ksort($values);
        }
        foreach($values as $pos=>$l){
            if($pos==0)
                continue;
            $l=$l/$this->pixel_users;
            $l=round($l);
            imageline($this->img,$this->x,$y,$this->x,$y-$l,$this->get_color($pos));
            if($this->highlight!==false && isset($users[$this->highlight]))
                imagesetpixel($this->img,$this->x,$y,$this->get_color('white'));
            $y-=$l;
        }
        if($y<$this->miny)
            $this->miny=$y;
        $this->x++;
    }
    function draw_line_edits_noip($values)
    {
        $this->draw_line_edits($values);
    }
    function draw_line_edits($values)
    {
        $y=$this->ybase;
        ksort($values);
        foreach($values as $k=>$v)
            $values[$k]=array_sum($v);
        if($this->average_num){
            $this->average[]=$values;
            if(count($this->average)<$this->average_num){
                return;
            }
            if(count($this->average)>$this->average_num)
                array_shift($this->average);
            $values=array();
            foreach($this->average as $vals)
                foreach($vals as $k=>$v)
                    if(isset($values[$k]))
                        $values[$k]+=$v;
                    else
                        $values[$k]=$v;
            foreach($values as $k=>$v)
                $values[$k]/=$this->average_num;
            ksort($values);
        }
        $sum=array();
        foreach($values as $pos=>$v){
            //$v=array_sum($users);
            if($pos==0){
                if($this->type=='edits_noip')
                    continue;
                imageline($this->img,$this->x,$y,$this->x,$y-round($v/$this->pixel_edits),$this->get_color($pos));
                $y-=round($v/$this->pixel_edits);
                continue;
            }
            $sum[$pos]=$v;
            while(($s=array_sum($sum))>=$this->pixel_edits||count($values)==0){
                $pos=round(array_sum(array_keys($sum))/count($sum));
                imagesetpixel($this->img,$this->x,$y--,$this->get_color($pos));
                $l=$this->pixel_edits;
                foreach($sum as $k=>$v){
                    if($v<=$l){
                        unset($sum[$k]);
                        $l-=$v;
                    }else{
                        $sum[$k]-=$l;
                        $l=0;
                    }
                    if($l==0)
                        break;
                }
            }
            if($this->highlight!==false && isset($users[$this->highlight])){
                imageline($this->img,$this->x,$y+1,$this->x,$y+1+round($users[$this->highlight]/$this->pixel_edits),$this->get_color('white'));
            }
        }
        if($y<$this->miny)
            $this->miny=$y;
        $this->x++;
    }

    function draw_legend()
    {
        $barsize=$this->legend_bar_size;
        $pos=0;
        $lasty='';
        $lastm='';
        $y=$this->ybase+10;
        $col=imagecolorallocatealpha($this->img,0xff,0xff,0xff,20);
        if(!$this->view_blank)
            $dates=array_keys($this->dates);
        else{
            $max=gmdate('Ymd');
            $date=reset($this->dates);
            while($date<=$max){
                $dates[]=$date;
                $date=date('Ymd',strtotime("+1 day $date"));
            }
        }
        foreach($dates as $date){
            $pos++;
            $x=$pos+$this->xbase;
            imageline($this->img,$x,$y,$x,$y+$barsize,$this->get_color($pos));
            $year=substr($date,0,4);
            if($year!=$lasty){
                imageline($this->img,$x,$y+$barsize-1,$x,$y+$barsize+5,$col);
                if($x>$this->xbase+5)
                    imagestring($this->img,5,$x-17,$y+$barsize+5,$year,$col);
                $lasty=$year;
            }
            $month=substr($date,0,6);
            if($month!=$lastm){
                imageline($this->img,$x,$y+$barsize-1,$x,$y+$barsize+1,$col);
                $lastm=$month;
            }
        }
    }
    function draw_author()
    {
        $col=imagecolorallocatealpha($this->img,0xff,0xff,0xff,20);
        $y=$this->height-$this->border-20;
        $x=$this->maxx-80;
        imagestring($this->img,3,$x,$y,$this->copyright,$col);
        $x=($this->maxx+2*$this->border)/2-100;
        imagestring($this->img,5,$x,$y,$this->author,$col);
    }
    function view()
    {
        header ('Content-type: image/png');
        imagepng($this->img);
    }
    function save($type=false)
    {
        if($type===false)
            $type=$this->type;
        $file='img/graph_'.$type.'.png';
        imagepng($this->img,$file);
    }

    function test_colors()
    {
        $this->width=800;
        $this->height=600;
        $this->img = imagecreatetruecolor($this->width, $this->height);
        for($y=0;$y<100;$y++){
            $x=0;
            foreach($this->colors as $k=>$v)
                imagesetpixel($this->img,$x++,$this->height-$y,$this->get_color($k));
        }
        for($y=0;$y<100;$y++){
            $x=0;
            foreach($this->colors as $k=>$v)
                imagesetpixel($this->img, $y, $this->height-100-$x++,$this->get_color($k));
        }
        $this->save('test');
    }
    function get_color($pos)
    {
        if($pos==='white')
            return imagecolorallocatealpha($this->img,0xff,0xff,0xff,0);
        if(isset($this->colors[$pos]))
            return imagecolorallocatealpha($this->img,$this->colors[$pos][0],$this->colors[$pos][1],$this->colors[$pos][2],$this->colors[$pos][3]);
        return imagecolorallocatealpha($this->img,60,60,60,0);
    }
    function set_colors()
    {
        $this->colors[0]=array(0,0,0,0);
        $r=$this->color_startR;
        $g=$this->color_startG;
        $b=$this->color_startB;
        $lvl=$this->color_start_level;
        $lum=0x50;
        $dark=0x80;
        for($i=1;$i<=5000;$i++){
            switch($lvl){
                case 1:
                    $c='r';
                    $order=-1;
                    break;
                case 2:
                    $c='g';
                    $order=1;
                    if($$c>=0xa0)
                        $order=1;
                    break;
                case 3:
                    $c='b';
                    $order=-1;
                    break;
                case 4:
                    $c='r';
                    $order=1;
                    break;
                case 5:
                    $c='g';
                    $order=-1;
                    if($$c>=0xa0)
                        $order=-1;
                    break;
                case 6:
                    $c='b';
                    $order=1;
                    break;
            }

            if($g>=0xc0 && ($b<=0x60 && $r<=0x60))
                $order*=2;
            if($r>=0xf0 && ($g<=0x30 && $b<=0x30))
                $order*=2;
            if($r>=0xe0 && $g==0 && $b>=0x60 && $b<=0xa0)
                $order*=2;

            $$c+=$order*$this->color_inc;
            if($$c<=0){
                $$c=0;
                $lvl++;
            }elseif($$c>=0xff){
                $$c=0xff;
                $lvl++;
            }
            if($lvl>6){
                $lvl=1;
            }
            $mr=$r;
            $mg=$g;
            $mb=$b;
            foreach(array('mr','mg','mb') as $v){
                $$v+=round($$v*($lum/255));
                $$v-=round($$v*($dark/255));
                if($$v>255)
                    $$v=255;
                if($$v<0)
                    $$v=0;
            }
            $this->colors[$i]=array($mr,round($mg*0.95),$mb,0);
        }
    }
}




?>