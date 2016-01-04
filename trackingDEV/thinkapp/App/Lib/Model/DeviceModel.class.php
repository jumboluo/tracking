<?php

class DeviceModel extends Model {
	protected $_validate = array(
		array('type','require', '设备类型不能为空',Model::MUST_VALIDATE, 'regex', Model:: MODEL_BOTH),
		array('type',array("GPS", "手机", "电子铅封"), '设备类型只能是GPS、手机或者电子铅封中的一种，带GPS的手机如果是用GPS进行监控，请设置为GPS。',Model::MUST_VALIDATE, 'in', Model:: MODEL_BOTH),
		array('interval','require', '数据上报频率不能为空',Model::MUST_VALIDATE, 'regex', Model:: MODEL_BOTH),
		array('delay','require', '允许的延迟不能为空',Model::MUST_VALIDATE, 'regex', Model:: MODEL_BOTH),
		array('label', '', '设备标识已经存在。', Model::VALUE_VAILIDATE, 'unique', Model:: MODEL_BOTH)
	);
	
//	protected $_auto = array(
//		array('label', 'generateLabel', Model::MODEL_BOTH, 'callback') //因为我们的回掉函数需要$type，自动完成无法传递这个参数，所有弃用。
//	);
	
	/**
	 * 对非空的设备标识，按系统内定的设备标识规则产生一个标识。
	 * 系统内定的标识规则为：DEV_[GPS|MOB]_[6位的序号]。
	 * @param string $type 请调用前确保$type=='GPS' || $type=='手机'
	 */
	public function generateLabel($label, $type) {
		if(!empty($label)) return $label;
		
		$condition = array(
			'type' => $type
		);
		$seq = $this->where($condition)->count() + 1;
		
		$seq = str_pad($seq, 6, '0', STR_PAD_LEFT);
		
		return 'DEV_'. ($type=='GPS' ? 'GPS_' : 'MOB_') . $seq; 
	}
}
?>