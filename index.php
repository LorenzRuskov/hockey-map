<?php
// --- Fonction pour charger .env ---
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode("=", $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

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

// Récupération des joueurs suisses
$players = $pdo->query("SELECT * FROM players ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
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
    body { margin:0; font-family: Arial, sans-serif; background:#f4f4f9; }
    header { background:#0b3d91; color:#fff; padding:15px 10px; text-align:center; }
    header h1 { margin:0; font-size:2rem; }
    #map { height:60vh; width:100%; }
    #list { padding:20px; display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:15px; }
    .player-card { background:#fff; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.1); overflow:hidden; transition:transform 0.2s; }
    .player-card:hover { transform:translateY(-5px); }
    .player-card img { width:100%; height:180px; object-fit:cover; background:#ddd; }
    .player-body { padding:10px; }
    .player-body h3 { margin:0 0 5px; font-size:1.1rem; color:#0b3d91; }
    .player-body p { margin:3px 0; font-size:0.9rem; color:#333; }
    @media(max-width:600px){#list{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <header>
    <h1>Joueurs Suisses à l'Étranger</h1>
    <p>Liste mise à jour depuis la base de données</p>
  </header>

  <div id="map"></div>
  <div id="list"></div>

  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
  <script>
    const players = <?= json_encode($players, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); ?>;

    const map = L.map('map').setView([46.8,8.2], 4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const markers = L.markerClusterGroup();
    const list = document.getElementById('list');
    const bounds = [];

    players.forEach(p => {
      const lat = parseFloat(p.lat);
      const lng = parseFloat(p.lng);

      // Popup si lat/lng valides
      if (!isNaN(lat) && !isNaN(lng)) {
        const popupHTML = `
          <div style="display:flex; align-items:center;">
            <img src="${p.photo_url || 'https://via.placeholder.com/60'}" 
                 alt="${p.name || '?'}" 
                 style="width:60px; height:60px; object-fit:cover; border-radius:5px; margin-right:8px;">
            <div>
              <b>${p.name || '?'}</b> (#${p.jersey_number || '?'})<br>
              ${p.team || '?'} (${p.position || '?'})<br>
              Ligue: ${p.league || '?'}<br>
              Taille: ${p.height_cm || '?'} cm, Poids: ${p.weight_kg || '?'} kg<br>
              Main dominante: ${p.shoots_catches || '?'}<br>
              Naissance: ${p.birthdate || '?'} à ${p.birth_place || '?'}
            </div>
          </div>
        `;
        const marker = L.marker([lat, lng]).bindPopup(popupHTML);
        markers.addLayer(marker);
        bounds.push([lat, lng]);
      }

      // Carte joueur dans la liste
      const div = document.createElement('div');
      div.className = 'player-card';
      div.innerHTML = `
        <img src="${p.photo_url || 'https://via.placeholder.com/300x180?text=No+Image'}" alt="${p.name || '?'}">
        <div class="player-body">
          <h3>${p.name || '?'} (#${p.jersey_number || '?'})</h3>
          <p><strong>Équipe:</strong> ${p.team || '?'} (${p.team_city || '?'})</p>
          <p><strong>Ligue:</strong> ${p.league || '?'}</p>
          <p><strong>Position:</strong> ${p.position || '?'}</p>
          <p><strong>Naissance:</strong> ${p.birthdate || '?'} à ${p.birth_place || '?'}</p>
          <p><strong>Taille / Poids:</strong> ${p.height_cm || '?'} cm / ${p.weight_kg || '?'} kg</p>
          <p><strong>Main dominante:</strong> ${p.shoots_catches || '?'}</p>
        </div>
      `;
      list.appendChild(div);
    });

    map.addLayer(markers);

    // Zoom automatique sur tous les marqueurs
    if (bounds.length > 0) {
      map.fitBounds(bounds, {padding:[50,50]});
    }
  </script>
</body>
</html>
