<?php

class RoleAction extends Action {
	
	/**
	 * all操作分页返回所有的角色json
	 * 注：每个角色的数据还包含了其权限内容
	 */
	public function all() {
		$Role = M('Role');
		check_error($Role);
		
		$total = $Role->count(); //0号角色是特殊角色，不在显示之列
		check_error($Role);
		
		$Role->field(array('id', 'name', 'enable'));
		
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) $Role->limit($limit)->page($page);
			
		$roles = $Role->select();
		check_error($Role);
		
		$RolePrivilege = M('RolePrivilege');
		check_error($RolePrivilege);
		foreach ( $roles as $i => $role ) {
			$privileges = $RolePrivilege->join('`menu` on `menu`.`id` = `role_privilege`.`menu_id`')
				->field(array('`role_privilege`.`id`', 'role_id', 'privilege', 'menu_id', 'level'))
				->where("`role_privilege`.`role_id`='" . $role['id'] . "'")
				->order('`level` ASC')
				->select();
			$roles[$i]['privileges'] = $privileges;
		}
		
		return_json(true,$total,'roles', $roles);
	}
	
	/**
	 * allprivilege操作返回所有的权限（即根据系统menu表反查所有权限）
	 */
	public function allprivilege() {
		$Menu = M('Menu');
		check_error($Menu);
		$privileges = $Menu->field(array('id', 'label'=>'privilege', 'id'=>'menu_id', 'level')) //没有role_id
				->order('`level` ASC')
				->select();
				
		return_json(true,null,'privileges', $privileges);
	}
	
	/**
	 * add操作根据post数据添加角色权限
	 */
	public function add() {
    	if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
    	
		//添加角色
		$Role = M('Role');
		check_error($Role);
		
		$Role->create();
		check_error($Role);
		
		$enable = $this->_post('enable');
		$Role->enable = empty($enable) ? 0 : 1;
		
		$role_id = $Role->add();
		if($role_id===false) {
			//保存日志
			R('Log/adduserlog', array(
				'添加角色',
				'添加角色失败',
				'失败：系统错误',
				'添加角色时出错：' . get_error($Role)
			));
			return_value_json(false, 'msg', get_error($Role));
		}
		//保存日志
		R('Log/adduserlog', array(
			'添加角色',
			'添加角色成功',
			'成功'
		));
		
		//查找全部权限
		$Menu = M('Menu');
		check_error($Menu);
		$privileges = $Menu->field(array('label'=>'privilege', 'id'=>'menu_id', 'level'))
				->order('`level` ASC')
				->select();
		
		//添加角色权限
		$RolePrivilege = M('RolePrivilege');
		check_error($RolePrivilege);
		$curLevel1 = null;
		$curLevel1Added = false;
		foreach ( $privileges as $privilege ) {
			if(strlen($privilege['level'])==2) {
				$curLevel1 = $privilege;
				$curLevel1Added = false;
				continue;
			}
			$level = $this->_post('p_' . $privilege['level']);
			if(!empty($level)) {
				if(!$curLevel1Added) {
					$RolePrivilege->create($curLevel1);
					check_error($RolePrivilege);
					$RolePrivilege->role_id = $role_id;
					if(false === $RolePrivilege->add()) {
						//保存日志
						R('Log/adduserlog', array(
							'添加角色',
							'添加角色权限失败',
							'失败：系统错误',
							'添加角色时出错：' . get_error($RolePrivilege)
						));
						return_value_json(false, 'msg', get_error($RolePrivilege));
					}
					
					$curLevel1Added = true;
				}
				
				$RolePrivilege->create($privilege);
				check_error($RolePrivilege);
				$RolePrivilege->role_id = $role_id;
				if(false === $RolePrivilege->add()) {
					//保存日志
					R('Log/adduserlog', array(
						'添加角色',
						'添加角色权限失败',
						'失败：系统错误',
						'添加角色时出错：' . get_error($RolePrivilege)
					));
					return_value_json(false, 'msg', get_error($RolePrivilege));
				}
			}
		}
		//保存日志
		R('Log/adduserlog', array(
			'添加角色',
			'添加角色权限成功',
			'成功'
		));
		
		
		return_value_json(true);
	}
	
	/**
	 * edit方法修改post指定的用户角色，并调整角色权限
	 */
	public function edit() {
    	if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
    	
		//添加角色
		$Role = M('Role');
		check_error($Role);
		
		$Role->create();
		check_error($Role);
		
		$enable = $this->_post('enable');
		$Role->enable = empty($enable) ? 0 : 1;
		
		$role_id = $Role->id;
		
		$result = $Role->save();
		if($result===false) {
			//保存日志
			R('Log/adduserlog', array(
				'修改角色权限',
				'修改角色权限失败',
				'失败：系统错误',
				'修改角色权限时出错：' . get_error($Role)
			));
			return_value_json(false, 'msg', get_error($Role));
		}
		
		//保存日志
		R('Log/adduserlog', array(
			'修改角色权限',
			'修改角色权限成功',
			'成功',
			'角色名称：' . $Role->name
		));
		
		//查找全部权限
		$Menu = M('Menu');
		check_error($Menu);
		$privileges = $Menu->field(array('label'=>'privilege', 'id'=>'menu_id', 'level'))
				->order('`level` ASC')
				->select();
		
		//添加角色权限
		$RolePrivilege = M('RolePrivilege');
		check_error($RolePrivilege);
		
		$RolePrivilege->startTrans();
		check_error($RolePrivilege);
		
		$hadLevel1 = false;	//原来是否有一级菜单
		$curLevel1 = null;	//当前的一级菜单
		$hasCurLevel1 = false;	//现在是否有一级菜单
		foreach ( $privileges as $privilege ) {
			$condition = array(
				'role_id' => $role_id,
				'privilege' => $privilege['privilege'],
				'menu_id' => $privilege['menu_id']
			);
			
			//查找原来是否有当前权限
			$has = $RolePrivilege->where($condition)->find();
			if(false === $has) {
				//保存日志
				R('Log/adduserlog', array(
					'修改角色权限',
					'修改角色权限失败',
					'失败：系统错误',
					'修改角色权限[角色名称：' . $Role->name . ']在查找原权限时失败：' . get_error($RolePrivilege)
				));
				$RolePrivilege->rollback();
				return_value_json(false, 'msg', '修改角色权限在查找原权限时失败：' . get_error($RolePrivilege));
			}

			if(strlen($privilege['level'])==2) { //新的一级
				$hadLevel1 = ($has != null);
				$curLevel1 = $condition;
				$hasCurLevel1 = false;
				continue;
			}

			$level = $this->_post('p_' . $privilege['level']);

			//如果现在有当前权限
			if(!empty($level)) {
				//如果此前没有一级菜单，则先添加一级菜单，
				if(!$hadLevel1){
					$RolePrivilege->create($curLevel1);
					check_error($RolePrivilege);
					
					if(false === $RolePrivilege->add()){
						//保存日志
						R('Log/adduserlog', array(
							'修改角色权限',
							'修改角色权限失败',
							'失败：系统错误',
							'修改角色权限[角色名称：' . $Role->name . ']在查找原权限时失败：' . get_error($RolePrivilege)
						));
						$RolePrivilege->rollback();
						return_value_json(false, 'msg', '修改角色权限[角色名称：' . $Role->name . ']在添加新权限时失败：' . get_error($RolePrivilege));
					}
						
					$hadLevel1 = true;
				}
				$hasCurLevel1 = true;
				
				//如果还没有当前权限，则添加
				if(!$has) {
					$RolePrivilege->create($condition);
					check_error($RolePrivilege);
					if( false === $RolePrivilege->add() ){
						//保存日志
						R('Log/adduserlog', array(
							'修改角色权限',
							'修改角色权限失败',
							'失败：系统错误',
							'修改角色权限[角色名称：' . $Role->name . ']在添加新权限时失败：' . get_error($RolePrivilege)
						));
						$RolePrivilege->rollback();
						return_value_json(false, 'msg', '修改角色权限[角色名称：' . $Role->name . ']在添加新权限时失败：' . get_error($RolePrivilege));
					}
				}
			}
			elseif($has) {//如果现在没有当前权限，而此前有，则删除
				//先删除当前权限
				if(false === $RolePrivilege->where($condition)->delete()){
					//保存日志
					R('Log/adduserlog', array(
						'修改角色权限',
						'修改角色权限失败',
						'失败：系统错误',
						'修改角色权限[角色名称：' . $Role->name . ']在删除旧权限时失败：' . get_error($RolePrivilege)
					));
					$RolePrivilege->rollback();
					return_value_json(false, 'msg', '修改角色权限[角色名称：' . $Role->name . ']在删除旧权限时失败：' . get_error($RolePrivilege));
				}
				
				//如果现在没有当前一级菜单，而此前有，则删除当前的一级菜单
				if(!$hasCurLevel1 && $hadLevel1) {					
					if(false === $RolePrivilege->where($curLevel1)->delete()) {
						//保存日志
						R('Log/adduserlog', array(
							'修改角色权限',
							'修改角色权限失败',
							'失败：系统错误',
							'修改角色权限[角色名称：' . $Role->name . ']在删除旧一级权限时失败：' . get_error($RolePrivilege)
						));
						$RolePrivilege->rollback();
						return_value_json(false, 'msg', '修改角色权限[角色名称：' . $Role->name . ']在删除旧一级权限时失败：' . get_error($RolePrivilege));
					}
					
					$hadLevel1 = false; //记得现在已经没有当前的一级菜单了，如果后面再有当前的二级菜单，将会添加一级菜单
				}
			}
		}
		
		$RolePrivilege->commit();
		return_value_json(true);
	}
	
	/**
	 * delete操作根据post数据删除一个用户角色，一并删除相关的角色权限。
	 */
	public function delete() {
    	if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
    	
		$roles = json_decode(file_get_contents("php://input"));
		if(!is_array($roles)) {
			$roles = array($roles);
		}

		$Role = M('Role');
		check_error($Role);
		$RolePrivilege = M('RolePrivilege');
		check_error($RolePrivilege);
		
		foreach ( $roles as $role ) {
			//删除角色
			if(false === $Role->where("`id`='" . $role->id . "'")->delete()) {
				//保存日志
				R('Log/adduserlog', array(
					'删除角色',
					'删除角色失败',
					'失败：系统错误',
					'删除角色['.$role->name.']时出错：' + get_error($Role)
				));
				return_value_json(false, 'msg', '删除角色['.$role->name.']时出错：' + get_error($Role));
			}
			
			//删除角色权限
			if(false === $RolePrivilege->where("`role_id`='" . $role->id . "'")->delete()) {
				//保存日志
				R('Log/adduserlog', array(
					'删除角色',
					'删除角色失败',
					'失败：系统错误',
					'删除角色['.$role->name.']的权限时出错：' + get_error($Role)
				));
				return_value_json(false, 'msg', '删除角色['.$role->name.']的权限时出错：' + get_error($Role));
			}
			
			//保存日志
			R('Log/adduserlog', array(
				'删除角色',
				'删除角色成功',
				'成功',
				'角色名称：'.$role->name
			));
		}
		
		return_value_json(true);
	}
}
?>