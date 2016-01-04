<?php

import('App.Util.LBS.CoordinateConversion');
import('App.Util.LBS.GpointConverter');
import('App.Util.LBS.Geometry');

class TestAction extends Action {
	
	public function _initialize() {
		header('Content-type: application/json;charset=utf-8');
		header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
		header('Pragma: no-cache');
	}

	public function session() {
		print_r($_SESSION);
	}
	
	/**
	 * cell操作返回指定边界之内的基站
	 */
	public function cell() {
		$nlat = $_GET['nelat'] + 0;
		$elng = $_GET['nelng'] + 0;
		$slat = $_GET['swlat'] + 0;
		$wlng = $_GET['swlng'] + 0;
		
		if(empty($nlat) || empty($elng) || empty($slat) || empty($wlng)) {
			return_value_json(false, msg, '边界坐标经度或（和）纬度为空');
		}
		
		$Cell = M('Cell');
		check_error($Cell);
		
		$cells = $Cell->field(array('id', 'mcc', 'mnc', 'lac', 'cellid', 
							'gps_lng', 'gps_lat', 'range', 'offset_lng', 'offset_lat',
							'address', 'update_time'))
					->where("`gps_lng`<{$elng} AND `gps_lng`>{$wlng} AND `gps_lat`<{$nlat} AND `gps_lat`>{$slat}")
					->limit('250')	//最多250个
					->select();
		check_error($Cell);
//		Log::write("\n".M()->getLastSql(), Log::SQL);
		return_json(true, null, 'cells', $cells);
	}
	
	/**
	 * convert操作将返回指定GPS坐标的百度坐标
	 */
	public function convert() {
		$lat = $_POST['lat'] + 0;
		$lng = $_POST['lng'] + 0;
		
		if(empty($lat) || empty($lng)) {
			return_value_json(false, msg, 'GPS坐标经度或（和）纬度为空');
		}
		
		$Interface = A('Interface');
		$baidu = $Interface->getBaiduCoordinate($lat, $lng);
		return_value_json(true, 'data', array_merge($baidu, $_POST));
	}
	
	public function test() {
		$target = array();
		$type_id_name = '分组^2^分组2';
		sscanf(str_replace('^',' ^ ', $type_id_name),"%s ^ %d ^ %s",$target['target_type'], $target['target_id'], $target['target_name']);
		var_dump($target);
	}
	
	
	public function geotest() {
		$point11 = array('x'=>1, 'y'=>1);
		$point12 = array('x'=>1, 'y'=>2);
		$point21 = array('x'=>2, 'y'=>1);
		$point22 = array('x'=>2, 'y'=>2);
		$point23 = array('x'=>2, 'y'=>3);
		$point31 = array('x'=>3, 'y'=>1);
		$point32 = array('x'=>3, 'y'=>2);
		$point33 = array('x'=>3, 'y'=>3);
		$point43 = array('x'=>4, 'y'=>3);
		$point75 = array('x'=>7, 'y'=>5);
		
		
		$line1 = array($point31, $point43);
		$line2 = array($point12, $point43);
		$line3 = array($point11, $point23);
		$line4 = array($point32, $point33);
		$line5 = array($point43, $point75);
		$polygon1 = array($point11, $point21, $point32, $point33, $point23);
		
//		var_dump(Geometry::lineCuttingPolygon($line1, $polygon1));
//		var_dump(Geometry::lineCuttingPolygon($line2, $polygon1));
//		var_dump(Geometry::segmentIntersectSegment($line1, $line4));
//		var_dump(Geometry::segmentCuttingPolygon($line1, $polygon1));
//		var_dump(Geometry::segmentCuttingPolygon($line5, $polygon1));
//		var_dump(Geometry::pointDistancePoint($point12, $point23));
		var_dump(Geometry::pointDistanceLine($point31, array($point21, $point32)));
//		var_dump(Geometry::pointDistanceSegment($point12, array($point23, $point11)));
//		var_dump(Geometry::pointPedalSegment($point12, array($point23, $point33)));
//		var_dump(Geometry::pointDistancePolyline($point22, $polygon1));
		var_dump(Geometry::pointDistancePolygon($point31, $polygon1));
	}
}
?>