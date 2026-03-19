<?php
// SERVER-SIDE LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $host = 'localhost'; $dbname = 'tricycle_booking'; $username = 'root'; $password = '';
    try {
        $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $action = $input['action'] ?? '';
        if ($action === 'exec' || $action === 'query') {
            $stmt = $db->prepare($input['sql']);
            $stmt->execute($input['params'] ?? []);
            if ($action === 'exec') {
                echo json_encode(['success' => true, 'insertId' => $db->lastInsertId()]);
            } else {
                echo json_encode(['success' => true, 'result' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }
        } else { echo json_encode(['success' => false, 'error' => 'Invalid action']); }
    } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
    exit;
}
?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1e40af;
        --bg: #f3f4f6;
        --card: #ffffff;
    }

    /* Inalis ang body background para hindi mag-conflict sa main layout mo */
    .location-manager-container {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        max-width: 1100px;
        margin: 20px auto;
        background: var(--card);
        border-radius: 16px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        animation: slideUp 0.6s ease-out;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .header-banner {
        background: linear-gradient(135deg, var(--primary-dark), var(--primary), #bac3d5);
        color: white;
        padding: 30px 20px;
        text-align: center;
    }

    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        padding: 30px;
    }

    @media (max-width: 850px) { .content-grid { grid-template-columns: 1fr; } }

    #map {
        height: 400px;
        border-radius: 12px;
        border: 2px solid #e5e7eb;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        z-index: 1;
    }

    .form-group { margin-bottom: 20px; position: relative; }
    
    .form-group label {
        font-size: 0.9rem;
        font-weight: 600;
        color: #4b5563;
        margin-bottom: 8px;
        display: block;
    }

    .input-style {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 1rem;
        transition: all 0.3s ease;
        box-sizing: border-box;
    }

    .input-style:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .btn-submit {
        background: var(--primary);
        color: white;
        border: none;
        padding: 14px;
        border-radius: 10px;
        font-weight: 700;
        cursor: pointer;
        width: 100%;
        transition: transform 0.2s, background 0.2s;
        margin-bottom: 20px;
    }

    .btn-submit:hover { background: var(--primary-dark); transform: translateY(-1px); }

   .table-container {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        overflow-x: hidden; /* Iwas sa horizontal scroll */
        overflow-y: auto;   /* Lalabas ang scrollbar pag lumampas sa height */
        max-height: 550px;  /* Itakda kung gaano kahaba bago mag-scroll */
    }

    /* Optional: Para mas maganda ang itsura ng scrollbar */
    .table-container::-webkit-scrollbar {
        width: 6px;
    }
    .table-container::-webkit-scrollbar-thumb {
        background-color: #d1d5db;
        border-radius: 10px;
    }
    .table-container::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .loc-table { width: 100%; border-collapse: collapse; }
    .loc-table th { background: #f8fafc; padding: 12px; text-align: left; font-size: 0.85rem; color: #64748b; }
    .loc-table td { padding: 14px 12px; border-top: 1px solid #f1f5f9; font-size: 0.95rem; }

    tr.row-anim { animation: fadeIn 0.4s ease forwards; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    .loc-table tr:hover { background-color: #f0f7ff; cursor: pointer; }

    .message {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    .success { background: #dcfce7; color: #166534; }
    .error { background: #fee2e2; color: #991b1b; }
</style>

<div class="location-manager-container">
    <div class="header-banner">
        <h2 style="margin:0; color: white;"> South Caloocan Location Manager</h2>
        <p style="opacity: 0.9; margin: 10px 0 0 0">Add locations around South Caloocan • View on map</p>
    </div>

    <div class="content-grid">
        <div class="left-panel">
            <div class="form-group">
                <label>Search Location</label>
                <input type="text" id="location" class="input-style" list="location-hints" placeholder="Start typing (e.g. Monumento)" autocomplete="off">
                <datalist id="location-hints"></datalist>
            </div>

            <button class="btn-submit" onclick="addLocation()">Add to Database</button>

            <div id="map"></div>
        </div>

        <div class="right-panel">
            <h3 style="margin-top:0">Saved Locations</h3>
            <div class="table-container">
                <table class="loc-table">
                    <thead>
                        <tr><th>Location</th><th style="text-align:right">Action</th></tr>
                    </thead>
                    <tbody id="table-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Siguraduhing pareho ang API Keys mo rito
    const GEOAPIFY_KEY = "2c48f43ad4134a588fbbde01128581dc";
    const MAPTILER_KEY = "DI93VaqaUOALks9Ooffd";
    let map, markerGroup;

    document.addEventListener('DOMContentLoaded', () => {
        map = L.map('map').setView([14.656, 120.984], 14);
        L.tileLayer(`https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, {
            attribution: '© MapTiler'
        }).addTo(map);

        markerGroup = L.layerGroup().addTo(map);
        loadLocations();
        setupAutocomplete();
    });

    function setupAutocomplete() {
    const input = document.getElementById('location');
    const datalist = document.getElementById('location-hints');

    input.addEventListener('input', async (e) => {
        const val = e.target.value;
        if (val.length < 3) return;

        // Bias results to South Caloocan (Proximity search)
        const url = `https://api.geoapify.com/v1/geocode/autocomplete?text=${encodeURIComponent(val)}&filter=circle:120.984,14.656,5000&limit=5&apiKey=${GEOAPIFY_KEY}`;
        
        try {
            const res = await fetch(url);
            const data = await res.json();
            datalist.innerHTML = '';
            
            data.features.forEach(f => {
                const option = document.createElement('option');
                option.value = f.properties.formatted;
                datalist.appendChild(option);
            });
        } catch (err) { console.error("Autocomplete error", err); }
    });
}

// --- DB & MAP LOGIC ---
async function dbRequest(mode, sql, params = []) {
    const res = await fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: mode, sql, params })
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error);
    return data.result ?? data.insertId;
}

async function addLocation() {
    const locName = document.getElementById('location').value.trim();
    if (!locName) return showMessage("Enter a location", "error");

    try {
        const coords = await geocode(locName);
        
        await dbRequest('exec', "INSERT INTO locations (name, lat, lon) VALUES (?,?,?)", 
            [locName, coords.lat, coords.lon]);

        updateMapView(coords.lat, coords.lon, locName);
        showMessage("Saved successfully!", "success");
        document.getElementById('location').value = '';
        loadLocations();
    } catch (err) { showMessage(err.message, "error"); }
}

async function geocode(text) {
    const url = `https://api.geoapify.com/v1/geocode/search?text=${encodeURIComponent(text)}&apiKey=${GEOAPIFY_KEY}&limit=1`;
    const res = await fetch(url);
    const data = await res.json();

    if (!data.features?.length) throw new Error("Location not found");
    const [lon, lat] = data.features[0].geometry.coordinates;
    return { lat, lon };
}

function updateMapView(lat, lon, name) {
    markerGroup.clearLayers();
    L.marker([lat, lon]).addTo(markerGroup).bindPopup(name).openPopup();
    map.flyTo([lat, lon], 16, { animate: true, duration: 1.5 });
}

async function loadLocations() {
    const rows = await dbRequest('query', "SELECT * FROM locations ORDER BY id DESC LIMIT 10");
    const tbody = document.getElementById('table-body');
    tbody.innerHTML = '';

    rows.forEach((row, index) => {
        const tr = document.createElement('tr');
        tr.className = 'row-anim';
        tr.style.animationDelay = `${index * 0.05}s`;
        tr.innerHTML = `
            <td><strong>${row.name}</strong></td>
            <td style="text-align:right; color:#94a3b8; font-size:0.8rem">View ➔</td>
        `;
        tr.onclick = () => updateMapView(row.lat, row.lon, row.name);
        tbody.appendChild(tr);
    });
}

function showMessage(text, type) {
    const div = document.createElement('div');
    div.className = `message ${type}`;
    div.textContent = text;
    document.querySelector('.left').prepend(div);
    setTimeout(() => div.remove(), 3000);
}
</script>

<?php include '../includes/footer.php'; ?>