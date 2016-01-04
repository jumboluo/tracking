<?php

class UserModel extends Model {
	protected $_validate = array(
		array('username','require', '用户名称不能为空', Model::MUST_VALIDATE, 'regex', Model:: MODEL_BOTH),
		array('role_id','require', '必须为用户选择一个角色', Model::MUST_VALIDATE, 'regex', Model:: MODEL_BOTH),
		array('role','require', '必须为用户选择一个角色', Model::MUST_VALIDATE, 'regex', Model:: MODEL_BOTH),
		array('password','require', '登陆密码不能为空', Model::MUST_VALIDATE, 'regex', Model:: MODEL_INSERT)
	);
	
	protected $_auto = array (
		array('password','md5', Model::MODEL_INSERT, 'function')
	);
}
?>