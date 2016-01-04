<?php
/**
 * 登陆模块
 * 操作：
 * check	检查是否登陆，返回json
 */
class AuthenticateAction extends Action {

	public function _initialize() {
	}
	
	/**
	 * 检查用户是否已经登录，如果已经登录，返回用户信息
	 * 附带返回系统服务器时间戳
	 */
	public function check() {
		Log::write(print_r($_SESSION, true), Log::INFO);
		if(session('logined')) {
			//设置返回数据
			$data = array();
			$data['serverTime'] = time();
			$data['user'] = session('user');
			$data['menu'] = session('menu');
			
			
			return_value_json(true, 'data', $data);
		}
		else {
			return_value_json(false);
		}
	}
	
	/**
	 * 登陆模块的入口：转到登陆操作
	 */
	public function index() {
		return $this->login();
	}
	
	/**
	 * 登陆，如果失败，返回失败原因（用户名或者密码不正确），如果成功，返回用户信息，
	 * 附带返回系统服务器时间戳
	 */
	public function login() {
		//查user表
		$User = M('User');
		check_error($User);
		$user = $User
			->field(array('id'=>'userId', 'username'=>'userName', 'name', 'role_id'=>'roleId', 'role'))
			->where(array('username'=>safe_post_param('username'), 
						'_string'=>"`password`=MD5('" . safe_post_param('password') . "')"
						))
			->find();
			
		if(!empty($user)) {
			//根据权限查菜单
			$Menu = M('Menu');
			check_error($Menu);
			$menu = $Menu
				->join('`role_privilege` on `menu`.`id`=`role_privilege`.`menu_id`')
				->join('`user` on `user`.`role_id`=`role_privilege`.`role_id`')
				->field(array('`menu`.`id`', 'level', 'label', 'icon', 'widget', 'show', 'big_icon'))
				->where("`user`.`id`='".$user['userId']."'")
				->order('`level` ASC')
				->select();
			check_error($Menu);
			
			//保存session
			session('logined', true);
			session('user', $user);
			session('menu', $menu);

			//设置返回数据
			$data = array();
			$data['serverTime'] = time();
			$data['user'] = $user;
			$data['menu'] = $menu;
			
			//保存日志
			R('Log/adduserlog', array(
				'登录',
				'登录成功',
				'成功'
			));
			
			//返回结果：用户数据+服务器时间
			return_value_json(true, 'data', $data);
		}
		else {
			//保存日志
			R('Log/adduserlog', array(
				'登录',
				'登录失败：用户名或者密码不正确' ,
				'失败：权限不够',
				'用户名：' . safe_post_param('username')
			));
			return_value_json(false, 'msg', '用户名或者密码不正确');
		}
	}
	
	public function logout() {
		$url = substr(U('Authenticate'), 0, stripos(U('Authenticate'),'thinkapp'));
		Log::write("url=".$url, Log::INFO);
		header( "Location: " . $url );
		//保存日志
		R('Log/adduserlog', array(
			'登出',
			'登出成功' ,
			'成功'
		));
		
		session(null);
		session('[destroy]');
		session('[regenerate]');
		$url = substr(U('Authenticate'), 0, stripos(U('Authenticate'),'thinkapp'));
		Log::write("url=".$url, Log::INFO);
		header( "Location: " . $url );
	}
	
	public function test() {
		header( "Location: abc"  );
	}
}
?>