<?php
//GeminiAPI用処理
//https://www.wakuwakubank.com/posts/604-php-google-trend/#index_id0
/*
Google Trends APIの仕様変更（URLの変更？）のため使用不可
変更の内容によっては「GTrends.php」の25～33行目を調整することにより対応できるかも
知らんけど
*/
require_once(dirname(__FILE__).'/vendor/autoload.php');
require_once(dirname(__FILE__).'/src/GTrends/GTrends.php');
$options = [
    'hl' => 'ja-JP',
    'tz' => -540,
    'geo' => 'JP',
];
$gt = new \Google\GTrends($options);

$keyWordList = ['java'];
$category = 0;
$time = 'today 12-m';
$property = '';

echo $result = $gt->explore($keyWordList, $category, $time, $property);*/
