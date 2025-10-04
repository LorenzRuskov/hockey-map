<?php
// --- Chargement du .env ---
if (!file_exists(__DIR__ . "/.env")) die(".env introuvable !");
foreach (file(__DIR__ . "/.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    [$name, $value] = explode("=", $line, 2);
    $_ENV[trim($name)] = trim($value);
}

// Connexion à la base
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

// Récupération des joueurs
$players = $pdo->query("SELECT * FROM players ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"/>
<title>Swiss Hockey Map - Premium</title>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css"/>
<style>
body { margin:0; font-family:'Segoe UI', Arial, sans-serif; background:#f4f4f9; }
header { background:#0b3d91; color:#fff; padding:20px 10px; text-align:center; }
header h1 { margin:0; font-size:2rem; }
#controls { padding:15px 20px; display:flex; gap:15px; flex-wrap:wrap; background:#fff; box-shadow:0 2px 5px rgba(0,0,0,0.1); }
#controls select { padding:5px 10px; font-size:1rem; border-radius:5px; border:1px solid #ccc; }
#map { height:60vh; width:100%; margin-top:10px; }
#list { padding:20px; display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:15px; }
.player-card { background:#fff; border-radius:12px; box-shadow:0 6px 12px rgba(0,0,0,0.15); overflow:hidden; transition:transform 0.2s; }
.player-card:hover { transform:translateY(-5px); }
.player-card img { width:100%; height:200px; object-fit:cover; background:#ddd; }
.player-body { padding:12px; }
.player-body h3 { margin:0 0 6px; font-size:1.2rem; color:#0b3d91; }
.player-body p { margin:4px 0; font-size:0.9rem; color:#333; }
@media(max-width:600px){#list{grid-template-columns:1fr}}
</style>
</head>
<body>
<header>
  <h1>Joueurs Suisses à l'Étranger</h1>
  <p>Interface interactive premium</p>
</header>

<div id="controls">
  <select id="filterLeague">
    <option value="">Toutes les ligues</option>
  </select>
  <select id="filterPosition">
    <option value="">Toutes les positions</option>
  </select>
  <select id="sortBy">
    <option value="name">Trier par nom</option>
    <option value="team">Trier par équipe</option>
  </select>
</div>

<div id="map"></div>
<div id="list"></div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
<script>
const allPlayers = <?= json_encode($players, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); ?>;
let filteredPlayers = [...allPlayers];

const map = L.map('map').setView([46.8,8.2],4);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

const markers = L.markerClusterGroup();
map.addLayer(markers);

const list = document.getElementById('list');
const filterLeague = document.getElementById('filterLeague');
const filterPosition = document.getElementById('filterPosition');
const sortBy = document.getElementById('sortBy');

function populateFilters() {
    const leagues = [...new Set(allPlayers.map(p => p.league).filter(Boolean))];
    const positions = [...new Set(allPlayers.map(p => p.position).filter(Boolean))];

    leagues.forEach(l => {
        const opt = document.createElement('option');
        opt.value = l; opt.textContent = l;
        filterLeague.appendChild(opt);
    });
    positions.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p; opt.textContent = p;
        filterPosition.appendChild(opt);
    });
}

function renderPlayers() {
    markers.clearLayers();
    list.innerHTML = '';
    const bounds = [];

    // Tri
    filteredPlayers.sort((a,b)=>{
        if(sortBy.value==='team') return (a.team_name||'').localeCompare(b.team_name||'');
        return (a.full_name||`${a.first_name} ${a.last_name}`.trim()).localeCompare(b.full_name||`${b.first_name} ${b.last_name}`.trim());
    });

    filteredPlayers.forEach(p => {
        const lat = parseFloat(p.lat);
        const lng = parseFloat(p.lng);
        const fullName = p.full_name || `${p.first_name || ""} ${p.last_name || ""}`.trim();

        if(!isNaN(lat) && !isNaN(lng)) {
            const popupHTML = `
                <div style="display:flex; align-items:center;">
                    <img src="${p.photo_url||'https://via.placeholder.com/80'}" style="width:80px;height:80px;border-radius:6px;margin-right:10px;">
                    <div>
                        <b>${fullName}</b> (#${p.jersey_number||"?"})<br>
                        ${p.team_name||"?"} (${p.team_city||"?"})<br>
                        Position: ${p.position||"?"}, Ligue: ${p.league||"?"}<br>
                        Naissance: ${p.birthdate||"?"} à ${p.birth_place||"?"}<br>
                        Taille: ${p.height_cm||"?"} cm, Poids: ${p.weight_kg||"?"} kg<br>
                        Main dominante: ${p.shoots_catches||"?"}
                    </div>
                </div>
            `;
            const marker = L.marker([lat,lng]).bindPopup(popupHTML);
            markers.addLayer(marker);
            bounds.push([lat,lng]);
        }

        const div = document.createElement('div');
        div.className='player-card';
        div.innerHTML = `
            <img src="${p.photo_url||'https://via.placeholder.com/300x200?text=No+Image'}">
            <div class="player-body">
                <h3>${fullName} (#${p.jersey_number||"?"})</h3>
                <p><strong>Équipe:</strong> ${p.team_name||"?"} (${p.team_city||"?"})</p>
                <p><strong>Ligue:</strong> ${p.league||"?"}</p>
                <p><strong>Position:</strong> ${p.position||"?"}</p>
                <p><strong>Naissance:</strong> ${p.birthdate||"?"} à ${p.birth_place||"?"}</p>
                <p><strong>Taille / Poids:</strong> ${p.height_cm||"?"} cm / ${p.weight_kg||"?"} kg</p>
                <p><strong>Main dominante:</strong> ${p.shoots_catches||"?"}</p>
            </div>
        `;
        list.appendChild(div);
    });

    if(bounds.length>0) map.fitBounds(bounds,{padding:[50,50]});
}

function applyFilters() {
    const leagueVal = filterLeague.value;
    const posVal = filterPosition.value;
    filteredPlayers = allPlayers.filter(p => {
        return (!leagueVal || p.league===leagueVal) && (!posVal || p.position===posVal);
    });
    renderPlayers();
}

populateFilters();
renderPlayers();

filterLeague.addEventListener('change', applyFilters);
filterPosition.addEventListener('change', applyFilters);
sortBy.addEventListener('change', renderPlayers);
</script>
</body>
</html>
