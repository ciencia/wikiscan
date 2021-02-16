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
require_once('include/toplist_page.php');

class GridPage extends site_page
{
    var $cache=true;

    static function title($date)
    {
        global $conf, $Site;
        $row=Dates::get($date);
        $last_update=@$row['last_update'];
        $o='<div class="date_title_grid main_title">';
        $o.='<div class="last_update">';
        $o.= $last_update!='' ? format_date($last_update, true) : '';
        $o.='</div>';
        $o.='<h1>'.msg('toplist-live-title').'</h1>';
        $o.='</div>';
        return $o;
    }
    function view($date=24)
    {
        global $conf;
        if($date==0)
            $date=24;
        $this->date=$date;
        if(!isset($_GET['purge'])||!$_GET['purge'])
            if($out=$this->get_cache())
                return $out;
        $o='<div class="grid">';
        $o.=$this->title($date);
        $o.=TopListPage::javascript_mini();
        $o.='<table class=mep><tr><td>';
        $o.='<div class="grid_item"><h3><a href="/live/'.$date.'/pages/">'.msg('grid-title-articles').'</a></h3>';
        if($toplist=TopList::create('pages', $date, 'main', 'weight', true))
            $o.=$toplist->view();
        $o.='</div>';
        $o.='<div class="grid_item"><h3>'.msg('grid-title-newarticles').'</h3>';
        if($toplist=TopList::create('pages', $date, 'main', 'weight', true)){
            $toplist->new_only=true;
            $o.=$toplist->view();
        }
        $o.='</div>';
        $o.='<div class="grid_item"><h3><a href="/?menu=live&filter=meta&sort=hits&date='.$date.'&list=pages">'.msg('grid-title-meta').'</a></h3>';
        if($toplist=TopList::create('pages', $date, 'meta', 'weight', true)){
            $toplist->pages_filter='admin';
            $toplist->pages_filter_invert=true;
            $o.=$toplist->view();
        }
        $o.='</div>';
        if(isset($conf['page_filters']['admin'])){
            $o.='<div class="grid_item"><h3>'.msg('grid-title-adminpages').'</h3>';
            if($toplist=TopList::create('pages', $date, 'meta', 'weight', true)){
                $toplist->pages_filter='admin';
                $o.=$toplist->view();
            }
            $o.='</div>';
        }
        $o.='</td><td>';
        $o.='<div class="grid_item"><h3><a href="/?menu=live&filter=main&sort=hits&date='.$date.'&list=pages">'.msg('grid-title-views').'</a></h3>';
        if($toplist=TopList::create('pages', $date, 'main', 'hits', true))
            $o.=$toplist->view();
        $o.='</div>';
        $o.='<div class="grid_item"><h3><a href="/?menu=live&filter=talk&sort=weight&date='.$date.'&list=pages">'.msg('grid-title-talks').'</a></h3>';
        if($toplist=TopList::create('pages', $date, 'talk', 'weight', true)){
            $toplist->view_ns=false;
            $toplist->pages_filter='deletion';
            $toplist->pages_filter_invert=true;
            $o.=$toplist->view();
        }
        $o.='</div>';
        if(isset($conf['page_filters']['admin'])){
            $o.='<div class="grid_item"><h3>'.msg('grid-title-deletion').'</h3>';
            if($toplist=TopList::create('pages', $date, 'all', 'weight', true)){
                $toplist->view_ns=false;
                $toplist->pages_filter='deletion';
                $o.=$toplist->view();
            }
            $o.='</div>';
        }
        $o.='<div class="grid_item"><h3><a href="/?menu=live&filter=model&sort=weight&date='.$date.'&list=pages">'.msg('grid-title-templates').'</a></h3>';
        if($toplist=TopList::create('pages', $date, 'model', 'weight', true)){
            $o.=$toplist->view();
        }
        $o.='</div>';
        $o.='<div class="grid_item"><h3><a href="/?menu=live&filter=other&sort=weight&date='.$date.'&list=pages">'.msg('grid-title-others').'</a></h3>';
        if($toplist=TopList::create('pages', $date, 'other', 'weight', true)){
            $o.=$toplist->view();
        }
        $o.='</div>';
        $o.='</td><td>';
        $o.='<div class="grid_item"><h3>'.msg('grid-title-graph_edits').'</h3>';
        $o.="<a href='/gimg.php?type=edits&date=$date&size=big'><img src='/gimg.php?type=edits&date=$date&size=small' alt='Graphique éditions'/></a>";
        $o.='</div>';
        $o.="<div class='grid_item'><h3>".msg('grid-title-graph_users')."</h3>";
        $o.="<a href='/gimg.php?type=users&date=$date&size=big'><img src='/gimg.php?type=users&date=$date&size=small' alt='Graphique éditions'/></a>";
        $o.='</div>';
        $o.='<div class="grid_item"><h3><a href="/live/'.$date.'/utilisateurs">'.msg('stat-users').'</a></h3>';
        if($toplist=TopList::create('users', $date, 'user', 'weight', true))
            $o.=$toplist->view();
        $o.='</div>';
        $o.='<div class="grid_item"><h3><a href="/?menu=live&filter=ip&sort=weight&date='.$date.'&list=users">'.msg('stat-ip').'</a></h3>';
        if($toplist=TopList::create('users', $date, 'ip', 'weight', true))
            $o.=$toplist->view();
        $o.='</div>';
        $o.='<div class="grid_item"><h3><a href="/?menu=live&filter=bot&sort=weight&date='.$date.'&list=users">'.msg('stat-bots').'</a></h3>';
        if($toplist=TopList::create('users', $date, 'bot', 'weight', true))
            $o.=$toplist->view();
        $o.='</div>';
        $o.='</td></tr></table></div>';
        $this->set_cache($o);
        return $o;
    }
    function valid_cache_date($cache_date)
    {
        $row=Dates::get($this->date);
        if(!isset($row['last_update']))
            return false;
        return $cache_date>$row['last_update'] || strtotime(gmdate('YmdHis'))-strtotime($row['last_update'])<=30;
    }
    function cache_key()
    {
        return 'grid:'.$this->date;
    }
}
?>