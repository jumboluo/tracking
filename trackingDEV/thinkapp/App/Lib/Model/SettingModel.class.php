<?php
class SettingModel extends Model{
	
	public function getSessionKey() {
		$sysSettings = $this->where('1')->find();
		if(empty($sysSettings)) return false;
	
		if(empty($sysSettings['SMS_sessionKey'])){
			$sysSettings['SMS_sessionKey'] = $this->_generateSessionKey();
			$this->where('1')->save($sysSettings);
		}
		return $sysSettings['SMS_sessionKey'];
	}
	
	public function unsetSessionKey() {
		$this->execute("UPDATE `setting` SET `SMS_sessionKey`='' WHERE 1");
	}
	
	public function updateLogined($value) {
		$this->execute("UPDATE `setting` SET `SMS_logined`='{$value}' WHERE 1");
	}
	
	private function _generateSessionKey() {
		return rand(100000,999999);
	}
	
	public function getSettings() {
		$sysSettings = $this->where('1')->find();
		if(empty($sysSettings)) return false;
		
		return $sysSettings;
	}
}
?>