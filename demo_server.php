<?php
ini_set('memory_limit','-1');
use Workerman\Worker;
use Workerman\Protocols\Http;
require_once __DIR__ . '/vendor/Autoloader.php';

date_default_timezone_set('PRC');
$GLOBALS['path'] = '/home/csgoserver/serverfiles/csgo/';
$GLOBALS['srcds_cfg'] = $GLOBALS['path'].'cfg/csgo-server.cfg';
$GLOBALS['lgsm'] = $GLOBALS['path'].'../../csgoserver';

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
	##var_dump($array);
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
	$header = '<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.0//EN" "http://www.wapforum.org/DTD/xhtml-mobile10.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Index of /</title>
<style type="text/css" media="screen">
pre{background:0 0}body{margin:2em}tb{width:600px;margin:0 auto}
</style>
<script language="JavaScript" type="text/javascript" src="http://cdn.bootcss.com/jquery/3.1.1/jquery.min.js"></script>
<script>
$(document).ready(function(){getJSONData();});
function getJSONData() {
	setTimeout("getJSONData()", 1000);
	$.getJSON(\'?csgoact=ajax&callback=?\', displayData);
}
function displayData(a) {
	$("#pid").html(a.pid);
	$("#net").html(a.net)
};
if (window.name != "bencalie") {
	location.reload();
	window.name = "bencalie"
} else {
	window.name = ""
}
</script>
</head>
<body>
<strong>Demo 下载</strong>';
	$footer = csgo_front()."<address>%s</address>\n</body>\n</html>";
	$template_a = $header.'<p>没有文件</p>'.$footer;
	$template = $header.'<table><th><img src="data:image/gif;base64,R0lGODlhFAAWAKEAAP///8z//wAAAAAAACH+TlRoaXMgYXJ0IGlzIGluIHRoZSBwdWJsaWMgZG9tYWluLiBLZXZpbiBIdWdoZXMsIGtldmluaEBlaXQuY29tLCBTZXB0ZW1iZXIgMTk5NQAh+QQBAAABACwAAAAAFAAWAAACE4yPqcvtD6OctNqLs968+w+GSQEAOw==" alt="[ICO]"></th><th><a href="?sort=name">名称</a></th><th><a href="?sort=mtime">最后更改</a></th><th><a href="?sort=duration">持续时间</a></th><th><a href="?sort=size">大小</a></th></tr><tr><th colspan="6"><hr></th></tr>%s<tr><th colspan="6"><hr></th></tr></table>'.$footer;
	if(!$table) return sprintf($template_a, get_ver($data));
	return sprintf($template, $table, "{$GLOBALS['total_files']} files, total {$GLOBALS['total_size']}.</br>".get_ver($data));
}

function get_pid($name_need) {
	$dir = scandir('/proc');
	if($dir) {
		foreach($dir as $key => $name) {
			$status_file = sprintf("/proc/%s/status", $name);
			if(is_readable($status_file)) {
				$status = file($status_file);
				$status = explode("\t", trim($status[0]));
				if($status[1] == $name_need) {
					#var_dump($name);
					return $name;
				} elseif(isset($dir[$key + 1])) {
					continue;
				} else {
					return false;
				}
			}
		}
	}
}

function lgsm_settings($what) {
	#var_dump($GLOBALS['lgsm']);
	$config = file($GLOBALS['lgsm']);
	if(!isset($GLOBALS['settings'])) {
		$GLOBALS['settings'] = [];
		foreach($config as $line) {
			if(strstr($line, '=')) {
				list($key, $val) = explode('=', $line);
				$GLOBALS['settings'][$key] = trim($val, "\"\n");
				#var_dump($GLOBALS['settings'][$key]);
			}
		}
	} else {
		return isset($GLOBALS['settings'][$what]) ? $GLOBALS['settings'][$what] : false;
	}
}

/* function csgo_stop() {
	if(!$GLOBALS['csgo']['pid']) return false;
	exec('pkill -9 srcds_linux', $result, $errno);
	return array('result' => $result, 'errno' => $errno);
} */

function csgo_stop() {
	exec('su - csgoserver -c "./csgoserver stop"', $result, $errno);
	return array('result' => $result, 'errno' => $errno);
}

function csgo_start() {
	exec('su - csgoserver -c "./csgoserver start"', $result, $errno);
	return array('result' => $result, 'errno' => $errno);
}

function csgo_restart() {
	exec('su - csgoserver -c "./csgoserver restart"', $result, $errno);
	return array('result' => $result, 'errno' => $errno);
}

function csgo_update() {
	exec('su - csgoserver -c "./csgoserver update >/tmp/lgsmlog 2>/tmp/lgsmlog &"', $result, $errno);
	return array('result' => $result, 'errno' => $errno);
}

function csgo_main($input) {
	$needs = array('gametype', 'gamemode', 'defaultmap', 'mapgroup', 'maxplayers', 'tickrate', 'port', 'sourcetvport', 'clientport', 'ip', 'gslt');
	$GLOBALS['csgo']['pid'] = get_pid('srcds_linux');
	foreach($needs as $need) if(!isset($GLOBALS['csgo'][$need])) $GLOBALS['csgo'][$need] = lgsm_settings($need);
	unset($need);
	switch($input) {
		case 'restart':
			$done = csgo_restart();
		break;
		case 'start':
			$done = csgo_start();
		break;
		case 'stop':
			$done = csgo_stop();
		break;
		case 'status':
			$running = is_numeric($GLOBALS['csgo']['pid']);
			exec(sprintf('netstat -ltu | grep %s', $GLOBALS['csgo']['port']), $listening, $errno);
			$done = array('result' => $listening, 'errno' => $errno);
		break;
		case 'update':
			$done = csgo_update();
		break;
		case 'ajax':
			exec(sprintf('netstat -ltu | grep %s', $GLOBALS['csgo']['port']), $listening);
			var_dump(get_pid('srcds_linux'));
			$ajax['pid'] = get_pid('srcds_linux');
			$ajax['net'] = end($listening);
			return htmlspecialchars($_GET['callback']).'('.json_encode($ajax).')';
		break;
	}
	$tmp = explode("\n", str_replace("\r", "\n", end($done['result'])));
	$tmp = end($tmp);
	$tmp = explode('] ', $tmp);
	$tmp = end($tmp);
	$result = array($tmp);
	$errno = $done['errno'];
	$html[] = sprintf('操作:%s', $input);
	$html[] = sprintf('结果:%s', implode('</br>', $result));
	$html[] = sprintf('状态:%s', $errno);
	$html[] = '<input type="button" name="Submit" value="返回" onclick="javascript:history.back(-1);">';
	return implode('</br>', $html);
}

function csgo_ajax() {
	
}

function csgo_front() {
	$html = '';
	$acts = array('restart' => '重启', 'start' => '启动', 'stop' => '停止', 'status' => '状态', 'update' => '升级');
	foreach($acts as $act => $tran) $html .= sprintf('<a href="?csgoact=%s" onclick="return confirm(\'确定要执行 %s 操作吗？\')"> %s</a> ', $act, $tran, $tran);
	$html .= 'srcds pid : <span id="pid">收集数据中...</span> 端口监听状态 : <span id="net">收集数据中...</span>';
	return $html;
}

$http_worker = new Worker("http://0.0.0.0:12101");
$http_worker->count = 4;
$http_worker->onMessage = function($connection, $data) {
	$GLOBALS['time_start'] = microtime(true);
	$GLOBALS['queries'] = 0;
	if(isset($_GET['csgoact'])) {
		$connection->send(csgo_main($_GET['csgoact']));
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