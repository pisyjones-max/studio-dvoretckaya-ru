<?php header('Content-Type: text/plain');
require __DIR__.'/lib.php';
$relay=rtrim((string)cfg('relay_url',''),'/');$t=(string)cfg('bot_token','');
foreach(['getMe','getWebhookInfo'] as $m){
$c=curl_init(($relay?$relay.'/tg':'https://api.telegram.org').'/bot'.$t.'/'.$m);
curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>($relay&&cfg('relay_secret','')!=='')?['X-Relay-Secret: '.cfg('relay_secret')]:[]]);
$r=curl_exec($c);$j=json_decode((string)$r,true);
echo $m,' http=',curl_getinfo($c,CURLINFO_HTTP_CODE),' ',$j?json_encode(['ok'=>$j['ok']??null,'user'=>$j['result']['username']??null,'webhook'=>$j['result']['url']??null,'pending'=>$j['result']['pending_update_count']??null,'allowed'=>$j['result']['allowed_updates']??null],JSON_UNESCAPED_UNICODE):'ERR '.curl_error($c),"\n";}
