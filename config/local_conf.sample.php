<?php
/*
This file is for local configuration.
It should be copied to "local_conf.php", each key will override conf
*/

$local_conf=array(
/*
    // Local filesystem path of this wikiscan installation
    'root_path'=>'/var/www/wikiscan_test',
    //'multi_path'=>'/var/www/wikiscan_test/multi',
    //'sites_path'=>'/var/www/wikiscan_test/multi/sites',

    // Wiki logo (URL)
    'logo'=>'imgi/logo_basic.png',
    // Favicon
    'icon'=>'/imgi/icon_basic.png',
    // Google analytics account (leave empty if you don't want to track users)
    'google_analytics'=>'',

    // Memcache configuration
    'memcache_host'=>'',
    'memcache_port'=>11211,

    // Language of the site. This may determine some paths of the wikiscan site. See urlpath-menu-* language messages
    'site_language'=>'en',
    // Wikiscan's interface language
    'interface_language'=>'en',
    // API url, used to retrieve the list of namespaces, or members of a category to get users from
    'mw_api'=>'http://localhost/mediawiki/w/api.php',
    // URL to article path (page titles will be appended to this URL)
    'link_page'=>'https://localhost/mediawiki/wiki/',
    // Local wiki configuration (when not multi site)
    'wiki'=>array(
        // URL to the wiki's homepage
        'url'=>'http://localhost/mediawiki',
        // Wiki's name
        'site_host'=>'localhost',
    ),
    // Local/custom group names (key: group, value: description)
    'groups'=>[
        'reversor'=>[
            'name'=>'Reversor',
            'abbr'=>'R',
        ],
        'sexy'=>'Sexy',
    ],
    // Display about link
    'view_about_link'=>false,
    // Robots policy
    'robots_policy'=>'index,follow',
    // Do we have hits information available?
    'hits_available'=>true,
    // use the revision_actor_temp table when building stats. Needs to be true before 1.39. This table no longer exists since MW 1.39
    'stats_join_revision_actor_temp'=>false,
    // use the revision_comment_temp table when building stats. Needs to be true before 1.40. This table will be removed in future versions of MediaWiki, somewhere after 1.40
    'stats_join_revision_comment_temp'=>true,
    // Log sql queries to the query_log table of dbg. Set it to false to not log queries
    'log_sql'=>false,
*/
);

?>
