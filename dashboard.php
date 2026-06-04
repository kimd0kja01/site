<?php
session_start();
include 'functions.php';
include 'template.php';

$user = null;
$adrSerre = null;
$serres = [];

if (isset($_SESSION['idUser'])) {
  $pdo = pdo_connect_mysql();

  $stmt = $pdo->prepare('SELECT idUser FROM Sécurité WHERE idSecurite = ?');
  $stmt->execute([$_SESSION['idUser']]);
  $sec = $stmt->fetch();

  if ($sec && isset($sec['idUser'])) {
    $stmt2 = $pdo->prepare('SELECT * FROM User WHERE idUser = ?');
    $stmt2->execute([$sec['idUser']]);
    $user = $stmt2->fetch();

    $stmt3 = $pdo->prepare('SELECT idSerre, adrSerre FROM Serre WHERE idUser = ?');
    $stmt3->execute([$sec['idUser']]);
    $serres = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($serres)) {
      $adrSerre = $serres[0]['adrSerre'];
    }
  }
}

echo template_header('Home');
?>

<div class="content">
  <?php if ($user): ?>
    <h2>Bienvenu(e) Mr <?= htmlspecialchars($user['Nom'] ?? $user['pseudo']) ?>.</h2>
    <p>Voici les données de votre serre, adr : <?= htmlspecialchars($adrSerre ?? 'N/A') ?>.</p>
    <br>
    <div class="button-group mb-4 flex gap-2" id="but2">
      <button class="btn-sensor">Activer le ventilateur</button>
      <button class="btn-sensor">Activer le brumisateur</button>
      <button class="btn-sensor">Augmenter la luminosité</button>
    </div>
    <br>
  <?php else: ?>
    <h2>Bienvenue !</h2>
    <p>Impossible de retrouver les infos du compte.</p>
  <?php endif; ?>
</div>

<div class="min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-5xl">

    <!-- ✅ BOUTONS PRINCIPAUX -->
    <div class="button-group mb-4 flex justify-center gap-2">
      <button type="button" id="btn-ens" class="btn-sensor active" onclick="setActive('ens')">☀️ Ensoleillement</button>
      <button type="button" id="btn-temp" class="btn-sensor" onclick="setActive('temp')">🌡️ Température</button>
      <button type="button" id="btn-hum" class="btn-sensor" onclick="setActive('hum')">💧 Humidité</button>
    </div>

    <div class="bg-white rounded-lg shadow-lg p-6 w-full">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <div class="md:col-span-1 border rounded-lg p-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-lg" id="list-title">Dernières valeurs</h3>
            <span class="text-sm text-gray-500" id="last-update">--</span>
          </div>

          <div class="max-h-96 overflow-auto">
            <ul id="last-values" class="space-y-2 text-sm">
              <li class="text-gray-500">Chargement…</li>
            </ul>
          </div>
        </div>

        <div class="md:col-span-2 border rounded-lg p-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-lg" id="chart-title">Graphique</h3>
            <select id="timeline-select" onchange="onTimelineChange()" class="text-sm rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option value="10m">10 dernières minutes</option>
              <option value="1h">1 heure</option>
              <option value="24h" selected>24 heures</option>
            </select>
          </div>
          <div id="mainChart" style="height: 420px;"></div>
        </div>

      </div>

      <div class="mt-6 border rounded-lg p-4 bg-gray-50">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
          <div>
            <label for="serre-select" class="block text-sm font-medium text-gray-700">Serre</label>
            <select id="serre-select" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <?php if (empty($serres)): ?>
                <option value="">Aucune serre</option>
              <?php else: ?>
                <?php foreach ($serres as $serre): ?>
                  <option value="<?= htmlspecialchars($serre['idSerre']) ?>">Serre <?= htmlspecialchars($serre['idSerre']) ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
          <div>
            <label for="insert-interval" class="block text-sm font-medium text-gray-700">Intervalle (secondes)</label>
            <input id="insert-interval" type="number" min="2" max="60" value="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div class="flex gap-2 items-center">
            <button type="button" id="start-sim" class="bg-green-500 text-white rounded px-4 py-2 hover:bg-green-600">Démarrer</button>
            <button type="button" id="stop-sim" class="bg-red-500 text-white rounded px-4 py-2 hover:bg-red-600" disabled>Arrêter</button>
          </div>
        </div>
        <p id="sim-status" class="text-sm text-gray-600 mt-4">Simulation arrêtée.</p>
      </div>

    </div>

  </div>
</div>

<script>
const SOURCES = {
  ens:  { type: 1, title: "Ensoleillement", unit: "lux" },
  temp: { type: 2, title: "Température", unit: "°C" },
  hum:  { type: 3, title: "Taux d'humidité", unit: "%" }
};

let activeKey = "ens";
let activeTimeline = "24h";
let chart = null;
let refreshTimer = null;
let sensorState = { ens: null, temp: null, hum: null };

function setActiveButton(key) {
  document.querySelectorAll(".btn-sensor").forEach(btn => btn.classList.remove("active"));
  const btn = document.getElementById("btn-" + key);
  if (btn) btn.classList.add("active");
}

async function fetchJson(url) {
  const res = await fetch(url, { cache: "no-store" });
  if (!res.ok) throw new Error("HTTP " + res.status);
  return await res.json();
}

function formatNow() {
  return new Date().toLocaleString();
}

function renderLastValues(data, unit) {
  const ul = document.getElementById("last-values");
  ul.innerHTML = "";

  const last20 = data.slice(-20).reverse();

  if (!last20.length) {
    ul.innerHTML = `<li class="text-gray-500">Aucune donnée.</li>`;
    return;
  }

  last20.forEach(row => {
    const x = row.x ?? "";
    const y = row.value ?? "";

    const li = document.createElement("li");
    li.className = "flex justify-between border-b pb-1";
    li.innerHTML = `<span class="text-gray-600">${x}</span><span class="font-semibold">${y} ${unit}</span>`;
    ul.appendChild(li);
  });
}

async function loadAndRender(key) {
  const cfg = SOURCES[key];
  const url = `data.php?type=${cfg.type}&timeline=${encodeURIComponent(getTimeline())}`;

  document.getElementById("chart-title").textContent = cfg.title;
  document.getElementById("list-title").textContent = `20 dernières valeurs — ${cfg.title}`;

  const data = await fetchJson(url);

  renderLastValues(data, cfg.unit);
  document.getElementById("last-update").textContent = "Maj: " + formatNow();

  if (!chart) {
    chart = anychart.line(data);
    chart.container("mainChart");
    chart.title(cfg.title);
    chart.draw();
  } else {
    chart.title(cfg.title);
    chart.data(data);
  }
}

let insertTimer = null;

function setActive(key) {
  activeKey = key;
  setActiveButton(key);

  if (refreshTimer) clearInterval(refreshTimer);

  loadAndRender(key).catch(err => {
    console.error(err);
    document.getElementById("last-values").innerHTML =
      `<li class="text-red-600">Erreur de chargement des données.</li>`;
  });

  refreshTimer = setInterval(() => {
    loadAndRender(key).catch(console.error);
  }, 5000);
}

function getSelectedSerre() {
  const select = document.getElementById('serre-select');
  return select ? select.value : '';
}

function getTimeline() {
  // Timeline pour le graphique uniquement.
  const select = document.getElementById('timeline-select');
  return select ? select.value : '24h';
}

function onTimelineChange() {
  activeTimeline = getTimeline();
  loadAndRender(activeKey).catch(console.error);
}

function clamp(value, min, max) {
  return Math.max(min, Math.min(max, value));
}

function getSensorKey(type) {
  return type === 1 ? 'ens' : type === 2 ? 'temp' : 'hum';
}

function getDefaultSensorValue(type) {
  if (type === 1) return 450;
  if (type === 2) return 20;
  return 55;
}

function generateSensorValue(type) {
  const key = getSensorKey(type);
  let current = sensorState[key];
  if (current === null) {
    current = getDefaultSensorValue(type);
  }

  let next;
  if (type === 1) {
    next = current + (Math.random() * 100 - 50);
    next = clamp(next, 100, 1000);
  } else if (type === 2) {
    next = current + (Math.random() * 0.4 - 0.2);
    next = clamp(next, -5, 50);
  } else {
    next = current + (Math.random() * 4 - 2);
    next = clamp(next, 0, 100);
  }

  sensorState[key] = Number(next.toFixed(2));
  return sensorState[key];
}

async function initSensorState() {
  const types = [1, 2, 3];
  await Promise.all(types.map(async type => {
    const key = getSensorKey(type);
    if (sensorState[key] !== null) return;
    const url = `data.php?type=${type}&timeline=all&limit=1`;
    try {
      const data = await fetchJson(url);
      if (data.length) {
        sensorState[key] = Number(data[data.length - 1].value);
      }
    } catch (err) {
      console.warn('Impossible de récupérer l’état initial du capteur', type, err);
    }
    if (sensorState[key] === null) {
      sensorState[key] = getDefaultSensorValue(type);
    }
  }));
}

function updateSimulationStatus(message, isError = false) {
  const status = document.getElementById('sim-status');
  status.textContent = message;
  status.className = isError ? 'text-sm text-red-600 mt-4' : 'text-sm text-gray-600 mt-4';
}

async function insertRandomValue(type) {
  const idSerre = getSelectedSerre();
  if (!idSerre) {
    throw new Error('Aucune serre sélectionnée');
  }

  const value = generateSensorValue(type);
  const response = await fetch('insert_data.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      type: type,
      idSerre: idSerre,
      value: value
    })
  });

  if (!response.ok) {
    const errorData = await response.json().catch(() => ({}));
    throw new Error(errorData.error || 'Erreur serveur');
  }

  return await response.json();
}

async function insertRandomValuesAll() {
  const types = [1, 2, 3];
  const results = await Promise.all(types.map(type => insertRandomValue(type)));
  return results;
}

async function startSimulation() {
  if (insertTimer) return;

  const interval = Number(document.getElementById('insert-interval').value) || 5;
  const idSerre = getSelectedSerre();

  if (!idSerre) {
    updateSimulationStatus('Sélectionnez une serre pour lancer la simulation.', true);
    return;
  }

  if (interval < 2) {
    updateSimulationStatus('Intervalle minimal de 2 secondes.', true);
    return;
  }

  document.getElementById('start-sim').disabled = true;
  document.getElementById('stop-sim').disabled = false;

  updateSimulationStatus(`Simulation active sur serre ${idSerre} — insertion sur les 3 capteurs toutes les ${interval} secondes.`);

  try {
    await initSensorState();
    await insertRandomValuesAll();
    await loadAndRender(activeKey);
  } catch (err) {
    console.error(err);
    updateSimulationStatus(`Erreur d'insertion initiale : ${err.message}`, true);
  }

  insertTimer = setInterval(async () => {
    try {
      await insertRandomValuesAll();
      await loadAndRender(activeKey);
    } catch (err) {
      console.error(err);
      updateSimulationStatus(`Erreur d'insertion automatique : ${err.message}`, true);
      stopSimulation();
    }
  }, interval * 1000);
}

function stopSimulation() {
  if (!insertTimer) return;
  clearInterval(insertTimer);
  insertTimer = null;
  document.getElementById('start-sim').disabled = false;
  document.getElementById('stop-sim').disabled = true;
  updateSimulationStatus('Simulation arrêtée.');
}

const startButton = document.getElementById('start-sim');
const stopButton = document.getElementById('stop-sim');
if (startButton) startButton.addEventListener('click', startSimulation);
if (stopButton) stopButton.addEventListener('click', stopSimulation);

setActive("ens");
</script>

<?=template_footer()?>
