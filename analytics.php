<?php
global $conf;
if(empty($conf))
    include('config/conf.php');

$robots='%SemrushBot|Googlebot|bingbot|BingPreview|YandexBot|YandexImages|MegaIndex|AhrefsBot|DotBot|ia_archiver|ltx71|Sogou web spider|SeznamBot|The Knowledge AI|netEstate|Applebot|BLEXBot|Cliqzbot|Baiduspider|MojeekBot|Twitterbot|coccocbot|RU_Bot|yacybot|archive.org_bot|ZoominfoBot|naver\.me|SEOkicks|MixnodeCache|TurnitinBot|Exabot|istellabot|Go-http-client|Yahoo! Slurp|Bleriot|TurnitinBot|Daum/|CCBot/|Seekport|UCBrowser|MQQBrowser|Mb2345Browser|LieBaoFast|Qwantify|Nimbostratus-Bot|curl/|python-requests|serpstatbot%';

// This "undefined" $include variable is actually being defined when this analytics.php file is included from site.php
if(!isset($include) && (@$_SERVER['HTTP_USER_AGENT']=='' || preg_match($robots, $_SERVER['HTTP_USER_AGENT'])))
    exit;
if(!isset($_GET['_title']) || !isset($_GET['_page']))
    exit;
$tid=isset($include) ? $conf['google_analytics_all'] : $conf['google_analytics'];
if($tid!=''){
    $title=urlencode($_GET['_title']);
    $page=urlencode($_GET['_page']);
    $referrer=urlencode($_GET['_referrer']);
    $ip=$_SERVER['REMOTE_ADDR'];
    if(preg_match('/^(\d+\.\d+)\.\d+\.\d+$/', $ip, $res))
        $ip=$res[1].'.0.0';
    $cid=hash('sha256', $conf['salts'][0].$_SERVER['REMOTE_ADDR'].$conf['salts'][1].@$_SERVER['HTTP_USER_AGENT'].$conf['salts'][2]);
    $p="v=1&tid=$tid&t=pageview&aip=1&uip=$ip&cid=$cid&dt=$title&dl=$page&dr=$referrer&ua=".urlencode(@$_SERVER['HTTP_USER_AGENT']);
    if(isset($_GET['_menu']))
        $p.="&cd1=".urlencode($_GET['_menu']);
    if(isset($_GET['_submenu']))
        $p.="&cd2=".urlencode($_GET['_submenu']);
    //echo $p;
    file_get_contents("https://www.google-analytics.com/collect?$p");
}
?>