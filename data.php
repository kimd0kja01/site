<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['idUser'])) {
    echo json_encode(["error" => "no session"]);
    exit;
}

$mysqli = mysqli_connect("localhost", "root", "admin", "Projet_Mana");
if (!$mysqli) {
    echo json_encode(["error" => "db"]);
    exit;
}

/* 1️⃣ Récupérer le vrai idUser depuis Sécurité */
$stmt = $mysqli->prepare("SELECT idUser FROM Sécurité WHERE idSecurite = ?");
$stmt->bind_param("i", $_SESSION['idUser']);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
    echo json_encode(["error" => "user not found"]);
    exit;
}

$idUser = (int)$res['idUser'];

/* 2️⃣ Récupérer les serres */
$serres = [];
$result = $mysqli->query("SELECT idSerre FROM Serre WHERE idUser = $idUser");

while ($row = $result->fetch_assoc()) {
    $serres[] = (int)$row['idSerre'];
}

if (empty($serres)) {
    echo json_encode([]);
    exit;
}

$serreList = implode(',', $serres);

/* 3️⃣ Choix du type */
$type = $_GET['type'] ?? '';
$timeline = $_GET['timeline'] ?? '24h';
$limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 100;
$data = [];

$timelineFilters = [
    '10m' => ' AND dateInf >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)',
    '1h' => ' AND dateInf >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
    '24h' => ' AND dateInf >= DATE_SUB(NOW(), INTERVAL 24 HOUR)',
    'all' => ''
];
$timeline = isset($timelineFilters[$timeline]) ? $timeline : '24h';
$filter = $timelineFilters[$timeline];

if ($type === '1') {
    $sql = "SELECT dateInf AS x, tauxEns AS value 
            FROM Ensoleillement 
            WHERE idSerre IN ($serreList)$filter
            ORDER BY dateInf ASC LIMIT $limit";
}
elseif ($type === '2') {
    $sql = "SELECT dateInf AS x, Temp AS value 
            FROM Temperature 
            WHERE idSerre IN ($serreList)$filter
            ORDER BY dateInf ASC LIMIT $limit";
}
elseif ($type === '3') {
    $sql = "SELECT dateInf AS x, tauxHum AS value 
            FROM Humidite 
            WHERE idSerre IN ($serreList)$filter
            ORDER BY dateInf ASC LIMIT $limit";
}
else {
    echo json_encode(["error" => "bad type"]);
    exit;
}

$resData = $mysqli->query($sql);

while ($row = $resData->fetch_assoc()) {
    $data[] = $row;
}

$mysqli->close();
echo json_encode($data);
