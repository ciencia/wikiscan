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
require_once('graph.php');
class GraphList
{
    static $classes=array(
        'uuser'=>'graph_user',
        'uip'=>'graph_ip',
        'ubot'=>'graph_bot',
        );

    static function toplist($date)
    {
        $o='';
        // FIXME: Localize
        if($date<=1)
            $alt='pour la dernière heure';
        elseif($date<=48)
            $alt="pour les dernières $date heures";
        else
            $alt=Dates::format($date);
        $graphs=array('edits'=>'des modifications','users'=>'des utilisateurs','nstypes'=>'des espaces de noms');
        $date=htmlspecialchars($date);
        foreach($graphs as $t=>$v)
            $oo[]="<a href='/gimg.php?type=$t&amp;date=$date&amp;size=big'>"
                ."<img src='/gimg.php?type=$t&amp;date=$date&amp;size=medium' alt='".htmlspecialchars("Graphique $v $alt")."'/></a>";
        $o.='<div class="graphs_toplist">'.implode('<br/>',$oo).'</div>';
        return $o;
    }

    static function total_graph()
    {
        $o='';
        $time=UpdateStats::load_stat(0,'time');
        $months=UpdateStats::average_time_months($time);
        $o.=self::svg_graph(array('user_edit', 'ip_edit', 'bot_edit'), 30, 1, $months);
        $o.=self::svg_graph(array('uuser', 'uip', 'ubot'), 30, 1, $months);
        return $o;
    }
    static function svg_graphs()
    {
        if(!$time=UpdateStats::load_stat(0,'time'))
            return false;
        $months=UpdateStats::average_time_months($time);
        $res['edits']=self::svg_graph(array('user_edit', 'ip_edit', 'bot_edit'), 30, 1, $months);
        $res['users']=self::svg_graph(array('uuser', 'uip', 'ubot'), 30, 1, $months);
        return $res;
    }
    static function svg_graph($cols, $height, $xinc, $months, $min=false, $reduce=false)
    {
        if(empty($months))
            return false;
        if($min!==false)
            foreach($months as $month=>$v)
                if($month<$min)
                    unset($months[$month]);
        if($reduce!==false)
            $months=UpdateStats::reduce_time_months($months, $reduce);
        $width=count($months)*$xinc;
        $o="<svg viewBox='0 0 $width $height' preserveAspectRatio='none' xmlns='http://www.w3.org/2000/svg' version='1.1'>\n";
        $o.=self::graph_paths($months, $cols, $height, $xinc, $min);
        $o.="</svg>";
        return $o;
    }
    static function graph_paths($months, $cols, $h, $xinc=1, $min=false)
    {
        $o='';
        $max=0;
        foreach($months as $month=>$v){
            if($min!==false && $month<$min){
                unset($months[$month]);
                continue;
            }
            $tot=0;
            foreach($cols as $col){
                $vv=!is_array($col) ? (isset($v[$col]) ? $v[$col] : 0) : (isset($v[$col[0]][$col[1]]) ? $v[$col[0]][$col[1]] : 0);
                $tot+=$vv;
            }
            if($tot>$max)
                $max=$tot;
        }
        $x=0;
        foreach($months as $month=>$v){
            foreach($cols as $k=>$col){
                if(!is_array($col)){
                    $vv=isset($v[$col]) ? $v[$col] : 0;
                }else{
                    $vv=isset($v[$col[0]][$col[1]]) ? $v[$col[0]][$col[1]] : 0;
                    $col=implode('-',$col);
                }
                if($k==0)
                    $points[$col][$x]=$h-($max!=0 ? round($h*@$vv/$max) : 0);
                else{
                    $prev=$cols[$k-1];
                    if(is_array($prev))
                        $prev=implode('-',$prev);
                    $points[$col][$x]=$points[$prev][$x]-($max!=0 ? round($h*@$vv/$max) : 0);
                }
            }
            $x+=$xinc;
        }
        foreach(array_reverse($cols, true) as $k=>$col){
            if(is_array($col))
                $col=implode('-',$col);
            $class=isset(self::$classes[$col]) ? self::$classes[$col] : "graph_$col";
            if($k>=1){
                $prev=$cols[$k-1];
                if(is_array($prev))
                    $prev=implode('-',$prev);
                $o.="<path class='$class' d='M0,".$points[$prev][0];
                foreach($points[$col] as $x=>$y)
                    $o.=" $x,$y";
                $o.=" $x,".$points[$prev][$x];
                foreach(array_reverse($points[$prev], true) as $x=>$y)
                    $o.=" $x,$y";
                $o.=" Z'></path>\n";
            }else{
                $o.="<path class='$class' d='M0,$h";
                foreach($points[$col] as $x=>$y)
                    $o.=" $x,$y";
                $o.=" $x,$h Z'></path>\n";
            }
        }
        return $o;
    }

    static function ns_pie($date)
    {
        $o='<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/3.5.6/d3.min.js" charset="utf-8"></script>';
        $o.="<div class=ns_charts>";
        $stats=UpdateStats::load_stat($date,'stats');
        $ns_stats=$stats['total']['ns'];
        arsort($ns_stats);
        $mwns=mwns::get();
        $total=array_sum($ns_stats);
        $data=array();
        $v=$ns_stats[0];
        $data[]='{"label":"'.$mwns->ns_name(0).' ('.round(100*$v/$total).'%)'.'", "value":'.$v.'}';
        $data[]='{"label":"'.'Autres'.'", "value":'.($total-$v).'}';
        $o.=self::pie('ns_main_chart', $data);
        $data=array();
        $other=0;
        foreach($ns_stats as $ns=>$v){
            if($ns==0)
                continue;
            if($v>=0.01*$total)
                $data[]='{"label":"'.$mwns->ns_name($ns).'", "value":'.$v.'}';
            else
                $other+=$v;
        }
        if($other>=1)
            $data[]='{"label":"'.'Autres'.'", "value":'.$other.'}';
        $o.=self::pie('ns_chart', $data);

        $o.="</div>";
        return $o;
    }
    static function pie($id, $data)
    {
        return '<div id="'.$id.'" class="ns_chart"></div><script type="text/javascript">
var w = 200;
var h = 200;
var r = h/2;
var color = d3.scale.category20();

var data = ['.implode(',', $data).'];


var vis = d3.select("#'.$id.'").append("svg:svg").data([data]).attr("width", w).attr("height", h).append("svg:g").attr("transform", "translate(" + r + "," + r + ")");
var pie = d3.layout.pie().value(function(d){return d.value;});

// declare an arc generator function
var arc = d3.svg.arc().outerRadius(r);

// select paths, use arc generator to draw
var arcs = vis.selectAll("g.slice").data(pie).enter().append("svg:g").attr("class", "slice");
arcs.append("svg:path")
    .attr("fill", function(d, i){
        return color(i);
    })
    .attr("d", function (d) {
        // log the result of the arc generator to show how cool it is :)
        console.log(arc(d));
        return arc(d);
    });

// add the text
arcs.append("svg:text").attr("transform", function(d){
            d.innerRadius = 0;
            d.outerRadius = r;
    return "translate(" + arc.centroid(d) + ")";}).attr("text-anchor", "middle").text( function(d, i) {
    return data[i].label;}
        );    </script>';
    }

    static function edits_graphs($date)
    {
        $step=60;
        $stats=UpdateStats::load_stat($date,'time');
        reset($stats);
        $start=key($stats);
        $min=(int)substr($start,2,2);
        $hour=(int)substr($start,0,2);
        $data=array();
        $cont=true;
        $uniques=array('list_user', 'list_ip', 'list_bot');
        for($h=$hour;$h<24;$h++){
            $h=str_pad($h,2,'0',0);
            for($m=$min;$m<60;$m++){
                $key=$h.str_pad($m,2,'0',0);
                $ms=floor($m/$step)*$step;
                $ms=str_pad($ms,2,'0',0);
                if(isset($stats[$key])){
                    @$data[$h]['user_edit']+=@$stats[$key]['user_edit'];
                    @$data[$h]['ip_edit']+=@$stats[$key]['ip_edit'];
                    @$data[$h]['bot_edit']+=@$stats[$key]['bot_edit'];
                    foreach($uniques as $list)
                        if(isset($stats[$key][$list]))
                            foreach($stats[$key][$list] as $kk=>$vv)
                                @$data[$h][$list][$kk]+=$vv;
                    unset($stats[$key]);
                }else{
                    if(!isset($data[$h]))
                        $data[$h]=array();
                }
                if(empty($stats))
                    break 2;
            }
            $min=0;
            if($cont && $h==23){
                $h=-1;
                $cont=false;
            }
        }

        $o="";
        $rows=array();
        foreach($data as $k=>$v){
            $row="{'time':'$k'";
            foreach(array('user_edit', 'ip_edit', 'bot_edit') as $kk)
                $row.=",'$kk':".(int)@$v[$kk];
            $row.="}";
            $rows[]=$row;
        }
        $o.="<div class=ns_charts>";
        $o.=self::edits_bar('edits_bar', '['.implode(',', $rows).']');
        $o.="</div>";
        $rows=array();
        foreach($data as $k=>$v){
            $row="{'time':'$k'";
            foreach($uniques as $kk)
                $row.=",'$kk':".count(@$v[$kk]);
            $row.="}";
            $rows[]=$row;
        }
        $data = '['.implode(',', $rows).']';
        $o.="<div class=ns_charts>";
        $o.=self::edits_bar('users_bar', '['.implode(',', $rows).']');
        $o.="</div>";
        return $o;
    }

    static function edits_bar($id, $data)
    {

    return '<div id="'.$id.'" class="ns_chart"></div><script type="text/javascript">
var margin = {top: 20, right: 20, bottom: 30, left: 40},
    width = 500 - margin.left - margin.right,
    height = 300 - margin.top - margin.bottom;

var x = d3.scale.ordinal()
    .rangeRoundBands([0, width], .1);

var y = d3.scale.linear()
    .rangeRound([height, 0]);

var color = d3.scale.ordinal()
    .range(["#5588ff", "#88aaff", "#aaddff", "#6b486b", "#a05d56", "#d0743c", "#ff8c00"]);

var xAxis = d3.svg.axis()
    .scale(x)
    .orient("bottom");

var yAxis = d3.svg.axis()
    .scale(y)
    .orient("left")
    .tickFormat(d3.format(".2s"));

var svg = d3.select("#'.$id.'").append("svg")
    .attr("width", width + margin.left + margin.right)
    .attr("height", height + margin.top + margin.bottom)
.append("g")
    .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

    var data = '.$data.';
    /*
    [
        {"time":1, "a":5871, "b":8916, "c":2868},
        {"time":2, "a":10048, "b":2060, "c":6171},
        {"time":3, "a":16145, "b":8090, "c":8045}
    ];
    */

/*d3.csv("out/data.csv", function(error, data) {
if (error) throw error;*/

color.domain(d3.keys(data[0]).filter(function(key) { return key !== "time"; }));

data.forEach(function(d) {
    var y0 = 0;
    d.ages = color.domain().map(function(name) { return {name: name, y0: y0, y1: y0 += +d[name]}; });
    d.total = d.ages[d.ages.length - 1].y1;
});

/*data.sort(function(a, b) { return b.total - a.total; });*/

x.domain(data.map(function(d) { return d.time; }));
y.domain([0, d3.max(data, function(d) { return d.total; })]);

svg.append("g")
    .attr("class", "x axis")
    .attr("transform", "translate(0," + (height+2) + ")")
    .call(xAxis);

svg.append("g")
    .attr("class", "y axis")
    .call(yAxis);
    /*
    .append("text")
    .attr("transform", "rotate(-90)")
    .attr("y", 6)
    .attr("dy", ".71em")
    .style("text-anchor", "end")
    .text("Population");*/

var state = svg.selectAll(".state")
    .data(data)
    .enter().append("g")
    .attr("class", "g")
    .attr("transform", function(d) { return "translate(" + x(d.time) + ",0)"; });

state.selectAll("rect")
    .data(function(d) { return d.ages; })
    .enter().append("rect")
    .attr("width", x.rangeBand())
    .attr("y", function(d) { return y(d.y1); })
    .attr("height", function(d) { return y(d.y0) - y(d.y1); })
    .style("fill", function(d) { return color(d.name); });

var legend = svg.selectAll(".legend")
    .data(color.domain().slice().reverse())
    .enter().append("g")
    .attr("class", "legend")
    .attr("transform", function(d, i) { return "translate(0," + i * 20 + ")"; });

legend.append("rect")
    .attr("x", width - 18)
    .attr("width", 18)
    .attr("height", 18)
    .style("fill", color);

legend.append("text")
    .attr("x", width - 24)
    .attr("y", 9)
    .attr("dy", ".35em")
    .style("text-anchor", "end")
    .text(function(d) { return d; });

/*});*/
</script>';

    }


    function months()
    {
        $o='<div class="graphs_months">';
        $cols=array(array('users_active','user'),array('users_med_active','user'),array('users_very_active','user'),array('users_active_totarch','user'));
        $file='active_users.csv';
        $this->csv($cols, $file);
        $o.=$this->graph_d('graph_active_users', $file);

        $file="edits.csv";
        $this->csv(array('edit', 'log', array('ns',0), array('logs', 'articlefeedbackv5', 'create'), array('logs', 'block', 'block')), $file);
        $o.=$this->graph_d("graph_edits", $file);

        $file="editors.csv";
        $this->csv(array(array('editors', 'user'), array('editors', 'bot'), array('editors', 'ip'), 'revert'), $file);
        $o.=$this->graph_d("graph_editors", $file);

        $file="new.csv";
        $this->csv(array(array('new', 'article'), array('logs', 'delete', 'delete')), $file);
        $o.=$this->graph_d("graph_new", $file);

        $file="logs.csv";
        $this->csv(array('log', array('logs', 'block', 'block'), array('logs', 'delete', 'delete'), array('logs', 'articlefeedbackv5', 'create')), $file);
        $o.=$this->graph_d("graph_logs", $file);

        $file="active_users_ns.csv";
        $this->csv(array(array("users_active",'user'),array("users_active_main",'user'),array("users_active_meta",'user'),array("users_active_talk",'user')), $file);
        $o.=$this->graph_d("graph_active_users_ns", $file);

        foreach(array('main','meta','talk') as $k){
            $cols=array(array("users_active_$k",'user'),array("users_med_active_$k",'user'),array("users_very_active_$k",'user'));
            $file="active_users_$k.csv";
            $this->csv($cols, $file);
            $o.=$this->graph_d("graph_active_users_$k", $file);
        }
        $o.='</div>';
        return $o;
    }
    function graph_d($name,$file)
    {
        return '
            <script type="text/javascript" src="/libs/dygraph-combined.js"></script>
            <div class="graph_d"><div id="'.$name.'_legend" class="legend"></div><div id="'.$name.'" style="width:900px; height:350px;"></div></div>
            <script type="text/javascript">
            var opts = {
                legend: "always",
                labelsDiv : "'.$name.'_legend",
                series: {},
                ylabel: "Utilisateurs",
                y2label: "y2",
                axes: {
                    x: {
                        drawGrid: false,
                    },
                    y: {
                        drawGrid: true,
                        independentTicks: true,
                    },
                    y2: {
                        independentTicks: true,
                        drawGrid: false,
                        gridLineColor: "#999999"
                    }
                }
            };
            g2 = new Dygraph(
                document.getElementById("'.$name.'"),
                "export/'.$file.'",
                opts
            );
            </script>';
    }
    function csv($cols,$name)
    {
        $o=$this->export_csv($cols);
        file_put_contents("export/$name",$o);
    }
    function export_csv($cols)
    {
        $s=$this->export_cols($cols);
        $months=$this->months_list($s);
        foreach($cols as $v)
            $empty[]='';
        $first=current($s);
        $o="date,".implode(',',array_keys($first))."\n";
        foreach($months as $date){
            $o.=$date."01,";
            if(isset($s[$date]))
                $o.=implode(',',$s[$date])."\n";
            else
                $o.=implode(',',$empty)."\n";
        }
        return $o;
    }
    function export_cols($cols,$norm_months=true)
    {
        $res=array();
        $s=UpdateStats::stats_months();
        foreach($s as $k=>$v){
            foreach($cols as $col){
                if(is_array($col)){
                    $base=isset($s[$k]) ? $s[$k] : 0;
                    foreach($col as $key)
                        if(isset($base[$key]))
                            $base=$base[$key];
                        else{
                            $base=0;
                            break;
                        }
                    $col=implode('-',$col);
                    $val=$base;
                }else
                    $val=isset($s[$k][$col]) && !is_array($s[$k][$col]) ? $s[$k][$col] : 0;
                if($norm_months){
                    $date=$k."01";
                    $val=round(30*$val/date('t',strtotime($date)));
                }
                $res[$k][$col]=$val;
            }
        }
        return $res;
    }
    function months_list($s)
    {
        $res=array();
        $first=key($s);
        end($s);
        $end=key($s);
        $miny=(int)substr($first,0,4);
        $minm=(int)substr($first,4,2);
        $maxy=(int)substr($end,0,4);
        $maxm=(int)substr($end,4,2);
        for($y=$miny;$y<=$maxy;$y++){
            for($m=$minm;$m<=12;$m++){
                if($y==$maxy && $m>$maxm)
                    break;
                if($m<10)
                    $m='0'.$m;
                $res[]=$y.$m;
            }
            $minm=1;
        }
        return $res;
    }

}
?>
