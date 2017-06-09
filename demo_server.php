<?php
ini_set('memory_limit','-1');
use Workerman\Worker;
use Workerman\Protocols\Http;
require_once __DIR__ . '/vendor/Autoloader.php';

date_default_timezone_set('PRC');
$GLOBALS['path'] = '/home/csgoserver/serverfiles/csgo/';

function formatsize($size, $key = 0) {
	if($size < 0) {
		return '0B';
	} else {
		$danwei = array('B','K','M','G','T','P');
		while($size > 1024) {
			$size = $size / 1024;
			$key++;
		}
		return round($size, 1).$danwei[$key];
	}
}

function get_ver($data) {
	$_d = $data['server'];
	$time_usage = round((microtime(true) - $GLOBALS['time_start']) * 1000, 4);
	$mem_usage = round(memory_get_usage()/1024/1024, 2);
	$_s = "Processed in {$time_usage} ms , {$mem_usage} MB memory used , {$GLOBALS['queries']} queries.</br>\n";
	return sprintf($_s.'%s Server at %s Port %s', $_d['SERVER_SOFTWARE'], $_d['SERVER_NAME'], $_d['SERVER_PORT']);
}

function get_create_time($filename) {
	$temp = explode('_', $filename);
	if(isset($temp[0]) and strlen($temp[0]) == 14) return strtotime($temp[0]);
}

function read_dir($dir, $sort = 'mtime', $order = SORT_DESC) {
	$handler = opendir($dir);
	while($filename = readdir($handler)) {
		if(strstr($filename, '.dem')) {
			$file_name[] = $filename;
			$file_path[] = $real_path = $GLOBALS['path'].$filename;
			$file_size[] = filesize($real_path);
			$file_ctime[] = $start_time = get_create_time($filename);
			$file_mtime[] = $end_time = filemtime($real_path);
			$duration[] = $end_time - $start_time;
			$GLOBALS['queries'] += 2;
		}
	}
	closedir($handler);
	switch($sort) {
		case 'name':
			array_multisort($file_name, $order, $file_size, $file_mtime, $duration);
		break;
		case 'size':
			array_multisort($file_size, $order, $file_name, $file_mtime, $duration);
		break;
		case 'duration':
			array_multisort($duration, $order, $file_size, $file_name, $file_mtime);
		break;
		case 'mtime':
			array_multisort($file_mtime, $order, $file_size, $file_name, $duration);
		break;
		default: break;
	}
	return (isset($file_name)) ? array('name' => $file_name, 'size' => $file_size, 'mtime' => $file_mtime, 'duration' => $duration) : false;
}

function make_list($array) {
	#var_dump($array);
	if(!$array) return false;
	$str = '';
	$GLOBALS['total_files'] = 0;
	$GLOBALS['total_size'] = 0;
	for($i=0;$i<count($array['name']);$i++) {
		$file_now = $array['name'][$i];
		$size_now = formatsize($array['size'][$i]);
		$mtime_now = date("Y-m-d H:i", $array['mtime'][$i]);
		$duration = floor($array['duration'][$i] / 60);
		$str .= "<tr>\n";
		$str .= "<td><img src=\"data:image/gif;base64,R0lGODlhFAAWAMIAAP///8z//8zMzJmZmTMzMwAAAAAAAAAAACH+TlRoaXMgYXJ0IGlzIGluIHRoZSBwdWJsaWMgZG9tYWluLiBLZXZpbiBIdWdoZXMsIGtldmluaEBlaXQuY29tLCBTZXB0ZW1iZXIgMTk5NQAh+QQBAAABACwAAAAAFAAWAAADaUi6vPEwEECrnSS+WQoQXSEAE6lxXgeopQmha+q1rhTfakHo/HaDnVFo6LMYKYPkoOADim4VJdOWkx2XvirUgqVaVcbuxCn0hKe04znrIV/ROOvaG3+z63OYO6/uiwlKgYJJOxFDh4hTCQA7\" alt=\"[   ]\"></td><td> <a href=\"?download=$file_now\">$file_now</a></td>\n";
		$str .= "<td align=\"right\"> $mtime_now</td>\n";
		$str .= "<td align=\"right\"> $duration 分钟</td>\n";
		$str .= "<td align=\"right\"> $size_now</td><td>&nbsp;</td>\n";
		$str .= "<td align=\"right\"><a href=\"?delete=$file_now\" onclick=\"return confirm('确定要删除吗？')\"> 删除</a></td>\n";
		$str .= "</tr>\n";
		$GLOBALS['total_files']++;
		$GLOBALS['total_size'] += $array['size'][$i];
	}
	return $str;
}

function get_full_html($table, $data) {
	$GLOBALS['total_size'] = formatsize($GLOBALS['total_size']);
	$header = "<!DOCTYPE html PUBLIC \"-//WAPFORUM//DTD XHTML Mobile 1.0//EN\" \"http://www.wapforum.org/DTD/xhtml-mobile10.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\n<head>\n<title>Index of /</title>\n<style type=\"text/css\" media=\"screen\">pre{background:0 0}body{margin:2em}tb{width:600px;margin:0 auto}</style>\n<script>if(window.name!=\"bencalie\"){location.reload();window.name=\"bencalie\"}else{window.name=\"\"}</script>\n</head>\n<body>\n<strong>Demo 下载</strong>\n";
	$footer = "<address>%s</address>\n</body>\n</html>";
	$template_a = $header.'<p>没有文件</p>'.$footer;
	$template = $header.'<table><th><img src="data:image/gif;base64,R0lGODlhFAAWAKEAAP///8z//wAAAAAAACH+TlRoaXMgYXJ0IGlzIGluIHRoZSBwdWJsaWMgZG9tYWluLiBLZXZpbiBIdWdoZXMsIGtldmluaEBlaXQuY29tLCBTZXB0ZW1iZXIgMTk5NQAh+QQBAAABACwAAAAAFAAWAAACE4yPqcvtD6OctNqLs968+w+GSQEAOw==" alt="[ICO]"></th><th><a href="?sort=name">名称</a></th><th><a href="?sort=mtime">最后更改</a></th><th><a href="?sort=duration">持续时间</a></th><th><a href="?sort=size">大小</a></th></tr><tr><th colspan="6"><hr></th></tr>%s<tr><th colspan="6"><hr></th></tr></table>'.$footer;
	if(!$table) return sprintf($template_a, get_ver($data));
	return sprintf($template, $table, "{$GLOBALS['total_files']} files, total {$GLOBALS['total_size']}.</br>".get_ver($data));
}

function csgo_init($input) {
	switch($input) {
		case 'restart':
		
		break;
		case 'start':
		
		break;
		case 'stop':
		
		break;
		case 'status':
		
		break;
		case 'update':
		
		break;
	}
}

$http_worker = new Worker("http://0.0.0.0:12101");
$http_worker->count = 4;
$http_worker->onMessage = function($connection, $data) {
	$GLOBALS['time_start'] = microtime(true);
	$GLOBALS['queries'] = 0;
	if(isset($_GET['csgoact'])) {
		csgo_init($_GET['csgoact']);
	} elseif(isset($_GET['download'])) {
		$file_path = $GLOBALS['path'].$_GET['download'];
		if(is_readable($file_path)) {
			Http::header("Content-type: text/plain");
			Http::header("Accept-Ranges: bytes");
			Http::header("Content-Disposition: attachment; filename=".$_GET['download']);
			$connection->send(file_get_contents($file_path));
		} else {
			Http::header("HTTP/1.1 404 Not Found");
		}
	} elseif(isset($_GET['delete'])) {
		unlink($GLOBALS['path'].$_GET['delete']);
		$connection->send('<script>history.go(-1)</script>');
	} elseif(isset($_GET['sort'])) {
		$connection->send(get_full_html(make_list(read_dir($GLOBALS['path'], $_GET['sort'])), $data));
	} else {
		$connection->send(get_full_html(make_list(read_dir($GLOBALS['path'])), $data));
	}
};

Worker::runAll();