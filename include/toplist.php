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
require_once('include/update_stats.php');
require_once('include/site_page.php');

class TopList extends site_page
{
    var $data;
    var $data_name;
    var $date;
    var $filters;
    var $filter;
    var $sorts;
    var $sort;
    var $order=-1;
    var $list_size=150;
    var $mini_list_size=20;
    var $link_max_len=100;
    var $ext_link;
    var $cache=true;
    var $cache_expire=172800;//2 days
    var $loaded=false;
    var $reduce=false;
    var $reduce_limit=1000;
    var $mini=false;
    var $new_only=false;

    function __construct($date=false, $filter=false, $sort=false, $mini=false)
    {
        $this->date=$date;
        if($filter!==false)
            $this->filter=$filter;
        if($sort!==false)
            $this->sort=$sort;
        if($mini!==false)
            $this->mini=$mini;
    }
    static function create($type, $date=false, $filter=false ,$sort=false, $mini=false)
    {
        switch($type){
            case 'stats':
                require_once('toplist_stats.php');
                return new TopListStats($date, $filter, $sort, $mini);
            case 'users':
                require_once('toplist_user.php');
                return new TopListUser($date, $filter, $sort, $mini);
            case 'pages':
                require_once('toplist_page.php');
                return new TopListPage($date, $filter, $sort, $mini);
        }
        return false;
    }
    function view()
    {
        if($this->date===false)
            return false;
        if(DEBUG) Debug::mem('tl view');
        if(!isset($_GET['purge'])||!$_GET['purge'])
            if($out=$this->get_cache())
                return $out;
        if($this->load_data()){
            if(DEBUG) Debug::mem('tl load');
            $this->filter();
            if($this->new_only)
                $this->filter_new();
            if(DEBUG) Debug::mem('tl filter');
            if($this->reduce && count($this->data)>=$this->reduce_limit){
                $this->reduce();
                if(DEBUG) Debug::mem('tl reduce');
            }
            $this->sort();
            if(DEBUG) Debug::mem('tl sort');
        }
        if(!$this->mini)
            $out='<div class="list">'.$this->render().'</div>';
        else
            $out='<div class="mini_list">'.$this->render_mini().'</div>';
        if(DEBUG) Debug::mem('tl render');
        $this->set_cache($out);
        return $out;
    }
    function reduce()
    {
    }
    function load_data()
    {
        $this->data='';
        if($this->date!==false)
            $this->data=UpdateStats::load_stat($this->date,$this->data_name);
        $this->loaded=!empty($this->data);
        return $this->loaded;
    }
    function loaded()
    {
        return $this->loaded;
    }
    function load_params()
    {
        if(isset($_GET['date']))
            $date=$_GET['date'];
        else
            $date=$this->date;
        if(($date=Dates::valid_date($date))===false)
            return false;
        $this->date=$date;
        if(isset($_GET['sort']))
            if(isset($this->sorts[$_GET['sort']]))
                $this->sort=$_GET['sort'];
            // We don't bother about invalid sorts!
            //else
            //    return false;
        if(isset($_GET['filter']))
            if(in_array($_GET['filter'], $this->filters))
                $this->filter=$_GET['filter'];
            else
                return false;
        return true;
    }
    function valid_cache_date($cache_date,$db_date=false)
    {
        if($db_date===false)
            $db_date=$this->date;
        $row=Dates::get($db_date);
        if(!isset($row['last_update']))
            return false;
        return $cache_date>$row['last_update'];
    }
    function cache_key()
    {
        return implode(':',array('toplist',$this->data_name,$this->mini,$this->date,$this->filter,$this->sort,$this->new_only));
    }

    function sort()
    {
        if(empty($this->data)||!isset($this->sorts[$this->sort]))
            return false;
        $t=microtime(true);
        $tot=count($this->data);
        uasort($this->data,array($this,'compare'));
    }
    function compare($a,$b)
    {
        if(isset($this->sorts[$this->sort])){
            foreach($this->sorts[$this->sort] as $k){
                if(@$a[$k]>@$b[$k])
                    return $this->order;
                if(@$a[$k]<@$b[$k])
                    return -$this->order;
            }
        }
        return 0;
    }

    function filter()
    {

    }
    function render()
    {
        $o='<div class="list_contents">';
        $o.=$this->menu_list();
        if($this->loaded){
            $o.='<div class="wrap_max_fullwidth">';
            $o.=$this->render_list();
            $o.='</div>';
        }else
            $o.='<div class="message"></div>';
        $o.='</div>';
        return $o;
    }
    function render_mini()
    {
        $o='';
        if(!empty($this->data))
            $o.=$this->render_list_mini();
        return $o;
    }
    function view_filters()
    {
        global $Site;
        if(!empty($this->filters)){
            foreach($this->filters as $k){
                $v=htmlspecialchars(msg("toplist-{$this->list}-filter-$k"));
                $oo[]=lnk($k===$this->filter ? "<b><u>$v</u></b>" : $v, array('menu'=>$Site->menu,'filter'=>$k,'sort'=>$this->sort));
            }
            return '<tr class="filters"><td colspan="20"><div class="list_filters">'.implode(' | ',$oo).'</div></td></tr>';
        }
        return false;
    }
    function view_sorts($link=true)
    {
        global $Site;
        if(empty($this->sort_cols))
            return false;
        $o='<tr class="sorts">';
        foreach($this->sort_cols as $k){
            if(isset($this->sort_images[$k]))
                $title='<img src="'.htmlspecialchars($this->sort_images[$k]).'" alt="'.htmlspecialchars(msg("toplist-{$this->list}-sort-$k-alt")).'"/>';
            else
                $title=htmlspecialchars(msg("toplist-{$this->list}-sort-$k"));
            $o.="<td class='tl-$k'>";
            if($link && isset($this->sorts[$k]))
                $o.=lnk($title, array('menu'=>$Site->menu,'filter'=>$this->filter,'sort'=>$k));
            else
                $o.=$title;
            $o.='</td>';
        }
        return $o.'</tr>';
    }
    function link($label,$attr)
    {
        return lnkp($label,$attr,array('menu','list','date','filter','sort'));
    }
    function menu_list()
    {
        global $Site;
        $o=array();
        foreach(array('pages', 'users', 'stats') as $k){
            $text=msg("toplist-menu-$k");
            $link='<a href="/'.htmlspecialchars(($Site->menu=='dates'? msg_site('urlpath-menu-date') : msg_site('urlpath-menu-live'))).'/'.htmlspecialchars($this->date).'/'.$k.'">'.htmlspecialchars($text).'</a>';
            $o[]='<div class="list_menu_item'.($this->list==$k?' sel':'').'">'.$link.'</div>';
        }
        return '<div class="list_menu">'.implode('',$o).'</div>';
    }
    function render_list()
    {
    }
    function render_list_mini()
    {
    }
    function extlink($title)
    {
        $title=mb_strlen($title)>$this->link_max_len ? mb_substr($title,0,$this->link_max_len-2).'..' : $title;
        return '<a href="'.$this->ext_link.$title.'">'.$title.'</a>';
    }
}

?>