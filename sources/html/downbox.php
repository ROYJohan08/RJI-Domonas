<?php
	$os = "linux";
	$rootName = "../../../media/Runable/DownBox/DownBox";
	$tmdbKey = "Key";
	$deletePassword = "Mot de passe fort";
	$fixedDestinationsPaths = [
		"Docs01"   => "/media/Docs01",
		"Docs02"   => "/media/Docs02",
		"Docs03"   => "/media/Docs03",
		"Series01" => "/media/Series01",
		"Series02" => "/media/Series02",
		"Series03" => "/media/Series03",
		"Films01"  => "/media/Films01",
		"Films02"  => "/media/Films02",
		"Films03"  => "/media/Films03",
	];
	function tmdb_get($url) {
		$token = "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJmYjRkZDU2NThlYWNjN2JlZWFhNmMyMzBhZTEzMjRmNSIsIm5iZiI6MTU4NzAyMDExMC4yNDUsInN1YiI6IjVlOTgwMTRlOWRlZmRhMDAxYWJiMmQ1MiIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.kCC55LmL-wn1Ics1qjNbafsPnRw4wB3o1lI_DjIyRL0";
    		$opts = [
        		"http" => [
            			"header" =>
                			"Authorization: Bearer $token\r\n" .
                			"Accept: application/json\r\n",
            				"timeout" => 8
        		]
    		];
    		$ctx = stream_context_create($opts);
    		$json = @file_get_contents($url, false, $ctx);
    		return $json ? json_decode($json, true) : null;
	}
	function securePath(string $path, string $root) {
		$dir = realpath(dirname($path));
		if (!$dir) return false;
		$final = $dir . '/' . basename($path);
		$root = rtrim(realpath($root), '/');
		if (strpos($final, $root) !== 0) return false;
		return $final;
	}
	function safeDelete(string $path): bool {
		if (!file_exists($path)) return false;
		if (is_file($path) || is_link($path)) {
			return @unlink($path);
		}
		$it = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $file) {
			if ($file->isDir()) {
				@rmdir($file->getPathname());
			} else {
				@unlink($file->getPathname());
			}
		}
		return @rmdir($path);
	}
	function runSystemCommand(string $action, string $source, ?string $target = null) {
		global $root;
		$source = securePath($source, $root);
		if (!$source) return false;
		if ($target !== null) {
			$target = securePath($target, $root);
			if (!$target) return false;
		}
		switch ($action) {
			case 'rename':
				if (file_exists($target)) return false;
				return @rename($source, $target);

			case 'move':
				if (file_exists($target)) return false;
				return @rename($source, $target);

			case 'mkdir':
				if (file_exists($source)) return false;
				return @mkdir($source, 0775, true);

			case 'delete':
				return safeDelete($source);
		}
		return false;
	}
	function checkRoot($rootName){
		$root = realpath(__DIR__ . "/$rootName");
		if (!$root) {
			die("Racine introuvable.");
		}
		$currentSubDir = $_GET['path'] ?? '';
		$currentSubDir = str_replace(['..', './'], '', $currentSubDir);
		$basePath = realpath($root . '/' . $currentSubDir);
		if (!$basePath || strpos($basePath, $root) !== 0) {
			$basePath = $root;
			$currentSubDir = '';
		}
		return [$root,$basePath,$currentSubDir];
	}
	function getItems($basePath){
		$allItems = [];
		if (is_dir($basePath)) {
			foreach (scandir($basePath) as $item) {
				if ($item[0] === '.') continue;
				$allItems[] = $item;
			}
		}
		$localFolders = [];
		foreach ($allItems as $item) {
			if (is_dir($basePath . '/' . $item)) {
				$localFolders[] = $item;
			}
		}
		return [$allItems, $localFolders];
	}
	
	[$root,$basePath,$currentSubDir] = checkRoot($rootName);
	[$allItems,$localFolders] = getItems($basePath);
	
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$action = $_POST['action'] ?? '';
		echo $action."<br/>";
		if ($action === 'delete') {
			if(!isset($_POST['password'])){return;}
			$password = $_POST['password'] ?? '';
			if ($password === $deletePassword) {
				$item = basename($_POST['target_item'] ?? '');
				if ($item !== '') {
					$full = securePath($basePath . '/' . $item, $root);
					if ($full) {
						safeDelete($full);
					}
				}
			}
		}
		elseif ($action === 'archive') {
			$script = "/media/Archive01/Archive.sh";
			if (file_exists($script)) {
				$output = [];
				$returnCode = 0;
				exec(escapeshellcmd($script) . " 2>&1", $output, $returnCode);
				file_put_contents("archive.log",
					"[" . date("Y-m-d H:i:s") . "] Archive.sh exécuté (code $returnCode)\n",
					FILE_APPEND
				);
			}
		}
		elseif ($action === 'rename') {
			$old = basename($_POST['old_name'] ?? '');
			$new = trim($_POST['new_name'] ?? '');
			if ($old === '' || $new === '' || $old === $new) {
				return;
			}
			$oldExt = pathinfo($old, PATHINFO_EXTENSION);
			$newExt = pathinfo($new, PATHINFO_EXTENSION);
			if ($oldExt !== '' && $newExt === '') {
				$new .= '.' . $oldExt;
			}
			if ($oldExt !== '' && $newExt !== '' && strtolower($newExt) !== strtolower($oldExt)) {
				$new = pathinfo($new, PATHINFO_FILENAME) . '.' . $oldExt;
			}
			$oldPath = securePath($basePath . '/' . $old, $root);
			$newPath = securePath($basePath . '/' . $new, $root);
			if ($oldPath && $newPath && !file_exists($newPath)) {
				@rename($oldPath, $newPath);
			}
		}
		elseif ($action === 'move') {
			$fileName = basename($_POST['file_name'] ?? '');
			$destType = $_POST['dest_type'] ?? '';
			$destKey  = $_POST['dest_name'] ?? '';
			if ($fileName !== '' && $destType !== '' && $destKey !== '') {
				if ($destType === 'fixed') {
					$targetPath = $fixedDestinationsPaths[$destKey] ?? null;
				} else {
					$targetPath = $basePath . '/' . $destKey;
				}
				if ($targetPath && file_exists($targetPath)) {
					$src = securePath($basePath . '/' . $fileName, $root);
					$dst = securePath($targetPath . '/' . $fileName, $root);
					if ($src && $dst && !file_exists($dst)) {
						@rename($src, $dst);
					}
				}
			}
		}
		elseif ($action === 'mkdir') {
			$folderName = preg_replace('/[\\/:*?"<>|]/', '_', $_POST['dir_name'] ?? '');
			$folderName = trim($folderName);
			if ($folderName !== '') {
				$dirPath = securePath($basePath . '/' . $folderName, $root);
				if (!$dirPath) {
					$dirPath = $basePath . '/' . $folderName;
					if (strpos(realpath(dirname($dirPath)), $root) === 0) {
						@mkdir($dirPath, 0775, true);
					}
				} else {
					if (!file_exists($dirPath)) {
						@mkdir($dirPath, 0775, true);
					}
				}
			}
		}
		elseif ($action === 'tmdb_rename_folder') {
		    $folder = basename($_POST['folder_name']);
		    $serieName = trim($_POST['serie_name']);
		    $season = intval($_POST['season_number']);
		    $folderPath = securePath($basePath . '/' . $folder, $root);
		    if (!$folderPath) die("Dossier invalide.");
		    $searchUrl = "https://api.themoviedb.org/3/search/tv?query=" . urlencode($serieName) . "&language=fr-FR";
		    $results = tmdb_get($searchUrl);
		    if (!$results || empty($results['results'])) {
		        die("Aucune série trouvée.");
		    }
		    echo "<h2 style='color:white;'>Sélectionne la série :</h2>";
		    foreach ($results['results'] as $r) {
		        $id = $r['id'];
		        $name = htmlspecialchars($r['name']);
		        $year = substr($r['first_air_date'] ?? "0000", 0, 4);
		        echo "
		        <form method='POST' style='margin-bottom:10px;'>
		            <input type='hidden' name='action' value='tmdb_confirm'>
		            <input type='hidden' name='folder_name' value='$folder'>
		            <input type='hidden' name='season_number' value='$season'>
		            <input type='hidden' name='show_id' value='$id'>
		            <button class='btn' style='width:100%; text-align:left;'>
		                🎬 $name ($year)
		            </button>
		        </form>
		        ";
		    }
		    return;
		}
		elseif ($action === 'tmdb_confirm') {
		    $folder = basename($_POST['folder_name']);
		    $season = intval($_POST['season_number']);
		    $showId = intval($_POST['show_id']);
		    $folderPath = securePath($basePath . '/' . $folder, $root);
		    if (!$folderPath) die("Dossier invalide.");
		    $seasonUrl = "https://api.themoviedb.org/3/tv/$showId/season/$season?language=fr-FR";
		    $seasonData = tmdb_get($seasonUrl);
		    if (!$seasonData || empty($seasonData['episodes'])) {
		        die("Impossible de récupérer les épisodes.");
		    }
		    $episodes = [];
		    foreach ($seasonData['episodes'] as $ep) {
		        $episodes[$ep['episode_number']] = $ep['name'];
		    }
		    $videoExt = ['mkv','mp4','avi','mov','wmv'];
		    foreach (scandir($folderPath) as $file) {
		        if ($file[0] === '.') continue;
		        $full = $folderPath . '/' . $file;
		        if (!is_file($full)) continue;
		        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		        if (!in_array($ext, $videoExt)) continue;
		        if (!preg_match('/(\d+)/', $file, $m)) continue;
		        $epNum = intval($m[1]);
		        if (!isset($episodes[$epNum])) continue;
		        $newName = sprintf(
		            "S%02dE%02d - %s.%s",
		            $season,
		            $epNum,
		            preg_replace('/[\\/:*?"<>|]/', '-', $episodes[$epNum]),
		            $ext
		        );

		        rename($full, $folderPath . '/' . $newName);
		    }

		    header("Location: ?path=" . urlencode($currentSubDir));
		    return;
		}
		if(strlen($currentSubDir)>0){
			header("Location: ?path=" . urlencode($currentSubDir));
		}
		else{
			header("Location: #");
		}
		return;
	}
	if (isset($_GET['tmdb']) && $_GET['tmdb'] === '1') {
		header('Content-Type: application/json; charset=utf-8');
		$q = trim($_GET['q'] ?? '');
		if ($q === '') {
			echo json_encode(['results' => []]);
			exit;
		}
		$url = "https://api.themoviedb.org/3/search/multi?api_key=" . urlencode($tmdbKey) .
			   "&query=" . urlencode($q) . "&language=fr-FR";
		$ctx = stream_context_create([
			'http' => [
				'timeout' => 5
			]
		]);
		$json = @file_get_contents($url, false, $ctx);
		if ($json === false) {
			echo json_encode(['results' => []]);
		} else {
			echo $json;
		}
		exit;
	}
?>
<!DOCTYPE html>
<html lang="fr">
	<head>
		<meta charset="UTF-8">
		<title>NeoManager v3.0</title>
		<style>
			:root { --bg: #0d0f14; --card: #161b22; --accent: #7c4dff; --danger: #ff4d4d; --text: #c9d1d9; --border: #30363d; }
			body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; padding: 20px; margin: 0; }
			.container { max-width: 1250px; margin: 40px auto; }
			.glass { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
			.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
			table { width: 100%; border-collapse: collapse; }
			td, th { padding: 12px; text-align: left; border-bottom: 1px solid #21262d; }
			input, select { background: #0d1117; border: 1px solid var(--border); color: white; padding: 8px; border-radius: 6px; outline: none; }
			.btn { background: var(--accent); color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; }
			.btn-tmdb { background: transparent; border: 1px solid var(--accent); color: var(--accent); margin-left: 4px; }
			.btn-del { background: transparent; color: var(--danger); border: 1px solid var(--danger); margin-left: 4px; font-size: 11px; }
			.btn-del:hover { background: var(--danger); color: white; }
			a { color: var(--accent); text-decoration: none; font-weight: 500; }
			#overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:90; backdrop-filter: blur(5px); }
			#tmdb-modal { display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:var(--card); padding:25px; border: 1px solid var(--accent); z-index:100; border-radius:15px; width:450px; }
			.tmdb-item { padding: 10px; border-bottom: 1px solid #333; cursor: pointer; }
			.tmdb-item:hover { background:#1f2933; }
			.actions-cell { display: flex; align-items: center; min-width: 350px; gap: 6px; }
			#results { max-height: 300px; overflow-y: auto; }
			.spinner { border: 3px solid #333; border-top: 3px solid var(--accent); border-radius: 50%; width: 20px; height: 20px; animation: spin 0.8s linear infinite; margin-right: 8px; display:inline-block; vertical-align:middle; }
			@keyframes spin { 0% { transform: rotate(0deg);} 100% { transform: rotate(360deg);} }
		</style>
	</head>
	<body>
		<div class="container">
			<div class="header">
				<form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>" style="margin-left:20px;">
					<input type="hidden" name="action" value="archive" />
					<input type="submit" class="btn" style="background:#444;" value="📦 Lancer l'archivage" />
				</form>
				<div>
					<h1 style="margin:0; font-weight: 300;">NEO<span style="color:var(--accent); font-weight: 800;">MANAGER</span></h1>
					<code style="color:#8b949e;">DownBox/<?php echo htmlspecialchars($currentSubDir); ?></code>
				</div>
				<form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>" style="display:flex; gap:10px;">
					<input type="hidden" name="action" value="mkdir" />
					<input type="text" name="dir_name" placeholder="Nouveau dossier..." required />
					<input type="submit" class="btn" value="CRÉER" />
				</form>
			</div>	
			<div class="glass">
				<table>
					<thead>
						<tr>
							<th style="width: 30%;">ÉLÉMENT</th>
							<th style="width: 45%;">ACTIONS</th>
							<th style="width: 25%;">DÉPLACER</th>
						</tr>
					</thead>
					<tbody>
						<?php if ($currentSubDir != ''): ?>
						<tr>
							<td colspan="3">
								<a href="?path=<?php echo urlencode(dirname($currentSubDir) == '.' ? '' : dirname($currentSubDir)); ?>">⤴ Retour</a>
							</td>
						</tr>
						<?php endif; ?>		
						<?php foreach ($allItems as $item):
							$isDir = is_dir($basePath . '/' . $item);
							$id = md5($item);
						?>
						<tr>
							<td>
								<?php if($isDir): ?>
									📁 <a href="?path=<?php echo urlencode(($currentSubDir ? $currentSubDir.'/' : '').$item); ?>"><?php echo htmlspecialchars($item); ?></a>
								<?php else: ?>
									📄 <span style="color:#8b949e;"><?php echo htmlspecialchars($item); ?></span>
								<?php endif; ?>
							</td>
							<td class="actions-cell">
								<form method="POST" style="display:flex; flex-grow:1;">
									<input type="hidden" name="action" value="rename">
									<input type="hidden" name="old_name" value="<?php echo htmlspecialchars($item); ?>">
									<input type="text" name="new_name" id="in_<?php echo $id; ?>" value="<?php echo htmlspecialchars($item); ?>" style="flex-grow:1; min-width: 100px;">
									<input type="submit" class="btn" title="Sauvegarder" value="💾" />
								</form>
								<form>
									<?php if($isDir): ?>
										<button type="button" class="btn btn-tmdb" onclick="openTMDBFolder('<?php echo addslashes($item); ?>')">🔍</button>
									<?php else: ?>
										<button type="button" class="btn btn-tmdb" onclick="searchTMDB('<?php echo addslashes($item); ?>', 'in_<?php echo $id; ?>')" title="Chercher TMDB">🔍</button>
									<?php endif; ?>
								</form>
								<form method="POST" onsubmit="return confirmDelete('<?php echo addslashes($item); ?>', this)">
									<input type="hidden" name="action" value="delete">
									<input type="hidden" name="target_item" value="<?php echo htmlspecialchars($item); ?>">
									<input type="hidden" name="password" class="pass-field">
									<input type="submit" class="btn btn-del" title="Supprimer" value="🗑️"/>
								</form>
							</td>
							<td>
								<form method="POST" id="form_move_<?php echo $id; ?>">
									<input type="hidden" name="action" value="move">
									<input type="hidden" name="file_name" value="<?php echo htmlspecialchars($item); ?>">
									<input type="hidden" name="dest_type" id="type_<?php echo $id; ?>">
									<input type="hidden" name="dest_name" id="val_<?php echo $id; ?>">
									<select onchange="submitMove('<?php echo $id; ?>', this)" style="width:100%;">
										<option value="">Destination...</option>
										<?php if (!empty($localFolders)): ?>
										<optgroup label="📂 LOCAUX">
											<?php foreach ($localFolders as $folder): if($folder == $item) continue; ?>
												<option value="local:<?php echo htmlspecialchars($folder); ?>">./<?php echo htmlspecialchars($folder); ?></option>
											<?php endforeach; ?>
										</optgroup>
										<?php endif; ?>
										<optgroup label="🚀 RACINES">
											<?php foreach ($fixedDestinationsPaths as $name => $path): ?>
												<option value="fixed:<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></option>
											<?php endforeach; ?>
										</optgroup>
									</select>
								</form>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<div id="overlay" onclick="closeModal()"></div>
		<div id="tmdb-modal">
			<h3 style="margin:0 0 15px 0; color:var(--accent);">TMDB Search</h3>
			<div id="results"></div>
			<button class="btn" onclick="closeModal()" style="width:100%; margin-top:15px; background:#333;">Fermer</button>
		</div>
		<div id="tmdb-folder-modal" style="display:none; position:fixed; top:50%; left:50%;
			transform:translate(-50%,-50%); background:var(--card); padding:25px; border:1px solid var(--accent);
			border-radius:15px; width:450px; z-index:200;">
			<h3 style="margin:0 0 15px 0; color:var(--accent);">Renommage Série TMDB</h3>
			<form method="POST">
				<input type="hidden" name="action" value="tmdb_rename_folder">
				<input type="hidden" name="folder_name" id="tmdb_folder_name">
				<label>Nom de la série :</label>
				<input type="text" name="serie_name" required style="width:100%; margin-bottom:10px;">
				<label>Saison :</label>
				<input type="number" name="season_number" min="1" required style="width:100%; margin-bottom:10px;">
				<button class="btn" style="width:100%;">Valider</button>
			</form>
			<button class="btn" onclick="closeTMDBFolder()" style="width:100%; margin-top:15px; background:#333;">
				Annuler
			</button>
		</div>
		<script>
			let activeInput = null;
			function confirmDelete(name, form) {
				const pass = prompt(`⚠️ SUPPRESSION DÉFINITIVE\n\nÉlément : "${name}"\n\nMot de passe requis :`);
				if (!pass) return false;
				form.querySelector('.pass-field').value = pass;
				return true;
			}
			function submitMove(id, select) {
				if (!select.value) return;
				const [type, val] = select.value.split(':');
				document.getElementById('type_' + id).value = type;
				document.getElementById('val_' + id).value = val;
				document.getElementById('form_move_' + id).submit();
			}	
			async function searchTMDB(fname, inputId) {
				activeInput = document.getElementById(inputId);
				let q = fname.includes('.') ? fname.substring(0, fname.lastIndexOf('.')) : fname;
				q = q.replace(/[._-]/g, " ").trim();		
				document.getElementById('overlay').style.display = 'block';
				document.getElementById('tmdb-modal').style.display = 'block';
				document.getElementById('results').innerHTML = '<span class="spinner"></span> Recherche TMDB...';	
				try {
					const r = await fetch(`?tmdb=1&q=${encodeURIComponent(q)}`);
					const d = await r.json();
					let html = '';
					(d.results || []).slice(0, 6).forEach(m => {
						let title = m.title || m.name || '';
						let date = (m.release_date || m.first_air_date || '').split('-')[0] || '';
						let safeTitle = title.replace(/'/g, "\\'");
						html += `<div class="tmdb-item" onclick="apply('${safeTitle} (${date})')"><strong>${title}</strong> (${date})</div>`;
					});
					document.getElementById('results').innerHTML = html || "Aucun résultat.";
				} catch (e) {
					document.getElementById('results').innerHTML = "Erreur de recherche TMDB.";
				}
			}	
			function apply(val) {
				const lastDot = activeInput.value.lastIndexOf('.');
				const ext = lastDot !== -1 ? activeInput.value.substring(lastDot) : '';
				const safe = val.replace(/[\\/:*?"<>|]/g, '-');
				activeInput.value = safe + ext;
				closeModal();
			}	
			function closeModal() {
				document.getElementById('overlay').style.display='none';
				document.getElementById('tmdb-modal').style.display='none';
			}
			function openTMDBFolder(folder) {
				document.getElementById('tmdb_folder_name').value = folder;
				document.getElementById('tmdb-folder-modal').style.display = 'block';
				document.getElementById('overlay').style.display = 'block';
			}
			function closeTMDBFolder() {
				document.getElementById('tmdb-folder-modal').style.display = 'none';
				document.getElementById('overlay').style.display = 'none';
			}
			
			
		</script>
	</body>
</html>
