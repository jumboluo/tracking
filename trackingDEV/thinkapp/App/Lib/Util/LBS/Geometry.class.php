<?php
class Geometry {

    function Geometry() {
    }
    
    /**直角坐标系点是否在多边形内，
     * 这里采用的算法是射线法，即总体思路是求解多边形各边与射线(x0,y0, -∞, y0)的交点(x, y0)的个数。
     * 如果交点个数为奇数个，那么点在多边形内，否则，交点个数为偶数个，那么点在多边形外。
     * 必须注意的是处理好特殊情况：（1）射线与多边形的边平行（或者重合），（2）射线与边相交于多边形的端点
     * （3）
     */
    public static function pointInPolygon($point, $polygon) {
    	$len = count($polygon);	//注意：$polygon是多边形端点的数组，起点==终点
    	if($polygon[$len-1]['x']!=$polygon[0]['x'] || $polygon[$len-1]['y']!=$polygon[0]['y']) {//如果起点!=终点，复制起点作为终点
    		$polygon[] = self::pointClone($polygon[0]);
    		$len++;
    	}
    	$in = false; //交点计数器
    	for($i=0; $i<$len-1; $i++) {
    		$p1 = $polygon[$i]; 
    		$p2 = $polygon[$i+1];
    		
    		if($p1['y'] == $p2['y']) continue;	//多边形的边平行于射线
    		
    		if($point['y'] < min($p1['y'], $p2['y']) || $point['y'] > max($p1['y'], $p2['y'])) continue; //射线与多边形交点在线段外面
    		
    		//求交点（即求解x）
    		$x = ($point['y'] - $p1['y']) * ($p2['x'] - $p1['x']) / ($p2['y'] - $p1['y']) + $p1['x']; 
    		
    		if($x==$point['x']) return true;	//点在直线上
    		
    		if($x < $point['x']) $in = !$in;	//交点应该是在
    	}
    	
    	return $in;
    }
    
    public static function pointClone($point) {
    	return array_merge($point);
    }
    
    /**经纬度坐标点是否在经纬度坐标的多边形内。
     * 先将经纬度坐标投影，然后用pointInPolygon进行判断
     */
    public static function geoPointInPolygon($geoPoint, $geoPolygon) {
    	$point = self::latlng2mercator($geoPoint['lat'], $geoPoint['lng']);
    	$polygon = array();
    	foreach ( $geoPolygon as $gp ) {
			$polygon[]  = self::latlng2mercator($gp['lat'], $gp['lng']);;
		}
		return self::pointInPolygon($point, $polygon);
    }
    
    /**点是否在直线上面，1：上（左）边，-1：下（右）边，0：点在直线上
     * 算法：比较笨的求解直线与x=x0的交点y，如果y<y0，那么点(x0,y0)在直线上方；y==y0点在直线上；y>y0点在直线下方
     */
    public static function pointOverLine($point, $line) {
    	$p1 = $line[0]; $p2 = $line[1];
    	
    	if(($p2['x'] == $p1['x'])) return ($point['x'] == $p1['x']) ? 0 : //直线与y轴平行，点在直线上 
    					($point['x'] < $p1['x'] ? 1 : -1);	//点在左边为1， 右边为-1  
    	
    	$y = ($p2['y'] - $p1['y']) * ($point['x']-$p1['x']) / ($p2['x'] - $p1['x']) + $p1['y'];
    	return $y<$point['y'] ? 1 : ($y>$point['y'] ? -1 : 0); 
    }
    
    /**
     * 直线是否切割多边形
     * 算法：拿多边形各定点与直线比对，
     * （1）如果有顶点在直线上，那么直线与多边形相切，
     * （2）如果全部顶点在直线的一侧，那么直线不与多边形切割
     * （3）否则，直线割多边形
     */
    public static function lineCuttingPolygon($line, $polygon) {
    	$hasUp = false; $hasDown = false;
    	foreach($polygon as $vertex) {
    		$upOrDown = self::pointOverLine($vertex, $line);  
    		if($upOrDown==0) return true;
    		$hasUp = $hasUp || ($upOrDown==-1);
    		$hasDown = $hasDown || ($upOrDown==1); 
    		if($hasUp && $hasDown) return true;
    	}
    	
    	return false;
    }
    
    
    /**
     * 线段是否切割多边形
     * 算法：
     * （1）如果线段所在直线不切割多边形，那么线段肯定不切割多边形
     * （2）依次求解线段所在直线与多边形各边的交点，
     * 如果交点在线段上（注意是线段上，含交点是线段的端点），那么线段切割多边形，否则不切割多边形
     * 其实第一步不是必要的，但是可以加速出结果。
     */
    public static function segmentCuttingPolygon($segment, $polygon) {
    	if(self::lineCuttingPolygon($segment, $polygon)) {//所在直线切割多边形
    		$count = count($polygon);
    		for($i=0, $j=$count-1; $i<$count; ++$i, $j=$i-1) {
    			if(self::segmentIntersectSegment($segment, array($polygon[$j], $polygon[$i]))!==false) {
    				return true;
    			}
    		}
    	}
    	return false;
    }
    


    /**
     * 线段与线段的交点
     * 算法：
     * 求直线与直线的交点，如果交点在两个线段的范围内，则这个交点为线段与线段的交点，否则线段与线段没有交点
     * 如果有交点，则返回交点坐标，否则返回false表示没有交点，返回true表示两线段部分重合
     */
    public static function segmentIntersectSegment($segment1, $segment2) {
    	$intersection = self::lineIntersectLine($segment1, $segment2);
    	
    	if($intersection===false) return false; //平行不重合
    	
    	if($intersection===true) {//所在直线重合
    		return !(($segment1[0]['x']!=$segment1[1]['x'] //两线段平行但不与y轴平行，则比较x的范围
    				&& (max($segment1[0]['x'], $segment1[1]['x'])<min($segment2[0]['x'], $segment2[1]['x'])
    					|| min($segment1[0]['x'], $segment1[1]['x'])>max($segment2[0]['x'], $segment2[1]['x']))
    			)
    			||($segment1[0]['x']==$segment1[1]['x']	//两线段都与y轴平行，比较y的范围
    				&& (max($segment1[0]['y'], $segment1[1]['y'])<min($segment2[0]['y'], $segment2[1]['y'])
    					|| min($segment1[0]['y'], $segment1[1]['y'])>max($segment2[0]['y'], $segment2[1]['y']))
    			)); 
    	}
    	
    	return ( ($intersection['x']>=min($segment1[0]['x'], $segment1[1]['x']) 
    				&& $intersection['x']<=max($segment1[0]['x'], $segment1[1]['x'])
    				&& $intersection['y']>=min($segment1[0]['y'], $segment1[1]['y'])
    				&& $intersection['y']<=max($segment1[0]['y'], $segment1[1]['y']) )	//交点在第一个线段范围内 
    			&& 
    			 ($intersection['x']>=min($segment2[0]['x'], $segment2[1]['x']) 
    				&& $intersection['x']<=max($segment2[0]['x'], $segment2[1]['x'])
    				&& $intersection['y']>=min($segment2[0]['y'], $segment2[1]['y'])
    				&& $intersection['y']<=max($segment2[0]['y'], $segment2[1]['y']) )	//交点在第二个线段范围 内
    			) 
    			? $intersection : false;
    }
    
    /**
     * 直线与直线的交点，
     * 如果有交点，则返回交点的坐标，
     * 如果没有交点，返回true代表两直线重合，false代表两直线平行
     */
    public static function lineIntersectLine($line1, $line2) {
    	if($line1[0]['x']==$line1[1]['x'] && $line2[0]['x']!=$line2[1]['x']) { //第一条直线与y轴平行，第二条不平行
    		return array(
    			'x' => $line1[0]['x'],
    			'y' => ($line1[0]['x']-$line2[0]['x']) * ($line2[1]['y'] - $line2[0]['y']) / ($line2[1]['x'] - $line2[0]['x']) + $line2[0]['y']
    		);
    	}
    	
    	if($line2[0]['x']==$line2[1]['x'] && $line1[0]['x']!=$line1[1]['x']) { //第二条直线与y轴平行，第一条不平行
    		return array(
    			'x' => $line2[0]['x'],
    			'y' => ($line2[0]['x']-$line1[0]['x']) * ($line1[1]['y'] - $line1[0]['y']) / ($line1[1]['x'] - $line1[0]['x']) + $line1[0]['y']
    		);
    	}
    	
    	if($line1[0]['x']==$line1[1]['x'] && $line2[0]['x']==$line2[1]['x']) { //两条直线都与y轴平行
    		return $line1[0]['x'] == $line2[0]['x'];	//如果两直线与x轴交点相同则两直线重合，否则两直线平行
    	}
    	
    	$A = ($line1[1]['y'] - $line1[0]['y']) / ($line1[1]['x'] - $line1[0]['x']);
    	$B = ($line2[1]['y'] - $line2[0]['y']) / ($line2[1]['x'] - $line2[0]['x']);
    	if($A == $B) { //斜率相同，看看两直线与y轴的交点是否相同，如果相同两直线重合，否则两直线平行
    		return ($line1[0]['y'] - $A * $line1[0]['x']) == ($line2[0]['y'] - $A * $line2[0]['x']);
    	}
    	else {
    		return array(
    			'x' => ($A * $line1[0]['x'] - $line1[0]['y'] - $B * $line2[0]['x'] + $line2[0]['y']) / ($A - $B),
    			'y' => ($A * $B * ($line1[0]['x'] - $line2[0]['x']) - $B * $line1[0]['y'] + $A * $line2[0]['y']) / ($A - $B),
    		);
    	}
    }
    
    /**
     * 点到点的距离（直角坐标系）
     */
    public static function pointDistancePoint($point1, $point2) {
    	return sqrt( ($point1['x'] - $point2['x']) * ($point1['x'] - $point2['x']) + ($point1['y'] - $point2['y']) * ($point1['y'] - $point2['y']) );
    }
        
    /**
     * 点到直线的距离
     */
    public static function pointDistanceLine($point, $line) {
    	return self::pointDistancePoint($point, self::pointPedalLine($point, $line));
    }
    
    /**
     * 点到线段的距离
     * 算法：
     * （1）求点到直线的垂足。
     * （2）如果垂足在线段范围内，则点到线段的距离为点到垂足的距离
     * （3）如果垂足不在线段范围内，则点到线段的距离为点到线短两端点的距离的较小者
     */
    public static function pointDistanceSegment($point, $segment) {
    	$pedal = self::pointPedalLine($point, $segment);
    	if($pedal['x']>=min($segment[0]['x'], $segment[1]['x']) 
    		&& $pedal['x']<=max($segment[0]['x'], $segment[1]['x'])
    		&& $pedal['y']>=min($segment[0]['y'], $segment[1]['y'])
    		&& $pedal['y']<=max($segment[0]['y'], $segment[1]['y'])) //垂足在线段范围内
    	{
    		return self::pointDistancePoint($point, $pedal);
    	}
    	else {
    		return min(self::pointDistancePoint($point, $segment[0]), self::pointDistancePoint($point, $segment[1]));
    	}
    }
    
    /**
     * 点到直线的垂足
     */
    public static function pointPedalLine($point, $line) {
    	if(($line[0]['y'] - $point['y']) * ($line[1]['x'] - $point['x']) == ($line[0]['x'] - $point['x']) * ($line[1]['y'] - $point['y'])){ //点在直线上
    		return $point;
    	}
    	
    	if($line[0]['x']==$line[1]['x']) {
    		return array(
    			'x' => $line[0]['x'],
    			'y' => $point['y']
    		);
    	}
    	
    	$A = ($line[1]['y'] - $line[0]['y']) / ($line[1]['x'] - $line[0]['x']);
    	return array(
    		'x' => ($A * ($point['y'] - $line[0]['y']) + $A * $A * $line[0]['x'] + $point['x']) / (1 + $A * $A),
    		'y' => ($A * ($point['x'] - $line[0]['x']) + $A * $A * $point['y'] + $line[0]['y']) / (1 + $A * $A)
    	);
    }
    
    /**
     * 点到线段的“垂足”（线段上到点距离最短的点）
     * 算法：先求点到直线的垂足，如果垂足在线段上，则返回该垂足，否则求线段两个端点到点的距离，哪个距离小就返回哪个端点
     */
    public static function pointPedalSegment($point, $segment) {
    	$pedal = self::pointPedalLine($point, $segment);
    	if($pedal['x']>=min($segment[0]['x'], $segment[1]['x']) 
    		&& $pedal['x']<=max($segment[0]['x'], $segment[1]['x'])
    		&& $pedal['y']>=min($segment[0]['y'], $segment[1]['y'])
    		&& $pedal['y']<=max($segment[0]['y'], $segment[1]['y'])) //垂足在线段范围内
    	{
    		return $pedal;
    	}
    	else {
    		return self::pointDistancePoint($point, $segment[0]) <= self::pointDistancePoint($point, $segment[1]) ? $segment[0] : $segment[1];
    	}
    }
    
    /**
     * 点到折线的距离
     */
    public static function pointDistancePolyline($point, $polyline) {
		$minDist = -1;
		$count = count($polyline);
			
		for($i=0; $i<$count-1; $i++) {
			$curDist = self::pointDistanceSegment($point, array($polyline[$i], $polyline[$i+1]));
			$minDist = ($minDist<0 || $minDist>$curDist) ? $curDist : $minDist;
		}
		
		return $minDist;
	}
	
	/**
     * 点到折线的“垂足”（折线上到点最短距离的点）
     */
    public static function pointPedalPolyline($point, $polyline) {
		$minDist = -1;
		$count = count($polyline);
		
		$pedal = 0;	//取得“垂足”的边（第一个点的坐标）
		for($i=0; $i<$count-1; $i++) {
			$curDist = self::pointDistanceSegment($point, array($polyline[$i], $polyline[$i+1]));
			$pedal = ($minDist<0 || $minDist>$curDist) ? $i : $pedal;
			$minDist = ($minDist<0 || $minDist>$curDist) ? $curDist : $minDist;
		}
		
		return self::pointPedalSegment($point, array($polyline[$pedal], $polyline[$pedal+1]) );
	}
	 
    /**
     * 点到多边形的距离
     */
    public static function pointDistancePolygon($point, $polygon) {
		$minDist = -1;
		$count = count($polygon);
		for($i=0, $j=$count-1; $i<$count; ++$i, $j=$i-1) {
			$curDist = self::pointDistanceSegment($point, array($polygon[$j], $polygon[$i]));
			$minDist = ($minDist<0 || $minDist>$curDist) ? $curDist : $minDist;
		}
		
		return $minDist;
	}
	
	/**
     * 点到多边形的“垂足”（多边形上到点最短距离的点）
     */
    public static function pointPedalPolygon($point, $polygon) {
		$minDist = -1;
		$count = count($polygon);
		$pedal = null;	//取得“垂足”的边（第一个点的坐标）
		for($i=0, $j=$count-1; $i<$count; ++$i, $j=$i-1) {
			$curDist = self::pointDistanceSegment($point, array($polygon[$j], $polygon[$i]));
			$pedal = ($minDist<0 || $minDist>$curDist) ? $j : $pedal;
			$minDist = ($minDist<0 || $minDist>$curDist) ? $curDist : $minDist;
		}
		
		return self::pointPedalSegment($point, array($polygon[$pedal], $polygon[$pedal==$count-1 ? 0: $pedal+1]) );
	}
	
	/**
	 * 指定经纬度的点是否在经纬度多边形内
	 */
	public static function geoPointInPolygon2($latitude, $longitude, $geoPolygon) {
    	$point = self::latlng2mercator($latitude, $longitude);
    	
    	$polygon = array();
    	foreach($geoPolygon as $geoPoint) {
    		$polygon[] = self::latlng2mercator($geoPoint['latitude'], $geoPoint['longitude']);
    	}
    	
    	return self::pointInPolygon($point, $polygon);
	}
	
	/**
	 * 指定两个经纬度点组成的经纬度线段是否切割经纬度多边形
	 */
	public static function geoSegmentCuttingPolygon($latitude1, $longitude1, $latitude2, $longitude2, $geoPolygon) {
    	$point1 = self::latlng2mercator($latitude1, $longitude1);
    	$point2 = self::latlng2mercator($latitude2, $longitude2);
		
    	$polygon = array();
    	foreach($geoPolygon as $geoPoint) {
    		$polygon[] = self::latlng2mercator($geoPoint['latitude'], $geoPoint['longitude']);
    	}
    	
    	return self::segmentCuttingPolygon(array($point1, $point2), $polygon);
	}
	
	/**
	 * 指定经纬度的点到经纬度多边形的“垂足”
	 */
	public static function geoPointPedalPolygon($latitude, $longitude, $geoPolygon) {
    	$point =  self::latlng2mercator($latitude, $longitude);
    	
    	$polygon = array();
    	foreach($geoPolygon as $geoPoint) {
    		$polygon[] = self::latlng2mercator($geoPoint['latitude'], $geoPoint['longitude']);
    	}
    	
    	$pedal = self::pointPedalPolygon($point, $polygon);
    	$geoPedal = self::mercator2latlng($pedal['x'], $pedal['y']);
    	
    	return $geoPedal;
	}
	
	/**
	 * 指定经纬度的点到经纬度多边形的距离
	 */
	public static function geoPointDistancePolygon($latitude, $longitude, $geoPolygon) {
    	$geoPedal = self::geoPointPedalPolygon($latitude, $longitude, $geoPolygon);
    	
    	return self::geoPointDistancePoint($latitude, $longitude, $geoPedal['latitude'], $geoPedal['longitude']);
	}
	
	/**
	 * 指定经纬度的点到经纬度折线的“垂足”
	 */
	public static function geoPointPedalPolyline($latitude, $longitude, $geoPolyline) {
    	$point =  self::latlng2mercator($latitude, $longitude);
    	
    	$polyline = array();
    	foreach($geoPolyline as $geoPoint) {
    		$polyline[] = self::latlng2mercator($geoPoint['latitude'], $geoPoint['longitude']);
    	}
    	
    	$pedal = self::pointPedalPolyline($point, $polyline);
    	$geoPedal = self::mercator2latlng($pedal['x'], $pedal['y']);
    	
    	return $geoPedal;
	}
	
	/**
	 * 指定经纬度的点到经纬度折线的距离
	 */
	public static function geoPointDistancePolyline($latitude, $longitude, $geoPolyline) {
    	$geoPedal = self::geoPointPedalPolyline($latitude, $longitude, $geoPolyline);
    	
    	return self::geoPointDistancePoint($latitude, $longitude, $geoPedal['latitude'], $geoPedal['longitude']);
	}
	
	/**
	 * 把经纬度坐标进行墨卡托投影（一个20037508.3427892 X 20037508.3427892大小的平面坐标系）
	 * @param float $latitude 纬度，[-85.05, 85.05]
	 * @param float $longitude 经度，[-180, 180]
	 * 返回 array(
	 * 		'x' => x坐标，[20037508.3427892, 20037508.3427892] 20037508.3427892为赤道周长
	 * 		'y' => y坐标，[20037508.3427892, 20037508.3427892] 20037508.3427892为赤道周长
	 * )
	 */
	public static function latlng2mercator($latitude, $longitude) {
		$R = 6378137;	//地球半径
		$sin_rad_lat = sin(deg2rad($latitude));	//纬度弧度
		return array(
			'x' => deg2rad($longitude) * $R,	//弧长=弧度*半径
			'y' => log((1 + $sin_rad_lat) / (1 - $sin_rad_lat)) / 2 * $R
		);
	}
	
	/**
	 * 把墨卡托投影还原为经纬度坐标
	 * @param float $x x坐标，[20037508.3427892, 20037508.3427892] 20037508.3427892为赤道周长
	 * @param float $y y坐标，[20037508.3427892, 20037508.3427892] 20037508.3427892为赤道周长
	 * 返回 array(
	 * 		'lat' 		=> 纬度，[-85.05, 85.05]
	 * 		'latitude'	=> 纬度，[-85.05, 85.05]
	 * 		'lng' 		=> 经度，[-180, 180]
	 * 		'longitude'	=> 经度，[-180, 180]
	 * ),
	 * 返回结果取6位小数
	 */
	public static function mercator2latlng($x, $y) {
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
	
	/**
	 * 计算两个经纬度坐标之间的距离（单位：米）。
	 */
	public static function geoPointDistancePoint($lat1, $lng1, $lat2, $lng2) {
		$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2))  
				+ cos(deg2rad($lat1)) * cos(deg2rad($lat2))  
				* cos(deg2rad($lng1 - $lng2));
		$dist = rad2deg(acos($dist));
		return round($dist * 60 * 1.1515 * 1609.344);	//转换成米
	}
}
?>