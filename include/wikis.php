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

class Wikis extends site_page
{
    var $site=false;
    static $base_host='wikiscan.org';
    var $stats;
    var $page;
    var $limit=60;
    var $limit_status=100;
    var $cache=true;
    var $cache_expire=3600;
    var $group_icons=array(
            'commons'=>'Commons-logo_sister_1x.png',
            'wikidata'=>'Wikidata-logo_sister_1x.png',
            'wikisource'=>'Wikisource-logo_sister_1x.png',
            'wiktionary'=>'Wiktionary-logo_sister_1x.png',
            'mediawiki'=>'MediaWiki-logo_sister_1x.png',
            'wikinews'=>'Wikinews-logo_sister_1x.png',
            'species'=>'Wikispecies-logo_sister_1x.png',
            'metawiki'=>'Meta-logo_sister_1x.png',
            'wikipedia'=>'Wikipedia-logo_sister_1x.png',
            'wikiversity'=>'Wikiversity-logo_sister_1x.png',
            'wikibooks'=>'Wikibooks-logo_sister_1x.png',
            'wikiquote'=>'Wikiquote-logo_sister_1x.png',
            'wikivoyage'=>'Wikivoyage-logo_sister_1x.png',
            'incubator'=>'Incubator-logo.png',
            'meta'=>'Wikimedia_Community_Logo.png',
            'outreach'=>'Wikimedia-logo.png',
            );
    var $size_names=array(
        'all'=>'0',
        'verysmall'=>'1',
        'small'=>'2',
        'medium'=>'3',
        'big'=>'4',
        'large'=>'5',
        );

    static function current_site()
    {
        global $conf;
        if(!isset($conf['wiki_key']) || $conf['wiki_key']=='')
            return false;;
        return $conf['wiki_key'];
    }

    function cache_key($year=false,$page=false)
    {
        return $this->cache_key_from_get('sites', array('menu','submenu','group','page'));
    }
    function valid_cache_date($cache_date)
    {
        return true;
    }
    function init($force_site=false)
    {
        global $conf;
        if(!$force_site && isset($_SERVER['HTTP_HOST']) && preg_match('/^(multi\.)?(wikiscan|ws\d)(\.org|\.freeddns\.org)?$/',$_SERVER['HTTP_HOST'])){
            chdir($conf['root_path']);
            return;
        }
        $this->init_site($force_site);
        $this->init_conf();
        $this->init_path(true);
        if(!$this->setup_wiki())
            return false;
        return true;
    }

    function init_site($force_site=false)
    {
        global $conf;
        set_include_path($conf['root_path']);
        if($force_site!==false){
            $this->site=$force_site;
        }elseif(isset($_SERVER['HTTP_HOST'])){
            $host=explode('.', $_SERVER['HTTP_HOST']);
            $sub=$host[0];
            $this->site=str_replace('-', '_', $sub);
            if(strlen($this->site)<6)
                $this->site.='wiki';
        }elseif(isset($_SERVER['SHELL'])){
            global $argv;
            if(!isset($argv[1]))
                die("No site name\n");
            $this->site=$argv[1];
            unset($argv[1]);
            $new=array();
            foreach($argv as $k=>$v)
                $new[]=$v;
            $argv=$new;
        }else
            die("Multi site error\n");
        $this->wiki=$this->get_wiki();
        if(empty($this->wiki)){
            $this->wiki=$this->get_wiki($this->site.'wiki');
            if(!empty($this->wiki)){
                $this->site.="wiki";
            }else{
                if(isset($_SERVER["SERVER_PROTOCOL"])){
                    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
                    header("Status: 404 Not Found");
                }
                trigger_error("Wiki not found '".htmlspecialchars($this->site)."'");
                echo '<h1>Error 404 Not Found</h1>';
                die("Wiki not found\n");
            }
        }
        $this->stats=self::get_site_stats($this->site);
    }
    function init_conf()
    {
        global $conf;
        $conf['multi']=true;
        $conf['stat_confirm_new_page']=false;
    }
    function init_path($die=false)
    {
        global $conf;
        if(!is_dir($conf['sites_path']) && !is_link($conf['sites_path'])){
            trigger_error("Site path not found '".htmlspecialchars($conf['sites_path'])."'");
            exit;
        }
        $this->site_path="$conf[sites_path]/{$this->site}";
        if(!is_dir($this->site_path)){
            //umask(0);
            mkdir($this->site_path, 0770);
        }
        if(!chdir($this->site_path)){
            if(!$die)
                return false;
            header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
            header("Status: 404 Not Found");
            trigger_error("Site path not found '".htmlspecialchars($this->site_path)."'");
            die("Site path not found\n");
        }
        if(!file_exists("gimg.php"))
            symlink("$conf[multi_path]/gimg.php", "gimg.php");
        if(!is_dir('img')){
            //umask(0);
            mkdir('img', 0770, true);
        }
    }
    function setup_wiki()
    {
        global $conf, $db_conf, $sites_conf, $InterfaceLanguage, $SiteLanguage;
        if(empty($this->wiki)){
            trigger_error("Wiki data not found '".htmlspecialchars($this->site)."'");
            return false;
        }
        $conf['wiki_key']=$this->site;
        $conf['cache_key_site']=$this->site;
        $db_conf['db']['database']=$this->site.'_p';
        $db_conf['db']['port']=3307;
        $db_conf['dbs']['database']="stats_".$this->site;
        if(isset($conf['multi_db']) && $conf['multi_db'] && isset($this->wiki['site_db']) && $this->wiki['site_db']!='')
            $db_conf['dbs']['host']=$this->wiki['site_db'];
        $data=unserialize($this->wiki['site_data']);
        $u=parse_url($data['paths']['file_path']);
        $url=$u['scheme'].'://'.$u['host'];
        $this->wiki['url']=$url;
        $conf['wiki']=$this->wiki;
        if(isset($this->wiki['site_language'])){
            $conf['site_language']=$this->wiki['site_language'];
            if(!$conf['forced_interface_language'])
                $conf['interface_language']=$this->wiki['site_language'];
        }
        $conf['mw_api']=str_replace('$1', 'api.php', $data['paths']['file_path']);
        $conf['link_page']=str_replace('$1', '', $data['paths']['page_path']);
        if(isset($sites_conf[$this->site]))
            foreach($sites_conf[$this->site] as $k=>$v)
                $conf[$k]=$v;
        if(!$InterfaceLanguage->lang_exists($conf['interface_language']))
            $conf['interface_language']='en';
        if($InterfaceLanguage->get_lang()!==$conf['interface_language'])
            $InterfaceLanguage->load_messages($conf['interface_language']);
        if(!$SiteLanguage->lang_exists($conf['site_language']))
            $conf['site_language']='en';
        if($SiteLanguage->get_lang()!==$conf['site_language'])
            $SiteLanguage->load_messages($conf['site_language']);
        if($SiteLanguage->key_exists('timezone'))
            date_default_timezone_set($SiteLanguage->message('timezone'));
        if(isset($this->stats['base_calc']) && $this->stats['base_calc']!='')
            $conf['base_calc']=$this->stats['base_calc'];
        if($conf['base_calc']=='month')
            $conf['live_hours']=[24, 48];
        $this->reset_db();
        $this->reset_api();
    }
    static function export_db_list()
    {
        $dbg=get_dbg();
        $rows=$dbg->select("select s.site_global_key, site_db from sites s, sites_stats ss where s.site_global_key=ss.site_global_key and last_stats is not null");
        $res=[];
        foreach($rows as $v)
            $res[$v['site_db']][]="stats_".$v['site_global_key'];
        foreach($res as $k=>$rows){
            echo 'nohup sh -c "mysqldump -v -q --single-transaction -u bm -p --databases wikiscan stats ';
            echo implode(' ', $rows);
            echo " | gzip -c > $k.sql.gz\"\n\n";
        }
    }

    function reset_db()
    {
        global $dbs;
        if(is_object($dbs) && $dbs->opened)
            $dbs->close();
        $dbs=null;
        global $db;
        if(is_object($db) && $db->opened)
            $db->close();
        $db=null;
    }
    function reset_api()
    {
        global $Api;
        if(is_object($Api))
            $Api->close();
        $Api=null;
    }


    static function get_site_url($site)
    {
        if($_SERVER['SERVER_NAME']=='wikiscan' || $_SERVER['SERVER_NAME']=='multi.wikiscan')
            $host='wikiscan';
        else
            $host=self::$base_host;
        if(preg_match('!wiki$!', $site))
            $site=substr($site,0, -4);
        $site=str_replace('_', '-', $site);
        return "$site.$host";
    }

    function get_wiki($site=false)
    {
        if($site===false)
            $site=$this->site;
        $db=get_dbg();
        $rows=$db->select("select * from sites where site_global_key='".$db->escape($site)."'");
        if(!empty($rows))
            return $rows[0];
        return false;
    }


    static function import_wikis()
    {
        $db=get_db();
        $rows=$db->select('select * from sites');
        if(!empty($rows)){
            $dbg=get_dbg();
            $dbg->query('start transaction');
            $dbg->query('truncate sites');
            foreach($rows as $v){
                $data=unserialize($v['site_data']);
                $u=parse_url($data['paths']['file_path']);
                $v['site_host']=$u['host'];
                $v['site_db']='db'.($v['site_id']%2+1);
                $dbg->insert('sites', $v);
                $dbg->insert_ignore('sites_stats', array('site_id'=>$v['site_id'], 'site_global_key'=>$v['site_global_key']));
            }
            $dbg->query('commit');
        }
        echo count($rows)."\n";
    }

    function get_total_stats()
    {
        $dbg=get_dbg();
        return $dbg->select1("select count(*) sites, sum(total_rev) total_rev, sum(total_page) total_page, sum(total_redirect) total_redirect  from sites_stats where users>0");
    }
    static function get_site_stats($site)
    {
        global $conf;
        if($conf['multi']) {
            $dbg=get_dbg();
            return $dbg->select1("select * from sites_stats where site_global_key='".$dbg->escape($site)."'");
        } else {
            $dbs=get_dbs();
            return $dbs->select1("select * from site_stats");
        }
    }

    static function get_global_stats($site)
    {
        $db=get_dbg();
        $data=$db->selectcol("select data from sites_stats where site_global_key='".$db->escape($site)."'");
        return self::read_global_data($data);
    }
    static function update_global_stats($callback)
    {
        $site=self::current_site();
        $db=get_dbg();
        $db->query('start transaction');
        $data=$db->selectcol("select data from sites_stats where site_global_key='".$db->escape($site)."' for update");
        $data=self::read_global_data($data);
        if(isset($data['users']))//temp clean
            unset($data['users']);
        $data=call_user_func($callback, $data);
        $data=self::write_global_data($data);
        $db->update('sites_stats', 'site_global_key', $site, array('data'=>$data));
        $db->query('commit');
    }
    static function update_local_stats($callback)
    {
        $initialize=false;
        $db=get_dbs();
        $db->query('start transaction');
        $data=$db->selectcol("select data from site_stats for update");
        if($data===false){
            $data=[];
            $initialize=true;
        }else{
            if(isset($data['users']))//temp clean
                unset($data['users']);
            $data=self::read_global_data($data);
        }
        $data=call_user_func($callback, $data);
        $data=self::write_global_data($data);
        if($initialize)
            $db->insert('site_stats', array('data'=>$data));
        else
            $db->update('site_stats', array('data'=>$data), '1=1');
        $db->query('commit');
    }
    static function read_global_data($data)
    {
        if($data!=''){
            if(ord($data[0]) == 0x78 && in_array(ord($data[1]),array(0x01,0x5e,0x9c,0xda)))
                $data=gzuncompress($data);
            $data=unserialize($data);
        }else
            $data=array();
        return $data;
    }
    static function write_global_data($data)
    {
        if(!empty($data)){
            $data=serialize($data);
            $data=gzcompress($data);
        }else
            $data='';
        return $data;
    }
    static function update_score($site=false)
    {
        if(!$site)
            $site=self::current_site();
        if(!$site){
            echo "No site\n";
            return false;
        }
        $dbg=get_dbg();
        $stats=self::get_site_stats($site);
        $data=self::read_global_data($stats['data']);
        $base=1000;
        $scores["edits"]=log(@$data['total']['stats']['user']['edit']+@$data['total']['stats']['ip']['edit']+@$data['total']['stats']['bot']['edit']/1000+$base, 2);
        $scores["articles"]=log(@$data['total']['stats']['user']['new']['article']+@$data['total']['stats']['ip']['new']['article']+@$data['total']['stats']['bot']['new']['article']/100+$base, 2);
        $scores["pages"]=log(@$data['total']['stats']['user']['new']['total']+@$data['total']['stats']['ip']['new']['total']+@$data['total']['stats']['bot']['new']['total']/1000+$base, 2);
        $cols=array(/*'total_rev', 'total_log',*/ /*'total_user',*/  'total_rev_user', /*'total_page', 'total_article',*/ 'users');
        if($site=='commonswiki')
            $cols[]='total_file';
        foreach($cols as $k)
            $scores[$k]=log($stats[$k]+$base, 2);
        print_r($scores);
        $score=array_sum($scores)/count($scores);
        echo "$score\n";
        $q="update sites_stats set score=$score where site_global_key='".$dbg->escape($site)."'";
        $dbg->query($q);
    }

    function view()
    {
        date_default_timezone_set('GMT');
        $submenu=isset($_GET['submenu']) ? $_GET['submenu']  : 'sites';
        if($submenu=='compare'||$submenu=='workers')
            $this->cache=false;
        elseif($submenu=='status')
            $this->cache_expire=300;
        if(!isset($_GET['purge'])||!$_GET['purge'])
            if($r=$this->get_cache())
                return $r;
        $o="<div class=allsites>";
        if($submenu=='sites')
            $o.=$this->view_sites();
        elseif($submenu=='status')
            $o.=$this->view_status();
        elseif($submenu=='tables')
            $o.=$this->view_tables();
        /*elseif($submenu=='compare')
            $o.=$this->view_compare();*/
        elseif($submenu=='workers')
            $o.=$this->view_workers();
        elseif($submenu=='doc')
            $o.=$this->view_doc();
        $o.="</div>";
        if($this->cache)
            $this->set_cache($o);
        return $o;
    }

    function view_workers()
    {
        require_once('include/worker_master.php');
        $master=new WorkerMaster();
        return $master->view_status();
    }

    function view_doc()
    {
        $o="<h1>Documentation</h1>";
        $o.="En construction...";
        return $o;
    }

    function group_icon($group)
    {
        if(isset($this->group_icons[$group]))
            return $this->group_icons[$group];
        return false;
    }
    function group_image($group, $height=18)
    {
        $img=$this->group_icon($group);
        if($img!='')
            return "<img height='$height' alt='$group' title='$group' src='/imgi/logos/$img'/>";
        return false;
    }
    function view_sites()
    {
        $this->load_sites_names();
        //groups
        $dbg=get_dbg();
        $query="from sites_stats ss, sites s where s.site_id=ss.site_id and users>0";
        $rows=$dbg->select("select site_group, sum(score) score, sum(total_rev) total_rev, count(*) n $query group by site_group order by score desc, total_rev, n desc");
        $groups=array(''=>0);
        $non_others=array();
        foreach($rows as $v)
            if($v['n']>=2){
                $groups[$v['site_group']]=$v['n'];
                $non_others[]=$v['site_group'];
            }else
                @$groups['others']++;
        $groups['']=array_sum($groups);
        $group=isset($_GET['group']) ? $_GET['group'] : '';
        //pages
        $this->page=isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if($this->page<1)
            $this->page=1;
        $start=($this->page-1)*$this->limit;
        if($group!=''){
            if($group!='others')
                $query.=" and site_group='".$dbg->escape($group)."'";
            else{
                $in=array();
                foreach($non_others as $v)
                    $in[]="'".$dbg->escape($v)."'";
                $query.=" and site_group not in (".implode(',', $in).")";
            }
        }
        $total=$dbg->selectcol("select count(*) $query");
        $this->totpages=ceil($total/$this->limit);
        $rows=$dbg->select("select * $query order by score desc, total_rev desc limit $start,{$this->limit}");
        $i=0;
        $o='';
        $o.="<div class=sites_nav>";
        $o.=$this->pages_nav();
        $o.=$this->menu_groups($groups);
        $o.="</div>";
        $o.="<div class=sites>";
        foreach($rows as $v){
            $o.=$this->view_site($v);
        }
        $o.="</div>";
        $o.="<div class=sites_nav>".msg('allsites-total_wikis', $total)." ".$this->pages_nav()."</div>";
        if(isset($_GET['quick']))
            $this->view_sites_quicklist();
        return $o;
    }
    function menu_groups($groups)
    {
        $o="<div class=sites_groups>";
        foreach($groups as $group=>$n){
            if($group=='')
                $name=msg('allsites-filter-all');
            elseif($group=='others')
                $name=msg('allsites-filter-others');
            else
                $name=ucfirst($group);
            $o.="<div class=sites_group>".$this->group_image($group,20)."<a href='?menu=allsites&group=".urlencode($group)."'>".$name."</a> <span>($n)</span></div>";
        }
        $o.="</div>";
        return $o;
    }
    function view_site($v)
    {
        $wiki_key=$v['site_global_key'];
        $data=$v['data'];
        if($data=='')
            return false;
        $data=self::read_global_data($data);
        if($data===false)
            echo "Error $wiki_key\n";
        if(!is_array($data) || !isset($data['total']))
            return false;
        $name=$this->site_name($v);
        $time=false;
        if(isset($data['total']['time']))
            $time=$data['total']['time'];
        $cumul_articles=array();
        $tot=array('user'=>0, 'ip'=>0, 'bot'=>0);
        foreach($time as $month=>$stats){
            foreach(array('user','ip','bot') as $type){
                $tot[$type]+=isset($stats["{$type}_new"]['article']) ? $stats["{$type}_new"]['article'] : 0;
                $cumul_articles[$month]["new_article_$type"]=$tot[$type];
            }
        }
        require_once('include/graphlist.php');
        $height=200;
        $xinc=1;
        $reduce=false;
        $graphs['edits']=GraphList::svg_graph(array('user_edit', 'ip_edit', 'bot_edit'), $height, $xinc, $time, 200301, $reduce);
        $graphs['users']=GraphList::svg_graph(array('uuser', 'uip', 'ubot'), $height, $xinc, $time, 200301, $reduce);
        $graphs['articles']=GraphList::svg_graph(array('new_article_user', 'new_article_ip', 'new_article_bot'), $height, $xinc, $cumul_articles, 200301, $reduce);
        $v['edits']=isset($data['total']['stats']['total']['edit']) ? $data['total']['stats']['total']['edit'] : null;
        $o="<div class=site>";
        $o.="<div class=site_inner>";
        $img=$this->group_icon($v['site_group']);
        $url=$this->get_site_url($wiki_key);
        $o.="<a href=\"//$url\"><div class=site_title>";
        if($img!='')
            $o.="<div class=site_logo>".$this->group_image($v['site_group'], 20)."</div>";
        $o.="<span class=site_name>$name</a></span></div>";
        $o.='</a>';
        $o.="<a href=\"//$url\">";
        $o.='<div class=site_graph title="'.msg('stat-users').'">'.$graphs['users']."<div class=site_num>".$this->fnum($v['users'])."</div></div>";
        $o.='<div class=site_graph title="'.msg('stat-edits').'">'.$graphs['edits']."<div class=site_num>".$this->fnum($v['edits'])."</div></div>";
        $o.='<div class=site_graph title="'.msg('stat-articles').'">'.$graphs['articles']."<div class=site_num>".$this->fnum($v['total_article'])."</div></div>";
        $o.='</a>';
        $o.="</div></div>";
        return $o;
    }
    function site_name($v)
    {
        $name=mb_strtoupper(mb_substr($v['site_group'],0,1)).mb_substr($v['site_group'],1);
        if(preg_match('%^wikt?i(?!mania|data)%i', $name)){
            if(isset($this->lang_names[$v['site_language']])){
                $name=$this->lang_names[$v['site_language']];
                $name=mb_strtoupper(mb_substr($name,0,1)).mb_substr($name,1);
            }else
                $name.=' '.$v['site_language'];
        }
        return $name;
    }
    function view_sites_quicklist()
    {
        $this->load_sites_names();
        $dbg=get_dbg();
        $query="from sites_stats ss, sites s where s.site_id=ss.site_id and users>0";
        $rows=$dbg->select("select site_group, sum(score) score, sum(total_rev) total_rev, count(*) n $query group by site_group order by score desc, total_rev, n desc");
        $non_others=array();
        foreach($rows as $v)
            if($v['n']>=2){
                $groups[$v['site_group']]=$v['n'];
                $non_others[]=$v['site_group'];
            }else
                @$groups['others']++;
        $o='Total : '.array_sum($groups);
        foreach($groups as $group=>$n){
            if($group!='others'){
                $where="site_group='".$dbg->escape($group)."'";
            }else{
                $in=array();
                foreach($non_others as $v)
                    $in[]="'".$dbg->escape($v)."'";
                $where=" site_group not in (".implode(',', $in).")";
            }
            $rows=$dbg->select("select * $query and $where order by score desc, total_rev desc");
            $o.="<p><b>$group</b> (".count($rows).") : ";
            $lst=array();
            foreach($rows as $v){
                preg_match('!^(?:www\.)?([^\.]+)\.!',$v['site_host'],$r);
                $url=$this->get_site_url($v['site_global_key']);
                $lst[]="[http://$url $r[1]]";
            }
            $o.=implode(', ', $lst).'.</p>';
        }
        echo $o;
        return $o;
    }
    function load_sites_names()
    {
        global $conf;
        $this->lang_names=[];
        if(!$conf['use_sites_names'])
            return;
        $file="include/languages/sites_names.json";
        if(file_exists($file))
            $this->lang_names=json_decode(file_get_contents($file), true);
    }

    function fnum($v)
    {
        $sp='&nbsp;';
        if($v<1000)
            return $v;
        if($v<10000)
            return round($v/1000,1).$sp.'k';
        if($v<1000000)
            return round($v/1000).$sp.'k';
        if($v<10000000)
            return round($v/1000000,1).$sp.'M';
        if($v<1000000000)
            return round($v/1000000).$sp.'M';
        return round($v/1000000000).$sp.'G';
    }
    function pages_nav()
    {
        $o="<div class='sites_pages'>";
        if($this->page>1){
            $o.="<span class='page_start'>".lnk("<img src='imgi/icons/start.png'/>",array('page'=>1),array('menu','submenu','group','lang')).'</span>';
            $o.="<span class='page_prev'>".lnk("<img src='imgi/icons/prev.png'/>",array('page'=>$this->page-1),array('menu','submenu','group','lang')).'</span>';
        }
        $o.=msg('navigation-page')." {$this->page}";
        if($this->page<$this->totpages){
            $o.="<span class='page_next'>".lnk("<img src='imgi/icons/next.png'/>",array('page'=>$this->page+1),array('menu','submenu','group','lang')).'</span>';
            $o.="<span class='page_end'>".lnk("<img src='imgi/icons/end.png'/>",array('page'=>$this->totpages),array('menu','submenu','group','lang')).'</span>';
        }
        $o.='</div>';
        return $o;
    }
    function view_status()
    {
        ini_set('max_execution_time','180');
        $o='<h1>'.msg('wikis_status-title').'</h1>';
        $o.='<table class=allsites_table>';
        $o.="<tr><th rowspan=2>".msg('wikis_status-wiki')."</th>
        <th colspan=4>".msg('wikis_status-edits')."</th>
        <th colspan=4>".msg('wikis_status-users')."</th>
        <th colspan=4>".msg('wikis_status-last_update')."</th>
        <th colspan=3>".msg('wikis_status-update_length')."</th>
        <th rowspan=2>".msg('wikis_status-size')."</th>
        </tr>";
        $o.="<tr>

        <th>".msg('wikis_status-last_hours')."</th>
        <th colspan=2>".msg('wikis_status-total')."</th>
        <th>".msg('wikis_status-labs_diff')."</th>

        <th>".msg('wikis_status-last_hours')."</th>
        <th colspan=2>".msg('wikis_status-total')."</th>
        <th>".msg('wikis_status-labs_diff')."</th>

        <th>".msg('wikis_status-last_hours')."</th>
        <th>".msg('wikis_status-total_recent')."</th>
        <th>".msg('wikis_status-total_full')."</th>
        <th>Total Labs</th>

        <th>".msg('wikis_status-last_hours')."</th>
        <th>".msg('wikis_status-total_recent')."</th>
        <th>".msg('wikis_status-total_full')."</th>
        </tr>";
        require_once('include/worker.php');
        $worker=new Worker();
        $dbg=get_dbg();
        $this->page=isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if($this->page<1)
            $this->page=1;
        $start=($this->page-1)*$this->limit_status;
        $total=$dbg->selectcol("select count(*) from sites where site_type='mediawiki'");
        $this->totpages=ceil($total/$this->limit_status);
        $rows=$dbg->select("select * from sites_stats ss, sites s where s.site_id=ss.site_id and site_type='mediawiki' order by total_rev desc limit $start,{$this->limit_status}");

        require_once('include/graphlist.php');
        foreach($rows as $v){
            $data=self::read_global_data($v['data']);
            $total=isset($data['total']['stats']['total'])?$data['total']['stats']['total']:false;
            $live=isset($data['live']['stats']['total'])?$data['live']['stats']['total']:false;
            $o.="<tr>";
            $size=Worker::$size_names[$v['size']];
            $this->worker_size=$worker->config[$size];
            $wiki_key=$v['site_global_key'];
            $height=200;
            $name=mb_ucfirst($v['site_group']);
            if(preg_match('%^wikt?i(?!mania|data)%', $v['site_group']))
                $name.=' '.$v['site_language'];
            $url=$this->get_site_url($wiki_key);
            $o.="<td>".($total ? "<a href=\"//$url\">$name</a>" : $name).'</td>';
            $o.="<td>".(isset($live['edit']) ? fnum($live['edit']) : '')."</td>";
            $o.="<td>".(isset($data['total']['time']) ? $graphs['edits']=GraphList::svg_graph(array('user_edit', 'ip_edit', 'bot_edit'), $height, 1, $data['total']['time'], 200301) : "")."</td>";
            $o.="<td>".($total ? fnum($total['edit']) : "<i>".fnum($v['total_rev'])."</i>")."</td>";
            $o.="<td>".($total ? ($total['edit']-$v['total_rev']>0 ? '+' : ''). fnum($total['edit']-$v['total_rev']) : '')."</td>";
            $o.="<td>".(isset($data['live']['stats']['users']['user']['threshold_edits'][1]) ? fnum($data['live']['stats']['users']['user']['threshold_edits'][1]) : '')."</td>";
            $o.="<td>".(isset($data['total']['time']) ? $graphs['edits']=GraphList::svg_graph(array('uuser', 'uip', 'ubot'), $height, 1, $data['total']['time'], 200301) : "")."</td>";
            $o.="<td>".($v['users_edit'] ? fnum($v['users_edit']) : ($v['total_rev_user']!==null ? "<i>".fnum($v['total_rev_user'])."</i>" : ''))."</td>";
            $o.="<td>".($total && $v['total_rev_user']!==null ? ($v['users_edit']-$v['total_rev_user']>0 ? '+' : ''). fnum($v['users_edit']-$v['total_rev_user']) : '')."</td>";
            foreach(array('live','sum','stats','count') as $k)
                $o.="<td class='".$this->color_last($k, $v["last_$k"])."'>".(isset($v["last_$k"]) ? format_time(time()-strtotime($v["last_$k"])) : '')."</td>";
            foreach(array('live','sum','stats') as $k)
                $o.="<td style='background-color:".$this->color_duration($v["duration_$k"])."'>".(isset($v["duration_$k"]) ? format_time($v["duration_$k"]) : '')."</td>";
            $o.="<td>".$v['size']."</td>";
            $o.="<td>$v[base_calc]</td>";
            $o.="</tr>";
        }
        $o.='</table>';
        $o.=$this->pages_nav();
        return $o;
    }
    function color_last($type, $last_date)
    {
        if($last_date=='')
            return "";
        $last=strtotime($last_date);
        $max_time=strtotime("-".$this->worker_size[$type]);
        $current_diff=time()-$last;
        $max_diff=time()-$max_time;
        if($current_diff<=0.25*$max_diff)
            $cls="fresh1";
        elseif($current_diff<=$max_diff*1.05)
            $cls="fresh2";
        elseif($current_diff<=1.5*$max_diff)
            $cls="out1";
        elseif($current_diff<=2*$max_diff)
            $cls="out2";
        else
            $cls="out3";
        return $cls;
    }
    function color_duration($v)
    {
        if($v=='')
            return '';
        if($v<=60)
            $c=array(245-round(60*$v/60), 255-round($v/60), 255-round($v/60));
        elseif($v<=600)
            $c=array(210-round(80*$v/600), 250-round($v/600), 250-round($v/600));
        elseif($v<=3600)
            $c=array(200-round(120*$v/3600), 250-round(170*$v/3600), 250-round(10*$v/3600));
        elseif($v<=86400)
            $c=array(80+round(80*$v/86400), 80, 240);
        else
            $c=array(160+min(round(5*$v/86400),50), 80, 240);
        return "rgb($c[0],$c[1],$c[2])";
    }
    function view_tables()
    {
        $o='<table class=allsites_table>';
        $o.="<tr><th>Site</th><th>Revisions</th><th>Archive</th><th>%</th><th>Logs</th><th>Users</th><th>Pages</th><th>Redirects</th><th>Articles</th><th>Dern màj</th><th>Lien</th></tr>";
        $dbg=get_dbg();
        $rows=$dbg->select("select * from sites_stats ss, sites s where s.site_id=ss.site_id order by total_rev desc");
        $db=get_dbs();
        foreach($rows as $v){
            $local=false;
            if($db->select_db("stats_".$v['site_global_key'])){
                $local=true;
            }
            $o.="<tr>";
            $prefix=$v['site_global_key'];
            $url=self::get_site_url($prefix);
            $name=$v['site_group'];
            if(preg_match('%^wikt?i(?!mania|data)%', $name))
                $name.=' '.$v['site_language'];
            $o.="<td>".($local ? "<a href=\"//$url\">$name</a>" : $name)."</td>";
            $o.="<td>".fnum($v['total_rev'])."</td>";
            $o.="<td>".fnum($v['total_archive'])."</td>";
            $o.="<td>".@round(100*$v['total_archive']/($v['total_rev']+$v['total_archive']))."%</td>";
            $o.="<td>".fnum($v['total_log'])."</td>";
            $o.="<td>".fnum($v['total_user'])."</td>";
            $o.="<td>".fnum($v['total_page'])."</td>";
            $o.="<td>".fnum($v['total_redirect'])."</td>";
            $o.="<td>".fnum($v['total_article'])."</td>";
            $o.="<td>".($v['last_count'] ? format_time(time()-strtotime($v['last_count'])) : '')."</td>";
            $o.="<td><a href=\"https://$v[site_host]\">$v[site_host]</a></td>";
            $o.="</tr>";
        }
        $o.='</table>';
        return $o;
    }
    /*
    function view_compare()
    {
        $wiki=$_GET['site'];
        if($wiki=='')
            return false;
        $h_wiki=htmlspecialchars($wiki);
        $u_wiki=urlencode($h_wiki);
        $o="<h1>Comparaison avec le Labs : $h_wiki</h1>";
        $o.="<div class=compare>";
        $ubase="?menu=allsites&submenu=compare&site=$u_wiki&warning=1";
        if(!isset($_GET['warning'])){
            $o.="<p>Cette page sert à faire des comparaisons entre les statistiques stockées sur Wikiscan et les valeurs calculables en direct sur la base de données du Labs.</p><p>Les requêtes en direct sur le Labs <b>peuvent prendre plusieurs minutes</b> voire <b>plusieurs dizaines de minutes</b> sur les gros projets.</p><p>Cette fonction doit être utilisée avec précaution pour ne pas surcharger les bases de données du Labs. Si vous voulez juste voir, prenez un wiki pas trop volumineux (moins de 5 millions de modifications).</p><p>Des petites différences sont parfaitement normales, cela vient généralement des pages supprimées depuis la dernière mise à jour.</p>";
            $o.="<p><form method=get action='?'>
            <input type=hidden name=menu value=allsites>
            <input type=hidden name=submenu value=compare>
            <input type=hidden name=site value=$h_wiki>
            <input type=hidden name=warning value=1>
            <input type=submit value='Continuer'>
            </form></p><br><br><br><br><br><br><br><br>";
            return $o."</div>";
        }
        $this->init($wiki);
        $dbs=get_dbs();
        $rows=$dbs->select("select * from dates where type='Y' order by date");
        $o.='<table class="allsites_table allsites_compare"><tr><th>Année</th>';
        foreach($rows as $v)
            $o.="<td><a href='$ubase&year=$v[date]'>$v[date]</a></td>";
        $o.="<td><a href='$ubase&allyear=1'>Années</a></td>";
        $o.="<td><a href='$ubase&all=1'>Total</a></td>";
        $o.="</tr></table>";
        if(isset($_GET['all'])){
            $db=get_db();
            $labs=array();
            $labs['users']=$db->selectcol("select count(distinct rev_user) from revision_userindex where rev_user>0");
            $labs['edits']=$db->selectcol("select count(*) edits from revision");
            $v=$dbs->select1("select * from dates where date=0");
            $o.=$this->compare_head();
            $o.="<tr>";
            $o.="<td>Total</td>";
            $o.=$this->compare_cols($v, $labs);
            $o.="</tr>";
            $o.='</table>';
        }elseif(isset($_GET['allyear'])){
            $db=get_db();
            $labs_rows=$db->select("select left(rev_timestamp,4) date, count(*) edits from revision group by date");
            foreach($labs_rows as $v)
                $labs[$v['date']]['edits']=$v['edits'];
            $labs_rows=$db->select($this->labs_users_query(4));
            foreach($labs_rows as $v)
                $labs[$v['date']]['users']=$v['users'];
            $rows=$dbs->select("select * from dates where type='Y' order by date");
            $o.=$this->compare_head();
            foreach($rows as $v){
                $labs_edits=(int)@$labs[$v['date']];
                $o.="<tr>";
                $o.="<td><a href='$ubase&year=$v[date]'>$v[date]</a></td>";
                $o.=$this->compare_cols($v, isset($labs[$v['date']]) ? $labs[$v['date']] : array());
                $o.="</tr>";
            }
            $o.='</table>';
        }elseif(isset($_GET['year'])){
            $db=get_db();
            $year=$_GET['year'];
            $min=$year.'0101000000';
            $max=($year+1).'0101000000';
            $labs_rows=$db->select("select left(rev_timestamp,6) date, count(*) edits from revision where rev_timestamp>='$min' and rev_timestamp<'$max' group by date");
            foreach($labs_rows as $v)
                $labs[$v['date']]['edits']=$v['edits'];
            $labs_rows=$db->select($this->labs_users_query(6, $min, $max));
            foreach($labs_rows as $v)
                $labs[$v['date']]['users']=$v['users'];
            $rows=$dbs->select("select * from dates where type='M' and date like '$year%' order by date");
            $o.=$this->compare_head();
            foreach($rows as $v){
                $labs_edits=(int)@$labs[$v['date']];
                $o.="<tr>";
                $o.="<td><a href='$ubase&month=$v[date]'>$v[date]</a></td>";
                $o.=$this->compare_cols($v, isset($labs[$v['date']]) ? $labs[$v['date']] : array());
                $o.="</tr>";
            }
            $o.='</table>';
        }elseif(isset($_GET['month'])){
            $db=get_db();
            $month=$_GET['month'];
            $rows=$dbs->select("select * from dates where type='M' and date like '".substr($month,0,4)."%' order by date");
            $o.='<table class="allsites_table allsites_compare"><tr><th>Mois</th>';
            foreach($rows as $v)
                $o.="<td><a href='$ubase&month=$v[date]'>".substr($v["date"],4,2)."</a></td>";
            $o.="</tr></table>";
            $min=$month.'01000000';
            $max=date('Ym',strtotime('+1 month', strtotime($min))).'01000000';
            $labs_rows=$db->select("select left(rev_timestamp,8) date, count(*) edits from revision where rev_timestamp>='$min' and rev_timestamp<'$max' group by date");
            foreach($labs_rows as $v)
                $labs[$v['date']]['edits']=$v['edits'];
            $labs_rows=$db->select($this->labs_users_query(8, $min, $max));
            foreach($labs_rows as $v)
                $labs[$v['date']]['users']=$v['users'];
            $rows=$dbs->select("select * from dates where type='D' and date like '$month%' order by date");
            $o.=$this->compare_head();
            foreach($rows as $v){
                $o.="<tr>";
                $url=$this->get_site_url($wiki);
                $url.="/?menu=dates&filter=all&sort=weight&date=".$v['date']."&list=users";
                $o.="<td><a href='//$url'>".$v['date']."</a></td>";
                $o.=$this->compare_cols($v, isset($labs[$v['date']]) ? $labs[$v['date']] : array());
                $o.="</tr>";
            }
            $o.='</table>';
        }
        return $o."</div>";
    }

    function labs_users_query($date_len, $min=false, $max=false)
    {
        return "select left(rev_timestamp,$date_len) date, count(distinct rev_user) users from revision_userindex where ".($min ? "rev_timestamp>='$min' and rev_timestamp<'$max' and " : "") ."rev_user>0 group by date";
    }
    function compare_head()
    {
        return '<table class="allsites_table allsites_compare">'
            ."<tr><th rowspan=2>Date</th><th colspan=3>Modifications</th><th colspan=3>Utilisateurs<th colspan=2 rowspan=2>Dernière màj</th></tr>"
            ."<tr><th>Local</th><th>Labs</th><th>Diff</th><th>Local</th><th>Labs</th><th>Diff</th></tr>";
    }
    function compare_cols($v, $labs)
    {
        if(!isset($labs['edits']))
            $labs['edits']=0;
        if(!isset($labs['users']))
            $labs['users']=0;
        $o="<td>".fnum($v['edits'])."</td>";
        $o.="<td>".fnum($labs['edits'])."</td>";
        $o.="<td>".($v['edits']-$labs['edits']>0 ? '+' : ''). fnum($v['edits']-$labs['edits'])."</td>";
        $o.="<td>".fnum($v['users_edit'])."</td>";
        $o.="<td>".fnum($labs['users'])."</td>";
        $o.="<td>".($v['users_edit']-$labs['users']>0 ? '+' : ''). fnum($v['users_edit']-$labs['users'])."</td>";
        $o.="<td>".format_time(time()-strtotime($v['last_update']))."</td>";
        $o.="<td>".date('d/m/Y H:i:s', strtotime($v['last_update']))."</td>";
        return $o;
    }
    */

    static function export()
    {
        $dbg=get_dbg();
        $rows=$dbg->select("select * from sites_stats ss, sites s where s.site_id=ss.site_id order by total_rev desc");
        foreach($rows as $v)
            echo "$v[site_host]\n";
    }

    static function query_all($query)
    {
        global $conf, $db_conf;
        echo "$query\n";
        $i=0;
        $list=self::list_all();
        foreach($list as $site){
            $i++;
            $db_conf['dbs']['database']="stats_".$site;
            $db_host=self::site_db($site);
            echo "$site on $db_host\n";
            if(isset($conf['multi_db']) && $conf['multi_db'] && $db_host!='')
                $db_conf['dbs']['host']=$db_host;
            if($db=get_dbs(true, true)){
                echo $i."/".count($list)." $site\n";
                $db->query($query);
            }
        }
    }
    static function list_all()
    {
        $dbg=get_dbg();
        $rows=$dbg->select("select site_global_key from sites");
        $res=array();
        foreach($rows as $v)
            $res[]=$v['site_global_key'];
        return $res;
    }
    static function list_all_full()
    {
        $dbg=get_dbg();
        $rows=$dbg->select("select * from sites");
        $res=array();
        foreach($rows as $v)
            $res[$v['site_global_key']]=$v;
        return $res;
    }
    static function list_all_with_stats()
    {
        $dbg=get_dbg();
        $rows=$dbg->select("select site_global_key from sites_stats where last_stats is not null");
        $res=array();
        foreach($rows as $v)
            $res[]=$v['site_global_key'];
        return $res;
    }
    static function site_db($site)
    {
        $dbg=get_dbg();
        return $dbg->select_col("select site_db from sites where site_global_key='".$dbg->escape($site)."'");
    }
    static function list_db_with_stats()
    {
        $dbg=get_dbg();
        $rows=$dbg->select("select sites.site_global_key, site_db from sites,sites_stats where sites.site_global_key=sites_stats.site_global_key and last_stats is not null");
        $res=array();
        foreach($rows as $v)
            $res[$v['site_db']][]=$v['site_global_key'];
        return $res;
    }
}

?>