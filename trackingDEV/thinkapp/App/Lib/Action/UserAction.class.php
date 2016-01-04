<?php

class UserAction extends Action{
	
	/**
	 * all操作分页返回所有的监控设备
	 */
	public function all() {
		$User = M('User');
		check_error($User);
		
		$total = $User->join('`department` on `department`.`id`=`user`.`department_id`')
				->count();
		check_error($User);
		
		$User->join('`department` on `department`.`id`=`user`.`department_id`')
			->field(array('`user`.`id`', 'username', '`user`.`name`', 'role_id', 'role','mobile', 'email',
						"CONCAT(LPAD(`role_id`, 4, '0'), `role`)" => 'role_groupby', 
						"CONCAT(LPAD(`department`.`sequence`, 4, '0'), `department`.`name`)" => 'seq_department', 
						'`user`.`department_id`', '`department`.`name`'=>'department'))
			->order('`department`.`sequence` ASC, `user`.`id`' );
			
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) $User->limit($limit)->page($page);
		
		$users = $User->select();
		check_error($User);
//		Log::write(M()->getLastSql(), Log::SQL);
		
		$ids = array();
		foreach($users as $i => $user) {
			$ids[] = $user['id'];
			$users[$i]['seq_department'] = empty($user['seq_department']) ? '0000未分组的' : $user['seq_department'];
		}
		
		$ManageTarget = M('ManageTarget');
		check_error($ManageTarget);
		
		$mtargets = $ManageTarget->where('`user_id` IN ('.implode(",", $ids).')')->order('`user_id` ASC, `id` ASC')->select();
// 		Log::write(M()->getLastSql(), Log::SQL);
		
		foreach ( $mtargets as $key => $mtarget ) {
			$mtarget['type_id_name'] = $mtarget['target_type'] . '^' .$mtarget['target_id'] . '^' . $mtarget['target_name']; 
			foreach ( $users as $index => $user ) {
				if($user['id']==$mtarget['user_id']) {
					if(!is_array($users[$index]['mtargets'])) $users[$index]['mtargets'] = array();
					$users[$index]['mtargets'][] = $mtarget ;
				}
			}
		}

		return_json(true,$total,'users', $users);
	}

	public function indepartment() {
		$departmentId = $_REQUEST['departmentId'] + 0;
		$condition = empty($departmentId) ?  '`department_id`=0 OR `department_id` IS NULL' : "`department_id`='{$departmentId}'";
		
		$User = M('User');
		check_error($User);
		
		$users = $User->field(array('id', 'username', 'name'))
			->where($condition)
			->order('`id` ASC')
			->select();
		check_error($User);
//		Log::write("\n".M()->getLastSql(), Log::SQL);
		
		foreach($users as $i => $user) {
			$users[$i]['name'] = empty($user['name']) ? $user['username'] : $user['name'];
		}

		return_json(true,null,'users', $users);
	}
	
	/**
	 * add操作根据POST数据，添加一个或多个监控设备资料到数据库
	 */
	public function add() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$users = json_decode(file_get_contents("php://input"));
		if(!is_array($users)) {
			$users = array($users);
		}
		
		$User = D('User');
		check_error($User);
		
		$ManageTarget = M('ManageTarget');
		check_error($ManageTarget);
		
		foreach ( $users as $user ) {
			$User->create($user);
			check_error($User);
			
			$User->id = null;
			
			$id = $User->add();
			
			if(false === $id)
				return_value_json(false, 'msg', get_error($User));
				
			$targets = explode(",", $user->selected_targets);
			foreach($targets as $type_id_name) {
				if(empty($type_id_name)) continue;
				$target = array('user_id' => $id);
				
				$this->getTargetData($type_id_name, $target);
				if(false === $ManageTarget->add($target)){
					//保存日志
					R('Log/adduserlog', array(
						'添加用户',
						'添加用户管理对象失败',
						'失败：系统错误',
						'添加用户管理对象时出错：' . get_error($ManageTarget)
					));
					return_value_json(false, 'msg', '添加用户管理对象时出错：' . get_error($ManageTarget));
				}
			}
			
			//保存日志
			R('Log/adduserlog', array(
				'添加用户',
				'添加用户成功',
				'成功',
				'新用户名：' . $User->name . '，登录用户名' . $User->username
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
	
	
	/**
	 * edit操作根据post数据更新数据库。
	 */
	public function edit() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$users = json_decode(file_get_contents("php://input"));
		if(!is_array($users)) {
			$users = array($users);
		}
		
		$User = D('User');
		check_error($User);
		
		$ManageTarget = M('ManageTarget');
		check_error($ManageTarget);
		
		foreach ( $users as $user ) {
			if(empty($user->password)) {
				unset($user->password);
			}
			else {
				$user->password=md5($user->password);
			}
			
			$User->create($user);	//使用CREATE方法，让ThinkPHP验证字段
			check_error($User);
			
			if(false === $User->save()) {
				//保存日志
				R('Log/adduserlog', array(
					'修改用户资料',
					'修改用户资料失败',
					'失败：系统错误',
					'更新用户['.$user->name.']的信息时出错：' + get_error($User)
				));
				return_value_json(false, 'msg', '更新用户['.$user->name.']的信息时出错：' + get_error($User));
			}
			
			if(false===$ManageTarget->where("`user_id`='{$user->id}'")->delete()){
				//保存日志
				R('Log/adduserlog', array(
					'修改用户资料',
					'更新用户管理对象失败',
					'失败：系统错误',
					'更新用户管理对象时出错：' . get_error($ManageTarget)
				));
				return_value_json(false, 'msg', '更新用户管理对象时出错：' . get_error($ManageTarget));
			}
			
			$targets = explode(",", $user->selected_targets);
			foreach($targets as $type_id_name) {
				if(empty($type_id_name)) continue;
				$target = array('user_id' => $user->id);
				
				$this->getTargetData($type_id_name, $target);
				if(false === $ManageTarget->add($target)){
					//保存日志
					R('Log/adduserlog', array(
						'修改用户资料',
						'更新用户管理对象失败',
						'失败：系统错误',
						'更新用户管理对象时出错：' . get_error($ManageTarget)
					));
					return_value_json(false, 'msg', '更新用户管理对象时出错：' . get_error($ManageTarget));
				}
			}
			
			//保存日志
			R('Log/adduserlog', array(
				'修改用户资料',
				'修改用户资料成功',
				'成功',
				'被修改的用户名：' . $User->name . '，登录用户名' . $User->username
			));
		}
		
		return_value_json(true);
	}
	
	public function delete() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$users = json_decode(file_get_contents("php://input"));
		if(!is_array($users)) {
			$users = array($users);
		}
		
		$User = D('User');
		check_error($User);
		
		$ManageTarget = M('ManageTarget');
		check_error($ManageTarget);
		
		foreach ( $users as $user ) {
			if(false===$ManageTarget->where("`user_id`='{$user->id}'")->delete()){
				//保存日志
				R('Log/adduserlog', array(
					'删除用户',
					'删除用户管理对象失败',
					'失败：系统错误',
					'删除用户管理对象时出错：' . get_error($ManageTarget)
				));
				return_value_json(false, 'msg', '删除用户管理对象时出错：' . get_error($ManageTarget));
			}
			
			if(false === $User->where("`id`='" . $user->id . "'")->delete()) {
				//保存日志
				R('Log/adduserlog', array(
					'删除用户',
					'删除用户失败',
					'失败：系统错误',
					'删除用户出错：' + get_error($User)
				));
				return_value_json(false, 'msg', '删除删除用户出错：' + get_error($User));
			}
			
			//保存日志
			R('Log/adduserlog', array(
				'删除用户',
				'删除用户成功',
				'成功',
				'被删除的用户' . $user->name
			));
		}
		
		return_value_json(true);
	}
	
	public function changepassword() {
		$oldpassword = $_POST['oldpassword'];
		$newpassword = $_POST['newpassword'];
		if(empty($oldpassword) || empty($newpassword)) {
			return_value_json(false, "msg", "旧密码或者新密码不能为空");
		}
		
		$user = session('user');
		if(empty($user) || empty($user['userId'])) {
			return_value_json(false, "msg", "用户尚未登陆，不能修改密码");
		}

		//更新前先进行MD5加密
		$oldpassword = md5($oldpassword);
		$newpassword = md5($newpassword);
		
		$result = M()->execute("UPDATE `user` SET `password`='{$newpassword}' WHERE `id`='{$user['userId']}' AND `password`='{$oldpassword}'");
		
		if($result) {
			return_value_json(true);
		}
		else {
			return_value_json(false, 'msg', '旧密码错误');
		}
		
	}
}
?>