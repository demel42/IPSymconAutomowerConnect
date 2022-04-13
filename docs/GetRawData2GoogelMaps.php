<?php

declare(strict_types=1);

// Beispiel zur Datestellung der GPS-Ќoordinaten mit Hilfe von GooleMaps (siehe https://github.com/demel42/IPSymconGoogleMaps.git)
// in dem Script verwendete Objekt-ID's durch die eigenen ID's ersetzen

$cdata = AutomowerConnect_GetRawData(24687 /*[Rasenmäher\Automower]*/, 'LastLocations');
$jdata = json_decode($cdata, true);
if ($jdata == false) {
	return;
}

$points = [];
foreach ($jdata as $jpoint) {
    $points[] = [
        'lat' => $jpoint['latitude'],
        'lng' => $jpoint['longitude'],
    ];
}

// allgemeine Angaben zur Karte
$map = [];

// Mittelpunkt der Karte
//$map['center'] = $points[0];

$map['zoom'] = 20;
$map['size'] = '640x640';
$map['scale'] = 1;
$map['maptype'] = 'satellite';

$markers = [];

$marker_points = [];

$point = [
    'lat' => GetValueFloat(17701 /*[Rasenmäher\Automower\letzter Breitengrad]*/),
    'lng' => GetValueFloat(40825 /*[Rasenmäher\Automower\letzter Längengrad]*/),
];

$marker_points[0] = $point;

$activity_label = ['E', 'D', 'G', 'L', 'P', 'F', 'R', 'M', 'S'];
$activity = GetValueInteger(30160 /*[Rasenmäher\Automower\Aktivität]*/) - 2;
if ($activity < 0 || $activity >= count($activity_label)) {
    $activity = 0;
}
$label = $activity_label[$activity];

$markers[] = [
    'color'     => 'green',
    'label'		   => $label,
    'points'    => $marker_points,
];

$map['markers'] = $markers;

$paths = [];
$paths[] = [
    'color'     => '0xff0000ff',       // 0xhhhhhhoo oo=opacity
    'weight'    => 2,
    'points'    => $points,
];

$map['paths'] = $paths;

$url = GoogleMaps_GenerateStaticMap(44269 /*[GoogleMaps]*/, json_encode($map));

$html = '<img width="500", height="500" src="' . $url . '" />';
SetValueString(17011 /*[GoogleMaps\Karte (static)]*/, $html);
