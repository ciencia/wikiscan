<?php
if(!defined('DEBUG'))
    define('DEBUG',false);

if(!file_exists('config/db_conf.php'))
    die("config/db_conf.php not found. The file config/db_conf.sample.php must be copied and configured");
include('config/db_conf.php');
include('config/sites_conf.php');

//Default configuration, use local_conf.php to override
global $conf;
$conf=array(
    'root_path'=>'/var/www/wikiscan',
    'multi_path'=>'/var/www/wikiscan/multi',
    'sites_path'=>'/var/www/wikiscan/multi/sites',
    
    'logo'=>'imgi/logo_basic.png',
    'icon'=>'/imgi/icon_basic.png',
    'google_analytics'=>'',
    'google_analytics_all'=>'',
    'salts'=>['xxx', 'yyy', 'zzz'],

    'memcache_host'=>'',
    'memcache_port'=>11211,
    
    'pageview_download_path'=>'/tmp',
    'pageview_day_url'=>'https://dumps.wikimedia.org/other/pagecounts-ez/merged',
    'pageview_hour_url'=>'https://dumps.wikimedia.org/other/pageviews',

    'view_about_link'=>false,
    
    'multi'=>false,
    'wiki_key'=>'',

    'multi_db'=>true,//use site_db value in wikiscan/sites for stats mysql host (dbs)

    'use_sites_names'=>false,
    'site_language'=>'en',
    'interface_language'=>'en',
    'forced_interface_language'=>false,
    'mw_api'=>'https://fr.wikipedia.org/w/api.php',
    'link_page'=>'https://fr.wikipedia.org/wiki/',

    'cache_key_global'=>'ws',
    'cache_key_site'=>'',
    'cache_path'=>'cache',
    
    'min_year'=>2001,
    'file_mode_graph'=>0644,
    'dir_mode_graph'=>0755,
    'file_mode_stats'=>0644,
    'dir_mode_stats'=>0755,
    'file_mode_cache'=>0644,
    'dir_mode_cache'=>0755,

    'stats_allow_chunks'=>true,// 2020-04-30 10:10:10
    'rev_chunk_rows'=>10000,
    'log_chunk_rows'=>20000,

    'stats_update_revs_max_retries'=>1,
    'stat_confirm_new_page'=>false,
    'stats_join_rev_sha'=>false, //seach for previous same rev_sha1 (revert), ~5x slower
    'stats_join_comment'=>true, //use the new comment table (around nov 2018)
    'stats_join_actor'=>true, //use the new actor table (june 2019) TODO remove
    'stats_max_pages'=>50000,
    'stats_sum_pages_edits_limit'=>3000000,
    'stats_sum_users_edits_limit'=>5000000,
    'stats_sum_time_edits_limit'=>null, //500000000,
    'stats_sum_ip_month'=>true,
    'stats_limit_users_per_page'=>false,
    'stats_users_date_filter'=>true,//enable filters by year and month on for top users table

    'live_hours'=>[6, 12, 24, 48],
    'live_expire_24h'=>'+20 minutes',
    'live_expire_48h'=>'+2 hours',
    'live_max24h_edits_for48h'=>1000,
    'live_expire_curent_day'=>'+30 minutes',
    'live_expire_other_days'=>'+2 hours',
    
    'recent_sum_limit_min_pages'=>100000,
    'recent_sum_expire_month'=>'+1 day',
    'recent_sum_expire_year'=>'+2 days',
    'recent_sum_expire_total'=>'+4 days',
 
    'whois_row_sleep'=>500,//ms
    'whois_rate_limit_sleep'=>600,//s
    'whois_loop_sleep'=>660,//s
    'whois_src_ip'=>array(),

    'base_calc'=>'day',//full update base granularity (day or month), auto set with multi wiki depending on base_calc_max_month
    'base_calc_max_month'=>750000,//max edit+log for monthly base calc
    'wiki_sizes'=>array(
        0=>        0,
        1=>        1,
        2=>  2000000,
        3=> 20000000,
        4=>300000000,
    ),
);

if(file_exists('config/local_conf.php')){
    include('config/local_conf.php');
    foreach($local_conf as $k=>$v)
        $conf[$k]=$v;
}

?>