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
require_once('include/graphlist.php');
require_once('include/wikis.php');
require_once('include/graphs.php');
require_once('include/mw/mwns.php');

class WikiHome
{

    function view()
    {
        global $conf;
        $this->wiki=Wikis::get_site_stats($conf['wiki_key']);
        $this->data=Wikis::read_global_data($this->wiki['data']);
        if(empty($this->data['total']['stats']))
            return "Error total stats missing";
        $o='<script src="/libs/d3.min.js"></script>
            <script src="/libs/d3pie.min.js"></script>';

        $o.="<div class=home>";
        $link='<a href="'.$conf['wiki']['url'].'">'.preg_replace('!^www\.|\.org$!', '', $conf['wiki']['site_host']).'</a>';
        $dates='<div class=home_dates>'.date('Y',strtotime($this->data['total']['stats']['update']['first'])).' - '.date('Y',strtotime($this->data['total']['stats']['update']['last'])).'</div>';
        $lastupdate='<div class=home_lastupdate>'.date('Y-m-d',strtotime($this->data['total']['stats']['update']['last'])).'</div>';
        $o.='<h1>'.msg('home_title').' '.$link.$dates.$lastupdate.'</h1>';
        $o.='<div class="home_stats">';
        $o.='<div class="home_row">';
        $o.=$this->main_stats_table();
        $o.=$this->users_stats_table();
        $o.=$this->time_stats_table();
        $o.='</div>';
        $o.='<div class="home_row">';
        $o.=$this->pie_charts();
        $o.='</div>';
        $o.='</div>';
        $o.=$this->history_graphs();
        $o.='</div>';
        if(isset($_GET['debug']))
            $o.=view_array($this->data);
        return $o;
    }
    function main_stats_table()
    {
        $s=$this->data['total']['stats'];
        $o='<div class="home_stats_block">';
        $o.='<h2 class="home_stats_title">'.msg('home_table-contents-title').'</h2>';
        $o.='<table class="home_stats_table">';
        $o.='<tr><th></th><th>'.msg('home_table-contents-articles').'</th><th>'.msg('home_table-contents-total').'</th></tr>';
        $o.='<tr><th class=key>'.msg('home_table-contents-pages').'</th><td class=num>'.fnum($this->wiki['total_article']).'</td><td class=num>'.fnum($this->wiki['total_page']).'</td></tr>';
        $o.='<tr><th class=key>'.msg('home_table-contents-edits').'</th><td class=num>'.fnum($s['total']['ns'][0]).'</td><td class=num>'.fnum($s['total']['edit']).'</td></tr>';
        $o.='<tr><th class=key>'.msg('home_table-contents-actualsize').'</th><td class=num>'.format_sizei($s['total']['diff_ns']['article']).'</td><td class=num>'.format_sizei($s['total']['diff']).'</td></tr>';
        $o.='<tr><th class=key>'.msg('home_table-contents-totalsize').'</th><td class=num>'.format_sizei($s['total']['tot_size_ns']['article']).'</td><td class=num>'.format_sizei($s['total']['tot_size']).'</td></tr>';
        $o.='</table>';
        $o.='</div>';
        return $o;
    }
    function users_stats_table()
    {
        if(!isset($this->data['user_thresholds']['total']['users']))
            return false;
        $s=$this->data['user_thresholds']['total']['users'];
        $months=$this->data['user_thresholds']['months'];
        krsort($months);
        $i=0;
        foreach($months as $month=>$v){
            if(empty($v) || $month==gmdate('Ymd'))
                continue;
            foreach(array(1,5,100,1000) as $edit_limit)
                foreach(array('users'/*, 'edits', 'tot_time2'*/) as $col)
                    $avg[$col][$edit_limit][]=(int)@$v['users']['edit'][$edit_limit][$col];
            if(++$i==12)
                break;
        }
        foreach($avg as $col=>$vv)
            foreach($vv as $edit_limit=>$v)
                $avg[$col][$edit_limit]=round(array_sum($v)/count($v));
        $o='<div class="home_stats_block">';
        $o.='<h2 class="home_stats_title">'.msg('home_table-users-title').'</h2>';
        $o.='<table class="home_stats_table">';
        $o.='<tr><th></th><th>'.msg('home_table-users-actives').'</th><th>'.msg('home_table-users-total').'</th></tr>';
        $o.='<tr><th class=key>'.msg('home_table-users-users').'</th><td class=num>'.fnum(@$avg['users'][1]).'</td><td class=num>'.fnum(@$s['edit'][1]['users']).'</td></tr>';
        $o.='<tr><th class=key>'.msg('home_table-users-users5').'</th><td class=num>'.fnum(@$avg['users'][5]).'</td><td class=num>'.fnum(@$s['edit'][5]['users']).'</td></tr>';
        $o.='<tr><th class=key>'.msg('home_table-users-users100').'</th><td class=num>'.fnum(@$avg['users'][100]).'</td><td class=num>'.fnum(@$s['edit'][100]['users']).'</td></tr>';
        $o.='<tr><th class=key>'.msg('home_table-users-users1000').'</th><td class=num>'.fnum(@$avg['users'][1000]).'</td><td class=num>'.fnum(@$s['edit'][1000]['users']).'</td></tr>';
        $o.='</table>';
        $o.='</div>';
        return $o;
    }

    /*
     * Average by day table
     * 
     * This table needs the fullupdate_months update to be run 
     */
    function time_stats_table()
    {
        if(!isset($this->data['total']['time']))
            return false;
        $months=$this->data['total']['time'];
        krsort($months);
        $avg=[];
        $i=0;
        foreach($months as $month=>$v){
            $avg['edits'][]=$v['edit'];
            $avg['edits_article'][]=@$v['article'];
            $avg['new_pages'][]=@$v['new']['total'];
            $avg['new_articles'][]=@$v['new']['article'];
            $avg['diff_pages'][]=isset($v['diff']) ? array_sum($v['diff']) : null;
            $avg['diff_articles'][]=@$v['diff']['article'];
            if(++$i==12)
                break;
        }
        foreach($avg as $col=>$v)
            $avg[$col]=round(array_sum($v)/count($v));
        $o='<div class="home_stats_block">';
        $o.='<h2 class="home_stats_title">'.msg('home_table-time-title').'</h2>';
        $o.='<table class="home_stats_table">';
        $o.='<tr><th></th><th>'.msg('home_table-time-articles').'</th><th>'.msg('home_table-time-total').'</th></tr>';
        $o.='<tr><th class=key>'.msg('home_table-time-edits').'</th><td class=num>'.fnum(@$avg['edits_article']).'</td><td class=num>'.fnum(@$avg['edits']).'</td></tr>';
        $o.='<tr><th class=key>'.msg('home_table-time-creations').'</th><td class=num>'.fnum(@$avg['new_articles']).'</td><td class=num>'.fnum(@$avg['new_pages']).'</td></tr>';
        $o.='<tr><th class=key>'.msg('home_table-time-contents').'</th><td class=num>'.format_sizei(@$avg['diff_articles']).'</td><td class=num>'.format_sizei(@$avg['diff_pages']).'</td></tr>';
        $o.='</table>';
        $o.='</div>';
        return $o;
    }

    function pie_charts()
    {
        $s=$this->data['total']['stats'];
        $o='<div class="home_pie_charts">';
        $o.=$this->d3pie('pie_nscateg', $this->d3pie_data($this->translate_nscateg($s['total']['nscateg'])), msg('home-piechart_title-nscateg'));
        $data=$s['total']['ns'];
        unset($data[0]);
        $ns_stats=$s['total']['ns'];
        unset($ns_stats[0]);
        $ns_stats=$this->ns_stats($ns_stats);
        $o.=$this->d3pie('pie_ns2', $this->d3pie_data($ns_stats), msg('home-piechart_title-nomain'));

        $data=array(
            array('label'=>msg('stat-users'), 'value'=>@$s['user']['edit']),
            array('label'=>msg('stat-ip'), 'value'=>@$s['ip']['edit']),
            array('label'=>msg('stat-bots'), 'value'=>@$s['bot']['edit']),
        );
        $o.=$this->d3pie('pie_users', $data, msg('home-piechart_title-usertypes'));
        $o.=$this->d3pie('pie_tot_size_ns', $this->d3pie_data($this->translate_nscateg($s['total']['tot_size_ns'])), msg('home-piechart_title-nshistory'));
        $new=$s['total']['new'];
        unset($new['total']);
        $o.=$this->d3pie('pie_new', $this->d3pie_data($this->translate_nscateg($new)), msg('home-piechart_title-new_nscateg'));
        $ns_stats=$this->ns_stats($s['total']['new_ns']);
        $o.=$this->d3pie('pie_new_ns', $this->d3pie_data($ns_stats), msg('home-piechart_title-new_ns'));
        $data=array(
            array('label'=>msg('stat-users'), 'value'=>@$s['user']['new']['article']),
            array('label'=>msg('stat-ip'), 'value'=>@$s['ip']['new']['article']),
            array('label'=>msg('stat-bots'), 'value'=>@$s['bot']['new']['article']),
        );
        $o.=$this->d3pie('pie_users_new', $data, msg('home-piechart_title-new_usertypes'));
        $o.='</div>';
        return $o;
    }
    function ns_stats($ns_stats)
    {
        $total=array_sum($ns_stats);
        arsort($ns_stats);
        $misc=0;
        $res=array();
        foreach($ns_stats as $ns=>$v){
            if($v>=0.01*$total)
                $res[mwns::get()->ns_name($ns)]=$v;
            else
                $misc+=$v;
        }
        $res[msg('ns-others')]=$misc;
        return $res;
    }
    function translate_nscateg($data)
    {
        $res=array();
        foreach($data as $k=>$v)
            $res[msg("nscateg-$k")]=$v;
        return $res;
    }

    function d3pie_data($data)
    {
        $res=array();
        foreach($data as $k=>$v)
            $res[]=array('label'=>$k, 'value'=>$v);
        return $res;
    }
    function d3pie($name, $data, $title='', $size=180, $inner=true, $pieOuterRadius=100)
    {
        return "<div id='$name' class='home_pie'></div>"
            .'<script>
var pie = new d3pie("'.$name.'", {
    "size": {
        "canvasWidth": '.$size.',
        "canvasHeight": '.$size.',
        "pieOuterRadius": "'.$pieOuterRadius.'%"       ,
        "pieInnerRadius": "0%"
    },
    "header": {
        "title": {
            "text": "'.$title.'",
            "fontSize": 12,
            "font": "open sans"
        },
        "subtitle": {
            "text": "",
            "color": "#999999",
            "fontSize": 12,
            "font": "open sans"
        },
        "titleSubtitlePadding": 9
    },/*
    "footer": {
        "text": "'.$title.'",
        "color": "#666666",
        "fontSize": 12,
        "font": "open sans",
        "location": "bottom-center"
    },*/
    "data": {
        "sortOrder": "value-desc",
        "content": '.json_encode($data).'
    },
    "labels": {'
    . ($inner ?
        '"outer": {
            "format": "none",
            "pieDistance": 1
        },
        "inner": {
            "format": "label-percentage1",
            "hideWhenLessThanPercentage": 3
        },' :
        '"outer": {
            "format": "label",
            "pieDistance": 10
        },
        "inner": {
            "format": "percentage",
            "hideWhenLessThanPercentage": 3
        },'
        ).'
        "mainLabel": {
            "color": "#000",
            "fontSize": 10
        },
        "percentage": {
            "color": "#ffffff",
            "decimalPlaces": 0
        },
        "value": {
            "color": "#adadad",
            "fontSize": 11
        },
        "lines": {
            "enabled": true
        },
        "truncation": {
            "enabled": true
        }
    },
    "effects": {
        "load": {
            "effect": "none",
            "speed": 200
        },
        "pullOutSegmentOnClick": {
            "effect": "linear",
            "speed": 400,
            "size": 8
        }
    },
    "misc": {
        "colors": {
            "segmentStroke": "#f0f0f0"
        },
        "gradient": {
            "enabled": false,
            "percentage": 50
        }
    }
});
</script>';
    }


    function history_graphs()
    {
        if(empty($this->data))
            return false;
        if(!isset($this->data['total']['time']))
            return false;
        $time=$this->data['total']['time'];
        $cols=array('uuser', 'user_edit', 'uip', 'ip_edit', 'ubot', 'bot_edit');
        $data_cols=array();
        $average_cols=array();
        $max_cols=array();
        foreach($time as $month=>$v)
            foreach($cols as $col){
                $data_cols[$col][$month]=isset($v[$col]) ? $v[$col] : 0;
                if(!isset($max_cols[$col]) || $data_cols[$col][$month]>$max_cols[$col])
                    $max_cols[$col]=$data_cols[$col][$month];
            }
        foreach($cols as $col)
            $average_cols[$col]=Graphs::data_average($data_cols[$col], 12, 4);
        $height=200;
        $xinc=4;
        $width=count($time)*$xinc;
        $o="<div class='home_graphs'>";
        $cols=array(
            array('uuser', 'user_edit'),
            array('uip', 'ip_edit'),
            array('ubot', 'bot_edit'),
            );
        foreach($cols as $cs){
            foreach($cs as $col){
                $o.="<div class=home_graph><div class=home_graph_inner>";
                $o.='<div class="home_graphs_title">'.msg("homegraphs-title-$col").'</div>';
                $o.='<table class=mep><tr><td>';
                $o.=$this->view_home_table($data_cols[$col]);
                $o.='</td><td>';
                $class=isset(GraphList::$classes[$col]) ? GraphList::$classes[$col] : "graph_$col";
                $o.="<svg viewBox='0 0 $width $height' xmlns='http://www.w3.org/2000/svg' version='1.1'>\n";
                $o.=Graphs::graph_path($data_cols[$col], array_keys($time), $height, $max_cols[$col], $xinc, $class);
                $o.=Graphs::graph_line($average_cols[$col], array_keys($time), $height, $max_cols[$col], $xinc, 'graph_line_back');
                $o.=Graphs::graph_line($average_cols[$col], array_keys($time), $height, $max_cols[$col], $xinc, 'graph_line');
                $o.=Graphs::graph_axes(array_keys($time), $height, $max_cols[$col], max($average_cols[$col]), $xinc, 'graph_axe');
                $o.="</svg>";
                $o.='</td></tr></table>';
                $o.="</div></div>";
            }
        }
        return $o."</div>";
    }
    function view_home_table($data)
    {
        $o='<table class=home_table>';
        $years=array();
        foreach($data as $month=>$v)
            $years[substr($month,0,4)][]=$v;
        $rows=array();
        foreach($years as $year=>$months){
            $v=array_sum($months)/count($months);
            $evo= isset($last) && $last!=0 ? 100*($v-$last)/$last : false;
            $rows[$year]=array('value'=>$v, 'evo'=>$evo);
            $last=$v;
        }
        $rows=array_reverse($rows, true);
        $i=0;
        foreach($rows as $year=>$v){
            $o.="<tr><th>$year</th><td>".fnum($v['value'], 2)."</td><td class=evo>";
            if($v['evo']!==false){
                if($v['evo']<=500)
                    $o.=plus(round($v['evo'],1)).fnum($v['evo'], 1).'&nbsp;%';
                else
                    $o.='>'.fnum(500).'&nbsp;%';
            }
            $o.="</td></tr>";
            if(++$i==7)
                break;
        }
        $o.='</table>';
        return $o;
    }

}


?>