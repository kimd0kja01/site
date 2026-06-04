<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'functions.php';
include 'template.php';

// Connexion BDD
$pdo = pdo_connect_mysql();

// Vérifier connexion
if (!isset($_SESSION['idUser'])) {
    die("Utilisateur non connecté");
}

// 🔐 Récupérer le vrai idUser depuis la table Sécurité
$stmtUser = $pdo->prepare("SELECT idUser FROM Sécurité WHERE idSecurite = ?");
$stmtUser->execute([$_SESSION['idUser']]);
$userData = $stmtUser->fetch();

if (!$userData) {
    die("Utilisateur introuvable");
}

$idUser = (int)$userData['idUser'];

// 🔎 Récupérer les serres liées à cet utilisateur
$stmtSerres = $pdo->prepare("SELECT idSerre FROM Serre WHERE idUser = ?");
$stmtSerres->execute([$idUser]);
$serres = $stmtSerres->fetchAll(PDO::FETCH_COLUMN);

if (empty($serres)) {
    $serres = [0]; // évite erreur SQL
}

$placeholders = implode(',', array_fill(0, count($serres), '?'));

$exportType = $_GET['type'] ?? 'ens';
$allowedTypes = ['ens', 'temp', 'hum'];
if (!in_array($exportType, $allowedTypes, true)) {
    $exportType = 'ens';
}

if (isset($_GET['export'])) {
    if ($exportType === 'temp') {
        $stmtExport = $pdo->prepare(
            "SELECT idSerre, Temp, dateInf FROM Temperature WHERE idSerre IN ($placeholders) ORDER BY dateInf DESC"
        );
        $headers = ['Serre', 'Température', 'Date'];
    } elseif ($exportType === 'hum') {
        $stmtExport = $pdo->prepare(
            "SELECT idSerre, tauxHum, dateInf FROM Humidite WHERE idSerre IN ($placeholders) ORDER BY dateInf DESC"
        );
        $headers = ['Serre', 'Humidité', 'Date'];
    } else {
        $stmtExport = $pdo->prepare(
            "SELECT idSerre, tauxEns, dateInf FROM Ensoleillement WHERE idSerre IN ($placeholders) ORDER BY dateInf DESC"
        );
        $headers = ['Serre', 'Lux', 'Date'];
    }

    $stmtExport->execute($serres);
    $rowsExport = $stmtExport->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="donnees_' . $exportType . '_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);

    foreach ($rowsExport as $r) {
        if ($exportType === 'temp') {
            $line = ['Serre ' . $r['idSerre'], number_format($r['Temp'], 2), $r['dateInf']];
        } elseif ($exportType === 'hum') {
            $line = ['Serre ' . $r['idSerre'], number_format($r['tauxHum'], 2), $r['dateInf']];
        } else {
            $line = ['Serre ' . $r['idSerre'], number_format($r['tauxEns'], 2), $r['dateInf']];
        }
        fputcsv($output, $line);
    }

    fclose($output);
    exit;
}


// ================== REQUÊTES FILTRÉES ==================

// 🌞 Ensoleillement
$stmt_ens = $pdo->prepare("
    SELECT idEns, idSerre, tauxEns, dateInf
    FROM Ensoleillement
    WHERE idSerre IN ($placeholders)
    ORDER BY dateInf DESC
    LIMIT 50
");
$stmt_ens->execute($serres);
$rows_ens = $stmt_ens->fetchAll();

// 🌡️ Température
$stmt_temp = $pdo->prepare("
    SELECT idTemp, idSerre, Temp, dateInf
    FROM Temperature
    WHERE idSerre IN ($placeholders)
    ORDER BY dateInf DESC
    LIMIT 50
");
$stmt_temp->execute($serres);
$rows_temp = $stmt_temp->fetchAll();

// 💧 Humidité
$stmt_hum = $pdo->prepare("
    SELECT idHum, idSerre, tauxHum, dateInf
    FROM Humidite
    WHERE idSerre IN ($placeholders)
    ORDER BY dateInf DESC
    LIMIT 50
");
$stmt_hum->execute($serres);
$rows_hum = $stmt_hum->fetchAll();
?>

<?=template_header('Données Capteurs')?>

<div class="listing-container">

    <div class="top-bar">
        <div class="button-group">
            <button type="button" class="btn-sensor<?= $exportType === 'ens' ? ' active' : '' ?>" data-type="ens" onclick="showData('ens', this)">☀️ Ensoleillement</button>
            <button type="button" class="btn-sensor<?= $exportType === 'temp' ? ' active' : '' ?>" data-type="temp" onclick="showData('temp', this)">🌡️ Température</button>
            <button type="button" class="btn-sensor<?= $exportType === 'hum' ? ' active' : '' ?>" data-type="hum" onclick="showData('hum', this)">💧 Humidité</button>
        </div>

        <div class="export-bar">
        <form method="get" action="listing.php" class="export-form">
            <label for="export-type">Exporter :</label>
            <select id="export-type" name="type" onchange="changeExportType(this.value)">
                <option value="ens"<?= $exportType === 'ens' ? ' selected' : '' ?>>Ensoleillement</option>
                <option value="temp"<?= $exportType === 'temp' ? ' selected' : '' ?>>Température</option>
                <option value="hum"<?= $exportType === 'hum' ? ' selected' : '' ?>>Humidité</option>
            </select>
            <button type="submit" name="export" value="1" class="btn-export">Exporter CSV</button>
            <button type="button" class="btn-export btn-export-pdf" onclick="exportPdf(document.getElementById('export-type').value)">Exporter PDF</button>
        </form>
    </div>
    </div>

    <!-- ================= ENSOLEILLEMENT ================= -->
    <div id="ens-section" class="data-section<?= $exportType === 'ens' ? ' active' : '' ?>">
        <?php if (!$rows_ens): ?>
            <div class="empty-state"><div class="empty-state-icon">☀️</div><p>Aucune donnée disponible</p></div>
        <?php else: ?>
            <div class="listing-grid">
                <?php for ($i = 0; $i < min(3, count($rows_ens)); $i++): $r = $rows_ens[$i]; ?>
                    <div class="listing-card">
                        <div class="listing-card-label">Serre <?=htmlspecialchars($r['idSerre'])?></div>
                        <div class="listing-card-value"><?=number_format($r['tauxEns'],2)?> <span class="listing-card-unit">lux</span></div>
                        <div class="listing-card-date">📅 <?=htmlspecialchars($r['dateInf'])?></div>
                    </div>
                <?php endfor; ?>
            </div>
            <table class="listing-table">
                <thead><tr><th>Serre</th><th>Lux</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($rows_ens as $r): ?>
                    <tr>
                        <td>Serre <?=htmlspecialchars($r['idSerre'])?></td>
                        <td><?=number_format($r['tauxEns'],2)?> lux</td>
                        <td><?=htmlspecialchars($r['dateInf'])?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- ================= TEMPÉRATURE ================= -->
    <div id="temp-section" class="data-section<?= $exportType === 'temp' ? ' active' : '' ?>">
        <?php if (!$rows_temp): ?>
            <div class="empty-state"><div class="empty-state-icon">🌡️</div><p>Aucune donnée disponible</p></div>
        <?php else: ?>
            <div class="listing-grid">
                <?php for ($i = 0; $i < min(3, count($rows_temp)); $i++): $r = $rows_temp[$i]; ?>
                    <div class="listing-card">
                        <div class="listing-card-label">Serre <?=htmlspecialchars($r['idSerre'])?></div>
                        <div class="listing-card-value"><?=number_format($r['Temp'],2)?> <span class="listing-card-unit">°C</span></div>
                        <div class="listing-card-date">📅 <?=htmlspecialchars($r['dateInf'])?></div>
                    </div>
                <?php endfor; ?>
            </div>
            <table class="listing-table">
                <thead><tr><th>Serre</th><th>Température</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($rows_temp as $r): ?>
                    <tr>
                        <td>Serre <?=htmlspecialchars($r['idSerre'])?></td>
                        <td><?=number_format($r['Temp'],2)?> °C</td>
                        <td><?=htmlspecialchars($r['dateInf'])?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- ================= HUMIDITÉ ================= -->
    <div id="hum-section" class="data-section<?= $exportType === 'hum' ? ' active' : '' ?>">
        <?php if (!$rows_hum): ?>
            <div class="empty-state"><div class="empty-state-icon">💧</div><p>Aucune donnée disponible</p></div>
        <?php else: ?>
            <div class="listing-grid">
                <?php for ($i = 0; $i < min(3, count($rows_hum)); $i++): $r = $rows_hum[$i]; ?>
                    <div class="listing-card">
                        <div class="listing-card-label">Serre <?=htmlspecialchars($r['idSerre'])?></div>
                        <div class="listing-card-value"><?=number_format($r['tauxHum'],2)?> <span class="listing-card-unit">%</span></div>
                        <div class="listing-card-date">📅 <?=htmlspecialchars($r['dateInf'])?></div>
                    </div>
                <?php endfor; ?>
            </div>
            <table class="listing-table">
                <thead><tr><th>Serre</th><th>Humidité</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($rows_hum as $r): ?>
                    <tr>
                        <td>Serre <?=htmlspecialchars($r['idSerre'])?></td>
                        <td><?=number_format($r['tauxHum'],2)?> %</td>
                        <td><?=htmlspecialchars($r['dateInf'])?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script>
function showData(type, btn) {
    document.querySelectorAll('.data-section').forEach(sec => sec.classList.remove('active'));
    document.querySelectorAll('.btn-sensor').forEach(b => b.classList.remove('active'));
    document.getElementById(type + '-section').classList.add('active');
    btn.classList.add('active');
    const exportSelect = document.getElementById('export-type');
    if (exportSelect) {
        exportSelect.value = type;
    }
}

function changeExportType(type) {
    const selectedButton = document.querySelector('.btn-sensor[data-type="' + type + '"]');
    if (selectedButton) {
        showData(type, selectedButton);
    }
}

function exportPdf(type) {
    const section = document.getElementById(type + '-section');
    if (!section) {
        alert('Type d’export invalide.');
        return;
    }

    const rows = Array.from(section.querySelectorAll('table tbody tr'));
    if (!rows.length) {
        alert('Aucune donnée à exporter.');
        return;
    }

    const headers = Array.from(section.querySelectorAll('table thead th'))
        .map(th => th.innerText.trim());

    const body = rows.map(tr =>
        Array.from(tr.querySelectorAll('td')).map(td => td.innerText.trim())
    );

    if (!window.jspdf || !window.jspdf.jsPDF) {
        alert('La bibliothèque PDF n’est pas chargée.');
        return;
    }

    const { jsPDF } = window.jspdf;

    // Logo à remplacer par ton image en base64
    const logo = "/image2.png";

    // Données entreprise
    const entreprise = {
        nom: "M.A.N.A",
        adresse: "Siège social M.A.N.A - Marseille",
        telephone: "Téléphone : 06 61 91 95 38",
        email: "Email : contact@mana.com",
    };

    const titleMap = {
        ens: "Ensoleillement",
        temp: "Température",
        hum: "Humidité"
    };

    const doc = new jsPDF({
        unit: "pt",
        format: "a4"
    });

    // Logo
    try {
        doc.addImage(logo, "PNG", 40, 40, 70, 45);
    } catch (e) {
        // Pas d'image valide, on continue sans logo
    }

    // Infos entreprise
    doc.setFont("helvetica", "bold");
    doc.setFontSize(14);
    doc.text(entreprise.nom, 125, 50);

    doc.setFont("helvetica", "normal");
    doc.setFontSize(9);
    doc.text(entreprise.adresse, 125, 63);
    doc.text(entreprise.telephone, 125, 76);
    doc.text(entreprise.email, 125, 86);

    // Date
    doc.setFontSize(9);
    doc.text(
        "Date : " + new Date().toLocaleDateString("fr-FR"),
        430,
        50
    );

    // Ligne de séparation
    doc.setDrawColor(195, 159, 66);
    doc.setLineWidth(1);
    doc.line(40, 105, 555, 105);

    // Titre
    doc.setFont("helvetica", "bold");
    doc.setFontSize(18);
    doc.setTextColor(40);
    doc.text("Extraction des données - " + titleMap[type], 40, 135);

    // Tableau
    doc.autoTable({
        startY: 180,
        head: [headers],
        body: body,
        theme: "grid",
        margin: { left: 40, right: 40 },

        headStyles: {
            fillColor: [195, 159, 66],
            textColor: 255,
            fontStyle: "bold",
            halign: "center"
        },

        styles: {
            fontSize: 9,
            cellPadding: 6,
            valign: "middle",
            textColor: [40, 40, 40]
        },

        alternateRowStyles: {
            fillColor: [245, 245, 245]
        },

        didDrawPage: function () {
            const pageHeight = doc.internal.pageSize.height;

            doc.setFont("helvetica", "normal");
            doc.setFontSize(8);
            doc.setTextColor(120);

            doc.text("Document généré automatiquement", 40, pageHeight - 25);

            doc.text(
                "Page " + doc.internal.getNumberOfPages(),
                500,
                pageHeight - 25
            );
        }
    });

    const now = new Date().toISOString().slice(0, 19).replace(/[:T]/g, "");

    doc.save("donnees" + type + "_" + now + ".pdf");
}
</script>



<?=template_footer()?>
