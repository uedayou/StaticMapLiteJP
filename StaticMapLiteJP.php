<?php
/**
 * StaticMapLiteJP
 *
 * Copyright 2016 Hiroshi Ueda
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author Hiroshi Ueda <yooueda AT gmail.com>
 *
 */

Class StaticMapLiteJP extends staticMapLite
{
    protected $tileSrcUrl = array('mapnik' => 'http://tile.openstreetmap.org/{Z}/{X}/{Y}.png',
        'osmarenderer' => 'http://otile1.mqcdn.com/tiles/1.0.0/osm/{Z}/{X}/{Y}.png',
        'cycle' => 'http://a.tile.opencyclemap.org/cycle/{Z}/{X}/{Y}.png',
        'osmjp' => 'http://j.tile.openstreetmap.jp/{Z}/{X}/{Y}.png', // osmjp
        'gsijp' => 'https://cyberjapandata.gsi.go.jp/xyz/std/{Z}/{X}/{Y}.png' // 国土地理院
    );

    protected $tileDefaultSrc = 'osmjp'; // gsijp, cycle, osmarenderer, mapnik
    protected $markerBaseDir = __DIR__.'/images/markers';
    protected $osmLogo = __DIR__.'/images/osm_logo.png';

    protected $osmLogoStr = "c OpenStreetMap contributors";
    protected $gsiLogoStr = "GSI Maps";

    protected $osmLogoWidth = 240;
    protected $gsiLogoWidth = 80;

    protected $logoFont = __DIR__."/font/OpenSans-Regular.ttf";

    protected $useRequestParam = true;

    protected $markerPrototypes = array(
        // found at http://www.mapito.net/map-marker-icons.html
        'lighblue' => array('regex' => '/^lightblue([0-9]+)$/',
            'extension' => '.png',
            'shadow' => false,
            'offsetImage' => '0,-19',
            'offsetShadow' => false
        ),
        // openlayers std markers
        'ol-marker' => array('regex' => '/^ol-marker(|-blue|-gold|-green)+$/',
            'extension' => '.png',
            'shadow' => '../marker_shadow.png',
            'offsetImage' => '-10,-25',
            'offsetShadow' => '-1,-13'
        ),
        // taken from http://www.visual-case.it/cgi-bin/vc/GMapsIcons.pl
        'ylw' => array('regex' => '/^(pink|purple|red|ltblu|ylw)-pushpin$/',
            'extension' => '.png',
            'shadow' => '../marker_shadow.png',
            'offsetImage' => '-10,-32',
            'offsetShadow' => '-1,-13'
        ),
        // http://svn.openstreetmap.org/sites/other/StaticMap/symbols/0.png
        'ojw' => array('regex' => '/^bullseye$/',
            'extension' => '.png',
            'shadow' => false,
            'offsetImage' => '-20,-20',
            'offsetShadow' => false
        ),
        // leaflet default marker
        'leaflet' => array('regex' => '/^leaflet$/',
            'extension' => '.png',
            'shadow' => '../marker_shadow_leaflet.png',
            'offsetImage' => '-12,-41',
            'offsetShadow' => '-13,-41'
        )
    );

    public function __construct($options=null)
    {
        $this->zoom = isset($options["ZOOM"])?$options["ZOOM"]:0;
        $this->lat = isset($options["LAT"])?$options["LAT"]:0;
        $this->lon = isset($options["LON"])?$options["LON"]:0;
        $this->width = isset($options["WIDTH"])?$options["WIDTH"]:500;
        $this->height = isset($options["HEIGHT"])?$options["HEIGHT"]:350;
        $this->markers = is_array($options["MARKERS"])?$options["MARKERS"]:array();
        $this->maptype = isset($options["MAPTYPE"])?$options["MAPTYPE"]:$this->tileDefaultSrc;
        $this->useTileCache = isset($options["USE_TILE_CACHE"])?$options["USE_TILE_CACHE"]:$this->useTileCache;
        $this->useMapCache = isset($options["USE_MAP_CACHE"])?$options["USE_MAP_CACHE"]:$this->useMapCache;
        $this->useRequestParam = isset($options["USE_REQUEST_PARAM"])?$options["USE_REQUEST_PARAM"]:$this->useRequestParam;
    }

    public function parseParams()
    {
    	if ($this->useRequestParam) {
	        global $_GET;

	        if (!empty($_GET['show'])) {
	           $this->parseOjwParams();
	        }
	        else {
	           $this->parseLiteParams();
	        }
	    }
    }

    public function copyrightNotice()
    {
        $text = $this->maptype=="gsijp"?$this->gsiLogoStr:$this->osmLogoStr;
        $logoWidth = $this->maptype=="gsijp"?$this->gsiLogoWidth:$this->osmLogoWidth;
        $logoImg = imagecreatetruecolor($logoWidth, 24);
        $color = imagecolorallocatealpha($logoImg, 0, 0, 0, 0);
        $backgroundColor = imagecolorallocatealpha($logoImg, 255, 255, 255, 30);
        imagealphablending($logoImg, true);
        imagesavealpha($logoImg, true);
        imagefill($logoImg, 0, 0, $backgroundColor);
        imagettftext($logoImg, 12, 0, 5, 18, $color, $this->logoFont, $text);

        imagecopy($this->image, $logoImg, imagesx($this->image) - imagesx($logoImg), imagesy($this->image) - imagesy($logoImg), 0, 0, imagesx($logoImg), imagesy($logoImg));
    }

    public function sendJpegHeader()
    {
        header('Content-Type: image/jpeg');
        $expires = 60 * 60 * 24 * 14;
        header("Pragma: public");
        header("Cache-Control: maxage=" . $expires);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
    }

    public function showJpegMap()
    {
        $this->parseParams();
        if ($this->useMapCache) {
            // use map cache, so check cache for map
            if (!$this->checkMapCache()) {
                // map is not in cache, needs to be build
                $this->makeMap();
                $this->mkdir_recursive(dirname($this->mapCacheIDToFilename()), 0777);
                imagepng($this->image, $this->mapCacheIDToFilename(), 9);
                $this->sendHeader();
                if (file_exists($this->mapCacheIDToFilename())) {
                    return file_get_contents($this->mapCacheIDToFilename());
                } else {
                    return imagejpeg($this->image);
                }
            } else {
                // map is in cache
                $this->sendJpegHeader();
                return file_get_contents($this->mapCacheIDToFilename());
            }

        } else {
            // no cache, make map, send headers and deliver png
            $this->makeMap();
            $this->sendJpegHeader();
            return imagejpeg($this->image);

        }
    }

}

?>