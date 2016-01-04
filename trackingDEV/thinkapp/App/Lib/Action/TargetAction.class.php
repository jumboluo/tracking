<?php

class TargetAction extends Action{
	
	public function get() {
		$type = $_REQUEST['type'];
		$departmentId = $_REQUEST['departmentId'] + 0;
		
		if(!in_array($type, array('车辆', '人员', '集装箱', '班列', '设备'))) {
			return_json(true, null, 'targets', array());
		};
		
		$condition = '1';
		$condition .= ($type!='设备') ? " AND `device`.`target_type`='{$type}' AND `device`.`target_id`<>0" : '';
		$condition .= empty($departmentId) ? '' : " AND `device`.`department_id`='{$departmentId}'";

		$Device = M('Device');
		check_error($Device);
		
		if($type=='车辆'){
			$Device->join('`vehicle` ON `vehicle`.`id`=`device`.`target_id`')
				->field(array("CONCAT('车辆')"=>'target_type', 'number'=>'name', 'target_id', '`device`.`id`'=>'device_id',
							"CONCAT('车辆^', `target_id`, '^', `device`.`id`, '^', `number`)"=>'type_target_device_name'));
		}
		else if($type=='人员') {
			$Device->join('`employee` ON `employee`.`id`=`device`.`target_id`')
				->field(array("CONCAT('人员')"=>'target_type', 'name', 'target_id', '`device`.`id`'=>'device_id',
							"CONCAT('人员^', `target_id`, '^', `device`.`id`, '^', `name`)"=>'type_target_device_name'));
		}
		else if($type=='集装箱') {
			$Device->join('`container` ON `container`.`id`=`device`.`target_id`')
				->field(array("CONCAT('集装箱')"=>'target_type', 'number'=>'name', 'target_id', '`device`.`id`'=>'device_id',
							"CONCAT('集装箱^', `target_id`, '^', `device`.`id`, '^', `number`)"=>'type_target_device_name'));
		}
		else if($type=='班列') {
			$Device->join('`train` ON `train`.`id`=`device`.`target_id`')
				->field(array("CONCAT('班列')"=>'target_type', 'number'=>'name', 'target_id', '`device`.`id`'=>'device_id',
							"CONCAT('班列^', `target_id`, '^', `device`.`id`, '^', `number`)"=>'type_target_device_name'));
		}
		else if($type=='设备') {
			$Device->field(array("CONCAT('设备')"=>'target_type', 'label'=>'name', 'target_id', '`device`.`id`'=>'device_id',
							"CONCAT('设备^', `target_id`, '^', `device`.`id`, '^', `label`)"=>'type_target_device_name'));
		}
		
		$targets = $Device->where($condition)->order('`device`.`id` ASC')->select();
		check_error($Device);
//		Log::write("\n".M()->getLastSql(), Log::SQL);
		
		return_json(true, null, 'targets', $targets);
	}
	
	public function getmanage() {
		$type = $_REQUEST['type'];
		$departmentId = $_REQUEST['departmentId'] + 0;
		
		if(!in_array($type, array('车辆', '人员', '集装箱', '班列', '设备'))) {
			return_json(true, null, 'targets', array());
		};
		
		$condition = '1';
		$condition .= empty($departmentId) ? '' : " AND `department_id`='{$departmentId}'";

		$DB = M();
		check_error($DB);
		
		if($type=='车辆'){
			$targets = $DB->query("SELECT `id` AS `target_id`, '车辆' AS `target_type`, `number` AS `target_name`, " .
					"CONCAT('车辆^', `id`, '^', `number`) AS `type_id_name` FROM `vehicle` WHERE " . $condition .
					" ORDER BY `sequence` ASC");
		}
		else if($type=='人员') {
			$targets = $DB->query("SELECT `id` AS `target_id`, '人员' AS `target_type`, `name` AS `target_name`, " .
					"CONCAT('人员^', `id`, '^', `name`) AS `type_id_name` FROM `employee` WHERE " . $condition .
					" ORDER BY `sequence` ASC");
		}
		else if($type=='集装箱') {
			$targets = $DB->query("SELECT `id` AS `target_id`, '集装箱' AS `target_type`, `number` AS `target_name`, " .
					"CONCAT('集装箱^', `id`, '^', `number`) AS `type_id_name` FROM `container` WHERE " . $condition .
					" ORDER BY `id` ASC");
		}
		else if($type=='班列') {
			$targets = $DB->query("SELECT `id` AS `target_id`, '班列' AS `target_type`, `number` AS `target_name`, " .
					"CONCAT('班列^', `id`, '^', `number`) AS `type_id_name` FROM `train` WHERE " . $condition .
					" ORDER BY `id` ASC");
		}
		else if($type=='设备') {
			$targets = $DB->query("SELECT `id` AS `target_id`, '设备' AS `target_type`, `label` AS `target_name`, " .
					"CONCAT('设备^', `id`, '^', `label`) AS `type_id_name` FROM `device` WHERE " . $condition .
					" ORDER BY `id` ASC");
		}
		check_error($DB);
//		Log::write("\n".M()->getLastSql(), Log::SQL);
		
		return_json(true, null, 'targets', $targets);
	}
}
?>