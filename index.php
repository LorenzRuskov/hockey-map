<?php
loadEnv(__DIR__ . "/.env");

$host = $_ENV["DB_HOST"];
$db   = $_ENV["DB_NAME"];
$user = $_ENV["DB_USER"];
$pass = $_ENV["DB_PASS"];

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die("Erreur DB: " . $e->getMessage());
}

// Tous les joueurs suisses
$players = $pdo->query("
    SELECT full_name, team_name, team_city, jersey_number, primary_position_name, league,
           birthdate, birth_place, birth_country, height_cm, weight_kg, shoots_catches,
           headshot_url, lat, lng
    FROM players
    WHERE nationality='CHE'
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"/>
  <title>Swiss Hockey Map</title>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css"/>
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css"/>
  <style>
    body { margin:0; font-family: 'Segoe UI', Arial, sans-serif; background:#f0f2f5; }
    header { background:#1d1d2c; color:#fff; padding:20px; text-align:center; }
    header h1 { margin:0; font-size:2.2rem; letter-spacing:1px; }
    header p { margin:5px 0 0; font-size:0.9rem; color:#ccc; }

    #map { height:60vh; width:100%; }
    #list { padding:20px; display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:20px; }

    .player-card { background:#fff; border-radius:15px; box-shadow:0 8px 20px rgba(0,0,0,0.1); overflow:hidden; transition:transform 0.3s, box-shadow 0.3s; }
    .player-card:hover { transform:translateY(-8px); box-shadow:0 12px 30px rgba(0,0,0,0.15); }

    .player-card img { width:100%; height:200px; object-fit:cover; background:#ddd; transition:transform 0.3s; }
    .player-card img:hover { transform:scale(1.05); }

    .player-body { padding:15px; }
    .player-body h3 { margin:0 0 8px; font-size:1.2rem; color:#1d1d2c; }
    .player-body p { margin:4px 0; font-size:0.9rem; color:#555; line-height:1.3; }

    .player-body p span { font-weight:600; color:#1d1d2c; }

    @media(max-width:600px){#list{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <header>
    <h1>Joueurs Suisses à l'Étranger</h1>
    <p>Visualisez les joueurs suisses et leurs équipes dans le monde</p>
  </header>

  <div id="map"></div>
  <div id="list"></div>

  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
  <script>
    const players = <?= json_encode($players, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); ?>;

    const map = L.map('map').setView([46.8,8.2],4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const markers = L.markerClusterGroup();
    const list = document.getElementById('list');
    const bounds = [];

    players.forEach(p => {
      const lat = parseFloat(p.lat);
      const lng = parseFloat(p.lng);

      if (!isNaN(lat) && !isNaN(lng)) {
        const popupHTML = `
          <div style="display:flex; align-items:center; gap:10px;">
            <img src="${p.headshot_url || 'https://via.placeholder.com/60'}" 
                 alt="${p.full_name || '?'}" 
                 style="width:60px; height:60px; object-fit:cover; border-radius:10px;">
            <div>
              <b>${p.full_name || '?'}</b> (#${p.jersey_number || '?'})<br>
              ${p.team_name || '?'} (${p.team_city || '?'})<br>
              ${p.primary_position_name || '?'} | ${p.league || '?'}<br>
              Né le ${p.birthdate || '?'} à ${p.birth_place || '?'} (${p.birth_country || '?'})<br>
              ${p.height_cm || '?'} cm / ${p.weight_kg || '?'} kg | Main: ${p.shoots_catches || '?'}
            </div>
          </div>
        `;
        const marker = L.marker([lat, lng]).bindPopup(popupHTML);
        markers.addLayer(marker);
        bounds.push([lat, lng]);
      }

      const div = document.createElement('div');
      div.className = 'player-card';
      div.innerHTML = `
        <img src="${p.headshot_url || 'https://via.placeholder.com/300x180?text=No+Image'}" alt="${p.full_name || '?'}">
        <div class="player-body">
          <h3>${p.full_name || '?'}</h3>
          <p><span>Numéro:</span> ${p.jersey_number || '?'}</p>
          <p><span>Équipe:</span> ${p.team_name || '?'} (${p.team_city || '?'})</p>
          <p><span>Ligue:</span> ${p.league || '?'}</p>
          <p><span>Position:</span> ${p.primary_position_name || '?'}</p>
          <p><span>Naissance:</span> ${p.birthdate || '?'} à ${p.birth_place || '?'} (${p.birth_country || '?'})</p>
          <p><span>Taille / Poids:</span> ${p.height_cm || '?'} cm / ${p.weight_kg || '?'} kg</p>
          <p><span>Main dominante:</span> ${p.shoots_catches || '?'}</p>
        </div>
      `;
      list.appendChild(div);
    });

    map.addLayer(markers);

    if (bounds.length > 0) {
      map.fitBounds(bounds, {padding:[60,60]});
    }
  </script>
</body>
</html>
