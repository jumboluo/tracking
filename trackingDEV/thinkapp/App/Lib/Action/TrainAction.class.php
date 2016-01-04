<?php

import('App.Util.LBS.Geometry');

class TrainAction extends Action{
	
	/**
	 * all操作分页返回所有的班列资料
	 */
	public function all() {
		$Train = M('Train');
		check_error($Train);
		
		$total = $Train->count();
		check_error($Train);
		
		$Train->join('`department` on `department`.`id`=`train`.`department_id`')
			->join("`device` on (`device`.`target_id`=`train`.`id` AND `device`.`target_type`='班列')")
			->join('`location` on `location`.`id`=`device`.`last_location`')
			->field(array('`train`.`id`', '`train`.`number`', '`train`.`due_date`', '`train`.`due_time`', '`train`.`memo`',
						"CONCAT(LPAD(`department`.`sequence`, 4, '0'), `department`.`name`)" => 'seq_department', 
						'`train`.`department_id`', '`department`.`name`'=>'department', 
						'`location`.`state`', '`location`.`online`' 
						))
			->order('`department`.`sequence` ASC, `train`.`id`' );
			
// 		$page = $_REQUEST['page'] + 0;
// 		$limit = $_REQUEST['limit'] + 0;
// 		if($page && $limit) $Train->limit($limit)->page($page);
		
		$trains = $Train->select();
		check_error($Train);
//		Log::write(M()->getLastSql(), Log::SQL);
		
		$ids = array();
		foreach($trains as $i => $train) {
			$trains[$i]['seq_department'] = empty($train['seq_department']) ? '0000未分组的' : $train['seq_department'];
		}

		return_json(true,$total,'trains', $trains);
	}
	
	/**
	 * add操作根据POST数据，添加一个或多个班列资料资料到数据库
	 */
	public function add() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$trains = json_decode(file_get_contents("php://input"));
		if(!is_array($trains)) {
			$trains = array($trains);
		}
		
		$Train = M('Train');
		check_error($Train);
		
		foreach ( $trains as $train ) {
			$train->due_time = substr($train->due_time, 11);
			if(empty($train->due_time)) unset($train->due_time);
			$Train->create($train);
			check_error($Train);
			
			$Train->id = null;
			
			if(false === $Train->add()) {
				//保存日志
				R('Log/adduserlog', array(
					'添加班列资料',
					'添加班列资料失败',
					'失败：系统错误',
					'班列：' . $train->number. '，失败原因：' . get_error($Train)
				));
				return_value_json(false, 'msg', get_error($Train));
			}
			//保存日志
			R('Log/adduserlog', array(
				'添加班列资料',
				'添加班列资料成功',
				'成功',
				'班列：' . $train->number
			));
		}
		
		return_value_json(true);
	}
	
	
	/**
	 * edit操作根据post数据更新数据库。
	 */
	public function edit() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$trains = json_decode(file_get_contents("php://input"));
		if(!is_array($trains)) {
			$trains = array($trains);
		}
		
		$Train = M('Train');
		check_error($Train);
		
		foreach ( $trains as $train ) {
			$train->due_time = substr($train->due_time, 11);
			if(empty($train->due_time)) unset($train->due_time);
			$Train->create($train);
			check_error($Train);
			
			if(false === $Train->save()) {
				//保存日志
				R('Log/adduserlog', array(
					'添加班列资料',
					'添加班列资料失败',
					'失败：系统错误',
					'班列：' . $train->number. '，出错原因：' . get_error($Train)
				));
				return_value_json(false, 'msg', '更新班列资料['.$train->number.']时出错：' . get_error($Train));
			}
			//保存日志
			R('Log/adduserlog', array(
				'添加班列资料',
				'添加班列资料成功',
				'成功',
				'班列：' . $train->number
			));
		}
		
		return_value_json(true);
	}
	
	
	public function delete() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$trains = json_decode(file_get_contents("php://input"));
		if(!is_array($trains)) {
			$trains = array($trains);
		}
		
		$Train = M('Train');
		check_error($Train);
		
		$ids = array();
		foreach ( $trains as $train ) {
			if(false === $Train->where("`id`='" . $train->id . "'")->delete()) {
				//保存日志
				R('Log/adduserlog', array(
					'删除班列资料',
					'删除班列资料失败',
					'失败：系统错误',
					'班列：' . $train->number. '，出错原因：' . get_error($Train)
				));
				return_value_json(false, 'msg', '删除班列资料['.$train->number.']时出错：' . get_error($Train));
			}
			//保存日志
			R('Log/adduserlog', array(
				'删除班列资料',
				'删除班列资料成功',
				'成功',
				'班列：' . $train->number
			));
		}
		
		return_value_json(true);
	}
	
	////////////////////////////班列的定位、轨迹相关///////////////////////////////////////
    public function statistics() {
		$Train = M('Train');
		check_error($Train);
		
		$targetsQuery = D('ManageTarget')->getTargetsQuery();
		
    	$total = $Train->join("`device` on (`train`.`id`=`device`.`target_id` AND `device`.`target_type`='班列')")
    			->where($targetsQuery)
    			->count();
    	$online = $Train->join("`device` on (`train`.`id`=`device`.`target_id` AND `device`.`target_type`='班列')")
				->join('`location` on `location`.`id`=`device`.`last_location`')
				->where("`location`.`online`='在线' AND " . $targetsQuery)
				->count();
    	$offline = $Train->join("`device` on (`train`.`id`=`device`.`target_id` AND `device`.`target_type`='班列')")
				->join('`location` on `location`.`id`=`device`.`last_location`')
				->where("`location`.`online`='离线' AND " . $targetsQuery)
				->count();
		
		$Alarm = M('Alarm');
		$alarm = $Alarm->join("LEFT JOIN `device` on `alarm`.`device_id`=`device`.`id`")
				->where("`target_type`='班列' AND `checked`=0 AND " . $targetsQuery)
				->count();
		
    	return_json(true, 1, 'statistics', array(array('total'=>$total,'online'=>$online, 'offline'=>$offline, 'alarm'=>$alarm, 'time'=>date('Y-m-d H:i:s'))));
    }
    
    
    /**
	 * query操作返回监控中心查询的班列列表
	 */
    public function query() {
		$Train = M('Train');
		check_error($Train);
		
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
			$condition['_string'] .= " `train`.`number` LIKE '%{$_REQUEST['fuzzy']}%' "; //注：MYSQL的like其实是不区分大小写的，也就是说like '%k%'将查询到包含K和k的
			$condition['_string'] .= " OR `train`.`memo` LIKE '%{$_REQUEST['fuzzy']}%' ";
			$condition['_string'] .= " ) ";
		}
		
		$trackings = $Train->join('`department` on `department`.`id`=`train`.`department_id`')
			->join("`device` on (`train`.`id`=`device`.`target_id` AND `device`.`target_type`='班列')")
			->join('`location` on `location`.`id`=`device`.`last_location`')
			->field(array('`device`.`id`', '`device`.`type`', '`device`.`label`', 'interval', 'delay', 
						'`train`.`id`'=>'train_id', '`train`.`number`', '`train`.`due_date`', '`train`.`due_time`', 
						'`train`.`department_id`', '`department`.`name`'=>'department', 
						'`device`.`target_type`', '`device`.`target_id`', '`device`.`target_name`', 
						'last_location', '`location`.`time`' , 'state', 'online', '`device`.`mobile_num`',
						'baidu_lat', 'baidu_lng', 'speed', 'direction', '`location`.`address`' , 
						'CONCAT(`state`, `online`)' => 'state_online', '`location`.`range`' 
						))
			->distinct(true)
			->where($condition)
			->order('`department`.`sequence` ASC, `department`.`id` ASC, `train`.`id` ASC')
			->select();
		check_error($Train);
		
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
    			$curDepartment = $tracking['department_id'];
    			++$curDepartmentIndex;
    			$group = array(
    					'tree_text' => $tracking['department'],
    					'expanded'=>($curDepartmentIndex==0),
    					'checked'=>false,
    					'state_online'=> '分组',
    					'children' => array($tracking)
    			);
    			$re[] = $group;
    		}
    		else {
    			$re[$curDepartmentIndex]['children'][] = $tracking;
    		}
    	}
    	return $re;
    }
    
    /**
     * 刷新定位
     */
    public function refresh() {
    	R('Tracking/refreshOnlineState');
    	
		$Train = M('Train');
		check_error($Train);
		
		$condition = array(
			'_string' => "`device`.`target_type`='班列' AND `device`.`target_id`<>0 AND " . D('ManageTarget')->getTargetsQuery()
		);
		
		$Train->join('`department` on `department`.`id`=`train`.`department_id`')
			->join("`device` on (`train`.`id`=`device`.`target_id` AND `device`.`target_type`='班列')")
			->join('`location` on `location`.`id`=`device`.`last_location`')
			->field(array('`device`.`id`', '`device`.`type`', '`device`.`label`', 'interval', 'delay',
						'`device`.`mobile_num`',
						'`train`.`id`'=>'train_id', '`train`.`number`', '`train`.`due_date`', '`train`.`due_time`', 
						'`train`.`department_id`', '`department`.`name`'=>'department', 
						'`device`.`target_type`', '`device`.`target_id`', '`device`.`target_name`', 
						'last_location', '`location`.`time`' , 'state', 'online',
						'baidu_lat', 'baidu_lng', 'speed', 'direction',  '`device`.`mobile_num`', 
						'CONCAT(`state`, `online`)' => 'state_online', '`location`.`range`' , '`location`.`address`'
						))
			->distinct(true)
			->where($condition)
			->order('`department`.`sequence` ASC, `train`.`id` ASC');
			
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) $Train->limit($limit)->page($page);
		
		$trackings = $Train->select();
		check_error($Train);
		
		$this->_fixNullOnline($trackings);

		return_json(true,null,'trackings', $trackings);
    }
    
    private function _fixNullOnline(&$trackings) {
    	foreach($trackings as $index => $tracking) {
    		if(empty($tracking['last_location']) ) {
    			$trackings[$index]['online'] = '离线';
    			$trackings[$index]['state'] = '没有定位';
    		}
    	}
    }
    
    /**
     * 查轨迹
     */
    public function tracking() {
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
			->join("`train` on (`train`.`id`=`device`.`target_id` AND `device`.`target_type`='班列')")
			->join('`department` on `department`.`id`=`train`.`department_id`')
			->join('`location` on `location`.`device_id`=`device`.`id`')
			->field(array('`device`.`id`', '`device`.`type`', '`device`.`label`', 'interval', 'delay', 
						'`train`.`id`'=>'train_id', '`train`.`number`', '`train`.`due_date`', '`train`.`due_time`', 
						'`train`.`department_id`', '`department`.`name`'=>'department', 
						'`device`.`target_type`', '`device`.`target_id`', '`device`.`target_name`', 
						'last_location', '`location`.`time`' , 'state', 'online', '`location`.`address`' , 
						'baidu_lat', 'baidu_lng', 'speed', 'direction', 'mcc', 'mnc', 'lac', 'cellid',
						'CONCAT(`state`, `online`)' => 'state_online', '`location`.`range`' 
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
				'train_id'=> $devicetracking['train_id'],
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
    
    /**
     * 区域班列查询
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
			'_string' => " `location`.`time`>='{$starttime}' AND `train`.`number` IS NOT NULL "
		);
		if(!empty($endtime)) {
			$condition['_string'] .= " AND `location`.`time`<='{$endtime}' ";
		}
		
    	$Location
			->join('`device` on `device`.`id`=`location`.`device_id`')
			->join("`train` on (`train`.`id`=`device`.`target_id` AND `device`.`target_type`='班列')")
			->join('`department` on `department`.`id`=`train`.`department_id`')
			->field(array('`location`.`id`', 
						'`department`.`id`'=>'department_id', '`department`.`name`'=>'department',
						'`train`.`id`' => 'train_id', '`train`.`number`', '`train`.`due_date`', '`train`.`due_time`', 
						'`location`.`device_id`', '`device`.`type`', '`device`.`label`',   
						'`location`.`time`' , 'state', 'online', '`location`.`address`' , 
						'baidu_lat', 'baidu_lng', 'speed', 'direction',
						'mcc', 'mnc', 'lac', 'cellid', '`location`.`range`' 
						))
			->where($condition)
			->order('`department`.`sequence`, `train`.`id`, `time` ASC');	//先按班列id，然后按时间顺序
    	
// 		$page = $_REQUEST['page'] + 0;
// 		$limit = $_REQUEST['limit'] + 0;
//		if($page && $limit) $Location->limit($limit)->page($page); //这里不用数据库的分页，而是用我们自己的分页（目前没法分页了）
		
		$locations = $Location->select();
		check_error($Location);
		
		$total = 0;
		$results = array();
		$curTrain = null;	//当前在区域内的班列
		$alreadyIn = false; //目前是否已经在区域内
		$lastLocation = null;
		foreach ( $locations as $location ) {
			$in = Geometry::geoPointInPolygon(array('lat' => $location['baidu_lat'], 'lng'=> $location['baidu_lng']), $points);
			//TODO 考虑当前点与上一个定位点的轨迹线段是否切割多边形？
			
			if($in) {
				if($alreadyIn && $curTrain != $location['train_id']){ //已经在区域内，但是现在来了个不同的车（原来的车不知道跑哪里去了，这个车不知道是从哪里来的）
					//那么我们认为前车的离开点就是他轨迹的最后一个点，并且它现在离开区域了
					$lastTrainLocationsCount = count($results[$total-1]['locations']);
					if($lastTrainLocationsCount>0) {
						$results[$total-1]['time_out'] = $results[$total-1]['locations'][$lastTrainLocationsCount-1]['time'];
						$results[$total-1]['duration'] = $this->_getFriendlyDurationText($results[$total-1]['time_in'], $results[$total-1]['time_out']);
						$alreadyIn = false;
						$curTrain = null;
					}
				}
				
				if(!$alreadyIn && $curTrain===null) { //首次进入
					$alreadyIn = true;
					$curTrain = $location['train_id'];
					$results[] = array(
						'id' 		=> $location['id'],
						'department_id'	=> $location['department_id'],
						'department'=> $location['department'],
						'train_id'=> $location['train_id'],
						'number'	=> $location['number'],
						'device_id'	=> $location['device_id'],
						'label'		=> $location['label'],
						'time_in'	=> $location['time'],	//进入时间
						'time_out'	=> '',	//离开时间,
						'duration'	=> '',
						'locations'	=> array(),
						'first_isout' => false,
						'last_isout' => false,
					);
					$total++;
					
					if($lastLocation!==null && $lastLocation['train_id'] ==$location['train_id']) {
						$results[$total-1]['locations'][] = $lastLocation;
						$results[$total-1]['first_isout'] = true;
					}
				}
				
				$results[$total-1]['locations'][] = $location;
			}
			else { //出了区域
				if($alreadyIn) {
					if($curTrain != $location['train_id']){//原来的车不知道跑哪里去了
						$lastTrainLocationsCount = count($results[$total-1]['locations']);
						if($lastTrainLocationsCount>0) {
							$results[$total-1]['time_out'] = $results[$total-1]['locations'][$lastTrainLocationsCount-1]['time'];
						}
						else { //这是不可能的。
							$results[$total-1]['time_out'] = $location['time'];
						}
					}
					else { //记录班列离开的点
						$results[$total-1]['time_out'] = $location['time'];
						$results[$total-1]['locations'][] = $location;
						$results[$total-1]['last_isout'] = true;
					}
					
					$results[$total-1]['duration'] = $this->_getFriendlyDurationText($results[$total-1]['time_in'], $results[$total-1]['time_out']);
					$alreadyIn = false;
					$curTrain = null;					
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
}
?>