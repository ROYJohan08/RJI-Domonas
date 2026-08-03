<?php
// ==========================================
// 1. DÉFINITION DES CLASSES DE BASE
// ==========================================

enum ErrorLevel: int {
    case ALL     = 0;
    case INFO    = 1;
    case WARNING = 2;
    case ERROR   = 3;
}

class ErrorItem {
    public function __construct(
        public readonly string $content,
        public readonly ErrorLevel $level,
        public readonly \DateTimeImmutable $date
    ) {}
}

class Errors {
    private static array $errors = [];

    public static function add(string $content, ErrorLevel $level): bool {
        $content = trim(strip_tags($content));
        if (strlen($content) < 5) {
            return false;
        }

        if (class_exists(\Transliterator::class)) {
            $trans = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($trans !== null) {
                $content = $trans->transliterate($content);
            }
        }

        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        self::$errors[] = new ErrorItem(
            content: $content,
            level: $level,
            date: new \DateTimeImmutable()
        );
        self::sort();
        return true;
    }

    /** @return ErrorItem[] */
    public static function get(?ErrorLevel $level = null): array {
        if ($level === null || $level === ErrorLevel::ALL) {
            return self::$errors;
        }
        return array_values(
            array_filter(
                self::$errors,
                fn (ErrorItem $e) => $e->level === $level
            )
        );
    }

    public static function clear(): void {
        self::$errors = [];
    }

    private static function sort(): void {
        usort(
            self::$errors,
            fn (ErrorItem $a, ErrorItem $b) =>
                $a->level->value <=> $b->level->value
        );
    }
}

class Config {
    private static $os = "linux";
    private static $downboxPath = "../ media/Runable/DownBox/DownBox";
    private static $zimPath = "/media/Runable/Docker/ZI-Data/";
    private static $tmdbKey = "fb4dd5658eacc7beeaa6c230ae1324f5";
    private static $passwordHash = "$2y$10$8qXxq84kfZ.vh4ROt4XfFuvtXlbTRS8Uqu.Ju/SN9xgsxaleSn.fy";
    private static $fixedDestinationsPaths = [
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

    public static function getOs(): string { return self::$os; }
    public static function getDownboxPath(): string { return realpath(__DIR__ . "/" . self::$downboxPath) ?: self::$downboxPath; }
    public static function getZimPath(): string { return self::$zimPath; }
    public static function getTmdbKey(): string { return self::$tmdbKey; }
    public static function getPasswordHash(): string { return self::$passwordHash; }
    public static function getFixedDestinationsPaths(): array { return self::$fixedDestinationsPaths; }
    public static function getFixedDestination(string $name): ?string { return self::$fixedDestinationsPaths[$name] ?? null; }
}

class Utils {

    public static function getZimFiles(): array {
        $path = Config::getZimPath();
        $real = realpath($path);
        if ($real === false || !is_dir($real)) {
            Errors::add("Erreur sur le chemin d'accès à ZIM", ErrorLevel::ERROR);
            return [];
        }
        return array_filter(scandir($real), function($f) use ($real) {
            return is_file($real . '/' . $f) && preg_match('/\.zim$/i', $f);
        });
    }

    public static function getAllDownBox(string $path): array {
        $root = Config::getDownboxPath();
        $real = realpath($path);
        if ($real !== false && strpos($real, $root) === 0) {
            return is_dir($real) ? array_filter(scandir($real), fn($f) => $f[0] !== '.') : [];
        }
        Errors::add("Erreur sur le chemin d'accès à DownBox", ErrorLevel::ERROR);
        return [];
    }

    public static function getAllDownBoxFolder(string $path): array {
        $root = Config::getDownboxPath();
        $real = realpath($path);
        if ($real === false || strpos($real, $root) !== 0) {
            Errors::add("Erreur sur le chemin d'accès à DownBox", ErrorLevel::ERROR);
            return [];
        }
        $items = self::getAllDownBox($real);
        return array_filter($items, fn($i) => $i[0] !== '.' && is_dir($real . '/' . $i));
    }

    public static function clearSubDir(string $subDir): string {
        $subDir = trim($subDir);
        $subDir = preg_replace('#\.\.+#', '', $subDir);
        $subDir = str_replace('\\', '/', $subDir);
        $subDir = preg_replace('#/+#', '/', $subDir);
        return ltrim($subDir, '/');
    }

    public static function runRename(string $source, string $cible): void {
        $root = Config::getDownboxPath();
        $realSource = realpath($source);
        $realTargetDir = realpath(dirname($cible));
        $targetName = basename($cible);

        if ($realSource === false || $realTargetDir === false) {
            Errors::add("L'un des chemins fournis est incorrect", ErrorLevel::ERROR);
            return;
        }
        if (strpos($realSource, $root) !== 0) {
            Errors::add("Le chemin de source est incorrect", ErrorLevel::ERROR);
            return;
        }
        if (strpos($realTargetDir, $root) !== 0) {
            Errors::add("Le chemin de cible est incorrect", ErrorLevel::ERROR);
            return;
        }

        if (Config::getOs() === "linux") {
            $cmd = "mv " . escapeshellarg($realSource) . " " . escapeshellarg($realTargetDir . "/" . $targetName);
        } else {
            $src = str_replace('/', '\\', $realSource);
            $cmd = "ren \"$src\" \"$targetName\"";
        }
        Errors::add((string)shell_exec($cmd . " > /dev/null 2>&1 &"), ErrorLevel::INFO);
    }

    public static function runMove(string $source, string $cible): void {
        $root = Config::getDownboxPath();
        $realSource = realpath($source);
        $realTargetDir = realpath(dirname($cible));
        $targetName = basename($cible);

        if ($realSource === false || $realTargetDir === false) {
            Errors::add("L'un des chemins fournis est incorrect", ErrorLevel::ERROR);
            return;
        }
        if (strpos($realSource, $root) !== 0) {
            Errors::add("Le chemin de source est incorrect", ErrorLevel::ERROR);
            return;
        }
        if (strpos($realTargetDir, $root) !== 0) {
            Errors::add("Le chemin de cible est incorrect", ErrorLevel::ERROR);
            return;
        }
        if (file_exists($realTargetDir . "/" . $targetName)) {
            Errors::add("La cible existe déjà", ErrorLevel::ERROR);
            return;
        }

        if (Config::getOs() === "linux") {
            $cmd = "mv " . escapeshellarg($realSource) . " " . escapeshellarg($realTargetDir . "/" . $targetName);
        } else {
            $src = str_replace('/', '\\', $realSource);
            $dst = str_replace('/', '\\', $realTargetDir . "\\" . $targetName);
            $cmd = "move \"$src\" \"$dst\"";
        }
        Errors::add((string)shell_exec($cmd . " > /dev/null 2>&1 &"), ErrorLevel::INFO);
    }

    public static function runCreate(string $source): void {
        $root = Config::getDownboxPath();
        $realParent = realpath(dirname($source));
        $folderName = basename($source);

        if ($realParent === false) {
            Errors::add("Le dossier parent n'existe pas", ErrorLevel::ERROR);
            return;
        }
        if (strpos($realParent, $root) !== 0) {
            Errors::add("Vous n'avez pas le droit d'écrire ici", ErrorLevel::ERROR);
            return;
        }

        $finalPath = $realParent . "/" . $folderName;
        if (Config::getOs() === "linux") {
            $cmd = "mkdir -p " . escapeshellarg($finalPath);
        } else {
            $win = str_replace('/', '\\', $finalPath);
            $cmd = "mkdir \"$win\"";
        }
        Errors::add((string)shell_exec($cmd . " > /dev/null 2>&1 &"), ErrorLevel::INFO);
    }

    public static function runDelete(string $source): void {
        $rootDown = Config::getDownboxPath();
        $rootZim  = Config::getZimPath();
        $real = realpath($source);

        if ($real === false) {
            Errors::add("Le fichier n'existe pas", ErrorLevel::ERROR);
            return;
        }
        if (strpos($real, $rootDown) !== 0 && strpos($real, $rootZim) !== 0) {
            Errors::add("Vous n'avez pas le droit d'effacer ici", ErrorLevel::ERROR);
            return;
        }

        if (Config::getOs() === "linux") {
            $cmd = "rm -rf " . escapeshellarg($real);
        } else {
            $win = str_replace('/', '\\', $real);
            $cmd = is_dir($real) ? "rd /s /q \"$win\"" : "del /f /q \"$win\"";
        }
        Errors::add((string)shell_exec($cmd . " > /dev/null 2>&1 &"), ErrorLevel::INFO);
    }

    public static function runDownload(string $url): void {
        if (!preg_match('/\.zim$/i', $url)) {
            Errors::add("Le lien doit être un fichier .zim", ErrorLevel::ERROR);
            return;
        }
        Errors::add("Lien d'un fichier zim correct", ErrorLevel::INFO);

        $newName = basename(parse_url($url, PHP_URL_PATH));
        Errors::add("Nom du fichier : " . $newName, ErrorLevel::INFO);

        if (!preg_match('/^[a-zA-Z0-9._-]+\.zim$/', $newName)) {
            Errors::add("Nom de fichier ZIM invalide", ErrorLevel::ERROR);
            return;
        }

        $rootZim = Config::getZimPath();
        $realZim = realpath($rootZim);

        if ($realZim === false || !is_dir($realZim)) {
            Errors::add("Erreur : dossier ZIM introuvable", ErrorLevel::ERROR);
            return;
        }

        $finalPath = $realZim . "/" . $newName;

        if (file_exists($finalPath)) {
            Errors::add("Le fichier existe déjà", ErrorLevel::ERROR);
            return;
        }

        if (Config::getOs() === "linux") {
            $cmd = "wget -O " . escapeshellarg($finalPath) . " " . escapeshellarg($url) . " 2>&1 &";
            Errors::add("Log : " . shell_exec($cmd), ErrorLevel::INFO);
            Errors::add("Téléchargement lancé (Linux)", ErrorLevel::INFO);
            return;
        }

        $winPath = str_replace('/', '\\', $finalPath);
        $winUrl  = escapeshellarg($url);
        $cmd = "powershell -Command \"Invoke-WebRequest -Uri $winUrl -OutFile '$winPath'\"";
        shell_exec($cmd . " > NUL 2>&1 &");

        Errors::add("Téléchargement lancé (Windows)", ErrorLevel::INFO);
    }

    public static function runUpdate(string $oldFile, string $url): void {
        if (!preg_match('/\.zim$/i', $url)) {
            Errors::add("Le lien doit être un fichier .zim", ErrorLevel::ERROR);
            return;
        }

        $rootZim = Config::getZimPath();
        $realRoot = realpath($rootZim);

        if ($realRoot === false || !is_dir($realRoot)) {
            Errors::add("Erreur : dossier ZIM introuvable", ErrorLevel::ERROR);
            return;
        }

        $realOld = realpath($oldFile);
        if ($realOld === false || strpos($realOld, $realRoot) !== 0) {
            Errors::add("Accès refusé : fichier hors du dossier ZIM", ErrorLevel::ERROR);
            return;
        }

        $newName = basename(parse_url($url, PHP_URL_PATH));
        if (!preg_match('/^[a-zA-Z0-9._-]+\.zim$/', $newName)) {
            Errors::add("Nom de fichier ZIM invalide", ErrorLevel::ERROR);
            return;
        }

        $newPath = $realRoot . "/" . $newName;
        if (file_exists($newPath)) {
            Errors::add("Le fichier existe déjà", ErrorLevel::ERROR);
            return;
        }

        if (Config::getOs() === "linux") {
            $cmd = "wget -O " . escapeshellarg($newPath) . " " . escapeshellarg($url) . " > /dev/null 2>&1 &";
            shell_exec($cmd);
            shell_exec("rm -f " . escapeshellarg($realOld));

            Errors::add("Mise à jour lancée (Linux)", ErrorLevel::INFO);
            return;
        }

        $winOld = str_replace('/', '\\', $realOld);
        $winNew = str_replace('/', '\\', $newPath);
        $winUrl = escapeshellarg($url);

        $cmd = "powershell -Command \"Invoke-WebRequest -Uri $winUrl -OutFile '$winNew'\"";
        shell_exec($cmd . " > NUL 2>&1 &");

        if (is_dir($realOld)) {
            shell_exec("rd /s /q \"$winOld\"");
        } else {
            shell_exec("del /f /q \"$winOld\"");
        }
        Errors::add("Mise à jour lancée (Windows)", ErrorLevel::INFO);
    }
}

// ==========================================
// 2. TRAITEMENT DES ACTIONS FORMULAIRE (POST)
// ==========================================

$zimPath = Config::getZimPath();

// DELETE
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? '') === "delete") {
    if (!password_verify($_POST['password'] ?? '', Config::getPasswordHash())) {
        Errors::add("Mot de passe incorrect", ErrorLevel::ERROR);
    } else {
        $file = basename($_POST["target_item"] ?? '');
        if ($file !== "" && file_exists($zimPath . "/" . $file)) {
            Utils::runDelete($zimPath . "/" . $file);
        } else {
            Errors::add("Fichier introuvable", ErrorLevel::ERROR);
        }
    }
}

// UPDATE
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? '') === "update") {
    if (!password_verify($_POST['password'] ?? '', Config::getPasswordHash())) {
        Errors::add("Mot de passe incorrect", ErrorLevel::ERROR);
    } else {
        $file = basename($_POST["file"] ?? '');
        $url  = trim($_POST["new_url"] ?? '');

        if (!preg_match('/\.zim$/i', $url)) {
            Errors::add("URL invalide : doit se terminer par .zim", ErrorLevel::ERROR);
        } else if (!file_exists($zimPath . "/" . $file)) {
            Errors::add("Fichier introuvable", ErrorLevel::ERROR);
        } else {
            Utils::runUpdate($zimPath . "/" . $file, $url);
        }
    }
}

// ADD
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? '') === "add") {
    if (!password_verify($_POST["password"] ?? '', Config::getPasswordHash())) {
        Errors::add("Mot de passe incorrect", ErrorLevel::ERROR);
    } else {
        $url = trim($_POST["dir_name"] ?? '');

        if (!preg_match('/\.zim$/i', $url)) {
            Errors::add("URL invalide : doit se terminer par .zim", ErrorLevel::ERROR);
        } else {
            $newName = basename(parse_url($url, PHP_URL_PATH));
            $realRoot = realpath($zimPath);

            if ($realRoot === false || !is_dir($realRoot)) {
                Errors::add("Erreur : dossier ZIM introuvable", ErrorLevel::ERROR);
            } else if (file_exists($realRoot . "/" . $newName)) {
                Errors::add("Erreur : ce fichier existe déjà", ErrorLevel::ERROR);
            } else {
                Utils::runDownload($url);
            }
        }
    }
}

$errorMessage = Errors::get(ErrorLevel::ALL);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZIM Manager</title>
    <style>
        :root {
            --bg: #0d0f14;
            --card: #161b22;
            --accent: #7c4dff;
            --danger: #ff4d4d;
            --text: #c9d1d9;
            --border: #30363d;
        }

        /* Base */
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
            padding: 20px;
            margin: 0;
        }

        .container {
            max-width: 1250px;
            margin: 40px auto;
            padding: 0 15px;
        }

        .glass {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        td, th {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #21262d;
            white-space: nowrap;
        }

        /* Inputs */
        input, select {
            background: #0d1117;
            border: 1px solid var(--border);
            color: white;
            padding: 8px;
            border-radius: 6px;
            outline: none;
            width: 100%;
            box-sizing: border-box;
        }

        input:focus, select:focus {
            border-color: var(--accent);
        }

        /* Buttons */
        .btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            white-space: nowrap;
            transition: opacity 0.2s, background-color 0.2s;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-tmdb {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            margin-left: 4px;
        }

        .btn-upd { 
            background: transparent; 
            border: 1px solid var(--accent); 
            color: var(--accent); 
            border-radius: 6px; 
            margin-left: 4px; 
            font-size: 13px;
        }

        .btn-upd:hover {
            background: var(--accent);
            color: white;
        }

        .btn-del {
            background: transparent;
            color: var(--danger);
            border: 1px solid var(--danger);
            margin-left: 4px;
            font-size: 11px;
        }

        .btn-del:hover {
            background: var(--danger);
            color: white;
        }

        /* Links */
        a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }

        /* Overlay + Modal */
        #overlay, #overlay-create {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            z-index: 90;
            backdrop-filter: blur(5px);
        }

        #update-modal, #create-modal, #tmdb-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%,-50%);
            background: var(--card);
            padding: 25px;
            border: 1px solid var(--accent);
            z-index: 100;
            border-radius: 15px;
            width: 450px;
            max-width: 90%;
        }

        /* TMDB items */
        .tmdb-item {
            padding: 10px;
            border-bottom: 1px solid #333;
            cursor: pointer;
        }

        /* Actions */
        .actions-cell {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 250px;
            flex-wrap: wrap;
        }

        /* Toasts */
        #toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 9999;
        }

        .toast {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #202225;
            border-left: 4px solid #f04747;
            color: #ffffff;
            font-size: 0.85rem;
            min-width: 260px;
            max-width: 340px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
            opacity: 0;
            transform: translateX(20px);
            animation: toast-in 0.25s forwards;
        }

        .toast-icon {
            margin-top: 2px;
            font-size: 1rem;
        }

        .toast-close {
            margin-left: auto;
            cursor: pointer;
            opacity: 0.7;
        }

        .toast-close:hover {
            opacity: 1;
        }

        /* Animations */
        @keyframes toast-in {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes toast-out {
            to {
                opacity: 0;
                transform: translateX(20px);
            }
        }

        /* ----------------------------- */
        /* RESPONSIVE */
        /* ----------------------------- */

        /* TABLETTE */
        @media (max-width: 900px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .actions-cell {
                min-width: unset;
                width: 100%;
                justify-content: flex-start;
            }

            table {
                font-size: 0.9rem;
            }
        }

        /* MOBILE */
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .container {
                margin: 20px auto;
                padding: 0 10px;
            }

            .glass {
                padding: 15px;
            }

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            td, th {
                padding: 8px;
                font-size: 0.85rem;
            }

            .btn {
                width: 100%;
                text-align: center;
                padding: 10px;
            }

            .actions-cell {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }

            #toast-container {
                right: 10px;
                left: 10px;
                bottom: 10px;
            }

            #update-modal, #create-modal, #tmdb-modal {
                width: 95%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 style="margin:0; font-weight: 300;">ZIM<span style="color:var(--accent); font-weight: 800;">MANAGER</span></h1>
                <code style="color:#8b949e;"><?php echo htmlspecialchars($zimPath); ?></code>
            </div>
            <form onsubmit="return openCreatePopup(this)" method="POST" style="display:flex; gap:10px; width: 100%; max-width: 400px;">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="password" class="pass-field">
                <input type="text" name="dir_name" placeholder="Nouveau zim..." required>
                <button type="submit" class="btn" style="width: auto;">CRÉER</button>
            </form>
        </div>

        <div class="glass">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50%;">ÉLÉMENT</th>
                            <th style="width: 50%;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (Utils::getZimFiles() as $item): ?>
                        <tr>
                            <td>
                                📄 <span style="color:#8b949e;"><?php echo htmlspecialchars($item); ?></span>
                            </td>
                            <td class="actions-cell">
                                <button class="btn btn-upd" onclick="openUpdate('<?php echo htmlspecialchars($item, ENT_QUOTES); ?>')">🔄 Update</button>

                                <form method="POST" onsubmit="return confirmDelete('<?php echo addslashes($item); ?>', this)" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="target_item" value="<?php echo htmlspecialchars($item, ENT_QUOTES); ?>">
                                    <input type="hidden" name="password" class="pass-field">
                                    <button type="submit" class="btn btn-del" title="Supprimer">🗑️ SUPPR.</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- POPUP UPDATE -->
        <div id="overlay" onclick="closeModal()"></div>

        <div id="update-modal">
            <h3 style="margin:0 0 15px; color:var(--accent);">Mettre à jour</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="file" id="upd_file">

                <label style="display:block; margin-top:10px; font-size:0.9rem;">Nouvelle URL (.zim)</label>
                <input type="text" name="new_url" id="upd_url" style="margin-top:5px;" required>

                <label style="display:block; margin-top:10px; font-size:0.9rem;">Mot de passe</label>
                <input type="password" name="password" style="margin-top:5px;" required>

                <button class="btn" style="width:100%; margin-top:15px;">Mettre à jour</button>
            </form>
            <button class="btn" onclick="closeModal()" style="width:100%; margin-top:10px; background:#21262d; border:1px solid var(--border);">Annuler</button>
        </div>

        <!-- POPUP CREATE -->
        <div id="overlay-create" onclick="closeCreatePopup()"></div>

        <div id="create-modal">
            <h3 style="margin:0 0 15px; color:var(--accent);">Mot de passe requis</h3>
            <label style="display:block; font-size:0.9rem;">Mot de passe</label>
            <input type="password" id="create-pass" style="margin-top:8px;">
            <button class="btn" onclick="validateCreate()" style="width:100%; margin-top:15px;">Valider</button>
            <button class="btn" onclick="closeCreatePopup()" style="width:100%; margin-top:10px; background:#21262d; border:1px solid var(--border);">Annuler</button>
        </div>

        <div id="toast-container"></div>
    </div>

    <script>
    function confirmDelete(name, form) {
        const pass = prompt(`⚠️ SUPPRESSION DÉFINITIVE\n\nÉlément : "${name}"\n\nMot de passe requis :`);
        if (!pass) return false;
        form.querySelector('.pass-field').value = pass;
        return true;
    }

    function openUpdate(file) {
        document.getElementById("upd_file").value = file;
        document.getElementById("upd_url").value = "";
        document.getElementById("overlay").style.display = "block";
        document.getElementById("update-modal").style.display = "block";
    }

    function closeModal() {
        document.getElementById("overlay").style.display = "none";
        document.getElementById("update-modal").style.display = "none";
    }

    let createForm = null;

    function openCreatePopup(form) {
        createForm = form;
        document.getElementById("overlay-create").style.display = "block";
        document.getElementById("create-modal").style.display = "block";
        return false;
    }

    function closeCreatePopup() {
        document.getElementById("overlay-create").style.display = "none";
        document.getElementById("create-modal").style.display = "none";
    }

    function validateCreate() {
        const pass = document.getElementById("create-pass").value.trim();
        if (!pass) return;

        createForm.querySelector(".pass-field").value = pass;
        closeCreatePopup();
        createForm.submit();
    }

    function showToast(message) {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = 'toast';

        toast.innerHTML = `
            <div class="toast-icon">⚠️</div>
            <div>${message}</div>
            <div class="toast-close" onclick="closeToast(this.parentElement)">✖</div>
        `;

        container.appendChild(toast);
        setTimeout(() => closeToast(toast), 5000);
    }

    function closeToast(toast) {
        toast.style.animation = 'toast-out 0.25s forwards';
        setTimeout(() => toast.remove(), 250);
    }

    <?php if (!empty($errorMessage) && is_array($errorMessage)): ?>
        <?php foreach ($errorMessage as $err): ?>
            showToast("<?= htmlspecialchars($err->content, ENT_QUOTES, 'UTF-8') ?>");
        <?php endforeach; ?>
    <?php endif; ?>
    </script>
</body>
</html>
