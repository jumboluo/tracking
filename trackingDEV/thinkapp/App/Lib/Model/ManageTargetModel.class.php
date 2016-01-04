<?php
class ManageTargetModel extends Model {
	public function getTargetsQuery(){
		$user = session('user');
		if(empty($user)) return " (0) ";
		$userId = $user['userId'];
		if(empty($userId)) return " (0) ";
		
		if($userId == 1) return " (1) ";
		$re = " (0";
		$targets = $this->where(array('user_id'=>$userId))->select();
		foreach ($targets as $target) {
			if($target['target_type']=='分组') {
				$re .= " OR `device`.`department_id`='{$target['target_id']}'";
			}
			else if($target['target_type']=='设备') {
				$re .= " OR `device`.`id`='{$target['target_id']}'";
			}
			else {
				$re .= " OR (`device`.`target_id`='{$target['target_id']}' AND `device`.`target_type`='{$target['target_type']}')";
			}
		}
		$re .= ") ";
		return $re;
	}
}
?>