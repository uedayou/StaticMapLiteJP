<?php

require_once 'staticmap.php';
require_once 'StaticMapLiteJP.php';

$options = array(
	"ZOOM"=>15,
	"LAT"=>35,
	"LON"=>135,
	"WIDTH"=>800,
	"HEIGHT"=>560,
	"MARKERS"=>array(array('lat' => 35, 'lon' => 135, 'type' => 'leaflet')),
	"MAPTYPE"=>"gsijp",
	"USE_TILE_CACHE"=>false,
	"USE_MAP_CACHE"=>false,
	"USE_REQUEST_PARAM"=>false
);

$map = new StaticMapLiteJP($options);
$map->showJpegMap();

?>