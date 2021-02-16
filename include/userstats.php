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
require_once('include/dates.php');
require_once('include/toplist.php');
require_once('include/site_page.php');

class UserStats extends site_page
{
    var $nb_user=0;
    var $nb_rev=0;
    var $s;
    var $limit=100;
    var $max_pages=100;
    var $cache=true;
    var $cache_expire=3600;
    var $lastupdate_key='userstats:lastupdate';
    var $user='';
    var $user_id;
    var $abbr_group=false;
    var $table='userstats';
    var $ip=false;
    var $sort_toggled=false;
    var $userlist_max=500;
    var $list_use_real_count=false;
    var $userlist_page;
    var $date_filter;
    var $data_path='wpstats';
    var $months_graphs_stats=array('users', 'tot_time2', 'edit', 'new_main', 'meta', 'talk', 'log_sysop', 'delete', 'block');
    var $months_graphs_stats_short=array('tot_time2');
    var $use_sum_userids_chunks=true;
    var $sum_chunk_min_users=50000;
    var $sum_user_ids_chunk_size=200000;
    var $thresholds=array(
        'edit'=>array(1, 5, 20, 100, 1000, 10000),
        'main'=>array(1, 5, 20, 100, 1000, 10000),
        'months'=>array(1, 3, 6, 12, 24, 60),
        'days'=>array(1, 3, 5, 10, 30, 100, 1000),
        'tot_time2'=>array(3600, 18000, 36000, 360000, 3600000),
        );

    function __construct($ip=false)
    {
        global $conf;
        $this->ip_mode($ip);
        $this->fields=array(
            'user'=>array   ('class'=>'','sum'=>false,'sort'=>false),
            'date'=>array   ('class'=>'','sum'=>false,'sort'=>true),
            'groups'=>array ('class'=>'grp','sum'=>false,'sort'=>false),
            'total'=>array  ('class'=>'','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit')),
            'reduced'=>array('hide'=>1,'class'=>'','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'total'),
            'edit'=>array   ('class'=>'uedit','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'total'),
            'article'=>array('class'=>'uedit','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'edit'),
            'main'=>array   ('class'=>'uedit tedit','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'edit'),
            'annexe'=>array ('class'=>'uedit tedit','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'edit'),
            'talk'=>array   ('class'=>'uedit tedit','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'edit'),
            'meta'=>array   ('class'=>'uedit tedit','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'edit'),
            'ns_file'=>array('class'=>'uedit tedit','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'edit'),
            'ns_user'=>array('class'=>'uedit tedit','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'edit'),
            'other'=>array  ('class'=>'uedit tedit','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'edit'),
            'redit'=>array  ('class'=>'uedit tedit','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'edit'),
            'edit_chain'=>array('class'=>'uedit tedit','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'edit'),
            'revert'=>array ('class'=>'uedit tedit','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'edit'),
            //New
            'new'=>array     ('class'=>'unew','sum'=>true,'sort'=>true,'func'=>'fnum','percent'=>'edit'),
            'new_main'=>array('class'=>'unew','sum'=>true,'sort'=>true,'func'=>'fnum','percent'=>'main'),
            'new_chain_main'=>array('class'=>'unew tnew','sum'=>true,'sort'=>true,'func'=>'fnum','percent'=>'new_main'),
            'new_redir'=>array('class'=>'unew tnew','sum'=>true,'sort'=>true,'func'=>'fnum','percent'=>'new'),
            //Texts
            'diff_tot'=>array('class'=>'utext','sum'=>true,'sort'=>true,'func'=>'format_sizei'),
            'diff'=>array    ('class'=>'utext ttext','sum'=>true,'sort'=>true,'func'=>'format_sizei'),
            'diff_article_no_rv'=>array('class'=>'utext ttext','sum'=>true,'sort'=>true,'func'=>'format_sizei'),
            'tot_size'=>array('class'=>'utext ttext','sum'=>true,'sort'=>true,'func'=>'format_sizei'),
            'diff_small'=>array('class'=>'utext ttext','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'total_diffs'),
            'diff_medium'=>array('class'=>'utext ttext','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'total_diffs'),
            'diff_big'=>array('class'=>'utext ttext','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'total_diffs'),
            //Log
            'log'=>array      ('class'=>'ulog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'total'),
            'log_sysop'=>array('class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log'),
            'log_chain'=>array('class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log'),
            'move'=>array     ('class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log'),
            'upload'=>   array('class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log'),
            'delete'=>   array('class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log_sysop'),
            'restore'=>  array('class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log_sysop'),
            'revdelete'=>array('class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log_sysop'),
            'block'=>    array('class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log_sysop'),
            'unblock'=>  array('class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log_sysop'),
            'protect'=>  array('class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log_sysop'),
            'unprotect'=>array('class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log_sysop'),
            'rename'=>   array('optional'=>true,'class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log'),
            'rights'=>   array('optional'=>true,'class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log'),
            'filter'=>   array('class'=>'ulog tlog','optional'=>true,'sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log'),
            'import'=>   array('hide'=>true,'optional'=>true,'class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log_sysop'),
            'newuser'=>  array('optional'=>true,'class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log'),
            'feedback'=> array('optional'=>true,'class'=>'ulog tlog','sum'=>true,'sort'=>true,'func'=>array($this,'format_edit'),'percent'=>'log'),
            //Time
            'days'=>array    ('class'=>'utime','sum'=>true,'sort'=>true),
            'months'=>array  ('class'=>'utime','sum'=>true,'sort'=>true),
            'tot_time'=>array('class'=>'utime ttime','hide'=>true,'sum'=>true,'sort'=>true),
            'tot_time2'=>array('class'=>'utime ttime','sum'=>true,'sort'=>true),
            'tot_time3'=>array('class'=>'utime ttime','hide'=>true,'sum'=>true,'sort'=>true),
            'time_day'=>array('class'=>'utime ttime','sum'=>false,'sort'=>false),
            'total_hour'=>array('class'=>'utime ttime','sum'=>false,'sort'=>false),
            'total_day'=>array('class'=>'utime ttime','sum'=>false,'sort'=>false),
            'total_month'=>array('class'=>'utime ttime','sum'=>false,'sort'=>false),
            );
    }
    function reset()
    {
        $this->s=array();
        $this->nb_user=0;
        $this->nb_rev=0;
    }
    function ip_mode($enable=true)
    {
        if($enable){
            $this->ip=true;
            $this->types=array('ip'=>'ip');
            $this->table='userstats_ip';
        }else{
            $this->ip=false;
            $this->types=array('user'=>'user','bot'=>'bot');
            $this->table='userstats';
        }
    }
    function load_params()
    {
        global $conf;
        $this->detail=isset($_GET['detail']) && $_GET['detail'] ? 1 : 0;
        $this->userlist=isset($_GET['userlist']) ? trim($_GET['userlist']) : '';
        $this->user=self::get_user_name();
        if($this->user!=''){
            $this->user=mwtools::format_user($this->user);
            if($this->user=='Script de conversion')
                $this->user='script de conversion';
            $this->detail=1;
        }
        if(isset($_GET['usort']) && isset($this->fields[$_GET['usort']]) && $this->fields[$_GET['usort']]['sort'])
            $this->sort=$_GET['usort'];
        elseif($this->user=='')
            $this->sort='total';
        else
            $this->sort='date';
        $this->order=isset($_GET['order']) && $_GET['order']=='asc' ? 'asc' : 'desc';
        if($this->order=='asc' && $this->sort!='diff' && $this->sort!='diff_article_no_rv')
            $this->order='desc';
        $this->bot=isset($_GET['bot']) && $_GET['bot'] ? 1 : 0;
        if($this->userlist!='')
            $this->bot=1;
        $this->percent=1;
        $this->page=isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if($this->page<1)
            $this->page=1;
        if($this->max_pages!==false && $this->page>$this->max_pages)
            $this->page=$this->max_pages;
        $this->recalc_user=isset($_GET['recalc']) && $this->user!='';
        $this->date_filter=$conf['stats_users_date_filter'] && isset($_GET['date_filter']) ? (int)$_GET['date_filter'] : null;
    }
    static function get_user_name()
    {
        if(preg_match('!^/'.preg_quote(msg('urlpath-user'), '!').'/(.+)$!', $_SERVER['REQUEST_URI'], $r)){
            //get user directly to avoid mod_rewrite url decode for name with '+'
            return urldecode($r[1]);
        }else
            return isset($_GET['user']) ? $_GET['user'] : '';
    }
    function user_exist($user)
    {
        $dbs=get_dbs();
        $user=mwtools::format_user($user);
        $row=$dbs->select1("select user from {$this->table}_tot where user='".$dbs->escape($user)."'");
        return isset($row['user']);
    }
    function get_userlist()
    {
        $res=array();
        $this->userlist_page='';
        if($this->userlist!=''){
            if(preg_match('/^.+:.+$/',$this->userlist)){
                $this->userlist_page=$this->userlist;
                $ns=mwns::get()->get_ns($this->userlist);
                $api=wiki_api();
                if($ns==NS_CATEGORY){
                    $users=$api->category_members($this->userlist,'2|3');
                    if(is_array($users)){
                        foreach($users as $v){
                            $v=mwns::get()->remove_ns($v);
                            if(($pos=strpos($v,'/'))!==false)
                                $v=substr($v,0,$pos);
                            $res[$v]=$v;
                            if(count($res)>=$this->userlist_max)
                                break;
                        }
                    }
                }else{
                    //extract from wikitext, support [[user/user talk/alias:...]] and {{u|...}} / {{u-|...}}
                    $content=$api->content($this->userlist);
                    $ns_strings=array_merge(mwns::get()->ns_strings(NS_USER),mwns::get()->ns_strings(NS_USER_TALK));
                    $ns_strings=array_map('preg_quote', $ns_strings);
                    $users=array();
                    if(preg_match_all('!\[\[(?:'.implode('|', $ns_strings).'):([^\|\]]+)!i', $content, $r))
                        $users=array_merge($users, $r[1]);
                    if(preg_match_all('!\{\{u-?\s*\|([^\|\}]+)!i', $content, $r))
                        $users=array_merge($users, $r[1]);
                    foreach($users as $user){
                        $user=mwtools::format_user(trim($user));
                        if($user!='')
                            $res[$user]=$user;
                        if(count($res)>=$this->userlist_max)
                            break;
                    }
                }
            }else{
                foreach(explode(',',$this->userlist) as $v){
                    $user=mwtools::format_user(trim($v));
                    if($user!='')
                        $res[$user]=$user;
                    if(count($res)>=$this->userlist_max)
                        break;
                }
            }
        }
        return $res;
    }
    function canonical($user)
    {
        if($this->user_exist($user))
            return '<link rel="canonical" href="'.($this->ip?self::ip_url($user):self::user_url($user)).'"/>';
        return false;
    }
    function view()
    {
        global $conf;

        $this->load_params();
        if($this->recalc_user)
            $this->recalc_user();
        if(!isset($_GET['purge'])||!$_GET['purge'])
            if($r=$this->get_cache())
                return $r;
        $dbs=get_dbs();
        $this->groups=mwTools::user_groups();
        $default=array('menu'=>$this->table,'usort'=>$this->sort,'bot'=>$this->bot,'detail'=>$this->detail,'page'=>$this->page);
        if($this->userlist!='')
            $default['userlist']=$this->userlist;
        if($this->date_filter!='')
            $default['date_filter']=$this->date_filter;
        $o='<div class="userstats">';
        $o.=$this->javascript();
        if($this->sort!='' && $this->sort!='total')
            $sorts[]='`'.$dbs->escape($this->sort).'` '.$this->order;
        $filters[]='<a class="js" href="javascript:td(\'.up\',\'inline\')">'.msg('userstats-show_percent').'</a>';
        $this->lusers=array();
        if($this->user!='')
            $o.=$this->view_user($this->user,$filters);
        else{
            $o.='<h1>'.msg('userstats-title').'</h1>';
            $o.="<table class=mep>";
            $o.="<tr><td>";
            $o.="<div class=inputs>".$this->user_form().$this->userlist_form().'</div>';
            if($this->userlist=='' && !$this->ip){
                $o.=$this->months_graphs();
                $o.="</td><tr><tr><td>";
            }
            if(!isset($_GET['graphs_details']) || !$_GET['graphs_details']){
                if($conf['stats_users_date_filter'])
                    $o.=$this->view_date_filter();
                $o.="</td><tr><tr><td>";
                $this->fields['date']['hide']=true;
                $this->abbr_group=true;
                if($this->date_filter)
                    $date_type = strlen($this->date_filter)==4 ? 'Y' : 'M';
                else
                    $date_type='T';
                $table=$this->multi_table_name($date_type);
                $where=[];
                if($this->date_filter)
                    $where[]="`$table`.date='".(int)$this->date_filter."'";
                if(!$this->bot)
                    $where[]="`$table`.user_type!='B'";
                $where=implode(' and ', $where);
                if($this->sort=='months'){
                    $sorts[]='days '.$this->order;
                    $sorts[]='total '.$this->order;
                }else
                    $sorts[]='total '.$this->order;
                if(!$this->ip && $this->userlist=='')
                    $filters[]=lnk($this->bot ? msg('userstats-hide_bots') : msg('userstats-show_bots'), array_merge($default,array('bot'=>$this->bot?0:1)));
                $filters[]=lnk($this->detail ? msg('userstats-rounded_edits') : msg('userstats-precise_edits'), array_merge($default,array('detail'=>$this->detail?0:1)));
                if($this->userlist!=''){
                    $this->lusers=$this->get_userlist();
                    if(!empty($this->lusers)){
                        if($this->userlist_max > $this->limit)
                            $this->limit=$this->userlist_max;
                        $in=array();
                        if(count($this->lusers)==$this->userlist_max)
                            $o.='<div class="userlist_limit">La limite des '.$this->userlist_max.' utilisateurs a été atteinte par la source, le reste est ignoré.</div>';
                        foreach($this->lusers as $v)
                            $in[]="'".$dbs->escape($v)."'";
                        $o.=$this->view_list($date_type, $in, $sorts, $filters, $where);
                    }else{
                        $o.='<div class="error">Pas d\'utilisateurs trouvés</div>';
                    }
                }else{
                    $o.=$this->view_list($date_type, false, $sorts, $filters, $where);
                }
            }
            $o.="</td><tr></table>";
        }
        $o.='</div>';
        if($this->cache)
            $this->set_cache($o);
        return $o;
    }
    function user_form()
    {
        return '<div class="user_form"><form action="?" method="get">'
            .'<input type="hidden" name="menu" value="'.$this->table.'"/>'
            .'<input type="input" name="user" value="" size="10"/>'
            .'<input type="submit" value="'.($this->ip ? msg('userstats-button-ip') : msg('userstats-button-user')).'"/></form></div>';
    }
    function userlist_form()
    {
        return '<div class="userlist_form"><form action="?" method="get">'
            .'<input type="hidden" name="menu" value="'.$this->table.'"/>'
            .'<input type="input" name="userlist" value="'.htmlspecialchars($this->userlist).'" size="15" placeholder="'.msg('userstats-list-placeholder').'"/>'
            .'<input type="submit" value="'.msg('userstats-button-list').'" title="'.msg('userstats-button-list-title').'"/></form></div>';
    }
    function view_date_filter()
    {
        $dbs=get_dbs();
        $rows=$dbs->select('select distinct date from userstats_years order by date desc');
        $o='<table class=userstats_date_filter><tr class=years>';
        $o.='<td class="'.($this->date_filter==null ? 'selected' : '').'">'.lnk(msg('userstats-date_filter-all'), array(), array('menu','usort','bot','detail','userlist')).'</td>';
        foreach($rows as $v)
            $o.='<td class="'.($this->date_filter && substr($this->date_filter,0,4)==$v['date'] ? 'selected' : '').'">'.lnk($v['date'], array('date_filter'=>$v['date']), array('menu','usort','bot','detail','userlist')).'</td>';
        if($this->date_filter!=''){
            $o.='</tr></table><table class=userstats_date_filter><tr class=months>';
            $rows=$dbs->select("select distinct date from userstats_months where date like '".(int)substr($this->date_filter,0,4)."%' order by date");
            foreach($rows as $v)
                $o.='<td class="'.($this->date_filter && $this->date_filter==$v['date'] ? 'selected' : '').'">'.lnk(mb_ucfirst(msg('month-long-'.(int)substr($v['date'],4,2))), array('date_filter'=>$v['date']), array('menu','usort','bot','detail','userlist')).'</td>';
        }
        $o.='</tr></table>';
        return $o;
    }

    function view_user($user,$filters=array())
    {
        global $conf;
        $dbs=get_dbs();
        $this->user_stats=$dbs->select1("select * from {$this->table}_tot where user='".$dbs->escape($user)."'");
        $o=$this->user_graph_common();
        $o.='<div class="user_total">';
        if(empty($this->user_stats)){
            $this->cache=false;
            $o.=$this->user_form();
            $o.='<table class="mep" style="clear:left"><tr><td>';
            $o.='<div class="error">'.msg('userstats-user_not_found').'</div>';
            $o.='</td><td>';
            if($this->ip)
                $o.=$this->user_links($user);
            $o.='</td></tr></table></div>';
            return $o;
        }
        $this->user_id=isset($this->user_stats['user_id']) ? $this->user_stats['user_id'] : null;
        $this->user_stats['total_diffs']=$this->total_diffs($this->user_stats);
        $o.='<h1>'.htmlspecialchars($user).'</h1>';
        $o.='<table class="mep" style="clear:left">';
        $o.="<tr><td>";
        $o.='<table class="user_totall">';
        $o.='<tr><td colspan="3" class="title"><h3>'.msg('userstat-total_title-global').'</h3></td></tr>';
        $o.=$this->user_rows(array('total'/*,'reduced'*/));
        $o.='<tr><td colspan="3" class="title"><h4>'.msg('userstat-total_title-edits').'</h4></td></tr>';
        $o.=$this->user_rows(array('edit','article','main','annexe','ns_file','talk','meta','ns_user','other'));
        $o.='<tr><td colspan="3" class="title"><h4>'.msg('userstat-total_title-edit_types').'</h4></td></tr>';
        $o.=$this->user_rows(array('redit','edit_chain','revert'));
        $o.='<tr><td colspan="3" class="title"><h4>'.msg('userstat-total_title-newpages').'</h4></td></tr>';
        $o.=$this->user_rows(array('new','new_main','new_chain_main','new_redir'));
        $o.='</table></td><td><table class="user_totall">';
        $o.='<tr><td colspan="3" class="title"><h4>'.msg('userstat-total_title-time').'</h4></td></tr>';

        if($conf['base_calc']=='month')
            $o.=$this->user_rows(array('months','tot_time2','total_hour','total_month'));
        else
            $o.=$this->user_rows(array('days','months','tot_time2','time_day','total_hour','total_day','total_month'));

        $o.='<tr><td colspan="3" class="title"><h4>'.msg('userstat-total_title-texts').'</h4></td></tr>';
        $o.=$this->user_rows(array('diff_tot', 'diff_article_no_rv', 'diff_small', 'diff_medium', 'diff_big', 'diff', 'tot_size'));
        if(!$this->ip){
            $o.='<tr><td colspan="3" class="title"><h4>'.msg('userstat-total_title-logs').'</h4></td></tr>';
            $o.=$this->user_rows(array('log','log_sysop','log_chain','move','upload','filter','rename','rights','newuser', 'feedback'));
        }
        $admin_stats='';
        if(!$this->ip && @$this->user_stats['log_sysop']>0){
            $admin_stats='<tr><td colspan="3" class="title"><h4>'.msg('userstat-total_title-sysop_logs').'</h4></td></tr>';
            $admin_stats.=$this->user_rows(array('log_sysop','delete','restore','revdelete','block','unblock','protect','unprotect','import'));
        }
        $o.='<tr><td colspan="3" class="title"><h4></h4></td></tr>';
        $o.='</table></td><td>';
        $o.=$this->user_form();
        $o.=$this->user_links($user);
        $o.='<table class="user_totall">';
        $o.=$admin_stats;
        $o.='</table>';
        if(($grp=$this->user_groups($user))!=''){
            $o.='<table class="user_totall usergroups">';
            $o.='<tr><td class="title"><h4>'.msg('userstat-groups-long').'</h4></td></tr>';
            $o.='<tr><td class=usergroups_text>'.$grp.'</td></tr>';
            $o.='</table>';
        }
        $o.='</td><td>';

        $wikis=Wikis::list_all_full();
        $all=self::list_all_wikis($user);
        $o.='<div class=user_wikis>';
        $o.='<table class="user_totall">';
        $o.='<tr><td colspan=2 class="title"><h3>'.msg('userstat-wikis_title-global').'</h3></td></tr>';
        $key='wikis';
        $label=msg("userstat-$key-long")!='' ? msg("userstat-$key-long") : msg("userstat-$key-short");
        $o.="<tr><td class='label'>".$label.'</td>';
        $o.="<td>".fnum($all['global'][$key]).'</td></tr>';
        $key='total';
        $label=msg("userstat-$key-long")!='' ? msg("userstat-$key-long") : msg("userstat-$key-short");
        $o.="<tr><td class='label'>".$label.'</td>';
        $o.="<td>".fnum($all['global'][$key]).'</td></tr>';
        $key='edit';
        $label=msg("userstat-$key-long")!='' ? msg("userstat-$key-long") : msg("userstat-$key-short");
        $o.="<tr><td class='label'>".$label.'</td>';
        $o.="<td>".fnum($all['global'][$key]).'</td></tr>';
        $key='log';
        $label=msg("userstat-$key-long")!='' ? msg("userstat-$key-long") : msg("userstat-$key-short");
        $o.="<tr><td class='label'>".$label.'</td>';
        $o.="<td>".fnum($all['global'][$key]).'</td></tr>';
        $o.='</table>';
        $o.='<table class="user_totall">';
        $o.='<tr style="font-size:75%"><th>'.msg("userstat-wiki-short").'</th>';
        unset($all['global']['wikis']);
        foreach($all['global'] as $k=>$v)
            $o.='<th>'.msg("userstat-$k-short").'</th>';
        $o.='</tr>';
        unset($all['global']);
        foreach($all as $site=>$v){
            $cls=$conf['wiki_key']==$site ? 'bold' : '';
            $o.="<tr><td class='$cls'><small><a href='https://".Wikis::get_site_url($site)."/user/".urlencode($user)."'>".$wikis[$site]['site_host']."</a></small></td>";
            $o.='<td>'.fnum($v['total']).'</td>';
            $o.='<td>'.fnum($v['edit']).'</td>';
            $o.='<td>'.fnum($v['log']).'</td>';
            $o.='</tr>';
        }
        $o.='</table>';
        $o.='</div>';


        $o.='</td></tr>';
        // *** Graph ****
        $o.='</table>';
        if($this->user_stats['months']>1){
            $o.='<table class="mep"><tr>';
            $o.='<td><a href="/gimg.php?type=user&size=big&user='.$user.($this->ip?'&ip=1':'&user_id='.$this->user_id).'"><img src="/gimg.php?type=user&size=medium2&user='.$user.($this->ip?'&ip=1':'&user_id='.$this->user_id).'"/></a></td>';
            if($this->user_stats['edit']>=3)
                $o.='<td>'.$this->user_graph_nstype().'</td>';
            $o.='</tr></table>';
        }elseif($this->user_stats['edit']>=3)
            $o.="<div style='float:right'>".$this->user_graph_nstype()."</div>";
        $this->abbr_group=true;
        $sorts=array('`'.$dbs->escape($this->sort).'` '.$this->order);
        $this->fields['date']['hide']=true;
        $this->fields['user']['hide']=true;
        $this->fields['groups']['hide']=true;
        $o.='<h3>'.msg('userstat-title-year_stats').'</h3>';
        $this->fields['date']['hide']=false;
        $o.=$this->view_list('Y', !$this->ip ? $this->user_id : $this->user, $sorts, $filters);
        $o.='<h3>'.msg('userstat-title-month_stats').'</h3>';
        $this->fields['date']['hide']=false;
        $this->fields['months']['hide']=true;
        $this->fields['total_month']['hide']=true;
        $o.=$this->view_list('M', !$this->ip ? $this->user_id : $this->user, $sorts, $filters);
        if(!$this->ip){
            $this->fields['user']['hide']=false;
            $this->fields['groups']['hide']=false;
            $this->fields['date']['hide']=true;
            $this->fields['total_month']['hide']=false;
            if($this->sort=='date'){
                $old_sort=$this->sort;
                $this->sort='total';
            }
            if(isset($old_sort))//restore for cache key
                $this->sort=$old_sort;
        }
        if(isset($_GET['debug'])){
            require_once('include/update_stats.php');
            ini_set('memory_limit', '1000M');
            $s=UpdateStats::load_stat(date('Ym'), 'users');
            print_r($s[$user]);
        }
        $o.='</div>';
        return $o;
    }
    function total_diffs($v)
    {
        return @$v['diff_big']+@$v['diff_medium']+@$v['diff_small'];
    }
    function user_graph_common()
    {
        return '<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
            <script src="/libs/highcharts/highcharts.js"></script>
            <script src="/libs/highcharts/modules/exporting.js"></script>';
    }
    function user_graph_nstype()
    {
        $o='<div id="graph_nstype" style="height: 300px; width: 300px; margin: 0 0;"></div>
        <script type="text/javascript">
$(function () {
    $("#graph_nstype").highcharts({
        chart: {
            backgroundColor:"#ffffff",
            plotBackgroundColor: null,
            plotBorderWidth: null,
            plotShadow: false,
            spacingBottom: 0,
            spacingTop: 5,
            spacingLeft: 0,
            spacingRight: 0,
            marginBottom: 0,
            marginTop: 16,
            marginLeft: 0,
            marginRight: 0
        },
        title: {
            text: "Éditions"
        },
        tooltip: {
            pointFormat: "{series.name} : <b>{point.percentage:.1f}%</b>"
        },
        plotOptions: {
            pie: {
                allowPointSelect: true,
                cursor: "pointer",
                dataLabels: {
                    enabled: true,
                    format: "{point.name} : {point.percentage:.0f} %",
                    distance :-38,
                    style: {
                        color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || "black"
                    }
                },
                tooltip: {
                    headerFormat : "{point.key} : ",
                    pointFormat : "<b>{point.percentage:.1f} %</b>"
                }
            }
        },
        credits: {
            position: {
                align: "left",
                verticalAlign: "bottom",
                x: -100,
                y: -10
            }
        },
        series: [{
            type: "pie",
            name: "Éditions",
            animation:false,
            data: [
            ';
        $cols=array(
            'talk'=>'#d8c24b',
            'meta'=>'#7876c3',
            'ns_user'=>'#41cbe0',
            'other'=>'#71abd4',
            'annexe'=>'#64954e',
            'ns_file'=>'#f4954e',
            'main'=>'#91d972',
            );
        foreach($cols as $k=>$color){
            $p=$this->user_stats['edit']>0 ? @round(100*$this->user_stats[$k]/$this->user_stats['edit'], 1) : 0;
            $o.='{name:"'.msg("userstat-piechart-$k").'", y:'.$p
                .($p<3?', dataLabels: {distance:20,enabled:false}':'')
                .',color:"'.$color.'"'
                ."},\n";
        }
        $o.=']
        }]
    });
});
        </script>';
        return $o;
    }

    function user_links($user)
    {
        global $conf;
        $u=$user;
        $user=mwtools::encode_user($user);
        $o='<div class="userlinks"><h3>'.msg('user-links_title').'</h3>';
        if($this->ip && preg_match('/^(\d{1,3}\.){3}\d{1,3}$/i',$user))
            $o.='<ul><li><a href="/plage-ip?ip='.$user.'">Plage de l\'IP '.htmlspecialchars($u).'</a></li></ul>';
        $o.='<h4>Wiki</h4><ul>';
        $img="<img src='imgi/logos/Wikimedia-logo.svg' height='14'/>";
        $special=urlencode(str_replace(' ', '_', mwns::get()->ns_string(NS_SPECIAL)));
        $o.="<li>$img<a href=\"".$conf['link_page'].$special.":Contributions/$user\">".msg('user-link_label-contributions')."</a></li>";
        $o.="<li>$img<a href=\"".$conf['link_page'].$special.":Log/$user\">".msg('user-link_label-logs')."</a></li>";
        if(!$this->ip){
            $o.="<li>$img<a href=\"".$conf['link_page'].urlencode(str_replace(' ', '_', mwns::get()->ns_string(NS_USER))).":$user\">".msg('user-link_label-userpage')."</a></li>";
            $o.="<li>$img<a href=\"".$conf['link_page'].$special.":CentralAuth/$user\">".msg('user-link_label-globalaccount')."</a></li>";
        }
        $o.='</ul>';
        if(isset($conf['wiki']['site_group'])){
            $o.='<h4>Tool Labs</h4><ul>';
            $o.="<li>$img<a href=\"https://tools.wmflabs.org/supercount/index.php?project=".$conf['wiki']['site_host']."&user=".str_replace('_','+',$user)."\">".msg('user-link_label-editcounter')."</a></li>";
            if(!$this->ip && isset($conf['wiki']['site_language']) && isset($conf['wiki']['site_group'])){
                $o.="<li>$img<a href=\"https://tools.wmflabs.org/xtools/pages/index.php?lang=".$conf['wiki']['site_language']."&wiki=".$conf['wiki']['site_group']."&namespace=0&redirects=noredirects&user=$user\">".msg('user-link_label-articlescreated')."</a></li>";
            }
            $o.='</ul>';
        }
        $o.='</div>';
        return $o;
    }
    function view_list($date_type, $user='', $sorts, $filters, $where='', $near_user='')
    {
        global $conf;
        $dbs=get_dbs();
        if($where=='')
            $where='1';
        $table=$this->multi_table_name($date_type);
        if(is_array($user))
            $where.=" and `$table`.user in (".implode(',',$user).')';
        elseif($user!=''){
            if(!$this->ip)
                $where .=" and `$table`.user_id='".$dbs->escape($user)."'";//now user_id
            else
                $where .=" and `$table`.user='".$dbs->escape($user)."'";
        }
        $this->totpages=0;
        if($near_user==''){
            $start=($this->page-1)*$this->limit;
            if(is_array($user))
                $total=count($user);
            elseif($user==''){
                if($this->list_use_real_count || !$conf['multi']){
                    $total=$dbs->selectcol($table, 'count(*)', $where);
                }else{
                    if($this->date_filter!=''){
                        $date=Dates::get($this->date_filter);// get cached date stats TODO check why real count have little diff
                        $total=$this->ip ? $date['ip'] : $date['users'];
                    }else{
                        $dbg=get_dbg();
                        $g_stats=$dbg->select1("select users,users_edit,total_user from sites_stats where site_global_key='".$dbg->escape($conf['wiki_key'])."'");
                        $total=$g_stats['users'];
                    }
                }
                $this->totpages=ceil($total/$this->limit);
                if($this->max_pages!==false && $this->totpages>$this->max_pages)
                    $this->totpages=$this->max_pages;
            }
        }
        if($near_user==''){
            $q="select /*userstats list*/ `$table`.* from `$table`";
            /*if($date_type!='T')
                $q.=' left join '.$this->multi_table_name('T')." t on `$table`.user_id=t.user_id";*/
            $rows=$dbs->select("$q where $where order by ".implode(',', $sorts).($user=='' ? " limit $start,".$this->limit : ""));
        }else{
            $where.=" and user!='".$dbs->escape($near_user)."'";
            $col="`".$dbs->escape($this->sort)."`";
            $rows=array_reverse($dbs->select("select * from $table where $where and $col<=".(int)$this->user_stats[$this->sort]." order by $col desc, total desc, edit desc limit 10"));
            $rows[]=$this->user_stats;
            $rows=array_merge($rows,$dbs->select("select * from $table where $where and $col>".(int)$this->user_stats[$this->sort]." order by $col, total, edit limit 10"));
        }
        $o='';
        if(is_array($user) && $this->userlist_page!='')
            $o.='<h3><a href="'.$conf['link_page'].htmlspecialchars(str_replace(' ','_',$this->userlist_page)).'">'.htmlspecialchars($this->userlist_page).'</a></h3>';
        if(!$this->sort_toggled && $this->sort!='' && ($pos=strpos(@$this->fields[$this->sort]['class'],' '))!==false){
            if(($cls='.'.substr($this->fields[$this->sort]['class'],$pos+1))!=''){
                $o.="<script type='text/javascript'>td('$cls');</script>";
                $this->sort_toggled=true;
            }
        }
        if($this->user==''){
            $o.="<div class='userstats_count'><strong>";
            if(!$this->ip || $this->date_filter!='')
                $o.=$this->ip ? msg('userstats-count-ip', fnum($total)) : msg('userstats-count-users', fnum($total));
            $o.='</strong></div>';
        }
        $o.='<table class="userstatsl">';
        $o.="<tr><td colspan=30 class=pages_row>";
        $o.='<table class="userstats_filters"><tr>';
        foreach($filters as $f)
            $o.='<td><div class="userstats_filter">'.$f.'</div></td>';
        if($this->user=='')
            $o.='<td>'.$this->pages_nav().'</td>';
        $o.='</tr></table>';
        $o.="</td></tr>";
        $span=2;
        if($this->user=='')
            $span+=$this->ip ? 1 : 2;
        if($conf['base_calc']=='month'){//TODO adapt colspan
            $this->fields['days']['hide']=true;
            $this->fields['time_day']['hide']=true;
            $this->fields['total_day']['hide']=true;
            $this->fields['tot_time2']['class']='utime';
        }
        $head0=preg_replace('/\s+/',' ','<tr class="head0">
            <td colspan="'.$span.'"></td>
            <td colspan="2">'.msg('userstat-table_title-edits').' <a class="tl js" href="javascript:td(\'.tedit\')">+</a></td>
            <td colspan="10" class="uedit tedit"></td>
            <td colspan="2">'.msg('userstat-table_title-newpages').' <a class="tl js" href="javascript:td(\'.tnew\')">+</a></td>
            <td colspan="2" class="unew tnew"></td>
            <td colspan="1">'.msg('userstat-table_title-texts').' <a class="tl js" href="javascript:td(\'.ttext\')">+</a></td>
            <td colspan="6" class="utext ttext"></td>
            '.(!$this->ip?'<td colspan="1">'.msg('userstat-table_title-logs').' <a class="tl js" href="javascript:td(\'.tlog\')">+</a></td>
            <td colspan="16" class="ulog tlog"></td>':'').'
            <td colspan="2">'.msg('userstat-table_title-time').' <a class="tl js" href="javascript:td(\'.ttime\')">+</a></td>
            <td colspan="7" class="utime ttime"></td>
            </tr>');
        $head='<tr class="head">';
        if($this->user=='')
            $head.='<td></td>';
        foreach($this->fields as $k=>$v){
            if(@$v['hide'])
                continue;
            if($this->ip && ($k=='groups'||$v['class']=='ulog'||$v['class']=='ulog tlog'))
                continue;
            if($this->user=='')
                $sort_params=array('usort'=>$k,'bot'=>$this->bot,'detail'=>$this->detail);
            else
                $sort_params=array('user'=>$this->user,'usort'=>$k);
            $class=@$v['class'];
            if($k==$this->sort){
                $sort_params['order']= $this->order=='asc' ? 'desc' : 'asc';
                $class.=$class==''?'sel':' sel';
            }
            $head.=$class!=''?"<td class='$class'>":'<td>';
            $info=str_replace('<br/>',' ', msg("userstat-$k-text"));
            if(@$v['sort']!='' && ($k!=$this->sort || $this->sort=='diff' || $this->sort=='diff_article_no_rv'))//remove asc link except for diff
                $head.=lnk(msg("userstat-$k-short"), $sort_params,array('menu','page','userlist','date_filter'),$info);
            else
                $head.='<span title="'.$info.'">'.msg("userstat-$k-short").'</span>';
            $head.='</td>';
        }
        $head.='</tr>';
        $lusers=$this->lusers;
        $pos=($this->page-1)*$this->limit;
        foreach($rows as $k=>$v){
            $v['total_diffs']=$this->total_diffs($v);
            unset($lusers[$v['user']]);
            if($k%50==0)
                $o.=$head0.$head;
            $pos++;
            $style=$near_user!='' && isset($v['user']) && $v['user']==$near_user ? " style='font-weight:bold'" : "";
            $o.="<tr onclick='hl(this)'$style>";
            if($this->user=='')
                $o.="<td>$pos</td>";
            foreach($this->fields as $k=>$f){
                if(@$f['hide'])
                    continue;
                if($this->ip && ($k=='groups'||$f['class']=='ulog'||$f['class']=='ulog tlog'))
                    continue;
                $val=$this->format_val($k,$v,$this->percent);
                $o.='<td'.(@$f['class']!=''?" class='$f[class]'":'').'>'.$val.'</td>';
            }
            $o.='</tr>'."\n";;
        }
        if($this->user==''){
            $o.="<tr><td colspan=20 class=pages_row>";
            $o.='<table class="userstats_filters"><tr>';
            $o.='<td>'.$this->pages_nav().'</td>';
            $o.="</td></tr></table></tr>";
        }
        $o.='</table>';
        if(!empty($lusers)){
            foreach($lusers as $k=>$user){
                $user=htmlspecialchars($user);
                $lusers[$k]='<a href="'.$conf['link_page'].urlencode(mwns::get()->ns_string(NS_SPECIAL)).":Contributions/$user\">$user</a>";
            }
            $o.=msg('userstats-list-not_found').' ('.count($lusers).') : '.implode(', ',$lusers);
        }
        return $o;
    }
    function pages_nav()
    {
        $o="<span class='userstats_pages'>";
        if($this->page>1){
            $o.="<span class='page_start'>".lnk("<img src='imgi/icons/start.png'/>",array('page'=>1),array('menu','usort','bot','detail','userlist','date_filter')).'</span>';
            $o.="<span class='page_prev'>".lnk("<img src='imgi/icons/prev.png'/>",array('page'=>$this->page-1),array('menu','usort','bot','detail','userlist','date_filter')).'</span>';
        }
        $o.=msg('navigation-page')." {$this->page}";
        if($this->page<$this->totpages){
            $o.="<span class='page_next'>".lnk("<img src='imgi/icons/next.png'/>",array('page'=>$this->page+1),array('menu','usort','bot','detail','userlist','date_filter')).'</span>';
            if($this->max_pages!==false)
                $o.="<span class='page_end'>".lnk("<img src='imgi/icons/end.png'/>",array('page'=>$this->totpages),array('menu','usort','bot','detail','userlist','date_filter')).'</span>';
        }
        $o.='</span>';
        return $o;
    }

    function multi_table_name($date_type)
    {
        switch($date_type){
            case 'T': return $this->table.'_tot';
            case 'Y': return $this->table.'_years';
            case 'M': return $this->table.'_months';
        }
        return false;
    }
    function recalc_user()
    {
        return;
        $us=new UpdateStats();
        $us->update_user($this->user);
        $this->save_user($this->user,$us->dates);
    }
    function user_rows($keys)
    {
        $o='';
        foreach($keys as $k){
            if(@$this->fields[$k]['hide'] || (@$this->fields[$k]['optional'] && $this->user_stats[$k]==0))
                continue;
            $o.=$this->user_row($k);
        }
        return $o;
    }
    function user_row($key)
    {
        $f=$this->fields[$key];
        $class=@$f['class']!='' ? " class='$f[class]'" : '';
        $label=msg("userstat-$key-long");
        if($label=='')
            $label=msg("userstat-$key-short");
        if(msg("userstat-$key-text")!='' && msg("userstat-$key-text")!=$label)
            $label='<span title="'.msg("userstat-$key-text").'">'.$label.'</span>';
        $o="<tr><td class='label'>".$label.'</td>';
        $val=$this->format_val($key, $this->user_stats);
        $o.="<td $class>".$val.'</td>';
        if(@$f['percent']!='')
            $o.='<td class="up">'.@round(100*$this->user_stats[$key]/$this->user_stats[$f['percent']]).'%</td>';
        else
            $o.='<td class="up"></td>';
        $o.='</tr>';
        return $o;
    }

    static function user_url($user)
    {
        return '/'.msg('urlpath-user').'/'.mwtools::encode_user($user);
    }
    static function ip_url($user)
    {
        return '/'.msg('urlpath-ip').'/'.mwtools::encode_user($user);
    }

    function format_val($k, $v, $percent=false)
    {
        $f=$this->fields[$k];
        $val='';
        switch($k){
            case 'user':
                if($this->ip)
                    $val='<a href="'.self::ip_url($v[$k]).'">'
                        .htmlspecialchars(mb_strlen($v[$k])<=30 ? $v[$k] : mb_substr($v[$k],0,28).'…')
                        .'</a>';
                else
                    $val='<a href="'.self::user_url($v[$k]).'">'
                        .htmlspecialchars(mb_strlen($v[$k])<=30 ? $v[$k] : mb_substr($v[$k],0,28).'…')
                        .'</a>';
                break;
            case 'date':
                $val=Dates::format($v[$k]);
                break;
            case 'groups':
                $val=$this->user_groups($v['user']);
                break;
            case 'tot_time':
            case 'tot_time2':
            case 'tot_time3':
                $val=format_hour($v[$k]);
                break;
            case 'time_day':
                $val=@round(($v['tot_time2']/$v['days'])/300)*300;
                $val=format_hour($val);
                break;
            case 'total_hour':
                $val=@round($v['total']/($v['tot_time2']/3600));
                break;
            case 'total_day':
                $val=@round($v['total']/$v['days']);
                break;
            case 'total_month':
                $val=@round($v['total']/$v['months']);
                break;
            case 'diff':
            case 'diff_article_no_rv':
                $val=format_sizei(@$v[$k]);
                if($val>0)
                    $val='+'.$val;
                break;
            default :
                if(@$f['func']!='' && (is_array($f['func'])||function_exists($f['func'])))
                    $val=call_user_func($f['func'], @$v[$k]);
                elseif(is_numeric(@$v[$k]))
                    $val=fnum($v[$k]);
                else
                    $val=@$v[$k];
        }
        if($percent && @$f['percent']!=''){
            $p=@round(100*$v[$k]/$v[$f['percent']]).'%';
            $val.="<span class='up'>$p</span>";
        }
        return $val;
    }

    function user_groups($user)
    {
        if(!isset($this->groups[$user]))
            return '';
        $val=array();
        if(!$this->abbr_group)
            foreach($this->groups[$user] as $gr)
                $val[]=$this->group_name($gr);
        else
            foreach($this->groups[$user] as $gr)
                $val[]=$this->group_abbr($gr);
        sort($val);
        return implode($this->abbr_group ?'':', ',$val);
    }

    function cache_key()
    {
        return 'userstats:'.$this->table.':'.$this->date_filter.':'.$this->user.':'.$this->userlist.':'.$this->page.':'.$this->bot.':'.$this->sort.':'.$this->order.':'.$this->detail.':'.@$_GET['graphs_details'];
    }
    function valid_cache_date($cache_date)
    {
        $Cache=get_cache();
        if($date=$Cache->get(cache_key($this->lastupdate_key)))
            return strtotime($cache_date)>=strtotime($date);
        return true;
    }
    function set_last_update()
    {
        $Cache=get_cache();
        $Cache->set(cache_key($this->lastupdate_key), gmdate('YmdHis'));
    }
    static function group_name($group)
    {
        if(msg_exists("group-$group"))
            return msg("group-$group");
        return $group;
    }
    static function group_abbr($group)
    {
        if(msg_exists("group_abbr-$group")){
            $abbr=msg("group_abbr-$group");
            return '<span title="('.$abbr.') '.self::group_name($group).'">'.$abbr.'</span>';
        }
        return '';
    }

    function javascript()
    {
        return preg_replace("/\s+/",' ','<script type="text/javascript">
        function get_css_rule(selector)
        {
            var rules = new Array();
            if (document.styleSheets[0].cssRules)
                rules = document.styleSheets[0].cssRules;
            else if (document.styleSheets[0].rules)
                rules = document.styleSheets[0].rules;
            for (i in rules)
                if (rules[i].selectorText == ".userstatsl "+selector)
                    return rules[i];
            return false;
        }
        function td(selector,block)
        {
            if(!block)
                if(navigator.appName!="Microsoft Internet Explorer")
                    block="table-cell";
                else
                    block="block";
            if(rule=get_css_rule(selector)){
                if(rule.style.display!="none")
                    rule.style.display="none";
                else
                    rule.style.display=block;
            }
        }
        function hl(tr)
        {
            if(tr.style.fontWeight=="bold")
                tr.style.fontWeight="normal";
            else
                tr.style.fontWeight="bold";
        }
        </script>');
    }
    function format_edit($v)
    {
        if($this->detail)
            return fnum($v);
        else
            return format_size($v);
    }
    function update_date($date)
    {
        if(strlen($date)==6)
            $this->update($date, false);
        else
            $this->sum($date);
    }
    function update($date='',$sum=true)
    {
        $dbs=get_dbs();
        $this->dbs2=clone $dbs;
        $this->dbs2->open();
        $months=false;
        if($date==''){
            $years=UpdateStats::subdirs();
        }elseif(strlen($date)==4){
            $years=array($date);
        }elseif(strlen($date)==6){
            $years=array(substr($date,0,4));
            $months=array(substr($date,4,2));
        }elseif(preg_match('/\d{4},\d{4}/',$date)){
            $years=explode(',',$date);
        }else{
            echo " Invalid date\n";
            var_dump($date);
            return false;
        }
        $sub_stat=false;
        if(UpdateStats::$separate_ip)
            $sub_stat=$this->ip ? 'ip' : 'user' ;
        foreach($years as $y){
            $ms= $months===false ? UpdateStats::subdirs($y) : $months;
            foreach($ms as $m){
                $date=$y.$m;
                echo $date;
                $users=UpdateStats::load_stat($date,'users',$sub_stat);
                echo ' '.count($users).' users';
                $this->save($users,$date);
                echo "\n";
            }
            if($sum)
                $this->sum($y);
        }
        if($sum)
            $this->sum(0);
        $this->dbs2->close();
        unset($this->dbs2);
    }
    function save_user($user,$stats)
    {
        $dbs=get_dbs();
        $this->last_update=gmdate('YmdHis');
        foreach($stats as $date=>$s){
            $us=$this->stat_row($user,$s['users'][$user],$date);
            print_r($us);echo '<br>';
        }
    }
    function save($users, $date=false)
    {
        $table=$this->multi_table_name(Dates::type($date));
        $this->dbs2->query('START TRANSACTION');
        $this->last_update=gmdate('YmdHis');
        $this->threshold_stats=array();
        $i=0;
        $users_edit=0;
        foreach($users as $k=>$v){
            if(!isset($this->types[$v['type']]))
                continue;
            if(@$v['edit']>=1)
                $users_edit++;
            $us=$this->stat_row($k,$v,$date);
            $this->update_threshold_stats($us);
            $this->dbs2->insert($table,$us,false,true);
            if(++$i%5000==0){
                $this->dbs2->query('COMMIT');
                $this->dbs2->query('START TRANSACTION');
            }
        }
        $this->dbs2->query('COMMIT');
        $this->dbs2->query("delete from $table where date='$date' and last_update<'".$this->last_update."'");
        $this->save_threshold_stats($date);
        $this->save_date($date, count($users), $users_edit);
    }
    function stat_row($user,$v,$date=false)
    {
        $us=array(
            'user'=>$user,
            'user_id'=>(int)@$v['id'],
            'user_type'=>strtoupper(substr($v['type'],0,1)),
            'date'=>$date,
            'year'=>(int)substr($date,0,4),
            'month'=>(int)substr($date,4,2),
            'total'=>(int)@$v['total'],
            'reduced'=>round(@$v['total']-0.9*@$v['redit']-0.9*@$v['edit_chain']-0.9*@$v['log_chain']),
            'edit'=>(int)@$v['edit'],
            'main'=>(int)@$v['nscateg']['article'],
            'talk'=>(int)@$v['nscateg']['talk'],
            'meta'=>(int)@$v['nscateg']['meta']+@$v['nscateg']['help'],
            'annexe'=>(int)@$v['nscateg']['annexe']+@$v['nscateg']['mediawiki'],
            'ns_user'=>(int)@$v['nscateg']['user'],
            'ns_file'=>(int)@$v['nscateg']['file'],
            'other'=>(int)@$v['nscateg']['other']+@$v['nscateg']['special'],
            'article'=>(int)@$v['edit_article'],
            'redit'=>(int)@$v['redit'],
            'edit_chain'=>(int)@$v['edit_chain'],
            'revert'=>(int)@$v['revert'],
            'new'=>(int)@$v['new']['total'],
            'new_main'=>(int)@$v['new']['article'],
            'new_redir'=>(int)@$v['new']['redirect'],
            'new_chain'=>(int)@$v['new_chain']['total'],
            'new_chain_main'=>(int)@$v['new_chain']['article'],
            //'hours'=>,
            'days'=>@$v['days'],
            'months'=>@$v['months'],
            'tot_time'=>@$v['tot_time'],
            'tot_time2'=>@$v['tot_time2'],
            'tot_time3'=>@$v['tot_time3'],
            'diff'=>(int)@$v['diff'],
            'diff_article_no_rv'=>(int)(@$v['diff_ns']['article']-@$v['diff_rv_article']),
            'diff_tot'=>(int)@$v['diff_tot'],
            'diff_small'=>(int)@$v['diffs']['small'],
            'diff_medium'=>(int)@$v['diffs']['medium'],
            'diff_big'=>(int)@$v['diffs']['big'],
            'tot_size'=>(int)@$v['tot_size'],
            'last_update'=>$this->last_update,
            'move'=>isset($v['logs']['move']) ? @$v['logs']['move']['move']+@$v['logs']['move']['move_redir'] : null,
            'filter'=>@$v['logs']['abusefilter']['modify'],
            'protect'=>isset($v['logs']['protect']) ? @$v['logs']['protect']['protect']+@$v['logs']['protect']['modify'] : null,
            'unprotect'=>@$v['logs']['protect']['unprotect'],
            'block'=>isset($v['logs']['block']) ? @$v['logs']['block']['block']+@$v['logs']['block']['reblock'] : null,
            'unblock'=>@$v['logs']['block']['unblock'],
            'delete'=>@$v['logs']['delete']['delete'],
            'restore'=>@$v['logs']['delete']['restore'],
            'revdelete'=>@$v['logs']['delete']['revision'],
            'upload'=>isset($v['logs']['upload']) ? @$v['logs']['upload']['upload']+@$v['logs']['upload']['overwrite'] : null,
            'rename'=>@$v['logs']['renameuser']['renameuser'],
            'rights'=>@$v['logs']['rights']['rights'],
            'import'=>@$v['logs']['import']['import']+@$v['logs']['import']['interwiki'],
            'newuser'=>@$v['logs']['newusers']['create2'],
            'feedback'=>isset($v['logs']['articlefeedbackv5']) ? array_sum($v['logs']['articlefeedbackv5']) : null,
            );
        foreach($this->fields as $k=>$f)
            if(!isset($us[$k])){
                if(isset($v[$k]))
                    $us[$k] = (int)$v[$k];
                elseif($f['sum'])
                    $us[$k] = 0;
            }
        return $us;
    }
    function save_sum($rows,$date)
    {
        $type=Dates::type($date);
        $table=$this->multi_table_name($type);
        $this->dbs2->query('START TRANSACTION');
        foreach($rows as $v){
            $v['date']=$date;
            $v['last_update']=$this->last_update;
            $this->dbs2->insert($table,$v,false,true);
            $this->sum_rows++;
            if($v['edit']>=1)
                $this->sum_users_edit++;
            $this->update_threshold_stats($v);
        }
        $this->dbs2->query('COMMIT');
    }
    function update_threshold_stats($v)
    {
        if($v['user_type']=='U')
            $type='users';
        elseif($v['user_type']=='B')
            $type='bots';
        else
            $type='ip';
        foreach($this->thresholds as $stat=>$limits)
            foreach($limits as $limit)
                if(isset($v[$stat]) && $v[$stat]>=$limit){
                    @$this->threshold_stats[$type][$stat][$limit]['users']++;
                    @$this->threshold_stats[$type][$stat][$limit]['edits']+=isset($v['edit']) ? $v['edit'] : 0;
                    @$this->threshold_stats[$type][$stat][$limit]['tot_time2']+=isset($v['tot_time2']) ? $v['tot_time2'] : 0;
                }
    }

    function sum($year=false)
    {
        $dbs=get_dbs();
        $close2=false;
        if(!isset($this->dbs2)){
            $this->dbs2=clone $dbs;
            $this->dbs2->open();
            $close2=true;
        }
        $dbs->query('SET SESSION net_read_timeout=1800');
        $dbs->query('SET SESSION net_write_timeout=1800');
        $this->dbs2->query('SET SESSION net_read_timeout=1800');
        $this->dbs2->query('SET SESSION net_write_timeout=1800');
        echo "Sum users $year\n";
        $year=(int)$year;
        $this->reset();
        $fields=array('max(user) user', 'user_id', 'max(user_type) user_type');
        foreach($this->fields as $k=>$v)
            if($v['sum'])
                $fields[]="sum(`$k`) `$k`";
        $where='1';
        if($year==0){
            $date_type='Y';
            $table_dest=$this->multi_table_name('T');
        }else{
            $date_type='M';
            if(!$this->ip)
                $where.=" and year=".(int)$year;
            else
                $where.=" and date like '$year%'";
            $table_dest=$this->multi_table_name('Y');
        }
        $table_src=$this->multi_table_name($date_type);

        $fast_count=$dbs->fast_count("select distinct user_id from `$table_src` where $where");
        echo "fast count $fast_count users\n";

        if(!$this->use_sum_userids_chunks || $this->ip || $fast_count<=$this->sum_chunk_min_users)
            $this->sum_all_rows($table_src, $table_dest, $year, $fields, $where);
        else
            $this->sum_userids_chunks($table_src, $table_dest, $year, $fields, $where);
        if($year!=0)
            $this->dbs2->query("delete from $table_dest where date='$year' and last_update<'{$this->last_update}'");
        else
            $this->dbs2->query("delete from $table_dest where last_update<'{$this->last_update}'");
        echo "userstats deleted : ".$this->dbs2->affected_rows()."\n";
        if($close2){
            $this->dbs2->close();
            unset($this->dbs2);
        }
        $this->save_threshold_stats($year);

        $this->save_date($year, $this->sum_rows, $this->sum_users_edit);
        if($this->cache && $year==0)
            $this->set_last_update();
        if($year==0)
            $this->save_global_stats($this->sum_rows, $this->sum_users_edit);
    }

    function sum_all_rows($table, $table_dest, $year, $fields, $where)
    {
        $dbs=get_dbs();
        $group = $this->ip ? 'user' : 'user_id';
        do{
            $this->sum_rows=0;
            $this->sum_users_edit=0;
            $this->threshold_stats=array();
            $this->last_update=gmdate('YmdHis');
            $dbs->select_walk_block("select SQL_NO_CACHE /*sum users $year*/ ".implode(',',$fields)." from $table where $where group by $group", array($this,'save_sum'),5000,$year);
            echo " ".$this->sum_rows." users";
            $err=$dbs->error_no();
            if($err==0){
                $dbs->query('COMMIT');
                $err=$dbs->error_no();
            }
            echo ' mem:'.round(memory_get_usage(true)/1048576).'/'.round(memory_get_peak_usage(true)/1048576).'Mb';
            echo "\n";
        }while($err!=0);
    }

    function sum_userids_chunks($table, $table_dest, $year, $fields, $where)
    {
        $dbs=get_dbs();
        do{
            $this->last_update=gmdate('YmdHis');
            $total_sum_rows=0;
            $total_sum_users_edit=0;
            $this->threshold_stats=array();
            $chunk=0;
            do{
                $this->sum_rows=0;
                $this->sum_users_edit=0;
                $w="$where and user_id between ".($chunk*$this->sum_user_ids_chunk_size+1)." and ".(($chunk+1)*$this->sum_user_ids_chunk_size);
                $dbs->select_walk_block("select SQL_NO_CACHE /*sum users $year*/ ".implode(',',$fields)." from $table where $w group by user_id", array($this,'save_sum'), 5000, $year);
                echo "chunk $chunk : {$this->sum_rows} users\n";
                $total_sum_rows+=$this->sum_rows;
                $total_sum_users_edit+=$this->sum_users_edit;
                $err=$dbs->error_no();
                if($err==0){
                    $dbs->query('COMMIT');
                    $err=$dbs->error_no();
                }
                $chunk++;
            }while($err==0 && $this->sum_rows>0);
            echo ' mem:'.round(memory_get_usage(true)/1048576).'/'.round(memory_get_peak_usage(true)/1048576).'Mb';
            echo "\n";
        }while($err!=0);
        $this->sum_rows=$total_sum_rows;
        $this->sum_users_edit=$total_sum_users_edit;
        echo "$total_sum_rows users $total_sum_users_edit users_edit\n";
    }

    function save_date($date, $users, $users_edit)
    {
        $data=array('date'=>$date);
        if($this->ip)
            $data['ip']=$users;
        else{
            $data['users']=$users;
            $data['users_edit']=$users_edit;
        }
        Dates::update($data, false);
    }
    function save_global_stats($users, $users_edit)
    {
        global $conf;
        if($conf['wiki_key']=='' || !$conf['multi'])
            return;
        $db=get_dbg();
        $db->update('sites_stats', 'site_global_key', $conf['wiki_key'], array('users'=>$users, 'users_edit'=>$users_edit));
    }
    function save_threshold_stats($date)
    {
        global $conf;
        if(!$conf['multi'])
            return;
        require_once('include/wikis.php');
        Wikis::update_global_stats(function($data) use($date){
            if($date===0)
                $data['user_thresholds']['total']=$this->threshold_stats;
            elseif(strlen($date)==4)
                $data['user_thresholds']['years'][$date]=$this->threshold_stats;
            else
                $data['user_thresholds']['months'][$date]=$this->threshold_stats;
            return $data;
        });
    }

    function user_months($user, $user_id=null)
    {
        $dbs=get_dbs();
        $table=$this->multi_table_name('M');
        if($user_id)
            $rows=$dbs->select("select * from $table where user_id='".$dbs->escape($user_id)."' order by date");
        else{
            if($this->ip)
                $rows=$dbs->select("select * from $table where user='".$dbs->escape($user)."' order by date");
            else{
                //compat with old links without id
                $table2=$this->multi_table_name('T');
                $rows=$dbs->select("select user_id from $table2 where user='".$dbs->escape($user)."'");
                if(!empty($rows))
                    $rows=$dbs->select("select * from $table where user_id='".$dbs->escape($rows[0]['user_id'])."' order by date");
            }
        }
        return $rows;
    }

    function months_graphs()
    {
        $details=isset($_GET['graphs_details']) && $_GET['graphs_details'];
        if(!$details)
            $this->months_graphs_stats=$this->months_graphs_stats_short;
        $height=100;
        if(!$this->months_graphs_data_load())
            return false;
        $last_m=date('Ym', strtotime("-1 month"));
        foreach($this->months_graphs_stats as $stat){
            foreach($this->months_graphs_dates[$stat] as $col=>$dates)
                $pie[$stat][$col]=isset($dates[$last_m]) ? $dates[$last_m] : 0;
            foreach($this->months_graphs_dates_cumul[$stat] as $col=>$dates)
                $pie_cumul[$stat][$col]=isset($dates[$last_m]) ? $dates[$last_m] : 0;
        }
        foreach($this->months_graphs_stats as $stat)
            $this->months_graphs_dates[$stat]=$this->data_average_array($this->months_graphs_dates[$stat], 1);

        for($i=1;$i<=6;$i++)
            $cols[]=$i;
        $o='<div class=userstats_months_graphs>';
        $o.="<table class=userstats_months_graphs_table>";
        $o.="<tr><td colspan=20 class=userstats_months_graph_legend>";
        $o.=$this->months_graphs_legend();
        $o.="</td></tr>";
        foreach($this->months_graphs_stats as $stat){
            $o.="<tr><td class=userstats_months_graph_name>";
            $o.=msg("userstats-months_graphs-$stat");
            if(!isset($header)){
                $header=true;
                $o.="</td><td style='text-align:center'>".date('m/Y', strtotime("-1 month"))."</td><td style='text-align:center'>".msg('userstats-months_graphs-total');
                if($details)
                    $o.="</td><td style='text-align:center'>".msg('userstats-months_graphs-history');
            }
            $o.="</td></tr>";
            $o.="<tr><td>";
            $o.=self::svg_graph($this->months_graphs_dates[$stat], $cols, $height, 3, false);
            $o.="</td><td>";
            $o.=$this->pie_graph($pie[$stat], $height);
            $o.="</td><td>";
            $o.=$this->pie_graph($pie_cumul[$stat], $height);
            $o.="</td><td>";
            if($details)
                $o.=self::svg_graph($this->months_graphs_dates_cumul[$stat], $cols, $height, 2, false);
            $o.="</td></tr>";
        }
        if(!$details)
            $o.="<tr><td colspan=20><a href='/utilisateurs?graphs_details=1'>".msg('userstats-months_graphs-more')."</a></td></tr>";
        $o.="</table>";
        $o.='</div>';
        return $o;
    }
    function months_graphs_legend()
    {
        $cols=array(1=>'≤ 1 '.msg('month'), 2=>'≤ 12 '.msg('months'), 3=>'≤ 3 '.msg('years'), 4=>'≤ 6 '.msg('years'), 5=>'≤ 9 '.msg('years'), 6=>'9+ '.msg('years'));
        $o='<div class=months_graphs_legend>'.msg('userstats-months_graphs-legend').' : ';
        foreach($cols as $k=>$v)
            $o.="<div class=legend_item><div class='legend_color legend_color$k'>&nbsp;</div>$v</div>";
        return $o."</div>";
    }

    function data_average_array($data, $average=3)
    {
        foreach($data as $k=>$v)
            $data[$k]=$this->data_average($v, $average);
        return $data;
    }
    function data_average($data, $average=3)
    {
        $lasts=array();
        $res=array();
        foreach($data as $k=>$v){
            $lasts[]=$v;
            if(count($lasts)<$average)
                continue;
            $res[$k]=round(array_sum($lasts)/count($lasts));
            array_shift($lasts);
        }
        return $res;
    }

    function months_graphs_data_update()
    {
        $this->months_graphs_data();
        $data=array('months'=>$this->months_graphs_dates, 'months_cumul'=>$this->months_graphs_dates_cumul);
        $this->months_graphs_dates=false;
        $this->months_graphs_dates_cumul=false;
        $data=serialize($data);
        file_put_contents($this->months_graphs_file(), $data);
    }
    function months_graphs_data_exists()
    {
        return file_exists($this->months_graphs_file());
    }
    function months_graphs_data_load()
    {
        if(!$this->months_graphs_data_exists())
            return false;
        $data=unserialize(file_get_contents($this->months_graphs_file()));
        $this->months_graphs_dates=$data['months'];
        $this->months_graphs_dates_cumul=$data['months_cumul'];
        $data='';
        return !empty($this->months_graphs_dates);
    }
    function months_graphs_file()
    {
        return $this->data_path.'/global_users_months_data';
    }
    function months_graphs_data()
    {
        $dbs=get_dbs();
        $this->last_month_date='';
        $this->last_count_date='';
        $this->months_users=array();
        $this->cur_month_users=array();
        $this->last_month_users=array();
        $this->months_graphs_dates=array();
        $this->months_graphs_dates_cumul=array();
        $this->months_graphs_dates_quit=array();
        echo "months_graphs_data ";
        $cols=array('date', 'user_id', 'user_type');
        foreach($this->months_graphs_stats as $col)
            if($col!='users')
                $cols[]="`$col`";
        $cols=implode(',', $cols);
        $start=$dbs->selectcol("select min(date) from userstats_months");
        if($start=='')
            return false;
        $dbs->query('SET SESSION net_read_timeout=900');
        $dbs->query('SET SESSION net_write_timeout=900');
        $max_year=date('Y');
        for($year=substr($start,0,4); $year<=$max_year; $year++){
            echo "$year ";
            $min=$year."01";
            if($year!=$max_year)
                $max=($year+1)."01";
            else
                $max=$year.date('m');
            $where="where date>='$min' and date<'$max'";
            $rows=$dbs->select_walk("select SQL_NO_CACHE /*months graphs $min-$max*/ $cols from userstats_months $where order by date", array($this, 'months_graphs_row'));
        }
        $this->months_graphs_count_date();
        echo "\n";
    }
    function months_graphs_row($v)
    {
        $id=$v['user_id'];
        $date=$v['date'];
        if($this->last_month_date!='' && $this->last_month_date!=$date)
            $this->months_graphs_count_date();
        $this->last_month_date=$date;
        if($v['user_type']=='B')
            return;
        @$this->months_users[$id]++;
        foreach($this->months_graphs_stats as $stat)
            if($stat!='users')
                $this->cur_month_users[$id][$stat]=$v[$stat];
    }
    function months_graphs_count_date()
    {
        $date=$this->last_month_date;
        foreach($this->cur_month_users as $id=>$v){
            $key=$this->months_key($this->months_users[$id]);
            @$this->months_graphs_dates['users'][$key][$date]++;
            foreach($this->months_graphs_stats as $stat){
                if($stat=='users')
                    continue;
                @$this->months_graphs_dates[$stat][$key][$date]+=$v[$stat];
                @$this->months_graphs_dates_cumul[$stat][$key][$date]+=$v[$stat];
            }
            if(!isset($this->last_month_users[$id]))
                @$this->months_graphs_dates_new[$key][$date]++;
        }
        if($this->last_count_date!='')
            foreach($this->last_month_users as $id=>$v)
                if(!isset($this->cur_month_users[$id])){
                    $key=$this->months_key($this->months_users[$id]);
                    @$this->months_graphs_dates_quit[$key][$date]++;
                }
        foreach($this->months_users as $id=>$months){
            $key=$this->months_key($months);
            @$this->months_graphs_dates_cumul['users'][$key][$date]++;
        }
        if($this->last_count_date!='')
            foreach($this->months_graphs_dates_cumul as $stat=>$keys)
                foreach($keys as $key=>$dates)
                    if(isset($this->months_graphs_dates_cumul[$stat][$key][$this->last_count_date]))
                        @$this->months_graphs_dates_cumul[$stat][$key][$date]+=$this->months_graphs_dates_cumul[$stat][$key][$this->last_count_date];
        $this->last_count_date=$date;
        $this->last_month_users=$this->cur_month_users;
        $this->cur_month_users=array();
    }
    function months_key($months)
    {
        $keys=array(1, 12, 3*12, 6*12, 9*12);
        foreach($keys as $k=>$v)
            if($months<=$v)
                return $k+1;
        return $k+2;
    }

    static function svg_graph($data, $cols, $height, $xinc=1, $min=false)
    {
        if(empty($data))
            return false;
        $max=0;
        $keys=0;
        $sum=array();
        foreach($cols as $k=>$col){
            if(!isset($data[$col])){
                unset($cols[$k]);
                continue;
            }
            foreach($data[$col] as $key=>$v){
                if($min!==false && $key<$min){
                    unset($data[$col][$key]);
                    continue;
                }
                @$sum[$key]+=$v;
                $data[$col][$key]=$sum[$key];
                if($sum[$key]>$max)
                    $max=$sum[$key];
            }
            if(count($data[$col])>$keys)
                $keys=count($data[$col]);
        }
        $width=$keys*$xinc;
        $imul=2;
        $iwidth=$imul*$width;
        $iheight=$imul*$height;
        ksort($sum);
        $o="<svg width='$width' height='$height' viewBox='0 0 $iwidth $iheight' preserveAspectRatio='xMinYMin meet' xmlns='http://www.w3.org/2000/svg' version='1.1'>\n";
        foreach(array_reverse($cols) as $col)
            $o.=self::graph_paths($data[$col], array_keys($sum), $iheight, $max, $xinc*$imul, "graph_c$col");
        $o.="</svg>";
        return $o;
    }
    static function graph_paths($data, $allkeys, $height, $max, $xinc=1, $class='')
    {
        $o='';
        $x=0;
        $o.="<path class='$class' d='M0,$height";
        $first=true;
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

    function pie_graph($data, $size)
    {
        $total=array_sum($data);
        if($total==0)
            return;
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
            $o.="<path class='graph_c$k' d='$d'></path>\n";
        }
        $o.="</svg>";
        return $o;
    }

    function cubes($user='')
    {
        require_once('include/update_stats.php');
        ini_set('memory_limit','800M');
        $date='201509';
        $tot=updatestats::load_stat($date,'stats');
        $data=updatestats::load_stat($date,'users');
        $type=$data[$user]['type'];
        echo "\n";
        foreach($data[$user]['ns'] as $k=>$v)
            echo "$k $v ".round(100*$v/$tot['ns_type'][$k][$type],3)."% ".$data[$user]['tot_time2_ns'][$k]." ".round(100*$data[$user]['tot_time2_ns'][$k]/$tot['tot_time2_ns_type'][$k][$type],3)."%\n";
        echo "\n";
        foreach($data[$user]['logs'] as $k=>$v){
            $v=array_sum($v);
            $t=array_sum($tot['logs'][$k]);
            if(isset($tot['logs_bot'][$k]))
                $t-=array_sum(@$tot['logs_bot'][$k]);
            echo "$k $v ".round(100*$v/$t,3)."% ".$data[$user]['tot_time2_logs'][$k]." ".round(100*$data[$user]['tot_time2_logs'][$k]/$tot['tot_time2_log_types'][$k][$type],3)."%\n";
        }
    }

    static function list_all_wikis($user)
    {
        global $conf, $db_conf;
        require_once('include/wikis.php');
        $list=wikis::list_db_with_stats();
        $res=[];
        $sum=['total', 'edit', 'log'];
        $all=[];
        foreach($sum as $k)
            $all[$k]=0;
        foreach($list as $db_host=>$wikis){
            $db_conf['dbs']['host']=$db_host;
            $dbs=init_db('dbs', false);
            foreach($wikis as $site){
                $row=@$dbs->select1("select * from `stats_".$dbs->escape($site)."`.userstats_tot where user='".$dbs->escape($user)."'");
                if(!empty($row)){
                    $res[$site]=$row;
                    foreach($sum as $k)
                        $all[$k]+=$row[$k];
                    $sort[$site]=$row['total'];
                }
            }
        }
        $all['wikis']=count($res);
        arsort($sort);
        $res2=[];
        foreach(array_keys($sort) as $k)
            $res2[$k]=$res[$k];
        $res2['global']=$all;
        return $res2;
    }

}

?>