<?php

class AreaAction extends Action {

	public function all() {
		$PathArea = M('PathArea');
		check_error($PathArea);
		
// 		$total = $PathArea->where("`type`='区域'")->count();
// 		check_error($PathArea);
		
		$PathArea->join('`point` on `point`.`path_area_id`=`path_area`.`id`')
			->field(array('`path_area_id`', 'type', 'label', 'memo', 'distance', 'area',
						'`point`.`id`'=>'point_id', 'latitude', 'longitude', 'sequence'
					))
			->where("`type`='区域'")
			->order('`path_area`.`id` ASC, `sequence` ASC');
		
// 		$page = $_REQUEST['page'] + 0;
// 		$limit = $_REQUEST['limit'] + 0;
// 		if($page && $limit) $PathArea->limit($limit)->page($page);
		
		$areasAndPoints = $PathArea->select();
		check_error($PathArea);
// 		Log::write("\n".M()->getLastSql(),Log::SQL);
		
		$areas = array();
		$curAreaId = null;
		$curPoints = array();
    	$counter = -1;
		foreach ( $areasAndPoints as $areaAndPoint ) {
			if(empty($areaAndPoint['path_area_id'])) continue;
			if($areaAndPoint['path_area_id']!=$curAreaId) {
				$curAreaId = $areaAndPoint['path_area_id'];
    			++$counter;
				$areas[$counter] = array(
					'id' => $areaAndPoint['path_area_id'],
					'type' => $areaAndPoint['type'],
					'label' => $areaAndPoint['label'],
					'memo' => $areaAndPoint['memo'],
					'distance' => $areaAndPoint['distance'],
					'area' => $areaAndPoint['area']
				);
				$areas[$counter]['points'] = array();
			}
			
			$areas[$counter]['points'][] = array(
				'id' => $areaAndPoint['point_id'],
				'path_area_id' => $areaAndPoint['path_area_id'],
				'latitude' =>  $areaAndPoint['latitude'],
				'longitude' =>  $areaAndPoint['longitude'],
				'sequence' =>  $areaAndPoint['sequence']
			);
		}
		
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) {
			$areas_pages = array_chunk($areas, $limit);
			$areas = $areas_pages[$page-1];
		}
		
		return_json(true, null, 'areas', $areas);
	}
	
	/**
	 * 所有的监控路径/区域
	 * 需要一个参数type，用于指定是路径还是区域（否则全部返回），结果不包括点集
	 */
	public function allpatharea() {
		$type = $_REQUEST['type'];
		$condition= array(
			'_string' => ($type!='区域' && $type!='路径') ? '1' : "`type`='".$type."'"
		);
		
		$PathArea = M('PathArea');
		check_error($PathArea);
		
		$total = $PathArea->where($condition)->count();
		check_error($PathArea);
		
		$PathArea->field(array('`id`', 'type', 'label', 'memo', 'distance', 'area'))
			->where($condition)
			->order('`id` ASC');
		
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) $PathArea->limit($limit)->page($page);
		
		$pathAreas = $PathArea->select();
		check_error($PathArea);
		
		return_json(true,$total,'areas', $pathAreas);
	}
	
	public function add() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$areas = json_decode(file_get_contents("php://input"));
		if(!is_array($areas)) {
			$areas = array($areas);
		}
		
		$PathArea = M('PathArea');
		check_error($PathArea);
		
		$Point = M('Point');
		check_error($Point);
		
		foreach ( $areas as $area ) {
			$PathArea->create($area);
			check_error($PathArea);
			
			$PathArea->id = null;
			
			$id = $PathArea->add();
			if(false === $id) 
				return_value_json(false, 'msg', '添加区域['.$area->label.']时出错：'.get_error($PathArea));
			
			$points = json_decode($area->pointsJson);
			if($points===null) {
				return_value_json(false, 'msg', '添加区域['.$area->label.']的顶点时出错：系统出错：定点数据格式不正确。');
			}
			foreach($points as $point) {
				$Point->create($point);
				check_error($Point);
				$Point->id = null;
				$Point->path_area_id = $id;
				
				if(false === $Point->add()) 
					return_value_json(false, 'msg', '添加区域['.$area->label.']的顶点时出错：'.get_error($Point));
			}
		}
		
		
		return_value_json(true);
	}
	
	public function edit() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$areas = json_decode(file_get_contents("php://input"));
		if(!is_array($areas)) {
			$areas = array($areas);
		}
		
		$PathArea = M('PathArea');
		check_error($PathArea);
		
		$Point = M('Point');
		check_error($Point);
		
		foreach ( $areas as $area ) {
			$PathArea->create($area);
			check_error($PathArea);
			
			if(false === $PathArea->save()) {
				return_value_json(false, 'msg', '更新监控区域['.$area->label.']时出错：' + get_error($PathArea));
			}
			
			//先删除原来的点
			$Point->where("`path_area_id` = '" . $area->id . "'")->delete();
			//再添加新的点
			$points = json_decode($area->pointsJson);
			if($points===null) {
				return_value_json(false, 'msg', '更新区域['.$area->label.']的顶点时出错：系统出错：定点数据格式不正确。');
			}
			foreach($points as $point) {
				$Point->create($point);
				check_error($Point);
				$Point->id = null;
				$Point->path_area_id = $area->id;
				
				if(false === $Point->add()) 
					return_value_json(false, 'msg', '更新区域['.$area->label.']的顶点时出错：'.get_error($Point));
			}
		}
		
		return_value_json(true);
	}
	
	public function delete() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$areas = json_decode(file_get_contents("php://input"));
		if(!is_array($areas)) {
			$areas = array($areas);
		}
		
		$id = array();
		foreach ( $areas as $area ) {
			$id[] = $area->id;
		}
		
		if(count($id)>0) {
			$PathArea = M('PathArea');
			check_error($PathArea);
			if(false === $PathArea->where("`id` IN (" . implode(",", $id) . ")")->delete()) {
				return_value_json(false, 'msg', get_error($PathArea));
			}
			
			$Point = M('Point');
			check_error($Point);
			if(false === $Point->where("`path_area_id` IN (" . implode(",", $id) . ")")->delete()) {
				return_value_json(false, 'msg', '删除区域的顶点时出错：'.get_error($Point));
			}
		}
		
		return_value_json(true);
	}
	
	public function getbyid() {
		$id = $_REQUEST['id'] + 0;
		
		if(empty($id)) {
			return_value_json(false, "msg", "系统错误：区域id为空或者为0");
		}
		
		$PathArea = M('PathArea');
		check_error($PathArea);
		
		$PathArea->join('`point` on `point`.`path_area_id`=`path_area`.`id`')
			->field(array('`path_area_id`', 'type', 'label', 'memo', 'distance', 'area',
						'`point`.`id`'=>'point_id', 'latitude', 'longitude', 'sequence'
					))
			->where("`type`='区域' AND `path_area_id`={$id}")
			->order('`path_area`.`id` ASC, `sequence` ASC');
		
		$areasAndPoints = $PathArea->select();
		check_error($PathArea);
// 		Log::write("\n".M()->getLastSql(),Log::SQL);
		
		$areas = array();
		$curAreaId = null;
		$curPoints = array();
    	$counter = -1;
		foreach ( $areasAndPoints as $areaAndPoint ) {
			if(empty($areaAndPoint['path_area_id'])) continue;
			if($areaAndPoint['path_area_id']!=$curAreaId) {
				$curAreaId = $areaAndPoint['path_area_id'];
    			++$counter;
				$areas[$counter] = array(
					'id' => $areaAndPoint['path_area_id'],
					'type' => $areaAndPoint['type'],
					'label' => $areaAndPoint['label'],
					'memo' => $areaAndPoint['memo'],
					'distance' => $areaAndPoint['distance'],
					'area' => $areaAndPoint['area']
				);
				$areas[$counter]['points'] = array();
			}
			
			$areas[$counter]['points'][] = array(
				'id' => $areaAndPoint['point_id'],
				'path_area_id' => $areaAndPoint['path_area_id'],
				'latitude' =>  $areaAndPoint['latitude'],
				'longitude' =>  $areaAndPoint['longitude'],
				'sequence' =>  $areaAndPoint['sequence']
			);
		}
		
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) {
			$areas_pages = array_chunk($areas, $limit);
			$areas = $areas_pages[$page-1];
		}
		
		return_json(true, null, 'areas', $areas);
	}
}
?>