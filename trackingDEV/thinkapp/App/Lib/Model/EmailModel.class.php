<?php

class EmailModel extends Model{
	public function log($data) {
		if(false===$this->addAll($data)) {
			return_value_json(false, 'msg', get_error($this));
		} 
	}
	
	public function send(&$data) {
		if(empty($data) || !is_array($data)) return;
		
		$Setting = D('Setting');
		$settings = $Setting->getSettings();
		$from = $settings['EMAIL_from'];
		
		require_once 'class.phpmailer.php';
		
		try{
			$mail = new PHPMailer(true); //New instance, with exceptions enabled
			
			$mail->CharSet	='UTF-8'; //设置邮件的字符编码，这很重要，不然中文乱码

			if($settings['EMAIL_smtp_enable']){
				$mail->IsSMTP();
				$mail->SMTPAuth   = $settings['EMAIL_smtp_auth']==1;
				$mail->Port       = $settings['EMAIL_smtp_port'];
				$mail->Host       = $settings['EMAIL_smtp_host'];
				$mail->Username   = $settings['EMAIL_smtp_username'];
				$mail->Password   = $settings['EMAIL_smtp_password'];
			
				$from = empty($from) ? $settings['EMAIL_smtp_username'] : $from;
			}

			$mail->AddReplyTo($from,"定位监控系统");
			$mail->From       = $from;
 			$mail->FromName   = "定位监控系统";

			foreach ($data as $value) {
 				if(is_email_well_form($value['email']))
 					$mail->AddAddress($value['email']);
			}

			$mail->Subject  = $value['title'];
			$mail->AltBody = $value['content'];
			$mail->MsgHTML( $value['content'] );
			$mail->WordWrap	= 80; // set word wrap
			$mail->IsHTML(true); // send as HTML
			
			$mail->Send();
			
			foreach ($data as $key => $value) {
				$data[$key]['success'] = '1';
				$data[$key]['error_msg'] = '';
			}
		}
		catch (phpmailerException $e) {
			foreach ($data as $key => $value) {
				$data[$key]['success'] = '0';
				$data[$key]['error_msg'] = $e->errorMessage();
			}
		}
		
		$this->log($data);
	}
}
?>