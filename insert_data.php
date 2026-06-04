<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload JSON requis']);
    exit;
}

$type = isset($input['type']) ? (int)$input['type'] : 0;
$idSerre = isset($input['idSerre']) ? $input['idSerre'] : '';
$value = isset($input['value']) && is_numeric($input['value']) ? (float)$input['value'] : null;

if (!$type || !$idSerre) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

if (!preg_match('/^\d+$/', (string)$idSerre)) {
    http_response_code(400);
    echo json_encode(['error' => 'idSerre invalide']);
    exit;
}

$idSerre = (int)$idSerre;

if (!isset($_SESSION['idUser'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Session invalide']);
    exit;
}

include 'functions.php';
$pdo = pdo_connect_mysql();

$stmt = $pdo->prepare('SELECT idUser FROM Sécurité WHERE idSecurite = ?');
$stmt->execute([$_SESSION['idUser']]);
$sec = $stmt->fetch();

if (!$sec || !isset($sec['idUser'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Utilisateur introuvable']);
    exit;
}

$idUser = (int)$sec['idUser'];

$stmt = $pdo->prepare('SELECT COUNT(*) FROM Serre WHERE idUser = ? AND idSerre = ?');
$stmt->execute([$idUser, $idSerre]);
$allowed = (int)$stmt->fetchColumn();

if ($allowed !== 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès interdit à cette serre']);
    exit;
}

$sql = '';
$params = [];

switch ($type) {
    case 1:
        if ($value === null) {
            $value = rand(100, 1000) / 1;
        }
        $sql = 'INSERT INTO Ensoleillement (idSerre, tauxEns, dateInf) VALUES (?, ?, NOW())';
        $params = [$idSerre, $value];
        break;
    case 2:
        if ($value === null) {
            $value = round(rand(150, 350) / 10, 2); // 15-35 °C
        }
        $sql = 'INSERT INTO Temperature (idSerre, Temp, dateInf) VALUES (?, ?, NOW())';
        $params = [$idSerre, $value];
        break;
    case 3:
        if ($value === null) {
            $value = round(rand(30, 90) / 1, 2); // 30-90 %
        }
        $sql = 'INSERT INTO Humidite (idSerre, tauxHum, dateInf) VALUES (?, ?, NOW())';
        $params = [$idSerre, $value];
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Type invalide']);
        exit;
}

$stmt = $pdo->prepare($sql);
if (!$stmt->execute($params)) {
    http_response_code(500);
    echo json_encode(['error' => 'Impossible d’insérer la donnée']);
    exit;
}

echo json_encode([
    'success' => true,
    'type' => $type,
    'idSerre' => $idSerre,
    'value' => $value,
    'inserted_at' => date('Y-m-d H:i:s')
]);
