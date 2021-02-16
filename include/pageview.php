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
require_once('include/site_page.php');
require_once('include/mw/mwtools.php');

class pageview extends site_page
{
    var $project='fr ';
    var $retry_other_path=true;
    var $hour_adjust=0;
    var $table_hours='pageview_hours';
    var $insert_select=false;
    var $insert_size=500;
    var $max_hours_keep='-2 days';
    var $file='';
    var $dir='/var/www/wikiscan/pagesview';
    var $graph_path='img/pageview/';
    var $graph_reset=false;
    var $cache=true;
    var $cache_expire=691200;//8 days
    var $year=0;
    var $ns='';
    var $limit=200;
    var $max_pages=5;
    var $download_url="http://dumps.wikimedia.org/other/pagecounts-raw";
    var $ignore_title='__OTHER__';
    var $remove_old_delete_size=50000;

    function __construct()
    {
        $this->path=getcwd().'/ctrl';
    }

    function init()
    {
    }
    function cache_key($year=false,$page=false)
    {
        return 'pageview:'.($year!==false?$year:$this->year).':'.($page!==false?$page:$this->page).':'.$this->limit;
    }
    function valid_cache_date($cache_date)
    {
        return true;
    }
    function view_top()
    {
        if(isset($_GET['year']))
            $this->year=(int)$_GET['year'];
        $this->page=isset($_GET['page'])?(int)$_GET['page']:1;
        if($this->page<1)
            $this->page=1;
        if($this->page>$this->max_pages)
            $this->page=$this->max_pages;
        if(isset($_GET['limit']))
            $this->limit=(int)$_GET['limit'];
        if(isset($_GET['ns']))
            $this->ns=$_GET['ns'];
        if(!isset($_GET['purge'])||!$_GET['purge'])
            if($r=$this->get_cache())
                return $r;
        $o='<div class="pageview">';
        if(empty($this->years))
            $this->get_years();
        if($this->year!==0)
            $o.="<h1>Pages les plus affichées sur Wikipédia en ".$this->year."</h1>";
        else{
            $min=min($this->years);
            $max=max($this->years);
            $o.="<h1>Pages les plus affichées sur Wikipédia pour la période $min".($min!=$max?"-$max":"")."</h1>";
        }
        $o.='<table class="menu_pageview"><tr>';
        $o.="<td><a href='/pages_vues/0/{$this->ns}'>".(0==$this->year?"<b>Tout</b>":"Tout")."</a></td>";
        foreach($this->years as $v)
            $o.="<td><a href='/pages_vues/$v/{$this->ns}'>".($v==$this->year?"<b>$v</b>":$v)."</a></td>";
        $o.='</tr></table>';
        $o.=$this->top_year();
        $o.='</div>';
        if($this->cache)
            $this->set_cache($o);
        return $o;
    }
    function get_years()
    {
        $dbs=get_dbs();
        $rows=$dbs->select("select distinct date from pageview_years where date!=0 order by date");
        $this->years=array();
        foreach($rows as $v)
            $this->years[]=$v['date'];
        return $this->years;
    }
    function get_title()
    {
        $year=isset($_GET['year'])?(int)$_GET['year']:0;
        if($year!==0)
            return "Pages vues sur Wikipedia en $year";
        if(empty($this->years))
            $this->get_years();
        if(empty($this->years))
            return "Pages vues";
        $min=$this->years[0];
        $max=$this->years[count($this->years)-1];
        return "Pages vues sur Wikipedia en $min".($min!=$max?"-$max":"");
    }
    function get_description()
    {
        $year=isset($_GET['year'])?(int)$_GET['year']:0;
        if($year!==0)
            return "Pages les plus affichées sur Wikipedia pour l'année $year";
        if(empty($this->years))
            $this->get_years();
        if(empty($this->years))
            return "Pages les plus affichées sur Wikipedia";
        $min=$this->years[0];
        $max=$this->years[count($this->years)-1];
        return "Pages les plus affichées sur Wikipedia pour la période $min".($min!=$max?"-$max":"");
    }
    function top_year()
    {
        $year=$this->year;
        $dbs=get_dbs();
        $start=($this->page-1)*$this->limit;
        $q="select * from pageview_years where date=$year and months>=2";
        if($this->ns!='')
            $q.=" and title like '".$dbs->escape($this->ns).":%'";
        $q.=" order by hits desc limit $start,".$this->limit;
        $rows=$dbs->select($q);
        $o="<div class='top_year'>";
        if(empty($rows)){
            $o.='<div class="error">Pas de donnée.</div>';
            return $o.'</div>';
        }
        $i=($this->page-1)*$this->limit+1;
        $o.='<table>';
        if($this->page!=1 || count($rows)==$this->limit)
            $o.='<tr><td class="pages" colspan="4">'.$this->pages_links().'</td></tr>';
        $o.='<tr class="header"><td>Position</td><td>Pages vues</td><td>Mois</td><td style="text-align:left">Titre de la page</td></tr>';
        foreach($rows as $v){
            $title=mwtools::rtitle($v['title']);
            $graph=$this->months_svg_image($v['title'],$year);
            if($v['hits']>10000)
                $hits=number_format($v['hits']/1000,0,',',' ').' k';
            else
                $hits=number_format($v['hits'],0,',',' ');
            $o.='<tr><td class="pos">'.$i++."</td><td class='num'>".str_replace(' ',' ',$hits)."</td>";
            $o.="<td class='graph'>";
            $o.=$graph;
            $o.="</td><td><a href='https://fr.wikipedia.org/wiki/".urlencode(str_replace(' ','_',$title))."'>$title</a></td></tr>";
        }
        if($this->page!=1 || count($rows)==$this->limit)
            $o.='<tr><td class="pages" colspan="4">'.$this->pages_links().'</td></tr>';
        $o.='</table><br><p>Nombre brut de requêtes sur le titre exact après /wiki/ (hors HTTPS).<br>Pages ayant au moins 10 affichages dans une journée, sur 2 mois différents.<br>Les redirections et les renommages ne sont pas pris en compte.</p></div>';
        return $o;
    }
    function pages_links()
    {
        $o="<span class='pageview_pages'>";
        if($this->page>1)
            $o.="<span class='page_prev'>".lnk("Précédent",array('page'=>$this->page-1),array('menu','year','ns')).'</span>';
        for($p=1;$p<=$this->max_pages;$p++){
            $o.="<span class='page'>".($p==$this->page?"<b> {$p} </b>":lnk(" {$p} ",array('page'=>$p),array('menu','year','ns'))).'</span>';
        }
        if($this->page<$this->max_pages)
            $o.="<span class='page_next'>".lnk("Suivant",array('page'=>$this->page+1),array('menu','year','ns')).'</span>';
        $o.='</span>';
        return $o;
    }
    function graph_data($page,$year)
    {
        $dbs=get_dbs();
        $title=$dbs->escape(mwtools::wtitle($page));
        $rows=$dbs->select("select * from pageview_months where ".($year!=0?"date like '$year%' and":"")." title='$title' order by date");
        $dates=index($rows,'date');
        if($year!=0){
            for($m=1;$m<=12;$m++){
                if($m<10)
                    $m="0$m";
                $date="$year$m";
                $hits[$date]=isset($dates[$date])?$dates[$date]['hits']:0;
            }
        }else{
            foreach($this->years as $y)
                for($m=1;$m<=12;$m++){
                    if($m<10)
                        $m="0$m";
                    $date="$y$m";
                    $hits[$date]=isset($dates[$date])?$dates[$date]['hits']:0;
                }
        }
        return $hits;
    }
    function months_svg_image($page,$year)
    {
        $height=16;
        $w=2;
        $months=$year!=0?12:count($this->years)*12;
        $width=$w*$months+1;
        $o="<svg class='pageview_months_graph' width='$width' height='$height'>";
        $hits=$this->graph_data($page,$year);
        $max=max($hits);
        if($max!=0){
            $o.="<path class='months_path' d='M0,$height";
            $x=0;
            foreach($hits as $k=>$v){
                $top=round($v*$height/$max);
                $y=$height-$top;
                $o.=" $x,$y";
                $x+=$w;
            }
            $x-=$w;
            $o.=" $x,$height Z'></path>\n";
        }
        $o.="</svg>";
        return $o;
    }
}

?>