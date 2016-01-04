<?php

class DataimportAction extends Action{
	private static $refreshcookiefile = 'tracking_data_import_refresh_cookie.txt';
	private static $tempcookiefile = 'tracking_data_import_temp_cookie.txt';
	private static $base_url = 'http://t.sinosafe.net/';
	
	/**
	 * 登录系统，并启动刷新线程
	 */
	public function login($returnOnSuccess=true) {
		$setting = $this->_getSetting();
		if(empty($setting)) return_value_json(false, 'msg', '系统错误：数据导入设置为空');
		
		$re = $this->_curl_request(
			self::$base_url . 'bll/doLogin.aspx',
			array(
				's' => date('D M m Y H:i:s') . ' GMT 0800',
				'username' => $setting['username'],
				'userpwd' => $setting['password'],
				'system' => 0
			),
			self::$base_url . 'login.aspx',
			false,
			self::$tempcookiefile
		);
		
		if($re['success'] && $re['response']==='1') {
			$index = $this->_curl_request(
				self::$base_url . 'index.aspx',
				null,
				self::$base_url . 'login.aspx',
				false,
				self::$tempcookiefile
			);
			
			$EVENTVALIDATION_pattern ='/<input type="hidden" name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="(.*)" \/>/'; 
			preg_match_all($EVENTVALIDATION_pattern	, $index['response'], $matches);
			if(!empty($matches) && !empty($matches[1])) {
				$setting['EVENTVALIDATION'] = $matches[1][0];
			}
			
			$VIEWSTATE_pattern ='/<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="(.*)" \/>/'; 
			preg_match_all($VIEWSTATE_pattern, $index['response'], $matches2);
			if(!empty($matches2) && !empty($matches2[1])) {
				$setting['VIEWSTATE'] = $matches2[1][0];
			}
			
			$cishu = $this->_curl_request(
				self::$base_url . 'bll/doIndex.aspx',
				array(
					's' => date('D M m Y H:i:s') . ' GMT 0800',
					'cishu' => 0
				),
				self::$base_url . 'index.aspx',
				false,
				self::$tempcookiefile
			);
			if($cishu['success'] && !empty($cishu['response'])) {
				$setting['countleft'] = $cishu['response'] + 0;
			}
			
			$setting['logined'] = 1;
			$setting['last_login'] = date('Y-m-d H:i:s');
			
			$DataImport = M('DataImport');
			check_error($DataImport);
			
			if(false===$DataImport->where('1')->save($setting)){
				return_value_json(false, 'msg', get_error($DataImport));
			}
			
			$this->_startRefreshThread();
			
			if($returnOnSuccess) return_value_json(true);
		}
		else {
			return_value_json(false, 'msg', '登录失败:'.$re['response']);
		}
	}
	
	public function logout() {
		$setting = $this->_getSetting();
		
		$re = $this->_curl_request(
			self::$base_url . 'index.aspx',
			array(
				'__EVENTTARGET' => 'exit',
				'__EVENTARGUMENT' => '',
				'__VIEWSTATE'=>$setting['VIEWSTATE'],
				'__EVENTVALIDATION'=> $setting['EVENTVALIDATION']
			),
			self::$base_url . 'index.aspx',
			true,
			self::$tempcookiefile
		);
		
		M()->execute("UPDATE `data_import` SET `logined`='0'");
		
		return_value_json(true);
	}
	
	public function getsetting() {
		$setting = $this->_getSetting();
		empty($setting) ? 
			return_value_json(false, 'msg', '系统错误：数据导入设置为空')
			: return_value_json(true, 'setting', $setting);
	}
	
	//////////////分组信息/////////////////////////
	public function group() {
		$DataImportGroup = M('DataImportGroup');
		check_error($DataImportGroup);
		
		$total = $DataImportGroup->count();
		
		$DataImportGroup->order('`id` ASC');
		
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) $DataImportGroup->limit($limit)->page($page);
		
		$groups = $DataImportGroup->select();
		
		return_json(true,$total,'groups', $groups);
	}
	
	/**
	 * 从网站抓取所有分组信息，并保存在数据库里
	 */
	public function getgroups() {
		set_time_limit(0);
		$setting = $this->_getSetting();
		
		if(!$setting['logined']) {
			$this->login(false);
		}
			
		M()->execute("UPDATE `data_import` SET `logined`='1', `last_login`='".date('Y-m-d H:i:s')."'");
		M()->execute('TRUNCATE `data_import_group`');
		
		$pageHtml = $this->_curl_request(
			self::$base_url . 'bll/doTeam.aspx',
			array(
				's' => date('D M m Y H:i:s') . ' GMT 0800',
				'page' => 0,
				'pageindex' => 1,
				'team' => 0,
				'where' => ''
			),
			self::$base_url . 'team.aspx',
			false,
			self::$tempcookiefile
		);
		
		preg_match_all ("/<span class='table_page_index' onclick='getList\((\d+)\);' >末页<\/span>/", $pageHtml['response'], $pages);
		
		$pageCount =(!empty($pages) && !empty($pages[1])) ? $pages[1][0] : 1;
		
		for($p=1; $p<=$pageCount; $p++) {
			$groupsHtml = $this->_curl_request(
				self::$base_url . 'bll/doTeam.aspx',
				array(
					's' => date('D M m Y H:i:s') . ' GMT 0800',
					'list' => 0,
					'pageindex' => $p,
					'team' => 0,
					'where' => ''
				),
				self::$base_url . 'team.aspx',
				false,
				self::$tempcookiefile
			);
			
			
			$seq_pattern ="/<div style='width: 30px;' class='table_cell'>(.*)<\/div>/"; 
			preg_match_all($seq_pattern	, $groupsHtml['response'], $seqs);
			
			$num_pattern = "/<div style='width: 80px; ' class='table_cell' >(\d*)<\/div>/";
			preg_match_all($num_pattern	, $groupsHtml['response'], $numbers);
			
			$name_pattern = "/<div style='width: 250px;' class='table_cell'><input type='text' id='txtname(\d+)' style='width:230px; ' value='(.*)'\/><\/div>/";
			preg_match_all($name_pattern	, $groupsHtml['response'], $names);
			
			$len = min(count($seqs[1]), count($numbers[1]), count($names[2]));
			for($i=0; $i<$len; $i++) {
				$this->_addGroup($seqs[1][$i], $names[2][$i], $numbers[1][$i]);
			}
		}
		return_value_json(true);
	}
	
	private function _addGroup($sequence, $name, $number) {
		$data = array(
			'name' => $name,
			'sequence' => $sequence,
			'number' => $number
		);
		
		$DataImportGroup = M('DataImportGroup');
		check_error($DataImportGroup);
		
		$DataImportGroup->add($data);
		check_error($DataImportGroup);
	}
	
	public function importselectedgroups() {
		$ids = $this->_request('ids');
		if(empty($ids)) return_value_json(false, 'msg', '系统错误：提交的分组id为空');
		
		$DataImportGroup = M('DataImportGroup');
		check_error($DataImportGroup);
		
		$groups = $DataImportGroup->where('`id` IN ('.$ids.')')->order('`id` ASC')->select();
		foreach ( $groups as $group ) {
			$this->_importGroup($group);
		}
		
		return_value_json(true);
	}
	
	public function importallgroups() {
		$DataImportGroup = M('DataImportGroup');
		check_error($DataImportGroup);
		
		$groups = $DataImportGroup->order('`id` ASC')->select();
		foreach ( $groups as $group ) {
			$this->_importGroup($group);
		}
		
		return_value_json(true);
	}
	
	private function _importGroup($group) {
		$data = array(
			'name' => $group['name'],
			'sequence' => $group['sequence'],
			'memo' => '原系统组编号：' . $group['number']
		);
		
		$Department = M('Department');
		check_error($Department);
		
		$department = $Department->where("`memo` LIKE '%原系统组编号：".$group['number']."%'")->select();
		if(!empty($department)) {
			$Department->where("`id`='{$department[0]['id']}'")->save($data);
		}
		else {
			$id = $Department->add($data);
		}
		check_error($Department);
	}
	
	///////////////////////定位号码//////////////////////////
	public function number() {
		$DataImportNumber = M('DataImportNumber');
		check_error($DataImportNumber);
		
		$total = $DataImportNumber->count();
		
		$DataImportNumber->order('`id` ASC');
		
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) $DataImportNumber->limit($limit)->page($page);
		
		$numbers = $DataImportNumber->select();
		
		return_json(true,$total,'numbers', $numbers);
	}
	
	/**
	 * 从网站抓取所有分组信息，并保存在数据库里
	 */
	public function getnumbers() {
		set_time_limit(0);
		$setting = $this->_getSetting();
		
		if(!$setting['logined']) {
			$this->login(false);
		}
			
		M()->execute("UPDATE `data_import` SET `logined`='1', `last_login`='".date('Y-m-d H:i:s')."'");
		M()->execute('TRUNCATE `data_import_number`');
		
		$pageHtml = $this->_curl_request(
			self::$base_url . 'bll/doChanpinInfo.aspx',
			array(
				's' => date('D M m Y H:i:s') . ' GMT 0800',
				'page' => 0,
				'pageindex' => 1,
				'where' => ''
			),
			self::$base_url . 'ChanpinInfo.aspx',
			false,
			self::$tempcookiefile
		);
		
		preg_match_all ("/<span class='table_page_index' onclick='getList\((\d+)\);' >末页<\/span>/", $pageHtml['response'], $pages);
		
		$pageCount =(!empty($pages) && !empty($pages[1])) ? $pages[1][0] : 1;
		
		for($p=1; $p<=$pageCount; $p++) {
			$groupsHtml = $this->_curl_request(
				self::$base_url . 'bll/doChanpinInfo.aspx',
				array(
					's' => date('D M m Y H:i:s') . ' GMT 0800',
					'list' => 0,
					'pageindex' => $p,
					'team' => 0,
					'where' => '',
					'sort' => ''
				),
				self::$base_url . 'ChanpinInfo.aspx',
				false,
				self::$tempcookiefile
			);
			
			
			$num_pattern = "/<div style='width: 80px;' class='table_cell'>(\d*)<\/div>/";
			preg_match_all($num_pattern	, $groupsHtml['response'], $numbers);
			
			$group_pattern ="/<div style='width: 130px;'  class='table_cell' title='(.*)' >(.*)<\/div>/"; 
			preg_match_all($group_pattern	, $groupsHtml['response'], $groups);
			
			$name_pattern = "/<div style='width: 100px;' class='table_cell'>(.*)<\/div>/";
			preg_match_all($name_pattern	, $groupsHtml['response'], $names);
			
			$len = min(count($numbers[1]), count($groups[1]), count($names[1])/2);
			for($i=0; $i<$len; $i++) {
				$this->_addNumber($numbers[1][$i], $names[1][$i*2], $groups[1][$i]);
			}
		}
		
		return_value_json(true);
	}
	
	private function _addNumber($number, $name, $group) {
		$data = array(
			'name' => $name,
			'group' => $group,
			'number' => $number,
			'last_time' => date('Y-m-d H:i:s', time()-3600*24)
		);
		
		$DataImportNumber = M('DataImportNumber');
		check_error($DataImportNumber);
		
		$DataImportNumber->add($data);
		check_error($DataImportNumber);
	}
	
	
	public function importselectednumbers() {
		$ids = $this->_request('ids');
		if(empty($ids)) return_value_json(false, 'msg', '系统错误：提交的分组id为空');
		
		$DataImportNumber = M('DataImportNumber');
		check_error($DataImportNumber);
		
		$numbers = $DataImportNumber->where('`id` IN ('.$ids.')')->order('`id` ASC')->select();
		foreach ( $numbers as $number ) {
			$this->_importNumber($number);
		}
		
		return_value_json(true);
	}
	
	public function importallnumbers() {
		$DataImportNumber = M('DataImportNumber');
		check_error($DataImportNumber);
		
		$numbers = $DataImportNumber->order('`id` ASC')->select();
		foreach ( $numbers as $number ) {
			$this->_importNumber($number);
		}
		
		return_value_json(true);
	}
	
	private function _importNumber($number) {
		$Department = M('Department');
		check_error($Department);
		
		$department = $Department->where("`name`='".$number['group']."'")->find();
		if(empty($department)) 
			return_value_json(false, 'msg', '在系统里找不到分组['.$number['group'].']的信息，请先导入分组信息');
		
		$employeeData = array(
			'name' => $number['name'],
			'department_id'  => $department['id'],
			'memo' => '手机号码：' . $number['number']
		);
		
		$Employee = M('Employee');
		check_error($Employee);
		
		$employee = $Employee->where("`memo` LIKE '%手机号码：".$number['number']."%'")->find();
		if(!empty($employee)) {
			$Employee->where("`id`='{$employee['id']}'")->save($employeeData);
			$employeeId = $employee['id'];
		}
		else {
			$employeeId = $Employee->add($employeeData);
		}
		check_error($Employee);
		
		$deviceData = array(
			'type' => '手机',
			'label' => 'MOB_' . $number['number'],
			'interval' => 60,
			'delay' => 60,
			'department_id' => $department['id'],
			'target_type' => '人员',
			'target_id' => $employeeId,
			'target_name' => $number['name'],
			'weekday0' => 1,
			'weekday1' => 1,
			'weekday2' => 1,
			'weekday3' => 1,
			'weekday4' => 1,
			'weekday5' => 1,
			'weekday6' => 1,
			'mobile_num' => $number['number']
		);
		
		$Device = M('Device');
		check_error($Device);
		
		$device = $Device->where("`mobile_num`='".$number['number']."'")->find();
		if(!empty($device)) {
			$Device->where("`id`='{$device['id']}'")->save($deviceData);
		}
		else {
			$id = $Device->add($deviceData);
		}
		check_error($Device);
	}
	
	
	/////////////////////定时设置/////////////////////////
	public function timing() {
		$DataImportTiming = M('DataImportTiming');
		check_error($DataImportTiming);
		
		$total = $DataImportTiming->count();
		
		$DataImportTiming->order('`id` ASC');
		
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) $DataImportTiming->limit($limit)->page($page);
		
		$timings = $DataImportTiming->select();
		
		return_json(true,$total,'timings', $timings);
	}
	
	/**
	 * 从网站抓取所有定时设置，并保存在数据库里
	 */
	public function gettimings() {
		set_time_limit(0);
		$setting = $this->_getSetting();
		
		if(!$setting['logined']) {
			$this->login(false);
		}
			
		M()->execute("UPDATE `data_import` SET `logined`='1', `last_login`='".date('Y-m-d H:i:s')."'");
		M()->execute('TRUNCATE `data_import_timing`');
		
		$pageHtml = $this->_curl_request(
			self::$base_url . 'bll/doTime.aspx',
			array(
				's' => date('D M m Y H:i:s') . ' GMT 0800',
				'page' => 0,
				'pageindex' => 1,
				'where' => ''
			),
			self::$base_url . 'systime.aspx',
			false,
			self::$tempcookiefile
		);
		
		preg_match_all ("/<span class='table_page_index' onclick='getList\((\d+)\);' >末页<\/span>/", $pageHtml['response'], $pages);
		
		$pageCount =(!empty($pages) && !empty($pages[1])) ? $pages[1][0] : 1;
		
		for($p=1; $p<=$pageCount; $p++) {
			$groupsHtml = $this->_curl_request(
				self::$base_url . 'bll/doTime.aspx',
				array(
					's' => date('D M m Y H:i:s') . ' GMT 0800',
					'list' => 0,
					'pageindex' => $p,
					'team' => 0,
					'where' => '',
					'sort' => ''
				),
				self::$base_url . 'ChanpinInfo.aspx',
				false,
				self::$tempcookiefile
			);
			
			
			$num_pattern = "/<div style='width: 30px;' class='table_cell'><input type='checkbox' id='chk(\d*)' \/><\/div>/";
			preg_match_all($num_pattern	, $groupsHtml['response'], $numbers);
			
			$name_pattern = "/<div style='width: 115px;' class='table_cell'>(.*)<\/div>/";
			preg_match_all($name_pattern	, $groupsHtml['response'], $names);
			
			$len = min(count($numbers[1]), count($names[1]));
			$keys = array('interval', 'weekday0', 'weekday1', 'weekday2', 'weekday3', 'weekday4', 'weekday5', 'weekday6', 'starttime', 'endtime', 'number');
			for($i=0; $i<$len; $i++) {
				$updateTimeHtml = $this->_curl_request(
					self::$base_url . 'bll/doUpdatetime.aspx',
					array(
						's' => date('D M m Y H:i:s') . ' GMT 0800',
						'list' => 0,
						'pageindex' => 1,
						'team' => 0,
						'where' => '',
						'sort' => '',
						'id' => $numbers[1][$i]
					),
					self::$base_url . 'UpdateTime.aspx?id=' . $numbers[1][$i],
					false,
					self::$tempcookiefile
				);
				
				$id_pattern = "/<a href='cycleUpdate.aspx\?id=(\d+)' class='table_row_link'>修改<\/a>/";
				preg_match_all($id_pattern	, $updateTimeHtml['response'], $id);
				
				$timingData = array();
				if(!empty($id[1]) && !empty($id[1][0])) {
					$dataHtml = $this->_curl_request(
						self::$base_url . 'bll/doCycleupdate.aspx',
						array(
							's' => date('D M m Y H:i:s') . ' GMT 0800',
							'update' => 0,
							'id' => $id[1][0]
						),
						self::$base_url . 'cycleUpdate.aspx?id=' . $id[1][0],
						false,
						self::$tempcookiefile
					);
					
					if(!empty($dataHtml['response'])) {
						$timingData = array_combine($keys, explode(",", $dataHtml['response']));
					}
					if(empty($timingData)) $timingData = array();
				}
				$this->_addTiming($numbers[1][$i], $names[1][$i], $timingData);
			}
		}
		
		return_value_json(true);
	}
	
	private function _addTiming($number, $name, $timing) {
		$data = array_merge(array(
			'name' => $name,
			'number' => $number
		), $timing);
		
		$DataImportTiming = M('DataImportTiming');
		check_error($DataImportTiming);
		
		$DataImportTiming->add($data);
		check_error($DataImportTiming);
	}
	
	
	public function importselectedtimings() {
		$ids = $this->_request('ids');
		if(empty($ids)) return_value_json(false, 'msg', '系统错误：提交的分组id为空');
		
		$DataImportTiming = M('DataImportTiming');
		check_error($DataImportTiming);
		
		$timings = $DataImportTiming->where('`id` IN ('.$ids.')')->order('`id` ASC')->select();
		foreach ( $timings as $timing ) {
			$this->_importTiming($timing);
		}
		
		return_value_json(true);
	}
	
	public function importalltimings() {
//		$DataImportTiming = M('DataImportTiming');
//		check_error($DataImportTiming);
//		
//		$timings = $DataImportTiming->order('`id` ASC')->select();
//		foreach ( $timings as $timing ) {
//			$this->_importTiming($timing);
//		}

		M()->execute("UPDATE `device` INNER JOIN `data_import_timing` ON `device`.`mobile_num`=`data_import_timing`.`number` " .
				"SET `device`.`starttime`=`data_import_timing`.`starttime`, " .
				"`device`.`endtime`=`data_import_timing`.`endtime`");
				
		M()->execute("UPDATE `device` INNER JOIN `data_import_timing` ON `device`.`mobile_num`=`data_import_timing`.`number` " .
				"SET `device`.`weekday0`=`data_import_timing`.`weekday0` " .
				"WHERE `data_import_timing`.`weekday0` IS NOT NULL");
				
		M()->execute("UPDATE `device` INNER JOIN `data_import_timing` ON `device`.`mobile_num`=`data_import_timing`.`number` " .
				"SET `device`.`weekday1`=`data_import_timing`.`weekday1` " .
				"WHERE `data_import_timing`.`weekday1` IS NOT NULL");
				
		M()->execute("UPDATE `device` INNER JOIN `data_import_timing` ON `device`.`mobile_num`=`data_import_timing`.`number` " .
				"SET `device`.`weekday2`=`data_import_timing`.`weekday2` " .
				"WHERE `data_import_timing`.`weekday2` IS NOT NULL");
				
		M()->execute("UPDATE `device` INNER JOIN `data_import_timing` ON `device`.`mobile_num`=`data_import_timing`.`number` " .
				"SET `device`.`weekday3`=`data_import_timing`.`weekday3` " .
				"WHERE `data_import_timing`.`weekday3` IS NOT NULL");
				
		M()->execute("UPDATE `device` INNER JOIN `data_import_timing` ON `device`.`mobile_num`=`data_import_timing`.`number` " .
				"SET `device`.`weekday4`=`data_import_timing`.`weekday4` " .
				"WHERE `data_import_timing`.`weekday4` IS NOT NULL");
				
		M()->execute("UPDATE `device` INNER JOIN `data_import_timing` ON `device`.`mobile_num`=`data_import_timing`.`number` " .
				"SET `device`.`weekday5`=`data_import_timing`.`weekday5` " .
				"WHERE `data_import_timing`.`weekday5` IS NOT NULL");
				
		M()->execute("UPDATE `device` INNER JOIN `data_import_timing` ON `device`.`mobile_num`=`data_import_timing`.`number` " .
				"SET `device`.`weekday6`=`data_import_timing`.`weekday6` " .
				"WHERE `data_import_timing`.`weekday6` IS NOT NULL");
		
		M()->execute("UPDATE `device` INNER JOIN `data_import_timing` ON `device`.`mobile_num`=`data_import_timing`.`number` " .
				"SET `device`.`interval`=`data_import_timing`.`interval`, " .
				"`device`.`delay`=`data_import_timing`.`interval` " .
				"WHERE `data_import_timing`.`interval` IS NOT NULL");
				
		return_value_json(true);
	}
	
	private function _importTiming($timing) {
		$Device = M('Device');
		check_error($Device);
		
		unset($timing['id']);
		unset($timing['name']);
		$number = $timing['number'];
		unset($timing['number']);
		
		$Device->where("`mobile_num`='{$number}'")->save($timing);
		check_error($Device);
	}
	
	
	public function test() {
		$location_pattern = "/jierrorlat=(\d+\.?\d*);jierrorlon=(\d+\.?\d*);lat=(\d+\.?\d*);lon=(\d+\.?\d*);gpsdatetime=(0|\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2});note=([^;]*)/";
		$str = 'jierrorlat=39.90824;jierrorlon=116.47303;lat=39.90824;lon=116.47303;gpsdatetime=2012-06-10 12:51:20;note=北京市朝阳区,大望桥-建国路交叉路口,百事和大厦,大中电器(大望桥店)附近
;gpsdatetime=2012-06-10 12:51:20

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" >
<head><title>
	无标题页
</title></head>

</html>';
		preg_match_all($location_pattern, $str, $matches);
		print_r($matches);
		
		echo "<br>===========================================<br>";
		$str2 = 'jierrorlat=0;jierrorlon=0;lat=0;lon=0;gpsdatetime=0;note=瀹氫綅澶辫触锛氱敤鎴峰叧鏈烘垨涓嶅湪鏈嶅姟鍖;gpsdatetime=0

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" >
<head><title>
	鏃犳爣棰橀〉
</title></head>

</html>
';
		preg_match_all($location_pattern, $str2, $matches);
		print_r($matches);
		echo "<br>===========================================<br>";
		echo U('Interface/gps');
		echo "<br>".$_SERVER['HTTP_HOST'];
	}
	
	//////////////////////////刷新///////////////////////////////
	public function startrefresh() {
		M()->execute("UPDATE `data_import` SET `refresh_on`='1'");
		$this->_startRefreshThread();
		sleep(3);
		return_value_json(true);
	}
	
	public function stoprefresh() {
		M()->execute("UPDATE `data_import` SET `refresh_on`='0', `refresh_heartbeat`=null");
		return_value_json(true);
	}
	
	public function location() {
		$DataImportLocation = M('DataImportLocation');
		check_error($DataImportLocation);
		
		$total = $DataImportLocation->count();
		
		$DataImportLocation->order('`id` ASC');
		
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) $DataImportLocation->limit($limit)->page($page);
		
		$locations = $DataImportLocation->select();
		
		return_json(true,$total,'locations', $locations);
	}
	
	public function refreshthread() {
		ignore_user_abort();
		set_time_limit(0);
		
		$threadId = uniqid();
		
//		Log::write("\nSTART REFRESHING, threadId=".$threadId, Log::INFO);
		//循环刷新
		while(true) {
			if(false === $this->_doRefreshing($threadId)) break;
//			$lastRefresh = time();
//			do{
				M()->execute("UPDATE `data_import` SET `refresh_heartbeat`='".date('Y-m-d H:i:s')."'");
				$setting = $this->_getSetting();
				if(!$setting['refresh_on']) break;
				sleep($setting['refresh_frequency']*60);
//			}
//			while((time()-$lastRefresh<$setting['refresh_frequency']*60) && $setting['refresh_on']);
			if(!$setting['refresh_on']) break;
		}
//		Log::write("\nSTOP REFRESHING, threadId=".$threadId, Log::INFO);
	}
	
	/**
	 * 刷新用户定位
	 * 根据临时表里的数据，一个个地刷新用户的定位，并更新data_import表的内容（countleft，last_refresh）
	 */
	private function _doRefreshing($threadId) {
		$numbers = $this->_gerNumbersForRefreshing();
		
		//首先以刷新cookie登录
		$setting = $this->_getSetting();
		$re = $this->_curl_request(
			self::$base_url . 'bll/doLogin.aspx',
			array(
				's' => date('D M m Y H:i:s') . ' GMT 0800',
				'username' => $setting['username'],
				'userpwd' => $setting['password'],
				'system' => 0
			),
			self::$base_url . 'login.aspx',
			false,
			self::$refreshcookiefile
		);
		
		if(!($re['success'] && $re['response']==='1')) {
			return true;
		}
//		Log::write("\nREFRESHING logined, threadId=".$threadId, Log::INFO);
		
		foreach ( $numbers as $number ) {
			if(false === $this->_refreshNumber($number, $threadId)) return false;
		}
		
		M()->execute("UPDATE `data_import` SET `refresh_finished`='".date('Y-m-d H:i:s')."'");
		M()->execute("UPDATE `data_import_location` SET `refreshing`='0' WHERE 1");
		return true;
	}
	
	private function _gerNumbersForRefreshing($ids=null) {
		$DataImportNumber = M('DataImportNumber');
		check_error($DataImportNumber);
		
		$condition = empty($ids) ? '1' : "`data_import_number`.`id` IN (".$ids.")";
		
		$numbers = $DataImportNumber->join('`device` on `device`.`mobile_num`=`data_import_number`.`number`')
			->field(array('`device`.`id`' => 'device_id', '`data_import_number`.`number`', 
						'`data_import_number`.`name`', '`data_import_number`.`last_time`', '`device`.`label`'))
			->where($condition)
			->order('`data_import_number`.`id`')
			->select();
		check_error($DataImportNumber);
		
		M()->execute('TRUNCATE `data_import_location`');
		
		$DataImportLocation = M('DataImportLocation');
		check_error($DataImportLocation);
		
		$DataImportLocation->addAll($numbers);
		
		return $numbers;
	}
	
	private function _refreshNumber($number, $threadId, $checkThreadId=true) {
		$setting = $this->_getSetting();
		
		if($checkThreadId && !$setting['refresh_on']) {
			M()->execute("UPDATE `data_import` SET `refresh_heartbeat`=null");
			return false;
		}
		
		//如果当前线程id不等于数据库中的线程id，则中断当前线程
		if($checkThreadId && $setting['refreshing'] && $setting['refresh_thread_id']!=$threadId) return false;
		
		M()->execute("UPDATE `data_import` SET `refresh_heartbeat`='".date('Y-m-d H:i:s')."', `refresh_thread_id`='{$threadId}'");
		
		$DataImportLocation = M('DataImportLocation');
		check_error($DataImportLocation);
		
		$DataImportLocation->where("`number`='{$number['number']}'")->save(array('refreshing'=>1));
				
		$errorLocation = array(
				'latitude' => null,
				'longitude' => null,
				'time' => null,
				'address' => null,
				'refresh_time' => date('Y-m-d H:i:s'),
				'countleft' => null,
				'refreshing' => 0,
				'success' => 0
		);
		
		$location = array_merge($number, $errorLocation);
							//	  0000-00-00 00:00:00
		if($number['last_time']=='0000-00-00 00:00:00') {	//如果时间不正确，则纠正时间。
			$number['last_time'] = date('Y-m-d H:i:s', time()-3600*24);
			Log::write("last_time UPDATED ".$number['last_time']." FOR ". $number['number'], Log::INFO);
			M()->execute("UPDATE `data_import_number` SET `last_time`='".$number['last_time']."' WHERE `number`='{$number['number']}'");
		}
		
		$locationHtml = $this->_curl_request(
				self::$base_url . 'bll/doTrajectory.aspx',
				array(
						'code' => $number['number'],
						'startdt' => $number['last_time'],
						'enddt' => date('Y-m-d H:i:s'),
						'select' => '0',
						's' => date('D M m Y H:i:s') . ' GMT 0800'
				),
				self::$base_url . 'index.aspx',
				false,
				self::$refreshcookiefile
		);
		
		if(!$locationHtml['success']) {
			$location['msg'] = '网络请求返回错误码：' . $locationHtml['errno'] . '，错误信息：' . $locationHtml['error'];
			$this->_addLocation($location);
		}
		else if(empty($locationHtml['response'])) {
			$location['msg'] = '无位置更新';
			$this->_addLocation($location);
		}
		else {
			$locations = explode("|", $locationHtml['response']);
			$c = count($locations);
			for($i=1; $i<$c; $i++) {//处理数据的。注意：$locations[0]是一个数字，不是定位数据
				$l = explode(";", $locations[$i]);
				if(count($l)< 3) continue;
				$location['longitude'] = $l[0];
				$location['latitude'] = $l[1];
				$location['time'] = $l[2];
				$location['name'] = $l[4];
				$locations[$i] = substr($locations[$i], 0, 0-strlen($l[count($l)-1])-1);
				$location['address'] = trim(substr($locations[$i], strlen($l[0])+strlen($l[1])+strlen($l[2])+strlen($l[3])+strlen($l[4])+5));
				$location['success'] = 1;
				$location['msg'] = null;
				$this->_addLocation($location);
			} 
		}
		
		M()->execute("UPDATE `data_import` SET `refresh_heartbeat`='".date('Y-m-d H:i:s')."', `last_refresh`='".date('Y-m-d H:i:s')."', `refresh_thread_id`='{$threadId}'");
		if(!empty($location['time'])){
			M()->execute("UPDATE `data_import_number` SET `last_time`=DATE_ADD('".$location['time']."', INTERVAL 1 SECOND) WHERE `number`='{$number['number']}'");
		}
		return true;		
	}
	
	private function _addLocation($location) {
		$DataImportLocation = M('DataImportLocation');
		check_error($DataImportLocation);
		
		$location['refresh_time'] = date('Y-m-d H:i:s');
		$DataImportLocation->where("`number`='{$location['number']}'")->save($location);
		Log::write(M()->getLastSql(), Log::SQL);
		
		if(empty($location['latitude']) || empty($location['longitude']) || empty($location['label'])) return;
		
		$interface = $this->_curl_request(
			'http://'.$_SERVER['HTTP_HOST'].U('Interface/gps'),
			array(
				'label' => $location['label'],
				'lat' => $location['latitude'],
				'lng' => $location['longitude'],
				'address' => $location['address'],
				'time' => $location['time']
			),
			null
		);
	}
	
	public function getrefreshstatus() {
		$DataImportLocation = M('DataImportLocation');
		check_error($DataImportLocation);
		
		$total = $DataImportLocation->count();
		
		$refreshing = $DataImportLocation->where("`refreshing`='1'")->order("`id` DESC")->find();
		
		$DataImport = M('DataImport');
		check_error($DataImport);
		
		$refreshTimes = $DataImport->field(array('last_refresh', 'refresh_finished', 'refresh_heartbeat'))->where('1')->find(); 
		
		$data = array(
			'total' => $total,
			'refreshingId' => empty($refreshing) ? 0 : $refreshing['id'],
			'refreshing' => (!empty($refreshTimes['refresh_heartbeat']) && (time()-strtotime($refreshTimes['refresh_heartbeat'])<10*60)),
			'last_refresh' => (empty($refreshTimes) || empty($refreshTimes['last_refresh'])) ? '从未完成' : $refreshTimes['last_refresh'],
			'refresh_finished' => (empty($refreshTimes) || empty($refreshTimes['refresh_finished'])) ? '从未完成' : $refreshTimes['refresh_finished']
		);
		
		return_value_json(true, 'refreshstatus', $data);
	}
	
	public function refreshselected() {
		$ids = $this->_request('ids');
//		if(empty($ids)) return_value_json(false, 'msg', '系统错误：提交的分组id为空');
		
		$DataImportLocation = M('DataImportLocation');
		$refreshing = $DataImportLocation->where("`refreshing`='1'")->find();
		if(!empty($refreshing)) return_value_json(false, 'msg', "正在刷新过程中。");
		
		if(function_exists('fsockopen')) { //可以用fsockopen
			//打开一个后台连接
			$fp = fsockopen($_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $errno, $errstr, 30);
			if($fp===FALSE) {
				die();
			}
			
			//设置流为非阻塞型
			if (!stream_set_blocking($fp, 0)) {
				die();
			}
			
			$postdata = "ids=$ids";
			
			//发送get
			$crlf = "\r\n"; 
			$file = U('Dataimport/dorefreshselected');
			$header = "POST $file HTTP/1.1" . $crlf;
			$header .= "Host: {$_SERVER['HTTP_HOST']}" . $crlf;
			$header .= 'Content-Type: application/x-www-form-urlencoded' . $crlf; 
			$header .= 'Content-Length: '. strlen($postdata) . $crlf . $crlf;
			$header .= $postdata . $crlf; 
			$header .= "Connection: Close" . $crlf . $crlf;
			fwrite($fp, $header);
			fclose($fp); //不等结果，直接关闭
		}
		
		sleep(2);
		
		return_value_json(true);
	}
	
	public function dorefreshselected() {
		ignore_user_abort();
		set_time_limit(0);
		
		$ids = $this->_request('ids');
		
		$numbers = $this->_gerNumbersForRefreshing($ids);
		
		//首先以刷新cookie登录
		$setting = $this->_getSetting();
		$re = $this->_curl_request(
			self::$base_url . 'bll/doLogin.aspx',
			array(
				's' => date('D M m Y H:i:s') . ' GMT 0800',
				'username' => $setting['username'],
				'userpwd' => $setting['password'],
				'system' => 0
			),
			self::$base_url . 'login.aspx',
			false,
			self::$refreshcookiefile
		);
		
		if(!($re['success'] && $re['response']==='1')) {
			return true;
		}
		
		foreach ( $numbers as $number ) {
			$this->_refreshNumber($number, null, false);
		}
		
		M()->execute("UPDATE `data_import` SET `refresh_finished`='".date('Y-m-d H:i:s')."'");
		M()->execute("UPDATE `data_import_location` SET `refreshing`='0' WHERE 1");
	}
	
	private function _startRefreshThread() {
//		Log::write("\n_startRefreshThread", Log::INFO);
		$setting = $this->_getSetting();
		
		if($setting['refreshing']) {
//			Log::write("\n_startRefreshThread last refreshing", Log::INFO);
			return;
		}
		
		if(function_exists('fsockopen')) { //可以用fsockopen
			//打开一个后台连接
			$fp = fsockopen($_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $errno, $errstr, 30);
			if($fp===FALSE) {
				die();
			}
			
			//设置流为非阻塞型
			if (!stream_set_blocking($fp, 0)) {
				die();
			}
			
			//发送get
			$crlf = "\r\n"; 
			$file = U('Dataimport/refreshthread');
			$header = "POST $file HTTP/1.1" . $crlf;
			$header .= "Host: {$_SERVER['HTTP_HOST']}" . $crlf;
			$header .= 'Content-Type: application/x-www-form-urlencoded' . $crlf; 
			$header .= 'Content-Length: 0' . $crlf . $crlf;
			$header .= "Connection: Close" . $crlf . $crlf;
			fwrite($fp, $header);
			fclose($fp); //不等结果，直接关闭
		}
	}
	
	private function _getSetting() {
		$DataImport = M('DataImport');
		check_error($DataImport);
		
		$setting = $DataImport->select();
		check_error($DataImport);
		
		if(!empty($setting) && !empty($setting[0]['last_login'])) {
			if(time()-strtotime($setting[0]['last_login'])>10*60) {	//假设客户端的session为10分钟（待测试）
				$setting[0]['logined'] = 0;
				$DataImport->execute("UPDATE `data_import` SET `logined`='0'");
			}
		}
		if(!empty($setting)) {
			if(!empty($setting[0]['refresh_heartbeat']) && (time()-strtotime($setting[0]['refresh_heartbeat'])<10*60)) {	//假设客户端的session为10分钟（待测试）
				$setting[0]['refreshing'] = 1;
			}
		}
		
		return empty($setting) ?  null : $setting[0];
	}
	
	public function savesetting() {
		$data = array();
		
		$username = $this->_post('username');
		$password = $this->_post('password');
		if(!empty($username) && !empty($password)) {
			$data['username'] = $username;
			$data['password'] = $password;
		}
		
		$refresh_frequency = $this->_post('refresh_frequency');
		if(!empty($refresh_frequency)) {
			$data['refresh_frequency'] = $refresh_frequency;
		}
		
		if(!empty($data)) {
			$DataImport = M('DataImport');
			check_error($DataImport);
			
			$DataImport->where('1')->save($data);
			check_error($DataImport);
		}
		
		return_value_json(true);
	}
	
	
	private function _curl_request($url, $params, $referer, $post=true, $cookie=null) {
		$ch = curl_init();
		if(!empty($cookie)) {
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
		}
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:12.0) Gecko/20100101 Firefox/12.0");
		curl_setopt($ch, CURLOPT_TIMEOUT, 60*10); //10分钟
		if($post){
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		}
		else if(!empty($params)) { // get，但是有参数
			$url .= '?' . http_build_query($params);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		if(!empty($referer)) curl_setopt($ch, CURLOPT_REFERER, $referer);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		
		$response = curl_exec($ch);
	
		$re = array(
			'success'	=> ($response!==false && curl_errno($ch)===0),
			'response' 	=> $response,
			'errno'		=> curl_errno($ch),
			'error'		=> curl_error($ch)
		);
		
		curl_close($ch);
		return $re;
	}
	
	/**
	 * 获取最新定位
	 * 相当于在旧网站上点击了“立即定位”，然后得到位置，发送请求给GPS接口，然后返回结果
	 */
	public function getlatestlocation() {
		$num = $this->_request('number');
		if(empty($num)) {
			return_value_json(false, 'msg', '待定位手机号码为空');
		}
		
		$DataImportNumber = M('DataImportNumber1');
		$number = $DataImportNumber->where(array('number'=>$num))->find();
		
		if(empty($number)) {
			return_value_json(false, 'msg', '即时定位暂时只支持手机定位数据导入方式，您现在看到的定位信息也许有时间延迟');
		}
		
		//以临时cookie登陆旧系统
		set_time_limit(0);
		$setting = $this->_getSetting();
		
		if(!$setting['logined']) {
			$this->login(false);
		}
			
		M()->execute("UPDATE `data_import1` SET `logined`='1', `last_login`='".date('Y-m-d H:i:s')."'");
		
		
		
	}
}
?>