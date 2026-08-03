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
          // Fond d'écran sombre
          {
            "id": "background",
            "type": "background",
            "paint": { "background-color": "#121212" }
          },
          // Plan d'eau (Bleu foncé)
          {
            "id": "water",
            "type": "fill",
            "source": "protomaps",
            "source-layer": "water",
            "paint": { "fill-color": "#0e2a47" }
          },
          // Routes (Orange)
          {
            "id": "roads",
            "type": "line",
            "source": "protomaps",
            "source-layer": "roads",
            "paint": {
              "line-color": "#d97706",
              "line-width": 1.2
            }
          },
          // Noms des Villes (Texte Blanc)
          {
            "id": "labels_places",
            "type": "symbol",
            "source": "protomaps",
            "source-layer": "places",
            "layout": {
              "text-field": "{name}",
              "text-size": 13,
              "text-font": ["OpenSansRegular"]
            },
            "paint": {
              "text-color": "#ffffff",
              "text-halo-color": "#121212",
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
