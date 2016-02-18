<?php
$config = array(
  'localhost:8888' => array(
    'url' => function ($h,$r) { return 'http://i.imgur.com'.str_replace('/cdn.php','',$r); },
    'cache' => function ($h,$r) { return $r; },
    'copy' => array( 'Content-Type' => true ),
  ),
);
$host = $_SERVER['HTTP_HOST'];
$request = $_SERVER['REQUEST_URI'];
if (!isset($config[$host])) die('Not Found');
$cache = $config[$host]['cache'];
$hash = md5($cache($host,$request));
$hash = substr($hash,0,1).'/'.substr($hash,1,2).'/'.substr($hash,3);
$upstream = $config[$host]['url'];
$url = $upstream($host,$request);
$files = "$host/files.txt";
$file = "$host/$hash";
$dir = dirname($file);
if (!file_exists($dir)) mkdir($dir,0755,true);
if (!file_exists($file.'.bin')) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  $response = curl_exec($ch);
  $redirects = curl_getinfo($ch,CURLINFO_REDIRECT_COUNT);
  $start = 0;
  for ($i=0;$i<$redirects;$i++) $start = strpos($response,"\r\n\r\n",$start)+4;
  $end = strpos($response,"\r\n\r\n",$start);
  $header_text = substr($response,$start,$end-$start);
  $headers = array();
  $copy = $config[$host]['copy'];
  foreach (explode("\r\n", $header_text) as $i => $line) {
    if ($i===0) {
      list($proto,$code,$status) = explode(' ',$line,3);
      if ($code!=200) die('Upstream Error');
    } else {
      list($key,$value) = explode(': ',$line,2);
      if (isset($copy[$key])) $headers[] = $line;
    }
  }
  file_put_contents($files,"$hash $request\r\n",FILE_APPEND);
  file_put_contents($file.'.txt',implode("\r\n",$headers));
  file_put_contents($file.'.bin',substr($response,$end+4));
  header("X-PHP-CDN: MISS");
} else {
  $headers = explode("\r\n",file_get_contents($file.'.txt'));
  header("X-PHP-CDN: HIT");
}
foreach ($headers as $header) header($header);
readfile($file.'.bin');
