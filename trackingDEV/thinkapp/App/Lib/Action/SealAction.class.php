<?php
class SealAction extends Action{

	/**
	 * all操作返回所有电子铅封信息
	 */
	public function all() {
		$Seal = M('Eseal');
		check_error($Seal);

		//根据过滤条件和查询涉及的各个表之间，决定哪些字段有可能会模糊不清，在这里做定义
		$ambiguous = array( 
				'eseal_id' => 'eseal`.`eseal_id',	//注意格式：表名`.`字段名
				'bar_id' => 'eseal`.`bar_id'
				);

		$condition = '1';
		
		$filters = $_REQUEST['filter'];
		if(!empty($filters)) $condition .= ' AND (' . $this->_getFiltersCondition($filters, $ambiguous) . ')';
		
		$total = $Seal->join('`device` on `device`.`id`=`eseal`.`device_id`')
			->where($condition)
			->count();
		check_error($Seal);

		$Seal->join('`device` on `device`.`id`=`eseal`.`device_id`')
			->join('`eseal_log` on `eseal_log`.`id`=`eseal`.`last_log`')
			->field(array('`eseal`.`id`', '`eseal`.`eseal_id`', '`eseal`.`bar_id`', '`eseal`.`device_id`', 
					'`device`.`target_type`', '`device`.`target_id`', '`device`.`target_name`',
					'`eseal_log`.`local_time`' , '`eseal_log`.`msg_data`', '`eseal_log`.`time`',
					'`eseal_log`.`power`' , '`eseal_log`.`power_pct`', '`eseal_log`.`location`', 
					'`eseal_log`.`latitude`' , '`eseal_log`.`longitude`',
					'`eseal_log`.`speed_kn`' , '`eseal_log`.`speed_km`', 
					'`eseal_log`.`direction`' , '`eseal_log`.`direction_text`', 
					'`eseal_log`.`gmtime`' , '`eseal_log`.`counter_hex`', 
					'`eseal_log`.`counter`' , '`eseal_log`.`msg`'))
			->order('`local_time` DESC, `device_id` ASC')
			->where($condition);
			
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) $Seal->limit($limit)->page($page);

		$seals = $Seal->select();
		check_error($Seal);

		return_json(true,$total,'seals', $seals);
	}
	
	
	public function logs() {
		$SealLog = M('EsealLog');
		check_error($SealLog);

		//根据过滤条件和查询涉及的各个表之间，决定哪些字段有可能会模糊不清，在这里做定义
		$ambiguous = array( 
				'eseal_id' => 'eseal_log`.`eseal_id',	//注意格式：表名`.`字段名
				'bar_id' => 'eseal_log`.`bar_id'
				);
		
		$condition = '1';
		
		$filters = $_REQUEST['filter'];
		if(!empty($filters)) $condition .= ' AND (' . $this->_getFiltersCondition($filters, $ambiguous) . ')';
		
		$total = $SealLog->where($condition)->count();
		check_error($SealLog);
		
		$SealLog
			->join('`eseal` on `eseal_log`.`eseal_tb_id`=`eseal`.`id`')
			->join('`device` on `device`.`id`=`eseal`.`device_id`')
			->field(array('`eseal_log`.`id`', '`eseal_log`.`eseal_id`', '`eseal_log`.`bar_id`', '`eseal`.`device_id`',
				'`device`.`target_type`', '`device`.`target_id`', '`device`.`target_name`',
				'`eseal_log`.`local_time`' , '`eseal_log`.`msg_data`', '`eseal_log`.`time`',
				'`eseal_log`.`power`' , '`eseal_log`.`power_pct`', '`eseal_log`.`location`',
				'`eseal_log`.`latitude`' , '`eseal_log`.`longitude`',
				'`eseal_log`.`speed_kn`' , '`eseal_log`.`speed_km`',
				'`eseal_log`.`direction`' , '`eseal_log`.`direction_text`',
				'`eseal_log`.`gmtime`' , '`eseal_log`.`counter_hex`',
				'`eseal_log`.`counter`' , '`eseal_log`.`msg`'))
				->order('`local_time` DESC')
				->where($condition);
			
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) $SealLog->limit($limit)->page($page);
	
		$logs = $SealLog->select();
		//		Log::write(M()->getLastSql(), Log::SQL);
		check_error($SealLog);
	
		return_json(true,$total,'logs', $logs);
	}
	
	private function _getFiltersCondition($filters, $ambiguous=array()) {
		if (is_array($filters)) {
			$encoded = false;
		} else {
			$encoded = true;
			$filters = json_decode($filters);
		}
		
		$condition = '1';
		foreach ($filters as $filter) {
	        if ($encoded) {
	            $field = $filter->field;
	            $value = $filter->value;
	            $compare = isset($filter->comparison) ? $filter->comparison : null;
	            $filterType = $filter->type;
	        } else {
	            $field = $filter['field'];
	            $value = $filter['data']['value'];
	            $compare = isset($filter['data']['comparison']) ? $filter['data']['comparison'] : null;
	            $filterType = $filter['data']['type'];
	        }
	        
	        $field = (!empty($ambiguous) && !empty($ambiguous[$field])) ? $ambiguous[$field] : $field;
	        
			switch ($filterType) {
				case 'string':
					$condition .= " AND `{$field}` LIKE '%{$value}%'";
					break;
				case 'numeric': //comparison
					$comparison = $compare=='lt' ? '<=' : ($compare=='gt' ? '>=' : '=');
					$value +=0;
					$condition .= " AND `{$field}`{$comparison}{$value}";
					break;
				case 'list':
					$list = array();
					$hasNull = false;
					$values = is_array($value) ? $value : explode(',',$value);
					foreach ($values as $v) {
						$list[] = "'".$v."'";
						if($v=='') $hasNull = true;
					}
					if(!empty($list)) $condition .= " AND (`{$field}` IN (".implode(",", $list).")" . ($hasNull ? " OR `{$field}` IS NULL": '') . ")";
					break;
				case 'date':
					$comparison = $compare=='lt' ? '<=' : ($compare=='gt' ? '>=' : '=');
					$condition .= " AND DATE(`".$field."`) ".$comparison." '".date('Y-m-d',strtotime($value))."'";
					break;
				case 'boolean':
					$condition .= " AND `{$field}`='".($value=='true' ? 1 : 0)."'";
					break;
			}
		}
		
		return $condition;
	}
	
	
	public function addtest() {
		$lngmin = $_POST['lngmin'] + 0;
		$lngmax = $_POST['lngmax'] + 0;
		$latmin = $_POST['latmin'] + 0;
		$latmax = $_POST['latmax'] + 0;
		$addnumber = $_POST['addnumber'] + 0;
		
		if(!$this->_ping()) {
			return_value_json(false, 'msg', '似乎电子铅封连接失败，请尝试在系统设置里面重启电子铅封服务');
		}
		
		if(empty($addnumber)) {
			return_value_json(false, 'msg', '并发数不能为空或者为0');
		}
		
		if($latmin>=$latmax) {
			return_value_json(false, 'msg', '纬度设置不对');
		}
		
		if($lngmin>=$lngmax) {
			return_value_json(false, 'msg', '经度设置不对');
		}
		
		$sealtests = session('sealtests');
		if(empty($sealtests)) $sealtests = array('sealtests'=>array());
		
		$sealtests['heartbeat'] = time();
		
		$s = count($sealtests['sealtests']);
		$new_seals = array();
		for($i=0; $i<$addnumber; $i++){
			$seal = $this->_genSeal($latmin, $latmax, $lngmin, $lngmax);
			$seal['id'] = $s+$i;
			$sealtests['sealtests'][$s+$i] = $seal;
			
			$new_seals[] = $seal;
		}
		
		session('sealtests', $sealtests);
		
		foreach ($new_seals as $new_seal) {	
			$data = array(
					'id' => $new_seal['id'],
					'session_id' => session_id()
					);
			$postdata = http_build_query($data);
			$url = U('Seal/addtestthread');
			$this->_socketPost($postdata, $url);
		}
		
		return_value_json(true);
	}
	
	public function addtestthread() {
		$id = $_POST['id'] + 0;
		$session_id = $_POST['session_id'];
		
		session_id($session_id);
		$sealtests = session('sealtests');
		$this->_sendData($sealtests['sealtests'][$id]['msg_data']);
	}
	
	private function _sendData($buffer) {
		$setting = R('Setting/get', array(true));
		if(empty($setting['ESEAL_port']) || empty($setting['ESEAL_ip'])) return;
		
		$socket_client = stream_socket_client('tcp://'.$setting['ESEAL_ip'].':'.$setting['ESEAL_port'], $errno, $errstr, 30);
		fwrite($socket_client, $buffer);
		fclose($socket_client);
	}
	
	private function _ping() {
		$ping_ret = R('Setting/pingEsealConnection', array(true));
		return ($ping_ret!==false);
	}
	
	/**
	 * 生成一个新的电子铅封
	 */
	private function _genSeal($latmin, $latmax, $lngmin, $lngmax) {
		$latlng = $this->_genLocation($latmin, $latmax, $lngmin, $lngmax);
		return $this->_newSealData(
				null, 
				$this->_genNumber(12),
				'CNCIMA' . $this->_genNumber(11), 
				$latlng['lat'],
				$latlng['lng'],
				strtoupper(dechex(rand('0x61'+0, '0x6D'+0))),
				rand(0, 5000) / 100, //速度：0-50节之间的两位随机小数,
				rand(0, 36000) / 100, //方向：0-360之间的两位随机小数
				'0000', 
				'新添加');
	}
	
	private function _newSealData($id, $eseal_id, $bar_id, $lat, $lng, $power, $speed_kn, $direction, $counter_hex, $msg) {
		$newseal = array(
				'id' => $id,
				'eseal_id' => $eseal_id,
				'bar_id' => $bar_id,
				'time' => date('Y-m-d-H-i-s'),
				'power' => $power,
				'latitude' => number_format($lat, 6, ".", ""),
				'longitude' => number_format($lng, 6, ".", ""),
				'speed_kn' => $speed_kn,
				'direction' => $direction,
				'gmtime' => gmdate('ymdHis'),
				'counter_hex' => strtoupper(str_pad(dechex(('0x'.$counter_hex) + 1), 4, '0', STR_PAD_LEFT)),
				'msg' => $msg);
		$this->_updateMsgData($newseal);
		return $newseal;
	}
	
	private function _updateMsgData(&$seal) {
		$msg_data = "AT\r\nAT\r\n(eSeal-ID:".$seal['eseal_id']
			."*Bar-ID :".$seal['bar_id']
			."*Time :".$seal['time']
			."*POW :".$seal['power']
			."*GPS-EW:".$this->_getGpsEw($seal['latitude'], $seal['longitude'], $seal['speed_kn'], $seal['direction'])
			."*".$seal['gmtime']
			."*".$seal['counter_hex']
			."*)";
		$seal['msg_data'] = $msg_data;
	}
	
	//最终格式ddmm.mmmm,[N/S],ddmm.mmmm,[W/E],S.ss,D.dd
	private function _getGpsEw($lat, $lng, $speed_kn, $direction) {
		return $this->_getLatLngDm($lat) . ',' . ($lat+0<0 ? 'S': 'N') . ','
			.$this->_getLatLngDm($lng) . ',' . ($lng+0<0 ? 'W': 'E') . ','
			.number_format($speed_kn, 2, ".", "") . ','
			.number_format($direction, 2, ".", "");
	}
	
	private function _getLatLngDm($latlng){
		$d = floor($latlng); //度
		$m = 60*($latlng-$d); //分
		return $d . (($m<10 ? '0' : '') . number_format($m, 4, ".", ""));
	}
	
	private function _genNumber($len) {
		if($len>11) return $this->_genNumber(11) . $this->_genNumber($len-11);
		
		$max = str_repeat('9', $len) + 0;
		$num = mt_rand(0, $max);
		return str_pad($num, $len, '0', STR_PAD_LEFT);
	}
	
	private function _genLocation($latmin, $latmax, $lngmin, $lngmax) {
		$latrange = $latmax - $latmin;
		$lat = $latmin + $latrange * rand(0, 10000) / 10000;
		$lngrange = $lngmax - $lngmin;
		$lng = $lngmin + $lngrange * rand(0, 10000) / 10000;
		return array(
				'lat' => $lat,
				'lng' => $lng
				);
	}

	private function _socketPost($postdata, $url)  {
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
			$header = "POST $url HTTP/1.1" . $crlf;
			$header .= "Host: {$_SERVER['HTTP_HOST']}" . $crlf;
			$header .= 'Content-Type: application/x-www-form-urlencoded' . $crlf;
			$header .= 'Content-Length: '. strlen($postdata) . $crlf . $crlf;
			$header .= $postdata . $crlf;
			$header .= "Connection: Close" . $crlf . $crlf;
			fwrite($fp, $header);
			fclose($fp); //不等结果，直接关闭
		}
	}
	
	public function alltest() {
		$sealtests = session('sealtests');
		if(empty($sealtests)) $sealtests = array('sealtests'=>array());
		
		return_json(true,null,'sealtests', $sealtests['sealtests']);
	}
	
	public function locktest() {
		if(!$this->_ping()) {
			return_value_json(false, 'msg', '似乎电子铅封连接失败，请尝试在系统设置里面重启电子铅封服务');
		}
		
		$ids = $_POST['ids'];
		$ids = explode (",", $ids);
		$total = $locked = 0;
		foreach ($ids as $id) {
			if(is_numeric($id)){
				if($this->_lockSeal($id+0)){
					$locked++;
				}
				$total++;
			}
		}
		
		
		$return_data = array(
				'locked' => $locked,
				'total' => $total
				);
		return_value_json(true, "data", $return_data);
	}
	
	private function _lockSeal($id) {
		$sealtests = session('sealtests');
		if($id>=count($sealtests['sealtests'])) return false;
		$seal = $sealtests['sealtests'][$id];
		if($seal['bar_id']!='FFFFFFFFFFFFFFFFF') return false; //插入状态
		
		$newseal = $this->_newSealData(
				$seal['id'], 
				$seal['eseal_id'], 
				'CNCIMA'.$this->_genNumber(11), 
				$seal['latitude'],
				$seal['longitude'],
				$seal['power'],
				0,	//速度
				0,	//方向
				$seal['counter_hex'],
				'锁杆插入');
		$sealtests['sealtests'][$id] = $newseal;
		session('sealtests', $sealtests);
		
		$this->_sendData($newseal['msg_data']);
		
		return true;
	}
	
	
	public function unlocktest() {
		if(!$this->_ping()) {
			return_value_json(false, 'msg', '似乎电子铅封连接失败，请尝试在系统设置里面重启电子铅封服务');
		}
		
		$ids = $_POST['ids'];
		$ids = explode (",", $ids);
		$total = $locked = 0;
		foreach ($ids as $id) {
			if(is_numeric($id)){
				if($this->_unlockSeal($id+0)){
					$locked++;
				}
				$total++;
			}
		}
	
	
		$return_data = array(
				'unlocked' => $locked,
				'total' => $total
		);
		return_value_json(true, "data", $return_data);
	}
	
	private function _unlockSeal($id) {
		$sealtests = session('sealtests');
		if($id>=count($sealtests['sealtests'])) return false;
		$seal = $sealtests['sealtests'][$id];
		if($seal['bar_id']=='FFFFFFFFFFFFFFFFF') return false; //插入状态
		
		$newseal = $this->_newSealData(
				$seal['id'],
				$seal['eseal_id'],
				'FFFFFFFFFFFFFFFFF',
				$seal['latitude'],
				$seal['longitude'], 
				$seal['power'],
				0,	//速度
				0,	//方向
				$seal['counter_hex'],
				'锁杆拔出');
		
		$sealtests['sealtests'][$id] = $newseal;
		session('sealtests', $sealtests);
	
		$this->_sendData($newseal['msg_data']);
	
		return true;
	}
	
	public function locatetest() {
		if(!$this->_ping()) {
			return_value_json(false, 'msg', '似乎电子铅封连接失败，请尝试在系统设置里面重启电子铅封服务');
		}
		
		$lngmin = $_POST['lngmin'] + 0;
		$lngmax = $_POST['lngmax'] + 0;
		$latmin = $_POST['latmin'] + 0;
		$latmax = $_POST['latmax'] + 0;
		
		if($latmin>=$latmax) {
			return_value_json(false, 'msg', '纬度设置不对');
		}
		
		if($lngmin>=$lngmax) {
			return_value_json(false, 'msg', '经度设置不对');
		}
		
		$ids = $_POST['ids'];
		$ids = explode (",", $ids);
		$total = $locked = 0;
		foreach ($ids as $id) {
			if(is_numeric($id)){
				if($this->_locateSeal($id+0, $latmin, $latmax, $lngmin, $lngmax)){
					$locked++;
				}
				$total++;
			}
		}
	
	
		$return_data = array(
				'relocated' => $locked,
				'total' => $total
		);
		return_value_json(true, "data", $return_data);
	}
	
	private function _locateSeal($id, $latmin, $latmax, $lngmin, $lngmax) {
		$sealtests = session('sealtests');
		if($id>=count($sealtests['sealtests'])) return false;
		
		$seal = $sealtests['sealtests'][$id];
		
		$latlng = $this->_genLocation($latmin, $latmax, $lngmin, $lngmax);
		
		$newseal = $this->_newSealData(
				$seal['id'],
				$seal['eseal_id'],
				$seal['bar_id'],
				$latlng['lat'],
				$latlng['lng'],
				strtoupper(dechex(rand('0x61'+0, '0x6D'+0))),
				rand(0, 5000) / 100, //速度：0-50节之间的两位随机小数,
				rand(0, 36000) / 100, //方向：0-360之间的两位随机小数
				$seal['counter_hex'],
				'变更位置');
		
		$sealtests['sealtests'][$id] = $newseal;
		session('sealtests', $sealtests);
	
		$this->_sendData($newseal['msg_data']);
	
		return true;
	}
	
	public function test() {
// 		echo str_pad(dechex(('0x'.'00da') + 1), 4, '0', STR_PAD_LEFT);
		$fvalue = '0,,1';
						$list = array();
						$values = explode(',',$fvalue);
						foreach ($values as $value) {
								$list[] = "'".$value."'";
						}
		if(!empty($list)) dump($list);
		else echo 'empty';
	}
	
	
	
}