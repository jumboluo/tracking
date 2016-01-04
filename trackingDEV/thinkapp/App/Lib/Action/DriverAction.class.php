<?php

class DriverAction extends Action{
	
	/**
	 * all操作返回所有被分组信息
	 */
	public function all() {
		$Driver = M('Driver');
		check_error($Driver);
		
		$total = $Driver->join('`department` on `department`.`id`=`driver`.`department_id`')
				->count();
		check_error($Driver);
		
		$Driver->join('`department` on `department`.`id`=`driver`.`department_id`')
			->field(array('`driver`.`id`', '`driver`.`name`', "CONCAT(LPAD(`department`.`sequence`, 4, '0'), `department`.`name`)" => 'seq_department', 
						'`department`.`id`'=>'department_id', '`department`.`name`'=>'department', 
						'`driver`.`sequence`', 'birthdate', 'sex', 'licence_date', '`driver`.`memo`',
						'`driver`.`id_card`', '`driver`.`license`',
						'CEIL((TO_DAYS(NOW())-TO_DAYS(`driver`.`licence_date`))/365)' =>'driving_age'))
			->order('`sequence` ASC');
			
// 		$page = $_REQUEST['page'] + 0;
// 		$limit = $_REQUEST['limit'] + 0;
// 		if($page && $limit) $Driver->limit($limit)->page($page);
		
		$drivers = $Driver->select();
//		Log::write(M()->getLastSql(), Log::SQL);
		check_error($Driver);
		
		foreach($drivers as $i => $driver) {
			$drivers[$i]['seq_department'] = empty($driver['seq_department']) ? '0000未分组的' : $driver['seq_department'];
		}
		
		return_json(true,$total,'drivers', $drivers);
	}
	
	/**
	 * indepartment操作分页返回指定分组（$_POST['department_id']下的司机
	 */
	public function indepartment() {
		$dpm_id = $this->_request('department_id') + 0;
		
		$Driver = M('Driver');
		check_error($Driver);
		
		$total = $Driver->join('`department` on `department`.`id`=`driver`.`department_id`')
			->where("`department_id`='{$dpm_id}'")
			->count();
		
		$Driver->join('`department` on `department`.`id`=`driver`.`department_id`')
			->field(array('`driver`.`id`', '`driver`.`name`', "CONCAT(LPAD(`department`.`sequence`, 4, '0'), `department`.`name`)" => 'seq_department', 
						'`department`.`id`'=>'department_id', '`department`.`name`'=>'department', 
						'`driver`.`sequence`', 'birthdate', 'sex', 'licence_date', '`driver`.`memo`',
						'`driver`.`id_card`', '`driver`.`license`',
						'CEIL((TO_DAYS(NOW())-TO_DAYS(`driver`.`licence_date`))/365)' =>'driving_age'))
			->where("`department_id`='{$dpm_id}'")
			->order('`sequence` ASC');
			
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) $Driver->limit($limit)->page($page);
		
		$drivers = $Driver->select();
//		Log::write(M()->getLastSql(), Log::SQL);
		check_error($Driver);
		
		return_json(true,$total,'drivers', $drivers);
	}
	
	/**
	 * add操作根据POST数据插入一个分组信息到数据库里，并返回操作结果
	 */
	public function add() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$Driver = M('Driver');
		
		//数据检查
    	$name = trim($this->_post('name'));
    	if(empty($name))
    		return_value_json(false, 'msg', '司机姓名为空');
		$dpm_id = $this->_post('department_id') + 0;
			
				
		//先更新次序在插入者之后的同一分组的司机的次序
    	$seq = $this->_post('sequence') + 0;
		if(!empty($seq)) {
			$condition['department_id'] = array('eq', $dpm_id);
			$condition['sequence'] = array('egt', $seq);
			$Driver->where($condition)->setInc('sequence', 1);
			check_error($Driver);
		}
		else {
			$condition['department_id'] = array('eq', $dpm_id);
			$seqs = $Driver->where($condition)->order('`sequence` DESC')->limit('1')->field('sequence')->select();
			$seq = empty($seqs) ? 1 : ($seqs[0]['sequence'] + 1);
		}
		
		//插入
		$Driver->create();
		check_error($Driver);
		$Driver->sequence = $seq;
		$Driver->id = null;
			
		if(false === $Driver->add()){
			//保存日志
			R('Log/adduserlog', array(
				'添加司机资料',
				'添加司机资料：' . get_error($Driver) ,
				'失败：系统错误',
				'司机名称：' . $Driver->name. '，失败原因：' . get_error($Driver)
			));
			return_value_json(false, 'msg', get_error($Driver));
		}
		//保存日志
		R('Log/adduserlog', array(
			'添加司机资料',
			'添加司机资料成功',
			'成功',
			'车牌号码：' . $Driver->name
		));
		return_value_json(true);
	}
	
	    /**
     * edit操作根据POST数据更新一个分组信息到数据库里，并返回操作结果
     */
    public function edit() {
    	if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
    	
    	$Driver = M('Driver');
    	$Driver->create();
    	false === $Driver->save() ? 
    		return_value_json(false, 'msg', get_error($Driver)) 
    		:  return_value_json(true);
    		
		if(false === $Driver->save()) {
			//保存日志
			R('Log/adduserlog', array(
				'修改司机资料',
				'修改司机资料失败',
				'失败：系统错误',
				'修改司机['.$Driver->name.']资料时出错：' + get_error($Driver)
			));
			return_value_json(false, 'msg', '修改司机['.$Driver->name.']资料时出错：' + get_error($Driver));
		}
		//保存日志
		R('Log/adduserlog', array(
			'修改司机资料',
			'修改司机资料成功',
			'成功',
			'司机：' . $Driver->name
		));
		return_value_json(true);
    }
    
    /**
     * delete操作根据POST数据更新一个分组信息到数据库里，并返回操作结果
     */
    public function delete() {
    	if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
    	
    	//数据检查
    	$seq = $this->_post('sequence') + 0;
    	if(empty($seq)) return_value_json(false, 'msg', '系统出错：司机在分组里的次序为空或者为0');

    	$id = $this->_post('id') + 0;
    	if(empty($id)) return_value_json(false, 'msg', '系统出错：司机id为空或者为0');
    	
    	$dpm_id = $this->_post('department_id') + 0;
    	
    	$Driver = M('Driver');
    	
    	//先更新次序在插入者之后的分组的次序
		$condition['department_id'] = array('eq', $dpm_id);
    	$condition['sequence'] = array('egt', $seq);
    	$Driver->where($condition)->setDec('sequence', 1);
    	
		if(false === $Driver->where("`id`='" . $id . "'")->delete()) {
			//保存日志
			R('Log/adduserlog', array(
				'删除司机资料',
				'删除司机资料失败' ,
				'失败：系统错误',
				'删除司机['.$this->_post('name').']时出错：' + get_error($Driver)
			));
			return_value_json(false, 'msg', '删除车辆['.$vehicle->number.']时出错：' + get_error($Driver));
		}
		//保存日志
		R('Log/adduserlog', array(
			'删除司机资料',
			'删除司机资料成功',
			'成功',
			'司机：' . $this->_post('name')
		));
		return_value_json(true);
    }
    
    /**
     * reorder操作根据POST['data']数据更新数据库里的sequence字段的值。
     * POST['data'] 的格式为　ID:旧:新,ID:旧:新,ID:旧:新,....
     */
    public function reorder() {
    	if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
    	
    	//数据检查
    	$data = safe_post_param('data');
		if(empty($data)) return_value_json(false, 'msg', '系统出错：重新排序内容为空');
		
		$oldnews = explode(",", $data);
		
    	$Driver = M('Driver');
		foreach ( $oldnews as $oldnew ) {
			$a = explode(":", $oldnew);
			if(count($a)!=3) continue;
			$data = array('sequence'=>$a[2]);
			$Driver->where("`id`='" . $a[0] . "'")->data($data)->save();
		}
		//保存日志
		R('Log/adduserlog', array(
			'调整司机排列顺序',
			'调整司机排列顺序成功',
			'成功',
			''
		));
		return_value_json(true);
    }
}
?>