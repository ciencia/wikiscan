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

function cache_key($params)
{
    global $conf, $InterfaceLanguage;
    if(is_array($params))
        $params=implode(':',$params);
    if(is_object($InterfaceLanguage))
        $params=$InterfaceLanguage->get_lang().':'.$params;
    return $conf['cache_key_global'].':'.$conf['cache_key_site'].':'.$params;
}

function get_cache()
{
    global $Cache, $conf;
    if(!class_exists('mCache'))
        require_once('include/common/mCache.php');
    if(!is_object($Cache)){
        $Cache=new mCache();
        if($conf['memcache_host']!=''){
            $Cache->host=$conf['memcache_host'];
            $Cache->port=$conf['memcache_port'];
            $Cache->init();
        }
    }
    return $Cache;
}

function wiki_api($dest=false)
{
    global $conf;
    if($dest!==false)
        return new wiki_api($dest);
    elseif(isset($conf['mw_api']) && $conf['mw_api']!='')
        return new wiki_api($conf['mw_api']);
    else
        return false;
}

function lnk($label,$attr,$preserve=false,$title='',$base='',$id=false)
{
    if($preserve===false)
        $preserve=array('menu','date','list');
    foreach($preserve as $k)
        if(isset($_GET[$k]) && !isset($attr[$k]))
            $attr[$k]=$_GET[$k];
    if((@$attr['menu']=='dates'||@$attr['menu']=='live') && isset($attr['date']) && ((isset($attr['list']) && count($attr)==3)||(!isset($attr['list']) && count($attr)==2))){
        $list=!isset($attr['list']) ? 'pages' : $attr['list'];
        $menu=$attr['menu']=='dates' ? msg('urlpath-menu-date') : msg('urlpath-menu-live');
        return '<a href="/'.$menu.'/'.(int)$attr['date'].'/'.$list.'">'.$label.'</a>';
    }
    $o='<a href="'.$base;
    if(!empty($attr))
        $o.='?'.urlattr($attr);
    if($id!==false)
        $o.="#$id";
    $o.='"';
    if($title!='')
        $o.=" title=\"$title\"";
    return $o.'>'.$label.'</a>';
}
function lnkp($label,$attr,$preserve=array())
{
    foreach($preserve as $k)
        if(isset($_GET[$k]) && !isset($attr[$k]))
            $attr[$k]=$_GET[$k];
    return '<a href="?'.urlattr($attr).'">'.$label.'</a>';
}

function urlattr($attr)
{
    if(!is_array($attr))
        return '';
    foreach($attr as $k=>$v)
        $o[] = urlencode($k).'='.urlencode($v);
    return implode('&',$o);
}

function format_time($time,$space=' ',$forceh=false)
{
    $sign=$time<0 ? '-' : '';
    $time=abs($time);
    if ( $time < 60 )
        return $sign.round($time).$space.msg('second-short');
    $r1 = $time % 60 ;
    $m=round($time/60);
    $time /= 60 ;
    if ( $time < 60 && !$forceh)
        return $sign.$m.$space.msg('minute-short');
    $r2 = $time % 60 ;
    $time /= 60 ;
    if ( $time < 72 )
        return  $sign.(int)$time.$space.msg('hour-short').$space.str_pad($r2,2,'0',STR_PAD_LEFT);
    $time /= 24 ;
    if ($time < 365 )
        return $sign.((int)($time)).$space.msg('day-short') ;
    return $sign.((int)($time/365)).$space.msg('year-short') ;
}

function format_date($date, $timezone=false)
{
    $format=msg('datetime_format_long');
    $t=strtotime($date);
    $tz='';
    if($timezone){
        $t+=date('Z',$t);
        $tz=' <small>'.date('T',$t).'</small>';
    }
    return date($format,$t).$tz;
}

/**
 * Formats a decimal number into a string, for use in HTML. Returned HTML is safe
 * 
 * @param number $v Decimal value to format
 * @param number $dec Number of decimal digits to use
 * @return string
 */
function fnum($v, $dec=0)
{
    $v=round($v, $dec);
    if($v==0)
        return '0';
    if(abs($v)<10 && $v-floor($v)!=0)
        return str_replace(' ','&nbsp;',number_format($v, $dec, msg('decimal_sep'), msg('thousands_sep')));
    return str_replace(' ','&nbsp;',number_format($v, 0, msg('decimal_sep'), msg('thousands_sep')));
}

/**
 * Formats a number representing binary units of information, to the most convenient factor (Kibibyte, Mebibyte, Gibibyte...)
 * 
 * @param number $v Quantity
 * @return string formatted value
 */
function format_sizei($v)
{
    $av=abs($v);
    if($av>=10737418240)
        $v=round($v/1073741824).' G';
    elseif($av>=1073741824)
        $v=round($v/1073741824,1).' G';
    elseif($av>=10485760)
        $v=round($v/1048576).' M';
    elseif($av>=1048576)
        $v=round($v/1048576,1).' M';
    elseif($av>=10240)
        $v=round($v/1024).' k';
    elseif($av>=1024)
        $v=round($v/1024,1).' k';
    else
        $v=strval($v);
    $v=str_replace('.', msg('decimal_sep'), $v);
    return $v;
}

/**
 * Formats a number representing decimal units of information, to the most convenient factor (Kilobyte, Megabyte, Gigabyte...)
 *
 * @param number $v Quantity
 * @return string formatted value
 */
function format_size($v)
{
    $av=abs($v);
    if($av>=10000000000)
        $v=round($v/1000000000).' G';
    elseif($av>=1000000000)
        $v=round($v/1000000000,1).' G';
    elseif($av>=10000000)
        $v=round($v/1000000).' M';
    elseif($av>=1000000)
        $v=round($v/1000000,1).' M';
    elseif($av>=10000)
        $v=round($v/1000).' k';
    elseif($av>=1000)
        $v=round($v/1000,1).' k';
    else
        $v=strval($v);
    $v=str_replace('.', msg('decimal_sep'), $v);
    return $v;
}
function format_hour($time)
{
    $h=floor($time/3600);
    $r = ($time/60) % 60;
    if($h<1)
        return $r.'&nbsp;'.msg('minute-short');
    if($r==0||$h>=24)
        return fnum($h).'&nbsp;'.msg('hour-short');
    if($r<10)
        $r="0$r";
    return $h.'&nbsp;'.msg('hour-short').'&nbsp;'.$r;
}
function format_diff($v)
{
    $v=format_size($v);
    if($v>0)
        $v='<span class="diffp">'.$v.'</span>';
    elseif($v<0)
        $v='<span class="diffm">'.$v.'</span>';
    return $v;
}
function flength($t)
{
    if($t<1)
        return round(1000*$t).'ms';
    elseif($t<100)
        return round($t).'s';
    else
        return round($t/60).'min';
}
/**
 * Returns a string formatted as a JavaScript string, with delimiters
 * 
 * @param string $v String to format
 * @return string
 */
function format_jsstring($v)
{
    return "'" . str_replace("\'", "\\'", $v) . "'";
}
function array_merge_recursive2($paArray1, $paArray2)
{
if (!is_array($paArray1) ){
        return $paArray2;
}elseif (!is_array($paArray2)){
        return $paArray1;
}
foreach ($paArray2 AS $sKey2 => $sValue2){
    $paArray1[$sKey2] = array_merge_recursive2(@$paArray1[$sKey2], $sValue2);
}
return $paArray1;
}
function array_sum_recursive($a1, $a2)
{
    if(!is_array($a1)){
        echo "Error array_sum_recursive a1 not array\n";
        var_dump($a1);
        var_dump($a2);
        return $a2;
    }
    foreach($a2 as $k=>$v){
        if(!isset($a1[$k])){
            $a1[$k]=$v;
            continue;
        }
        if(!is_array($v)){
            $a1[$k]+=$v;
            continue;
        }
        $a1[$k]=array_sum_recursive($a1[$k], $v);
    }
    return $a1;
}
function index($rows, $k)
{
    if ( !is_array($rows) )
        return false ;
    $res = array() ;
    foreach( $rows as $row ) {
        $res[$row[$k]] = $row ;
    }
    return $res ;
}
function s($num)
{
    return abs($num) >= 2 ? 's' : '';
}
/**
 * Returns a + sign if the number is greater than 0, an empty string otherwise 
 * 
 * @param number $num
 * @return string
 */
function plus($num)
{
    return $num > 0 ? '+' : '';
}
if(!function_exists('mb_ucfirst')){
    function mb_ucfirst($str)
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }
}

function remove_value(&$array, $value)
{
    if(($key = array_search($value, $array)) !== false)
        unset($array[$key]);
}
function remove_values(&$array, $values)
{
    foreach($values as $value)
        if(($key = array_search($value, $array)) !== false)
            unset($array[$key]);
}

function view_array($s, $lvl=0)
{
    $o='';
    foreach($s as $k=>$v){
        $o.="<pre>".str_repeat('    ',$lvl)."<b>$k : </b>";
        if(is_array($v))
            $o.=view_array($v,$lvl+1);
        else
            $o.=$v;
        $o.='</pre>';
    }
    return $o;
}

function pr($data)
{
    echo '<pre class=debug>';
    if($data=='')
        var_dump($data);
    else
        print_r($data);
    echo '</pre>';
}



?>