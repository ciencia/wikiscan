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
require_once('include/toplist.php');
require_once('include/dates.php');
require_once('include/userstats.php');
require_once('include/graphlist.php');
require_once('include/grid_page.php');

class Site
{
    var $main_host='wikiscan.org';
    var $menus=array('home', 'live', 'grid', 'dates', 'userstats', 'ranges');//, 'userstats_ip'
    var $menu='home';
    var $menu_func;
    var $date=0;
    var $list='pages';
    var $filter='main';
    var $us;
    var $pv;
    var $wrong_params=false;
    var $host;

    function __construct()
    {
        if($this->menu!='')
            $this->menu_func='menu_'.$this->menu;
    }

    function init()
    {
        global $conf;
        $this->fr_multi_redirect();
        if($conf['multi'])
            $this->setup_multi();
        else{
            remove_values($this->menus, array('ranges'));
            $this->menu='home';
            $this->menu_func='menu_'.$this->menu;
        }
        $this->load_params();
    }

    function fr_multi_redirect()
    {
        //redirect old wikiscan.org frwiki links
        if(isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST']=='wikiscan.org' || $_SERVER['HTTP_HOST']=='wikiscan')){
            if($_SERVER['REQUEST_URI']=='/')
                return;
            if(isset($_GET['menu']) && ($_GET['menu']=='allsites' || $_GET['menu']=='about'))
                return;
            if(count($_GET)==1 && (isset($_GET['page']) || isset($_GET['purge'])))
                return;
            header('Status: 301 Moved Permanently', false, 301);
            $http=isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ? 'https' : 'http';
            header("Location: $http://fr.".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
            exit;
        }
    }

    function setup_multi()
    {
        global $conf;
        if($conf['wiki_key']!=''){
            remove_values($this->menus, array('ranges'));
        }else{
            $this->menus=array();
            $this->menu='allsites';
            if(isset($_GET['menu']) && $_GET['menu']=='about'){
                $_GET['menu']='allsites';
                $_GET['submenu']='about';
            }
        }
    }

    function load_params()
    {
        global $conf;
        if(isset($_SERVER['HTTP_HOST']))
            $this->host=$_SERVER['HTTP_HOST'];
        $this->https=isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on';
        if(isset($_SERVER['REQUEST_URI'])){
            $url=$_SERVER['REQUEST_URI'];
            $u=parse_url($url);
            if(isset($u['path'])){
                if(preg_match('!^/plages?-ip(?:/(.*))?!', $u['path'], $res)){
                    $_GET['menu']='ranges';
                    if(@$res[1]!='')
                        $_GET['range']=$res[1];
                }
            }
        }
        if(isset($_GET['menu'])){
            $this->menu='';
            $this->menu_func='';
            if($_GET['menu']!='' && (!is_array($conf['menus_enabled']) || in_array($_GET['menu'], $conf['menus_enabled']))){
                $menu=$_GET['menu'];
                $func='menu_'.$menu;
                if(method_exists($this,$func)){
                    $this->menu=$menu;
                    $this->menu_func=$func;
                }
            }
        }
        if(isset($_GET['date'])){
            $this->date='';
            if(($date=Dates::valid_date($_GET['date']))!==false)
                $this->date=$date;
        }else{
            switch($this->menu){
                case 'live':
                    $this->date=24;
                    break;
                case 'dates':
                    $this->date=$conf['base_calc']=='month' ? gmdate('Ym') : gmdate('Ymd');
                    break;
            }
        }
        if(isset($_GET['list'])){
            $this->list=$_GET['list'];
            // Sanity check
            if (!in_array($this->list, ['pages','users','stats']))
                $this->list='';
        }elseif($this->date==0 || strlen($this->date)==4)
            $this->list='stats';
        if(isset($_GET['filter'])){
            $this->filter=$_GET['filter'];
        }else{
            switch($this->list){
                case 'pages':
                    $this->filter='main';
                    break;
                case 'users':
                    $this->filter='user';
                    break;
            }
        }
        if($this->menu=='userstats' || $this->menu=='userstats_ip'){
            if(!is_object($this->us))
                $this->us=new UserStats($this->menu=='userstats_ip');
        }
    }

    function host_link()
    {
        return ($this->https ? 'https' : 'http').'://'.$this->host;
    }

    function show()
    {
        if($this->redirects())
            return;
        $o=$this->header();
        if($this->menu!='allsites')
            $o.=$this->site_banner();
        $o.='<div class="main">';
        if($this->menu=='allsites'){
            $o.=$this->menu_allsites();
            if(isset($_GET['submenu']) && $_GET['submenu']=='about')
                $o.=$this->menu_about();
        }else{
            $o.=$this->menu();
            $o.=$this->contents();
        }
        $o.='</div>';
        $o.=$this->footer();
        echo $o;
        if(DEBUG)
            echo $this->debug();
    }

    function msg_banner()
    {
        global $conf;
        if($conf['interface_language']=='fr')
            return "<div class=banner_msg>Maintenance du serveur en cours, les mises à jour des statistiques sont désactivées.</div>";
        return "<div class=banner_msg>Server maintenance in progress, statistics updates are disabled.</div>";
    }

    function preload_cache($date)
    {
        $tz=date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
        $_GET['purge']=1;
        $this->date=$date;
        if($date!='')
            $_GET['date']=$date;
        if(Dates::type($date)=='L')
            $this->menu='live';
        else
            $this->menu='dates';
        $this->view_toplist();
        $this->menu='grid';
        $this->menu_grid();
        date_default_timezone_set($tz);
    }

    function redirects()
    {
        if(isset($_SERVER['REDIRECT_URL']))
            return false;
        switch($this->menu){
            case 'userstats' :
                if(@$_GET['user']!='' && !isset($_GET['usort']) && !isset($_GET['sort']) && !isset($_GET['recalc'])){
                    $this->redirect(Userstats::user_url($_GET['user']));
                    return true;
                }
                if(@$_GET['user']!='' && !isset($_GET['usort']) && isset($_GET['sort'])){
                    //old links
                    $_GET['usort']=$_GET['sort'];
                    unset($_GET['sort']);
                    $this->redirect('?'.urlattr($_GET));
                    return true;
                }
                break;
            case 'dates' :
            case 'live' :
                if(@$_GET['date']!='' && !isset($_GET['sort']) && !isset($_GET['filter'])){
                    switch(@$_GET['list']){
                        case 'stats':
                        case 'statistiques':
                            $list='statistiques';
                            break;
                        case 'users':
                        case 'utilisateurs':
                            $list='utilisateurs';
                            break;
                        case 'pages':
                        default:
                            $list='pages';
                    }
                    $this->redirect(($this->menu=='dates'?'date':$this->menu).'/'.(int)$_GET['date']."/$list");
                    return true;
                }
                break;
            case 'ranges';
                if(isset($_GET['ip'])){
                    require_once('include/ranges.php');
                    $obj=new ranges();
                    if($page=$obj->redirect_ip($_GET['ip']))
                        $this->redirect($page);
                }
                break;
        }
        return false;
    }

    function redirect($page)
    {
        if(substr($page,0,1)=='/')
            $page=substr($page,1);
        header('Status: 301 Moved Permanently', false, 301);
        header('Location: '.$this->host_link().'/'.$page);
    }

    function header()
    {
        global $conf;
        $o='<!doctype html>
<html dir="ltr">
<head>
<title>';
        $o.=htmlspecialchars($this->head_title());
        $o.='</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<meta name="source" content="'.$this->main_host.'"/>
<meta name="viewport" content="initial-scale=1.0, user-scalable=yes" />
<base href="'. htmlspecialchars($this->host_link()) .'/">
<link rel="stylesheet" href="'. htmlspecialchars($this->host_link()) .'/wikiscan.css?ad"/>
'.(isset($conf['icon']) && $conf['icon']!='' ? '<link rel="icon" href="'.htmlspecialchars($conf['icon']).'" type="image/png"/>' : '');
        $o.='<script type="text/javascript" src="/libs/jquery-3.5.0.min.js"></script>';
        $o.='<script type="text/javascript" src="wikiscan.js?ad"></script>';

        $o.=$this->canonical();
        if($this->menu=='about' || ($this->menu=='ranges' && isset($_GET['whois']) && $_GET['whois'] !=''))
            $o.='<meta name="robots" content="noindex,nofollow">';
        else
            $o.='<meta name="robots" content="'.$conf['robots_policy'].'">';
        /*if(preg_match('!\.\w{2,}$!', $_SERVER['HTTP_HOST']))
            $o.=$this->analytics();*/
        $o.="</head><body>\n";
        $o.=$this->serveur_analytics();
        $this->remove_cookies();
        return $o;
    }

    function remove_cookies()
    {
        foreach($_COOKIE as $k => $v)
            setcookie($k, $v, time()-3600, '/');
    }

    function analytics_menus()
    {
        $menu=$this->menu;
        if($menu!='' && !preg_match('!^[\w\d _-]+$!i', $menu))
            $menu='';
        if($menu=='')
            $menu='(not set)';
        if(isset($_GET['submenu']))
            $submenu=$_GET['submenu'];
        elseif(isset($_GET['user']) && $_GET['user']!='')
            $submenu='user';
        elseif(isset($_GET['userlist']) && $_GET['userlist']!='')
            $submenu='userlist';
        elseif(isset($_GET['list']) && $_GET['list']!='')
            $submenu=$_GET['list'];
        elseif($menu=='pageview' && isset($_GET['year']) && $_GET['year']!='')
            $submenu=$_GET['year'];
        else
            $submenu='';
        if($submenu!='' && !preg_match('!^[\w\d _-]+$!i', $submenu))
            $submenu='';
        if($submenu=='')
            $submenu='(not set)';
        return [$menu, $submenu];
    }

    function analytics()
    {
        global $conf;
        if(!isset($conf['google_analytics']) || $conf['google_analytics']=='')
            return;
        $menus=$this->analytics_menus();
        $o="<script>(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,'script','//www.google-analytics.com/analytics.js','ga');";
        $o.="ga('create', '".$conf['google_analytics']."', 'auto');";
        $o.="ga('set', 'dimension1', '".$menus[0]."');";
        $o.="ga('set', 'dimension2', '".$menus[1]."');";
        $o.="ga('send', 'pageview');";
        $o.="</script>";
        return $o;
    }

    function serveur_analytics()
    {
        $include=true;
        $menus=$this->analytics_menus();
        $_GET['_menu']=$menus[0];
        $_GET['_submenu']=$menus[1];
        $_GET['_title']=$this->head_title();
        $_GET['_page']=(@$_SERVER['HTTPS']== 'on' ? 'https' : 'http')."://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $_GET['_referrer']=@$_SERVER['HTTP_REFERER'];
        include('analytics.php');
        return '<script type="text/javascript">var _menu="'.$menus[0].'";var _submenu="'.$menus[1].'";</script>';
    }

    function canonical()
    {
        if(($this->menu=='userstats'||$this->menu=='userstats_ip') && @$_GET['user']!='' && (@$_GET['usort']!='' || @$_GET['sort']!='')){
            if(!is_object($this->us))
                $this->us=new UserStats($this->menu=='userstats_ip');
            return $this->us->canonical($_GET['user']);
        }
        if($this->menu=='ranges'){
            if(isset($_GET['range']) && $_GET['range']!='' && (@$_GET['srt']!=''||@$_GET['page']!=''))
                return '<link rel="canonical" href="/'.msg_site('urlpath-menu-ranges').'/'.htmlspecialchars($_GET['range']).'"/>\n';
            if(isset($_GET['range']) && $_GET['range']!='' && @$_GET['whois']!=''){
                if(preg_match('!^([\d\.]+)/(\d+)$!',$_GET['whois'],$res))
                    return '<link rel="canonical" href="/'.msg_site('urlpath-menu-ranges').'/'.htmlspecialchars($_GET['whois'])."?whois=".htmlspecialchars($_GET['whois']).'"/>\n';
            }
            if(@$_GET['range']=='' && @$_GET['owner']!='' && @$_GET['srt']!='')
                return '<link rel="canonical" href="/'.msg_site('urlpath-menu-ranges').'/?owner='.htmlspecialchars($_GET['owner']).'"/>\n';
        }
    }
    function head_title()
    {
        global $conf;
        $o='';
        switch($this->menu){
            case 'home'  :
                $name=$conf['multi']?mb_ucfirst($conf['wiki']['site_global_key']):$conf['wiki']['site_host'];
                $o.=$name.' - '.msg('window_title-statitics').' - Wikiscan';
                break;
            case 'live'  :
            case 'dates' :
                if($this->menu==='live')
                    $o.=msg('window_title-live', $this->date.'h');
                else
                    if(@$_GET['menu']!='' && @$_GET['date']!='')
                        $o.=Dates::format($this->date);
                    else
                        $o.=msg('window_title-current_day');
                if($this->list=='stats')
                    $o.=" - ". msg("window_title-toplist-{$this->list}");
                else
                    $o.=" - ". msg("window_title-toplist-{$this->list}-{$this->filter}");
                $o.=' - Wikiscan';
                break;
            case 'grid' :
                $date= $this->date ? $this->date : 24;
                $o.=msg('window_title-grid')." {$date} h - Wikiscan";
                break;
            case 'userstats' :
                if(@$_GET['user']!=''){
                    if(!is_object($this->us))
                        $this->us=new UserStats($this->menu=='userstats_ip');
                    if($this->us->user_exist(UserStats::get_user_name()))
                        $o.=htmlspecialchars(mwtools::format_user($_GET['user']).' - '.msg('window_title-userstats').' - Wikiscan');
                    else
                        $o.=msg('window_title-userstats-user_not_found').' - Wikiscan';
                }else
                    $o.=msg('window_title-userstats-all').' - Wikiscan';
                break;
            case 'userstats_ip' :
                if(@$_GET['user']!=''){
                    if(!is_object($this->us))
                        $this->us=new UserStats($this->menu=='userstats_ip');
                    if($this->us->user_exist(UserStats::get_user_name()))
                        $o.=htmlspecialchars(mwtools::format_user($_GET['user']).' - '.msg('window_title-userstats-ip').' - Wikiscan');
                    else
                        $o.=msg('window_title-userstats-ip_not_found').' - Wikiscan';
                }else
                    $o.=msg('window_title-userstats-ip-all').' - Wikiscan';
                break;
            case 'pageview' :
                if(!is_object($this->pv))
                    $this->pv=new pageview();
                $o.=$this->pv->get_title().' - Wikiscan';
                break;
            case 'ranges' :
                require_once('include/ranges.php');
                $o.=ranges::get_title().' - Wikiscan';
                break;
            case 'allsites' :
                $submenu=isset($_GET['submenu']) ? $_GET['submenu'] : 'sites';
                if($submenu=='about')
                    $o.=msg("window_title-about");
                else
                    $o.=msg("window_title-allsites-$submenu");
                break;
            case 'about' :
                $o.=msg("window_title-about");
                break;
        }
        return $o;
    }
    
    function site_banner()
    {
        global $conf;
        $o='<div class="site_banner">';
        if(isset($conf['logo']) && $conf['logo']!='')
            $o.='<div class="logo"><a href="'.htmlspecialchars($this->host_link()).'"><img src="'.htmlspecialchars($conf['logo']).'" alt="'.htmlspecialchars(msg('logo-alt')).'"/></a></div>';
        if(isset($conf['wiki']['url']) && $conf['wiki']['url']!=''){
            $link='<a href="'.htmlspecialchars($conf['wiki']['url']).'">'.htmlspecialchars($conf['wiki']['site_host']).'</a>';
            $o.='<h1>'.str_replace('$1', $link, htmlspecialchars(msg('banner_text'))).'</h1>';
        }
        $o.='</div>';
        return $o;
    }
    
    function menu()
    {
        global $conf;
        if(empty($this->menus))
            return;
        $o='<div class="menu">';
        if($conf['multi'])
            $o.="<div class='menu_item'><a href='//{$this->main_host}'>".htmlspecialchars(msg("menu-wikis"))."</a></div>";
        foreach($this->menus as $k){
            if(!is_array($conf['menus_enabled'])||in_array($k,$conf['menus_enabled']))
                $o.="<div class='menu_item".($k==$this->menu?' sel':'')."'><a href='/".htmlspecialchars(msg_site("urlpath-menu-$k"))."'>".htmlspecialchars(msg("menu-$k"))."</a></div>";
        }
        $o.="</div>\n";
        return $o;
    }
    function contents()
    {
        $o='<div class="contents">';
        if($this->menu!='' && $this->menu_func!=''){
            $o.=call_user_func(array($this,$this->menu_func));
        }else
            return $this->wrong_params('menu');
        return $o.'</div>';
    }

    function menu_about()
    {
        global $conf;
        $o="<div class=about>";
        $file=$conf['root_path'].'/include/languages/about.'.$conf['interface_language'].'.html';
        if(file_exists($file))
            $o.=file_get_contents($file);
        $o.="</div>";
        return $o;
    }

    function menu_home()
    {
        require_once('include/wiki_home.php');
        $home=new WikiHome();
        return $home->view();
    }

    function menu_grid()
    {
        if((int)ini_get('memory_limit')<'800')
            ini_set('memory_limit', '800M');
        $grid=new GridPage();
        return $grid->view($this->date);
    }

    function view_mini_toplist($list=false,$date=false,$filter=false,$sort=false)
    {
        if($toplist=TopList::create($list,$date,$filter,$sort,true))
            return $toplist->view();
        return false;
    }

    function menu_live()
    {
        ini_set('memory_limit', '100M');
        if(Dates::type($this->date)!='L')
            return $this->wrong_params('live/date');
        $oo=$this->view_toplist($this->list);
        if($this->wrong_params)
            return $oo;
        $o='<table class="list_mep" border="0" cellspacing="0"><tr><td>';
        $o.="<div class=date_title><h1>".msg('toplist-live-title')."</h1></div>";
        $o.=Dates::menu_live($this->date, $this->list);
        $o.=$oo;
        $o.='</td><td>';
        if($this->toplist && $this->toplist->loaded())
            $o.=GraphList::toplist($this->date);
        $o.='</td></tr></table>';
        return $o;
    }

    function menu_dates()
    {
        ini_set('memory_limit', '130M');
        if(Dates::type($this->date)=='L')
            return $this->wrong_params('dates/live');
        $oo=$this->view_toplist();
        if($this->wrong_params)
            return $oo;
        $o='<table class="list_mep" border="0" cellspacing="0"><tr><td>';
        $this->dates=new Dates();
        $this->dates->load();
        $o.=$this->dates->menu($this->date, $this->list);
        $o.=$oo;
        $o.='</td><td>';
        if($this->toplist && $this->toplist->loaded())
            $o.=GraphList::toplist($this->date);
        $o.='</td></tr></table>';
        return $o;
    }

    function view_toplist($list=false)
    {
        $this->toplist=false;
        if($list===false)
            $list=$this->list;
        ini_set('memory_limit', '4000M');
        ini_set('max_execution_time','120');
        $this->toplist=TopList::create($list,$this->date,$this->filter);
        if($this->toplist!==false){
            if($this->toplist->load_params())
                $o=$this->toplist->view();
            else
                return $this->wrong_params('toplist');
        }else
            return $this->wrong_params('toplist');
        if($o=='')
            $o='Erreur View';
        return $o;
    }

    function menu_userstats()
    {
        ini_set('memory_limit', '128M');
        if(!is_object($this->us))
            $this->us=new UserStats();
        return $this->us->view();
    }

    function menu_userstats_ip()
    {
        if(!is_object($this->us)||!$this->us->ip)
            $this->us=new UserStats(true);
        return $this->us->view();
    }

    function menu_pageview()
    {
        require_once('include/pageview.php');
        if(!is_object($this->pv))
            $this->pv=new pageview();
        return $this->pv->view_top();
    }

    function menu_ranges()
    {
        require_once('include/ranges.php');
        $obj=new ranges();
        return $obj->view();
    }

    function menu_active_users()
    {
        ini_set('memory_limit', '100M');
        require_once('include/active_users.php');
        $obj=new active_users();
        return $obj->view();
    }

    function menu_alldates()
    {
        ini_set('memory_limit', '130M');
        $o='<div>';
        $this->dates=new Dates();
        $this->dates->load(true);
        $o.=$this->dates->view($this->date);
        $o.='</div>';
        return $o;
    }

    function menu_allsites()
    {
        ini_set('memory_limit', '128M');
        require_once('include/wikis.php');
        $wikis=new Wikis();
        $total=$wikis->get_total_stats();
        $o='<div class=allsites_menu><div class=allsites_menu_item><a href="/?menu=allsites">'.msg("menu-allsites-sites").'</a></div>';
        foreach(array('status','workers'/*,tables,'doc'*/) as $sub)
            $o.='<div class=allsites_menu_item><a href="/?menu=allsites&submenu='.$sub.'">'.msg("menu-allsites-$sub").'</a></div>';
        $o.="<div class='main_total_stats'>";
        $o.="<span class='main_total_stat'>".number_format($total['sites'],0,',',' ')." ".msg('allsites-subtitle-sites')."</span>";
        $o.="<span class='main_total_stat'>".number_format(round($total['total_rev']/1000000000,1),1,',',' ')." ".msg('allsites-subtitle-billions_edits')."</span>";
        $o.="<span class='main_total_stat'>".number_format(round(($total['total_page']-$total['total_redirect'])/1000000),0,',',' ')." ".msg('allsites-subtitle-millions_pages')."</span>";
        $o.="</div>";
        $o.='</div>';
        $o.='<div>';
        $o.=$wikis->view();
        $o.='</div>';
        return $o;
    }

    function menu_graphs()
    {
        $obj=new GraphList();
        return $obj->months();
    }

    function menu_tables()
    {
        require_once('include/stats_tables.php');
        $tables=new StatsTables();
        $date=isset($GET['date']) ? $GET['date'] : 0;
        return $tables->view($date);
    }

    function menu_pages_reverts()
    {
        $o='<script type="text/javascript" src="/libs/jquery.tablesorter.min.js"></script>
            <script type="text/javascript">
            $(document).ready(function()
            {
                $(".sortable").tablesorter();
            });</script>';
        $o.=UpdateStats::read_export_pages_rv();
        return $o;
    }

    function footer()
    {
        global $conf;
        $o="<div class='footer'>";
        if($this->menu!='about' && (!isset($_GET['submenu']) || $_GET['submenu']!='about')){
            if($conf['view_about_link'])
                $o.="<a href='/?menu=about'>".htmlspecialchars(msg('footer-about_link'))."</a> - ";
            $lic='<a href="'.htmlspecialchars($conf['license_statistics']['url']).'" rel="nofollow">'.htmlspecialchars($conf['license_statistics']['license']).'</a>';
            $o.=str_replace('$1',$lic,htmlspecialchars(msg('footer-license'))).".";
        }
        $o.="</div>";
        $o.="</body>\n</html>";
        return $o;
    }

    function generate_htaccess()
    {
        if(!file_exists('include/htaccess_template.txt')){
            echo "Error template not found\n";
            return false;
        }
        $data=file_get_contents('include/htaccess_template.txt');
        $Language=new Language();
        $langs=$Language->list_langs();
        foreach($langs as $lang){
            echo "$lang\n";
            $Language->load_messages($lang);
            foreach($Language->list_messages('urlpath-') as $k=>$v)
                $messages[$k][]=$v;
        }
        foreach($messages as $k=>$values){
            $values=array_unique($values);
            if(count($values)>=2)
                $data=str_replace('{'.$k.'}', "(?:".implode('|', $values).")", $data);
            else
                $data=str_replace('{'.$k.'}', $values[0], $data);
        }
        echo "\n$data\n";
        file_put_contents('.htaccess', $data);
    }

    function wrong_params($type='')
    {
        $this->wrong_params=true;
        header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
        header("Status: 404 Not Found");
        trigger_error("wrong params ".$type.' '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REMOTE_ADDR'].' "'.$_SERVER['REQUEST_URI'].'" "'.@$_SERVER["HTTP_USER_AGENT"].'"');
        return '<div class="error"><b>Error</b>: wrong parameters.</div>';
    }
    function debug()
    {
        global $Debug;
        return $Debug->view();
    }

}

?>