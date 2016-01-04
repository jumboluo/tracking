<?php

class RuleAction extends Action{
	
	public function all() {
		$Rule = M('Rule');
		check_error($Rule);
		
		$total = $Rule->count();
		
		$Rule->join('`path_area` on `path_area`.`id`=`rule`.`path_area_id`')
			->field('`rule`.*, `path_area`.`label` AS `path_area_label`')
			->order('`id` ASC');
		
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) $Rule->limit($limit)->page($page);
		
		$rules = $Rule->select();
//		Log::write("\n".M()->getLastSql(), Log::SQL);
		
		$ids = array();
		foreach ( $rules as $rule ) {
			$ids[] = $rule['id'];
		}
		
		if(!empty($ids)) {
			$RuleTarget = M('RuleTarget');
			check_error($RuleTarget);
			
			$targets = $RuleTarget
					->where('`rule_id` IN ('.implode(',', $ids).')')
					->field(array('id', 'rule_id', 'device_id', 'name', 'target_type', 'target_id',
							"CONCAT(`target_type`, '^', `target_id`, '^', `device_id`, '^', `name`)"
								=>'type_target_device_name'))
					->order('`rule_id` ASC')
					->select();
			check_error($RuleTarget);
			
			foreach ( $targets as $target ) {
				foreach ( $rules as $index => $rule ) {
					if($rule['id']==$target['rule_id']) {
						if(!is_array($rules[$index]['targets'])) $rules[$index]['targets'] = array();
						$rules[$index]['targets'][] = $target;
						break;
					}
				}
			}
			
			
			$AlarmReceiver = M('AlarmReceiver');
			check_error($AlarmReceiver);
			
			$receivers = $AlarmReceiver->join('`user` on `user`.`id`=`alarm_receiver`.`receiver_id`')
					->where('`rule_id` IN ('.implode(',', $ids).')')
					->field("`alarm_receiver`.*, `user`.`name`, `user`.`username`, " .
							"CONCAT(`sms`, `alarm_receiver`.`email`, `window`, `sound`, `receiver_id`) AS `value`")
					->order('`rule_id` ASC')
					->select();
			check_error($AlarmReceiver);
					
			foreach ( $receivers as $receiver ) {
				if(empty($receiver['name'])) $receiver['name'] = $receiver['username'];
				foreach ( $rules as $index => $rule ) {
					if($rule['id']==$receiver['rule_id']) {
						if(!is_array($rules[$index]['receivers'])) $rules[$index]['receivers'] = array();
						$rules[$index]['receivers'][] = $receiver;
						break;
					}
				}
			}
			
			$Model = M();
			$alarms = $Model -> query('SELECT `rule_id`, `id` AS `last_alarm_id`, ' .
					'`start_time` AS `last_alarm_time`,  COUNT(`rule_id`) AS `alarm_count` ' .
					'FROM (SELECT `id`, `rule_id`, `start_time` FROM `alarm` ' .
							'WHERE `rule_id` IN ('.implode(',', $ids).') ' .
							'ORDER BY `id` DESC) AS t ' .
					'GROUP BY `rule_id` ' .
					'ORDER BY `rule_id` ASC');
			check_error($Model);
			
			foreach ( $alarms as $alarm ) {
				foreach ( $rules as $index => $rule ) {
					if($rule['id']==$alarm['rule_id']) {
						$rules[$index]['last_alarm_id'] = $alarm['last_alarm_id'];
						$rules[$index]['last_alarm_time'] = $alarm['last_alarm_time'];
						$rules[$index]['alarm_count'] = $alarm['alarm_count'];
						break;
					}
				}
			}

		}
		
		return_json(true,$total,'rules', $rules);
	}
	
	public function delete() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$rules = json_decode(file_get_contents("php://input"));
		if(!is_array($rules)) {
			$rules = array($rules);
		}
		
		$id = array();
		foreach ( $rules as $rule ) {
			$id[] = $rule->id;
		}
		
		if(count($id)>0) {
			$Rule = M('Rule');
			check_error($Rule);
			if(false === $Rule->where("`id` IN (" . implode(",", $id) . ")")->delete()) {
				return_value_json(false, 'msg', get_error($Rule));
			}
			
			$RuleTarget = M('RuleTarget');
			check_error($RuleTarget);
			if(false === $RuleTarget->where("`rule_id` IN (" . implode(",", $id) . ")")->delete()) {
				return_value_json(false, 'msg', '删除规则对象时出错：'.get_error($RuleTarget));
			}
			
			$AlarmReceiver = M('AlarmReceiver');
			check_error($AlarmReceiver);
			if(false === $AlarmReceiver->where("`rule_id` IN (" . implode(",", $id) . ")")->delete()) {
				return_value_json(false, 'msg', '删除报警接收者时出错：'.get_error($AlarmReceiver));
			}
		}
		
		return_value_json(true);
	}
	
	public function save() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		$Rule = M('Rule');
		check_error($Rule);
		
		$Rule->create();
		check_error($Rule);
		
		$sql = "1";
		$dest = "";
		if($_POST['valid_time']=='given') {//特定时间
			$dest = "特定时间：";
			$hastime = false;
			if(!empty($_POST['valid']) && strtotime($_POST['valid'])!==false) { //有起效时间
				$sql .= " AND NOW()>'{$_POST['valid']}'";
				$dest .= "生效时间：" . $_POST['valid'] . "。";
				$hastime = true;
			}
			if(!empty($_POST['invalid']) && strtotime($_POST['invalid'])!==false) { //有起效时间
				$sql .= " AND NOW()<'{$_POST['invalid']}'";
				$dest .= "失效时间：" . $_POST['invalid'] . "。";
				$hastime = true;
			}
			if(!$hastime) {
				$dest .= "未指定格式正确的生效时间或者失效时间。";
			}
		}
		else if($_POST['valid_time']=='specific') { //指定时间
			$dest = "指定时间：";
			$daysInWeek = array();
			$daysInWeekArray = array('一','二','三','四','五','六','日');
			$daysInWeekDest = array();
			for($i=0; $i<7; $i++) {
				if(!empty($_POST['weekday'.$i])) {
					$daysInWeek[] = $i;
					$daysInWeekDest[] = $daysInWeekArray[$i];
				}
			}
			if(!empty($daysInWeek)) {
				$sql .= " AND WEEKDAY(NOW()) IN (" . implode(",", $daysInWeek) . ")";
				$dest .= "星期" . implode("、", $daysInWeekDest) . "。";
			}
		}
		else { //启用即有效
			$dest = "启用即有效。";
		}
		
		if(!empty($_POST['valid_time_inday'])) {
			$times = explode(",", $_POST['times']);
			$goodTimes = array();
			foreach ( $times as $time ) {
				$fromto = $this->_parseTime($time);
				if($fromto) {
					$goodTimes[] = $time;
					$sql .= " AND (HOUR(NOW())>'{$fromto['fromhour']}' " .
							"	OR (HOUR(NOW())='{$fromto['fromhour']}' " .
							"		AND MINUTE(NOW())>='{$fromto['fromminute']}'))" .
							" AND (HOUR(NOW())<'{$fromto['tohour']}' " .
							"	OR (HOUR(NOW())='{$fromto['tohour']}' " .
							"		AND MINUTE(NOW())<='{$fromto['tominute']}'))";
				}
			}
			
			if(!empty($goodTimes)) {
				$dest .= "仅在以下时间有效：" . implode("，", $goodTimes);
			}
		}
		$Rule->valid_time_discription = $dest;
		$Rule->valid_time_sql = $sql;
		
		$id = 0;
		if($_POST['action'] == 'add') {
			$id = $Rule->add();
		}
		else {
			$Rule->save();
			$id = $_POST['id'] + 0;
		}
		check_error($Rule);
		
		$this->_updateRuleTarget($id, $_POST['selected_targets']);
		$this->_updateAlarmReceiver($id, $_POST['selected_receivers']);
		
		return_value_json(true);
	}
	
	private function _updateRuleTarget($rule_id, $selected_targets) {
		$RuleTarget = M('RuleTarget');
		check_error($RuleTarget);

		//先删除原来的
		$RuleTarget->where("`rule_id`='{$rule_id}'")->delete();
		check_error($RuleTarget);
		
		$targets = explode(",", $selected_targets);
		
		foreach ( $targets as $index => $type_target_device_name ) {
			$target = $this->_parseRuleTarget($type_target_device_name);
			if($target) {
				$target['rule_id'] = $rule_id;
				$RuleTarget->add($target);
				check_error($RuleTarget);
			}
		}
	}
	
	/**
	 * 把“类型^xx^名称”字符串分析称数组array("target_type"=>"类型", "target_id"=>xx, "name"=>"名称")
	 */
	private function _parseRuleTarget($type_target_device_name) {
		$type = substr($type_target_device_name, 0, strpos($type_target_device_name, '^'));
		$left = substr(strstr($type_target_device_name, '^'), 1);
		$target = substr($left, 0, strpos($left, '^'));
		$left = substr(strstr($left, '^'), 1);
		$device = substr($left, 0, strpos($left, '^'));
		$name = substr(strstr($left, '^'), 1);
		return (empty($type)||empty($name)) ? null : array(
			"target_type" 	=> $type,
			"target_id" 	=>$target,
			"device_id"		=>$device,
			"name"			=>$name
		);
	}
	
	/**
	 * 把"从XX:XX到YY:YY"的字符串分析成数组array("fromtime"=>"XX:XX", "totime"=>"YY:YY")
	 */
	private function _parseTime($time) {
		if(strlen($time) != (10 + strlen('从到')) ) return null;
		
		$fromtime = substr(strstr($time,'从'), strlen('从'), 5);
		$totime = substr(strstr($time,'到'), strlen('到'), 5);;
		$fromhour = substr($fromtime, 0, 2) + 0;
		$fromminute = substr($fromtime, 3, 2) + 0;
		$tohour = substr($totime, 0, 2) + 0;
		$tominute = substr($totime, 3, 2) + 0;
		return array(
			"fromtime" => $fromtime,
			"fromhour" => $fromhour,
			"fromminute" => $fromminute,
			"totime" => $totime,
			"tohour" => $tohour,
			"tominute" => $tominute
		);
	}
	
	private function _updateAlarmReceiver($rule_id, $receiveInfo) {
		$AlarmReceiver = M('AlarmReceiver');
		check_error($AlarmReceiver);

		//先删除原来的
		$AlarmReceiver->where("`rule_id`='{$rule_id}'")->delete();
		check_error($AlarmReceiver);
		
		$receivers = explode(",", $receiveInfo);
		
		foreach ( $receivers as $index => $receiver ) {
			if(strlen($receiver)>4) {
				$data = array(
					'sms' 			=> substr($receiver, 0, 1),
					'email' 		=> substr($receiver, 1, 1),
					'window'		=> substr($receiver, 2, 1),
					'sound' 		=> substr($receiver, 3, 1),
					'receiver_id' 	=> substr($receiver, 4),
					'rule_id'		=> $rule_id
				);
				$AlarmReceiver->add($data);
				check_error($AlarmReceiver);
			}
		}
	}
}
?>