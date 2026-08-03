<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Google Maps Dark Local</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <!-- MapLibre GL JS -->
  <script src="lib/maplibre-gl.js"></script>
  <link href="lib/maplibre-gl.css" rel="stylesheet" />

  <!-- PMTiles Plugin -->
  <script src="lib/pmtiles.js"></script>

  <style>
    body, html { margin: 0; padding: 0; height: 100%; width: 100%; background-color: #121212; }
    #map { height: 100vh; width: 100vw; }
  </style>
</head>
<body>
  <div id="map"></div>

  <script>
    // 1. Déclaration du protocole pmtiles://
    const protocol = new pmtiles.Protocol();
    maplibregl.addProtocol("pmtiles", protocol.tile);

    const PMTILES_URL = "/europe.pmtiles";

    // 2. Création de la carte
    const map = new maplibregl.Map({
      container: 'map',
      style: {
        version: 8,
        glyphs: "fonts/{fontstack}/{range}.pbf",
        sources: {
          "protomaps": {
            "type": "vector",
            "url": `pmtiles://${PMTILES_URL}`
          }
        },
        layers: [
          // Fond d'écran terrestre (Google Dark Ground)
          {
            "id": "background",
            "type": "background",
            "paint": { "background-color": "#212124" }
          },
          // Plan d'eau (Google Dark Water)
          {
            "id": "water",
            "type": "fill",
            "source": "protomaps",
            "source-layer": "water",
            "paint": { "fill-color": "#17263c" }
          },
          // Bâtiments (Google Dark Building Footprints)
          {
            "id": "buildings",
            "type": "fill",
            "source": "protomaps",
            "source-layer": "buildings",
            "minzoom": 13,
            "paint": {
              "fill-color": "#282d34",
              "fill-outline-color": "#1f2329",
              "fill-opacity": 0.9
            }
          },
          // Reseau routier secondaire et local (Rues)
          {
            "id": "roads_local",
            "type": "line",
            "source": "protomaps",
            "source-layer": "roads",
            "filter": ["!=", ["get", "pmap:kind"], "highway"],
            "paint": {
              "line-color": "#2c2d30",
              "line-width": [
                "interpolate", ["linear"], ["zoom"],
                12, 1,
                16, 4
              ]
            }
          },
          // Autoroutes et axes principaux (Google Dark Highways)
          {
            "id": "roads_highways",
            "type": "line",
            "source": "protomaps",
            "source-layer": "roads",
            "filter": ["==", ["get", "pmap:kind"], "highway"],
            "paint": {
              "line-color": "#3c4043",
              "line-width": [
                "interpolate", ["linear"], ["zoom"],
                6, 1.5,
                14, 5
              ]
            }
          },
          // Noms des voies / rues (Google Dark Street Labels)
          {
            "id": "labels_roads",
            "type": "symbol",
            "source": "protomaps",
            "source-layer": "roads",
            "minzoom": 13,
            "layout": {
              "symbol-placement": "line",
              "text-field": "{name}",
              "text-font": ["OpenSansRegular"],
              "text-size": 11,
              "text-max-angle": 30
            },
            "paint": {
              "text-color": "#9aa0a6",
              "text-halo-color": "#212124",
              "text-halo-width": 2
            }
          },
          // Noms des Villes et Lieux (Google Dark City Labels)
          {
            "id": "labels_places",
            "type": "symbol",
            "source": "protomaps",
            "source-layer": "places",
            "layout": {
              "text-field": "{name}",
              "text-font": ["OpenSansRegular"],
              "text-size": [
                "interpolate", ["linear"], ["zoom"],
                4, 11,
                10, 16
              ]
            },
            "paint": {
              "text-color": "#e8eaed",
              "text-halo-color": "#212124",
              "text-halo-width": 2
            }
          }
        ]
      },
      center: [2.3522, 48.8566], // Paris
      zoom: 6
    });

    map.addControl(new maplibregl.NavigationControl());
  </script>
</body>
</html>
