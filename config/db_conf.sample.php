<?php
/*
This file must be copied to "db_conf.php" and completed
*/

global $db_conf;
$db_conf=array(
    //MediaWiki database
    'db'=>array(
        'host'=>'localhost',
        'user'=>'u0000',
        'password'=>'...',
        'database'=>'wiki',
        'port'=>3306,
        'charset'=>'binary',
        //'flags'=>MYSQLI_CLIENT_COMPRESS,
        ),
    //stats database
    'dbs'=>array(
        'host'=>'localhost',
        'user'=>'stats',
        'password'=>'...',
        'database'=>'stats',
        'charset'=>'binary',
        ),
    //global database for multiwikis
    'dbg'=>array(
        'host'=>'localhost',
        'user'=>'wikiscan',
        'password'=>'...',
        'database'=>'wikiscan',
        'charset'=>'binary',
        ),
);

?>