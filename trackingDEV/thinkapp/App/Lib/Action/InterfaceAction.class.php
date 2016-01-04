<?php
import('App.Util.LBS.Geometry');

/**
 * 接口类
 */
class InterfaceAction extends Action {
	//TODO 完善异常处理（不能轻易用check_error和返回false）和日志。
	
	/**
	 * 设备登陆操作接口
	 * 输入：HTTP POST：设备类型[type]、设备标识[number]、当前运行参数（interval）
	 * 输出：HTTP 返回：json，设备ID，设备运行参数（如果运行参数有变化的话，上报间隔：interval）
	 * 说明：此接口暂时不启用
	 */
	public function login(){
		
	}
	
	/**
	 * 基站定位数据上传接口
	 * 输入：HTTP POST：设备标识、基站数据（LAC、CELLID）、运行参数（interval）
	 * 输出：HTTP 返回：json，成功与否，是否有告警，如果有告警，则有告警的原因提示。
	 * 说明：暂时不返回告警
	 * 
	 * 操作：（1）查询
	 */
	public function cell() {
//		if(function_exists('getallheaders') && APP_DEBUG===true) Log::write("\n".print_r(getallheaders(), true), Log::DEBUG);
//		if(APP_DEBUG===true) 
//		Log::write("\n".print_r($_POST, true), Log::DEBUG);
    	if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
    	
    	$label = $this->_post('label');
    	$lac = $this->_post('lac');
    	$cellid = $this->_post('cellid');
    	$interval = $this->_post('interval');
    	$battery_state = $this->_post('battery_state')===0 ? 0 : 1;
    	$battery_level = $this->_post('battery_level');
    	$signal_state = $this->_post('signal_state')===0 ? 0 : 1;
    	$signal_level = $this->_post('signal_level');
    	
    	if(empty($label)) return_value_json(false, 'msg', '设备标识不能为空');
    	if(empty($lac)|| empty($cellid)) return_value_json(false, 'msg', '基站定位数据为空或不全');
    	
    	$this->_cell(date('Y-m-d H:i:s'), $label, $lac, $cellid, $interval, $battery_state, $battery_level, $signal_state, $signal_level);
	}
	
	/**
	 * GPS定位数据上传接口
	 * 输入：HTTP POST：设备标识、GPS坐标、运行参数（interval）
	 * 输出：HTTP 返回：json，成功与否，是否有告警，如果有告警，则有告警的原因提示。
	 * 说明：暂时不返回告警
	 */
	public function gps() {
// 		if(function_exists('getallheaders') && APP_DEBUG===true) Log::write("\n".print_r(getallheaders(), true), Log::DEBUG);
// 		if(APP_DEBUG===true) Log::write("\n".print_r($_POST, true), Log::DEBUG);
    	if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
    	
    	$label = $this->_post('label');
    	$lat = $this->_post('lat') + 0;	//转换成数字
    	$lng = $this->_post('lng') + 0;
    	$address = $this->_post('address');
    	$interval = $this->_post('interval');
    	$battery_state = $this->_post('battery_state')===0 ? 0 : 1;
    	$battery_level = $this->_post('battery_level');
    	$signal_state = $this->_post('signal_state')===0 ? 0 : 1;
    	$signal_level = $this->_post('signal_level');
    	$time = $this->_post('time');
    	$time = empty($time) ? date('Y-m-d H:i:s') : $time;
    	
    	$speed = $this->_post('speed');
    	
    	if(empty($label)) return_value_json(false, 'msg', '设备标识不能为空');
    	if(empty($lat)|| empty($lng)) return_value_json(false, 'msg', 'GPS坐标不全');
    
    	$this->_gps($time, $label, $lat, $lng, $address, $interval, $battery_state, $battery_level, $signal_state, $signal_level);	
	}
	
	public function cell2() {
// 		if(function_exists('getallheaders') && APP_DEBUG===true) Log::write("\n".print_r(getallheaders(), true), Log::INFO);
    	if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$cells = json_decode(file_get_contents("php://input"));
		if(!is_array($cells)) {
			$cells = array($cells);
		}
		
		$re = null;
		foreach ( $cells as $cell ) {
			if(strlen($cell->time)!=19) return_value_json(false, 'msg', '时间格式不正确');
			$cell->time = str_ireplace("T", " ", $cell->time);
			if(strtotime($cell->time)===false) return_value_json(false, 'msg', '时间格式不正确');
	    	if(empty($cell->label)) return_value_json(false, 'msg', '设备标识不能为空');
	    	if(empty($cell->lac)|| empty($cell->cellid)) return_value_json(false, 'msg', '基站定位数据为空或不全');
	    	
    		$re = $this->_cell($cell->time, $cell->label, $cell->lac, $cell->cellid, $cell->interval, 
    							$cell->battery_state, $cell->battery_level, $cell->signal_state, $cell->signal_level, 
    							false);
		}
		
    	if(!empty($re) && $re!==true) {
    		return_value_json(true, 'interval', $re);
    	}
    	
    	return_value_json(true);
	}
	
	/**
	 * 批量上传
	 */
	public function batch() {
//		Log::write("\n".print_r($_POST, true), Log::DEBUG);
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');

		$label = $this->_post('label');
		$interval = $_POST['interval'] + 0;
		$locations = $_POST['locations'];
		if(!is_array($locations)) return_value_json(false, 'msg', '定位数据为空或者格式不正确');
		
		set_time_limit(0);
		ignore_user_abort();
		
		$device = $this->findDevice($label);
		
		foreach ($locations as $index => $location) {
			if( !(isset($location['gps_lng']) && isset($location['gps_lat']))
				&& !(isset($location['baidu_lng']) && isset($location['baidu_lat'])))
			{ //如果没有经纬度
				if(!empty($location['lac']) && !empty($location['cellid'])) {  //有基站信息
					$cellLocation = $this->getCellLocation($location['lac'], $location['cellid']);
					if(empty($cellLocation)) continue;
					$location = array_merge($location, $cellLocation);
				}
				else {
					continue;
				}
			}
			else if(isset($location['gps_lng']) && isset($location['gps_lat'])){
				//有GPS经纬度，将GPS经纬度转换成百度坐标
				$location = array_merge($location, $this->getBaiduCoordinate($location['gps_lat'], $location['gps_lng']));
			}
			else if(isset($location['baidu_lng']) && isset($location['baidu_lat'])) {
				//没有GPS经纬度，直接将百度坐标当成GPS坐标（因为数据库不允许GPS坐标为空）
				$location['gps_lat'] = $location['baidu_lat'];
				$location['gps_lng'] = $location['baidu_lng'];
			}
			else {
				continue;
			}
			
			$location['device_time'] = $location['time'];
			
			//找到上次的定位
			$lastLocation = $this->getLastLocation($device['id']);
			 
			//更新上次的速度和方向
			if($lastLocation) {
				if(!isset($lastLocation['speed']) || !isset($lastLocation['direction'])) {
					$this->updateLastSpeedAndDirection($location, $lastLocation);
				}
				
				if(!isset($location['speed']) || !isset($location['direction'])) {
					$location['speed'] = $lastLocation['speed'];
					$location['direction'] = $lastLocation['direction'];
				}
				else {
					if(!is_numeric($location['speed']) || $location['speed']<0)
						$location['speed'] = 0;
					
					if(!is_numeric($location['direction'])) {
						$location['direction'] = '原地不动';
					}
					else {
						$location['direction'] = $this->getDirectionFromDegree($location['direction'] + 0);
					}
				}
			}
			else {
				$location['speed'] = 0;
				$location['direction'] = '原地不动';
			}
				
			if(!$lastLocation || ($lastLocation && $lastLocation['online']=='离线')) {
				//TODO设备登陆
			}
			
			//保存当前定位
			$location['id'] = $this->saveLocation($device, $location);

			$this->checkAlarm($device, $location);
		}
		

		if(empty($interval) || $device['interval']!=$interval) {
			return_value_json(true, 'data', array(
				'interval' => $device['interval'],
				'weekday0' => $device['weekday0'],
				'weekday1' => $device['weekday1'],
				'weekday2' => $device['weekday2'],
				'weekday3' => $device['weekday3'],
				'weekday4' => $device['weekday4'],
				'weekday5' => $device['weekday5'],
				'weekday6' => $device['weekday6'],
				'starttime' => empty($device['starttime']) ? "" : $device['starttime'],
				'endtime' => empty($device['endtime']) ? "" : $device['endtime']
			));
		}
		 
		return_value_json(true, 'data', "");
	}
	
	public function eseal() {
// 		if(function_exists('getallheaders') && APP_DEBUG===true) Log::write("\n".print_r(getallheaders(), true), Log::DEBUG);
// 		if(APP_DEBUG===true) Log::write("\n".print_r($_POST, true), Log::DEBUG);
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$label = $this->_post('label');
		$bar_id = $this->_post('bar_id');
    	$lat = $this->_post('lat') + 0;	//转换成数字
    	$lng = $this->_post('lng') + 0;
    	$time = $this->_post('time');
    	$speed = $this->_post('speed');
    	$direction = $this->getDirectionFromDegree($this->_post('direction')+0);
    	$battery = $this->_post('battery');
    	
    	$device = $this->findDevice($label);	//注：如果找不到设备已经退出脚本了。
    	
    	$location = $this->getBaiduCoordinate($lat, $lng);
    	$location['time'] = date('Y-m-d H:i:s');//$time;
    	$location['device_time'] = $time;
    	$location['speed'] = $speed;
    	$location['bar_id'] = $bar_id;
    	
    	$this->sealBattery($location, $battery);
    	
    	//根据上次的Location来判断现在的电子铅封的状态.
    	$lastLocation = $this->getLastLocation($device['id']);
    	if(empty($lastLocation)){
    		$location['state'] = '新发现';
    	}
    	else if($lastLocation['bar_id']=='FFFFFFFFFFFFFFFFF' && !empty($location['bar_id']) && $location['bar_id']!='FFFFFFFFFFFFFFFFF') {
    		$location['state'] = '锁杆插入';
    	}
    	else if(!empty($lastLocation['bar_id']) && $lastLocation['bar_id']!='FFFFFFFFFFFFFFFFF' && $location['bar_id']=='FFFFFFFFFFFFFFFFF') {
    		$location['state'] = '锁杆拔出';
    	}
    	else if(!empty($lastLocation['bar_id']) && !empty($location['bar_id']) && $location['bar_id']!=$lastLocation['bar_id']) {
    		$location['state'] = '新发现';	//这种情况是发现锁杆id已经变成了另一个锁杆id，应该不会发现这种情况才对
    	}
    	else {	//注意:上述情况是没有direction数据的.
    		$location['direction'] = empty($direction) ? '停车' : $direction;
    	}
    	
    	//保存当前定位
    	$location['id'] = $this->saveLocation($device, $location);
    	
    	//TODO根据告警规则判断是否触及告警，并采取相应的告警措施。
    	$this->checkAlarm($device, $location);
	}
	
	private function sealBattery(&$location, $battery) {
		$b = ('0x' . $battery) + 0;
    	if($b<('0x65'+0)) {
    		$location['state'] = '电量低';
    		$location['battery_state'] = 0;
    	}
    	else {
    		$location['state'] = '正常';
    		$location['battery_state'] = 1;
    	}
    	$location['battery_level'] = round(($b-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0)));
	}
	
	public function outside() {
		$input = trim(file_get_contents("php://input"));
		if(empty($input)) return;
		
		$a = explode(",", $input);
		if(count($a)<4) return;
		
		$label = strstr($a[3], '@');
		$l = strlen($label);
		$label = substr($label, 1, $l-2);
		$time = strstr($a[3], 'T');
		$a[3] = substr($a[3], 0, 0-strlen($time));
		$time = substr($time, 1, 0-$l);
		
		$b = explode(";", $a[3]);
		
		if(empty($b) || count($b)<2) return;
		if(!substr($b[0], 0, 2)=='2M') return;
		
		$pos = strpos($b[0], '_');
		if($pos===false) return;
		$mnc = substr($b[0], $pos+1) + 0;
		$mcc = substr($b[0], 2, $pos-2) + 0;
		
		//目前只分析一个基站数据
		$c = explode("_", $b[1]);
		if(count($c)!=3) return;
		
		$lac = $c[0] + 0;
		$cellid = $c[1] + 0;
		$rssi = $c[2] + 0;
		
		$interval = 30;
		$battery_state = 1;
		$battery_level = null;
		$signal_state = 1;	//TODO 信号强度根据rssi进行换算
		$signal_level = null;
		
		$this->_cell(date('Y-m-d H:i:s'), $label, $lac, $cellid, $interval, $battery_state, $battery_level, $signal_state, $signal_level, false, $time);
		
	}
	
	public function getlog() {
		$file = realpath(null) . "/App/Runtime/Logs/".date('y_m_d').".log";
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: public");
		header("Content-Description: File Transfer");
		header("Content-Type: application/octet-stream");
		
		header("Content-Disposition: attachment; filename=".date('y_m_d').".log");
		header("Content-Transfer-Encoding: binary");
		$len = filesize($file);
		header("Content-Length: ".$len);
		
		@readfile($file);
	}
	
	private function _cell($time, $label, $lac, $cellid, $interval, $battery_state, $battery_level, $signal_state, $signal_level, $return=true, $device_time=null) {
		//找到相关的设备
		if(empty($label)) return;
		$device = $this->findDevice($label);
		
    	if(!$this->inTime($device)) {
    		return_value_json(true, 'time', array(
    			'weekday0' => $device['weekday0'],
    			'weekday1' => $device['weekday1'],
    			'weekday2' => $device['weekday2'],
    			'weekday3' => $device['weekday3'],
    			'weekday4' => $device['weekday4'],
    			'weekday5' => $device['weekday5'],
    			'weekday6' => $device['weekday6'],
    			'starttime' => $device['starttime'],
    			'endtime' => $device['endtime']
    		));
    	}
    	
    	//找到相关的基站数据
    	$location = $this->getCellLocation($lac, $cellid);
    	 
    	$location['time'] = $time;
    	$location['device_time'] = $device_time;
    	$location['battery_state'] = $battery_state;
    	$location['battery_level'] = $battery_level;
    	$location['signal_state'] = $signal_state;
    	$location['signal_level'] = $signal_level;
    	
    	
    	//找到上次的定位
    	$lastLocation = $this->getLastLocation($device['id']);
    	
    	//更新上次的速度和方向
    	if($lastLocation) {
    		$this->updateLastSpeedAndDirection($location, $lastLocation);
    		//对基站定位来说，本次定位的方向和速度是跟上次一样的（将在下次定位时更新）
    		$location['speed'] = $lastLocation['speed'];
    		$location['direction'] = $lastLocation['direction'];
    	}
    	else {
    		$location['speed'] = 0;
    		$location['direction'] = '停车';
    	}
    	
    	if(!$lastLocation || ($lastLocation && $lastLocation['online']=='离线')) {
    		//TODO设备登陆
    	}

    	//保存当前定位
    	$location['id'] = $this->saveLocation($device, $location);
    	
    	//TODO根据告警规则判断是否触及告警，并采取相应的告警措施。
    	$this->checkAlarm($device, $location);
    	
    	//TODO如果客户端的数据上报频率发生了变化，通知客户端。
    	if(empty($interval) || $device['interval']!=$interval) {
    		if($return) {
    			return_value_json(true, 'data', array(
    				'interval' => $device['interval'],
	    			'weekday0' => $device['weekday0'],
	    			'weekday1' => $device['weekday1'],
	    			'weekday2' => $device['weekday2'],
	    			'weekday3' => $device['weekday3'],
	    			'weekday4' => $device['weekday4'],
	    			'weekday5' => $device['weekday5'],
	    			'weekday6' => $device['weekday6'],
	    			'starttime' => $device['starttime'],
	    			'endtime' => $device['endtime']
	    		));
    		}
    		else return $device['interval'];
    	}
    	
    	if($return) return_value_json(true);
    	else return true;
	}
	
	private function _gps($time, $label, $lat, $lng, $address, $interval, $battery_state, $battery_level, $signal_state, $signal_level)  {
    	//找到相关的设备
    	$device = $this->findDevice($label);
    	
    	//找到相关的基站数据
    	$location = $this->getBaiduCoordinate($lat, $lng);
    	if(!$this->inTime($device)) {
    		return_value_json(true, 'time', array(
    			'weekday0' => $device['weekday0'],
    			'weekday1' => $device['weekday1'],
    			'weekday2' => $device['weekday2'],
    			'weekday3' => $device['weekday3'],
    			'weekday4' => $device['weekday4'],
    			'weekday5' => $device['weekday5'],
    			'weekday6' => $device['weekday6'],
    			'starttime' => $device['starttime'],
    			'endtime' => $device['endtime']
    		));
    	}
    	$location['address'] = $address;
    	$location['time'] = $time;
    	$location['battery_state'] = $battery_state;
    	$location['battery_level'] = $battery_level;
    	$location['signal_state'] = $signal_state;
    	$location['signal_level'] = $signal_level;
    	
    	//找到上次的定位
    	$lastLocation = $this->getLastLocation($device['id']);
    	
    	
    	//更新上次的速度和方向
    	if($lastLocation) {
    		if(empty($speed)) { //这次没有速度，我们认为上次的速度也是我们自己算的，所以更新一下
	    		$this->updateLastSpeedAndDirection($location, $lastLocation);
	    		//如果是自己算的速度，那么本次定位的方向和速度是跟上次一样的（将在下次定位时更新）
	    		$location['speed'] = $lastLocation['speed'];
	    		$location['direction'] = $lastLocation['direction'];
    		}
    		else { //这次有速度，我们认为上次的速度也是GPS设备发过来的，就不更新了，只更新方向
    			$this->updateLastDirection($location, $lastLocation);
    			//设备有速度数据当然用设备的速度数据，但方向目前认为还是跟上次一样（将在下次定位时更新）
    			$location['speed'] = $speed;
    			$location['direction'] = $lastLocation['direction'];
    		}
    	}
    	else { //完全的首次定位
    		$location['speed'] = 0;
    		$location['direction'] = '停车';
    	}
    	
    	if(!$lastLocation || ($lastLocation && $lastLocation['online']=='离线')) {
    		//TODO设备登陆
    	}

    	//保存当前定位
    	$location['id']  = $this->saveLocation($device, $location);

    	//TODO根据告警规则判断是否触及告警，并采取相应的告警措施。
    	$this->checkAlarm($device, $location);
    	
    	
    	//TODO如果客户端的数据上报频率发生了变化，通知客户端。
    	if(empty($interval) || $device['interval']!=$interval) {
    		return_value_json(true, 'interval', $device['interval']);
    	}
    	
    	return_value_json(true);
	}
	
	/**
	 * 根据$device的设置，检查目前是否在定时设置时间内（在时间内则返回true，否则返回false
	 */
	private function inTime($device) {
		$weekday = date('w') + 0;
		if($weekday==0) $weekday = 7;
		$weekday = $weekday - 1;
		if($device['weekday' . $weekday] || is_null($device['weekday' . $weekday]) ) { //在星期内
			if((!is_null($device['starttime']) && time()<strtotime($device['starttime'])) 
				|| (!is_null($device['endtime']) && time()>strtotime($device['endtime']))) { //不在时间内
				return false;
			}
			return true;
		}
		else  return false;  //不在星期内。
	}
		
	private function findDevice($label) {
    	$Device = D('Device');
    	check_error($Device);
    	
    	$condition = array('label'=>$label);
    	
    	$device = $Device->where($condition)->find();
    	check_error($Device);
    	
    	if($device['target_type']=='车辆') {
    		$vehicle = M('Vehicle')->where("`id`='{$device['target_id']}'")->find();
    		$device['target_name'] = $vehicle['number'];
    	}
    	else if($device['target_type']=='人员') { //TODO 设备的target_name
    		
    	}
    	else if($device['target_type']=='集装箱') {
    		
    	}
    	else if($device['target_type']=='班列') {
    		
    	}
    	
    	if(empty($device)) return_value_json(false, 'msg', '错误的设备标识');
    	
    	return $device;
	}
	
	private function saveLocation($device, $location) {
		$Location = M('Location');
		check_error($Location);
		
		$data = array_merge($location, $device);
		$data['id'] = null;
		$data['device_id'] = $device['id'];
		$data['online'] = '在线';
		$data['state'] = empty($data['state']) ? '正常' : $data['state'];
		if(empty($data['address']))
			$data['address'] = $this->getAddressFormBaiduCoordinate($data['baidu_lat'], $data['baidu_lng']);
		
		$location_id = $Location->add($data);
		if($location_id) {
			$Device = M('Device');
			check_error($Device);
			
			if(false === $Device->where(array('id'=>$device['id']))->save(array('last_location' => $location_id))){
				//TODO 写日志：更新设备的最后定位失败。
//				Log::write('更新设备的最后定位失败');
			}
			
			return $location_id;
		}
		else { //保存位置失败
			//TODO 写日志：保存位置失败
// 			Log::write('保存位置失败: '.get_error($Location));
			return false;
		}
	}
	
	/**
	 * 查找指定基站的坐标
	 * 会将数据库里的GPS坐标转换成百度坐标
	 */
	private function getCellLocation($lac, $cellid) {
		$Cell = M('Cell');
		check_error($Cell);
		
		$location = $Cell->where(array('lac'=>$lac, 'cellid'=>$cellid))->find();
		check_error($Cell);
		
		if(empty($location)) { //数据库里没有指定基站的数据
			return array();
// 			$location = $this->getUnknowCellLocation($lac, $cellid);
		}
		
		return array_merge($location, $this->getBaiduCoordinate($location['gps_lat'], $location['gps_lng']));
	}
	
	/**
	 * 由于Google也不提供基站定位查询服务，所以本函数作废
	 */
	private function getUnknowCellLocation($lac, $cellid) {
		/**安特网已经不能使用了：为充分贯彻国家相关法律法规，安特网关闭。
		$url = "http://www.anttna.com/cell2gps/cell2gps3.php?lac=$lac&cellid=$cellid";
		$response = file_get_contents($url);
		//结果：gps_lat|gps_lng|offset_lat|offset_lng|地址|range
		//例如：23.033258|113.743561|23.030542757218|113.74875912107|广东省;东莞市;城区;红山西路/红山西路横街(路口)西北70米;金域大厦东140米;八达菜篮子西北50米;|800
		$result = explode("|", $response);
		
		if(empty($result) || count($result)!=6) {
			Log::write("\n无法定位位置：lac=".$lac.", cellid=".$cellid, Log::INFO);
			//TODO 写日志，错误返回。
			return_value_json(false, 'msg', '无法定位您的位置');
		}
		*/
		
		$url = "http://www.google.com/loc/json";
		$access_token = session('access_token');
		$json = '{"access_token":"'.$access_token.'", 
		"host" : "code.google.com", 
		"radio_type" : "gsm", 
		"request_address" : true, 
		"version" : "1.1.0",
		"cell_towers": [{
			"cell_id": '.$cellid.',
			"location_area_code": '.$lac.',
			"mobile_country_code": 460,
			"mobile_network_code": 0
		}] }';
		$response = curl_post($url, $json, array(), null);
		//{"location":{"latitude":23.0350802,"longitude":113.7421495,"address":{"country":"China","country_code":"CN","region":"Guangdong","city":"Dongguan","street":"Chuangye Rd","street_number":"128号"},"accuracy":1305.0},"access_token":"2:SOmR0o7FatgA00uM:AlMxTgteIhTVOjRm"}
//		Log::write("getUnknowCellLocation: response:".print_r($response, true));
		if($response['success']) {
			$result = json_decode($response['response'], true);
			session('access_token', $result["location"]['access_token']);
			$baiduCoord = $this->getBaiduCoordinate($result["location"]['latitude'], $result["location"]['longitude']);
			
			$data = array(
					'mcc' => 460,
					'mnc' => 0,	//全部算是移动的
					'lac' => $lac,
					'cellid' => $cellid,
					'gps_lat' => $result["location"]['latitude'],
					'gps_lng' => $result["location"]['longitude'],
					'range' => $result["location"]["accuracy"]+0,
					'offset_lat' => $baiduCoord['baidu_lat'],
					'offset_lng' => $baiduCoord['baidu_lng'],
					'address' => $this->getAddressFormBaiduCoordinate($baiduCoord['baidu_lat'], $baiduCoord['baidu_lng']),
					'update_time' => date('Y-m-d H:i:s')
			);
			
			$Cell = M('Cell');
			check_error($Cell);
			
			$Cell->add($data);
			return $data;
		}
		
		return array(
			'mcc' => 460,
			'mnc' => 0,	//全部算是移动的
			'lac' => $lac,
			'cellid' => $cellid,
			'gps_lat' => 0,
			'gps_lng' => 0,
			'range' => 0,
			'offset_lat' => 0,
			'offset_lng' => 0,
			'address' => '',
			'update_time' => date('Y-m-d H:i:s')
		);

	}
	
	private function getAddressFormBaiduCoordinate($lat, $lng){
		//key: 169dfcd4f9180470d39cc4df2fa9b1ea
		$url = "http://api.map.baidu.com/geocoder?output=json&location=$lat,%20$lng&key=169dfcd4f9180470d39cc4df2fa9b1ea";
		$response = curl_post($url, null, array(), null);
		$re = '';
		if($response['success']) {
			$result = json_decode($response['response'], true);
			$re = $result['result']['formatted_address'];
		}
		return $re;
	}
	
	/**
	 * 将GPS坐标转换成百度坐标
	 * @param unknown $lat
	 * @param unknown $lng
	 * @return multitype:unknown Ambigous <string>
	 */
	public function getBaiduCoordinate($lat, $lng) {
// 		Log::write("getBaiduCoordinate: LAT=$lat, LNG=$lng", Log::INFO);
		$cordBaidu = $this->coordinateTransfer($lat, $lng, 0, 4);
		
		return array(
			'baidu_lng' => $cordBaidu['lng'],
			'baidu_lat' => $cordBaidu['lat'],
			'gps_lng' => $lng,
			'gps_lat' => $lat
		);
	}
	

	/**
	 * 将百度坐标转换成GPS坐标
	 * @param unknown $lat
	 * @param unknown $lng
	 * @return multitype:unknown Ambigous <string>
	 */
	public function getGpsCoordinateFromBaidu($lat, $lng) {
// 		Log::write("getGpsCoordinateFromBaidu: LAT=$lat, LNG=$lng", Log::INFO);
		$cordGps = $this->coordinateTransfer($lat, $lng, 4, 0);
	
		return array(
				'baidu_lng' =>$lng,
				'baidu_lat' => $lat,
				'gps_lng' =>  $cordGps['lng'],
				'gps_lat' => $cordGps['lat']
		);
	}
	
	/*
	 * 通过百度的坐标转换接口进行坐标转换
	 * @param int $from 源坐标系，0表示GPS坐标（默认），2表示google坐标系转，4表示百度坐标系
	 * @param int $to 目标坐标系，0表示GPS坐标，2表示google坐标系转，4表示百度坐标系（默认）
	 */
	private function coordinateTransfer($lat, $lng, $from=0, $to=4) {
		$url = "http://api.map.baidu.com/ag/coord/convert?from=$from&to=$to&x=$lng&y=$lat";
		$json = file_get_contents($url);
		$coord = json_decode($json);
		if(!isset($coord) || !isset($coord->error) || !isset($coord->x) || !isset($coord->y)) {
			//TODO保存日志
			return_value_json(false, 'msg', '百度坐标转换接口调用返回异常，也许是网络问题。');
		}
		
		if($coord->error) {
			//TODO保存日志
			return_value_json(false, 'msg', '坐标转换出错：' . $coord->error);
		}
		

		return array(
			'lng' => base64_decode($coord->x),
			'lat' => base64_decode($coord->y)
		);
	}
	
	private function getLastLocation($device_id){
		$Location = M('Location');
		check_error($Location);
		
		$location = $Location->where(array('device_id'=>$device_id))->order('`id` DESC')->find();
		check_error($Location);
		
		return $location;
	}
	
	/**
	 * 根据当前的位置和上一次的位置，计算上一次的速度，并更新到数据库里
	 */
	private function updateLastSpeedAndDirection($location, &$lastLocation) {
		$lastSpeed = $this->getSpeed($location, $lastLocation);
		$lastDirection = ($lastSpeed==0) ? '停车' : 
			$this->getDirection($lastLocation['gps_lat']+0, $lastLocation['gps_lng']+0, 
				$location['gps_lat']+0, $location['gps_lng']+0);
    	
    	$data =  array(
			"speed" => $lastSpeed,
			"direction" => $lastDirection
		);
		
    	
    	$Location = M('Location');
    	check_error($Location);
    	
    	if(false === $Location->where(array('id'=>$lastLocation['id']))->save($data)) {
    		//TODO 写日志： 上次速度和方向更新失败
    	}

		$lastLocation = array_merge($lastLocation, $data);
	}
	
	/**
	 * 根据两点经纬度，返回从点1到点2的方向（以点1为基准）
	 * 注：只考虑北半球东经区域距离比较近的两个点。
	 */
	private function getDirection($lat1, $lng1, $lat2, $lng2) {
		//先将坐标进行投影：
		$p1 = Geometry::latlng2mercator($lat1, $lng1);
		$p2 = Geometry::latlng2mercator($lat2, $lng2);
		return $this->getDirectionFromDegree(round($this->compass(($p2['x']-$p1['x']),  ($p2['y']-$p1['y'])), 1));
	}
	
	private function getDirectionFromDegree($degree){
		$direction = array('北', '东北', '东', '东南', '南', '西南', '西' ,'西北');
		return $direction[floor((($degree*10+225)%3600)/450)];
	}
	
	/**
	 * 基准点为(0,0)，点(x,y)在相对基准点的方位（最北为0度，顺时针增加）
	 * @see http://www.php.net/manual/zh/function.atan2.php
	 */
	private function compass($x,$y) {
		if($x==0 AND $y==0){ return 0; } // ...or return 360
		return ($x < 0)
			? rad2deg(atan2($x,$y))+360      // TRANSPOSED !! y,x params
			: rad2deg(atan2($x,$y)); 
	}
	
	/**
	 * 根据两个location的GPS坐标距离和时间间隔计算速度（单位：公里/小时）
	 */
	private function getSpeed($location, $lastLocation) {
		$dist = $this->getDistance($location['gps_lat']+0, $location['gps_lng']+0, 
			$lastLocation['gps_lat']+0, $lastLocation['gps_lng']+0);
	
		$time = $this->getTimeDiff($lastLocation['time'], $location['time']);
		
		return $dist/$time * 3.6;
	}
	
	/**
	 * 计算两个时间之间的时间差（单位：秒）
	 */
	private function getTimeDiff($time1, $time2=null) {
		return isset($time2) ? 
			strtotime($time2) - strtotime($time1)
			: time() - strtotime($time1);
	}
	
	/**
	 * 计算两个经纬度坐标之间的距离（单位：米）。
	 */
	private function getDistance($lat1, $lng1, $lat2, $lng2) {
		return Geometry::geoPointDistancePoint($lat1, $lng1, $lat2, $lng2);
	}
	
	private function checkAlarm($device, $location) {
		$rules = $this->getRules($device);
		if(empty($rules)) return;
		foreach($rules as $rule) {
			$alarm = null;
			if($rule['type']=='区域') {
				$alarm = $this->checkAreaAlarm($device, $location, $rule);
			}
			else {
				$alarm = $this->checkPathAlarm($device, $location, $rule);
			}
		}
	}
	
	private function checkAreaAlarm($device, $location, $rule) {
//		Log::write("" .
//				"\ncheckAreaAlarm\n" .$rule['id'].
//				"\nRule:\n".print_r($rule, true)."" .
//				"\nDevice\n".print_r($device, true)."" .
//				"\nLocation\n".print_r($location, true) .
//				"", Log::INFO);
		$polygon = $this->getPathAreaPoints($rule['path_area_id']);
		
		//将有关数字转成数字形式（否则是字符串形式，某些比较运算会有问题的）
		$rule['speed'] += 0;
		$location['speed'] += 0;
		$rule['long'] += 0;
		$rule['distance'] += 0;
		
		$speedMsg = $this->getSpeedLimitAlarmMsg($location['speed'], $rule['speed']);
		
		if($rule['in_out'] == '进入') {
			if(Geometry::geoPointInPolygon2($location['baidu_lat'], $location['baidu_lng'], $polygon)) {
				if(empty($rule['long'])) {
					$msg = '按照规则['.$rule['label'].']，'.$device['target_type'].'['.$device['target_name'].']，' .
							'不允许在此时['.date('Y-m-d H:i:s').']进入区域['.$rule['path_area_label'].']，' .
							"而现在检测到目标在区域内（坐标：{$location['baidu_lat']}, {$location['baidu_lng']}）";
					$msg .= (empty($speedMsg) ? '': '，并且' . $speedMsg);
					return $this->startAlarm($device, $location, $rule, $msg);
				}
				else { //允许进入时间 不为0
					$longInSeconds = $rule['long'] * 60;
					while($previousLocation = $this->getPreviousLocation($device['id'], $location['id'])) {
						if(!Geometry::geoPointInPolygon2($previousLocation['baidu_lat'], $previousLocation['baidu_lng'], $polygon)) {
							return empty($speedMsg) ? null : $this->startSpeedAlarm($device, $location, $rule); //历史轨迹点不在区域内，不用报警（如果不超速的话）
						}
						
						$timeDiff = $this->getTimeDifference('now', $previousLocation['time']);
						if($timeDiff >= $longInSeconds) {
							$msg = '按照规则['.$rule['label'].']，'.$device['target_type'].'['.$device['target_name'].']，' .
									'不允许在此时['.date('Y-m-d H:i:s').']进入区域['.$rule['path_area_label'].']' .
									'超过'.$rule['long'].'分钟，' .
									"而现在检测到目标在区域内（坐标：{$location['baidu_lat']}, {$location['baidu_lng']}），" .
									"并且时间已经超过" . floor($timeDiff/60) . "分钟";
							$msg .= (empty($speedMsg) ? '': '，并且' . $speedMsg);
							return $this->startAlarm($device, $location, $rule, $msg);
						}
					}
					return empty($speedMsg) ? null : $this->startSpeedAlarm($device, $location, $rule); //已经 没有上一个定位了（时间仍不够长），不用报警
				}
			}
			else {//不允许进入，但是现在点不在区域内，仍有可能需要报警：如果当前点和上一个轨迹点的线段切割区域多边形的话。
				$previousLocation = $this->getPreviousLocation($device['id'], $location['id']);
				$timeDiff = floor($this->getTimeDifference('now', $previousLocation['time'])/60);
				
				if($timeDiff>=$rule['long'] 
					&& Geometry::geoSegmentCuttingPolygon(
										$previousLocation['baidu_lat'], $previousLocation['baidu_lng'],
										$location['baidu_lat'], $location['baidu_lng'], 
										$polygon)) 
				{
					$msg = '按照规则['.$rule['label'].']，'.$device['target_type'].'['.$device['target_name'].']，' .
							'不允许在此时['.date('Y-m-d H:i:s').']进入区域['.$rule['path_area_label'].']' .
							(empty($rule['long']) ? '' : '超过'.$rule['long'].'分钟')  .
							"，而现在检测到目标的当前位置与上一个定位点的轨迹路线经过该区域" .
							(empty($rule['long']) ? '' : '，并且两轨迹点时间间隔已经超过'.$timeDiff.'分钟');
					$msg .= (empty($speedMsg) ? '': '，并且' . $speedMsg);
					return $this->startAlarm($device, $location, $rule, $msg);
				}
				
				return null;
			}
		}
		else { //离开区域报警
			$distance = round(Geometry::geoPointDistancePolygon($location['baidu_lat'], $location['baidu_lng'], $polygon));
			if(!Geometry::geoPointInPolygon2($location['baidu_lat'], $location['baidu_lng'], $polygon) //定位点在区域外面
				&& $distance>=$rule['distance'])//定位点到区域的距离超过限制 
			{
				if(empty($rule['long'])) { // 时长限制为0
					$msg = '按照规则['.$rule['label'].']，'.$device['target_type'].'['.$device['target_name'].']，' .
							'不允许在此时['.date('Y-m-d H:i:s').']离开区域['.$rule['path_area_label'].']' . $rule['distance'] . '米，' .
							"而现在检测到目标在区域外（坐标：{$location['baidu_lat']}, {$location['baidu_lng']}），距离区域" . $distance . '米';
					$msg .= (empty($speedMsg) ? '': '，并且' . $speedMsg);
					return $this->startAlarm($device, $location, $rule, $msg);
				}
				else {//允许离开一段时间 
					$longInSeconds = $rule['long'] * 60;
					$curLocationId = $location['id'];
					while($previousLocation = $this->getPreviousLocation($device['id'], $curLocationId)) {
						$historyDist = Geometry::geoPointDistancePolygon($previousLocation['baidu_lat'], $previousLocation['baidu_lng'], $polygon);
						if(Geometry::geoPointInPolygon2($previousLocation['baidu_lat'], $previousLocation['baidu_lng'], $polygon)
							|| $historyDist<$rule['distance']) {//历史轨迹点在区域内或者离开距离不够
							return (empty($speedMsg) || $distance<$rule['distance']) ? null : $this->startSpeedAlarm($device, $location, $rule);
						}
						
						$timeDiff = $this->getTimeDifference('now', $previousLocation['time']);
						if($timeDiff >= $longInSeconds) {
							$msg = '按照规则['.$rule['label'].']，'.$device['target_type'].'['.$device['target_name'].']，' .
									'不允许在此时['.date('Y-m-d H:i:s').']离开区域['.$rule['path_area_label'].']'  . $rule['distance'] . '米，' .
									'超过'.$rule['long'].'分钟，' .
									"而现在检测到目标在区域外（坐标：{$location['baidu_lat']}, {$location['baidu_lng']}），" .
									"并且离开区域至少" . $rule['distance'] . '米' .
									"已经超过" . floor($timeDiff/60) . "分钟";
							$msg .= (empty($speedMsg) ? '': '，并且' . $speedMsg);
							return $this->startAlarm($device, $location, $rule, $msg);
						}
						$curLocationId = $previousLocation['id'];
					}
					return (empty($speedMsg) || $distance<$rule['distance']) ? null : 
						$this->startSpeedAlarm($device, $location, $rule); //已经 没有上一个定位了（时间仍不够长），不用报警
				}
			}
			return null;  //轨迹点在区域里面，不用报警
		}
	}
	
	private function startSpeedAlarm($device, $location, $rule) {
		$rule['speed'] += 0;
		$location['speed'] += 0;
		
		if(empty($rule['speed']) || $location['speed']<$rule['speed']) return null;
		
		$msg = '按照规则['.$rule['label'].']，'.$device['target_type'].'['.$device['target_name'].']，' .
				'在此时['.date('Y-m-d H:i:s').']在区域['.$rule['path_area_label'].']' .
				($rule['in_out']=='进入' ? '里' : '外') . '的速度不能超过'.$rule['speed'].'公里/小时，' .
				"而现在检测到目标在区域".($rule['in_out']=='进入' ? '内' : '外')."（坐标：{$location['baidu_lat']}, {$location['baidu_lng']}），" .
				"并且速度为" . $location['speed'] . "公里/小时";
		return $this->startAlarm($device, $location, $rule, $msg);
	}
	
	private function checkPathAlarm($device, $location, $rule) {
//		Log::write("" .
//				"\ncheckPathAlarm\n" .
//				"\nRule:\n".print_r($rule, true)."" .
//				"\nDevice\n".print_r($device, true)."" .
//				"\nLocation\n".print_r($location, true) .
//				"", Log::INFO);
		
		$polyline = $this->getPathAreaPoints($rule['path_area_id']);
		$rule['speed'] += 0;
		$location['speed'] += 0;
		$rule['long'] += 0;
		$rule['distance'] += 0;
		
		$speedMsg = $this->getSpeedLimitAlarmMsg($location['speed'], $rule['speed']);
		
		$distance = round(Geometry::geoPointDistancePolyline($location['baidu_lat'], $location['baidu_lng'], $polyline));
		
		if( $distance >= $rule['distance'] )//定位点到路径的距离超过限制 
		{
			if(empty($rule['long'])) { // 时长限制为0
				$msg = '按照规则['.$rule['label'].']，'.$device['target_type'].'['.$device['target_name'].']，' .
						'不允许在此时['.date('Y-m-d H:i:s').']离开路径['.$rule['path_area_label'].']' . $rule['distance'] . '米，' .
						"而现在检测到目标在路径外（坐标：{$location['baidu_lat']}, {$location['baidu_lng']}），距离路径" . $distance . '米';
				$msg .= (empty($speedMsg) ? '': '，并且' . $speedMsg);
				return $this->startAlarm($device, $location, $rule, $msg);
			}
			else {//允许离开一段时间 
				$longInSeconds = $rule['long'] * 60;
				$curLocationId = $location['id'];
				while($previousLocation = $this->getPreviousLocation($device['id'], $curLocationId)) {
					$historyDist = Geometry::geoPointDistancePolyline($previousLocation['baidu_lat'], $previousLocation['baidu_lng'], $polyline);
					if( $historyDist<$rule['distance'] ) {//历史轨迹点离开距离不够
						return (empty($speedMsg) || $distance<$rule['distance']) ? null : $this->startSpeedAlarm($device, $location, $rule);
					}
					
					$timeDiff = $this->getTimeDifference('now', $previousLocation['time']);
					if($timeDiff >= $longInSeconds) {
						$msg = '按照规则['.$rule['label'].']，'.$device['target_type'].'['.$device['target_name'].']，' .
								'不允许在此时['.date('Y-m-d H:i:s').']离开路径['.$rule['path_area_label'].']'  . $rule['distance'] . '米，' .
								'超过'.$rule['long'].'分钟，' .
								"而现在检测到目标在路径外（坐标：{$location['baidu_lat']}, {$location['baidu_lng']}），" .
								"并且离开路径至少" . $rule['distance'] . '米' .
								"已经超过" . floor($timeDiff/60) . "分钟";
						$msg .= (empty($speedMsg) ? '': '，并且' . $speedMsg);
						return $this->startAlarm($device, $location, $rule, $msg);
					}
					$curLocationId = $previousLocation['id'];
				}
				return (empty($speedMsg) || $distance<$rule['distance']) ? null : $this->startSpeedAlarm($device, $location, $rule); //已经 没有上一个定位了（时间仍不够长），不用报警
			}
		}		
		return null;  //轨迹点在区域里面，不用报警
	}
	
	private function getSpeedLimitAlarmMsg($speed, $speedLimit) {
		if(!empty($speedLimit) && $speed>=$speedLimit) {
			return '速度限制为' . $speedLimit . '公里/小时，目前定位对象的速度为' . $speed . '公里/小时，超过限制';
		}
		return '';
	}
	
	/**
	 * 获取指定的两个时间之间的时间差（秒）
	 */
	private function getTimeDifference($time1, $time2) {
		return abs(strtotime($time1) - strtotime($time2));
	}
	
	/**
	 * 查找指定的设备在指定定位id的前一个定位数据
	 */
	private function getPreviousLocation($device_id, $location_id) {
		$Location = M('Location');
		check_error($Location);
		
		$location = $Location->where("`device_id`='{$device_id}' AND `id`<'{$location_id}'")->order('`id` DESC')->find();
		check_error($Location);
		
		return $location;
	}
	
	/**
	 * 在数据库里插入一条告警记录，并启动报警“线程”
	 */
	private  function startAlarm($device, $location, $rule, $msg) {
		$alarm = array(
			'type' 		=> $rule['type'],
			'rule_id' 	=> $rule['id'],
			'start_time'=> date('Y-m-d H:i:s'),
			'msg' 		=> $msg,
			'department_id' => $device['department_id'],
			'target_type'=> $device['target_type'],
			'target_id'	 => $device['target_id'],
			'target_name'=> $device['target_name'],
			'device_id'	 => $device['id']
		);
		
		$Alarm = M('Alarm');
		check_error($Alarm);
		
		$id = $Alarm->add($alarm);
		check_error($Alarm);
		
		$this->startAlarmThread($id);
		
		return $alarm;
	}
	
	private function startAlarmThread($id) {
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
			
			$postdata = "id=$id";
			
			//发送get
			$crlf = "\r\n"; 
			$file = U('Alarm/thread');
			$header = "POST $file HTTP/1.1" . $crlf;
			$header .= "Host: {$_SERVER['HTTP_HOST']}" . $crlf;
			$header .= 'Content-Type: application/x-www-form-urlencoded' . $crlf; 
			$header .= 'Content-Length: '. strlen($postdata) . $crlf . $crlf;
			$header .= $postdata . $crlf; 
			$header .= "Connection: Close" . $crlf . $crlf;
			fwrite($fp, $header);
			fclose($fp); //不等结果，直接关闭
			
			return true;
		}
//		elseif(function_exists('stream_socket_client')) { //TODO可以用stream_socket_client
//			
//		}
		else {
			return true; //照样返回true，以便客户端可以读取错误进度信息。
		}
	}
	
	private function getPathAreaPoints($path_area_id) {
		$Point = M('Point');
		check_error($Point);
		
		$points = $Point->where("`path_area_id`='{$path_area_id}'")->order('`sequence` ASC')->select();
		check_error($Point);
		
		return $points;
	}
	
	/**
	 * 查找指定设备在当前时间下有效的报警规则
	 */
	private function getRules($device) {
		$Rule = M('Rule');
		check_error($Rule);
		
		$rules = $Rule->join('rule_target on `rule`.`id`=`rule_target`.`rule_id`')
				->join('path_area on `rule`.`path_area_id`=`path_area`.`id`')
				->where("( `rule_target`.`device_id`='{$device['id']}' " .
						"OR (`rule_target`.`target_type`='分组' AND `target_id`='{$device['department_id']}') ) " .
						"AND `rule`.`enable`='1' ")
				->field('`rule`.*, `path_area`.`label` AS `path_area_label`')
				->distinct(true)
				->select();
				
//		Log::write("\n".M()->getLastSql(), Log::SQL);
		
		if(!empty($rules) && is_array($rules)) {
			$Model = M();
			foreach ( $rules as $index => $rule ) {
				$result = $Model->query('SELECT ' . $rule['valid_time_sql'] );
				$result = array_values($result[0]);
				if(!$result[0]) unset($rules[$index]);
			}
		}
		
		return $rules;
	}
	
	public function test() {
		header('Content-Type:text/html; charset=utf-8');
		echo "<pre>";
//		print_r($this->getUnknowCellLocation(9534, 52270));
//		echo "上海 到 杭州  （应该是西南）：";
//		echo $this->getDirection(31.140590819105512, 121.46282306250009, 30.23361031151709, 120.10051837500009) ;
//		echo "<br>上海 到 舟山  （应该是东南）";
//		echo $this->getDirection(31.140590819105512, 121.46282306250009, 29.872255619728037, 122.27581134375009) ;
//		echo "<br>上海 到 宁波  （应该是南）";
//		echo $this->getDirection(31.140590819105512, 121.46282306250009, 29.83414169126136, 121.30901446875009) ;
//		echo "<br>上海 到 苏州  （应该是西）";
//		echo $this->getDirection(31.140590819105512, 121.46282306250009, 31.215786635922846, 120.58391681250009) ;
//		echo "<br>上海 到 苏州  （应该是西）";
//		echo $this->getDirection(31.140590819105512, 121.46282306250009, 31.215786635922846, 120.58391681250009) ;
//		echo "<br>上海 到 无锡  （应该是我也不知道）";
//		echo $this->getDirection(31.140590819105512, 121.46282306250009, 31.51597151671413, 120.25432696875009) ;
//		echo "<br>上海 到 南通  （应该是我也不知道）";
//		echo $this->getDirection(31.140590819105512, 121.46282306250009, 31.98308404297609, 120.84758868750009) ;
//		echo "<br>上海 到 烟台  （应该是北）";
//		echo $this->getDirection(31.140590819105512, 121.46282306250009, 37.41657267413561, 121.39690509375009) ;
//		$cc = new CoordinateConversion();
//		$p1 = $cc->latLngToUtmXY(23.141113089581847, 113.321597188076);
//		$p2 = $cc->latLngToUtmXY(23.141438653691957, 113.3283885413017);
//		echo $this->getDistance(23.141113089581847, 113.321597188076, 23.141438653691957, 113.3283885413017);
//		echo '<br>'.Geometry::pointDistancePoint($p1, $p2);
		echo "\n查Cell表\n";
		print_r($this->getBaiduCoordinate(23.034512,113.742584));
		echo "\n根据上传的GPS换成百度\n";
		print_r($this->getBaiduCoordinate(23.029947,113.747174));
//		var_dump($this->getLastLocation(2));

//		print_r($this->getRu	les(array('id'=>1, 'department_id'=>3)));

//		print_r(strtotime('21:23:32')."\n");
//		print_r(strtotime('2012-05-29 21:23:32'));
		
// 		$this->getUnknowCellLocation(42420, 172502323);
		
		
// 		var_dump(round((('0x61'+0)-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0))));
// 		var_dump(round((('0x62'+0)-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0))));
// 		var_dump(round((('0x63'+0)-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0))));
// 		var_dump(round((('0x64'+0)-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0))));
// 		var_dump(round((('0x65'+0)-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0))));
// 		var_dump(round((('0x66'+0)-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0))));
// 		var_dump(round((('0x67'+0)-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0))));
// 		var_dump(round((('0x68'+0)-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0))));
// 		var_dump(round((('0x69'+0)-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0))));
// 		var_dump(round((('0x6A'+0)-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0))));
// 		var_dump(round((('0x6B'+0)-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0))));
// 		var_dump(round((('0x6C'+0)-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0))));
// 		var_dump(round((('0x6D'+0)-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0))));
		
		echo "</pre>";
	}
}
?>