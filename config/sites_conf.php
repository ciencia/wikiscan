<?php
global $sites_conf;
$sites_conf=array(
    'frwiki'=>array(
        'page_filters'=>array(
            'deletion'=>'^Discussion.*:.+/Suppression$',
            'admin'=>"^Wikipédia:(Bulletin des bureaucrates|Bulletin des administrateurs|Requête aux administrateurs|Demande de renommage de compte utilisateur|Demande de suppression immédiate|Demande de restauration de page|Demande de renommage|Demande de fusion d'historiques|Demande de purge d'historique|Demande d'intervention sur une page protégée|Demande d'intervention sur un message système|Vandalisme en cours|Demande de protection de page|Vérificateur d'adresses IP|Bulletin du filtrage)",
        ),
    ),
    'enwiki'=>array(
        'stats_sum_ip_month'=>false,
        'live_hours'=>[6, 12, 24],
        'live_expire_24h'=>'+1 hour',
        'live_expire_curent_day'=>'+1 hour',
        'live_expire_other_days'=>'+12 hours',
        'stats_max_pages'=>20000,
        'stats_limit_users_per_page'=>true,
        'stats_users_date_filter'=>false,
        'page_filters'=>array(
            'deletion'=>'^Wikipedia:Articles for deletion',
            'admin'=>"^Wikipedia:(Administrator|Requests for page protection|Usernames for administrator attention)",
        ),
        //'stats_join_comment'=>false,
    ),
    'commonswiki'=>array(
        'live_hours'=>[6, 12, 24],
        'live_expire_24h'=>'+1 hour',
        'live_expire_curent_day'=>'+1 hour',
        'live_expire_other_days'=>'+12 hours',
        'stats_max_pages'=>20000,
        'stats_limit_users_per_page'=>true,
        //'stats_join_comment'=>false,
    ),
    'wikidatawiki'=>array(
        'stats_max_pages'=>20000,
        'stats_limit_users_per_page'=>true,
        'live_hours'=>[3, 6, 12],
        'live_expire_24h'=>'+3 hour',
        'live_expire_curent_day'=>'+3 hours',
        'live_expire_other_days'=>'+12 hours',
        //'stats_join_comment'=>false,
    ),
    'viwiki'=>array(
        'live_hours'=>[6, 12, 24],
        'live_expire_24h'=>'+3 hours',
        'live_expire_curent_day'=>'+3 hours',
        'live_expire_other_days'=>'+12 hours',
        'stats_max_pages'=>20000,
        'stats_limit_users_per_page'=>true,
        //'stats_join_comment'=>false,
    ),
    'itwiki'=>array(
        //'stats_join_comment'=>false,
    ),
    'cywiki'=>array(
        //'stats_join_comment'=>false,
    ),
    'arwiki'=>array(
        //'stats_join_comment'=>false,
    ),
    'ruwiki'=>array(
        //'stats_join_comment'=>false,
    ),
    'eswiki'=>array(
        //'stats_join_comment'=>false,
    ),
    'ukwiki'=>array(
        //'stats_join_comment'=>false,
    ),
    'plwiktionary'=>array(
        //'stats_join_comment'=>false,
    ),
    'ruwiktionary'=>array(
        //'live_hours'=>[6, 12],
        //'stats_join_comment'=>false,
    ),
    'enwiktionary'=>array(
        //'live_hours'=>[3, 6],
        //'stats_join_comment'=>false,
    ),

);

?>