<?php

class SmsAction {
	public function log($data) {
		$Sms = M('Sms');
		check_error($Sms);
		
		$Sms->create($data);
		check_error($Sms);
		
		if(false===$Sms->add()) {
			return_value_json(false, 'msg', get_error($Sms));
		} 
	}
	
	public function test() {
		$Sms = D('Sms');
		$data = array(
				);
		$Sms->send($data);
	}
}
?>