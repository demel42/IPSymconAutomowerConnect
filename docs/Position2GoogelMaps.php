<?php

// Beispiel zur Datestellung der GPS-Ќoordinaten mit Hilfe von GooleMaps (siehe https://github.com/demel42/IPSymconGoogleMaps.git)
// in dem Script verwendete Objekt-ID's durch die eigenen ID's ersetzen

// allgemeine Angaben zur Karte
$map = [];

$map['zoom'] = 20;
$map['size'] = '640x640';
$map['scale'] = 1;
$map['maptype'] = 'satellite';

// aktuelle Position
$markers = [];

$marker_points = [];

$point = [
        'lat' => GetValueFloat(17701 /*[Rasenmäher\Automower\letzter Breitengrad]*/),
        'lng' => GetValueFloat(40825 /*[Rasenmäher\Automower\letzter Längengrad]*/),
    ];

$marker_points[0] = $point;

$activity = GetValueInteger(30160 /*[Rasenmäher\Automower\Aktivität]*/);
$activity_label = ['E', 'D', 'P', 'L', 'S', 'F', 'M'];
$label = $activity_label[$activity + 1];

$markers[] = [
        'color'     => 'green',
        'label'		   => $label,
        'points'    => $marker_points,
    ];

$map['markers'] = $markers;

// Fahrten der letzten 3 Tage
$paths = [];

$paths_color = ['0xFF4040', '0x7B68EE', '0x00FF00'];

$dt = new DateTime(date('d.m.Y 00:00:00', time()));
$now = $dt->format('U');

for ($i = 2; $i >= 0; $i--) {
    $from = $now - ($i * 24 * 60 * 60);
    $until = $from + (24 * 60 * 60) - 1;

    $values = AC_GetLoggedValues(17849 /*[Archive]*/, 54501 /*[Rasenmäher\Automower\Position]*/, $from, $until, 0);

    $points = [];
    foreach ($values as $value) {
        $pos = json_decode($value['Value'], true);
        $points[] = [
                'lat' => $pos['latitude'],
                'lng' => $pos['longitude'],
            ];
    }

    $paths[] = [
            'color'     => $paths_color[$i],
            'weight'    => 2,
            'points'    => $points,
        ];
}

$map['paths'] = $paths;

$url = GoogleMaps_GenerateStaticMap(44269 /*[GoogleMaps]*/, json_encode($map));

$html = '<img width="500", height="500" src="' . $url . '" />';
SetValueString(12495 /*[GoogleMaps\Karte (static/day)]*/, $html);
