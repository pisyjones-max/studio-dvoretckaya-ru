<?php header('Content-Type: text/plain');
$c=curl_init('https://t.me/s/studio_olgadvoretckaya');curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_HEADER=>1,CURLOPT_TIMEOUT=>15,CURLOPT_USERAGENT=>'Mozilla/5.0']);
$h=curl_exec($c);echo substr((string)$h,0,600);
