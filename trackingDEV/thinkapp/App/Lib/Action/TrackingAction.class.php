<?php

import('App.Util.LBS.Geometry');

class TrackingAction extends Action {
	
	public function _initialize() {
	}

    public function statistics() {
    	$Vehicle = M('Vehicle');
    	check_error($Vehicle);
    	
		$targetsQuery = D('ManageTarget')->getTargetsQuery();
    	
    	$total = $Vehicle->join("`device` on (`vehicle`.`id`=`device`.`target_id` AND `device`.`target_type`='车辆')")
    			->where($targetsQuery)
    			->count();
    	
    	$online = $Vehicle->join("`device` on (`vehicle`.`id`=`device`.`target_id` AND `device`.`target_type`='车辆')")
				->join('`location` on `location`.`id`=`device`.`last_location`')
				->where("`location`.`online`='在线' AND " . $targetsQuery)
				->count();
    	$offline = $Vehicle->join("`device` on (`vehicle`.`id`=`device`.`target_id` AND `device`.`target_type`='车辆')")
				->join('`location` on `location`.`id`=`device`.`last_location`')
				->where("`location`.`online`='离线' AND " . $targetsQuery)
				->count();
		
		$Alarm = M('Alarm');
		$alarm = $Alarm->join("LEFT JOIN `device` on `alarm`.`device_id`=`device`.`id`")
				->where("`target_type`='车辆' AND `checked`=0 AND " . $targetsQuery)
				->count();
				
//		Log::write(M()->getLastSql(), Log::SQL);
		
    	return_json(true, 1, 'statistics', array(array('total'=>$total,'online'=>$online, 'offline'=>$offline, 'alarm'=>$alarm, 'time'=>date('Y-m-d H:i:s'))));
    }
    
    public function index() {
    	return $this->refresh();
    }
    
    public function refresh() {
    	$mode = $this->_request('mode');
   		
   		$this->refreshOnlineState();
   		
    	if(empty($mode) || $mode=='locate_all') {
    		return $this->_allVehicleLastLocations() ;
    	}
    	elseif($mode=='tracking') {
    		return $this->_getTrackings() ;
    	}
    }
    
    /**
	 * center操作返回监控中心查询的车辆列表
	 */
    public function query() {
		$Vehicle = M('Vehicle');
		check_error($Vehicle);
		
		$condition = array(
			'_string' => D('ManageTarget')->getTargetsQuery()
		);
		
		
		if($_REQUEST['online']=='在线') {
			$condition['_string'] .= " AND (`location`.`online`='在线') ";
		}
		else if($_REQUEST['online']=='离线') { //有些设备从来就没有location信息的，属于离线。
			$condition['_string'] .= " AND (`location`.`online` IS NULL OR `location`.`online`<>'在线') ";
		}
		
		if($_REQUEST['nolocation']=='1') {
			$condition['_string'] .= " AND (`location`.`baidu_lat` IS NULL OR `location`.`baidu_lng` IS NULL) ";
		}
		
		if(!empty($_REQUEST['department'])) {
			$condition['_string'] .= " AND (`department`.`name` LIKE '%{$_REQUEST['department']}%') ";
		}
		
		if(!empty($_REQUEST['fuzzy'])) {
			$condition['_string'] .= " AND (";
			$condition['_string'] .= " `vehicle`.`number` LIKE '%{$_REQUEST['fuzzy']}%' "; //注：MYSQL的like其实是不区分大小写的，也就是说like '%k%'将查询到包含K和k的
			$condition['_string'] .= " OR `driver`.`name` LIKE '%{$_REQUEST['fuzzy']}%' ";
			$condition['_string'] .= " OR `device`.`label` LIKE '%{$_REQUEST['fuzzy']}%' ";
			$condition['_string'] .= " ) ";
		}
		
		$trackings = $Vehicle->join('`department` on `department`.`id`=`vehicle`.`department_id`')
			->join('`driver` on `driver`.`id`=`vehicle`.`driver_id`')
			->join("`device` on (`vehicle`.`id`=`device`.`target_id` AND `device`.`target_type`='车辆')")
			->join('`location` on `location`.`id`=`device`.`last_location`')
			->field(array('`vehicle`.`id`'=>'vehicle_id', '`vehicle`.`number`', 
						'`device`.`target_type`', '`device`.`target_id`', '`device`.`target_name`', 
						'`device`.`id`', '`device`.`type`', '`device`.`label`', 'interval', 'delay', 
						'`vehicle`.`department_id`', '`department`.`name`'=>'department', 
						'`driver`.`id`'=>'driver_id', '`driver`.`name`'=>'driver',
						'last_location', '`location`.`time`' , 'state', 'online', '`device`.`mobile_num`',
						'baidu_lat', 'baidu_lng', 'speed', 'direction',  '`location`.`address`',
						'CONCAT(`state`, `online`)' => 'state_online', '`location`.`range`'
						))
			->distinct(true)
			->where($condition)
			->order('`department`.`sequence` ASC, `department`.`id` ASC, `vehicle`.`sequence` ASC')
			->select();
		check_error($Vehicle);
//		Log::write(M()->getLastSql(), Log::SQL);
		
		return_value_json(true, 'children', $this->_trackingTree($trackings));
    }
    
    /**
     * 把平坦的数据格式化成一个树形结构的数组
     * @param unknown_type $trackings
     */
    private function _trackingTree(&$trackings) {
    	$re = array();
    	$curDepartment = -1;
    	$curDepartmentIndex = -1;
    	foreach ($trackings as $tracking) {
    		$tracking['tree_text'] = $tracking['number'];
    		$tracking['leaf'] = true;
    		$tracking['checked'] = false;
    		$tracking['department'] = empty($tracking['department_id']) ? '未分组' : $tracking['department'];
    		$tracking['department_id'] = (empty($tracking['department_id'])) ? 0 : $tracking['department_id'];
    		if($curDepartment!=$tracking['department_id']) {
    			//新的分组
    			if($curDepartmentIndex>=0) {
    				$childrenCount = count($re[$curDepartmentIndex]['children']);
    				$re[$curDepartmentIndex]['tree_text'] .= " ( " . $childrenCount ." ) " ;
    				$re[$curDepartmentIndex]['interval'] = $childrenCount;
    			}
    			$curDepartment = $tracking['department_id'];
    			++$curDepartmentIndex;
    			$group = array(
    					'tree_text' => $tracking['department'],
    					'expanded'=>($curDepartmentIndex==0),
    					'checked'=> false,
    					'state_online'=> '分组',
    					'children' => array($tracking)
    			);
    			$re[] = $group;
    		}
    		else {
    			$re[$curDepartmentIndex]['children'][] = $tracking;
    		}
    	}
    	if($curDepartmentIndex>=0) {
    		$childrenCount = count($re[$curDepartmentIndex]['children']);
    		$re[$curDepartmentIndex]['tree_text'] .= " ( " . $childrenCount ." ) " ;
    		$re[$curDepartmentIndex]['interval'] = $childrenCount;
    	}
    	return $re;
    }

    /**
     * device操作分页返回指定设备在指定时间里的定位数据，数据模式：DeviceLocation
     * 用于轨迹传标签及响应的数据标签的表格
     */
    public function device() {
    	$condition = $this->_parseCondition();
    
    	$Location = M('Location');
    	check_error($Location);
    
    	$total = $Location
    	->join('`device` on `device`.`id`=`location`.`device_id`')
    	->join('`department` on `department`.`id`=`device`.`department_id`')
    	->where($condition)->count();
    	check_error($Location);
    
    	$Location
    	->join('`device` on `device`.`id`=`location`.`device_id`')
    	->join('`department` on `department`.`id`=`device`.`department_id`')
    	->field(array('`location`.`id`',
    			'`device`.`department_id`', '`department`.`name`'=>'department',
    			'`location`.`device_id`', '`device`.`type`', '`device`.`label`',
    			'`device`.`target_type`', '`device`.`target_id`', '`device`.`target_name`',
    			'`location`.`time`' , 'state', 'online', '`location`.`address`' ,
    			'baidu_lat', 'baidu_lng', 'speed', 'direction',
    			'mcc', 'mnc', 'lac', 'cellid', '`location`.`range`'
    	))
    	->where($condition)
    	->order('`time` ASC');	//按时间顺序
    
    	$page = $_REQUEST['page'] + 0;
    	$limit = $_REQUEST['limit'] + 0;
    	if($page && $limit) $Location->limit($limit)->page($page);
    
    	$locations = $Location->select();
    	check_error($Location);
    
    	return_json(true, $total, 'locations', $locations);
    }
    
    public function devicelocationtoexcel() {
    	$file = time().rand(1000,9999).'.xlsx';
    	$url = U('Tracking/dodevicelocationtoexcel');
    	$_REQUEST['file'] = $file;
    	$_REQUEST['session_id'] = session_id();
    	$postdata = http_build_query($_REQUEST);
    	$this->_socketPost($postdata, $url);
    	return_value_json(true, 'file', $file);
    }
    
    public function dodevicelocationtoexcel() {
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
    
    public function devicelocationtopdf() {
    	$file = time().rand(1000,9999).'.pdf';
    	$url = U('Tracking/dodevicelocationtopdf');
    	$_REQUEST['file'] = $file;
    	$_REQUEST['session_id'] = session_id();
    	$postdata = http_build_query($_REQUEST);
    	$this->_socketPost($postdata, $url);
    	return_value_json(true, 'file', $file);
    }
    
    public function dodevicelocationtopdf() {
    	$session_id = $this->_request('session_id');
    	$filename = $this->_request('file');
    	if(empty($session_id) || empty($filename)) return;
    
    	$objPHPExcel = $this->_exportData2Excel('PDF');
    
    	if(empty($objPHPExcel)) {
    		R('File/fileexit', array($filename, '导出成Excel失败'));
    	}
    
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
    	$objWriter->setImagesRoot('..');
    
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
    
    	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'HTML');
    	$objWriter->save('../'.str_replace('.pdf', '.html', $file));
    
    	R('File/fileexit', array($filename));
    }
    
    private function _exportData2Excel($target='Excel2007') {
    	require_once dirname(__FILE__)."/../Util/PHPExcel/PHPExcel/IOFactory.php";
    
    	$session_id = $this->_request('session_id');
    	$filename = $this->_request('file');
    	$device_id = $this->_request('device') + 0;
    	if(empty($session_id) || empty($filename) || empty($device_id)) return;
    
    	session_id($session_id);
    	session_write_close();
    	ignore_user_abort(true);
    	set_time_limit(0);
    
    	R('File/setfilepercent', array($filename, '正在读取模版文件...'));
    	$objReader = PHPExcel_IOFactory::createReader('Excel2007');
    	$objPHPExcel = $objReader->load(EXCEL_TEMPLATES_PATH."track_template.xlsx");
    	$activeSheet = $objPHPExcel->getActiveSheet();
    
    	$condition = $this->_parseCondition();
    
    	//先查一下Device信息，以便在没有定位数据的情况下仍然能填写表头数据
    	$Device = M('Device');
    	check_error($Device);
    
    	R('File/setfilepercent', array($filename, '正在查询设备信息...'));
    	$device = $Device
    	->join('`department` on `department`.`id`=`device`.`department_id`')
    	->field(array('`target_type`', '`target_name`', '`department`.`name`'=>'department_name', ))
    	->where("`device`.`id`=$device_id")
    	->find();
    	check_error($Device);
    
    	if(empty($device)) {
    		R('File/fileexit', array($filename, '设备id错误：无法查找到指定id的设备'));
    	}
    
    	R('File/setfilepercent', array($filename, '正在处理表头信息...'));
    
    	//写表头信息：
    	$activeSheet
    	->setCellValue('A1', $device['target_type'] . '轨迹数据')
    	->setCellValue('A2', '定位对象：' . $device['target_name'])
    	->setCellValue('A3', '所属分组：' . $device['department_name'])
    	->setCellValue('C2', '从：' . $_REQUEST['startTime']);
    	if(!empty($_REQUEST['endTime']))
    		$activeSheet->setCellValue('C3', '到：' . $_REQUEST['endTime']);
    
    
    	$Location = M('Location');
    	check_error($Location);
    
    	R('File/setfilepercent', array($filename, '正在查询数据库...'));
    
    	$locations = $Location
    	->join('`device` on `device`.`id`=`location`.`device_id`')
    	->join('`department` on `department`.`id`=`device`.`department_id`')
    	->field(array('`location`.`id`',
    			'`device`.`department_id`', '`department`.`name`'=>'department',
    			'`location`.`device_id`', '`device`.`type`', '`device`.`label`',
    			'`device`.`target_type`', '`device`.`target_id`', '`device`.`target_name`',
    			'`location`.`time`' , 'state', 'online', '`location`.`address`' ,
    			'baidu_lat', 'baidu_lng', 'speed', 'direction',
    			'mcc', 'mnc', 'lac', 'cellid', '`location`.`range`'
    	))
    	->where($condition)
    	->order('`time` ASC')	//按时间顺序
    	->select();
    	check_error($Location);
    
    	if(empty($locations)) {
    		$activeSheet->setCellValue('A6', '没有定位数据');
    	}
    	else {
    		$center = $this->_request('center');
    		$width = $this->_request('width') + 0;
    		$height = $this->_request('height') + 0;
    		$zoom = $this->_request('zoom') + 0;
    		if(empty($center) || empty($width) || empty($height) || empty($zoom)) {
    			$activeSheet->setCellValue('A6', '地图参数不正确，无法创建地图');
    		}
    		else {
    			$mapparams = array(
    					'center'	=>$center,
    					'width'		=>$width,
    					'height'	=>$height,
    					'zoom'		=>$zoom
    			);
    		}
    	}
    
    	$total = count($locations);
    	R('File/setfilepercent', array($filename, '正在处理数据库查询结果...',$total, 0));
    	$lastTime = time();
    	$baseRow = 9;
    	$startMarker = null;
    	$endMarker = null;
    	$paths = array();
    	foreach($locations as $r => $dataRow) {
    		$row = $baseRow + $r;
    		if($r){
    			$activeSheet->insertNewRowBefore($row,1);
    
    		}
    		if(!empty($dataRow['baidu_lat']) && !empty($dataRow['baidu_lng'])) {
    			$endMarker = $p = $dataRow['baidu_lng'] . "," . $dataRow['baidu_lat'];
    			if(empty($startMarker)) $startMarker = $p;
    			$paths[] = $p;
    		}
    
    		$activeSheet
    		->setCellValue('A'.$row, $dataRow['time'])
    		->setCellValue('B'.$row, $dataRow['address'])
    		->setCellValue('C'.$row, $dataRow['speed'])
    		->setCellValue('D'.$row, $dataRow['direction'])
    		->setCellValue('E'.$row, $dataRow['state']);
    		$activeSheet->getRowDimension($row)->setRowHeight(-1);
    		 
    		if(time()-$lastTime) { //过了1秒
    			$lastTime = time();
    			R('File/setfilepercent', array($filename, '正在处理数据库查询结果...', $total, $r+1));
    		}
    	}
    
    	if(!empty($mapparams)) {
    		R('File/setfilepercent', array($filename, '正在准备地图...'));
    		if(!empty($startMarker)){
    			$mapparams['markers'] = $startMarker;
    			$mapparams['markerStyles'] = 'm,A';
    		}
    		if(!empty($endMarker) && !empty($mapparams['markers'])){
    			$mapparams['markers'] .= '|' .$endMarker;
    			$mapparams['markerStyles'] = '|m,B';
    		}
    		if(!empty($paths)) {
    			$mapparams['paths'] = implode(";", $paths);
    			$mapparams['pathStyles'] = '0xff0000,3,1';
    		}
    		$img = R('File/getbaidumapstaticimage', array($mapparams));
    		if($img) {
    			$objDrawing = new PHPExcel_Worksheet_Drawing();
    			$objDrawing->setName('map');
    			$objDrawing->setDescription('Map');
    			$objDrawing->setPath($img);
    			$objDrawing->setCoordinates('A6');
    			$objDrawing->getShadow()->setVisible(true);
    			$objDrawing->getShadow()->setDirection(45);
    
    
    			//调整图片大小，使之适应实际大小
    			$widthPT = $activeSheet->getColumnDimension('A')->getWidth()
    			+$activeSheet->getColumnDimension('B')->getWidth()
    			+$activeSheet->getColumnDimension('C')->getWidth()
    			+$activeSheet->getColumnDimension('D')->getWidth()
    			+$activeSheet->getColumnDimension('E')->getWidth();
    			//根据经验调整$widthPT
    			if($target=='PDF') $widthPT *= 4.7499; //经验：导出成PDF
    			else if($target=='Excel5') $widthPT *= 5.251282; //纯属经验：导出成Excel5,但是图片宽度被拉大了(1.1438451倍)
    			else $widthPT *= 6; //经验：导出成Excel2007
    
    			$widthPX = round($widthPT*4/3);
    			$scale = $objDrawing->getWidth() / $widthPX;
    			$heightPX = round($objDrawing->getHeight() / $scale);
    			$heightPT = $heightPX * 0.75;
    
    			$objDrawing->setWidth($widthPX);
    			$objDrawing->setHeight($heightPX);
    			$activeSheet->getRowDimension('6')->setRowHeight($heightPT);
    
    			$objDrawing->setWorksheet($activeSheet);
    			R('File/setfilepercent', array($filename, '地图就绪...'));
    		}
    		else {
    			R('File/setfilepercent', array($filename, '地图准备失败...'));
    		}
    	}
    
    	return $objPHPExcel;
    }
    
    private function _parseCondition() {
    	//检查数据，并组装查询条件
    	$device_id = $_REQUEST['device'] + 0;
    	$startTime = $_REQUEST['startTime'];
    	$endTime = $_REQUEST['endTime'];
    
    	$condition = array();
    
    	if(empty($device_id)) return_value_json(false, 'msg', '系统出错：设备编号为空');
    	$condition['_string'] = "`location`.`device_id`='{$device_id}' ";
    
    	if(empty($startTime)) return_value_json(false, 'msg', '系统出错：开始时间为空');
    	if(strlen($startTime)!=19) return_value_json(false, 'msg', '系统出错：开始时间格式不正确');
    	$_REQUEST['startTime'] = $startTime = str_ireplace("T", " ", $startTime);
    	if(strtotime($startTime)===false) return_value_json(false, 'msg', '系统出错：开始时间字符串无法解释成时间');
    	$condition['_string'] .= " AND `location`.`time`>='{$startTime}' ";
    
    	if(!empty($endTime)) {
    		if(strlen($endTime)!=19) return_value_json(false, 'msg', '系统出错：结束时间格式不正确，可以不填写结束时间，如果填写，请填写正确的时间');
    		$_REQUEST['endTime'] = $endTime = str_ireplace("T", " ", $endTime);
    		if(strtotime($endTime)===false) return_value_json(false, 'msg', '系统出错：结束时间字符串无法解释成时间，可以不填写结束时间，如果填写，请填写正确的时间');
    		$condition['_string'] .= " AND `location`.`time`<='{$endTime}'";
    	}
    
    	$filters = $_REQUEST['filter'];
    	if(!empty($filters)) $condition['_string'] .= ' AND (' . $this->_getFiltersCondition($filters) . ')';
    
    	return $condition;
    }
    
    private function _getFiltersCondition($filters, $ambiguous=array()) {//TODO
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

    /**
     * 区域车辆查询
     */
    public function inarea() {
    	//先获取到参数
    	$starttime = $this->_get('starttime');
    	$endtime = $this->_get('endtime');
    	
    	$points = json_decode($_GET['area']);

		if(empty($starttime)) 
			return_value_json(false, 'msg', '系统出错：开始时间为空');
		if(strlen($starttime)!=19 || strtotime($starttime)===false) 
			return_value_json(false, 'msg', '系统出错：开始时间格式不正确');
		if(!empty($endtime) &&(strlen($endtime)!=19 || strtotime($endtime)===false)) 
			return_value_json(false, 'msg', '系统出错：结束时间格式不正确');
			
		if(empty($points) || !is_array($points) || count($points)<2) {
			return_value_json(false, 'msg', '系统出错：多边形端点数量不够');
		}
		
		foreach($points as $index => $point) {
			$points[$index] = (array)$point;	//把对象转成数组
		}
		
		//首先查询数据库里指定时间内所有的定位信息
    	$Location = M('Location');
    	check_error($Location);
		
		$condition = array(
			'_string' => " `location`.`time`>='{$starttime}' AND `vehicle`.`number` IS NOT NULL "
		);
		if(!empty($endtime)) {
			$condition['_string'] .= " AND `location`.`time`<='{$endtime}' ";
		}
		
    	$Location
			->join('`device` on `device`.`id`=`location`.`device_id`')
			->join("`vehicle` on (`vehicle`.`id`=`device`.`target_id` AND `device`.`target_type`='车辆')")
			->join('`driver` on `driver`.`id`=`vehicle`.`driver_id`')
			->join('`department` on `department`.`id`=`vehicle`.`department_id`')
			->field(array('`location`.`id`', 
						'`vehicle`.`department_id`', '`department`.`name`'=>'department',
						'`vehicle`.`driver_id`', '`driver`.`name`'=>'driver',
						'`vehicle`.`id`' => 'vehicle_id', '`vehicle`.`number`',
						'`location`.`device_id`', '`device`.`type`', '`device`.`label`',   
						'`location`.`time`' , 'state', 'online', '`location`.`address`' ,
						'baidu_lat', 'baidu_lng', 'speed', 'direction',
						'mcc', 'mnc', 'lac', 'cellid', '`location`.`range`'
						))
			->where($condition)
			->order('`department`.`sequence`, `vehicle`.`id`, `time` ASC');	//先按车辆id，然后按时间顺序
    	
// 		$page = $_REQUEST['page'] + 0;
// 		$limit = $_REQUEST['limit'] + 0;
//		if($page && $limit) $Location->limit($limit)->page($page); //这里不用数据库的分页，而是用我们自己的分页（目前没法分页了）
		
		$locations = $Location->select();
		check_error($Location);
		
// 		Log::write('查询结果定位数：'. count($locations), Log::INFO);
		
		$total = 0;
		$results = array();
		$curVehicle = null;	//当前在区域内的车辆
		$alreadyIn = false; //目前是否已经在区域内
		$lastLocation = null;
		foreach ( $locations as $location ) {
			$in = Geometry::geoPointInPolygon(array('lat' => $location['baidu_lat'], 'lng'=> $location['baidu_lng']), $points);
			//TODO 考虑当前点与上一个定位点的轨迹线段是否切割多边形？
			
			if($in) {
				if($alreadyIn && $curVehicle != $location['vehicle_id']){ //已经在区域内，但是现在来了个不同的车（原来的车不知道跑哪里去了，这个车不知道是从哪里来的）
					//那么我们认为前车的离开点就是他轨迹的最后一个点，并且它现在离开区域了
					$lastVehicleLocationsCount = count($results[$total-1]['locations']);
					if($lastVehicleLocationsCount>0) {
						$results[$total-1]['time_out'] = $results[$total-1]['locations'][$lastVehicleLocationsCount-1]['time'];
						$results[$total-1]['duration'] = $this->_getFriendlyDurationText($results[$total-1]['time_in'], $results[$total-1]['time_out']);
						$alreadyIn = false;
						$curVehicle = null;
					}
				}
				
				if(!$alreadyIn && $curVehicle===null) { //首次进入
					$alreadyIn = true;
					$curVehicle = $location['vehicle_id'];
					$results[] = array(
						'id' 		=> $location['id'],
						'department_id'	=> $location['department_id'],
						'department'=> $location['department'],
						'driver_id'	=> $location['driver_id'],
						'driver'	=> $location['driver'],
						'vehicle_id'=> $location['vehicle_id'],
						'number'	=> $location['number'],
						'device_id'	=> $location['device_id'],
						'label'		=> $location['label'],
						'time_in'	=> $location['time'],	//进入时间
						'time_out'	=> date('Y-m-d H:i:s'),	//进入时间,
						'duration'	=> '直到现在',
						'locations'	=> array(),
						'first_isout' => false,
						'last_isout' => false,
					);
					$total++;
					
					if($lastLocation!==null && $lastLocation['vehicle_id'] ==$location['vehicle_id']) {
						$results[$total-1]['locations'][] = $lastLocation;
						$results[$total-1]['first_isout'] = true;
					}
				}
				
				$results[$total-1]['locations'][] = $location;
			}
			else { //出了区域
				if($alreadyIn) {
					if($curVehicle != $location['vehicle_id']){//原来的车不知道跑哪里去了
						$lastVehicleLocationsCount = count($results[$total-1]['locations']);
						if($lastVehicleLocationsCount>0) {
							$results[$total-1]['time_out'] = $results[$total-1]['locations'][$lastVehicleLocationsCount-1]['time'];
						}
						else { //这是不可能的。
							$results[$total-1]['time_out'] = $location['time'];
						}
					}
					else { //记录车辆离开的点
						$results[$total-1]['time_out'] = $location['time'];
						$results[$total-1]['locations'][] = $location;
						$results[$total-1]['last_isout'] = true;
					}
					
					$results[$total-1]['duration'] = $this->_getFriendlyDurationText($results[$total-1]['time_in'], $results[$total-1]['time_out']);
					$alreadyIn = false;
					$curVehicle = null;					
				}
			}
			
			$lastLocation = $location;
		}
		
		return_json(true, $total, 'results', $results);
    }
    
    private function _getFriendlyDurationText($time_in, $time_out) {
    	$t_in = strtotime($time_in);
    	$t_out= strtotime($time_out);
    	$re = '';
    	if($t_in!==FALSE && $t_out!==FALSE) {
    		$sec = $t_out - $t_in;
    		if( $sec > 3600*24 ) {
    			$days = floor($sec/3600/24);
    			$sec -= $days * 3600 * 24;
    		}
    		if( $sec > 3600 ) {
    			$hours = floor($sec/3600);
    			$sec -= $hours * 3600 ;
    		}
    		if( $sec > 60 ) {
    			$mins = floor($sec/60);
    			$sec -= $mins * 60;
    		}
    		
    		if(!empty($days)) $re .= $days . '天';
    		if(!empty($hours)) $re .= $hours . '小时';
    		if(empty($days) && !empty($mins)) $re .= $mins . '分';
    		if(empty($days) && empty($hours) && !empty($sec)) $re .= $sec . '秒';
    	}
    	return $re;
    }
    
    /**
     * 返回所有车辆的最后定位
     */
    private function _allVehicleLastLocations() {
    	$Device = M('Device');
    	check_error($Device);
    	
		$condition = array(
			'_string' => "`device`.`target_type`='车辆' AND `target_id`<>0"
		);
		
		$trackings = $Device
			->join("`vehicle` on (`vehicle`.`id`=`device`.`target_id` AND `device`.`target_type`='车辆')")
			->join('`department` on `department`.`id`=`vehicle`.`department_id`')
			->join('`driver` on `driver`.`id`=`vehicle`.`driver_id`')
			->join('`location` on `location`.`id`=`device`.`last_location`')
			->field(array('`device`.`id`', '`device`.`type`', '`device`.`label`', 'interval', 'delay', 
						'`device`.`mobile_num`',
						'`device`.`target_type`', '`device`.`target_id`', '`device`.`target_name`', 
						'`vehicle`.`department_id`', '`department`.`name`'=>'department', 
						'`driver`.`id`'=>'driver_id', '`driver`.`name`'=>'driver', 
						'`vehicle`.`id`'=>'vehicle_id', '`vehicle`.`number`',
						'last_location', '`location`.`time`' , 'state', 'online', '`location`.`range`',
						'baidu_lat', 'baidu_lng', 'speed', 'direction', '`location`.`address`'
						))
			->where( $condition )
			->order('`department`.`sequence` ASC, `vehicle`.`sequence` ASC, `device`.`id`' )
			->select();
		check_error($Device);
		
		$this->_fixNullOnline($trackings);
		
		return_json(true, null, 'trackings', $trackings);
    }
    
    private function _fixNullOnline(&$trackings) {
    	foreach($trackings as $index => $tracking) {
    		if(empty($tracking['last_location']) ) {
    			$trackings[$index]['online'] = '离线';
    			$trackings[$index]['state'] = '没有定位';
    		}
    	}
    }
    
    private function _getTrackings() {
		$deviceIds = $_GET['deviceIds'];
    	$startTime = $_GET['startTime'];
    	$endTime = $_GET['endTime'];
    	
    	$condition = array();
    	
    	if(empty($deviceIds)) return_value_json(false, 'msg', '系统出错：设备编号为空');
    	$condition['_string'] = "`device`.`id` IN ({$deviceIds}) ";
		
		if(empty($startTime)) return_value_json(false, 'msg', '系统出错：开始时间为空');
		if(strlen($startTime)!=19) return_value_json(false, 'msg', '系统出错：开始时间格式不正确');
		$startTime = str_ireplace("T", " ", $startTime);
		if(strtotime($startTime)===false) return_value_json(false, 'msg', '系统出错：开始时间字符串无法解释成时间');
		$condition['_string'] .= " AND `location`.`time`>'{$startTime}' ";
		
		if(!empty($endTime)) {
			if(strlen($endTime)!=19) return_value_json(false, 'msg', '系统出错：结束时间格式不正确，可以不填写结束时间，如果填写，请填写正确的时间');
			$endTime = str_ireplace("T", " ", $endTime);
			if(strtotime($endTime)===false) return_value_json(false, 'msg', '系统出错：结束时间字符串无法解释成时间，可以不填写结束时间，如果填写，请填写正确的时间');
			$condition['_string'] .= " AND `location`.`time`<'{$endTime}'";
		}
    	
    	$Device = M('Device');
    	check_error($Device);
		
		$devicetrackings = $Device
			->join("`vehicle` on (`vehicle`.`id`=`device`.`target_id` AND `device`.`target_type`='车辆')")
			->join('`department` on `department`.`id`=`vehicle`.`department_id`')
			->join('`driver` on `driver`.`id`=`vehicle`.`driver_id`')
			->join('`location` on `location`.`device_id`=`device`.`id`')
			->field(array('`device`.`id`', '`device`.`type`', '`device`.`label`', 'interval', 'delay', 
						'`device`.`mobile_num`',
						'`device`.`target_type`', '`device`.`target_id`', '`device`.`target_name`', 
						'`vehicle`.`department_id`', '`department`.`name`'=>'department', 
						'`driver`.`id`'=>'driver_id', '`driver`.`name`'=>'driver', 
						'`vehicle`.`id`'=>'vehicle_id', '`vehicle`.`number`', 'last_location',
						'`location`.`id`'=>'location_id', '`location`.`time`' , 'state', 'online',
						'baidu_lat', 'baidu_lng', 'speed', 'direction', '`location`.`address`' ,
						'mcc', 'mnc', 'lac', 'cellid', '`location`.`range`'
						))
			->where( $condition )
			->order('`device`.`id` ASC, `location`.`time` ASC' )
			->select();
		check_error($Device);
//		Log::write("\n".M()->getLastSql(), Log::SQL);
		
		$this->_fixNullOnline($devicetrackings);
		
		$trackings = $this->_parseTrackingsData($devicetrackings);
		
		return_json(true, null, 'trackings', $trackings);
    }
    
    private function _parseTrackingsData (&$devicetrackings) {
    	$re = array();
    	$curDevice = null;
    	$curTracking = array();
    	$counter = -1;
    	
    	foreach($devicetrackings as $devicetracking) {
    		if($devicetracking['id']!=$curDevice) {
    			$counter ++;
    			$curDevice = $devicetracking['id'];
    			$curTracking = array_merge(array(),$devicetracking);
    			$re[$counter] = $curTracking;
    			$re[$counter]['locations'] = array();
    		}
    		$re[$counter]['locations'][] = array(
				'id' 		=> $devicetracking['location_id'],
				'department_id'	=> $devicetracking['department_id'],
				'department'=> $devicetracking['department'],
				'driver_id'	=> $devicetracking['driver_id'],
				'driver'	=> $devicetracking['driver'],
				'vehicle_id'=> $devicetracking['vehicle_id'],
				'number'	=> $devicetracking['number'],
				'device_id'	=> $devicetracking['id'],
				'label'		=> $devicetracking['label'],
				'type' 		=> $devicetracking['type'],
				'time'		=> $devicetracking['time'],
				'baidu_lat'	=> $devicetracking['baidu_lat'],
				'baidu_lng'	=> $devicetracking['baidu_lng'],
				'state'		=> $devicetracking['state'],
				'online'	=> $devicetracking['online'],
				'speed'		=> $devicetracking['speed'],
				'direction'	=> $devicetracking['direction'],
				'mcc'		=> $devicetracking['mcc'],
				'mnc'		=> $devicetracking['mnc'],
				'lac'		=> $devicetracking['lac'],
				'cellid'	=> $devicetracking['cellid'],
				'target_type'=> $devicetracking['target_type'],
				'target_id'	=> $devicetracking['target_id'],
				'target_name'=> $devicetracking['target_name'],
				'address'	=> $devicetracking['address'],
				'range'		=> $devicetracking['range']
    		);
    	}
    	
    	return $re;
    }
    
    //刷新所有设备的离线状态
    public function refreshOnlineState() {
    	$Model = new Model();
    	check_error($Model);
    	
    	$now = date('Y-m-d H:i:s');
    	
    	$count = $Model->execute("INSERT INTO `location`(`device_id`, " .
    			"`time`, `gps_lng`, `gps_lat`, `offset_lng`, `offset_lat`, `baidu_lng`, `baidu_lat`, " .
    			"`mcc`, `mnc`, `lac`, `cellid`, `state`, `online`, `speed`, `direction`, `address`, `bar_id`) " .
    			"SELECT `location`.`device_id`, '{$now}', `gps_lng`, `gps_lat`, `offset_lng`, `offset_lat`, `baidu_lng`, `baidu_lat`, " .
    			"`mcc`, `mnc`, `lac`, `cellid`, `state`, '离线', `speed`, `direction`, `address`, `bar_id` " .
    			"FROM `location` INNER JOIN `device` ON `device`.`last_location` = `location`.`id` " .
    			"WHERE `location`.`time` < DATE_ADD( '{$now}' , INTERVAL( 0 - `device`.`interval` - `device`.`delay` ) MINUTE )" .
    			"AND `location`.`online`='在线'; ", false);
    	if($count===false) {
    		//TODO 写日志：刷新设备的离线状态失败
//    		Log::write("\ncount===false", Log::INFO);
    	}
    	else if($count>0) {
    		$count2 = $Model->execute("UPDATE `device` " .
    				"INNER JOIN  " .
    				"(SELECT `location`.`id`, `location`.`device_id` from `location` ORDER BY `id` DESC LIMIT $count ) " .
    				"AS `loc` " .
    				"SET `device`.`last_location`=`loc`.`id` " .
    				"WHERE `loc`.`device_id` = `device`.`id`", false);
    		if($count2===false){
    			//TODO 写日志：刷新设备的离线状态更新设备的最后定位字段数据失败
//    			Log::write("\ncount2===false", Log::INFO);
    		}
    		if($count2!==$count) {
//    			Log::write("\ncount2!==count", Log::INFO);
    			//TODO 写日志：刷新设备的离线状态，也许部分设备的最后定位字段没有更新成功
    		}
    	}
    	else {
//    		Log::write("\n" . $Model->getLastSql(), Log::SQL);
//    		Log::write("\n没有设备离线状态发生变化", Log::INFO);
    	}
    }
    
    /**
     * 查找单个设备最后的定位
     * @param int $device_id 设备id
     */
    public function single($device_id) {
    	if(empty($device_id) || !is_int($device_id)) {
    		return_value_json(false, "msg", "非法的调用：设备id为空");
    	}
    	
    	//先找到相关的设备
    	$Device = M('Device');
    	check_error($Device);
    	
    	$device = $Device->find($device_id);
    	if(empty($device)) {
    		return_value_json(false, "msg", "无法查找到相关的设备");
    	}
    	
    	$condition = array(
    		'_string' => "`device`.`id`='{$device_id}'"
    	);

    	$fields = array('`device`.`id`', '`device`.`type`', '`device`.`label`', 'interval', 'delay',
    			'`device`.`mobile_num`',
    			'`device`.`target_type`', '`device`.`target_id`', '`device`.`target_name`',
    			'`department`.`id`' => 'department_id', '`department`.`name`'=>'department',
    			'last_location', '`location`.`time`' , 'state', 'online', '`location`.`range`',
    			'baidu_lat', 'baidu_lng', 'speed', 'direction', '`location`.`address`'
    	);
    	
    	if($device['target_type']=='车辆'){
    		$Device->join("`vehicle` on `vehicle`.`id`=`device`.`target_id`")
    		->join('`driver` on `driver`.`id`=`vehicle`.`driver_id`');
    		array_merge($fields, 
    			array('`driver`.`id`'=>'driver_id', '`driver`.`name`'=>'driver', 
				'`vehicle`.`id`'=>'vehicle_id', '`vehicle`.`number`'));
    	}
    	else if($device['target_type']=='人员'){
    		$Device->join("`employee` on `employee`.`id`=`device`.`target_id`");
    		array_merge($fields, 
    			array('`employee`.`id`'=>'employee_id', '`employee`.`name`', '`employee`.`post`'));
    	}
    	else if($device['target_type']=='班列'){
    		$Device->join("`train` on `train`.`id`=`device`.`target_id`");
    		array_merge($fields,
    			array('`train`.`id`'=>'train_id', '`train`.`number`', '`train`.`due_date`'));
    	}
    	else if($device['target_type']=='集装箱'){
    		$Device->join("`container` on `container`.`id`=`device`.`target_id`");
    		array_merge($fields,
    			array('`container`.`id`'=>'container_id', '`container`.`number`'));
    	}
    	else {
    		return_value_json(false, "msg", "无法查找到相关的设备：设备类型不正确");
    	}
    	
    	$tracking = $Device
	    	->join('`department` on `department`.`id`=`device`.`department_id`')
	    	->join('`location` on `location`.`id`=`device`.`last_location`')
	    	->field($fields)
	    	->where( $condition )
	    	->find();
    	check_error($Device);
    	
    	return_value_json(true, 'tracking', $tracking);
    }
}
?>