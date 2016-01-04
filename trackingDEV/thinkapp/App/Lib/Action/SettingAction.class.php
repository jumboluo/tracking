<?php
// import('App.Util.EpollSocketServer');

class SettingAction extends Action {
	/**
	 * 获取设置参数
	 */
	public function get($asArray=false) {
		$Setting = M('Setting');
		check_error($Setting);
		
		$settings = $Setting->select();
		if($asArray)  return empty($settings) ? null : $settings[0];
		else return_value_json(true, 'settings', $settings);
	}
	
	/**
	 * 保存参数设置
	 */
	public function update() {
		$Setting = M('Setting');
		check_error($Setting);
		
		$hasData = ($Setting->count() > 0);
		
		$Setting->create();
		
		$result = $hasData ? $Setting->where('1')->save() : $Setting->add();
		if($result === false) {
			//TODO Log
			return_value_json(false, 'msg', get_error($Setting));
		}
		//TODO Log
		return_value_json(true);
	}
	
	/**
	 * 短信平台注册
	 */
	public function smslogin() {
		$Sms = D('Sms');
		$error = $Sms->login();
		if(empty($error)) return_value_json(true);
		else return_value_json(false, 'msg', $error); 
	}
	
	/**
	 * 短信平台注销
	 */
	public function smslogout() {
		$Sms = D('Sms');
		$error = $Sms->logout();
		if(empty($error)) return_value_json(true);
		else return_value_json(false, 'msg', $error); 
	}
	
	public function smstest() {
		$mobile = $_POST['mobile'] + 0;
		if(empty($mobile)) {
			return_value_json(false, 'msg', '手机号码不能为空');
		}
		
		$user = session('user');
		if(empty($user) || empty($user['userId'])){
			return_value_json(false, 'msg', '测试手机短信必须为登陆用户');
		}
		
		$data = array(array(
					'type' => '测试',
					'related_id' => 0,
					'user_id' => $user['userId'],
					'mobile' => $mobile,
					'content' => '短信测试',
					'send_time' => date('Y-m-d H:i:s')
				));
		
		$Sms = D('Sms');
		$Sms->send($data);
		
		if($data[0]['success']) {
			return_value_json(true);
		}
		else {
			return_value_json(false, 'msg', empty($data[0]['result']) ? '短信平台没有返回结果或者系统无法确认结果。' : $data[0]['result']);
		}
		
	}
	
	public function emailtest() {
		$email = $_POST['email'];
		if(empty($email)) {
			return_value_json(false, 'msg', '电子邮件地址不能为空');
		}
		
		if(!is_email_well_form($email)) {
			return_value_json(false, 'msg', '电子邮件地址不正确');
		}
		
		$user = session('user');
		if(empty($user) || empty($user['userId'])){
			return_value_json(false, 'msg', '测试电子邮件发送必须为登陆用户');
		}
		$data = array(array(
					'type' => '测试',
					'related_id' => 0,
					'user_id' => $user['userId'],
					'email' => $email,
					'title' => '邮件测试',
					'content' => '这是一个用于测试邮件发送是否成功的邮件，不需要回复。',
					'send_time' => date('Y-m-d H:i:s')
				));
		
		$Email = D('Email');
		check_error($Email);
		
		$Email->send($data);
		
		if($data[0]['success']) {
			return_value_json(true);
		}
		else {
			return_value_json(false, 'msg', $data[0]['error_msg']);
		}
	}
	
	/**
	 * 启用或者停用电子铅封
	 */
	public function enableeseal() {
		$enable = $this->_post('ESEAL_enable') + 0;
		$setting = $this->get(true);
		
		if(!empty($setting['ESEAL_enable']) && $enable==1){ //要启用已经启用
			return_value_json(true, 'action', '启用');
		}
		else if(empty($setting['ESEAL_enable']) && $enable==0){ //要停用已经停用
			return_value_json(true, 'action', '停用');
		}
		
		if(empty($setting['ESEAL_ip']) || empty($setting['ESEAL_port'])) {
			return_value_json(false, 'msg', '电子铅封ip或者端口还没保存');
		}
		
		if (!extension_loaded('libevent')) {
			return_value_json(false, 'msg', "请先安装libevent扩展库。");
		}
		
		if ($setting['ESEAL_port'] < 1024) {
			return_value_json(false, 'msg', "端口号不应小于1024。");
		}
		
		//保存数据库
		M()->execute("UPDATE `setting` SET `ESEAL_enable`=$enable WHERE 1");
		
		//如果是启用，则启动线程（如果不是启用，那么将在一秒内停用）
		if($enable==1) { //启用
			$this->_startESeal();
			return_value_json(true, 'action', '启用');
		}
		else {
			$this->_stopESeal();
			return_value_json(true, 'action', '停用');
		}
	}
	
	private function _startESeal() {
		$url = U('Setting/thread');
		$postdata = "";
		$this->_socketPost($postdata, $url);
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
	
	//本函数连接一下socket，以便thread()函数能及时跳出循环
	private function _stopESeal() {
		if(!function_exists('socket_create') 
			|| !function_exists('socket_connect')
			|| !function_exists('socket_write')
			|| !function_exists('socket_close')) 
			return;
		
		if(APP_DEBUG===true) Log::write("_stopESeal", Log::INFO);
		
		$setting = $this->get(true);
		if(empty($setting['ESEAL_port']) || empty($setting['ESEAL_ip'])) return;
		
		$socket_client = stream_socket_client('tcp://'.$setting['ESEAL_ip'].':'.$setting['ESEAL_port'], $errno, $errstr, 30);
		if(!$socket_client) return;
		
		fwrite($socket_client, "QUIT");
		fclose($socket_client);
		
		//old_version
// 		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
// 		$connection = socket_connect($socket, '0.0.0.0', $setting['ESEAL_port']);
// 		if(!socket_write($socket, "quiting...\r\n")) return;
// 		socket_close($socket);
	}
	
	/////////////////以下为电子铅封内部实现//////////////////
	
	private static $connections;
	private static $buffers;
	private static $base;
	private static $socket_server;
	
	public function thread() {
		$setting = $this->get(true);
		if(empty($setting['ESEAL_enable']) || empty($setting['ESEAL_port']) || empty($setting['ESEAL_ip'])) return;
		
		set_time_limit(0);
		
		self::$socket_server = stream_socket_server("tcp://0.0.0.0:".$setting['ESEAL_port'], $errno, $errstr);
		if(!self::$socket_server) {
			Log::write("Socket Error: ".$errno.", Msg: ".$errstr, Log::INFO);
			return;
		}
		stream_set_blocking(self::$socket_server, 0); // 非阻塞
		
		if(APP_DEBUG===true) Log::write('eSeal online. 电子铅封启动就绪。', Log::INFO);
		
		self::$connections = array();
		self::$buffers = array();
		
		self::$base = event_base_new();
		$event = event_new();
		event_set($event, self::$socket_server, EV_READ | EV_PERSIST, array(__CLASS__, 'ev_accept'), self::$base);
		event_base_set($event, self::$base);
		event_add($event);
		event_base_loop(self::$base);
	}
	
	function ev_accept($socket, $flag, $base)
	{
		static $id = 0;
	
		$connection = stream_socket_accept($socket);
		stream_set_blocking($connection, 0);
	
		$id++; // increase on each accept
	
		$buffer = event_buffer_new($connection, array(__CLASS__, 'ev_read'), array(__CLASS__, 'ev_write'), array(__CLASS__, 'ev_error'), $id);
		event_buffer_base_set($buffer, $base);
		event_buffer_timeout_set($buffer, 3600, 3600);
		event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
		event_buffer_priority_set($buffer, 10);
		event_buffer_enable($buffer, EV_READ | EV_PERSIST);
	
		// we need to save both buffer and connection outside
		self::$connections[$id] = $connection;
		self::$buffers[$id] = $buffer;
	}
	
	function ev_error($buffer, $error, $id)
	{
		if(APP_DEBUG===true) Log::write("eSeal[$id] " . __METHOD__ . ' > connection closed.', Log::INFO);
		event_buffer_disable(self::$buffers[$id], EV_READ | EV_WRITE);
		event_buffer_free(self::$buffers[$id]);
		fclose(self::$connections[$id]);
		unset(self::$buffers[$id], self::$connections[$id]);
	}
	
	function ev_read($buffer, $id)
	{
		$data = '';
		while ($read = event_buffer_read($buffer, 1024)) {
			$data .= $read;
		}
		if(APP_DEBUG===true) Log::write("eSeal[$id] " . __METHOD__ . " > " . $data, Log::INFO);
		
		if($data=='QUIT') {
			$this->_closeConnections();
		}
		else if($data=='PING') { //ping的时候服务器返回服务器的时间
			event_buffer_write($buffer, date('Y-m-d H:i:s'));
		}
		else {
			$this->_processSeal($data);
		}
	}
	
	function _closeConnections() {
		if(APP_DEBUG===true) Log::write('eSeal offline. 电子铅封退出。', Log::INFO);
		foreach (self::$buffers as $key => $buf) {
			event_buffer_disable($buf, EV_READ | EV_WRITE);
			event_buffer_free($buf);
			unset(self::$buffers[$key]);
		}
		foreach (self::$connections as $key => $conn) {
			fclose($conn);
			unset(self::$connections[$key]);
		}
		event_base_loopexit(self::$base);
		event_base_free(self::$base);
		fclose(self::$socket_server);
	}
	
	function ev_write($buffer, $id)
	{
		if(APP_DEBUG===true) Log::write("eSeal[$id] " . __METHOD__. ' : ' . $buffer ,Log::INFO);
	}
	
	//-----------20130318增加 开始----------
	public function processSeal() {
		if(!$this->isPost() || empty($_POST['data'])) return;
		$this->_processSeal($_POST['data']);
	}
	//-----------20130318增加 结束----------
	
	
	private function _processSeal($buffer){
		$a = explode("*", $buffer);
		if(count($a)<7) return;
		
		$eseal_log['eseal_id'] = $data['label'] = substr(strstr($a[0], ':'), 1);
		$eseal_log['bar_id'] = $data['bar_id'] = substr(strstr($a[1], ':'), 1);
		
		$eseal_log['eseal_tb_id'] = $this->_findEseal($eseal_log);
		if(empty($eseal_log['eseal_tb_id'])) return;
		
		$this->_addEsealIfNotExist($eseal_log['eseal_id']);
		
		$eseal_log['msg_data'] = $buffer;
		
		//time
		$time = explode("-" , substr(strstr($a[2], ':'), 1));
		if(count($time)<6) return;
		$eseal_log['time'] = $data['time'] = $time[0] . '-' . $time[1] . '-' . $time[2] . ' ' . $time[3] . ':'  . $time[4] . ':'  . $time[5];
		
		//pow
		$eseal_log['power'] = $data['battery'] = substr(strstr($a[3], ':'), 1);
		$b = ('0x' . $eseal_log['power']) + 0;
		$eseal_log['power_pct'] = round(($b-('0x61'+0))*100/(('0x6D'+0)-('0x61'+0)));
		
		//gps-ew
		$eseal_log['location'] = substr(strstr($a[4], ':'), 1);
		$gps = explode("," , substr(strstr($a[4], ':'), 1));
		//lat
		$du = floor($gps[0]/100);
		$fen = $gps[0] - $du*100;
		$data['lat'] = $du + $fen/60;
		if($gps[1]=='S'||$gps[1]=='s') $data['lat'] *= -1;
		$eseal_log['latitude'] = $data['lat'];
		//lng
		$du = floor($gps[2]/100);
		$fen = $gps[2] - $du*100;
		$data['lng'] = $du + $fen/60;
		if($gps[3]=='W'||$gps[3]=='w') $$data['lng'] *= -1;
		$eseal_log['longitude'] = $data['lng'];
		
//		if(empty($data['lat']) && empty($data['lng'])) return;
		
		//speed
		$eseal_log['speed_kn'] = $gps[4];
		$eseal_log['speed_km'] = $data['speed'] = $gps[4] * 1.852; //把节换算成公里
		
		//direction
		$eseal_log['direction'] = $data['direction'] = $gps[5] + 0 ;
		$direction = array('北', '东北', '东', '东南', '南', '西南', '西' ,'西北');
		$eseal_log['direction_text'] = $direction[floor((($data['direction']*10+225)%3600)/450)];
		
		//gmtime
		$eseal_log['gmtime'] = $a[5];
		
		//counter
		$eseal_log['counter_hex'] = $a[6];
		$eseal_log['counter'] = ('0x' . $a[6]) + 0;
		
		$this->_saveEsealLog($eseal_log);
		
		$postdata = http_build_query($data);
		$url = U('Interface/eseal');
		$this->_socketPost($postdata, $url);
	}
	
	private function _addEsealIfNotExist($eseal_id) {
		$Container = M('Container');
		check_error($Container);
		$container = $Container->where(array('eseal_id' => $eseal_id))->find();
		if(empty($container)) {
			$container_data = array(
					'number' => '电子铅封' . $eseal_id,
					'eseal_id' =>  $eseal_id
					);
			$container_id = $Container->add($container_data);
		}
		
		$Device = M('Device');
		$device = $Device->where(array('label'=> $eseal_id))->find();
		if(empty($device)) {
			$data = array(
					'type' => '电子铅封',
					'label' => $eseal_id,
					'interval' => 15,
					'delay' => 15,
					'target_type' => '集装箱',
					'target_id' => empty($container_id) ? '0' : $container_id,
					'target_name' => '电子铅封' . $eseal_id
					);
			$Device->add($data);
		}
	}
	
	private function _findEseal($data) {
		$Eseal = M('Eseal');
		
		$seal = $Eseal->where("`eseal_id`='{$data['eseal_id']}'")->find();
		if(empty($seal)){ //没找到，新的，添加
			$id = $Eseal->add($data);
		}
		else {
			$id = $seal['id'];
		}
		return $id;
	}
	
	private function _saveEsealLog($eseal_log) {
		$EsealLog = M('EsealLog');
		check_error($EsealLog);
		
		$last = $EsealLog->where("`eseal_tb_id`='{$eseal_log['eseal_tb_id']}'")->order('`id` DESC')->find();
		if(empty($last)) {
			$eseal_log['msg'] = '新发现';
		}
		else if($last['bar_id'] != $eseal_log['bar_id']) {
			$eseal_log['msg'] = ($last['bar_id'] == 'FFFFFFFFFFFFFFFFF') ? '锁杆插入' : '锁杆拔除';
		}
		else {
			$eseal_log['msg'] = '定位';
		}
		
		$eseal_log['local_time'] = date('Y-m-d H:i:s');
		$last_log = $EsealLog->add($eseal_log);
		
		$Eseal = M('Eseal');
		$Eseal->where("`eseal_id`='{$eseal_log['eseal_id']}'")
			->save(array(
					'bar_id' => $eseal_log['bar_id'],
					'last_log' => $last_log
					));
	}
	
	public function pingEsealConnection($return=false) {
		$ret = false;
		
		$setting = $this->get(true);
		if(!empty($setting['ESEAL_port']) && !empty($setting['ESEAL_ip'])) {

			$socket_client = stream_socket_client('tcp://'.$setting['ESEAL_ip'].':'.$setting['ESEAL_port'], $errno, $errstr, 30);

			if($socket_client!==false) {
				fwrite($socket_client, 'PING');
				$ret = fread($socket_client, 1024);
				fclose($socket_client);
				$ret = (strtotime($ret)===false) ? false : $ret;
			}
		}
		
		if($return) return $ret;
		
		if($ret===false)  {
			return_value_json(false, 'msg', '服务器在两秒内没有回复，也许连接失败');
		}
		else {
			return_value_json(true, 'data', $ret);
		}
	}
	
	public function test4() {
		dump(strtotime(null));
	}
	
	public function test2() {
		$buffer = 'AT
AT
(eSeal-ID:000160001234*Bar-ID:CNCIMA00000154235*Time:2012-08-24-14-22-23*POW:6D*GPS-EW:2228.7814,N,11354.3361,E,1.27,176.25*120825160146*00D1*)';
		
		$setting = $this->get(true);
		if(empty($setting['ESEAL_port']) || empty($setting['ESEAL_ip'])) return;
		
		$socket_client = stream_socket_client('tcp://'.$setting['ESEAL_ip'].':'.$setting['ESEAL_port'], $errno, $errstr, 30);
		for ($i=0; $i<5; $i++){
			fwrite($socket_client, $buffer);
			sleep(5);
		}
		fclose($socket_client);
	}
	
	public function test() {
		$fp = fsockopen('61.138.95.250', 8040, $errno, $errstr, 30);
		if($fp===FALSE) {
			die();
		}
		
		//设置流为非阻塞型
		if (!stream_set_blocking($fp, 0)) {
			die();
		}
		
		//发送get
		$crlf = "\r\n";
		$header = "POST /thinkapp/index.php/Path/delete" . $crlf;
		$header .= "Host: 61.138.95.250" . $crlf;
		$header .= 'Content-Length: '. strlen($postdata) . $crlf . $crlf;
		$header .= $postdata . $crlf;
		$header .= "Connection: Close" . $crlf . $crlf;
		fwrite($fp, $header);
		fclose($fp); //不等结果，直接关闭
	}
	
	public function test3() {
		$data = '(eSeal-ID:000160000213*Bar-ID:FFFFFFFFFFFFFFFFF*Time:2012-10-15-15-43-05*POW:6C*GPS-EW:0000.0000,0,00000.0000,0,00.00,000.00*000000001460*0012*)';
		$this->_processSeal($data);
	}
}
?>