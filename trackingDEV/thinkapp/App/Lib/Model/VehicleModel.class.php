<?php

class VehicleModel extends Model {
	protected $_validate = array(
		array('number','require', '车牌号码不能为空')
//		,array('number','/^[\u4e00-\u9fa5]{1}[a-zA-Z]{1}[\s|-]{0,1}[A-Za-z0-9]{5}$/', '请输入正确的车牌号码，车牌号码第一个字符是汉字、第二个是字母（其后可以添加空格或者短横线作为连接分割符），后面五个是字母或数字。如果此规则不正确，请联系开发人员。')
	);
	
	protected $_auto = array (
		array('number','strtoupper', Model::MODEL_BOTH, 'function')
	);
}
?>