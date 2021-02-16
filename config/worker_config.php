<?php

$worker_units=array(
    'live'=>array(
        'small'=>1,
        'medium'=>1,
        'big'=>1,
        'large'=>1,
        ),
    'sum'=>array(
        'small'=>1,
        'medium'=>1,
        'big'=>1,
        'large'=>1,
        ),
    'stats'=>array(
        'small'=>1,
        'medium'=>1,
        'big'=>1,
        'large'=>1,
        ),
    'misc'=>array(
        'small+'=>1,
        ),
    'groups'=>array(
        'small+'=>1,
        ),
    'count'=>array(
        'all'=>1,
        ),
    );

$worker_config=array(
    'default'=>array(
        'live'=>'60 mins',
        'sum'=>'2 hours',
        'stats'=>'1 month',
        'count'=>'2 days',//all
        'misc'=>'1 day',
        'groups'=>'2 days',
        ),
    'large'=>array(
        'live'=>'6 hours',
        'sum'=>'12 hours',
        'stats'=>'3 months',
        ),
    'big'=>array(
        'sum'=>'3 hours',
        'stats'=>'2 months',
        ),
    'medium'=>array(
        ),
    'small'=>array(
        ),
    'medium+'=>array(
        ),
    'small+'=>array(
        ),
    'all'=>array(
        ),
    'none'=>array(
        ),
    );

foreach(array_keys($worker_config) as $key)
    if($key!=='default')
        foreach($worker_config['default'] as $k=>$v)
            if(!isset($worker_config[$key][$k]))
                $worker_config[$key][$k]=$v;

?>