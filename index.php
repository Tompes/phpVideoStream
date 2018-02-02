<?php
@error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
@ignore_user_abort(0);
@set_time_limit(0);

$url = trim($_GET['url']);
$play = (bool)$_GET['play'];

if ($play && !empty($url)) {
    $url = urlencode($url);
    header('Content-Type: text/html; charset=utf-8');
    echo <<<EOF
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>视频中转</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=1">
    <style>
        html, body, video {
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>
    <video src="?url=$url" controls>
</body>
</html>
EOF;
    return;
}

if(!empty($url)) {
    $urlArgs = parse_url($url);
    
    $host = $urlArgs['host'];
    $requestUri = $urlArgs['path'];
    
    if (isset($urlArgs['query'])) {
    	$requestUri .= '?' . $urlArgs['query'];
    }
    
    $protocol = ($urlArgs['scheme'] == 'http') ? 'tcp' : 'ssl';
    $port = $urlArgs['port'];
    
    if (empty($port)) {
    	$port = ($protocol == 'tcp') ? 80 : 443;
    }
    
    $header = "{$_SERVER['REQUEST_METHOD']} {$requestUri} HTTP/1.1\r\nHost: {$host}\r\n";
    
    unset($_SERVER['HTTP_HOST']);
    $_SERVER['HTTP_CONNECTION'] = 'close';
    
    if ($_SERVER['CONTENT_TYPE']) {
        $_SERVER['HTTP_CONTENT_TYPE'] = $_SERVER['CONTENT_TYPE'];
    }
    
    foreach ($_SERVER as $x => $v) {
        if (substr($x, 0, 5) !== 'HTTP_') {
            continue;
        }
        $x = strtr(ucwords(strtr(strtolower(substr($x, 5)), '_', ' ')), ' ', '-');
        $header .= "{$x}: {$v}\r\n";
    }
    
    $header .= "\r\n";

    $remote = "{$protocol}://{$host}:{$port}";
    
    $context = stream_context_create();
    stream_context_set_option($context, 'ssl', 'verify_host', false);

    $p = stream_socket_client($remote, $err, $errstr, 60, STREAM_CLIENT_CONNECT, $context);
    
    if (!$p) {
        exit;
    }
    
    fwrite($p, $header);
    
    $pp = fopen('php://input', 'r');
    
    while ($pp && !feof($pp)) {
        fwrite($p, fread($pp, 1024));
    }
    
    fclose($pp);
    
    $header = '';
    
    $x = 0;
    $len = false;
    $off = 0;
    
    while (!feof($p)) {
        if ($x == 0) {
            $header .= fread($p, 1024);
    		
            if (($i = strpos($header, "\r\n\r\n")) !== false) {
                $x = 1;
                $n = substr($header, $i + 4);
                $header = substr($header, 0, $i);
                $header = explode("\r\n", $header);
                foreach ($header as $m) {
                    if (preg_match('!^\\s*content-length\\s*:!is', $m)) {
                        $len = trim(substr($m, 15));
                    }
                    header($m);
                }
                $off = strlen($n);
                echo $n;
                flush();
            }
        } else {
            if ($len !== false && $off >= $len) {
                break;
            }
            $n = fread($p, 1024);
            $off += strlen($n);
            echo $n;
            flush();
        }
    }
    
    fclose($p);
    return;
}

header('Content-Type: text/html; charset=utf-8');
echo <<<EOF
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>文件中转</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=1">
</head>
<body>
    <h1>文件中转</h1>
    <form action="" method="get">
        文件地址：<input name="url">
        <input type="submit" value="下载">
        <input type="submit" value="播放" name="play">
    </form>
</body>
</html>
EOF;

