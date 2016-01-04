<?php

import('App.Util.LBS.Geometry');

class EmployeeAction extends Action{
	
	/**
	 * all操作分页返回所有的人员资料
	 */
	public function all() {
		$Employee = M('Employee');
		check_error($Employee);
		
		$total = $Employee->count();
		check_error($Employee);
		
		$Employee->join('`department` on `department`.`id`=`employee`.`department_id`')
			->join("`device` on (`device`.`target_id`=`employee`.`id` AND `device`.`target_type`='人员')")
			->join('`location` on `location`.`id`=`device`.`last_location`')
			->join('`user` on `user`.`id`=`employee`.`user_id`')
			->field(array('`employee`.`id`', '`employee`.`name`', '`employee`.`post`', '`employee`.`user_id`', 
						'`employee`.`sequence`', '`employee`.`memo`',
						"CONCAT(LPAD(`department`.`sequence`, 4, '0'), `department`.`name`)" => 'seq_department', 
						'`employee`.`department_id`', '`department`.`name`'=>'department', 
						'`user`.`username`', '`user`.`role_id`', '`user`.`role`', '`user`.`mobile`', '`user`.`email`', 
						'`location`.`state`', '`location`.`online`' 
						))
			->order('`department`.`sequence` ASC, `employee`.`sequence` ASC, `employee`.`id`' );
			
// 		$page = $_REQUEST['page'] + 0;
// 		$limit = $_REQUEST['limit'] + 0;
// 		if($page && $limit) $Employee->limit($limit)->page($page);
		
		$employees = $Employee->select();
		check_error($Employee);
//		Log::write(M()->getLastSql(), Log::SQL);
		
		$ids = array();
		foreach($employees as $i => $employee) {
			$employees[$i]['is_user'] = empty($employee['user_id']) ? 0 : 1;
			if(!empty($employee['user_id'])) $ids[] = $employee['user_id'];
			$employees[$i]['seq_department'] = empty($employee['seq_department']) ? '0000未分组的' : $employee['seq_department'];
		}
		
		$ManageTarget = M('ManageTarget');
		check_error($ManageTarget);
		
		$mtargets = $ManageTarget->where('`user_id` IN ('.implode(",", $ids).')')->order('`user_id` ASC, `id` ASC')->select();
//		Log::write(M()->getLastSql(), Log::SQL);
		
		foreach ( $mtargets as $key => $mtarget ) {
			$mtarget['type_id_name'] = $mtarget['target_type'] . '^' .$mtarget['target_id'] . '^' . $mtarget['target_name']; 
			foreach ( $employees as $index => $employee ) {
				if($employee['user_id']==$mtarget['user_id']) {
					if(!is_array($employees[$index]['mtargets'])) $employees[$index]['mtargets'] = array();
					$employees[$index]['mtargets'][] = $mtarget ;
				}
			}
		}

		return_json(true,$total,'employees', $employees);
	}
	
	/**
	 * add操作根据POST数据，添加一个或多个人员资料资料到数据库
	 */
	public function add() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$employees = json_decode(file_get_contents("php://input"));
		if(!is_array($employees)) {
			$employees = array($employees);
		}
		
		$Employee = M('Employee');
		check_error($Employee);
		
		$User = D('User');
		check_error($User);
		
		$ManageTarget = M('ManageTarget');
		check_error($ManageTarget);
		
		foreach ( $employees as $employee ) {
//			Log::write("\n".print_r($employee, true), Log::INFO);
			$user_id = 0;
			if(isset($employee->is_user) && !empty($employee->is_user)) {
				$User->create($employee);
				check_error($User);
				
				$User->id = null;
				
				$user_id = $User->add();
				
				if(false === $user_id) {
					//保存日志
					R('Log/adduserlog', array(
						'添加人员资料',
						'添加人员的系统用户信息失败',
						'失败：系统错误',
						'人员：' . $User->name. '，失败原因：' . get_error($User)
					));
					return_value_json(false, 'msg', '插入人员的系统用户信息时出错：'.get_error($User));
				}
				
				$targets = explode(",", $employee->selected_targets);
				foreach($targets as $type_id_name) {
					if(empty($type_id_name)) continue;
					$target = array('user_id' => $user_id);
					
					$this->getTargetData($type_id_name, $target);
					if(false === $ManageTarget->add($target)){
						//保存日志
						R('Log/adduserlog', array(
							'添加人员资料',
							'添加用户管理对象失败',
							'失败：系统错误',
							'人员：' . $User->name. '，添加用户管理对象时出错：' . get_error($ManageTarget)
						));
						return_value_json(false, 'msg', '添加用户管理对象时出错：' . get_error($ManageTarget));
					}
				}
			}
			$Employee->create($employee);
			check_error($Employee);
			
			$Employee->id = null;
			$Employee->user_id = $user_id;
			
			$seq = isset($employee->sequence) ? $employee->sequence + 0 : 0;
			if(!empty($seq)) {
				$condition['department_id'] = array('eq', $employee->department_id);
				$condition['sequence'] = array('egt', $seq);
				$Employee->where($condition)->setInc('sequence', 1);
				check_error($Employee);
			}
			else {
				$condition['department_id'] = array('eq', $employee->department_id);
				$seqs = $Employee->where($condition)->order('`sequence` DESC')->limit('1')->field('sequence')->select();
				$seq = empty($seqs) ? 1 : ($seqs[0]['sequence'] + 1);
			}
			$Employee->sequence = $seq;
			
			if(false === $Employee->add()) {
				//保存日志
				R('Log/adduserlog', array(
					'添加人员资料',
					'添加人员资料失败',
					'失败：系统错误',
					'人员：' . $Employee->name. '，失败原因：' . get_error($Employee)
				));
				return_value_json(false, 'msg', get_error($Employee));
			}
			
			//保存日志
			R('Log/adduserlog', array(
				'添加人员资料',
				'添加人员资料成功',
				'成功',
				'人员：' . $Employee->name
			));
		}
		
		return_value_json(true);
	}
	
	
	/**
	 * edit操作根据post数据更新数据库。
	 */
	public function edit() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$employees = json_decode(file_get_contents("php://input"));
		if(!is_array($employees)) {
			$employees = array($employees);
		}
		
		$Employee = M('Employee');
		check_error($Employee);
		
		$User = D('User');
		check_error($User);
		
		$ManageTarget = M('ManageTarget');
		check_error($ManageTarget);
		
		foreach ( $employees as $employee ) {
			if(!empty($employee->user_id)) { //原来是系统用户
				if(empty($employee->is_user)) { //现在不是系统用户了
					if(false === $User->where("`id`='{$employee->user_id}'")->delete()){
						//保存日志
						R('Log/adduserlog', array(
							'修改人员资料',
							'修改人员用户信息失败',
							'失败：系统错误',
							'人员：' . $Employee->name. '，删除人员用户信息时出错：' . get_error($User)
						));
						return_value_json(false, 'msg', '删除人员用户信息时出错：' . get_error($User));
					}
					if(false === $ManageTarget->where("`user_id`='{$employee->user_id}'")->delete()){
						//保存日志
						R('Log/adduserlog', array(
							'修改人员资料',
							'修改人员用户的管理对象失败',
							'失败：系统错误',
							'人员：' . $Employee->name. '，删除人员用户的管理对象时出错：' . get_error($ManageTarget)
						));
						return_value_json(false, 'msg', '删除人员用户的管理对象时出错：' . get_error($ManageTarget));
					}
					$employee->user_id = 0;
				}
				else {
					if(empty($employee->password)) {
						unset($employee->password);
					}
					else {
						$employee->password=md5($employee->password);
					}
					
					$User->create($employee);
					$User->id = $employee->user_id;
					
					if(false === $User->save()) {
						//保存日志
						R('Log/adduserlog', array(
							'修改人员资料',
							'修改人员用户信息失败',
							'失败：系统错误',
							'人员：' . $Employee->name. '，失败原因：' . get_error($User)
						));
						return_value_json(false, 'msg', '更新人员用户信息时出错：' . get_error($User));
					}
					
					$ManageTarget->where("`user_id`='".$employee->user_id."'")->delete();
					
					$targets = explode(",", $employee->selected_targets);
					foreach($targets as $type_id_name) {
						if(empty($type_id_name)) continue;
						$target = array('user_id' => $employee->user_id);

						$this->getTargetData($type_id_name, $target);
						if(false === $ManageTarget->add($target)){
							//保存日志
							R('Log/adduserlog', array(
								'修改人员资料',
								'修改人员用户的管理对象失败',
								'失败：系统错误',
								'人员：' . $Employee->name. '，更新用户管理对象时出错：' . get_error($ManageTarget)
							));
							return_value_json(false, 'msg', '更新用户管理对象时出错：' . get_error($ManageTarget));
						}
					}
				}
			}
			else if(!empty($employee->is_user)) { //原来不是系统用户，现在是
				$User->create($employee);
				check_error($User);
				
				$User->id = null;
				
				$user_id = $User->add();
				
				if(false === $user_id) {
					//保存日志
					R('Log/adduserlog', array(
						'修改人员资料',
						'修改人员的用户信息失败',
						'失败：系统错误',
						'人员：' . $Employee->name. '，插入人员的系统用户信息时出错：' . get_error($User)
					));
					return_value_json(false, 'msg', '插入人员的系统用户信息时出错：'.get_error($User));
				}
				
				$targets = explode(",", $employee->selected_targets);
				foreach($targets as $type_id_name) {
					if(empty($type_id_name)) continue;
					$target = array('user_id' => $user_id);
					
					$this->getTargetData($type_id_name, $target);
					if(false === $ManageTarget->add($target)){
						//保存日志
						R('Log/adduserlog', array(
							'修改人员资料',
							'修改人员用户的管理对象出错',
							'失败：系统错误',
							'人员：' . $Employee->name. '，添加用户管理对象时出错：' . get_error($ManageTarget)
						));
						return_value_json(false, 'msg', '添加用户管理对象时出错：' . get_error($ManageTarget));
					}
				}
				$employee->user_id = $user_id;
			}
			
			$Employee->create($employee);
			check_error($Employee);
			
			if(false === $Employee->save()) {
				//保存日志
				R('Log/adduserlog', array(
					'修改人员资料',
					'修改人员资料出错',
					'失败：系统错误',
					'人员：' . $Employee->name. '，出错原因：' . get_error($Employee)
				));
				return_value_json(false, 'msg', '更新人员资料['.$employee->name.']时出错：' . get_error($Employee));
			}
			
			//保存日志
			R('Log/adduserlog', array(
				'修改人员资料',
				'修改人员资料成功',
				'成功',
				'人员：' . $Employee->name
			));
		}
		
		return_value_json(true);
	}
	
	private function getTargetData($type_id_name, &$target) {
		$target['target_type'] = substr($type_id_name, 0, strpos($type_id_name, '^'));
		$left = substr(strstr($type_id_name, '^'), 1);
		$target['target_id'] = substr($left, 0, strpos($left, '^'));
		$target['target_name'] = substr(strstr($left, '^'), 1);;
	}
	
	public function delete() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$employees = json_decode(file_get_contents("php://input"));
		if(!is_array($employees)) {
			$employees = array($employees);
		}
		
		$Employee = M('Employee');
		check_error($Employee);
		
		$ids = array();
		foreach ( $employees as $employee ) {
	    	//先更新次序在插入者之后的分组的次序
			$condition['department_id'] = array('eq', $employee->department_id);
	    	$condition['sequence'] = array('egt', $employee->sequence);
	    	$Employee->where($condition)->setDec('sequence', 1);
	    	
			if(false === $Employee->where("`id`='" . $employee->id . "'")->delete()) {
				//保存日志
				R('Log/adduserlog', array(
					'删除人员资料',
					'删除人员资料出错',
					'失败：系统错误',
					'人员：' . $employee->name. '，出错原因：' . get_error($Employee)
				));
				return_value_json(false, 'msg', '删除人员资料['.$employee->name.']时出错：' . get_error($Employee));
			}
			
			if(!empty($employee->user_id)) $ids[] = $employee->user_id;
			
			//保存日志
			R('Log/adduserlog', array(
				'删除人员资料',
				'删除人员资料成功',
				'成功',
				'人员：' . $employee->name
			));
		}
		
		if(!empty($ids)) {
			$User = D('User');
			check_error($User);
			
			$ManageTarget = M('ManageTarget');
			check_error($ManageTarget);
			
			$ids = implode(",", $ids);
			if(false === $User->where("`id` IN ({$ids})")->delete()){
				//保存日志
				R('Log/adduserlog', array(
					'删除人员资料',
					'删除人员用户信息出错',
					'失败：系统错误',
					'删除人员用户信息时出错：' . get_error($User)
				));
				return_value_json(false, 'msg', '删除人员用户信息时出错：' . get_error($User));
			}
			if(false === $ManageTarget->where("`user_id` IN ({$ids})")->delete()){
				//保存日志
				R('Log/adduserlog', array(
					'删除人员资料',
					'删除人员用户的管理对象出错',
					'失败：系统错误',
					'删除人员用户的管理对象时出错：' . get_error($ManageTarget)
				));
				return_value_json(false, 'msg', '删除人员用户的管理对象时出错：' . get_error($ManageTarget));
			}
		}

		return_value_json(true);
	}
	
	////////////////////////////人员的定位、轨迹相关///////////////////////////////////////
    public function statistics() {
		$Employee = M('Employee');
		check_error($Employee);
    	
		$targetsQuery = D('ManageTarget')->getTargetsQuery();
		
    	$total = $Employee->join("`device` on (`employee`.`id`=`device`.`target_id` AND `device`.`target_type`='人员')")
    			->where($targetsQuery)
    			->count();
    	$online = $Employee->join("`device` on (`employee`.`id`=`device`.`target_id` AND `device`.`target_type`='人员')")
				->join('`location` on `location`.`id`=`device`.`last_location`')
				->where("`location`.`online`='在线' AND " . $targetsQuery)
				->count();
    	$offline = $Employee->join("`device` on (`employee`.`id`=`device`.`target_id` AND `device`.`target_type`='人员')")
				->join('`location` on `location`.`id`=`device`.`last_location`')
				->where("`location`.`online`='离线' AND " . $targetsQuery)
				->count();
		
		$Alarm = M('Alarm');
		$alarm = $Alarm->join("LEFT JOIN `device` on `alarm`.`device_id`=`device`.`id`")
				->where("`target_type`='人员' AND `checked`=0 AND " . $targetsQuery)
				->count();
		
    	return_json(true, 1, 'statistics', array(array('total'=>$total,'online'=>$online, 'offline'=>$offline, 'alarm'=>$alarm, 'time'=>date('Y-m-d H:i:s'))));
    }
    
    
    /**
	 * query操作返回监控中心查询的人员列表
	 */
    public function query() {
    	$Employee = M('Employee');
    	check_error($Employee);
    	
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
    		$condition['_string'] .= " `employee`.`name` LIKE '%{$_REQUEST['fuzzy']}%' "; //注：MYSQL的like其实是不区分大小写的，也就是说like '%k%'将查询到包含K和k的
    		$condition['_string'] .= " OR `user`.`username` LIKE '%{$_REQUEST['fuzzy']}%' ";
    		$condition['_string'] .= " OR `user`.`email` LIKE '%{$_REQUEST['fuzzy']}%' ";
    		$condition['_string'] .= " OR `user`.`mobile` LIKE '%{$_REQUEST['fuzzy']}%' ";
    		$condition['_string'] .= " OR `employee`.`post` LIKE '%{$_REQUEST['fuzzy']}%' ";
    		$condition['_string'] .= " ) ";
    	}
    	
    	
    	$trackings = $Employee->join('`department` on `department`.`id`=`employee`.`department_id`')
    	->join('`user` on `user`.`id`=`employee`.`user_id`')
    	->join("`device` on (`employee`.`id`=`device`.`target_id` AND `device`.`target_type`='人员')")
    	->join('`location` on `location`.`id`=`device`.`last_location`')
    	->field(array('`device`.`id`', '`device`.`type`', '`device`.`label`', 'interval', 'delay',
    			'`employee`.`id`'=>'employee_id', '`employee`.`name`', '`employee`.`post`',
    			'`employee`.`department_id`', '`department`.`name`'=>'department',
    			'`device`.`target_type`', '`device`.`target_id`', '`employee`.`name`'=>'target_name',
    			'last_location', '`location`.`time`' , 'state', 'online', '`location`.`address`',
    			'baidu_lat', 'baidu_lng', 'speed', 'direction', '`device`.`mobile_num`',
    			'CONCAT(`state`, `online`)' => 'state_online', '`location`.`range`' 
    	))
    	->distinct(true)
    	->where($condition)
    	->order('`department`.`sequence` ASC, `department`.`id` ASC, `employee`.`sequence` ASC')
    	->select();
    	check_error($Employee);
//     	return_value_json(true, 'children', array(array(
//     		'tree_text'=>'Project: Shopping1',
//     		'expanded'=>true,
//     		'children' => array(array(
//     				'tree_text'=>'Housewares1',
//     				leaf=>true
//     		),array(
//     				'tree_text'=>'Housewares2',
//     				leaf=>true
//     		))
//     	),array(
//     		'tree_text'=>'Project: Shopping2',
//     		'expanded'=>true,
//     		'children' => array(array(
//     				'tree_text'=>'Housewares3',
//     				leaf=>true
//     		),array(
//     				'tree_text'=>'Housewares4',
//     				leaf=>true
//     		),array(
//     				'tree_text'=>'Housewares5',
//     				leaf=>true
//     		))
//     	)));
    	
//     	Log::write("\n".M()->getLastSql());
//     	Log::write("\n".print_r($trackings, true), Log::INFO);
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
    		$tracking['tree_text'] = $tracking['name'];
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
     * 刷新定位
     */
    public function refresh() {
    	R('Tracking/refreshOnlineState');
    	
		$Employee = M('Employee');
		check_error($Employee);
		
		$condition = array(
			'_string' => "`device`.`target_type`='人员' AND `device`.`target_id`<>0 AND " . D('ManageTarget')->getTargetsQuery()
		);
		
		$Employee->join('`department` on `department`.`id`=`employee`.`department_id`')
			->join('`user` on `user`.`id`=`employee`.`user_id`')
			->join("`device` on (`employee`.`id`=`device`.`target_id` AND `device`.`target_type`='人员')")
			->join('`location` on `location`.`id`=`device`.`last_location`')
			->field(array('`device`.`id`', '`device`.`type`', '`device`.`label`', 'interval', 'delay',
						'`device`.`mobile_num`',
						'`employee`.`id`'=>'employee_id', '`employee`.`name`', '`employee`.`post`', 
						'`employee`.`department_id`', '`department`.`name`'=>'department', 
						'`device`.`target_type`', '`device`.`target_id`', '`employee`.`name`'=>'target_name', 
						'last_location', '`location`.`time`' , 'state', 'online',
						'baidu_lat', 'baidu_lng', 'speed', 'direction',  '`device`.`mobile_num`', 
						'CONCAT(`state`, `online`)' => 'state_online', '`location`.`range`' , '`location`.`address`'
						))
			->distinct(true)
			->where($condition)
			->order('`department`.`sequence` ASC, `employee`.`sequence` ASC');
			
//		$page = $_REQUEST['page'] + 0;
//		$limit = $_REQUEST['limit'] + 0;
//		if($page && $limit) $Employee->limit($limit)->page($page);
		
		$trackings = $Employee->select();
		check_error($Employee);
		
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
			->join("`employee` on (`employee`.`id`=`device`.`target_id` AND `device`.`target_type`='人员')")
			->join('`department` on `department`.`id`=`employee`.`department_id`')
			->join('`location` on `location`.`device_id`=`device`.`id`')
			->field(array('`device`.`id`', '`device`.`type`', '`device`.`label`', 'interval', 'delay', 
						'`employee`.`id`'=>'employee_id', '`employee`.`name`', '`employee`.`post`', 
						'`employee`.`department_id`', '`department`.`name`'=>'department', 
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
				'employee_id'=> $devicetracking['employee_id'],
				'name'		=> $devicetracking['name'],
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
				'target_name'=> $devicetracking['name'],//原来是target_name
				'address'	=> $devicetracking['address'],
				'range'		=> $devicetracking['range']
    		);
    	}
    	
    	return $re;
    }
    
    /**
     * 区域人员查询
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
			'_string' => " `location`.`time`>='{$starttime}' AND `employee`.`name` IS NOT NULL AND " . D('ManageTarget')->getTargetsQuery()
		);
		if(!empty($endtime)) {
			$condition['_string'] .= " AND `location`.`time`<='{$endtime}' ";
		}
		
    	$Location
			->join('`device` on `device`.`id`=`location`.`device_id`')
			->join("`employee` on (`employee`.`id`=`device`.`target_id` AND `device`.`target_type`='人员')")
			->join('`department` on `department`.`id`=`employee`.`department_id`')
			->field(array('`location`.`id`', 
						'`department`.`id`'=>'department_id', '`department`.`name`'=>'department',
						'`employee`.`id`' => 'employee_id', '`employee`.`name`', '`employee`.`post`',
						'`location`.`device_id`', '`device`.`type`', '`device`.`label`',   
						'`location`.`time`' , 'state', 'online', '`location`.`address`' ,
						'baidu_lat', 'baidu_lng', 'speed', 'direction',
						'mcc', 'mnc', 'lac', 'cellid', '`location`.`range`' 
						))
			->where($condition)
			->order('`department`.`sequence`, `employee`.`sequence`,`employee`.`id`, `time` ASC');	//先按人员id，然后按时间顺序
    	
// 		$page = $_REQUEST['page'] + 0;
// 		$limit = $_REQUEST['limit'] + 0;
//		if($page && $limit) $Location->limit($limit)->page($page); //这里不用数据库的分页，而是用我们自己的分页（目前没法分页了）
		
		$locations = $Location->select();
		check_error($Location);
		
		$total = 0;
		$results = array();
		$curEmployee = null;	//当前在区域内的人员
		$alreadyIn = false; //目前是否已经在区域内
		$lastLocation = null;
		foreach ( $locations as $location ) {
			$in = Geometry::geoPointInPolygon(array('lat' => $location['baidu_lat'], 'lng'=> $location['baidu_lng']), $points);
			//TODO 考虑当前点与上一个定位点的轨迹线段是否切割多边形？
			
			if($in) {
				if($alreadyIn && $curEmployee != $location['employee_id']){ //已经在区域内，但是现在来了个不同的车（原来的车不知道跑哪里去了，这个车不知道是从哪里来的）
					//那么我们认为前车的离开点就是他轨迹的最后一个点，并且它现在离开区域了
					$lastEmployeeLocationsCount = count($results[$total-1]['locations']);
					if($lastEmployeeLocationsCount>0) {
						$results[$total-1]['time_out'] = $results[$total-1]['locations'][$lastEmployeeLocationsCount-1]['time'];
						$results[$total-1]['duration'] = $this->_getFriendlyDurationText($results[$total-1]['time_in'], $results[$total-1]['time_out']);
						$alreadyIn = false;
						$curEmployee = null;
					}
				}
				
				if(!$alreadyIn && $curEmployee===null) { //首次进入
					$alreadyIn = true;
					$curEmployee = $location['employee_id'];
					$results[] = array(
						'id' 		=> $location['id'],
						'department_id'	=> $location['department_id'],
						'department'=> $location['department'],
						'employee_id'=> $location['employee_id'],
						'name'		=> $location['name'],
						'post'		=> $location['post'],
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
					
					if($lastLocation!==null && $lastLocation['employee_id'] ==$location['employee_id']) {
						$results[$total-1]['locations'][] = $lastLocation;
						$results[$total-1]['first_isout'] = true;
					}
				}
				
				$results[$total-1]['locations'][] = $location;
			}
			else { //出了区域
				if($alreadyIn) {
					if($curEmployee != $location['employee_id']){//原来的车不知道跑哪里去了
						$lastEmployeeLocationsCount = count($results[$total-1]['locations']);
						if($lastEmployeeLocationsCount>0) {
							$results[$total-1]['time_out'] = $results[$total-1]['locations'][$lastEmployeeLocationsCount-1]['time'];
						}
						else { //这是不可能的。
							$results[$total-1]['time_out'] = $location['time'];
						}
					}
					else { //记录人员离开的点
						$results[$total-1]['time_out'] = $location['time'];
						$results[$total-1]['locations'][] = $location;
						$results[$total-1]['last_isout'] = true;
					}
					
					$results[$total-1]['duration'] = $this->_getFriendlyDurationText($results[$total-1]['time_in'], $results[$total-1]['time_out']);
					$alreadyIn = false;
					$curEmployee = null;					
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