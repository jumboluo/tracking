<?php
class LogAction extends Action{
	
	public function user() {
		$condition = $this->_parseCondition('user');
		
		$UserLog = M('UserLog');
		check_error($UserLog);
		
		$total = $UserLog->join('`user` on `user`.`id`=`user_log`.`user_id`')
			->where($condition)
			->count();
		check_error($UserLog);
			
		$UserLog->join('`user` on `user`.`id`=`user_log`.`user_id`')
			->where($condition)
			->field('`user_log`.*')
			->order('`user_log`.`time` ASC, `user_log`.`id` ASC');
				
		$page = $this->_request('page') + 0;
		$limit = $this->_request('limit') + 0;
		if($page && $limit) $UserLog->limit($limit)->page($page);
		
		$logs = $UserLog->select();
		check_error($UserLog);
		
		return_json(true, $total, 'logs', $logs);
	}
	
	
	public function device() {
		$condition = $this->_parseCondition('device');
		
		$DeviceLog = M('DeviceLog');
		check_error($DeviceLog);
		
		$total = $DeviceLog->join('`device` on `device`.`id`=`device_log`.`device_id`')
			->where($condition)
			->count();
		check_error($DeviceLog);
			
		$DeviceLog->join('`device` on `device`.`id`=`device_log`.`device_id`')
			->where($condition)
			->field('`device_log`.*')
			->order('`device_log`.`time` ASC, `device_log`.`id` ASC');
				
		$page = $this->_request('page') + 0;
		$limit = $this->_request('limit') + 0;
		if($page && $limit) $DeviceLog->limit($limit)->page($page);
		
		$logs = $DeviceLog->select();
		check_error($DeviceLog);
		
		return_json(true, $total, 'logs', $logs);
	}
	
	public function adduserlog($operation, $result, $result_type, $memo='') {
		$user = session('user');

		$data = array(
			'user_id' => (empty($user) || !is_array($user)) ? 0 : $user['userId'],
			'user_name' => (empty($user) || !is_array($user)) ? '' : (empty($user['name']) ? $user['userName'] : $user['name']) ,
			
			'time' => date('Y-m-d H:i:s'),
			'operation' => $operation,
			'result' => $result,
			'result_type' => $result_type,
			'memo' => $memo
		);
		
		$UserLog = M('UserLog');
		$UserLog->add($data);
	}
	
	public function adddevicelog($device_id, $device_type, $target_id, $target_type, $target_name, $operation, $result, $result_type, $memo='') {
		$data = array(
			'device_id' => $device_id,
			'device_type' => $device_type,
			'target_id' => $target_id,
			'target_type' => $target_type,
			'target_name' => $target_name,
			
			'time' => date('Y-m-d H:i:s'),
			'operation' => $operation,
			'result' => $result,
			'result_type' => $result_type,
			'memo' => $memo
		);
		

		$DeviceLog = M('DeviceLog');
		$DeviceLog->add($data);
	}
	
	/**
	 * 根据请求添加用户日志（例如用于进入和离开定位中心）
	 */
	public function addlog() {
		$operation = $this->_request('operation');
		if(empty($operation)) return;
		
		$this->adduserlog($operation, $operation.'成功', '成功');
		
		return_value_json('true');
	}
	
	public function userlogexporttoexcel() {
		$file = time().rand(1000,9999).'.xlsx';
		$url = U('Log/douserlogexporttoexcel');
		$_REQUEST['file'] = $file;
		$_REQUEST['session_id'] = session_id();
		$postdata = http_build_query($_REQUEST);
		$this->_socketPost($postdata, $url);
		return_value_json(true, 'file', $file);
	}

	public function douserlogexporttoexcel() {
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
	
	public function userlogexporttopdf() {
		$file = time().rand(1000,9999).'.pdf';
		$url = U('Log/douserlogexporttopdf');
		$_REQUEST['file'] = $file;
		$_REQUEST['session_id'] = session_id();
		$postdata = http_build_query($_REQUEST);
		$this->_socketPost($postdata, $url);
		return_value_json(true, 'file', $file);
	}
	
	public function douserlogexporttopdf() {
		require_once dirname(__FILE__)."/../Util/PHPExcel/PHPExcel/IOFactory.php";
		
		$session_id = $this->_request('session_id');
		$filename = $this->_request('file');
		if(empty($session_id) || empty($filename)) return;
		
		$objPHPExcel = $this->_exportData2Excel();
		
		//坑爹的dompdf对表格的支持非常不好
// 		$rendererName = PHPExcel_Settings::PDF_RENDERER_DOMPDF;
// 		$rendererLibrary = 'domPDF0.6.0beta3';
// 		$rendererLibraryPath = dirname(__FILE__).'/../Util/dompdf/';

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
	
	/**
	 * 没用，
	 * @return void|unknown
	 */
	private function _exportData2Excel() {
		$session_id = $this->_request('session_id');
		$filename = $this->_request('file');
		if(empty($session_id) || empty($filename)) return;
		
		session_id($session_id);
		session_write_close();
		ignore_user_abort(true);
		set_time_limit(0);
		
		R('File/setfilepercent', array($filename, '正在读取模版文件...'));
		$objReader = PHPExcel_IOFactory::createReader('Excel5');
		$objPHPExcel = $objReader->load(EXCEL_TEMPLATES_PATH."userlog_template.xls");
		
		R('File/setfilepercent', array($filename, '正在查询数据库...'));
		
		$condition = $this->_parseCondition('user');
		
		$UserLog = M('UserLog');
		check_error($UserLog);
		
		$logs = $UserLog
		->join('`user` on `user`.`id`=`user_log`.`user_id`')
		->where($condition)
		->field('`user_log`.*')
		->order('`user_log`.`time` ASC, `user_log`.`id` ASC')
		->select();
		check_error($UserLog);
		
		$total = count($logs);
		R('File/setfilepercent', array($filename, '处理数据库查询结果...', $total, 0));
		
		$lastTime = time();
		$baseRow = 3;
		foreach($logs as $r => $dataRow) {
			$row = $baseRow + $r;
			if($r) $objPHPExcel->getActiveSheet()->insertNewRowBefore($row,1);
		
			$objPHPExcel->getActiveSheet()
			->setCellValue('A'.$row, $dataRow['time'])
			->setCellValue('B'.$row, $dataRow['user_name'])
			->setCellValue('C'.$row, $dataRow['operation'])
			->setCellValue('D'.$row, $dataRow['result'])
			->setCellValue('E'.$row, $dataRow['result_type'])
			->setCellValue('F'.$row, $dataRow['memo']);
			$objPHPExcel->getActiveSheet()->getRowDimension($row)->setRowHeight(-1);
			
			if(time()-$lastTime) { //过了1秒
				$lastTime = time();
				R('File/setfilepercent', array($filename, '正在处理数据库查询结果...', $total, $r+1));
			}
		}
		
		return $objPHPExcel;
	}
	
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
	
	private function _parseCondition($table) {
		$condition = array(
				'_string' => '1'
		);
		
		$startTime = $this->_request('startTime');
		if(!empty($startTime)) {
			if(strlen($startTime)!=19) return_value_json(false, 'msg', '系统出错：开始时间格式不正确，可以不填写开始时间，如果填写，请填写正确的时间');
			$startTime = str_ireplace("T", " ", $startTime);
			if(strtotime($startTime)===false) return_value_json(false, 'msg', '系统出错：开始时间字符串无法解释成时间，可以不填写开始时间，如果填写，请填写正确的时间');
			$condition['_string'] .= " AND `{$table}_log`.`time`>='{$startTime}'";
		}
		
		$endTime = $this->_request('endTime');
		if(!empty($endTime)) {
			if(strlen($endTime)!=19) return_value_json(false, 'msg', '系统出错：开始时间格式不正确，可以不填写开始时间，如果填写，请填写正确的时间');
			$endTime = str_ireplace("T", " ", $endTime);
			if(strtotime($endTime)===false) return_value_json(false, 'msg', '系统出错：开始时间字符串无法解释成时间，可以不填写开始时间，如果填写，请填写正确的时间');
			$condition['_string'] .= " AND `{$table}_log`.`time`<='{$endTime}'";
		}
		
		$departmentId = $this->_request('departmentId') + 0;
		if(!empty($departmentId)) {
			$condition['_string'] .= " AND `{$table}`.`department_id`='{$departmentId}'";
		}
		
		$id = $this->_request("{$table}Id") + 0;
		if(!empty($id)) {
			$condition['_string'] .= " AND `{$table}_log`.`{$table}_id`='{$id}'";
		}
		
		$operation = $this->_request('operation');
		if(!empty($operation)) {
			$condition['_string'] .= " AND `{$table}_log`.`operation` LIKE '%{$operation}%'";
		}
		
		$resultType = $this->_request('resultType');
		if(!empty($resultType)) {
			$condition['_string'] .= " AND `{$table}_log`.`result_type`='{$resultType}'";
		}
		
		$filters = $_REQUEST['filter'];
		if(!empty($filters)) $condition['_string'] .= ' AND (' . $this->_getFiltersCondition($filters) . ')';
		
		return $condition;
	}
	
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
	
	public function test() {
// 		ini_set("memory_limit","1024M");
		
// 		$html = file_get_contents('../'.EXPORT_TEMP_PATH.'13554680705609.html');;
		
// 		require_once dirname(__FILE__)."/../Util/MPDF54/mpdf.php";
		
// 		$mpdf=new mPDF();
		
// 		$mpdf->useAdobeCJK = true;		// Default setting in config.php
// 		// You can set this to false if you have defined other CJK fonts
		
// 		$mpdf->SetAutoFont(AUTOFONT_CJK);	//	AUTOFONT_CJK | AUTOFONT_THAIVIET | AUTOFONT_RTL | AUTOFONT_INDIC	// AUTOFONT_ALL
// 		// () = default ALL, 0 turns OFF (default initially)
		
// 		$mpdf->WriteHTML($html);
		
// 		$filename = '../'.EXPORT_TEMP_PATH.'13554680705609'.'_'.rand(100,999).'.pdf';
		
// 		$mpdf->Output($filename);
// 		echo $filename;
// 		Log::write(date('H:i:s') . " Peak memory usage: " . (memory_get_peak_usage(true) / 1024 / 1024) . " MB" );
		
// 		$objReader = PHPExcel_IOFactory::createReader('Excel5');
// 		$objPHPExcel = $objReader->load(EXCEL_TEMPLATES_PATH."track_template.xls");
		$objReader = PHPExcel_IOFactory::createReader('Excel2007');
		$objPHPExcel = $objReader->load(EXCEL_TEMPLATES_PATH."track_template.xlsx");
		
		$row = 9;
		$objPHPExcel->getActiveSheet()
		->setCellValue('A'.$row, date('Y-m-d H:i:s'))
		->setCellValue('B'.$row, '内蒙古自治区包头市东河区,西脑包南五道巷-巴彦塔拉西大街交叉路口,包头市东河区国家税务局,中国石油宁鹿大酒店,出租车上下客站附近')
		->setCellValue('C'.$row, 25.33)
		->setCellValue('D'.$row, '东北')
		->setCellValue('E'.$row, '正常');
		$objPHPExcel->getActiveSheet()->getRowDimension($row)->setRowHeight(-1);
		
// 		$ws  = "\nwidth A:" . $objPHPExcel->getActiveSheet()->getColumnDimension('A')->getWidth();
// 		$ws .= "\nwidth B:" . $objPHPExcel->getActiveSheet()->getColumnDimension('B')->getWidth();
// 		$ws .= "\nwidth C:" . $objPHPExcel->getActiveSheet()->getColumnDimension('C')->getWidth();
// 		$ws .= "\nwidth D:" . $objPHPExcel->getActiveSheet()->getColumnDimension('D')->getWidth();
// 		$ws .= "\nwidth E:" . $objPHPExcel->getActiveSheet()->getColumnDimension('E')->getWidth();
// 		Log::write($ws);
		
		$widthPT = ($objPHPExcel->getActiveSheet()->getColumnDimension('A')->getWidth()
				+$objPHPExcel->getActiveSheet()->getColumnDimension('B')->getWidth()
				+$objPHPExcel->getActiveSheet()->getColumnDimension('C')->getWidth()
				+$objPHPExcel->getActiveSheet()->getColumnDimension('D')->getWidth()
				+$objPHPExcel->getActiveSheet()->getColumnDimension('E')->getWidth()) 
// 				* 5.251282; //纯属经验：导出成Excel5,但是图片宽度被拉大了(1.1438451倍)
				* 6; //经验：导出成Excel2007
// 				* 4.7499; //经验：导出成PDF
		Log::write("\n-----------widthPT:".$widthPT."-----------");
		$widthPX = round($widthPT*4/3);
		Log::write("\n-----------widthPX:".$widthPX."-----------");
		
		$heightPT = 300;
		$heightPX = 400;
		$objPHPExcel->getActiveSheet()->getRowDimension('6')->setRowHeight($heightPT);
		
		$rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
		$rendererLibrary = 'mPDF5.4';
		$rendererLibraryPath = dirname(__FILE__).'/../Util/MPDF54';
		
		PHPExcel_Settings::setPdfRenderer(
		$rendererName,
		$rendererLibraryPath
		);

// 		$widthPX +=-2;	//Excel5和Excel2007才需要
// 		$heightPX +=-2;
		$url = 'http://api.map.baidu.com/staticimage?width='.$widthPX.'&height='.$heightPX.'&center=116.468265,39.90692&zoom=11&markers=116.418822,39.859083|116.49586,39.960917&markerStyles=l,|l,';
		$newfname = '../'.EXPORT_TEMP_PATH.'staticimage.png';
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
		
		$objDrawing = new PHPExcel_Worksheet_Drawing();
		$objDrawing->setName('map');
		$objDrawing->setDescription('Map');
		$objDrawing->setPath('../'.EXPORT_TEMP_PATH.'staticimage.png');
// 		$objDrawing->setOffsetX(1);	//Excel5和Excel2007才需要
// 		$objDrawing->setOffsetY(1);
// 		$objDrawing->setHeight($heightPT);
		$objDrawing->setCoordinates('A6');
		$objDrawing->getShadow()->setVisible(true);
		$objDrawing->getShadow()->setDirection(45);
		$objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
		
		$wd  = "\nWidth: ".$objDrawing->getWidth();
		$wd .= "\nHeight: ".$objDrawing->getHeight();
		Log::write($wd);
		
		$file = EXPORT_TEMP_PATH.time().rand(1000,9999).'.pdf';
		
		$objPHPExcel->getActiveSheet()->setShowGridlines(false); //要导出成PDF（或者HTML）这个比较重要，否则表格线会造成出来的结果不好
		ini_set("memory_limit","1024M"); //MPDF消耗内存比较厉害
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'PDF');
  		$objWriter->setImagesRoot('..');
			$objWriter->setPrintParams(
					'A4',
					'P',
					'',
					'',
					'',
					'',
					16,
					16,
					15,
					15,
					9,
					9,
					true,
					null,
					null
					);
  		$objWriter->save('../'.$file);
  		
  		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'HTML');
  		$objWriter->save('../'.str_replace('.pdf', '.html', $file));
  		
  		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
  		$objWriter->save('../'.str_replace('.pdf', '.xls', $file));
  		
  		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
  		$objWriter->save('../'.str_replace('.pdf', '.xlsx', $file));
  		
	}
}
?>