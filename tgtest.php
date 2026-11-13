<?php header('Content-Type: text/plain');
foreach(['https://t.me/s/studio_olgadvoretckaya','https://api.telegram.org'] as $u){
$c=curl_init($u);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>15,CURLOPT_USERAGENT=>'Mozilla/5.0']);
$h=curl_exec($c);echo $u,' http=',curl_getinfo($c,CURLINFO_HTTP_CODE),' len=',strlen((string)$h),' err=',curl_errno($c),' ',curl_error($c),"\n";}
