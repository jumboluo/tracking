<?php

class SmsModel extends Model{
	public function log($data) {
		if(false===$this->addAll($data)) {
			return_value_json(false, 'msg', get_error($this));
		} 
	}
	
	
	//根据data发送短信
	public function send(&$data) {
		if(empty($data) || !is_array($data)) return;
		
		$mobiles = array();
		foreach ($data as $value) {
			$mobiles[] = $value['mobile'];
		}
		
		$Setting = D('Setting');
		$settings = $Setting->getSettings();
		
 		$client = $this->_getClient();
		$re = $client->sendSMS($mobiles, $value['content'] . '【' . $settings['SMS_eName'] . '】');
	
		$msg = $this->_getSmsErrorMsg($re);
		if(!empty($msg)) {
			foreach ( $data as $key => $d ) {
				$data[$key]['success'] = 0;
				$data[$key]['result']	= $msg;
			}
		}
		if(!empty($data)) $this->log($data); 
	}
	
	public function login() {
		$Setting = D('Setting');
		$settings = $Setting->getSettings();
		
 		$client = $this->_getClient();
 		
		$re = $client->login();
		$msg = $this->_getSmsErrorMsg($re);
		if($msg===false) {
			return '注册序列号时出错：参数不正确';
		}
		if(!empty($msg)) {
			return '注册序列号时出错。<br>'. $msg;
		}
		
		$re = $client->registDetailInfo(
				$settings['SMS_eName'], 
				$settings['SMS_linkMan'], 
				$settings['SMS_phoneNum'], 
				$settings['SMS_mobile'], 
				$settings['SMS_email'], 
				$settings['SMS_fax'], 
				$settings['SMS_address'], 
				$settings['SMS_postcode']);
		
		$msg = $this->_getSmsErrorMsg($re);
		if($msg===false) {
			return '注册企业信息时出错：企业信息不符合要求';
		}
		if(!empty($msg)) {
			return '注册企业信息时出错。<br>'. $msg;
		}
		
		$Setting->updateLogined(1);
	}
	
	public function logout() {
		$client = $this->_getClient();
 		
		$re = $client->logout();
		$msg = $this->_getSmsErrorMsg($re);
		if($msg===false) {
			return '注销时出错：参数不正确';
		}
		if(!empty($msg)) {
			return '注销时出错。<br>'. $msg;
		}
		
		$Setting = D('Setting');
		$Setting->unsetSessionKey();
		$Setting->updateLogined(0);
	}
	
	private function _getClient() {
		$Setting = D('Setting');
		$settings = $Setting->getSettings();
		
		$gwUrl = 'http://sdkhttp.eucp.b2m.cn/sdk/SDKService?wsdl';
		$serialNumber = $settings['SMS_serialNumber'];
		$password = $_SESSION['SMS_password'];
		$sessionKey = $Setting->getSessionKey();
		$connectTimeOut = 2;
		$readTimeOut = 10;
		$proxyhost = false;
		$proxyport = false;
		$proxyusername = false;
		$proxypassword = false;
		
		require_once 'emay.php';
		
		$client = new EmayClient($gwUrl,$serialNumber,$password,$sessionKey,$proxyhost,$proxyport,$proxyusername,$proxypassword,$connectTimeOut,$readTimeOut);
		$client->setOutgoingEncoding("UTF-8");
		return $client;
	}
	
	private function _getSmsErrorMsg($code) {
		$pre = '错误码：' . $code. '<br>错误信息：';
		switch ($code + 0) {
			case 0:
				return 0;
			case 304:
				return $pre . '客户端发送三次失败';
			case 305:
				return $pre . '服务器返回了错误的数据，原因可能是通讯过程中有数据丢失';
			case 307:
				return $pre . '发送短信目标号码不符合规则，手机号码必须是以0、1开头';
			case 308:
				return $pre . '非数字错误，修改密码时如果新密码不是数字那么会报308错误';
			case 3:
				return $pre . '连接过多，指单个节点要求同时建立的连接数过多';
			case 10:
				return $pre . '客户端注册失败';
			case 11:
				return $pre . '企业信息注册失败';
			case 12:
				return $pre . '查询余额失败';
			case 13:
				return $pre . '查询余额失败';
			case 14:
				return $pre . '手机转移失败';
			case 15:
				return $pre . '手机扩展转移失败';
			case 16:
				return $pre . '取消转移失败';
			case 17:
				return $pre . '发送信息失败';
			case 18:
				return $pre . '发送定时信息失败';
			case 22:
				return $pre . '注销失败';
			case 27:
				return $pre . '查询单条短信费用错误码';
			case 101:
				return $pre . '客户端网络故障';
				
			case -1: 
				return false;
			case 997:
				return $pre . '平台返回找不到超时的短信，该信息是否成功无法确定';
			case 998:
				return $pre . '由于客户端网络问题导致信息发送超时，该信息是否成功下发无法确定';
			case 999:
				return $pre . '操作频繁';
				
			default:
				return $pre . '未知的错误码：' . $code;
		}
	}
}
?>