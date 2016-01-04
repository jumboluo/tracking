<?php

class PathAction extends Action {

	public function all() {
		$PathArea = M('PathArea');
		check_error($PathArea);
		
// 		$total = $PathArea->where("`type`='路径'")->count();
// 		check_error($PathArea);
		
		$PathArea->join('`point` on `point`.`path_area_id`=`path_area`.`id`')
			->field(array('`path_area_id`', 'type', 'label', 'memo', 'distance',
						'`point`.`id`'=>'point_id', 'latitude', 'longitude', 'sequence'
					))
			->where("`type`='路径'")
			->order('`path_area`.`id` ASC, `sequence` ASC');
		
// 		$page = $_REQUEST['page'] + 0;
// 		$limit = $_REQUEST['limit'] + 0;
// 		if($page && $limit) $PathArea->limit($limit)->page($page);
		
		$pathsAndPoints = $PathArea->select();
		check_error($PathArea);
//		Log::write("\n".M()->getLastSql(),Log::SQL);
		
		$paths = array();
		$curPathId = null;
		$curPoints = array();
    	$counter = -1;
		foreach ( $pathsAndPoints as $pathAndPoint ) {
			if($pathAndPoint['path_area_id']!=$curPathId) {
				$curPathId = $pathAndPoint['path_area_id'];
    			++$counter;
				$paths[$counter] = array(
					'id' => $pathAndPoint['path_area_id'],
					'type' => $pathAndPoint['type'],
					'label' => $pathAndPoint['label'],
					'memo' => $pathAndPoint['memo'],
					'distance' => $pathAndPoint['distance']
				);
				$paths[$counter]['points'] = array();
			}
			
			$paths[$counter]['points'][] = array(
				'id' => $pathAndPoint['point_id'],
				'path_area_id' => $pathAndPoint['path_area_id'],
				'latitude' =>  $pathAndPoint['latitude'],
				'longitude' =>  $pathAndPoint['longitude'],
				'sequence' =>  $pathAndPoint['sequence']
			);
		}
		
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) {
			$paths_pages = array_chunk($paths, $limit);
			$paths = $paths_pages[$page-1];
		}
		
		return_json(true, null, 'paths', $paths);
	}
	
	public function add() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$paths = json_decode(file_get_contents("php://input"));
		if(!is_array($paths)) {
			$paths = array($paths);
		}
		
		$PathArea = M('PathArea');
		check_error($PathArea);
		
		$Point = M('Point');
		check_error($Point);
		
		foreach ( $paths as $path ) {
			$PathArea->create($path);
			check_error($PathArea);
			
			$PathArea->id = null;
			
			$id = $PathArea->add();
			if(false === $id) 
				return_value_json(false, 'msg', '添加路径['.$path->label.']时出错：'.get_error($PathArea));
			
			$points = json_decode($path->pointsJson);
			if($points===null) {
				return_value_json(false, 'msg', '添加路径['.$path->label.']的顶点时出错：系统出错：定点数据格式不正确。');
			}
			foreach($points as $point) {
				$Point->create($point);
				check_error($Point);
				$Point->id = null;
				$Point->path_area_id = $id;
				
				if(false === $Point->add()) 
					return_value_json(false, 'msg', '添加路径['.$path->label.']的顶点时出错：'.get_error($Point));
			}
		}
		
		
		return_value_json(true);
	}
	
	public function edit() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$paths = json_decode(file_get_contents("php://input"));
		if(!is_array($paths)) {
			$paths = array($paths);
		}
		
		$PathArea = M('PathArea');
		check_error($PathArea);
		
		$Point = M('Point');
		check_error($Point);
		
		foreach ( $paths as $path ) {
			$PathArea->create($path);
			check_error($PathArea);
			
			if(false === $PathArea->save()) {
				return_value_json(false, 'msg', '更新监控路径['.$path->label.']时出错：' + get_error($PathArea));
			}
			
			//先删除原来的点
			$Point->where("`path_area_id` = '" . $path->id . "'")->delete();
			//再添加新的点
			$points = json_decode($path->pointsJson);
			if($points===null) {
				return_value_json(false, 'msg', '更新路径['.$path->label.']的顶点时出错：系统出错：定点数据格式不正确。');
			}
			foreach($points as $point) {
				$Point->create($point);
				check_error($Point);
				$Point->id = null;
				$Point->path_area_id = $path->id;
				
				if(false === $Point->add()) 
					return_value_json(false, 'msg', '更新路径['.$path->label.']的顶点时出错：'.get_error($Point));
			}
		}
		
		return_value_json(true);
	}
	
	public function delete() {
		if(!$this->isPost()) return_value_json(false, 'msg', '非法的调用');
		
		$paths = json_decode(file_get_contents("php://input"));
		if(!is_array($paths)) {
			$paths = array($paths);
		}
		
		$id = array();
		foreach ( $paths as $path ) {
			$id[] = $path->id;
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
				return_value_json(false, 'msg', '删除路径的顶点时出错：'.get_error($Point));
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
		->field(array('`path_area_id`', 'type', 'label', 'memo', 'distance',
				'`point`.`id`'=>'point_id', 'latitude', 'longitude', 'sequence'
		))
		->where("`type`='路径' AND `path_area_id`={$id}")
		->order('`path_area`.`id` ASC, `sequence` ASC');
	
		$pathsAndPoints = $PathArea->select();
		check_error($PathArea);
	
		$paths = array();
		$curPathId = null;
		$curPoints = array();
		$counter = -1;
		foreach ( $pathsAndPoints as $pathAndPoint ) {
			if($pathAndPoint['path_area_id']!=$curPathId) {
				$curPathId = $pathAndPoint['path_area_id'];
				++$counter;
				$paths[$counter] = array(
						'id' => $pathAndPoint['path_area_id'],
						'type' => $pathAndPoint['type'],
						'label' => $pathAndPoint['label'],
						'memo' => $pathAndPoint['memo'],
						'distance' => $pathAndPoint['distance']
				);
				$paths[$counter]['points'] = array();
			}
				
			$paths[$counter]['points'][] = array(
					'id' => $pathAndPoint['point_id'],
					'path_area_id' => $pathAndPoint['path_area_id'],
					'latitude' =>  $pathAndPoint['latitude'],
					'longitude' =>  $pathAndPoint['longitude'],
					'sequence' =>  $pathAndPoint['sequence']
			);
		}
	
		$page = $_REQUEST['page'] + 0;
		$limit = $_REQUEST['limit'] + 0;
		if($page && $limit) {
			$paths_pages = array_chunk($paths, $limit);
			$paths = $paths_pages[$page-1];
		}
	
		return_json(true, null, 'paths', $paths);
	}
}
?>