<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test map — Kostaa</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="stylesheet" href="https://unpkg.com/maplibre-gl@5.15.0/dist/maplibre-gl.css">
    <style>
        html, body, #map { height: 100%; margin: 0; }
        #map { background: #d5d0c8; }
    </style>
</head>
<body>
    <div id="map"></div>
    <script src="https://unpkg.com/maplibre-gl@5.15.0/dist/maplibre-gl.js"></script>
    <script>
        const map = new maplibregl.Map({
            container: 'map',
            style: 'https://tiles.openfreemap.org/styles/liberty',
            center: [23.3247, 42.6975],
            zoom: 16.2,
            pitch: 62,
            bearing: -18,
            maxPitch: 75,
            attributionControl: true,
        });

        map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'top-right');
    </script>
</body>
</html>
