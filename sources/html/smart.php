<?php
/****************************************************
 * smart.php - Dashboard SMART NAS (monofichier)
 * Version complète avec :
 * - Material Dark Design
 * - % secteurs défectueux
 * - Date/heure dernier test SMART
 * - Lancement tests short/long
 ****************************************************/

// --- CONFIG ---
$DISK_FILTER = '/^sd[a-z]+$/'; // adapter si besoin

// --- ACTION SMARTCTL ---
$smart_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['device'], $_POST['test_type'])) {
    $device = escapeshellarg($_POST['device']);
    $test_type = $_POST['test_type'] === 'long' ? 'long' : 'short';

    $cmd = "sudo smartctl -t $test_type $device 2>&1";
    $output = shell_exec($cmd);
    $smart_message = "Commande exécutée : smartctl -t $test_type $device\n\n" . htmlspecialchars($output ?? '');
}

// --- FONCTIONS ---

function array_search_line($lines, $needle) {
    foreach ($lines as $i => $line) {
        if (stripos($line, $needle) !== false) return $i;
    }
    return null;
}

function format_bytes($bytes) {
    if ($bytes <= 0) return 'N/A';
    $units = ['B','KiB','MiB','GiB','TiB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return sprintf('%.1f %s', $bytes, $units[$i]);
}

function get_smart_defect_percent($device) {
    $cmd = "sudo smartctl -a " . escapeshellarg($device) . " 2>&1";
    $output = shell_exec($cmd);
    if (!$output) return null;

    $lines = explode("\n", $output);

    $realloc = 0;
    $pending = 0;
    $uncorrect = 0;
    $sector_size = null;
    $capacity_bytes = null;

    foreach ($lines as $line) {
        if (preg_match('/Sector Size:\s+(\d+)/i', $line, $m)) {
            $sector_size = (int)$m[1];
        }
        if (preg_match('/User Capacity:\s+([\d,]+)/i', $line, $m)) {
            $capacity_bytes = (int)str_replace(',', '', $m[1]);
        }
        if (preg_match('/Reallocated_Sector_Ct\s+.*\s(\d+)$/', $line, $m)) {
            $realloc = (int)$m[1];
        }
        if (preg_match('/Current_Pending_Sector\s+.*\s(\d+)$/', $line, $m)) {
            $pending = (int)$m[1];
        }
        if (preg_match('/Offline_Uncorrectable\s+.*\s(\d+)$/', $line, $m)) {
            $uncorrect = (int)$m[1];
        }
    }

    $bad = $realloc + $pending + $uncorrect;

    if (!$sector_size || !$capacity_bytes) return $bad;

    $total_sectors = $capacity_bytes / $sector_size;
    $percent = ($bad / $total_sectors) * 100;

    return min(100, round($percent, 4));
}

function get_smart_info($device) {
    $info = [
        'last_test'   => 'N/A',
        'last_result' => 'N/A',
    ];

    $cmd = "sudo smartctl -a " . escapeshellarg($device) . " 2>&1";
    $output = shell_exec($cmd);
    if (!$output) return $info;

    $lines = explode("\n", $output);

    $logStart = array_search_line($lines, 'SMART Self-test log');
    if ($logStart === null) return $info;

    for ($i = $logStart + 1; $i < count($lines); $i++) {
        $l = trim($lines[$i]);
        if ($l === '' || strpos($l, '#') !== 0) continue;

        $info['last_result'] = $l;

        if (preg_match('/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2})/', $l, $m)) {
            $info['last_test'] = $m[1];
        } else {
            if (preg_match('/\s(\d{3,6})\s+$/', $l, $m)) {
                $hours = (int)$m[1];
                $info['last_test'] = "Heures de vie disque : $hours h";
            }
        }
        break;
    }

    return $info;
}

function get_disks($filterRegex) {
    $disks = [];
    $json = shell_exec('lsblk -J -o NAME,TYPE,LABEL,MOUNTPOINT');
    if (!$json) return $disks;

    $data = json_decode($json, true);
    if (!isset($data['blockdevices'])) return $disks;

    foreach ($data['blockdevices'] as $dev) {
        if ($dev['type'] !== 'disk') continue;
        if (!preg_match($filterRegex, $dev['name'])) continue;

        $devicePath = '/dev/' . $dev['name'];
        $label = $dev['label'] ?? '';
        $mountpoint = '';
        $used = 0;
        $avail = 0;
        $used_percent = 0;

        if (!empty($dev['children'])) {
            foreach ($dev['children'] as $child) {
                if (!empty($child['mountpoint'])) {
                    $mountpoint = $child['mountpoint'];
                    $df = shell_exec('df -B1 ' . escapeshellarg($mountpoint) . ' | tail -n 1');
                    if ($df) {
                        $parts = preg_split('/\s+/', trim($df));
                        if (count($parts) >= 6) {
                            $used = (int)$parts[2];
                            $avail = (int)$parts[3];
                            $used_percent = (int)str_replace('%', '', $parts[4]);
                        }
                    }
                    break;
                }
            }
        }

        $smart_info = get_smart_info($devicePath);
        $defect_percent = get_smart_defect_percent($devicePath);

        $disks[] = [
            'device'        => $devicePath,
            'label'         => $label,
            'mountpoint'    => $mountpoint,
            'used'          => $used,
            'avail'         => $avail,
            'used_percent'  => $used_percent,
            'last_test'     => $smart_info['last_test'],
            'last_result'   => $smart_info['last_result'],
            'defect_percent'=> $defect_percent,
        ];
    }

    return $disks;
}

$disks = get_disks($DISK_FILTER);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>NAS SMART Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
/* Material Dark Design */
:root {
    --bg-color: #121212;
    --bg-elevated: #1E1E1E;
    --primary: #BB86FC;
    --secondary: #03DAC6;
    --text-main: #FFFFFF;
    --text-muted: #B3B3B3;
    --error: #CF6679;
    --border-color: #2A2A2A;
}
body {
    margin: 0;
    background-color: var(--bg-color);
    color: var(--text-main);
    font-family: system-ui;
}
.app-bar {
    background-color: var(--bg-elevated);
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    box-shadow: 0 2px 4px rgba(0,0,0,0.6);
}
.container {
    padding: 24px;
    max-width: 1200px;
    margin: auto;
}
.card {
    background-color: var(--bg-elevated);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.5);
}
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    padding: 10px 8px;
    border-bottom: 1px solid var(--border-color);
}
.badge {
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
}
.badge-ok { background-color: rgba(3,218,198,0.15); color: var(--secondary); }
.badge-warn { background-color: rgba(255,193,7,0.15); color: #FFC107; }
.badge-error { background-color: rgba(207,102,121,0.15); color: var(--error); }
.progress-bar {
    width: 100%;
    height: 6px;
    background-color: #2A2A2A;
    border-radius: 999px;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--secondary), var(--primary));
    border-radius: 999px;
}
.btn {
    padding: 6px 12px;
    border-radius: 999px;
    background-color: var(--primary);
    border: none;
    cursor: pointer;
}
select {
    background-color: #2A2A2A;
    color: var(--text-main);
    border-radius: 999px;
    padding: 4px 8px;
}
.smart-message {
    background-color: #1A1A1A;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}
</style>
</head>

<body>
<header class="app-bar">
    <div>
        <div style="font-size:20px;">NAS SMART Dashboard</div>
        <div style="font-size:12px;color:var(--text-muted);">Monitoring disques & tests SMART</div>
    </div>
    <button class="btn" onclick="location.reload();">Actualiser</button>
</header>

<main class="container">

<section class="card">
    <h3>Disques détectés : <?php echo count($disks); ?></h3>

    <table>
        <thead>
            <tr>
                <th>Device</th>
                <th>Label</th>
                <th>Montage</th>
                <th>Utilisé</th>
                <th>Libre</th>
                <th>% utilisé</th>
                <th>Dernier test</th>
                <th>Résultat</th>
                <th>% défectueux</th>
                <th>SMART</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($disks as $disk): ?>
            <?php
                $percent = $disk['used_percent'];
                $badgeClass = $percent < 70 ? 'badge-ok' : ($percent < 90 ? 'badge-warn' : 'badge-error');

                $def = $disk['defect_percent'];
                $defBadge = 'badge-ok';
                if ($def > 0.01) $defBadge = 'badge-warn';
                if ($def > 0.1)  $defBadge = 'badge-error';
            ?>
            <tr>
                <td><?= htmlspecialchars($disk['device']) ?></td>
                <td><?= htmlspecialchars($disk['label'] ?: '—') ?></td>
                <td><?= htmlspecialchars($disk['mountpoint'] ?: 'Non monté') ?></td>
                <td><?= format_bytes($disk['used']) ?></td>
                <td><?= format_bytes($disk['avail']) ?></td>
                <td>
                    <div class="badge <?= $badgeClass ?>"><?= $percent ?>%</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width:<?= $percent ?>%"></div>
                    </div>
                </td>
                <td><?= htmlspecialchars($disk['last_test']) ?></td>
                <td><?= htmlspecialchars($disk['last_result']) ?></td>
                <td>
                    <div class="badge <?= $defBadge ?>">
                        <?= $def ?>%
                    </div>
                </td>
                <td>
                    <form method="post" style="display:flex;gap:6px;">
                        <input type="hidden" name="device" value="<?= htmlspecialchars($disk['device']) ?>">
                        <select name="test_type">
                            <option value="short">Short</option>
                            <option value="long">Long</option>
                        </select>
                        <button class="btn">?</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php if ($smart_message): ?>
<section class="card">
    <h3>Résultat lancement SMART</h3>
    <div class="smart-message"><?= nl2br($smart_message) ?></div>
</section>
<?php endif; ?>

</main>
</body>
</html>
