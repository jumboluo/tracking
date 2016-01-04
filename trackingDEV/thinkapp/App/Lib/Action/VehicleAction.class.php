<?php

class VehicleAction extends Action{

    public function all() {
		$Vehicle = M('Vehicle');
		check_error($Vehicle);
		
		
		$total = $Vehicle->join('`department` on `department`.`id`=`vehicle`.`department_id`')
				->count();
		check_error($Vehicle);
		
		$Vehicle->join('`department` on `department`.`id`=`vehicle`.`department_id`')
			->join('`driver` on `driver`.`id`=`vehicle`.`driver_id`')
			->field(array('`vehicle`.`id`', '`vehicle`.`number`', 
						"CONCAT(LPAD(`department`.`sequence`, 4, '0'), `department`.`name`)" => 'seq_department', 
						'`vehicle`.`department_id`', '`department`.`name`'=>'department', 
						'`driver`.`id`'=>'driver_id', '`driver`.`name`'=>'driver',
						'`vehicle`.`sequence`', 'brand', 'model', 'engine', 'displacement',
						'vin', 'ein', 
						'color', 'load', 'buy_date', '`vehicle`.`memo`'))
			->order('`department`.`sequence` ASC, `vehicle`.`sequence` ASC');
			
// 		$page = $_REQUEST['page'] + 0;
// 		$limit = $_REQUEST['limit'] + 0;
// 		if($page && $limit) $Vehicle->limit($limit)->page($page);
		
		$vehicles = $Vehicle->select();
		check_error($Vehicle);
//		Log::write(M()->getLastSql(), Log::SQL);
		
		foreach($vehicles as $i => $vehicle) {
			$vehicles[$i]['seq_department'] = empty($vehicle['seq_department']) ? '0000未分组的' : $vehicle['seq_department'];
		}

		return_json(true,$total,'vehicles', $vehicles);
    }
    
    
	/**
	 * indepartment操作分页返回指定分组（$_POST['department_id']下的车辆
	 */
	public function indepartment() {
		$dpm_id = $this->_request('department_id') + 0;
		
		$Vehicle = M('Vehicle');
		check_error($Vehicle);
		
		$total = $Vehicle->join('`department` on `department`.`id`=`vehicle`.`department_id`')
				->where("`vehicle`.`department_id`='{$dpm_id}'")->count();
		check_error($Vehicle);
		
		$Vehicle->join('`department` on `department`.`id`=`vehicle`.`department_id`')
			->join('`driver` on `driver`.`id`=`vehicle`.`driver_id`')
			->field(array('`vehicle`.`id`', '`vehicle`.`number`', 
						"CONCAT(LPAD(`department`.`sequence`, 4, '0'), `department`.`name`)" => 'seq_department', 
						'`vehicle`.`department_id`', '`department`.`name`'=>'department', 
						'`driver`.`id`'=>'driver_id', '`driver`.`name`'=>'driver',
						'`vehicle`.`sequence`', 'brand', 'model', 'engine', 'displacement', 
						'vin', 'ein', 
						'color', 'load', 'buy_date', '`vehicle`.`memo`'))
			->where("`vehicle`.`department_id`='{$dpm_id}'" )
			->order('`sequence` ASC');
			
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) $Vehicle->limit($limit)->page($page);
		
		$vehicles = $Vehicle->select();
		check_error($Vehicle);
//		Log::write(M()->getLastSql(), Log::SQL);
		
		foreach($vehicles as $i => $vehicle) {
			$vehicles[$i]['seq_department'] = empty($vehicle['seq_department']) ? '0000未分组的' : $vehicle['seq_department'];
		}
		
		return_json(true,$total,'vehicles', $vehicles);
	}
    
    /**
     * add操作根据POST数据，添加一个或多个车辆资料到数据库
     */
    public function add() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
    	$vehicles = json_decode(file_get_contents("php://input"));
    	if(!is_array($vehicles)) {
    		$vehicles = array($vehicles);
    	}
    	
		$Vehicle = D('Vehicle');
		
    	foreach ( $vehicles as $vehicle ) {
			$seq = $vehicle->sequence;
			$dpm_id = $vehicle->department_id;
			
			if(!empty($seq)) {
				$condition = array(
					'department_id' => array('eq', $dpm_id),
					'sequence' => array('egt', $seq)
				);
				$Vehicle->where($condition)->setInc('sequence', 1);
				check_error($Vehicle);
			}
			else {
				$condition = array(
					'department_id' => array('eq', $dpm_id)
				);
				$seqs = $Vehicle->where($condition)->order('`sequence` DESC')->limit('1')->field('sequence')->select();
				check_error($Vehicle);
				$seq = empty($seqs) ? 1 : ($seqs[0]['sequence'] + 1);
			}
			
			$vehicle->id = null;
			$vehicle->sequence = $seq;
			
			$Vehicle->create($vehicle);
			check_error($Vehicle);
			
			if(false === $Vehicle->add()){
				//保存日志
				R('Log/adduserlog', array(
					'添加车辆资料',
					'添加车辆资料：' . get_error($Vehicle) ,
					'失败：系统错误',
					'车牌号码：' . $Vehicle->number. '，失败原因：' . get_error($Vehicle)
				));
				return_value_json(false, 'msg', get_error($Vehicle));
			}
			//保存日志
			R('Log/adduserlog', array(
				'添加车辆资料',
				'添加车辆资料成功',
				'成功',
				'车牌号码：' . $Vehicle->number
			));
		}
		
		return_value_json(true);
    }
    
    /**
     * edit操作根据post数据更新数据库。
     */
    public function edit() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
    	$vehicles = json_decode(file_get_contents("php://input"));
    	if(!is_array($vehicles)) {
    		$vehicles = array($vehicles);
    	}
    	
		$Vehicle = D('Vehicle');
		
    	foreach ( $vehicles as $vehicle ) {
    		$Vehicle->create($vehicle);
			check_error($Vehicle);
			
			if(false === $Vehicle->save()) {
				//保存日志
				R('Log/adduserlog', array(
					'修改车辆资料',
					'修改车辆资料失败',
					'失败：系统错误',
					'更新车辆['.$vehicle->number.']时出错：' . get_error($Vehicle)
				));
				return_value_json(false, 'msg', '更新车辆['.$vehicle->number.']时出错：' . get_error($Vehicle));
			}
			//保存日志
			R('Log/adduserlog', array(
				'修改车辆资料',
				'修改车辆资料成功',
				'成功',
				'车牌号码：' . $Vehicle->number
			));
    	}
    	
		return_value_json(true);
    }
    
    public function delete() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
    	
    	$vehicles = json_decode(file_get_contents("php://input"));
    	if(!is_array($vehicles)) {
    		$vehicles = array($vehicles);
    	}
    	
		$Vehicle = D('Vehicle');
		
    	foreach ( $vehicles as $vehicle ) {
			if(false === $Vehicle->where("`id`='" . $vehicle->id . "'")->delete()) {
				//保存日志
				R('Log/adduserlog', array(
					'删除车辆资料',
					'删除车辆资料失败' ,
					'失败：系统错误',
					'删除车辆['.$vehicle->number.']时出错：' . get_error($Vehicle)
				));
				return_value_json(false, 'msg', '删除车辆['.$vehicle->number.']时出错：' . get_error($Vehicle));
			}
			//保存日志
			R('Log/adduserlog', array(
				'删除车辆资料',
				'删除车辆资料成功',
				'成功',
				'车牌号码：' . $vehicle->number
			));
    	}
    	
		return_value_json(true);
    }
    
}
?>