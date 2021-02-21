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

class ranges extends site_page
{
    var $path='/plage-ip';
    var $cache=false;
    var $range='';
    var $srt='';
    var $ip='';
    var $owner='';
    var $search='';
    var $rir_only=false;
    var $cidr_only=false;
    var $blocked_only=false;
    var $edits='';
    var $limit=100;
    var $max_limit=5000;
    var $max_pages=200;
    var $edits_limit=200;
    var $max_edits_limit=10000;
    var $max_logs_limit=300;
    var $min_sum_edits_time='9 months';
    var $min_edits_time='9 months';
    var $multi_table=true;
    var $total_file='ctrl/out/ranges_stats';
    var $flags=array(
        'direct'=>             1,
        'ipb_create_account'=> 2,
        'ipb_anon_only'=>      4,
        'ipb_allow_usertalk'=> 8,
        'ipb_block_email'=>   16,
        );
    var $whois_cache=true;
    var $whois_cache_path='whois_cache';
    var $whois_rand_src_ip=true;
    var $raw=false;
    var $view16=false;
    var $sum_edits=false;
    var $title_prefix=false;
    var $from='';
    var $whois='';
    var $submenu='';

    function __construct()
    {
    }

    function cache_key($year=false,$page=false)
    {
        return 'ranges:'.($year!==false?$year:$this->year).':'.($page!==false?$page:$this->page).':'.$this->limit;
    }
    function valid_cache_date($cache_date)
    {
        return true;
    }
    static function get_title()
    {
        if(isset($_GET['range']) && $_GET['range']!=''){
            $o=htmlspecialchars($_GET['range']);
            if(isset($_GET['edits'])||@$_GET['submenu']=='edits')
                $o.=" Contributions";
            elseif(@$_GET['submenu']=='blocks')
                $o.=" Blocages";
            elseif(isset($_GET['whois'])||@$_GET['submenu']=='whois'){
                $o.=" Whois";
                if(@$_GET['whois']!='' && @$_GET['whois']!=@$_GET['range'])
                    if(preg_match('!^([\d\.]+)/(\d+)$!',$_GET['whois']))
                        $o=htmlspecialchars($_GET['whois'])." Whois";
                    else
                        $o.=' '.htmlspecialchars($_GET['whois']);
            }else
                $o.=" Adresses IP";
            return $o;
        }
        $o="Plages d'IP";
        if(isset($_GET['owner']))
            $o.=" ".htmlspecialchars($_GET['owner']);
        elseif(isset($_GET['search']))
            $o.=" Recherche";
        if(isset($_GET['sum_edits']))
            $o.=" Contributions";
        return $o;
    }
    static function get_description()
    {
        $suf="statistiques sur les IP";
        if(isset($_GET['range']) && $_GET['range']!=''){
            $range=htmlspecialchars($_GET['range']);
            if(isset($_GET['edits']))
                $o="Liste des contributions sur Wikipédia fr dans la plage d'IP $range, $suf";
            elseif(isset($_GET['whois'])){
                $whois=htmlspecialchars($_GET['whois']);
                if(preg_match('!^([\d\.]+)/(\d+)$!',$_GET['whois']))
                    $o="Whois plage $whois, $suf pour Wikipédia fr";
                else
                    $o="Whois $whois dans la plage $range, $suf pour Wikipédia fr";
            }else
                $o="Adresses IP utilisées sur Wikipédia fr dans la plage $range avec statistiques";
            return $o;
        }
        $o="Statistiques sur les plages d'IP utilisées sur Wikipédia fr";
        if(isset($_GET['owner'])||isset($_GET['search'])){
            $o.=". Recherche « ";
            if(isset($_GET['owner']))
                $o.=htmlspecialchars($_GET['owner']);
            else
                $o.=htmlspecialchars($_GET['search']);
            $o.=" »";
        }
        return $o;
    }
    function init()
    {
        $this->submenu='';
        if(isset($_GET['range']))
            $this->range=$_GET['range'];
        if(isset($_GET['ip']))
            $this->ip=trim($_GET['ip']);
        if(isset($_GET['srt']))
            $this->srt=$_GET['srt'];
        if(isset($_GET['owner']))
            $this->owner=$_GET['owner'];
        if(isset($_GET['search']))
            $this->search=$_GET['search'];
        if(isset($_GET['rir_only']))
            $this->rir_only=true;
        if(isset($_GET['blocked_only']))
            $this->blocked_only=true;
        if(isset($_GET['cidr']) && (int)$_GET['cidr'])
            $this->cidr_only=(int)$_GET['cidr'];
        if(isset($_GET['submenu']))
            $this->submenu=$_GET['submenu'];

        if(($this->submenu=='edits'||isset($_GET['sum_edits'])) && isset($_GET['limit'])){
            $this->edits_limit=(int)$_GET['limit'];
            if($this->edits_limit>$this->max_edits_limit)
                $this->edits_limit=$this->max_edits_limit;
        }
        if(isset($_GET['whois'])){
            $this->whois=$_GET['whois'];
            if($this->whois!=''){
                $this->submenu='whois';
                if($this->range=='')
                    $this->range=$this->whois;
            }
        }
        $this->page=isset($_GET['page'])?(int)$_GET['page']:1;
        if($this->page<1)
            $this->page=1;
        if($this->page>$this->max_pages)
            $this->page=$this->max_pages;
        if(isset($_GET['limit']))
            $this->limit=(int)$_GET['limit'];
        if($this->limit>$this->max_limit)
            $this->limit=$this->max_limit;
        if(isset($_GET['raw']) && $_GET['raw'])
            $this->raw=true;
        if(isset($_GET['view16']) && $_GET['view16'])
            $this->view16=true;
        if(isset($_GET['sum_edits']) && $_GET['sum_edits'])
            $this->sum_edits=true;
        if(isset($_GET['from']))
            $this->from=$_GET['from'];
        if(isset($_GET['title_prefix']))
            $this->title_prefix=urldecode($_GET['title_prefix']);
    }
    function view()
    {
        $this->init();
        if(!isset($_GET['purge'])||!$_GET['purge'])
            if($r=$this->get_cache())
                return $r;
        $o='<div class="ranges">';
        if($this->ip!='')
            $o.=$this->view_ip($this->ip);
        elseif($this->range!='')
            $o.=$this->view_range($this->range);
        else
            $o.=$this->view_list();
        $o.='</div>';
        if($this->cache)
            $this->set_cache($o);
        return $o;
    }
    function view_list()
    {
        $db=get_dbs();
        $q="select * from ranges";
        $where="1 ";
        $search='';
        if($this->owner!=''){
            $search=$this->owner;
            $where.=" and whois_owner='".$db->escape($this->owner)."'";
        }elseif($this->search!=''){
            $where.=" and whois_owner like '%".$db->escape($this->search)."%'";
            $search=$this->search;
        }
        if($this->rir_only)
            $where.=" and rir!=''";
        if($this->cidr_only)
            $where.=" and cidr=".(int)$this->cidr_only;
        if($this->blocked_only)
            $where.=" and blocked=1";
        if($this->srt==''){
            if($this->search=='')
                $order="order by edits desc";
            else
                $order="order by cidr, start";
        }elseif($this->srt=='range')
            $order="order by cidr, start";
        else
            $order="order by `".$db->escape($this->srt)."` desc";
        $this->where=$where;
        $start=($this->page-1)*$this->limit;
        $search_chars=str_replace('%', '', $search);
        $rows=array();
        $this->total=0;
        if($search=='' || strlen($search_chars)>=2){
            $rows=$db->select("$q where $where $order limit $start,".$this->limit);
            $this->total=$db->selectcol("select count(*) from ranges where $where");
        }
        $o="<h1>Plages d'IP sur Wikipédia fr</h1>";
        $o.='<table class="mep"><tr><td>';
        if($search!=''){
            $o.="<div class='search'><big><b>Recherche</b></big> :<br> « <em>".htmlspecialchars($search)."</em> »";
            if(strlen($search_chars)<2)
                $o.='<br><b>Erreur</b> : recherche avec moins de 2 caractères.';
            if(!empty($rows)){
                $links=array();
                if(!$this->raw)
                    $links[]=lnk('Liste brute',array('raw'=>1),array('owner', 'search', 'rir_only', 'cidr', 'blocked_only','limit'),'', $this->path);
                if(!$this->view16)
                    $links[]=lnk('Agréger',array('view16'=>1),array('owner', 'search', 'rir_only', 'cidr', 'blocked_only','limit'),'', $this->path);
                if($this->raw||$this->view16)
                    $links[]=lnk('Statistiques',array(),array('owner', 'search', 'rir_only', 'cidr', 'blocked_only','limit'),'', $this->path);
                $o.='<ul>';
                foreach($links as $v)
                    $o.="<li>$v</li>";
                $o.='</ul>';
            }
            $o.="</div>";
            $o.='<span class="retour">'.lnk('Retour à la liste',array(),array('page','srt'),'', $this->path).'</span>';
            $o.='</td><td>';
        }
        $o.=$this->form();
        $o.='</td><td>';
        $o.=$this->view_total_stats();
        $o.='</td></tr></table>';
        if($search!='' && count($rows)>1){
            $o.='<table class="ranges">';
            $o.=$this->range_header(false);
            $o.=$this->view_sum_row();
            $o.="</table>";
        }
        $o.='<table class="ranges">';
        if($this->view16){
            $pages_links='';
            $o.='<tr><td class="pages" colspan="20">Compilation des /16 :</td></tr>';
        }elseif($this->sum_edits){
            $pages_links='';
            $o.='<tr><td class="pages" colspan="20">Compilation des éditions :</td></tr>';
        }else{
            $pages_links='<tr><td class="pages" colspan="20">'.$this->pages_links(count($rows)).'</td></tr>';
            $o.=$pages_links;
        }
        if($this->raw){
            $o.='<tr><td><div class="raw"><pre>';
            foreach($rows as $v)
                $o.=$v['range']."\n";
            $o.='</pre></div></tr></td>';
        }elseif($this->view16){
            $o.=$this->range_header(false);
            $o.=$this->view_16();
        }elseif($this->sum_edits){
            $o.=$this->view_sum_edits();
        }else{
            $o.=$this->range_header();
            if(!empty($rows))
                foreach($rows as $v)
                    $o.=$this->range_row($v);
            else
                $o.='<tr><td colspan=20>Aucune plage trouvée.</td></tr>';
        }
        if(!empty($rows))
            $o.=$pages_links;
        $o.='</table>';
        if($this->view16){
            $o.='<h2>Plages agrégées</h2>';
            $o.='<table class="ranges"><tr><td><div class="raw"><pre>';
            $o.=$this->aggregate_rows($rows);
            $o.='</pre></div></td></tr></table>';
        }
        return $o;
    }
    function form()
    {
        $search=isset($_GET['owner'])?htmlspecialchars($_GET['owner']):(isset($_GET['search'])?htmlspecialchars($_GET['search']):'');
        $o="<div class='searchform'><form method='get' action='{$this->path}?'>
        IP <input type='text' name='ip' value=''/>&nbsp;&nbsp;&nbsp;&nbsp;
        Nom <input type='text' name='search' value=\"$search\"/><br/>Afficher seulement :
        <input type='checkbox' name='rir_only' value=1 ".($this->rir_only?" checked":"")."/> RIR
        <input type='checkbox' name='cidr' value=16 ".($this->cidr_only==16?" checked":"")."/> /16
        <input type='checkbox' name='cidr' value=24 ".($this->cidr_only==24?" checked":"")."/> /24
        <input type='checkbox' name='blocked_only' value=1 ".($this->blocked_only?" checked":"")."/> Plage bloquée";
        if(isset($_GET['srt']) && $_GET['srt']!='')
            $o.="<input type='hidden' name='srt' value='".htmlspecialchars($_GET['srt'])."'/>";
        $o.="<br>&nbsp;<input style='height:24px' type='submit' value='Rechercher'/>
        </form></div>\n";
        return $o;
    }
    function range_titles()
    {
        $i=0;
        return array(
            'Plage'=>'range',
            'Blocages<br>de plages'=>'range_blocks',
            'Éditions'=>'edits',
            '<abbr title="IP avec des éditions">IP</abbr>'=>'ips',
            'IP<br>bloquées'=>'blocked_ips',
            'Total<br>blocages'=>'blocks',
            '<abbr title="Déblocages">Déb.</abbr>'=>'unblocks',
            'Proxys'=>'proxy',
            $i++=>'Pays',
            );
    }
    function range_header($links=true)
    {
        $o='<tr class="header">';
        $titles=$this->range_titles();
        foreach($titles as $k=>$v)
            if($links && !is_numeric($k))
                $o.="<td".($this->srt==$v?' class="selected"':'').">".lnk($k,array('srt'=>$v),array('owner', 'search', 'rir_only', 'cidr', 'blocked_only'),'', $this->path)."</td>";
            elseif(is_numeric($k))
                $o.="<td>$v</td>";
            else
                $o.="<td>$k</td>";
        $o.='<td colspan="2">Whois</td><td>RIR</td><td></td></tr>';
        return $o;
    }
    function range_row($v)
    {
        $cls=(isset($v['blocked']) && $v['blocked']) ? " ip_blocked" : (isset($v['range_blocks']) && $v['range_blocks']>0 ? " ip_blocked_old" : " ip");
        $o="<tr".(isset($v['rir']) && $v['rir']!='' ? " class='rir'" : "").">";
        if($v['range']!='Total')
            $o.="<td class='num $cls'>".lnk(htmlspecialchars($v['range']),array(),array('page','srt'),'', $this->path.'/'.$v['range'])."</td>";
        else
            $o.="<td class='num $cls'>Total recherche :</td>";
        $o.="<td class='nblocks nw'>".$this->lock_icon($v['blocked'],$v['flags'],$v['range_blocks'])."</td>";
        $o.="<td class='num'>".fnum($v['edits'])."</td>";
        $max=$this->range_max_ips($v['range']);
        $p=fnum(round(100*$v['ips']/$max,1),1).' %';
        $o.="<td class='num'><span title='$p'>".fnum($v['ips'])."</span></td>";
        $p=fnum(round(100*$v['blocked_ips']/$max,1),1).' %';
        $o.="<td class='num nw'><span title='$p'>".($v['blocked_ips']>0 ? fnum($v['blocked_ips']).'&nbsp;'.$this->lock_icon($v['blocked_ips']>0?1:0,4,0,true):"")."</span></td>";
        $o.="<td class='num'>".fnum($v['blocks'])."</td>";
        $o.="<td class='num'>".fnum($v['unblocks'])."</td>";
        $p=fnum(round(100*$v['proxy']/$max,1),1).' %';
        $o.="<td class='num'><span title='$p'>".fnum($v['proxy'])."</span></td>";
        if($v['range']!='Total'){
            $o.="<td style='text-align:center'>".(isset($v['whois_country'])?$v['whois_country']:"?")."</td>";
            $o.="<td style='max-width:25em;'>".($v['whois_owner']!=''?lnk($v['whois_owner'],array('owner'=>$v['whois_owner']),array('rir_only'),'', $this->path):"")."</td>";
            $o.="<td style='max-width:25em;'>".(isset($v['whois_name'])?$v['whois_name']:"")."</td>";
            $o.=(isset($v['rir']) && $v['rir']!='' ? "<td class='rir'>".$this->rir_name($v['rir']) : "<td>")."</td>";
            $o.="<td>".lnk('whois',array('whois'=>$v['range']),array('range','ip'),'', $this->path)."</td>";
        }
        $o.="</tr>";
        return $o;
    }
    function pages_links($nbrows)
    {
        $db=get_dbs();
        $o="<span class='pages'>";
        if($nbrows==0)
            return $o."0 résultat</span>";
        $start=($this->page-1)*$this->limit;
        if($nbrows==$this->total)
            return $o."$nbrows résultat".($nbrows>1?'s':'')."</span>";
        if($this->page>1){
            $o.=lnk("|&lt;",array(),array('srt','search', 'rir_only', 'cidr', 'owner', 'blocked_only', 'raw'),'', $this->path);
            $o.="<span class='page_prev'>".lnk("&lt;&lt;",array('page'=>$this->page-1),array('srt','search', 'rir_only', 'cidr', 'owner', 'blocked_only', 'raw'),'', $this->path).'</span>';
        }
        $o.=($start+1)."&nbsp;-&nbsp;".($start+$nbrows)." / {$this->total} ";
        if($nbrows==$this->limit && $this->page<$this->max_pages)
            $o.="<span class='page_next'>".lnk("&gt;&gt;",array('page'=>$this->page+1),array('srt','search', 'rir_only', 'cidr', 'owner', 'blocked_only', 'raw'),'', $this->path).'</span>';
        $o.='</span>';
        return $o;
    }
    function aggregate_rows($rows)
    {
        $bin=array();
        foreach($rows as $v){
            $start=preg_replace('!/\d+$!', '', $v['range']);
            $bin[$start]=base_convert(ip2long($start),10,2);
        }
        $tot=0;
        $last=$first=$lasti='';
        foreach($bin as $k=>$v){
            if($last==''){
                $last=$v;
                $first=$k;
                continue;
            }
            for($i=0;$i<=31;$i++){
                if(($lasti>16 && $i+1==$lasti) || $v[$i]!=$last[$i])
                    break;
            }
            $last=$v;
            if($i>=20){
                $lasti=$i+1;
                continue;
            }
            if($lasti){
                $res[]="$first/$lasti";
                $tot+=pow(2, 32-$lasti);
                $first=$k;
                $lasti='';
            }
        }
        if($lasti){
            $res[]="$first/$lasti";
            $tot+=pow(2, 32-$lasti);
            $first=$k;
            $lasti='';
        }
        $o='';
        if(isset($res))
            foreach($res as $v)
                $o.="$v\n";
        $o.="\n\n$tot max ip";
        return $o;
    }
    function view_sum_row()
    {
        $db=get_dbs();
        foreach($this->range_titles() as $k=>$v)
            if(!is_numeric($k))
                $cols[]="sum(`$v`) `$v`";
        $row=$db->select1("select ".implode(', ', $cols)." from ranges where ".$this->where);
        $row['range']='Total';
        $row['whois_owner']='';
        $row['blocked']=0;
        $row['flags']=0;
        $o=$this->range_row($row);
        return $o;
    }
    function view_16()
    {
        $db=get_dbs();
        $rows=$db->select("select `range` from ranges where ".$this->where);
        $ranges=array();
        foreach($rows as $v){
            if(preg_match('!^(\d{1,3}\.\d{1,3})\.\d{1,3}\.\d{1,3}/(\d{1,2})$!',$v['range'], $r)){
                if($r[2]>=16)
                    $ranges[$r[1].'.0.0/16']='';
            }
        }
        if(empty($ranges))
            return false;
        $ranges=array_keys($ranges);
        $rows=$db->select("select * from ranges where `range` in ('".implode("','", $ranges)."')");
        $o='';
        foreach($rows as $v)
            $o.=$this->range_row($v);
        return $o;
    }
    function view_sum_edits()
    {
        return "Cette fonction est désactivée.";
        $db=get_dbs();
        $rows=$db->select("select `range` from ranges where ".$this->where);
        $ranges=array();
        foreach($rows as $v){
            if(preg_match('!^((\d{1,3}\.\d{1,3}\.)\d{1,3}\.)\d{1,3}/(\d{1,2})$!',$v['range'], $r)){
                if($r[3]==24)
                    $ranges[]=preg_quote($r[1]);
                elseif($r[3]==16)
                    $ranges[]=preg_quote($r[2]);
            }
        }
        if(empty($ranges))
            return false;
        if(!$db=get_db2())
            return false;
        if($this->from==''){
            $date=date('YmdHis',strtotime("-{$this->min_sum_edits_time}"));
            $order="desc";
        }else{
            $date=date('YmdHis',strtotime($this->from));
            $order="asc";
        }
        $rows=$db->select("select rev_id, rev_user_text, rev_timestamp, page_title, page_namespace, comment_text from revision_userindex left join comment on rev_comment_id=comment_id, page where rev_user=0 and rev_user_text REGEXP '^(".implode("|", $ranges).")' and rev_timestamp>='$date' and rev_page=page_id order by rev_timestamp $order limit ".$this->edits_limit);
        if($order=='asc')
            $rows=array_reverse($rows);
        $o='<table class="range_edits">';
        foreach($rows as $v){
            $title=mwns::get()->ns_title($v['page_title'],$v['page_namespace']);
            $o.="<tr><td>".date('d/m/Y H:i:s',strtotime($v['rev_timestamp'])+date('Z'))."</td>
            <td><a class='wp' href=\"https://fr.wikipedia.org/wiki/Spécial:Contributions/".htmlspecialchars(urlencode($v['rev_user_text']))."\">".htmlspecialchars($v['rev_user_text'])."</a></td>
            <td><a class='wp' href=\"https://fr.wikipedia.org/w/index.php?oldid=".(int)$v['rev_id']."&diff=prev\">diff</a></td>
            <td><a class='wp' href=\"https://fr.wikipedia.org/wiki/$title\">".htmlspecialchars(str_replace('_',' ',$title))."</a></td>
            <td class='comment'>".htmlspecialchars($v['comment_text'])."</td>
            </tr>";
        }
        $o.='</table>';
        $o.=count($rows). " lignes";
        return $o;
    }
    function view_ip($ip)
    {
        $db=get_dbs();
        if(preg_match('!^([\d\.]+)/(\d+)$!',$ip,$res)){
            $rows=$db->select("select * from ranges where `range`='".$db->escape($ip)."' limit 1");
            if(!empty($rows)){
                return $this->view_range($rows[0]['range']);
            }
            $ip=$res[1];
        }
        if(!preg_match('!^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$!',$ip))
            return '<p>Adresse IP non supportée</p>';
        $o='';
        $ipx=self::ip_hex($ip);
        $rows=$db->select("select * from ranges where start!='' and start<='".$db->escape($ipx)."' and end>='".$db->escape($ipx)."' order by cidr desc limit 1");
        if(isset($rows[0]))
            $o.=$this->view_range($rows[0]['range']);
        else{
            $o.='<div class="range">';
            $o.='<span class="retour">'.lnk('Retour à la liste',array(),array('page','srt'), '', $this->path).'</span>';
            $o.='<p>Plage non trouvée.</p>';
            $o.='</div>';
        }
        return $o;
    }
    function redirect_ip($ip)
    {
        $db=get_dbs();
        $fromip='?fromip='.$ip;
        if(preg_match('!^([\d\.]+)/(\d+)$!',$ip,$res)){
            $rows=$db->select("select * from ranges where `range`='".$db->escape($ip)."' limit 1");
            if(!empty($rows))
                return $this->path.'/'.$rows[0]['range'];
            $ip=$res[1];
            $fromip='';
        }elseif(!preg_match('!^([\d\.]+)$!',$ip))
            return false;
        $ipx=self::ip_hex($ip);
        $rows=$db->select("select * from ranges where start!='' and start<='".$db->escape($ipx)."' and end>='".$db->escape($ipx)."' order by cidr desc limit 1");
        if(isset($rows[0]))
            return $this->path.'/'.$rows[0]['range'].$fromip;
        return false;
    }
    function view_range($range)
    {
        if(!preg_match('!^([\d\.]+)/(\d+)$!',$range,$res))
            return false;
        $cidr=$res[2];
        $o='<div class="range">';
        if(isset($_GET['fromip']))
            $o.=$this->fromip();
        $o.='<h1 id="titre">Plage '.htmlspecialchars($range).'</h1>';
        if(!isset($_GET['fromip']))
            $o.=$this->form();
        $o.='<span class="retour">'.lnk('Retour à la liste',array(),array('page','srt'), '', $this->path).'</span>';
        $db=get_dbs();
        $rows=$db->select("select * from ranges where `range`='".$db->escape($range)."' limit 1");
        $row=isset($rows[0]) ? $rows[0] : array();
        $up_ranges=$this->view_upranges($range,1,32);
        if($up_ranges!==false){
            $o.=$up_ranges;
            $o.='<div class="subranges">';
        }
        $o.=$this->view_main_range($range, $row);
        if(!$db=get_db2()){
            $o.='<p><b>Error</b> Wiki replica database unavailable</p>';
        }
        $rows=$this->log_rows($range,true);
        if(!empty($rows)){
            $rows=array_reverse($rows);
            $o.='<table class="range_blocks">';
            $o.='<tr><td colspan="5">Blocages de la plage :</td></tr>';
            foreach($rows as $v){
                $b=$this->log_params($v['log_params']);
                $o.="<tr>
                    <td>".date('d/m/Y',strtotime($v['log_timestamp']))."</td>
                    <td>$v[log_action]</td>
                    <td>$b[len]</td>
                    <td>$v[actor_name]</td>
                    <td>$b[opts]</td>
                    <td>".(isset($v['log_comment']) ? htmlspecialchars($v['log_comment']) : '')."</td>
                    </tr>";
            }
            $o.='</table>';
        }
        $o.='<div class="submenu">';
        $menu=array(''=>'Adresses IP', /*'edits'=>'Contributions',*/ 'blocks'=>'Blocages', 'whois'=>'Whois');
        if($cidr!=16 && $cidr!=24){
            unset($menu['edits']);
            unset($menu['blocks']);
        }else{
            if(@$row['edits']==0)
                unset($menu['edits']);
            if(@$row['blocks']==0)
                unset($menu['blocks']);
        }
        foreach($menu as $k=>$v){
            $o.=' <span'.($this->submenu==$k?' class="selected"':'').'>';
            $params=$k!='' ? array($k=>$range) : array();
            $o.=lnk($v, $k!='' ? array('submenu'=>$k) : array(), array(), '', $this->path.'/'.$range);
            $o.='</span>';
        }
        if(!@$row['blocked']){
            if($cidr>=16)
                $o.=" <a class='wp' href='https://fr.wikipedia.org/wiki/Special:Block/$range' rel='nofollow'>Bloquer $range</a>";
        }else{
            $o.=" <a class='wp' href='https://fr.wikipedia.org/wiki/Special:Block/$range' rel='nofollow'>Rebloquer $range</a>";
            $o.=" <a class='wp' href='https://fr.wikipedia.org/wiki/Special:Unblock/$range' rel='nofollow'>Débloquer</a>";
        }
        $o.='</div>';
        switch($this->submenu){
            case 'edits':
                $o.=$this->view_edits($range);
                break;
            case 'blocks':
                $o.=$this->view_blocks($range);
                break;
            case 'whois':
                $o.=$this->view_whois($this->whois!=''?$this->whois:$range);
                break;
            case '':
            default :
                $o.='<div class="subranges">';
                if($cidr<16)
                    $o.=$this->view_subranges($range,1,16);
                elseif($cidr<24)
                    $o.=$this->view_subranges($range,17,30);
                else
                    $o.=$this->view_ips($range);
                $o.='</div>';
        }
        if($up_ranges!==false)
            $o.='</div>';
        $o.='</div>';

        return $o;
    }
    function view_main_range($range, $v)
    {
        if(empty($v))
            return '';
        $cidr = explode('/', $range);
        $min = long2ip((ip2long($cidr[0])) & ((-1 << (32 - (int)$cidr[1]))));
        $max = long2ip((ip2long($cidr[0])) + pow(2, (32 - (int)$cidr[1])) - 1);
        $tot=pow(2, 32 - (int)$cidr[1]);
        $o="<table class='mep'><tr><td>";
        $o.='<div class=mainrange>';
        $o.='<table class="mep"><tr><td>';
        $o.="<tr><td colspan=2><span class=min_max>$min<br>$max</span><h2>".lnk(htmlspecialchars($v['range']),array(),array('page','srt'),'', $this->path.'/'.$v['range'])."</h2></td></tr>";
        $o.='<tr><td>';
        $o.='<table class="ranges_mainrange">';
        $o.="<tr><td class=header>Éditions</td><td class='num'>".fnum($v['edits'])."</td></tr>";
        $max=$this->range_max_ips($v['range']);
        $p=fnum(round(100*$v['ips']/$max,1),1).' %';
        $o.="<tr><td class=header>IP utilisées</td><td class='num'>".fnum($v['ips'])."</td>".(@$v['ips']!=0 ?"<td class=p>$p</td>":"")."</tr>";
        $p=fnum(round(100*$v['blocked_ips']/$max,1),1).' %';
        $o.="<tr><td class=header>IP bloquées</td><td class='num nw'>".($v['blocked_ips']>0 ? $this->lock_icon($v['blocked_ips']>0?1:0,4,0,true).'&nbsp;' : ''). fnum($v['blocked_ips'])."</td>".(@$v['blocked_ips']!=0 ?"<td class=p>$p</td>":"")."</tr>";
        $o.="<tr><td class=header>Total blocages</td><td class='num'>".fnum($v['blocks'])."</td><td class='p'>(".fnum($v['unblocks'])." déblocage".s($v['unblocks']).")</td></tr>";
        $p=fnum(round(100*$v['proxy']/$max,1),1).' %';
        $o.="<tr><td class=header>Proxys détectés</td><td class='num'>".fnum($v['proxy'])."</td>".(@$v['proxy']!=0 ?"<td class=p>$p</td>":"")."</tr>";
        $o.="<tr><td class=header>Total IP possibles</td><td class='num'>".fnum($tot)."</td></tr>";
        $o.='</table>';
        $o.='</td><td>';
        $o.='<table class="ranges_mainrange right">';
        $o.="<tr><td class=header".(@$v['whois_name']!=''?' rowspan=2':'').">".lnk('Whois',array('whois'=>$v['range']),array('range','ip'),'', $this->path)."</td><td>".($v['whois_owner']!=''?lnk($v['whois_owner'],array('owner'=>$v['whois_owner']),array('rir_only'),'', $this->path):"")."</td></tr>";
        if(@$v['whois_name']!='')
            $o.="<tr><td>".(isset($v['whois_name'])?$v['whois_name']:"")."</td></tr>";
        $o.="<tr><td class=header>Pays</td><td style='text-align:center'>".(isset($v['whois_country'])?$v['whois_country']:"?")."</td></tr>";
        if(@$v['rir']!='')
            $o.="<tr><td class=header>RIR</td><td class='rir'>".(isset($v['rir']) && $v['rir']!='' ? $this->rir_name($v['rir']) : "sous-plage")."</td></tr>";
        $o.='</table>';
        $o.='</td></tr></table>';
        $o.='</div></td></tr></table>';
        return $o;
    }
    function fromip()
    {
        $ip=filter_input(INPUT_GET, 'fromip', FILTER_SANITIZE_STRING);
        $o='<div class="fromip"><h1 id="titre">IP '.htmlspecialchars($ip).'</h1>';
        if(!preg_match('!^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$!',$ip))
            return $o.'<p>Adresse IP non supportée</p></div>';
        $ipx=self::ip_hex($ip);
        $db=get_dbs();
        $rows=$db->select("select * from ranges where rir!='' and start!='' and start<='".$db->escape($ipx)."' and end>='".$db->escape($ipx)."' order by cidr limit 1");
        if(empty($rows))
            $rows=$db->select("select * from ranges where rir='' and start!='' and start<='".$db->escape($ipx)."' and end>='".$db->escape($ipx)."' order by cidr limit 1");
        $o.='<table class="mep"><tr><td>';
        if(!empty($rows)){
            $v=$rows[0];
            $o.='<table class="mep"><tr><td>';
            $o.="<div class='ip_infos'><table><tr><td class=label>Plage d'IP racine : </td><td><big>". lnk(htmlspecialchars($v['range']),array(),array('page','srt'),'', $this->path.'/'.$v['range'])."</big></td></tr>";
            $o.="<tr><td class=label>Whois : </td><td>". ($v['whois_owner']!=''?lnk($v['whois_owner'],array('owner'=>$v['whois_owner']),array('rir_only'),'', $this->path):"")."</td></tr>";
            $o.="<tr><td class=label>Pays : </td><td>". htmlspecialchars($v['whois_country'])."</td></tr>";
            $o.='</table></div>';
            if($v['cidr']!=24){
                $vv=$v;
                if(preg_match('!^(\d{1,3}\.\d{1,3}\.\d{1,3})\.\d{1,3}$!',$ip, $r)){
                    $r24=$r[1].'.0/24';
                    $rows=$db->select("select * from ranges where `range`='$r24' limit 1");
                    if(!empty($rows)){
                        $v=$rows[0];
                        $o.="<div class='ip_infos'><table><tr><td class=label>Plage d'IP /24 : </td><td><big>".lnk(htmlspecialchars($v['range']),array(),array('page','srt'),'', $this->path.'/'.$v['range'])."</big></td></tr>";
                        $o.="<tr><td class=label>Whois : </td><td>".($v['whois_owner']!=''?lnk($v['whois_owner'],array('owner'=>$v['whois_owner']),array('rir_only'),'', $this->path):"")."</td></tr>";
                        if($v['whois_country']!=$vv['whois_country'])
                            $o.="<tr><td class=label>Pays : </td><td>". htmlspecialchars($v['whois_country'])."</td></tr>";
                        $o.='</table></div>';
                    }
                }
            }
            $o.='</td></tr></table>';
        }else
            $o.='<p>Aucune plage trouvée</p>';
        $o.='</td><td>';
        $o.=$this->form();
        $o.='</td></tr></table>';
        $o.='</div>';
        return $o;
    }

    function range_max_ips($range)
    {
        $cidr = explode('/', $range);
        if(!isset($cidr[1]))
            return -1;
        return pow(2, 32 - (int)$cidr[1]);
    }
    function view_subranges($range,$cidr_min=1,$cidr_max=32)
    {
        $rows=$this->get_subranges($range,$cidr_min,$cidr_max);
        $o='<table class="ranges">';
        $n=count($rows)-1>=0 ? count($rows)-1 :0;
        $s=$n>1?'s':'';
        $o.='<tr><td colspan="100" class="count">'."$n sous-plage$s</td></tr>";
        $o.=$this->range_header(false);
        $rows=index($rows,'range');
        $order=array_keys($rows);
        natsort($order);
        foreach($rows as $r=>$v){
            if($rows[$r]['range']==$range)
                continue;
            $o.=$this->range_row($rows[$r]);
        }
        $o.='</table>';
        return $o;
    }
    function get_subranges($range,$cidr_min=1,$cidr_max=32)
    {
        $db=get_dbs();
        list($start, $end)=self::parse_range($range);
        return $db->select("select * from ranges where ((start>='".$db->escape($start)."' and start<='".$db->escape($end)."')
            or (end>='".$db->escape($start)."' and end<='".$db->escape($end)."'))
            and cidr>=$cidr_min and cidr<=$cidr_max order by cidr,start limit 500");
    }
    function view_upranges($range,$cidr_min=1,$cidr_max=32)
    {
        $db=get_dbs();
        list($start, $end)=self::parse_range($range);
        $rows=$db->select("select * from ranges where (start<='".$db->escape($start)."' and end>='".$db->escape($end)."')
            and cidr>=$cidr_min and cidr<=$cidr_max and `range`!='".$db->escape($range)."' order by cidr,start limit 500");
        if(empty($rows))
            return false;
        $o='<table class="ranges">';
        $s=count($rows)>1?'s':'';
        $o.='<tr><td colspan="100" class="count">'.count($rows)." plage$s supérieure$s</td></tr>";
        $o.=$this->range_header(false);
        $rows=index($rows,'range');
        $order=array_keys($rows);
        natsort($order);
        foreach($rows as $r=>$v){
            if($rows[$r]['range']==$range)
                continue;
            $o.=$this->range_row($rows[$r]);
        }
        $o.='</table>';
        return $o;
    }
    function view_edits($range)
    {
        return "Cette fonction est désactivée.";
        global $conf;
        if(!preg_match('!^([\d\.]+)/(\d+)$!',$range,$res))
            return false;
        $cidr=$res[2];
        $ip=explode('.',$res[1]);
        if($cidr==24)
            $ip="$ip[0].$ip[1].$ip[2].%";
        elseif($cidr==16)
            $ip="$ip[0].$ip[1].%";
        else
            return false;
        if(!empty($conf['hide_rev_summary']))
            $hide_rev_summary=array_flip($conf['hide_rev_summary']);
        if(!$db=get_db2())
            return false;
        if($this->from==''){
            $date='';
            $order="desc";
        }else{
            $date=date('YmdHis',strtotime($this->from));
            $order="asc";
        }
        $q="select rev_id, rev_timestamp, page_title, page_namespace, actor_name from revision_userindex left join page on rev_page=page_id, actor where rev_actor=actor_id and /*actor_user is null and*/ actor_name like '$ip'".($date!=""?" and rev_timestamp>='$date'":"")." order by rev_timestamp $order limit ".$this->edits_limit;

        $o='<table class="range_edits">';
        $rows=$db->select($q);
        foreach($rows as $v){
            $title=mwns::get()->ns_title($v['page_title'],$v['page_namespace']);
            if($this->title_prefix!='' && !preg_match("!^".preg_quote($this->title_prefix, '!')."!", $title))
                continue;
            $comment = !isset($hide_rev_summary[$v['rev_id']]) ? htmlspecialchars(isset($v['comment_text']) ? $v['comment_text'] : '') : '';
            $o.="<tr><td>".date('d/m/Y H:i:s',strtotime($v['rev_timestamp'])+date('Z'))."</td>
            <td><a class='wp' href=\"https://fr.wikipedia.org/wiki/Spécial:Contributions/".htmlspecialchars(urlencode($v['actor_name']))."\">".htmlspecialchars($v['actor_name'])."</a></td>
            <td><a class='wp' href=\"https://fr.wikipedia.org/w/index.php?oldid=".(int)$v['rev_id']."&diff=prev\">diff</a></td>
            <td><a class='wp' href=\"https://fr.wikipedia.org/wiki/$title\">".htmlspecialchars(str_replace('_',' ',$title))."</a></td>
            <td class='comment'>".$comment."</td>
            </tr>";
        }
        $o.='</table>';
        return $o;
    }

    function view_whois($ip)
    {
        $o='<div class="whois">';
        $o.='<h2>Whois '.htmlspecialchars($ip).' :</h2>';
        $o.='<pre>';
        if(!$this->whois_cache || isset($_GET['purge']) || !$data=$this->load_whois_cache($ip)){
            //$w=$this->whois($ip);//TODO
            //$data=implode("\n",$w['rawdata']);
            $data='';
            if($data==''){
                $o.="<b>Erreur, pas de donnée</b></div>\n";
                return $o;
            }
            if($this->whois_cache)
                $this->save_whois_cache($ip, $data);
        }
        if(stripos($data,'ERROR:201: access denied')!==false){
            $s= preg_match('/This is the (.+?) Whois server/i',$data,$r) ? $r[1] : '';
            $data="Erreur : requête refusée par le serveur whois $s";
        }
        $data=htmlspecialchars($data);
        $data=preg_replace('!(\d{1,3}\.){3}\d{1,3}(/\d{1,2})?!','<a href="?menu=ranges&ip=\0">\0</a>',$data);
        $o.=$data;
        $o.='</pre></div>';
        return $o;
    }
    function whois($ip)
    {
        require_once('include/common/phpwhois/whois.main.php');
        $whois = new Whois();
        error_reporting(E_ALL^E_NOTICE);
        $src_ip=$this->whois_src_ip();
        @$this->i['source_ip'][$src_ip]++;
        $w = $whois->Lookup($ip,false,$src_ip);
        error_reporting(E_ALL);
        if(@$w['regrinfo']['owner']['organization']=='This network has been transferred to AFRINIC'){
            $whois->UseServer('ip','whois.afrinic.net');
            error_reporting(E_ALL^E_NOTICE);
            $w = $whois->Lookup($ip,false,$src_ip);
            error_reporting(E_ALL);
        }
        return $w;
    }
    function whois_src_ip()
    {
        global $conf;
        static $i;
        if(!isset($conf['whois_src_ip'])||empty($conf['whois_src_ip']))
            return false;
        if(!is_array($conf['whois_src_ip']))
            return $conf['whois_src_ip'];
        if($this->whois_rand_src_ip)
            return $conf['whois_src_ip'][mt_rand(0,count($conf['whois_src_ip'])-1)];
        return $conf['whois_src_ip'][$i++ % count($conf['whois_src_ip'])];
    }
    function whois_cache_file($ip)
    {
        if(strpos($ip, '.')!==false)
            $c=explode('.', $ip);
        elseif(strpos($ip, ':')!==false)
            $c=explode(':', $ip);
        $ip=str_replace('/','%2f', $ip);
        if(!isset($c))
            return $this->whois_cache_path.'/'.$ip;
        return $this->whois_cache_path.'/'.$c[0].'/'.$ip;
    }
    function save_whois_cache($ip, $data)
    {
        if(empty($data))
            return false;
        $file=$this->whois_cache_file($ip);
        if(!is_dir(dirname($file)))
            mkdir(dirname($file));
        $data=gzcompress($data);
        file_put_contents($file, $data);
    }
    function load_whois_cache($ip)
    {
        $file=$this->whois_cache_file($ip);
        if(!file_exists($file))
            return false;
        $data=file_get_contents($file);
        if(!empty($data))
            return gzuncompress($data);
        return false;
    }

    function view_ips($range)
    {
        if(!preg_match('!^([\d\.]+)/(\d+)$!',$range,$res))
            return false;
        $cidr=$res[2];
        $ip=explode('.',$res[1]);
        $ip="$ip[0].$ip[1].$ip[2].%";
        $db=get_dbs();
        $ips=array();
        if($this->multi_table)
            $rows=$db->select("select user,edit,days,months from userstats_ip_tot where user like '$ip'");
        else
            $rows=$db->select("select user,edit,days,months from userstats_ip where user like '$ip' and date_type='T'");
        foreach($rows as $v)
            $ips[$v['user']]=$v;
        $rows=$this->block_rows($ip);
        foreach($rows as $v){
            if(preg_match('!^([\d\.]+)/(\d+)$!',$v['ipb_address']))
                continue;
            array_walk($v, function(&$val){ $val=htmlspecialchars($val); });
            $ips[$v['ipb_address']]['blocked']=1;
            $ips[$v['ipb_address']]['flags']=$this->flag_bits($v, true);
            $ips[$v['ipb_address']]['user']=$v['ipb_by_text'];
            $ips[$v['ipb_address']]['date']=$v['ipb_timestamp'];
            $ips[$v['ipb_address']]['expiry']=$v['ipb_expiry'];
        }
        $rows=$this->log_rows($ip);
        foreach($rows as $v){
            if(preg_match('!^([\d\.]+)/(\d+)$!',$v['log_title']))
                continue;
            if(!isset($ips[$v['log_title']][$v['log_action']]))
                $ips[$v['log_title']][$v['log_action']]=0;
            $ips[$v['log_title']][$v['log_action']]++;
            if(strpos($v['log_params'],'5::duration')!==false){
                $params=unserialize($v['log_params']);
                $len=$this->len_fr($params['5::duration']);
            }else{
                //old format
                $params=explode("\n",$v['log_params']);
                $len=$this->len_fr($params[0]);
            }
            array_walk($v, function(&$val){ $val=htmlspecialchars($val); });
            $by=$v['actor_name'].' '.date('d/m/Y',strtotime($v['log_timestamp']));
            if($v['log_action']=='block'||$v['log_action']=='reblock')
                @$ips[$v['log_title']]['hist'][]="<span title=\"$by\">$len <span class='bhy'>".date('Y',strtotime($v['log_timestamp']))."</span></span>";
            elseif($v['log_action']=='unblock')
                @$ips[$v['log_title']]['hist'][]="<span title=\"$by\">déblocage <span class='bhy'>".date('Y',strtotime($v['log_timestamp']))."</span></span>";
        }
        $order=array_keys($ips);
        natsort($order);
        $o='<table class="mep"><tr><td>';
        $head="<table class='range'><tr class='header'><td>IP</td><td>Edits</td><td><abbr title='Mois différents'>Mois</abbr></td><td colspan='2'><abbr title='Expiration du blocage M/Y'>Blocage</abbr></td><td>Historique des blocages</td><td colspan='3'></td></tr>";
        $o.=$head;
        $i=0;
        foreach($order as $ip){
            if($ip==$range)
                continue;
            $o.=$this->ip_row($ips[$ip],$ip);
            if(count($ips)>10 && ++$i==round(count($ips)/2))
                $o.='</table></td><td>'.$head;
        }
        $o.='</table></td></table>';
        return $o;
    }
    function view_blocks($range)
    {
        if(!preg_match('!^([\d\.]+)/(\d+)$!',$range,$res))
            return false;
        $cidr=$res[2];
        $ip=explode('.',$res[1]);
        if($cidr==24)
            $ip="$ip[0].$ip[1].$ip[2].%";
        elseif($cidr==16)
            $ip="$ip[0].$ip[1].%";
        else
            return false;
        $ips=array();
        $o='';
        $rows=$this->block_rows($ip);
        foreach($rows as $v)
            $blocks[$v['ipb_address']]=$v;
        $rows=$this->log_rows($ip, true);
        if(!empty($rows)){
            $o.='<table class="ranges"><tr class=header><td>Actif</td><td>IP</td><td>Date</td><td>Action</td><td>Durée</td><td>Par</td><td>Options</td><td>Commentaire</td></tr>';
            foreach($rows as $v){
                $v['log_timestamp']=date('d/m/Y H:i:s',strtotime($v['log_timestamp']));
                $b=$this->log_params($v['log_params']);
                array_walk($v, function(&$val){ $val=htmlspecialchars($val); });
                $block='';
                if(isset($blocks[$v['log_title']])){
                    $block=$this->lock_icon(true, $this->flag_bits($blocks[$v['log_title']], true), 0, false,'');
                    unset($blocks[$v['log_title']]);
                }
                $o.="<tr><td>$block</td><td>$v[log_title]</td><td>$v[log_timestamp]</td><td>$v[log_action]</td><td>$b[len]</td><td>$v[actor_name]</td><td>$b[opts]</td><td>".(isset($v['log_comment']) ? htmlspecialchars($v['log_comment']) : '')."</td></tr>";
            }
            $o.='</table>';
            if(count($rows)==$this->max_logs_limit)
                $o.='<p>Résultats limités à 300.</p>';
        }else{
            $o.="<p>Aucun blocage dans le journal pour la plage ".htmlspecialchars($range).".</p>";
        }
        return $o;
    }
    function log_params($params)
    {
        if(preg_match('!^a:\d+:{!', $params)){
            $params=unserialize($params);
            $res['len']=isset($params['5::duration']) ? htmlspecialchars($this->len_fr($params['5::duration'])) : false;
            $res['opts']=htmlspecialchars(@$params['6::flags']);
        }else{
            $params=explode("\n",$params);
            $res['len']=htmlspecialchars($this->len_fr($params[0]));
            $res['opts']=htmlspecialchars(@$params[1]);
        }
        return $res;
    }
    function len_fr($len)
    {
        $len=str_replace('infinite', 'infini', $len);
        $len=str_replace('year', 'an', $len);
        $len=preg_replace('/months?/', 'mois', $len);
        $len=preg_replace('/weeks?/', 'sem.', $len);
        $len=preg_replace('/days?/', 'j', $len);
        $len=preg_replace('/hours?/', 'h', $len);
        $len=preg_replace('/minutes?/', 'm', $len);
        return $len;
    }
    function block_rows($ip)
    {
        if(!$db=get_db2())
            return [];
        //return $db->select("select ipb_address, ipb_by_text, ipb_create_account, ipb_anon_only, ipb_timestamp, ipb_expiry from ipblocks where ipb_user=0 and ipb_address like '$ip' order by ipb_timestamp desc limit {$this->max_logs_limit}");
        return $db->select("select ipb_address, actor_name ipb_by_text, ipb_create_account, ipb_anon_only, ipb_timestamp, ipb_expiry from ipblocks left join actor on ipb_by_actor=actor_id where ipb_user=0 and ipb_address like '$ip' order by ipb_timestamp desc limit {$this->max_logs_limit}");
    }
    function log_rows($ip,$comment=false)
    {
        if(!$db=get_db2())
            return [];
        //TODO join comment
        return $db->select("select log_action, log_title, log_timestamp, log_params, actor_user, actor_name".($comment?"":"")." from logging_logindex left join actor on log_actor=actor_id where log_type='block' and log_title like '$ip' and log_namespace=2 order by log_timestamp desc limit {$this->max_logs_limit}");
    }
    function ip_row($v,$ip)
    {
        $cls=@$v['blocked'] ? " ip_blocked" : (@$v['block']>0 ? " ip_blocked_old" : " ip");
        $fromip=isset($_GET['fromip']) && $_GET['fromip']==$ip ? 'fromip_sel' : '';
        $o="<tr><td class='num $cls $fromip'><a class='wp' href='https://fr.wikipedia.org/wiki/Sp%C3%A9cial:Contributions/$ip'>$ip</a></td>";
        $o.="<td class='num'>".(@$v['edit']>0 ? $v['edit']:0).'</td>';
        $o.="<td class='num'>".(int)@$v['months'].'</td>';
        $exp='';
        if(@$v['blocked']){
            if($v['expiry']=='infinity')
                $exp='indéfini';
            else
                $exp=date('m/y',strtotime($v['expiry']));
        }
        $o.="<td class='nblocks'>".$this->lock_icon(@$v['blocked'],@$v['flags'],@$v['block'],false)."</td><td>$exp</td>";
        $old=array();
        $hist=isset($v['hist'])?$v['hist']:array();
        $hist="<span>".implode(', ',$hist)."</span>";
        $o.="<td class='hist'>$hist</td>";
        if(@$v['edit']>=5)
            $o.='<td><a href="/ip/'.htmlspecialchars($ip).'">stats</a></td>';
        else
            $o.="<td>&nbsp;</td>";
        $o.="<td>".lnk('whois',array('whois'=>$ip),array('ip'),'', $this->path.'/'.$this->range)."</td>";
        if(@$v['blocked'])
            $o.="<td><a class='wp' href='https://fr.wikipedia.org/wiki/Special:Unblock/$ip' rel='nofollow'><abbr title='Débloquer'>Déb.</abbr></a></td>";
        $o.="</tr>";
        return $o;
    }
    function lock_icon($blocked,$flags,$nb=0,$small=false,$infos='')
    {
        $n='';
        if($nb>=1)
            $n=$nb;
        $h=$small?" height='10'":"";
        if(!$blocked){
            if($nb>=1)
                return "<img src='imgi/icons/lock-open.png' title='$infos'$h/>$n";
            return '';
        }
        if($flags==0)
            $type=0;
        else{
            if(!($flags & $this->flags['ipb_create_account']))
                $type=1;
            else
                $type=2;
            if(!($flags & $this->flags['ipb_anon_only']))
                $type=3;
        }
        switch($type){
            case 1 : return "<img src='imgi/icons/lock-grey.png' title='$infos'$h/>$n";
            case 2 : return "<img src='imgi/icons/lock-gold.png' title='$infos'$h/>$n";
            case 3 : return "<img src='imgi/icons/lock-red.png' title='$infos'$h/>$n";
        }
        return false;
    }

    function update($types=array('rir','block', 'log', 'edit', 'proxy'))
    {
        $types=array_flip($types);
        $full_update=count($types)==4;
        $this->total_stats=array();
        $this->s=array();
        $limit="";
        $cols=array();
        if(isset($types['rir'])){
            echo "RIRs\n";
            $this->update_rir();
        }
        if(!$db=get_db2())
            return false;
        if(isset($types['block'])){
            echo "Active blocks ";
            $db->select_walk_block("select ipb_address, actor_name ipb_by_text, ipb_create_account, ipb_anon_only from ipblocks left join actor on ipb_by_actor=actor_id where ipb_user=0 $limit",array($this,'blockrow'), 1000);
            echo " Total active block ranges : ".count($this->s)."\n";
            $cols[]='blocked';
            $cols[]='flags';
            $cols[]='blocked_ips';
        }
        if(isset($types['log'])){
            echo "Block logs ";
            $db->select_walk_block("select log_action, log_title from logging where log_type='block' order by log_timestamp $limit",array($this,'logrow'), 10000);
            echo " Total block ranges : ".count($this->s)."\n";
            $cols[]='blocks';
            $cols[]='unblocks';
            $cols[]='range_blocks';
            $cols[]='block_ips';
        }
        $db->close();
        $db=get_dbs();
        if(isset($types['proxy'])){
            echo "Proxies ";
            $db->select_walk_block("select distinct ipout from proxy.list where confirmed is not null $limit",array($this,'proxyrow'), 10000);
            echo " Total proxy : ".count($this->s)."\n";
            $cols[]='proxy';
        }

        if(isset($types['edit'])){
            echo "Edits ";
            if($this->multi_table)
                $db->select_walk_block("select user,edit from userstats_ip_tot where edit>0 $limit",array($this,'iprow'), 10000);
            else
                $db->select_walk_block("select user,edit from userstats_ip where date_type='T' and edit>0 $limit",array($this,'iprow'), 10000);
            $cols[]='edits';
            $cols[]='ips';
        }
        echo "\nTotal ranges : ".count($this->s)."\n";
        if(empty($cols))
            return false;
        $cols=array_unique($cols);
        echo "Update ";
        $db->query('start transaction');
        $this->stats=array();
        $i=0;
        foreach($this->s as $k=>$v){
            if($full_update)
                @$this->total_stats['plages']++;
            if($i++%10000==0){
                echo ".";
                $db->query('commit');
                $db->query('start transaction');
            }
            $v['range']=$k;
            list($v['start'], $v['end'])=self::parse_range($k);
            if(isset($v['block_ips']))
                $v['block_ips']=count($v['block_ips']);
            $ip=explode('.',$v['ip']);
            $P=$v['prefix1']=$this->pad_ip($ip[0]);
            $v['prefix2']=$this->pad_ip("$ip[0].$ip[1]");
            foreach($cols as $col)
                if(isset($v[$col])){
                    if(!isset($this->stats[$P][$k][$col]))
                        $this->stats[$P][$k][$col]=0;
                    $this->stats[$P][$k][$col]+=$v[$col];
                    unset($v[$col]);
                }
            $db->insert('ranges', $v, false, true);
            unset($this->s[$k]);
        }
        unset($this->s);
        $db->query('commit');
        print_r($this->total_stats);
        $this->save_total_stats();
        echo "\nUpranges  ";
        foreach(array_keys($this->stats) as $prefix){
            echo (int)$prefix;
            $db->query('start transaction');
            $vals=array();
            foreach($cols as $k)
                $vals[]="`$k`=0";
            $db->query("update ranges set ".implode(', ',$vals)." where prefix1='$prefix'");
            echo " ".$db->affected_rows();
            $total_up=$total_sub=0;
            foreach($this->stats[$prefix] as $k=>$v){
                list($start, $end)=self::parse_range($k);
                if(isset($v['blocked']) && $v['blocked']){
                    $block=true;
                    unset($v['blocked']);
                    $flags=$v['flags'] ^ $this->flags['direct'];;
                    unset($v['flags']);
                }else
                    $block=false;
                if(!empty($v)){
                    $vals=array();
                    foreach($v as $kk=>$vv)
                        $vals[]="`$kk`=`$kk`+".$vv;
                    $where="prefix1='$prefix' and start<='".$db->escape($start)."' and end>='".$db->escape($end)."'";
                    $db->query("update ranges set ".implode(', ',$vals)." where $where");
                    $total_up+=$db->affected_rows();
                }
                if($block && $end > $start){
                    $where="prefix1='$prefix' and start>='".$db->escape($start)."' and end<='".$db->escape($end)."'";
                    $db->query("update ranges set blocked=blocked+1, flags=flags | $flags where $where");
                    $total_sub+=$db->affected_rows();
                }
            }
            echo " $total_up $total_sub\n";
            $db->query('commit');
        }
        $db->close();
        echo "\nDone\n";
    }
    function view_total_stats()
    {
        $s=$this->get_total_stats();
        if(!$s)
            return '';
        $o='<div class="total_stats">';
        $o.='<table>';
        $o.="<tr><td></td><td>Total</td><td>Blocages</td><td>Actifs</td></tr>";
        $o.="<tr><td><strong>IP :</strong></td><td>".fnum($s['ip'])."</td><td>".fnum($s['blocages_ip'])."</td><td>".fnum($s['blocages_actifs_ip'])."</td></tr>";
        $o.="<tr><td><strong>Plages :</strong></td><td>".fnum($s['plages'])."</td><td>".fnum(@$s['blocages_plage'])."</td><td>".fnum(@$s['blocages_actifs_plage'])."</td></tr>";
        $o.="</table></div>";
        return $o;
    }
    function save_total_stats()
    {
        if(empty($this->total_stats))
            return;
        $stats=$this->total_stats;
        if(file_exists($this->total_file)){
            $old=unserialize(file_get_contents($this->total_file));
            if(is_array($old))
                foreach($old as $k=>$v)
                    if(!isset($stats[$k]))
                        $stats[$k]=$v;
        }
        file_put_contents($this->total_file,serialize($stats));
    }
    function get_total_stats()
    {
        if(file_exists($this->total_file))
            return unserialize(file_get_contents($this->total_file));
        return false;
    }
    function iprow($rows)
    {
        echo '.';
        foreach($rows as $v){
            if(preg_match('/^((\d{1,3}\.\d{1,3})\.\d{1,3})\.\d{1,3}$/',$v['user'], $res)){
                @$this->total_stats['ip']++;
                $range="$res[1].0/24";
                $this->s[$range]['ip']="$res[1].0";
                $this->s[$range]['cidr']=24;
                $this->s[$range]['ips']= isset($this->s[$range]['ips']) ? $this->s[$range]['ips']+1 : 1;
                $this->s[$range]['edits']=isset($this->s[$range]['edits']) ? $this->s[$range]['edits'] + $v['edit'] : $v['edit'];
                $range="$res[2].0.0/16";
                $this->s[$range]['ip']="$res[2].0.0";
                $this->s[$range]['cidr']=16;
            }
        }
    }
    function logrow($rows)
    {
        echo '.';
        foreach($rows as $v){
            if(preg_match('!^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/?(\d{0,2})$!',$v['log_title'], $res)){
                if($res[2]!=''){
                    $range=$res[0];
                    $this->s[$range]['ip']=$res[1];
                    $this->s[$range]['cidr']=$res[2];
                }else{
                    $ip=explode('.',$res[1]);
                    $ip0="$ip[0].$ip[1].$ip[2].0";
                    $range="$ip0/24";
                    $this->s[$range]['ip']=$ip0;
                    $this->s[$range]['cidr']=24;
                    $this->s["$ip[0].$ip[1].0.0/16"]['ip']="$ip[0].$ip[1].0.0";
                    $this->s["$ip[0].$ip[1].0.0/16"]['cidr']=16;
                }
                if($v['log_action']=='block'){
                    @$this->total_stats['blocages']++;
                    if(!isset($this->s[$range]['blocks']))
                        $this->s[$range]['blocks']=0;
                    $this->s[$range]['blocks']++;
                    if($res[2]!=''){
                        @$this->total_stats['blocages_plage']++;
                        @$this->s[$range]['range_blocks']++;
                    }else{
                        @$this->total_stats['blocages_ip']++;
                        @$this->s[$range]['block_ips'][$res[0]]++;
                    }
                }elseif($v['log_action']=='unblock'){
                    @$this->total_stats['deblocages']++;
                    @$this->s[$range]['unblocks']++;
                    if($res[2]!='')
                        @$this->total_stats['deblocages_plage']++;
                    else
                        @$this->total_stats['deblocages_ip']++;
                }
            }
        }
    }
    function blockrow($rows)
    {
        echo '.';
        foreach($rows as $v){
            if(preg_match('!^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/?(\d{0,2})$!',$v['ipb_address'], $res)){
                @$this->total_stats['blocages_actifs']++;
                if($res[2]!=''){
                    @$this->total_stats['blocages_actifs_plage']++;
                    $range=$res[0];
                    $flags=$this->flag_bits($v, true);
                    $this->s[$range]['blocked']=1;
                    $this->s[$range]['flags']=$flags;
                    $this->s[$range]['ip']=$res[1];
                    $this->s[$range]['cidr']=$res[2];
                }else{
                    @$this->total_stats['blocages_actifs_ip']++;
                    $ip=explode('.',$res[1]);
                    $ip0="$ip[0].$ip[1].$ip[2].0";
                    $range="$ip0/24";
                    $this->s[$range]['ip']=$ip0;
                    $this->s[$range]['cidr']=24;
                    $this->s["$ip[0].$ip[1].0.0/16"]['ip']="$ip[0].$ip[1].0.0";
                    $this->s["$ip[0].$ip[1].0.0/16"]['cidr']=16;
                    if(!isset($this->s[$range]['blocked_ips']))
                        $this->s[$range]['blocked_ips']=0;
                    $this->s[$range]['blocked_ips']++;
                }
            }
        }
    }
    function proxyrow($rows)
    {
        echo '.';
        foreach($rows as $v){
            if(preg_match('/^((\d{1,3}\.\d{1,3})\.\d{1,3})\.\d{1,3}$/',$v['ipout'], $res)){
                $range="$res[1].0/24";
                if(!isset($this->s[$range])){
                    $this->s[$range]['ip']="$res[1].0";
                    $this->s[$range]['cidr']=24;
                }
                @$this->s[$range]['proxy']++;
                $range="$res[2].0.0/16";
                if(!isset($this->s[$range])){
                    $this->s[$range]['ip']="$res[2].0.0";
                    $this->s[$range]['cidr']=16;
                }
            }
        }
    }
    function flag_bits($row, $direct=false)
    {
        $flags=0;
        foreach($this->flags as $k=>$v)
            if(isset($row[$k]) && $row[$k])
                $flags|=$v;
        if($direct)
            $flags|=$this->flags['direct'];
        return $flags;
    }
    function pad_ip($ip)
    {
        $vv=explode('.',$ip);
        foreach($vv as $k=>$v)
            $vv[$k]=str_pad($v,3,'0',STR_PAD_LEFT);
        return implode('.',$vv);
    }
    function rir_name($rir)
    {
        $names=array(
            'afrinic'=>'AfriNIC',
            'apnic'=>'APNIC',
            'arin'=>'ARIN',
            'iana'=>'',
            'lacnic'=>'LACNIC',
            'ripencc'=>'RIPE-NCC',
            );
        return $names[$rir];
    }
    function update_rir()
    {
        $url='http://bgp.potaroo.net/stats/nro/delegated.joint.txt';
        echo "Loading $url\n";
        $data=file_get_contents($url);
        //file_put_contents('/tmp/rir',$data);
        if($data==''){
            echo "No data\n";
            return false;
        }
        $db=get_dbs();
        $db->query('start transaction');
        $data=explode("\n",$data);
        unset($data[0]);
        echo count($data)."\n";
        $i=0;
        foreach($data as $k=>$v){
            $v=explode('|',$v);
            if($v[0]=='' || $v[1]=='*' || ($v[2]!='ipv4' /*&& $v[2]!='ipv6'*/))
                continue;
            $ip=$v[3];
            $size=$v[4];
            $cidr=log($size,2);
            if(bccomp($cidr, round($cidr))!=0){
                echo "Error cidr $cidr ".round($cidr)."\n";
                print_r($v);
                continue;
            }
            $cidr=32-$cidr;
            $range="$ip/$cidr";
            list($start, $end)=self::parse_range($range);
            $d=array('range'=>$range, 'ip'=>$ip, 'cidr'=>$cidr, 'rir'=>$v[0], 'start'=>$start, 'end'=>$end, 'whois_country'=>$v[1]);
            $ip=explode('.',$ip);
            $d['prefix1']=$this->pad_ip($ip[0]);
            $d['prefix2']=$this->pad_ip("$ip[0].$ip[1]");
            $db->insert('ranges', $d, false, true);
            if(++$i%1000==0){
                $db->query('commit');
                $db->query('start transaction');
                echo '.';
            }
        }
        $db->query('commit');
        echo "Done\n";
    }

    function update_whois_loop($refresh_time='-6 months')
    {
        global $conf;
        $db=get_dbs();
        $this->whois_stats();
        $t=time();
        while(true){
            foreach(array('edits'=>60, 'ips'=>10, 'blocks'=>5, 'proxy'=>5) as $k=>$v){
                $rows=$db->select("select * from ranges where (whois_check is null or whois_check<'".date('Y-m-d H:i:s',strtotime($refresh_time))."') order by $k desc, edits desc limit $v");
                $this->update_whois($rows);
                unset($rows);
            }
            echo round(60/((time()-$t)/$this->i['total']))."/mins\n";
            sleep($conf['whois_loop_sleep']);
        }
    }
    function fix_whois($list=false)
    {
        $db=get_dbs();
        if($list==false){
            $rows=$db->select("select * from ranges where whois_owner='' and whois_check<'".date('Y-m-d H:i:s', strtotime('-1 month'))."' order by edits desc limit 500");
        }else{
            if(empty($list))
                return false;
            foreach($list as $v)
                $in[]="'".$db->escape($v)."'";
            $in=implode(', ',$in);
            $rows=$db->select("select * from ranges where `range` in ($in)");
        }
        $this->update_whois($rows);
    }
    function update_whois_range($range)
    {
        $db=get_dbs();
        $rows=$db->select("select * from ranges where `range`='".$db->escape($range)."'");
        if(empty($rows)){
            echo "non trouvé\n";
            echo "raw whois :\n";
            $w=$this->whois($range);
            print_r($w);
            $data=implode("\n",$w['rawdata']);
            print_r($this->extract_whois($data));
            if(preg_match('/^inetnum:\s*([\d\.]+)\s*-\s*([\d\.]+)/im', $data, $r)){
                $cidr=$this->ip2cidr(array($r[1], $r[2]));
                echo $r[0]."\n";
                print_r($cidr);
            }
            return;
        }
        $this->update_whois($rows,true);
    }
    function update_whois_subranges($range)
    {
        $rows=$this->get_subranges($range);
        $this->update_whois($rows,true);
    }
    function update_whois_owner($owner)
    {
        $db=get_dbs();
        $rows=$db->select('select * from ranges where whois_owner like "'.$owner.'" order by edits');
        echo count($rows)." rows\n";
        $this->update_whois($rows,true);
    }
    function import_whois($file)
    {
        if($file=='-')
            $file="php://stdin";
        if(!$f=fopen($file, "r"))
            return false;
        $o='';
        while(!feof($f)){
            $o.=fread($f,16384);
            while(($pos=strpos($o,"\n"))!==false){
                $row=substr($o,0,$pos);
                $o=substr($o,$pos+1);
                if($row==''){
                    $this->import_whois_row(implode("\n", $rows));
                    $rows=array();
                    continue;
                }
                $rows[]=$row;
            }
        }
        print_r($this->i);
    }
    function import_whois_row($data)
    {
        @$this->i['import_whois_rows']++;
        if(preg_match('/^inetnum:\s*([\d\.]+)\s*-\s*([\d\.]+)/im', $data, $r)){
            @$this->i['import_inetnum']++;
            $cidr=$this->ip2cidr(array($r[1], $r[2]));
            $d=$this->extract_whois($data);
            foreach($cidr as $range){
                $db=get_dbs();
                $db->update('ranges', 'range', $range, $d);
                if($db->affected_rows()>=1){
                    @$this->i['import_update']++;
                }else{
                    @$this->i['import_notfound']++;
                }
            }
        }elseif(preg_match('!^route:\s*([\d\.]+/\d+)!im', $data, $r)){
            $range=$r[1];
            @$this->i['import_route']++;
            $d=$this->extract_whois($data);
            $db=get_dbs();
            $db->update('ranges', 'range', $range, $d);
            if($db->affected_rows()>=1){
                @$this->i['import_update']++;
            }else{
                @$this->i['import_notfound']++;
            }
        }
        if($this->i['import_whois_rows']%20000==0)
            print_r($this->i);
    }
    function ip2cidr($ips)
    {
        $res=array();
        $num=ip2long($ips[1])-ip2long($ips[0])+1;
        $bin=decbin($num);
        $chunk=str_split($bin);
        $chunk=array_reverse($chunk);
        $start=0;
        while($start < count($chunk)){
            if($chunk[$start] != 0){
                $start_ip=isset($range) ? long2ip(ip2long($range[1]) + 1) : $ips[0];
                $range=$this->cidr2ip($start_ip . '/' . (32 - $start));
                $res[]=$start_ip . '/' . (32 - $start);
            }
            $start++;
        }
        return $res;
    }
    function cidr2ip($cidr)
    {
        $ip_arr = explode('/', $cidr);
        $start = ip2long($ip_arr[0]);
        $nm = $ip_arr[1];
        $num = pow(2, 32 - $nm);
        $end = $start + $num - 1;
        return array($ip_arr[0], long2ip($end));
    }
    function update_whois($rows=false,$debug=false)
    {
        global $conf;
        require_once('include/common/phpwhois/whois.main.php');
        $whois = new Whois();
        $db=get_dbs();
        if($rows===false)
            $rows=$db->select('select * from ranges where whois_name is null order by edits desc limit 100');
        echo count($rows)."\n";
        foreach($rows as $v){
            echo @++$i.") $v[range] ($v[edits]/$v[ips]/$v[block_ips]/$v[blocks]/$v[proxy]) : ";
            @$this->i['total']++;
            @$this->i['rir'][$v['rir']]++;
            $data='';
            $d=array('whois_check'=>date('Y-m-d H:i:s'));
            $w=$this->whois($v['range']);
            if($debug)
                print_r($w);
            $data=implode("\n",$w['rawdata']);
            if($this->whois_cache)
                $this->save_whois_cache($v['range'], $data);
            if($data==''){
                @$this->i['no range whois']++;
                echo " no range whois ";
            }
            if($data==''){
                $w=$this->whois($v['ip']);
                $data=implode("\n",$w['rawdata']);
                if($data==''){
                    echo " No whois\n";
                    @$this->i['no whois']++;
                    $db->update('ranges','range',$v['range'],$d);
                    continue;
                }
            }
            $d=$this->extract_whois($data);
            echo ' "'.@$d['whois_owner'].'" "'.@$d['whois_name'].'" "'.@$d['whois_net'] ."\"\n";
            $db->update('ranges','range',$v['range'],$d);
            @$this->i['updates']++;
            if($this->i['total']%500==0){
                print_r($this->i);
                $this->whois_stats();
            }
            usleep($conf['whois_row_sleep']*1000);
        }
    }

    function extract_whois($data)
    {
        $d=array('whois_check'=>date('Y-m-d H:i:s'));
        if(preg_match('/^source\s*:\s*(.+)$/im',$data,$res) || preg_match('/(whois\.arin\.net|whois\.lacnic\.net)/',$data,$res))
            @$this->i['source'][$res[1]]++;
        if(isset($w['regyinfo']['servers'][0]['server']))
            foreach($w['regyinfo']['servers'] as $srv)
                @$this->i['server'][$srv['server']]++;
        if(preg_match('/Query rate limit exceeded/i',$data)){
            echo "\nSleep rate limit exceeded/\n";
            print_r($this->i);
            sleep($conf['whois_rate_limit_sleep']);
            return false;
        }
        if(preg_match('/ERROR:201: access denied/i',$data)){
            echo "\nRipe access denied\n";
            print_r($this->i);
            exit;
        }
        if(preg_match('/^country:\s*(.+)$/im',$data,$res))
            $d['whois_country']=preg_replace('!#.+$!', '', trim($res[1]));
        else{
            echo " no coutry";
        }
        if(preg_match('/^CIDR:\s*(.+)$/im',$data,$res))
            $d['whois_cidr']=trim($res[1]);
        if(preg_match('/^inetnum:\s*(.+)$/im',$data,$res))
            $d['whois_net']=trim($res[1]);
        else
            echo " no net";

        if(preg_match('/^netname:\s*(.+)$/im',$data,$res)){
            $d['whois_name']=trim($res[1]);
            if(isset($w['regrinfo']['network']['name']) && $d['whois_name']!=$w['regrinfo']['network']['name'])
                echo " name != ".$w['regrinfo']['network']['name']."\n";
        }
        if(preg_match('/^owner:\s*(.+)$/im',$data,$res))
            $d['whois_owner']=trim($res[1]);
        elseif(preg_match('/^org-name:\s*(.+)$/im',$data,$res))
            $d['whois_owner']=trim($res[1]);
        elseif(preg_match('/^descr:\s*(.+)$/im',$data,$res)){
            $d['whois_owner']=trim($res[1]);
            if($d['whois_owner']=='To determine the registration information for a more')
                $d['whois_owner']='RIPE NCC';
        }elseif(isset($w['regrinfo']['owner']['organization']))
            $d['whois_owner']=$w['regrinfo']['owner']['organization'];
        elseif(preg_match('/^org:\s*(.+)$/im',$data,$res))
            $d['whois_owner']=trim($res[1]);
        elseif(preg_match('/data has been removed from this object/im',$data,$res))
            $d['whois_owner']='(protected)';
        else{
            $d['whois_owner']='(unknown)';
            echo " no owner";
            echo "\n$data\n\n";
        }
        foreach($d as $kk=>$vv){
            if(is_array($vv))
                if(preg_match('/^NCC#/',$vv[0]) && isset($vv[1]))
                    $d[$kk]=$vv[1];
                else
                    $d[$kk]=$vv[0];
        }
        $pool='';
        if(preg_match_all('/^descr\s*:\s*(.*(Dynamic|pool|static).*)$/im',$data,$res)){
            foreach($res[1] as $kk=>$vv)
                if($d['whois_name']==$vv || $d['whois_owner']==$vv)
                    unset($res[1][$kk]);
            if(!empty($res[1]))
                $pool=implode("\n",$res[1]);
        }
        if($pool!='' && @$d['whois_name']=='FR-PROXAD-ADSL'){
            $d['whois_name']=$pool;
            if(isset($w['regrinfo']['owner']['organization']) && count($w['regrinfo']['owner']['organization'])==4)
                $d['whois_name'].=' '.$w['regrinfo']['owner']['organization'][2];
        }
        foreach(array('whois_owner', 'whois_name', 'whois_net') as $k)
            if(preg_match('/Ã©/',@$d[$k]))
                $d[$k]=utf8_decode($d[$k]);// parfois double encodage ??
        return $d;
    }
    function whois_stats()
    {
        $db=get_dbs();
        $t=$db->selectcol("select count(*) from ranges");
        $n=$db->selectcol("select count(*) from ranges where whois_check is not null");
        echo "Total $n/$t ".round(100*$n/$t)."%\n";
        $t=$db->selectcol("select count(*) from ranges where rir!=''");
        $n=$db->selectcol("select count(*) from ranges where rir!='' and whois_check is not null");
        echo "RIR $n/$t ".round(100*$n/$t)."%\n";
        $t=$db->selectcol("select count(*) from ranges where cidr=16");
        $n=$db->selectcol("select count(*) from ranges where cidr=16 and whois_check is not null");
        echo "/16 $n/$t ".round(100*$n/$t)."%\n";
        $t=$db->selectcol("select count(*) from ranges where cidr=24");
        $n=$db->selectcol("select count(*) from ranges where cidr=24 and whois_check is not null");
        echo "/24 $n/$t ".round(100*$n/$t)."%\n";
    }

    static function ip_hex($ip)
    {
        return str_pad(strtoupper(dechex(ip2long($ip))), 8, '0', STR_PAD_LEFT);
    }
    function parse_range($range)
    {
        if(preg_match('!^([\d\.]+)/(\d+)$!', $range, $r)){
            $ip=ip2long($r[1]);
            $ip=$ip & ~((1<<(32-$r[2]))-1);
            $ip=long2ip($ip);
            $s=ranges::ip_hex($ip);
            $e=ranges::ip_hex(long2ip(ip2long($ip)+pow(2,(32-$r[2]))-1));
        }elseif(preg_match('!^([\d\.]+)-([\d\.]+)$!', $range, $r)){
            trigger_error("unsupported range $range");
            return;
        }elseif(preg_match('!^([\d\.]+)$!', $range)){
            $s=$e=ranges::ip_hex($range);
        }else{
            trigger_error("range format ? $range");
            return;
        }
        return [$s, $e];
    }
}

?>