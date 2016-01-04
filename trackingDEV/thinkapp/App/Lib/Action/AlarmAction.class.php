<?php
class AlarmAction extends Action {
	
	private static $filterAmbiguous = array(
		"type" => "alarm`.`type",
		"rule_label" => "rule`.`label",
		"path_area_label" => "path_area`.`label",
		"department" => "alarm`.`department_id",
		"target_type" => "alarm`.`target_type",
		"target_name" => "alarm`.`target_name",
		"device_label" => "device`.`label"
	);
	
	public function _initialize() {
	}
	
	public function current() {//其实可以和history合并，但是……，算了，不搞了
		//加一个参数，即可用history();
		$_REQUEST['checked'] = 0;
		$this->history();
	}
	
	public function history() {
    	$condition = $this->_parseCondition(self::$filterAmbiguous);
    	
		$Alarm = M('Alarm');
		check_error($Alarm);
		
		$total = $Alarm->join('`department` on `department`.`id`=`alarm`.`department_id`')
				->join('`device` on `device`.`id`=`alarm`.`device_id`')
				->join('`rule` on `rule`.`id`=`alarm`.`rule_id`')
				->join('`path_area` on `path_area`.`id`=`rule`.`path_area_id`')
				->where($condition)
				->count();
		check_error($Alarm);
		
		$Alarm->join('`department` on `department`.`id`=`alarm`.`department_id`')
				->join('`device` on `device`.`id`=`alarm`.`device_id`')
				->join('`rule` on `rule`.`id`=`alarm`.`rule_id`')
				->join('`path_area` on `path_area`.`id`=`rule`.`path_area_id`')
				->field(array('`alarm`.`id`', '`alarm`.`type`', '`alarm`.`rule_id`', '`rule`.`label`' => 'rule_label',
						'`path_area`.`id`' => 'path_area_id', '`path_area`.`label`' => 'path_area_label',
						'`alarm`.`start_time`', 'check_time', 'checked',
						'`alarm`.`department_id`', '`department`.`name`'=>'department', 
						'`alarm`.`target_type`', '`alarm`.`target_id`', '`alarm`.`target_name`',
						'`alarm`.`device_id`', '`device`.`label`' => 'device_label',
						'`alarm`.`start_time`', '`alarm`.`check_time`', '`alarm`.`checked`', 
						'last_sms', 'sms_count', 'last_email', 'email_count', 'last_window', 'window_count'
				))
				->where($condition)
				->order('`alarm`.`start_time` ASC');
				
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) $Alarm->limit($limit)->page($page);
		
		$alarms = $Alarm->select();
// 		Log::write("\nSQL:" . M()->getLastSql(), Log::SQL);
		check_error($Alarm);

		return_json(true, $total, 'alarms', $alarms);
	}
	
	public function alarmlistexporttoexcel() {//currentAlarmExportToExcel
		$file = time().rand(1000,9999).'.xlsx';
		$url = U('Alarm/doalarmlistexporttoexcel');
		$_REQUEST['file'] = $file;
		$_REQUEST['session_id'] = session_id();
		$postdata = http_build_query($_REQUEST);
		$this->_socketPost($postdata, $url);
		return_value_json(true, 'file', $file);
	}
	
	public function doalarmlistexporttoexcel() { //doCurrentAlarmExportToExcel
		require_once dirname(__FILE__)."/../Util/PHPExcel/PHPExcel/IOFactory.php";

		$session_id = $this->_request('session_id');
		$filename = $this->_request('file');
		if(empty($session_id) || empty($filename)) return;
		
		$objPHPExcel = $this->_exportData2Excel();
		
		if(empty($objPHPExcel)) {
			R('File/fileexit', array($filename, '导出成Excel失败'));
		}
		else {
			$file = EXPORT_TEMP_PATH.$filename;
		
			R('File/setfilepercent', array($filename, '正在保存到Excel文件中...'));
			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('../'.$file);
		
			R('File/fileexit', array($filename));
		}
	}
	

	public function alarmlisttopdf() {
		$file = time().rand(1000,9999).'.pdf';
		$url = U('Alarm/doalarmlisttopdf');
		$_REQUEST['file'] = $file;
		$_REQUEST['session_id'] = session_id();
		$postdata = http_build_query($_REQUEST);
		$this->_socketPost($postdata, $url);
		return_value_json(true, 'file', $file);
	}
	
	public function doalarmlisttopdf() {
		require_once dirname(__FILE__)."/../Util/PHPExcel/PHPExcel/IOFactory.php";
		$session_id = $this->_request('session_id');
		$filename = $this->_request('file');
		if(empty($session_id) || empty($filename)) return;
	
		$objPHPExcel = $this->_exportData2Excel();
	
		$isPrint = ($this->_request('action')=='print');
		R('File/setfilepercent', array($filename, '正在准备PDF文件，以便开始'.($isPrint ? '打印':'预览').'...'));
	
		ini_set("memory_limit","1024M"); //MPDF消耗内存比较厉害
		$rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
		$rendererLibrary = 'mPDF5.4';
		$rendererLibraryPath = dirname(__FILE__).'/../Util/MPDF54';
	
		PHPExcel_Settings::setPdfRenderer(
		$rendererName,
		$rendererLibraryPath
		);
	
		$file = EXPORT_TEMP_PATH.$filename;
	
		$objPHPExcel->getActiveSheet()->setShowGridlines(false);
	
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'PDF');

		//$objWriter应该是mPDF
		if($isPrint){
			$objWriter->setScript('try{this.print();}catch(e) {window.onload = window.print;}');
		}
		if($_REQUEST['keeptemplate']!='true') {
			$objWriter->setPrintParams(
					$this->_request('papersize'),
					$this->_request('orientation'),
					$_REQUEST['header1'],
					$_REQUEST['footer1'],
					$_REQUEST['header2'],
					$_REQUEST['footer2'],
					$this->_request('tmargin')+0,
					$this->_request('bmargin')+0,
					$this->_request('lmargin')+0,
					$this->_request('rmargin')+0,
					$this->_request('hmargin')+0,
					$this->_request('fmargin')+0,
					$_REQUEST['mirrormargins']=='true',
					$this->_request('customPaperWidth'),
					$this->_request('customPaperHeight')
			);
		}
	
		R('File/setfilepercent', array($filename, '正在保存PDF文件，以便开始'.($isPrint ? '打印':'预览').'...'));
// 		$objWriter->save('php://output');
		$objWriter->save('../'.$file);

		R('File/fileexit', array($filename));
	}
	
	///////////////////////////////////
	
	private function _socketPost($postdata, $url)  {
		if(function_exists('fsockopen')) { //可以用fsockopen
			//打开一个后台连接
			$fp = fsockopen($_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $errno, $errstr, 30);
			if($fp===FALSE) {
				die();
			}
				
			//设置流为非阻塞型
			if (!stream_set_blocking($fp, 0)) {
				die();
			}
				
			//发送get
			$crlf = "\r\n";
			$header = "POST $url HTTP/1.1" . $crlf;
			$header .= "Host: {$_SERVER['HTTP_HOST']}" . $crlf;
			$header .= 'Content-Type: application/x-www-form-urlencoded' . $crlf;
			$header .= 'Content-Length: '. strlen($postdata) . $crlf . $crlf;
			$header .= $postdata . $crlf;
			$header .= "Connection: Close" . $crlf . $crlf;
			fwrite($fp, $header);
			fclose($fp); //不等结果，直接关闭
		}
	}
	
	private function _parseCondition($ambiguous=array()) {
		$condition = "1";
		
		//target_type
		$target_type = $_REQUEST['target_type'];
		if(!empty($target_type)) $condition .= " AND `alarm`.`target_type`='{$target_type}' ";
		
		//target_id
		$target_id = $_REQUEST['target_id'];
		if(isset($target_id) && strlen($target_id)>0) $condition .= " AND `alarm`.`target_id` IN ({$target_id}) ";
		
		//starttime
    	$starttime = $_REQUEST['starttime'];
    	if(!empty($starttime) &&(strlen($starttime)!=19 || strtotime($starttime)===false))
    		return_value_json(false, 'msg', '系统出错：开始时间格式不正确');
    	else if(!empty($starttime)) {
    		$starttime = str_replace("T", " ", $starttime);
    		$condition .= " AND `alarm`.`start_time`>'{$starttime}' ";
    	}
    	
    	//endtime
    	$endtime = $_REQUEST['endtime'];
		if(!empty($endtime) &&(strlen($endtime)!=19 || strtotime($endtime)===false)) 
			return_value_json(false, 'msg', '系统出错：结束时间格式不正确');
		else if(!empty($endtime)) {
			$endtime = str_replace("T", " ", $endtime);
			$condition .= " AND `alarm`.`start_time`<'{$endtime}' ";
		}
		
		//type
		$type = $_REQUEST['type'];
		if(!empty($type)) $condition .= " AND `alarm`.`type`='{$type}' ";
		
		//rule_id
		$rule_id = $_REQUEST['rule_id'];
		if(!empty($rule_id)) $condition .= " AND `alarm`.`rule_id`='{$rule_id}' ";
		
		//checked表示是否已经确认
		if(isset($_REQUEST['checked']) && ($_REQUEST['checked']==0 || $_REQUEST['checked']==1)) {
			$condition .= " AND `alarm`.`checked`=" . $_REQUEST['checked'] . " ";
		}
		
		//filters
		$filters = $_REQUEST['filter'];
		if(!empty($filters)) $condition .= ' AND (' . $this->_getFiltersCondition($filters, $ambiguous) . ')';
		
		return $condition;
	}
	
	/**
	 * 根据ExtJS的GridFilter这个Feature提交的参数分析成数据库的WHERE条件
	 * @param unknown $filters filter参数，通常来自网页提交的参数，例如$_REQEST['filter']
	 * @param array $ambiguous 条件中可能会混淆的字段（例如有一个字段名称叫id，但是涉及的很多表都有这个字段，这里需要指定是哪个表的id，格式是"id"=>"table_name`.`id"，注意中间的`.`），
	 * 也可以给混淆字段改名（例如department字段实际指的是department_id，那么可以指定"department"=>"department_id"）
	 * @return string
	 */
	private function _getFiltersCondition($filters, $ambiguous=array()) {
		if (is_array($filters)) {
			$encoded = false;
		} else {
			$encoded = true;
			$filters = json_decode($filters);
		}
		
		$condition = '1';
		foreach ($filters as $filter) {
	        if ($encoded) {
	            $field = $filter->field;
	            $value = $filter->value;
	            $compare = isset($filter->comparison) ? $filter->comparison : null;
	            $filterType = $filter->type;
	        } else {
	            $field = $filter['field'];
	            $value = $filter['data']['value'];
	            $compare = isset($filter['data']['comparison']) ? $filter['data']['comparison'] : null;
	            $filterType = $filter['data']['type'];
	        }
	        
	        $field = (!empty($ambiguous) && !empty($ambiguous[$field])) ? $ambiguous[$field] : $field;
	        
			switch ($filterType) {
				case 'string':
					$condition .= " AND `{$field}` LIKE '%{$value}%'";
					break;
				case 'numeric': //comparison
					$comparison = $compare=='lt' ? '<=' : ($compare=='gt' ? '>=' : '=');
					$value +=0;
					$condition .= " AND `{$field}`{$comparison}{$value}";
					break;
				case 'list':
					$list = array();
					$hasNull = false;
					$values = is_array($value) ? $value : explode(',',$value);
					foreach ($values as $v) {
						$list[] = "'".$v."'";
						if($v=='') $hasNull = true;
					}
					if(!empty($list)) $condition .= " AND (`{$field}` IN (".implode(",", $list).")" . ($hasNull ? " OR `{$field}` IS NULL": '') . ")";
					break;
				case 'date':
					$comparison = $compare=='lt' ? '<=' : ($compare=='gt' ? '>=' : '=');
					$condition .= " AND DATE(`".$field."`) ".$comparison." '".date('Y-m-d',strtotime($value))."'";
					break;
				case 'boolean':
					$condition .= " AND `{$field}`='".($value=='true' ? 1 : 0)."'";
					break;
			}
		}
		
		return $condition;
	}
	
	private function _exportData2Excel() {
		$session_id = $this->_request('session_id');
		$filename = $this->_request('file');
		if(empty($session_id) || empty($filename)) return;
		
		session_id($session_id);
		session_write_close();
		ignore_user_abort(true);
		set_time_limit(0);
		
		R('File/setfilepercent', array($filename, '正在读取模版文件...'));
    	$objReader = PHPExcel_IOFactory::createReader('Excel2007');
    	$objPHPExcel = $objReader->load(EXCEL_TEMPLATES_PATH."alarm_template.xlsx");
    	$activeSheet = $objPHPExcel->getActiveSheet();
    	
    	if(!empty($_REQUEST['target_type'])) {
    		$activeSheet->setCellValue('A1', $_REQUEST['target_type'] . '报警列表');
    	}
    	
    	$time_str = $this->_getTimeString();
    	
    	if(!empty($time_str)) {
    		$activeSheet->setCellValue('A2', $time_str);
    	}
    	
    	$activeSheet->setCellValue('I2', '制表时间：'. date('Y-m-d H:i:s'));
    	
		R('File/setfilepercent', array($filename, '正在查询数据库...'));
		
		$condition = $this->_parseCondition(self::$filterAmbiguous);

		$Alarm = M('Alarm');
		check_error($Alarm);
		
		$alarms = $Alarm->join('`department` on `department`.`id`=`alarm`.`department_id`')
			->join('`device` on `device`.`id`=`alarm`.`device_id`')
			->join('`rule` on `rule`.`id`=`alarm`.`rule_id`')
			->join('`path_area` on `path_area`.`id`=`rule`.`path_area_id`')
			->field(array('`alarm`.`id`', '`alarm`.`type`', '`alarm`.`rule_id`', '`rule`.`label`' => 'rule_label',
					'`path_area`.`id`' => 'path_area_id', '`path_area`.`label`' => 'path_area_label',
					'`alarm`.`start_time`', 'check_time', 'checked',
					'`alarm`.`department_id`', '`department`.`name`'=>'department',
					'`alarm`.`target_type`', '`alarm`.`target_id`', '`alarm`.`target_name`',
					'`alarm`.`device_id`', '`device`.`label`' => 'device_label',
					'`alarm`.`start_time`', '`alarm`.`check_time`', '`alarm`.`checked`',
					'last_sms', 'sms_count', 'last_email', 'email_count', 'last_window', 'window_count'
			))
			->where($condition)
			->order('`alarm`.`start_time` ASC')
			->select();
		check_error($Alarm);
		
		$total = count($alarms);
		R('File/setfilepercent', array($filename, '处理数据库查询结果...', $total, 0));
		
		$lastTime = time();
		$baseRow = 4;
		foreach($alarms as $r => $dataRow) {
			$row = $baseRow + $r;
			if($r) $activeSheet->insertNewRowBefore($row,1);
		
			$activeSheet
				->setCellValue('A'.$row, $r+1)
				->setCellValue('B'.$row, $dataRow['start_time'])
				->setCellValue('C'.$row, $dataRow['type'])
				->setCellValue('D'.$row, $dataRow['rule_label'])
				->setCellValue('E'.$row, $dataRow['path_area_label'])
				->setCellValue('F'.$row, $dataRow['checked'] ? '√': '')
				->setCellValue('G'.$row, $dataRow['check_time'])
				->setCellValue('H'.$row, $dataRow['department'])
				->setCellValue('I'.$row, $dataRow['target_type'])
				->setCellValue('J'.$row, $dataRow['target_name'])
				->setCellValue('K'.$row, $dataRow['device_label'])
				->setCellValue('L'.$row, $dataRow['sms_count'])
				->setCellValue('M'.$row, $dataRow['email_count'])
				->setCellValue('N'.$row, $dataRow['window_count']);
			$activeSheet->getRowDimension($row)->setRowHeight(-1);
			
			if(time()-$lastTime) { //过了1秒
				$lastTime = time();
				R('File/setfilepercent', array($filename, '正在处理数据库查询结果...', $total, $r+1));
			}
		}
		
		return $objPHPExcel;
	}
	
	private function _getTimeString(){
		$time1 = 0;
		$time2 = MAX_VALUE;
		if(!empty($_REQUEST['filter'])) {
			$filterStartTime = $this->_getFilterStartTime($_REQUEST['filter']);
			if(isset($filterStartTime['lt'])) { //<=
				$time2 = strtotime($filterStartTime['lt'] . ' 23:59:59');
			}
			else if(isset($filterStartTime['gt'])) {//>=
				$time1 = strtotime($filterStartTime['gt'] . ' 00:00:00');
			}
			else if(isset($filterStartTime['eq'])) {//=
				$time1 = strtotime($filterStartTime['eq'] . ' 00:00:00');
				$time2 = strtotime($filterStartTime['eq'] . ' 23:59:59');
			}
		}
		
		if(!empty($_REQUEST['starttime'])) {
			$starttime = strtotime(str_replace("T", " ", $_REQUEST['starttime']));
			if($starttime && $time1<$starttime) {
				$time1 = $starttime;
			}
		}
		 
		if(!empty($_REQUEST['endtime'])) {
			$endtime = strtotime(str_replace("T", " ", $_REQUEST['endtime']));
			if($endtime && $time2>$endtime) {
				$time2 = $endtime;
			}
		}
		 
		$time_str = '';
		if($time1 != 0) {
			$time_str .= '从' . date('Y-m-d H:i:s', $time1);
		}
		 
		if($time2 != MAX_VALUE) {
			$time_str .= ' 到' . date('Y-m-d H:i:s', $time2);
		}
		
		return $time_str;
	}
	
	private function _getFilterStartTime($filters){
		if (is_array($filters)) {
			$encoded = false;
		} else {
			$encoded = true;
			$filters = json_decode($filters);
		}
		
		$result = array();
		foreach ($filters as $filter) {
			$filterType = $encoded ? $filter->type : $filter['data']['type'];
			$field = $encoded ? $filter->field : $filter['field'];
			if($filterType=='date' && $field=='start_time'){
				$result[$encoded ? $filter->comparison : $filter['data']['comparison']]
					= $encoded ? $filter->value : $filter['data']['value'];
			}
		}
		return $result;
	}
	
	//////////////////////////////
	
	//主要用于“确认”
	public function update() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$alarms = json_decode(file_get_contents("php://input"));
		if(!is_array($alarms)) {
			$alarms = array($alarms);
		}
		
		$Alarm = M('Alarm');
		check_error($Alarm);
		
		foreach ( $alarms as $alarm ) {
			$alarm->check_time = date('Y-m-d H:i:s');
			$Alarm->create($alarm);
			check_error($Alarm);
			
			if(false === $Alarm->save()) {
				return_value_json(false, 'msg', get_error($Alarm));
			}
		}
		
		return_value_json(true);
	}
	
	
	/**
	 * 报警“线程”
	 * 处理指定id的报警，直至报警信息全部发送或者用户确认了报警才结束“线程”
	 */
	public function thread() {
		$id = $_POST['id'];
		if(empty($id)) exit();
		
		$Alarm = M('Alarm');
		check_error($Alarm);
		
		
		$alarm = $Alarm->find($id);
		if(empty($alarm)) exit();
		
		$Rule = M('Rule');
		check_error($Rule);
		
		$rule = $Rule->find($Alarm->rule_id);
		if(empty($rule)) exit();
		
		$AlarmReceiver = M('AlarmReceiver');
		check_error($AlarmReceiver);
		
		$receivers = $AlarmReceiver
			->join('`user` on `user`.`id`=`alarm_receiver`.`receiver_id`')
			->field(array('`alarm_receiver`.`receiver_id`', '`user`.`mobile`', '`user`.`email`'))
			->where("`rule_id`='{$Rule->id}'")
			->select();
//		Log::write("\nID: ".$id. "\n". M()->getLastSql(), Log::SQL);
		if(empty($receivers)) exit();
		
		ignore_user_abort();
		set_time_limit(0);
		
		while(true) {
			$alarm = $Alarm->find($id);	//更新一下alarm
			if($alarm['checked'] //已经确认了
				|| (empty($rule['email']) || $alarm['email_count']>=$rule['email_repeat']) //既不用发送email
					&& ((empty($rule['sms']) || $alarm['sms_count']>=$rule['sms_repeat'])))//也不用发送短信
			{
				exit();	
			}
						
			if($rule['sms'] && $alarm['sms_count']<$rule['sms_repeat'] 
				&& $this->_timeup($alarm['last_sms'], $rule['sms_interval']))
			{
				$this->_send_alarm_sms($alarm, $rule, $receivers);
				$alarm['sms_count'] ++ ;
				$alarm['last_sms'] = date('Y-m-d H:i:s');
			}
						
			if($rule['email'] && $alarm['email_count']<$rule['email_repeat'] 
				&& $this->_timeup($alarm['last_email'], $rule['email_interval']))
			{
				$this->_send_alarm_email($alarm, $rule, $receivers);
				$alarm['email_count'] ++ ;
				$alarm['last_email'] = date('Y-m-d H:i:s');
			}
			
			$Alarm->save($alarm);
			
			sleep(60);
			
			//注：声音报警和窗口报警是被动式的（即客户请求alarm方法）不在此处处理
		}
	}
	
	/**
	 * 声音报警 和 窗口报警
	 * 根据用户的session里的用户id，返回该用户当前应该接收到的报警
	 * 返回：
	 * array(
	 * 	'total': 
	 * 	'alarms': [{
	 * 		'id': //报警id 
	 * 		'start_time': 
	 * 		'msg':
	 * 		'sound': 
	 * 		'window':
	 * 		'window_countdown': //窗口剩余次数（包括此次）
	 *  }]
	 * )
	 */
	public function alarm() {
		if(!session('logined')) return_json(true, 0, 'alarms', array());
		
		$user = session('user');
		
//		Log::write("\nUSER:".print_r($user, true), Log::INFO);
		
		$Alarm = M('Alarm');
		check_error($Alarm);
		
		$Alarm->execute("SET sql_mode = 'NO_UNSIGNED_SUBTRACTION'");
		
		$alarms = $Alarm->join('`rule` ON `rule`.`id`=`alarm`.`rule_id`')
			->join('INNER JOIN `alarm_receiver` ON `alarm_receiver`.`rule_id`=`alarm`.`rule_id`')
			->field(array('`alarm`.`id`', '`alarm`.`start_time`', '`alarm`.`msg`', 
						'`rule`.`sound` AND `alarm_receiver`.`sound`' => 'sound', 
						'`rule`.`window` AND `alarm_receiver`.`window` AND (`rule`.`window_repeat`-`alarm`.`window_count`>0)' => 'window',
						'`rule`.`window_repeat`-`alarm`.`window_count`' => 'window_countdown'
			))
			->where("`alarm_receiver`.`receiver_id`='".$user['userId']."' " .
					"AND `alarm`.`checked`='0' " .
					"AND ((`alarm_receiver`.`window`='1' AND `rule`.`window`='1' AND (`rule`.`window_repeat`-`alarm`.`window_count`)>0) " .
//					"OR (`alarm_receiver`.`sound`='1' AND `rule`.`sound`='1')" .
					")")
			->select();
		check_error($Alarm);
//		Log::write("\n".M()->getLastSql(), Log::SQL);
		
		$ids = array();
		foreach ( $alarms as $key => $alarm ) {
			$ids[] = $alarm['id'];
		}
		if(!empty($ids)) {
			$Alarm->execute("UPDATE `alarm` SET `last_window`='".date('Y-m-d H:i:s')."', `window_count`=`window_count`+1 " .
					"WHERE `id` IN (".implode(",", $ids).")");
		}
		
		return_json(true, null, 'alarms', $alarms);
	}
	
	/**
	 * 判断是否到时间了（比如到时间发送短信）
	 * @param string $last 上次操作时间
	 * @param int $interval 时间间隔（分）
	 * @return boolean 如果到时间（误差在30秒内）或者上次操作时间为空，则返回true，否则（包括参数不正确）都返回false
	 */
	private function _timeup($last, $interval){
		$interval = intval($interval);
		if(empty($interval)) return false;
		
		if(empty($last)) return true;
		
		$t = strtotime($last);
		if($t===FALSE || $t===-1) return false;	//如果$last不能解释成时间也将返回false
		
		return (abs( time() - $t - $interval*60 ) < 30);
	}
	
	/**
	 * 根据$rule设置发送报警短信给$receivers
	 */
	private function _send_alarm_sms($alarm, $rule, $receivers) {
		$content = str_replace('{时间}', $alarm['start_time'], $rule['sms_text']);
		$content = str_replace('{消息}', $alarm['msg'], $content);
		
		$sms = array(
			'type' 			=> '报警',
			'related_id' 	=> $alarm['id'],
			'content'		=> $content,
			'send_time'		=> date('Y-m-d H:i:s')
		);
		
		$mobiles = array();
		$smsData = array();
		foreach ( $receivers as $key => $receiver ) {
			$rcv = array(
				'user_id'	=> $receiver['receiver_id'],
				'mobile'	=> $receiver['mobile']
			);
			if(is_mobile_number($receiver['mobile'])) {
				$mobiles[] = $receiver['mobile'];
				$smsData[] = array_merge($sms, $rcv);
			}
			else {
				$smsData[] = array_merge($sms, $rcv, array('success'=>0, 'result'=>'手机号码不是正确的手机号码，所以没有发送短信'));
			}
		}
		
		$Sms = D('Sms');
		$Sms->send($smsData);
	}
	
	/**
	 * 根据$rule设置发送报警email给$receivers
	 */
	private function _send_alarm_email($alarm, $rule, $receivers) {
		$content = str_replace('{时间}', $alarm['start_time'], $rule['email_text']);
		$content = str_replace('{消息}', $alarm['msg'], $content);
		
		$email = array(
			'type' 			=> '报警',
			'related_id' 	=> $alarm['id'],
			'content'		=> $content,
			'title'			=> '报警邮件',//暂时都用这个标题
			'send_time'		=> date('Y-m-d H:i:s')
		);
		
		$emails = array();
		$emailData = array();
		foreach ( $receivers as $key => $receiver ) {
			$rcv = array(
				'user_id'	=> $receiver['receiver_id'],
				'email'		=> $receiver['email']
			);
			if(is_email_well_form($receiver['email'])) {
				$emails[] = $receiver['email'];
				$emailData[] = array_merge($email, $rcv);
			}
			else {
				$emailData[] = array_merge($email, $rcv, array('success'=>0, 'result'=>'电子邮件地址格式不正确，所以没有发送电子邮件'));
			}
		}
		
		$Email = D('Email');
		check_error($Email);
		
		if(empty($emails))	{
			if(!empty($emailData)) $Email->log($emailData); 
			return;
		}
		else {
			$Email->send($emailData);
		}
	}
	
	public function deleteall() {
    	$condition = $this->_parseCondition(self::$filterAmbiguous);
    	
		$Alarm = M('Alarm');
		check_error($Alarm);
		
		$total = $Alarm->join('`department` on `department`.`id`=`alarm`.`department_id`')
				->join('`device` on `device`.`id`=`alarm`.`device_id`')
				->join('`rule` on `rule`.`id`=`alarm`.`rule_id`')
				->join('`path_area` on `path_area`.`id`=`rule`.`path_area_id`')
				->where($condition)
				->delete();
		if($total===false) {
			return_value_json(false, "msg", get_error($Alarm));
		}
		else {
			return_value_json(true, "msg", "共删除了 " . $total . " 条报警记录");
		}
	}
	
	public function deleteselected() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		if(!isset($_POST['ids'])) {
			return_value_json(false, 'msg', '系统错误：需要删除的记录请求为空');
		}
		else {
			$Alarm = M('Alarm');
			check_error($Alarm);

			$total = $Alarm->where("`id` IN (".$_POST['ids'].")")->delete();
			
			if($total===false) {
				return_value_json(false, "msg", get_error($Alarm));
			}
			else {
				return_value_json(true, "msg", "共删除了 " . $total . " 条报警记录");
			}
		}
	}
}
?>