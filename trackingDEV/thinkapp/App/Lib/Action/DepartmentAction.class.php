<?php

class DepartmentAction extends Action{

	/**
	 * all操作返回所有被分组信息
	 */
    public function all() {
    	$Department = M('Department');
// 		$total = $Department->count(); //0号角色是特殊角色，不在显示之列
    	$Department->order('`sequence` ASC');
//     	$page = $_REQUEST['page'] + 0;
//     	$limit = $_REQUEST['limit'] + 0;
//     	if($page && $limit) $Department->limit($limit)->page($page);
    	$departments = $Department->select();
    	check_error($Department);
    	return_json(true,null,'departments', $departments);
    }
    
    /**
     * get操作返回分页的分组信息
     */
    public function get() {
    	
    }
    
    /**
     * add操作根据POST数据插入一个分组信息到数据库里，并返回操作结果
     */
    public function add() {
    	if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
    	
    	$Department = M('Department');
    	
    	//数据检查
    	$name = trim($this->_post('name'));
    	if(empty($name))
    		return_value_json(false, 'msg', '分组名称为空');
    	$seq = $this->_post('sequence') + 0;
    	if(empty($seq)) 
			return_value_json(false, 'msg', '系统出错：提交的序号为0或者为空');
    	
    	//先更新次序在插入者之后的分组的次序
    	$condition['sequence'] = array('egt', $seq);
    	$Department->where($condition)->setInc('sequence', 1);
		check_error($Department);
    	
    	//插入
    	$Department->create();
		check_error($Department);
    	$Department->id = null;
    	if(false === $Department->add() ){ 
			//保存日志
			R('Log/adduserlog', array(
				'添加分组',
				'添加分组失败：' . get_error($Department) ,
				'失败：系统错误',
				'分组名称：' . $name
			));
    		return_value_json(false, 'msg', get_error($Department));
    	}
    	else {
			//保存日志
			R('Log/adduserlog', array(
				'添加分组',
				'添加分组成功',
				'成功',
				'分组名称：' . $name
			));
    		return_value_json(true);
    	}
    }
    
    /**
     * edit操作根据POST数据更新一个分组信息到数据库里，并返回操作结果
     */
    public function edit() {
    	if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
    	
    	$Department = M('Department');
    	$Department->create();
    	if(false === $Department->save() ){ 
			//保存日志
			R('Log/adduserlog', array(
				'修改分组信息',
				'修改分组信息失败：' . get_error($Department) ,
				'失败：系统错误',
				'分组名称：' . $Department->name
			));
    		return_value_json(false, 'msg', get_error($Department));
    	}
    	else {
			//保存日志
			R('Log/adduserlog', array(
				'修改分组信息',
				'修改分组信息成功',
				'成功',
				'分组名称：' . $Department->name
			));
    		return_value_json(true);
    	}
    }
    
    /**
     * delete操作根据POST数据更新一个分组信息到数据库里，并返回操作结果
     */
    public function delete() {
    	if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
    	
    	//数据检查
    	$seq = $this->_post('sequence') + 0;
    	if(empty($seq)) return_value_json(false, 'msg', '系统出错：分组次序为空或者为0');

    	$id = $this->_post('id') + 0;
    	if(empty($id)) return_value_json(false, 'msg', '系统出错：分组id为空或者为0');
    	
    	$Department = M('Department');
    	
    	//先更新次序在插入者之后的分组的次序
    	$condition['sequence'] = array('egt', $seq);
    	$Department->where($condition)->setDec('sequence', 1);
    	
    	if(false === ($Department->where("`id`='" . $id . "'")->delete()) ){ 
			//保存日志
			R('Log/adduserlog', array(
				'删除分组',
				'删除分组失败：' . get_error($Department) ,
				'失败：系统错误',
				'分组名称：' . $Department->name
			));
    		return_value_json(false, 'msg', get_error($Department));
    	}
    	else {
			//保存日志
			R('Log/adduserlog', array(
				'删除分组',
				'删除分组成功',
				'成功',
				'分组名称：' . $Department->name
			));
    		return_value_json(true);
    	}
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
		
    	$Department = M('Department');
		foreach ( $oldnews as $oldnew ) {
			$a = explode(":", $oldnew);
			if(count($a)!=3) continue;
			$data = array('sequence'=>$a[2]);
			$Department->where("`id`='" . $a[0] . "'")->data($data)->save();
		}
		//保存日志
		R('Log/adduserlog', array(
			'修改分组信息',
			'重新调整分组的排序',
			'成功',
			'分组名称：' . $Department->name
		));
		return_value_json(true);
    }
}
?>