<?php
// --- Chargement du .env ---
if (!file_exists(__DIR__ . "/.env")) die(".env introuvable !");
foreach (file(__DIR__ . "/.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    [$name, $value] = explode("=", $line, 2);
    $_ENV[trim($name)] = trim($value);
}

// --- Connexion à la base ---
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

// --- Récupération des joueurs ---
$players = $pdo->query("SELECT * FROM players ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"/>
<title>Swiss Hockey Map - Premium</title>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<link rel="stylesheet"
