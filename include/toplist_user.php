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
class TopListUser extends TopList
{
    var $ext_link='https://fr.wikipedia.org/wiki/Spécial:Contributions/';
    var $link_max_len=30;
    var $reduce=true;
    var $reduce_limit=2000;
    var $filter='all';
    var $sort='weight';
    var $mini_size=10;
    var $mini_expand_size=30;

    function __construct($date=false, $filter=false, $sort=false, $mini=false)
    {
        parent::__construct($date, $filter, $sort, $mini);
        $this->data_name='users';
        $this->list='users';
        $this->filters=array('all', 'user', 'ip', 'bot', 'sysop');
        $this->sorts=array(
            'weight'=>array('weight','edit'),
            'edit'=>array('edit','weight'),
            'revert'=>array('revert','weight'),
            'log'=>array('log','weight','revert'),
            'diff'=>array('diff_abs','weight'),
            'diff_tot'=>array('diff_tot','weight'),
            'chain'=>array('chain','weight'),
            'tot_size'=>array('tot_size','weight'),
            'tot_time'=>array('tot_time2','weight'),
            );
        $this->sort_cols=array('edit', 'revert', 'log', 'diff', 'diff_tot', 'tot_size', 'tot_time', 'speed', 'user');
        $this->sort_images=array(
            'edit'=>'imgi/edit.png',
            'revert'=>'imgi/revert.png',
            );
        $this->graphs=array('users');
    }
    function filter()
    {
        if(empty($this->data)||$this->filter=='')
            return false;
        $groups=mwTools::user_groups();
        foreach($this->data as $user=>$v){
            if($this->filter=='all'
            || $this->filter=='user' && $v['type']=='user' && !isset($groups[$user]['bot'])
            || $this->filter=='ip' && $v['type']=='ip'
            || $this->filter=='bot' && isset($groups[$user]['bot'])
            || $this->filter=='sysop' && isset($groups[$user]['sysop']))
                continue;
            unset($this->data[$user]);
        }
        return true;
    }
    function reduce()
    {
        $count=count($this->data);
        if($count<=5000)
            $min_weight=2;
        elseif($count<=10000)
            $min_weight=4;
        elseif($count<=20000)
            $min_weight=8;
        elseif($count<=50000)
            $min_weight=15;
        else
            $min_weight=20;
        foreach(array_keys($this->data) as $k){
            if(@$this->data[$k]['weight']<$min_weight)
                unset($this->data[$k]);
                continue;
        }
    }
    function render_list()
    {
        global $conf;
        $o='<table class="list_list" cellspacing="0">';
        $o.=$this->view_filters();
        $o.=$this->view_sorts();
        reset($this->data);
        for($i=0;$i<$this->list_size;$i++){
            $user=key($this->data);
            if($user===null)
                break;
            $v=current($this->data);
            next($this->data);
            $user=str_replace('_',' ',$user);
            $icons='';
            $o.='<tr>';
            $o.='<td>'.(int)@$v['edit'].'</td>';
            $o.='<td>'.@$v['revert'].'</td>';
            $o.='<td>'.@$v['log'].'</td>';
            $o.='<td>'.format_diff(@$v['diff']).'</td>';
            $o.='<td>'.format_sizei(@$v['diff_tot']).'</td>';
            $o.='<td>'.format_sizei(@$v['tot_size']).'</td>';
            $tt=floor((int)@$v['tot_time2']/300)*300;
            $o.='<td>'.format_hour($tt).'</td>';
            $speed= $tt!=0 ? 60*@$v['total']/$tt : 0;
            if($speed<1)
                $speed=round($speed*60).'/h';
            else
                $speed=round($speed).'/m';
            $o.='<td>'.$speed.'</td>';
            if($v['type']!='ip'){
                $usert=mb_strlen($user)>$this->link_max_len ? mb_substr($user,0,$this->link_max_len-2).'..' : $user;
                $o.='<td class="name"><a href="/utilisateur/'.mwtools::encode_user($user).'">'.htmlspecialchars($usert).'</a></td>';
            }else{
                $usert=mb_strlen($user)>$this->link_max_len ? mb_substr($user,0,$this->link_max_len-2).'..' : $user;
                $o.='<td class="name"><a href="/ip/'.mwtools::encode_user($user).'">'.htmlspecialchars($usert).'</a></td>';
            }
            $o.'</tr>';
        }
        $o.='</table>';
        return $o;
    }
    function render_list_mini($size=10)
    {
        global $conf;
        $o='<table class="mini_list" cellspacing="0">';
        remove_values($this->sort_cols, array('tot_size', 'tot_time', 'diff_tot'));
        if($this->filter=='ip')
            remove_value($this->sort_cols, 'log');
        $o.=$this->view_sorts(false);

        reset($this->data);
        for($i=0;$i<$this->mini_expand_size;$i++){
            $user=key($this->data);
            if($user===null)
                break;
            $v=current($this->data);
            next($this->data);
            $user=str_replace('_',' ',$user);
            $icons='';
            if($i+1==$this->mini_size+1){
                $o.="<tr class='mini_expand' style='display:table-row'><td class='mini_expand_link' colspan=10><a href='#' onclick='return tmin(this);'><img src='/imgi/icons/expand.png'/><img src='/imgi/icons/expand.png'/></a></td></tr>";
                $o.="<tr class='mini_expand'><td class='mini_expand_link' colspan=10><a href='#' onclick='return tmin(this);'><img src='/imgi/icons/expand_up.png'/><img src='/imgi/icons/expand_up.png'/></a></td></tr>";
            }
            if($i+1>$this->mini_size)
                $o.="<tr class='mini_expand'>";
            else
                $o.='<tr>';
            $o.='<td>'.(int)@$v['edit'].'</td>';
            $o.='<td>'.@$v['revert'].'</td>';
            if($this->filter!='ip')
                $o.='<td>'.@$v['log'].'</td>';
            $o.='<td>'.format_diff(@$v['diff']).'</td>';
            $tt=floor((int)@$v['tot_time2']/300)*300;
            $speed= $tt!=0 ? 60*@$v['total']/$tt : 0;
            if($speed<1)
                $speed=round($speed*60).'/h';
            else
                $speed=round($speed).'/m';
            $avg=round(@$v['tot_time2']/@$v['total']);
            $avg=round($avg/10)*10;
            $o.='<td>'.format_time($avg).'</td>';

            if($v['type']!='ip'){
                $usert=mb_strlen($user)>$this->link_max_len ? mb_substr($user,0,$this->link_max_len-2).'…' : $user;
                $o.='<td class="name"><a href="/utilisateur/'.mwtools::encode_user($user).'">'.htmlspecialchars($usert).'</a></td>';
            }else{
                if(preg_match('/^((?:[\da-f]{1,4}:){4})([\da-f]{1,4}:){3}[\da-f]{1,4}$/i',$user,$res))
                    $usert=strtolower(substr($res[1],0,-1)).'…';
                else
                    $usert=mb_strlen($user)>$this->link_max_len ? mb_substr($user,0,$this->link_max_len-2).'…' : $user;
                $o.='<td class="name"><a href="/ip/'.mwtools::encode_user($user).'">'.htmlspecialchars($usert).'</a></td>';
            }
            $o.'</tr>';
        }
        $o.='</table>';
        return $o;
    }
    function icons($v)
    {
        $o='';
        if(@$v['revert']>0)
            $o.=$v['revert'].'<img src="imgi/revert.png" width="8"/>';
        if(@$v['log']>0)
            $o.=$v['log'].'<img src="imgi/48px-Text-x-generic_with_pencil.svg.png" width="8"/>';
        return $o;
    }
}
?>