<?php
import('App.Util.LBS.CoordinateConversion');
import('App.Util.LBS.LatLng');
import('App.Util.LBS.Geometry');
class ConvertAction extends Action{
	public function test () {
		$cc = new CoordinateConversion();
		echo "<pre>";
		print_r($cc->latLngToUtmXY(80, -178));
		print_r($cc->latLngToUtmXY(0, 0));
		echo "//////////////////////////////////////\n";
		print_r($cc->latLngToUtmXY(21.827688, 90.279838));
		print_r($cc->latLngToUtmXY(49.004931, 114.269914));
		print_r($cc->latLngToUtmXY(36.251841, 126.780075));
		print_r($cc->latLngToUtmXY(9.739644, 128.546216));
		print_r($cc->latLngToUtmXY(39.915, 116.404));
		echo "</pre>";
	}
	
	public function test2() {
		echo "<pre>";
		$latlng = new LatLng(21.827688, 90.279838);
		print_r($latlng->toUTMRef());
		$latlng = new LatLng(49.004931, 114.269914);
		print_r($latlng->toUTMRef());
		$latlng = new LatLng(36.251841, 126.780075);
		print_r($latlng->toUTMRef());
		$latlng = new LatLng(9.739644, 128.546216);
		print_r($latlng->toUTMRef());
		$latlng = new LatLng(39.915, 116.404);
		print_r($latlng->toUTMRef());
		echo "</pre>";
	}
	
	public function test3() {
		echo "<pre>";
		$latlng = new LatLng(21.827688, 90.279838);
		print_r($latlng->toOSRef());
		$latlng = new LatLng(49.004931, 114.269914);
		print_r($latlng->toOSRef());
		$latlng = new LatLng(36.251841, 126.780075);
		print_r($latlng->toOSRef());
		$latlng = new LatLng(9.739644, 128.546216);
		print_r($latlng->toOSRef());
		$latlng = new LatLng(39.915, 116.404);
		print_r($latlng->toOSRef());
		echo "</pre>";
	}
	
	public function test4() {
		echo "<pre>";
		print_r($this->latlng2mercator(21.827688, 90.279838));
		print_r($this->latlng2mercator(49.004931, 114.269914));
		print_r($this->latlng2mercator(36.251841, 126.780075));
		print_r($this->latlng2mercator(9.739644, 128.546216));
		print_r($this->latlng2mercator(39.915, 116.404));
		echo "</pre>";
	}
	
	private function latlng2mercator($lat, $lng) {
		$R = 6378137;	//地球半径
		$equator =  20037508.3427892;
		$rad_lat = deg2rad($lat);	//纬度弧度
		$rad_lng = deg2rad($lng);	//经度弧度
		return array(
			'x' => $rad_lng * $R,	//弧长=弧度*半径
//			'y' => log((1 + sin($rad_lat)) / (1 - sin($rad_lat))) / (4 * pi()) * $equator * 2,
			'y' => log((1 + sin($rad_lat)) / (1 - sin($rad_lat))) / 2 * $R,
//			'y' => $rad_lat * $R
		);
	}
	
	public function test5() {
		echo "<pre>";
		print_r($this->latlng2mercator(0,0));
		print_r($this->latlng2mercator(-85.05112877980659,-180));
		print_r($this->latlng2mercator( 85.05112877980659, 180));
		echo "</pre>";
	}
	
	public function mercator2latlng($x, $y) {
		$R = 6378137;	//地球半径
		$lng = round(rad2deg($x/$R), 6);
		$a = exp(2*$y/$R);
		$lat = round(rad2deg(asin(($a-1)/($a+1))), 6);
		return array(
			'lat' 		=> $lat,
			'latitude'	=> $lat,
			'lng'		=> $lng,
			'longitude' => $lng
		);
	}
	
	public function test6() {
		$R = 6378137;	//地球半径
		echo "<pre>";
		print_r($this->mercator2latlng(10049905.595059, 2490849.6495987));
		print_r($this->mercator2latlng(12720468.639471, 6275698.1229586));
		print_r($this->mercator2latlng(14113093.391733, 4335329.8671858));
		print_r($this->mercator2latlng(14309699.308522, 1089471.8554132));
		print_r($this->mercator2latlng(12958034.0063,   4853597.9882998));
		echo "\n************************************\n";
		print_r($this->mercator2latlng(0, 0));
		print_r($this->mercator2latlng(-20037508.342789, -20037508.342789));
		print_r($this->mercator2latlng( 20037508.342789,  20037508.342789));
		echo "</pre>";
	}
	
	public function test7() {
		$point = array('latitude'=>39.915, 'longitude'=>116.404);
		$polyline = array(
			array('latitude'=>21.827688, 'longitude'=> 90.279838),
			array('latitude'=>49.004931, 'longitude'=>114.269914),
			array('latitude'=>36.251841, 'longitude'=>126.780075),
			array('latitude'=>9.739644 , 'longitude'=>128.546216)
		);
		echo "<pre>";
		print_r(Geometry::geoPointPedalPolyline(39.915, 116.404, $polyline));
		print_r(Geometry::geoPointDistancePolyline(39.915, 116.404, $polyline));
		echo "</pre>";
	}
	
	public function test8() {
		$point = array('latitude'=>39.915, 'longitude'=>116.404);
		$polygon = array(
			array('latitude'=>21.827688, 'longitude'=> 90.279838),
			array('latitude'=>49.004931, 'longitude'=>114.269914),
			array('latitude'=>36.251841, 'longitude'=>126.780075),
			array('latitude'=>9.739644 , 'longitude'=>128.546216),
			array('latitude'=>21.827688, 'longitude'=> 90.279838)
		);
		echo "<pre>";
		print_r(Geometry::geoPointPedalPolygon(39.915, 116.404, $polygon));
		print_r(Geometry::geoPointDistancePolygon(39.915, 116.404, $polygon));
		echo "</pre>";
	}
    public function test0() {
//    	echo (564939-300344); echo "<br>";
//    	echo (4418388-5431595); echo "<br>";
//    	echo (564939-300344) * (4418388-5431595); echo "<br>";
//    	echo (3255295-5431595); echo "<br>";
//    	echo (564939-300344) * (4418388-5431595) / (3255295-5431595); echo "<br>";
//    	echo (564939-300344) * (4418388-5431595) / (3255295-5431595) + 300344;

// create a blank image
$image = imagecreate(5000, 5300);

// fill the background color
$bg = imagecolorallocate($image, 255, 255, 255);

// choose a color for the polygon
$col_poly = imagecolorallocate($image, 0, 0, 0);

// draw the polygon
imagepolygon($image,
             array (
                    50, 1491,
                    2729, 5276,
                    4113, 3335,
                    4310, 89
             ),
             4,
             $col_poly);

imagepolygon($image,
             array (
                    2958, 3854,
                    2960, 3854,
                    2960, 3856,
                    2958, 3856
             ),
             4,
             $col_poly);
//$text_color = imagecolorallocate($image, 233, 14, 91);
//imagestring($image, 1, 1380, 439,  "1380, 439", $text_color);
//imagestring($image, 1, 426, 918,  "426, 918", $text_color);
//imagestring($image, 1, 1028, 2258,  "1028, 2258", $text_color);
//imagestring($image, 1, 4028, 1570,  "4028, 1570", $text_color);
//imagestring($image, 1, 796, 1414,  "796, 1414", $text_color);
header("Content-type: image/png");
imagepng($image);


    }
    
}
?>
