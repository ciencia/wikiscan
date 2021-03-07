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
class TopListStats extends TopList
{
    function __construct($date=false, $filter=false, $sort=false, $mini=false)
    {
        parent::__construct($date, $filter, $sort, $mini);
        $this->data_name='stats';
        $this->list='stats';
        $this->graphs=array('edits','users','nstypes');
    }
    function cache_key()
    {
        return implode(':',array('toplist',$this->data_name,$this->date));
    }
    function render_list()
    {
        global $conf;

        $s=$this->data;
        $o='<table class="list_stats"><tr><td class="mep">';
        //------------------  1  ------------------
        $o.='<h3>'.htmlspecialchars(msg('toplist-stats-title-general')).'</h3>';
        $o.='<table class="list_stats_item">';
        $o.='<tr><td>'.htmlspecialchars(msg('userstat-edit-short')).' :</td><td>'.fnum((int)@$s['total']['edit']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('toplist-stats-edited_pages')).' :</td>';
        if(isset($s['pages']['partial_data']) && $s['pages']['partial_data'])
            $o.='<td>-</td></tr>';
        else
            $o.='<td>'.(@$s['pages']['edited']!=0?fnum($s['pages']['edited']):'-').'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('userstat-log-short')).' :</td><td>'.fnum((int)@$s['total']['log']).'</td></tr>';
        if(@$s['views']['hits']>0)
            $o.='<tr><td>'.htmlspecialchars(msg('stat-pageviews')).' :</td><td>'.format_size(@$s['views']['hits']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-users')).' :</td><td>'.(@$s['users']['user']['total']!=0?fnum(@$s['users']['user']['total']):'-').'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-ip')).' :</td><td>'.(@$s['users']['ip']['total']!=0?fnum($s['users']['ip']['total']):'-').'</td></tr>';
        if(@$s['users']['ipv6']>0)
            $o.='<tr><td>'.htmlspecialchars(msg('stat-ipv6')).' :</td><td>'.fnum($s['users']['ipv6']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-bots')).' :</td><td>'.(@$s['users']['bot']['total']!=0?fnum($s['users']['bot']['total']):'-').'</td></tr>';
        $o.='<tr><td class="list_stats_subtitle">'.htmlspecialchars(msg('toplist-stats-subtitle-with_edits')).' :</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-users')).' :</td><td>'.(@$s['users']['user']['threshold_edits']!=0?fnum(@$s['users']['user']['threshold_edits'][1]):'-').'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-ip')).' :</td><td>'.(@$s['users']['ip']['threshold_edits']!=0?fnum($s['users']['ip']['threshold_edits'][1]):'-').'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-bots')).' :</td><td>'.(@$s['users']['bot']['threshold_edits']!=0?fnum($s['users']['bot']['threshold_edits'][1]):'-').'</td></tr>';
        $o.='</table>';
        $o.='<h3>'.htmlspecialchars(msg('toplist-stats-title-creations')).'</h3>';
        $o.='<table class="list_stats_item">';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-pages')).' :</td><td>'.fnum((int)@$s['total']['new']['total']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-articles')).' :</td><td>'.fnum((int)@$s['total']['new']['article']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-redirects')).' :</td><td>'.fnum((int)@$s['total']['new']['redirect']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-others')).' :</td><td>'.fnum((int)@$s['total']['new']['other']).'</td></tr>';
        $o.='</table>';
        $o.='<h3>'.htmlspecialchars(msg('toplist-stats-title-edits')).'</h3>';
        $o.='<table class="list_stats_item">';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-total')).' :</td><td>'.fnum((int)@$s['total']['edit']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-users')).' :</td><td>'.fnum((int)@$s['user']['edit']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-ip')).' :</td><td>'.fnum((int)@$s['ip']['edit']).'</td></tr>';
        if(@$s['misc']['ipv6_edits']>0)
            $o.='<tr><td>'.htmlspecialchars(msg('stat-ipv6')).' :</td><td>'.fnum((int)@$s['misc']['ipv6_edits']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-bots')).' :</td><td>'.fnum((int)@$s['bot']['edit']).'</td></tr>';
        $o.='<tr><td class="list_stats_subtitle">'.htmlspecialchars(msg('toplist-stats-subtitle-by_type')).' :</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-minors')).' :</td><td>'.fnum((int)@$s['total']['minor']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-reverts')).' :</td><td>'.fnum((int)@$s['total']['revert']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-empty')).' :</td><td>'.fnum((int)@$s['total']['empty_rev']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-hidden')).' :</td><td>'.fnum((int)@$s['total']['deleted_rev']).'</td></tr>';
        $o.='<tr><td class="list_stats_subtitle">'.htmlspecialchars(msg('toplist-stats-subtitle-by_status')).' :</td></tr>';
        if(isset($s['groups'])){
            arsort($s['groups']);
            foreach($s['groups'] as $k=>$v){
                $groupdesc = $k;
                if(array_key_exists($k,$conf['groups']))
                    $groupdesc=$conf['groups'][$k];
                elseif(msg_exists("group-$k"))
                    $groupdesc=msg("group-$k");
                else
                    $groupdesc=$k;
                $o.='<tr><td>'.htmlspecialchars($groupdesc).' :</td><td>'.fnum($v).'</td></tr>';
            }
        }
        $o.='</table>';

        $o.='</td><td class="mep">';
        //------------------  2  ------------------
        $o.='<h3>'.htmlspecialchars(msg('toplist-stats-title-dates')).'</h3>';
        $o.='<table class="list_stats_item">';
        $o.='<tr><td>'.htmlspecialchars(msg('toplist-stats-dates-first')).' :</td><td>'.format_date($s['update']['first'],true).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('toplist-stats-dates-last')).' :</td><td>'.format_date($s['update']['last'],true).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('toplist-stats-dates-update')).' :</td><td>'.format_date($s['update']['last_update'],true).'</td></tr>';
        $o.='</table>';
        $o.='<h3>'.htmlspecialchars(msg('toplist-stats-title-time')).'</h3>';
        $o.='<table class="list_stats_item">';
        $o.='<tr><td colspan="2" style="text-align:center"><small>'.htmlspecialchars(msg('toplist-stats-time-text')).'</small></td></tr>';
        $o.='<tr><td><b>'.htmlspecialchars(msg('toplist-stats-cumulated_time')).' :</b></td><td>'.format_hour(@$s['total']['tot_time2']).'</td></tr>';
        $o.='<tr><td class="list_stats_subtitle" colspan="2">'.htmlspecialchars(msg('toplist-stats-subtitle-by_user_type')).' :</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-users')).' :</td><td>'.format_hour(@$s['user']['tot_time2']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-ip')).' :</td><td>'.format_hour(@$s['ip']['tot_time2']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-bots')).' :</td><td>'.format_hour(@$s['bot']['tot_time2']).'</td></tr>';
        $o.='<tr><td class="list_stats_subtitle" colspan="2">'.htmlspecialchars(msg('toplist-stats-subtitle-by_page_type')).' :</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('nscateg-article')).' :</td><td>'.format_hour(@$s['total']['tot_time2_nscateg']['article']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('nscateg-annexe')).' :</td><td>'.format_hour(@$s['total']['tot_time2_nscateg']['annexe']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('nscateg-talk')).' :</td><td>'.format_hour(@$s['total']['tot_time2_nscateg']['talk']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('nscateg-meta')).' :</td><td>'.format_hour(@$s['total']['tot_time2_nscateg']['meta']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('nscateg-other')).' :</td><td>'.format_hour(@$s['total']['tot_time2_nscateg']['other']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-logs')).' :</td><td>'.format_hour(@$s['total']['tot_time2_log']).'</td></tr>';
        $o.='</table>';
        $o.='<h3>'.htmlspecialchars(msg('toplist-stats-title-texts')).'</h3>';
        $o.='<table class="list_stats_item">';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-diff')).' :</td><td>'.format_diff($s['total']['diff']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-diff_tot')).' :</td><td>'.format_sizei($s['total']['diff_tot']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-tot_size_latest')).' :</td><td>'.format_sizei(@$s['pages']['tot_size_latest']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-tot_size')).' :</td><td>'.format_sizei($s['total']['tot_size']).'</td></tr>';
        $o.='<tr><td class="list_stats_subtitle">'.htmlspecialchars(msg('toplist-stats-subtitle-on_articles')).' :</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-diff')).' :</td><td>'.format_diff(@$s['total']['diff_ns']['article']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-diff_tot')).' :</td><td>'.format_sizei(@$s['total']['diff_tot_ns']['article']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-tot_size_latest')).' :</td><td>'.format_sizei(@$s['pages']['tot_size_latest_ns']['article']).'</td></tr>';
        $o.='<tr><td>'.htmlspecialchars(msg('stat-tot_size')).' :</td><td>'.format_sizei(@$s['total']['tot_size_ns']['article']).'</td></tr>';
        $o.='</table>';
        $o.='</td><td class="mep">';
        //------------------  3  ------------------
        $o.='<h3>'.htmlspecialchars(msg('toplist-stats-title-namespaces')).'</h3>';
        $o.='<table class="list_stats_item">';
        $o.='<tr><td>Article :</td><td>'.fnum((int)@$s['total']['ns'][0]).'</td></tr>';
        foreach(mwns::get()->namespaces() as $no=>$name)
            if($no>0)
                $o.='<tr><td>'.htmlspecialchars($name).' :</td><td>'.fnum((int)@$s['total']['ns'][$no]).'</td></tr>';
        $o.='</table>';
        $o.='<h3>'.htmlspecialchars(msg('toplist-stats-title-logs')).'</h3>';
        $o.='<table class="list_stats_item">';
        if(isset($s['total']['logs'])){
            foreach($s['total']['logs'] as $log=>$actions)
                foreach($actions as $k=>$v)
                    $o.='<tr><td>'.htmlspecialchars($log).' '.htmlspecialchars($k).' :</td><td>'.fnum((int)$v).'</td></tr>';
        }
        $o.='</table>';
        $o.='</td></tr></table>';
        if(DEBUG || isset($_GET['debug']))
            $o.=$this->view_raw($s);
        return $o;
    }
    function view_raw($s, $lvl=0)
    {
        $o='';
        foreach($s as $k=>$v){
            $o.="<pre>".str_repeat('    ',$lvl)."<b>$k : </b>";
            if(is_array($v))
                $o.=$this->view_raw($v,$lvl+1);
            else
                $o.=$v;
            $o.='</pre>';
        }
        return $o;
    }
}

?>