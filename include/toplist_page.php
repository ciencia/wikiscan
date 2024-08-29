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

class TopListPage extends TopList
{
    var $link_max_len=75;
    var $reduce=true;
    var $reduce_limit=2000;
    var $filter='all';
    var $sort='weight';
    var $view_ns='full'; //full, abbr, talk_only
    var $mini_size=10;
    var $mini_expand_size=30;
    var $pages_filter='';
    var $pages_filter_invert=false;
    var $ignore_title='__OTHER__';
    var $graphs;

    function __construct($date=false, $filter=false, $sort=false, $mini=false)
    {
        parent::__construct($date, $filter, $sort, $mini);
        $this->data_name='pages';
        $this->list='pages';
        $this->filters=array('all', 'main', 'talk', 'meta', 'model', 'user', 'file', 'other');
        $this->sorts=array(
            'weight'=>array('weight','nb_users'),
            'hits'=>array('hits','weight'),
            'users'=>array('utot','weight'),
            'edit'=>array('edit','weight'),
            'revert'=>array('revert','weight'),
            'diff'=>array('diff_abs','weight'),
            'diff_tot'=>array('diff_tot','weight'),
            'size'=>array('size','weight'),
            );
        $this->sort_cols=array('hits', 'users', 'edit', 'revert', 'diff', 'diff_tot', 'size', 'title');
        $this->sort_images=array(
            'users'=>'imgi/user-icon.png',
            'edit'=>'imgi/edit.png',
            'revert'=>'imgi/revert.png',
            );
        global $conf;
        if(DEBUG)
            $this->sort_cols=array_merge(array('weight','redit'), $this->sort_cols);
        $this->graphs=array('edits');
    }
    function cache_key()
    {
        return parent::cache_key().':'.$this->pages_filter.':'.$this->pages_filter_invert;
    }
    function filter()
    {
        global $conf;
        if(empty($this->data)||$this->filter=='')
            return false;
        $used=array_flip(array(NS_MAIN,NS_TALK,NS_CATEGORY,NS_CATEGORY_TALK,NS_USER,NS_USER_TALK,
                        NS_PROJECT,NS_PROJECT_TALK,NS_TEMPLATE,NS_TEMPLATE_TALK,NS_FILE,NS_FILE_TALK));
        foreach(array_keys($this->data) as $k){
            $title=isset($this->data[$k]['title']) ? $this->data[$k]['title'] : $k;
            if($title==$this->ignore_title || $title=='index.html'){
                unset($this->data[$k]);
                continue;
            }
            $ns=@$this->data[$k]['ns'];
            if(isset($conf['hide_title']))
                foreach($conf['hide_title'] as $match)
                    if(strpos($title, $match)!==false)
                        unset($this->data[$k]);
            if($this->filter=='all'
            || $this->filter=='main' && ($ns==NS_MAIN || $ns==NS_CATEGORY)
            || $this->filter=='talk' && ($ns==NS_TALK || $ns==NS_CATEGORY_TALK)
            || $this->filter=='meta' && ($ns==NS_PROJECT || $ns==NS_PROJECT_TALK)
            || $this->filter=='user' && ($ns==NS_USER || $ns==NS_USER_TALK)
            || $this->filter=='model'&& ($ns==NS_TEMPLATE || $ns==NS_TEMPLATE_TALK)
            || $this->filter=='file' && ($ns==NS_FILE || $ns==NS_FILE_TALK)
            || $this->filter=='other' && !isset($used[$ns]) )
                continue;
            unset($this->data[$k]);
        }
        $this->filter_pages();
        return true;
    }
    function filter_new()
    {
        foreach(array_keys($this->data) as $k){
            if(!@$this->data[$k]['new'])
                unset($this->data[$k]);
        }
        return true;
    }
    function filter_pages()
    {
        global $conf;
        if(empty($this->data)||$this->pages_filter=='')
            return false;
        $filter=isset($conf['page_filters'][$this->pages_filter]) ? $conf['page_filters'][$this->pages_filter] : '';
        if($filter==''){
            if(!$this->pages_filter_invert)
                $this->data=array();
            return false;
        }
        foreach($this->data as $k=>$v){
            if(!isset($v['title'])){
                $fulltitle=mwTools::rtitle($k);
            }else{
                //old format
                $fulltitle=mwns::get()->ns_title(mwTools::rtitle($v['title']), $v['ns']);
            }
            if(!$this->pages_filter_invert){
                if(!preg_match("!$filter!", $fulltitle))
                    unset($this->data[$k]);
            }else{
                if(preg_match("!$filter!", $fulltitle))
                    unset($this->data[$k]);
            }
        }
        return true;
    }
    function reduce()
    {
        $count=count($this->data);
        if($count<=5000){
            $min_weight=2;
            $min_hits=100;
        }elseif($count<=10000){
            $min_weight=10;
            $min_hits=300;
        }elseif($count<=30000){
            $min_weight=15;
            $min_hits=700;
        }elseif($count<=60000){
            $min_weight=20;
            $min_hits=1000;
        }else{
            $min_weight=30;
            $min_hits=3000;
        }
        foreach(array_keys($this->data) as $k){
            if(@$this->data[$k]['weight']<$min_weight && @$this->data[$k]['hits']<=$min_hits)
                unset($this->data[$k]);
                continue;
        }
    }
    /**
     * Generates a plain comma separated list of users (contributors), with the number of edits inside parentheses.
     * 
     * @param array $v Associative array of user statistics
     * @return string Content that's not HTML-safe
     */
    function user_list($v)
    {
        $ou=array();
        $users=array();
        foreach(array('user','bot','ip') as $type)
            if(!empty($v['list_'.$type]))
                $users=array_merge($users,$v['list_'.$type]);
        arsort($users);
        $j=0;
        foreach($users as $user=>$edits){
            $ou[]="$user ($edits)";
            if(++$j==15){
                $ou[]='…';
                break;
            }
        }
        return implode(', ',$ou);
    }
    function icon($col,$value,$max_value,$max_width=15,$min_width=7)
    {
        if($value==0)
            return '';
        if($value>$max_value)
            $value=$max_value;
        $width=round(($max_width*$value/$max_value));
        if($width<$min_width)
            $width=$min_width;
        return "<img src='imgi/".$this->icons[$col]."' width='$width' title='$value' alt='$value'/>";
    }

    function render_list()
    {
        $o='<table class="list_list" cellspacing="0">';
        $o.=$this->view_filters();
        $hits=false;
        $max=$this->list_size*1.5;
        $i=0;
        foreach($this->data as $v)
            if(@$v['hits']>0){
                $hits=true;
                break;
            }elseif(++$i>=$max)
                break;
        if(!$hits)
            remove_value($this->sort_cols, 'hits');
        $o.=$this->view_sorts();
        reset($this->data);
        for($i=0;$i<$this->list_size;$i++){
            $k=key($this->data);
            if($k===null)
                break;
            $v=current($this->data);
            next($this->data);
            if(!isset($v['title']))
                $fulltitle=mwTools::rtitle($k);
            else//old format
                $fulltitle=mwns::get()->ns_title(mwTools::rtitle($v['title']), $v['ns']);
            if($fulltitle=='')
                continue;
            $o.='<tr>';
            $ulist=$this->user_list($v);
            if(DEBUG){
                $o.='<td>'.@$v['weight'].'</td>';
                $o.='<td>'.@$v['redit'].'</td>';
            }
            if($hits)
                $o.='<td>'.format_size(@$v['hits']).'</td>';
            $o.='<td><span title="'.htmlspecialchars($ulist).'">'.@$v['utot'].'</span></td>';
            $o.='<td>'.(int)@$v['edit'].'</td>';
            $o.='<td>'.@$v['revert'].'</td>';
            $o.='<td>'.format_diff(@$v['diff']).'</td>';
            $o.='<td>'.format_sizei(@$v['diff_tot']).'</td>';
            $o.='<td>'.format_sizei(@$v['size']).'</td>';
            $o.='<td class="name">'.$this->extlink($fulltitle,$fulltitle,$ulist).'</td>';
            $o.'</tr>';
        }
        $o.='</table>';
        unset($this->data);
        return $o;
    }
    function render_list_mini()
    {
        $o='<table class="mini_list" cellspacing="0">';
        $hits=false;
        $max=$this->mini_size*1.5;
        $i=0;
        foreach($this->data as $v)
            if(@$v['hits']>0){
                $hits=true;
                break;
            }elseif(++$i>=$max)
                break;
        if(!$hits)
            remove_value($this->sort_cols, 'hits');
        remove_values($this->sort_cols, array('diff_tot', 'size'));
        $o.=$this->view_sorts(false);
        reset($this->data);
        for($i=0;$i<$this->mini_expand_size;$i++){
            $k=key($this->data);
            if($k===null)
                break;
            $v=current($this->data);
            next($this->data);
        if(!isset($v['title']))
                $fulltitle=mwTools::rtitle($k);
            else//old format
                $fulltitle=mwns::get()->ns_title(mwTools::rtitle($v['title']), $v['ns']);
            if($fulltitle=='')
                continue;
            $title = $this->view_ns===false ? mwns::get()->remove_ns($fulltitle) : $fulltitle;
            if($i+1==$this->mini_size+1){
                $o.="<tr class='mini_expand' style='display:table-row'><td class='mini_expand_link' colspan=10><a href='#' onclick='return tmin(this);'><img src='/imgi/icons/expand.png'/><img src='/imgi/icons/expand.png'/></a></td></tr>";
                $o.="<tr class='mini_expand'><td class='mini_expand_link' colspan=10><a href='#' onclick='return tmin(this);'><img src='/imgi/icons/expand_up.png'/><img src='/imgi/icons/expand_up.png'/></a></td></tr>";
            }
            if($i+1>$this->mini_size)
                $o.="<tr class='mini_expand'>";
            else
                $o.='<tr>';
            $ulist=$this->user_list($v);
            if(DEBUG){
                $o.='<td>'.@$v['weight'].'</td>';
                $o.='<td>'.@$v['redit'].'</td>';
            }
            if($hits)
                $o.='<td>'.format_size(@$v['hits']).'</td>';
            $o.='<td><span title="'.htmlspecialchars($ulist).'">'.@$v['utot'].'</span></td>';
            $o.='<td>'.(int)@$v['edit'].'</td>';
            $o.='<td>'.@$v['revert'].'</td>';
            $o.='<td>'.format_diff(@$v['diff']).'</td>';
            $o.='<td class="name">'.$this->extlink($title, $fulltitle, $ulist).'</td>';
            $o.'</tr>';
        }
        $o.='</table>';
        unset($this->data);
        return $o;
    }
    function extlink($title,$fulltitle='',$tooltip='')
    {
        global $conf;
        if($fulltitle=='')
            $fulltitle=$title;
        if(mb_strlen($title)>$this->link_max_len){
            $tooltip=$title.' : '.$tooltip;
            $title=truncate($title,$this->link_max_len);
        }
        return '<a href="'.htmlspecialchars($conf['link_page'].mwtools::title_url($fulltitle)).'"'.($tooltip!=''?' title="'.htmlspecialchars($tooltip).'"':'').'>'.htmlspecialchars($title).'</a>';
    }
    static function javascript_mini()
    {
        return preg_replace("/\s+/",' ','<script type="text/javascript">
        function tmin(link)
        {
            var td=link.offsetParent;
            var table=td.offsetParent;
            for (var i = 0, row; row = table.rows[i]; i++) {
                if(row.className=="mini_expand"){
                    if(row.style.display=="")
                        row.style.display="table-row";
                    else
                        row.style.display="";
                }
            }
            return false;
        }
        </script>');
    }
}
?>
