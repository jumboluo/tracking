<?php
/**
 * 基站数据导入Action
 * 注：导入前已经建立了基站数据表（并且可能有部分数据）
 */
class ImportAction extends Action {
	public function _initialize() {
		$CellDao = M('Cell');
		
		$dbError  = $CellDao->getDbError();
		if(!empty($dbError)) {
			return_value_json(false, 'msg', '数据库错误：' . $dbError);
		}
		
		$modelError = $CellDao->getError();
		if(!empty($modelError)) {
			return_value_json(false, 'msg', '数据库错误：' . $modelError);
		}
	}
	
	/**
	 * 获取数据库里基站数据表的最后id（以便确定从哪个记录开始导入）
	 */
	public function lastid() {
		$CellDao = M('Cell');
		$lastid = $CellDao->order('`id` DESC')->limit(1)->getField('id') + 0;
		return_value_json(true, 'lastid', $lastid);
	}
	
	public function import() {
		$CellDao = M('Cell');
				
		$data_json = get_magic_quotes_gpc() ? stripslashes($_POST['data']) : $_POST['data'];
		$data = json_decode($data_json);
		
		if($data) {
			$result = $CellDao->addAll($data);
			if( $result === false ){
				$dbError  = $CellDao->getDbError();
				$modelError = $CellDao->getError();
				$error = empty($dbError) && empty($modelError) ? '未知错误' : ( empty($dbError) ? $modelError : $dbError );
				Log::write("出错了" . $error);
				return_value_json(false, 'msg', '插入数据出错：' . $error);
			}
			else {
				return_value_json(true);
			}
		}
		else {
			Log::write("数据格式不正确：" . $data_json);
			return_value_json(false, 'msg', '数据格式不正确');
		}
	}
}
?>