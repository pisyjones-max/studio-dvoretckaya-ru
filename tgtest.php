<?php header('Content-Type: text/plain');
$u='https://t.me/s/studio_olgadvoretckaya';
if(function_exists('curl_init')){$c=curl_init($u);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>15]);$h=curl_exec($c);$e=curl_error($c);}else{$h=@file_get_contents($u);$e='nocurl';}
echo ($h?substr_count($h,'tgme_widget_message_wrap'):'FAIL '.$e),' php ',PHP_VERSION;
