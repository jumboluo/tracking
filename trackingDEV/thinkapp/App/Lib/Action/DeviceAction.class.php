<?php

class DeviceAction extends Action{
	
	/**
	 * all操作分页返回所有的监控设备
	 */
	public function all() {
		$Device = M('Device');
		check_error($Device);
		
		$total = $Device->count();
		check_error($Device);
		
		$Device->join('`department` on `department`.`id`=`device`.`department_id`')
//			->join('`driver` on `driver`.`id`=`device`.`driver_id`')
//			->join('`vehicle` on `vehicle`.`id`=`device`.`vehicle_id`')
			->join('`location` on `location`.`id`=`device`.`last_location`')
			->field(array('`device`.`id`', '`device`.`type`', '`device`.`label`', 'interval', 'delay',
						"CONCAT(LPAD(`department`.`sequence`, 4, '0'), `department`.`name`)" => 'seq_department', 
						'`device`.`department_id`', '`department`.`name`'=>'department', 
//						'`driver`.`id`'=>'driver_id', '`driver`.`name`'=>'driver', 
//						'`vehicle`.`id`'=>'vehicle_id', '`vehicle`.`number`',
						'weekday0', 'weekday1', 'weekday2', 'weekday3', 'weekday4', 'weekday5', 'weekday6', 
						'starttime', 'endtime', 'mobile_num', 'bar_id',
						'`device`.`target_id`', '`device`.`target_type`', '`device`.`target_name`',
						'`device`.`brand`', '`device`.`model`', '`device`.`system`', 
						'`device`.`buy_date`', '`device`.`memo`',
						'`location`.`state`', '`location`.`online`' 
						))
			->order('`department`.`sequence` ASC, `device`.`id`' );
			
// 		$page = $_REQUEST['page'] + 0;
// 		$limit = $_REQUEST['limit'] + 0;
// 		if($page && $limit) $Device->limit($limit)->page($page);
		
		$devices = $Device->select();
		check_error($Device);
//		Log::write(M()->getLastSql(), Log::SQL);
		
		foreach($devices as $i => $device) {
			$devices[$i]['seq_department'] = empty($device['seq_department']) ? '0000未分组的' : $device['seq_department'];
		}

		return_json(true,$total,'devices', $devices);
	}
	
	/**
	 * add操作根据POST数据，添加一个或多个监控设备资料到数据库
	 */
	public function add() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$devices = json_decode(file_get_contents("php://input"));
		if(!is_array($devices)) {
			$devices = array($devices);
		}
		
		$Device = D('Device');
		check_error($Device);
		
		foreach ( $devices as $device ) {
			$Device->create($device);
			check_error($Device);
			
			$Device->id = null;
			$Device->label = $Device->generateLabel($Device->label, $Device->type);
			
			if(false === $Device->add()) {
				//保存日志
				R('Log/adduserlog', array(
					'添加监控设备资料',
					'添加监控设备资料：' . get_error($Device) ,
					'失败：系统错误',
					'监控设备：' . $device->label. '，失败原因：' . get_error($Device)
				));
				return_value_json(false, 'msg', get_error($Device));
			}
			
			//保存日志
			R('Log/adduserlog', array(
				'添加监控设备资料',
				'添加监控设备资料成功' ,
				'成功',
				'监控设备：' . $device->label
			));
		}
		
		return_value_json(true);
	}
	
	
	/**
	 * edit操作根据post数据更新数据库。
	 */
	public function edit() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$devices = json_decode(file_get_contents("php://input"));
		if(!is_array($devices)) {
			$devices = array($devices);
		}
		
		$Device = D('Device');
		check_error($Device);
		
		foreach ( $devices as $device ) {
			$Device->create($device);
			check_error($Device);
			
			$Device->label = $Device->generateLabel($Device->label, $Device->type);
			
			if(false === $Device->save()) {
				//保存日志
				R('Log/adduserlog', array(
					'修改监控设备资料',
					'修改监控设备资料：' . get_error($Device) ,
					'失败：系统错误',
					'监控设备：' . $device->label. '，失败原因：' . get_error($Device)
				));
				return_value_json(false, 'msg', '更新监控设备['.$device->label.']时出错：' + get_error($Device));
			}
			//保存日志
			R('Log/adduserlog', array(
				'修改监控设备资料',
				'修改监控设备资料成功' ,
				'成功',
				'监控设备：' . $device->label
			));
		}
		
		return_value_json(true);
	}
	
	public function delete() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$devices = json_decode(file_get_contents("php://input"));
		if(!is_array($devices)) {
			$devices = array($devices);
		}
		
		$Device = D('Device');
		check_error($Device);
		
		foreach ( $devices as $device ) {
			if(false === $Device->where("`id`='" . $device->id . "'")->delete()) {
				//保存日志
				R('Log/adduserlog', array(
					'删除监控设备资料',
					'删除监控设备资料：' . get_error($Device) ,
					'失败：系统错误',
					'监控设备：' . $device->label. '，失败原因：' . get_error($Device)
				));
				return_value_json(false, 'msg', '删除监控设备['.$device->label.']时出错：' + get_error($Device));
			}
			//保存日志
			R('Log/adduserlog', array(
				'删除监控设备资料',
				'删除监控设备资料成功' ,
				'成功',
				'监控设备：' . $device->label
			));
		}
		
		return_value_json(true);
	}
}
?>