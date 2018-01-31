<?php
require './src/Region.php';

$region = new Region(); 
$data = array(
    array('date'=>'2014-12', 'url'=>'http://files2.mca.gov.cn/cws/201502/20150225163817214.html'),
    array('date'=>'2015-12', 'url'=>'http://www.mca.gov.cn/article/sj/tjbz/a/2015/201706011127.html'),
    array('date'=>'2016-12', 'url'=>'http://www.mca.gov.cn/article/sj/tjbz/a/2016/201612/201705311652.html'),
    array('date'=>'2017-08', 'url'=>'http://www.mca.gov.cn/article/sj/tjbz/a/2017/20178/201709251028.html'),
    array('date'=>'2017-11', 'url'=>'http://www.mca.gov.cn/article/sj/tjbz/a/2017/201801/201801151447.html'),
);

foreach ($data as $key => $value) {
    echo "processing {$value['date']} data" . PHP_EOL;
    $region->setUrl($value['url']);
    $value['date'] = str_replace('-', '_', $value['date']);
    file_put_contents("./data/region_{$value['date']}.sql", $region->parse());
}

echo 'done';
