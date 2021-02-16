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

class active_users extends site_page
{

    function __construct()
    {
    }

    function cache_key()
    {
        return '...';
    }
    function valid_cache_date($cache_date)
    {
        return true;
    }
    function view()
    {
        $o='';
        $this->groups=mwTools::user_groups();
        $db=get_dbs();
        $rows=$db->select("select rc_timestamp, rc_user_text, rc_user, user, total, edit, log_sysop, months from wikirepli.recentchanges left join stats.userstats_tot on userstats_tot.user=rc_user_text where rc_timestamp>='".gmdate('YmdHis',strtotime('-3 hours'))."' order by rc_timestamp desc");
        $time=strtotime($rows[0]['rc_timestamp'])+date('Z');
        $o.=" <span class='auhour'>■ ".date('G',$time).'h'.date('i',$time)." ■</span> ";
        $done[date('G-i',$time)]=1;
        $first=true;
        $s=array();
        $tot=0;
        foreach($rows as $v){
            $tot++;
            $time=strtotime($v['rc_timestamp'])+date('Z');
            $hour=date('G',$time);
            $min=date('i',$time);
            if(!isset($done[$hour]) && count($done)>1){
                $h=$hour<=23 ? $hour+1 : 0;
                $o.=" <span class='auhour'>■ {$h} h 00 ■</span> ";
                $first=true;
            }elseif($min!=0 && $min%15==0 && !isset($done["$hour-$min"])){
                $o.=" <span class='auhour'>■ {$hour} h {$min} ■</span> ";
                $done["$hour-$min"]=1;
                $first=true;
            }
            $done[$hour]=1;
            $user=$v['rc_user_text'];
            $user_text=$user;
            if($v['rc_user']==0){
                $cls="auip";
                if(preg_match('/^([a-f\d]+:[a-f\d]+:[a-f\d]+:[a-f\d]+):[a-f\d:]+$/i',$user,$res))
                    $user_text=strtolower($res[1]);
            }elseif(isset($this->groups[$user]['bot']))
                $cls="aubot";
            elseif($v['total']<500 || $v['months']<6)
                $cls='au1';
            elseif($v['total']<5000 || $v['months']<24)
                $cls='au2';
            elseif($v['total']<10000 || $v['months']<48)
                $cls='au3';
            elseif($v['total']<20000 || $v['months']<72)
                $cls='au4';
            else
                $cls='au5';
            @$s[$cls]++;
            if(!$first)
                $o.=" • ";
            else
                $first=false;
            if(mb_strlen($user_text)>30)
                $user_text=mb_substr($user_text,0,29).'…';
            if($v['user']!=''){
                $o.="<a href='/utilisateur/".htmlspecialchars($user)."'".($cls!=''?" class='$cls'":"").">".htmlspecialchars($user_text)."</a>";
            }else
                $o.="<span".($cls!=''?" class='$cls'":"").">".htmlspecialchars($user_text)."</span>";
        }
        foreach($s as $k=>$v)
            $p[$k]=round(100*$v/$tot);
        $h='<div class="active_users">';
        $h.="<div class='note'>Utilisateurs selon le total des actions et les mois participés, détails en infobulle.</div>";
        $h.="<h2>Derniers utilisateurs actifs ";
        $h.="<span class='legend'>
            <span title='IP'><span class='auip'>■</span> <span class='ltext'>$p[auip] %</span></span>
            <span title='< 500 / 6 mois'><span class='au1'>■</span> <span class='ltext'>$p[au1] %</span></span>
            <span title='< 5 000 / 24 mois (2 ans)'><span class='au2'>■</span> <span class='ltext'>$p[au2] %</span></span>
            <span title='< 10 000 / 48 mois (4 ans)'><span class='au3'>■</span> <span class='ltext'>$p[au3] %</span></span>
            <span title='< 20 000 / 72 mois (6 ans)'><span class='au4'>■</span> <span class='ltext'>$p[au4] %</span></span>
            <span title='> 20 000 / 72 mois (6 ans)'><span class='au5'>■</span> <span class='ltext'>$p[au5] %</span></span>
            <span title='Robots'><span class='aubot'>■</span> <span class='ltext'>$p[aubot] %</span></span>
            <span class='ltext'> sur ".fnum($tot)." actions (3h)</span>
            </span>";
        $h.='</h2>';
        return "$h<div class='content'>$o</div></div>";
    }

}

?>