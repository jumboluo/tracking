<?php
class FileAction extends Action {
	public function getfile() {
		$file = $this->_request('file');
		$session_file_state = session('file');
		if($session_file_state[$file] == 'ok'){
			return_value_json(true, 'file', EXPORT_TEMP_PATH.$file);
		}
		else if($session_file_state[$file]['doing']){
			if(!empty($session_file_state[$file]['percent']))
				return_value_json(true, 'percent', $session_file_state[$file]['percent']);
			else
				return_value_json(true, 'doing', true);
		}
		else if(is_string($session_file_state[$file])) {
			return_value_json(false, 'msg', $session_file_state[$file]);
		}
		else {
			$session_file_state[$file]  += 1;
			if($session_file_state[$file] > 20) {
				return_value_json(false, 'msg', '操作超时');
			}
			else {
				session('file', $session_file_state);
				return_value_json(true);
			}
		}
	}
	
	public function pdfloading() {
		echo '
		<!DOCTYPE html>
		<html>
		<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head>
		<body style="background-color: gray;">
		<div style="position: absolute; width: 300px; height: 48px; left: 50%; top: 50%; margin-top: -24px; margin-left: -150px; vertical-align:middle;">
			<div id="pdfloading" style="line-height:24px; padding-left: 20px; font-size: 16px; font-family: tahoma,arial,verdana,sans-serif; background: url(../../../resources/images/loading16x16.gif) gray no-repeat left center;">
				正在准备打印文件
			</div>
		</div>
		</body>
		</html>
		';
	}
	
	public function pdfloaderror() {
		echo '
		<!DOCTYPE html>
		<html>
		<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head>
		<body style="background-color: gray;">
		<div style="position: absolute; width: 150px; height: 32px; left: 50%; top: 50%; margin-top: -16px; margin-left: -75px;">
			<div style="height:32px; line-height:32px; padding-left: 20px; font-size: 16px; font-family: tahoma,arial,verdana,sans-serif; background: url(../../../resources/images/alarm.png) gray no-repeat left center;">
				准备打印文件失败
			</div>
		</div>
		</body>
		</html>
		';
	}
	

	/**
	 * 文件操作退出
	 * @param string $filename
	 * @param string $msg 如果$msg不是"ok"，那么表示失败退出，失败原因是$msg（如果$msg是empty那么失败原因未知）
	 */
	public function fileexit($filename, $msg='ok') {
		session_start();
		$session_file_state = session('file');
		$session_file_state[$filename] = empty($msg) ? '失败原因未知':$msg;
		session('file', $session_file_state);
		exit();
	}
	
	public function setfilepercent($filename, $msg, $total=0, $done=0) {
		session_start();
		$session_file_state = session('file');
		$session_file_state[$filename]['doing'] = true;
		$session_file_state[$filename]['percent'] = array(
				'msg' => $msg,
				'total' => $total,
				'done' => $done
				);
		session('file', $session_file_state);
		session_write_close();
	}
	
	public function getbaidumapstaticimage($params) {
		if(empty($params)) return null;
		$url = "http://api.map.baidu.com/staticimage?".http_build_query($params);
		$newfname = '../'.EXPORT_TEMP_PATH.time().rand(1000,9999).'.png';
		$file = fopen ($url, "rb");
		if ($file) {
			$newf = fopen ($newfname, "wb");
			if ($newf)
				while(!feof($file)) {
				fwrite($newf, fread($file, 1024 * 8 ), 1024 * 8 );
			}
		}
		if ($file) {
			fclose($file);
		}
		if ($newf) {
			fclose($newf);
		}
		return $newfname;
	}
}
?>