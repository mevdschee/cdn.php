<?php
$config = array(
  'localhost' => array(
    'url' => function ($h,$r) { return 'http://i.imgur.com'.$r; },
    'cache' => function ($h,$r) { return $r; },
    'copy' => array( 'Content-Type' => true ),
  ),
);
$host = $_SERVER['HTTP_HOST'];
$request = $_SERVER['REQUEST_URI'];
if (!isset($config[$host])) die('Access Denied');
$cache = $config[$host]['cache'];
$hash = md5($cache($host,$request));
$hash = substr($hash,0,1).'/'.substr($hash,1,2).'/'.substr($hash,3);
$upstream = $config[$host]['url'];
$url = $upstream($host,$request);
if (!$url) die('Not Found');
$files = "$host/files.txt";
$file = "$host/$hash";
$dir = dirname($file);
if (!file_exists($dir)) mkdir($dir,0755,true);
if (!file_exists($file.'.bin')) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_HEADER, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $response = curl_exec($ch);
  $split = strpos($response, "\r\n\r\n");
  $header_text = substr($response, 0, $split);
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
  file_put_contents($file.'.bin',substr($response, $split+4));
  header("X-PHP-CDN: MISS");
} else {
  $headers = explode("\r\n",file_get_contents($file.'.txt'));
  header("X-PHP-CDN: HIT");
}
foreach ($headers as $header) header($header);
readfile($file.'.bin');
