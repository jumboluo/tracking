<?php

function safe_post_param($key, $is_number = false) {
	if($is_number) return $_POST[$key] + 0;
	
	return get_magic_quotes_gpc() ? $_POST[$key] : addslashes($_POST[$key]); 
}

function check_error($model) {
	$error = get_error($model);
	if(!empty($error)) return_value_json(false, 'msg', $error);
}

function get_error($model) {
	$modelError = $model->getError();
	$dbError = $model->getDbError();
	$error = empty($modelError) ? ( empty($dbError) ? null : '数据库出错：' . $dbError ) : $modelError;
	return $error;
}

function return_json($success=true, $total=0, $dataroot=null, $data=array()) {
	//设置结果数据
	$data = is_array($data) ? $data : ($data ? array($data) : array());
	$result  =  array();
	$result['success']  =  $success ? true : false;
	$result['total'] =  isset($total) ? $total + 0 : count($data);
	$result[(empty($dataroot)? 'data' : $dataroot)] = $data;
	
	//设置头信息
	header('Content-type: application/json;charset=utf-8');
	header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
	header('Pragma: no-cache');
	
	//返回结果，并终止脚本
//	exit(custom_json_encode($result)); 
	exit(json_encode($result));
}

function return_value_json($success=true, $name=null, $value=null) {
	//设置结果数据
	$result = array();
	$result['success']  =  $success ? true : false;
	if(isset($value)) {
		$result[(is_string($name) && !empty($name) ? $name : 'value' )] = $value;
	}

	//设置头信息
	header('Content-type: application/json;charset=utf-8');
	header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
	header('Pragma: no-cache');
	
	//返回结果，并终止脚本
//	exit(custom_json_encode($result)); 
	exit(json_encode($result));
}

/**
 * 由于php的json扩展自带的函数json_encode会将汉字转换成unicode码
 * 所以我们在这里用自定义的json_encode，这个函数不会将汉字转换为unicode码
 */
function custom_json_encode($a = false) {
	if (is_null($a)) return 'null';
	if ($a === false) return 'false';
	if ($a === true) return 'true';
	if (is_scalar($a)) {
		if (is_float($a)) {
			// Always use "." for floats.
			return floatval(str_replace(",", ".", strval($a)));
		}

		if (is_string($a)) {
			static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
			return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
		} else {
			return $a;
		}
	}

	$isList = true;
	for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
		if (key($a) !== $i) {
			$isList = false;
			break;
		}
	}

	$result = array();
	if ($isList) {
		foreach ($a as $v) $result[] = custom_json_encode($v);
		return '[' . join(',', $result) . ']';
	} else {
		foreach ($a as $k => $v) $result[] = custom_json_encode($k).':'.custom_json_encode($v);
		return '{' . join(',', $result) . '}';
	}
}
 
function is_mobile_number( $str ) {
	return  preg_match("/^((\+86)?|(86)?)(13[0-9]{9}|15[0|1|2|3|5|6|7|8|9]\d{8}|18[0|1|2|3|5|6|7|8|9]\d{8})$/", $str);
}

function is_email_well_form ( $email ) {
	return eregi("^[_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,4}$", $email);
}


/**
 * 通过cURL发送POST请求，并返回结果
 * @return array 返回数组形式如下：
 * array(
 * 	'success'	=> 请求是否成功
 * 	'response' 	=> '请求返回内容'（不包含头）
 * 	'errno'		=> 错误号码
 * 	'error'		=> 错误信息
 * )
 */
function curl_post($url, $params, $headers, $proxy, $timeout=0){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_POST, 			1);
	curl_setopt($ch, CURLOPT_URL, 			$url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, 	$params);
	curl_setopt($ch, CURLOPT_HTTPHEADER, 	$headers);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,TRUE);
	if(!empty($timeout)) 
	curl_setopt($ch, CURLOPT_TIMEOUT, 		$timeout);
	
	//proxy for test
	if(!empty($proxy) && !empty($proxy['type']) && !empty($proxy['address']) && !empty($proxy['port'])) {
		curl_setopt($ch, CURLOPT_PROXYTYPE, $proxy['type']);
		curl_setopt($ch, CURLOPT_PROXY, 	$proxy['address']);
		curl_setopt($ch, CURLOPT_PROXYPORT, $proxy['port']);
	}
	
	$response = curl_exec($ch);
	
	$re = array(
		'success'	=> ($response!==false && curl_errno($ch)===0),
		'response' 	=> $response,
		'errno'		=> curl_errno($ch),
		'error'		=> curl_error($ch)
	);
	
	curl_close($ch);
	return $re;
}

function send_mail($to, $from, $subject, $content, $html=false) {
	if(is_array($to)) $to = implode(", ", $to);
	
	$headers = '';
	if($html){
		$headers .= 'MIME-Version: 1.0' ."\r\n";
		$headers .= 'Content-type: text/html; charset=utf-8' ."\r\n";
	}
	$headers .= 'To: ' . $to ."\r\n";
	$headers .= 'From: ' . $from ."\r\n";
	
	$config = "-f$from"; 
			
	return mail ($to, $subject, $content, $headers, $config);
}

ini_set("date.timezone", "PRC");
date_default_timezone_set('PRC');
?>
