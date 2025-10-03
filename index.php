<?php
// --- Fonction pour charger .env ---
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // commentaire
        list($name, $value) = explode("=", $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Charger le .env
loadEnv(__DIR__ . "/.env");

// Variables DB depuis .env
$host = $_ENV["DB_HOST"];
$db   = $_ENV["DB_NAME"];
$user = $_ENV["DB_USER"];
$pass = $_ENV["DB_PASS"];

// Connexion PDO
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die("Erreur DB: " . $e->getMessage());
}

// Table enrichie
$pdo->exec("CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(50),
    name VARCHAR(100),
    team VARCHAR(100),
    nationality VARCHAR(100),
    position VARCHAR(50),
    birthdate DATE NULL,
    birth_place VARCHAR(150),
    description TEXT,
    photo_url VARCHAR(255),
    lat DECIMAL(10,6) NULL,
    lng DECIMAL(10,6) NULL
)");

// Vérifie si table vide → importe depuis API
$count = $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
if ($count == 0) {
    $names = ["Nico Hischier", "Roman Josi", "Kevin Fiala"];
    foreach ($names as $name) {
        $url = "https://www.thesportsdb.com/api/v1/json/1/searchplayers.php?p=" . urlencode($name);
        $json = json_decode(file_get_contents($url), true);

        if (!empty($json["player"][0])) {
            $p = $json["player"][0];
            $stmt = $pdo->prepare("INSERT INTO players 
                (external_id, name, team, nationality, position, birthdate, birth_place, description, photo_url, lat, lng)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $p["idPlayer"], $p["strPlayer"], $p["strTeam"], $p["strNationality"],
                $p["strPosition"], $p["dateBorn"], $p["strBirthLocation"], $p["strDescriptionEN"],
                $p["strThumb"], null, null
            ]);
        }
    }
}

// Récupération joueurs
$players = $pdo->query("SELECT * FROM players")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"/>
  <title>Swiss Hockey Map (API + .env)</title>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
  <style>
    body { margin:0; font-family: Arial, sans-serif; }
    header { background:#0b3d91; color:#fff; padding:10px; }
    #map { height:70vh; width:100%; }
    #list { padding:10px; }
    .player { margin:10px 0; border-bottom:1px solid #ccc; padding-bottom:8px; }
    .player img { max-width:100px; float:left; margin-right:10px; }
  </style>
</head>
<body>
  <header><h1>Swiss Hockey Map (via API)</h1></header>
  <div id="map"></div>
  <div id="list"><h3>Joueurs</h3></div>

  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script>
    const players = <?php echo json_encode($players, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); ?>;

    const map = L.map('map').setView([46.8, 8.2], 4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const list = document.getElementById('list');

    players.forEach(p => {
      if (p.lat && p.lng) {
        const marker = L.marker([p.lat, p.lng]).addTo(map);
        marker.bindPopup(`<b>${p.name}</b><br>${p.team} (${p.position})`);
      }
      const div = document.createElement('div');
      div.className = 'player';
      div.innerHTML = `
        ${p.photo_url ? `<img src="${p.photo_url}" alt="${p.name}"/>` : ""}
        <strong>${p.name}</strong> — ${p.team || "?"} (${p.position || "?"})<br/>
        Nationalité: ${p.nationality}<br/>
        Né le: ${p.birthdate || "?"} à ${p.birth_place || "?"}<br/>
        <p>${p.description ? p.description.substring(0,200)+"..." : ""}</p>
        <div style="clear:both"></div>
      `;
      list.appendChild(div);
    });
  </script>
</body>
</html>
