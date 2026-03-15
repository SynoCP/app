<?php

/*
 *  SynoCP / App Übersicht
 *
 *  Auto-discovers self-hosted applications by scanning subdirectories for
 *  app.json files and rendering them as a responsive, searchable card grid.
 *
 *  (C) RINws / SynoCP
 */

require_once __DIR__ . "/src/functions.php";

// ── Security headers ──────────────────────────────────────────────────────────
// Generate a per-request nonce that allows our single inline <script> block
// while blocking all other inline scripts (CSP).
$csp_nonce = base64_encode(random_bytes(16));

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . $csp_nonce . "'; style-src 'self'; img-src 'self'; font-src 'self'; media-src 'none'; connect-src 'self'; object-src 'none'; worker-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; manifest-src 'none'");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ── Configuration ────────────────────────────────────────────────────────────
// Set $cache_mode to true to cache the discovered app list in apps_cache.json.
// $apps_parent_dir is the subdirectory (relative to this file) that is scanned
// for app.json files.  Use "/" to scan all direct subdirectories.
    $cache_mode = false;
    $apps_parent_dir = "/"; // default /

// ── Site configuration (title, subtitle, navigation) ─────────────────────────
    $config       = load_config(__DIR__ . "/assets/config.json");
    $page_title   = $config["site_title"];
    $site_subtitle = $config["site_subtitle"];
    $nav_items    = $config["nav"];


// ── Optional file-based cache ─────────────────────────────────────────────────
// When $cache_mode is true, the app list is written to apps_cache.json and
// re-used until it expires ($cache_time seconds).  Append ?refresh to the URL
// to force an immediate rebuild.
    if($cache_mode == true){
        $cache_file = __DIR__ . "/apps_cache.json";
        $cache_time = 60; // seconds

        if (!isset($_GET["refresh"]) && file_exists($cache_file)) {

            if (time() - filemtime($cache_file) < $cache_time) {

                $apps = json_decode(file_get_contents($cache_file), true);

            }
        }
    }



if (!isset($apps)) {

    // ── App discovery ─────────────────────────────────────────────────────────
    // Delegate to discover_apps() from src/functions.php.
    $apps = discover_apps(__DIR__, $apps_parent_dir);

    // Persist to cache if caching is enabled.
    if($cache_mode == true){
        file_put_contents($cache_file,json_encode($apps));
    }
}

/*
 * ── Build category index ──────────────────────────────────────────────────────
 * Group apps by their "category" field so the template can render one section
 * per category.  Also build the $favorites subset for the pinned section.
 */

$categories = build_categories($apps);

$favorites = get_favorites($apps);

?>

<!DOCTYPE html>
<html lang="de">
<head>

<meta charset="UTF-8">
<title>SynoCP | <?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="icon" href="/assets/img/app-icon-fallback.svg" type="image/svg+xml">
<link rel='stylesheet' href='./assets/css/style.css'>

</head>

<body>

<script nonce="<?= htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8') ?>">try{if(localStorage.getItem("theme")==="light")document.body.classList.add("light");}catch(e){}</script>

<nav class="pre-header">

  <div class="nav-links">
    <?php foreach($nav_items as $item): ?>
    <a class="nav-link" href="<?= htmlspecialchars($item['url']) ?>" target="_blank" rel="noopener noreferrer">
      <?= htmlspecialchars($item['label']) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="nav-search">
    <input id="search" placeholder="🔍 Search apps…" autocomplete="off">
  </div>

  <div class="nav-actions">
    <button class="theme-toggle" aria-label="Toggle theme">🌓</button>
  </div>

</nav>

<header class="header-banner">

  <h1><?= htmlspecialchars($page_title) ?></h1>
  <?php if($site_subtitle): ?>
  <p class="site-subtitle"><?= htmlspecialchars($site_subtitle) ?></p>
  <?php endif; ?>

</header>


<div class="recent">

<h2>🕘 Recent</h2>

<div class="grid" id="recent"></div>

</div>


<?php if(count($favorites)): ?>

<div class="section">

<h2>⭐ Favorites</h2>

<div class="grid">

<?php foreach($favorites as $app): ?>

<a class="app"
data-name="<?= htmlspecialchars(strtolower($app["name"]), ENT_QUOTES, 'UTF-8') ?>"
data-label="<?= htmlspecialchars($app["name"], ENT_QUOTES, 'UTF-8') ?>"
data-url="<?= htmlspecialchars($app["url"], ENT_QUOTES, 'UTF-8') ?>"
data-icon="<?= htmlspecialchars($app["icon"], ENT_QUOTES, 'UTF-8') ?>"
data-color="<?= htmlspecialchars($app["color"], ENT_QUOTES, 'UTF-8') ?>"
href="<?= htmlspecialchars($app["url"], ENT_QUOTES, 'UTF-8') ?>">

<img class="icon" src="<?= htmlspecialchars($app["icon"], ENT_QUOTES, 'UTF-8') ?>" alt="">

<h3><?= htmlspecialchars($app["name"], ENT_QUOTES, 'UTF-8') ?></h3>

<p><?= htmlspecialchars($app["description"], ENT_QUOTES, 'UTF-8') ?></p>

</a>

<?php endforeach; ?>

</div>
</div>

<?php endif; ?>


<?php foreach($categories as $category=>$apps): ?>

<div class="section">

<h2><?= htmlspecialchars($category) ?></h2>

<div class="grid">

<?php foreach($apps as $app): ?>

<a class="app"
data-name="<?= htmlspecialchars(strtolower($app["name"]), ENT_QUOTES, 'UTF-8') ?>"
data-label="<?= htmlspecialchars($app["name"], ENT_QUOTES, 'UTF-8') ?>"
data-url="<?= htmlspecialchars($app["url"], ENT_QUOTES, 'UTF-8') ?>"
data-icon="<?= htmlspecialchars($app["icon"], ENT_QUOTES, 'UTF-8') ?>"
data-color="<?= htmlspecialchars($app["color"], ENT_QUOTES, 'UTF-8') ?>"
href="<?= htmlspecialchars($app["url"], ENT_QUOTES, 'UTF-8') ?>">

<img class="icon" src="<?= htmlspecialchars($app["icon"], ENT_QUOTES, 'UTF-8') ?>" alt="">

<h3>
<?= htmlspecialchars($app["name"], ENT_QUOTES, 'UTF-8') ?>
<?php if($app["login_required"]): ?> 🔒 <?php endif; ?>
</h3>

<p><?= htmlspecialchars($app["description"], ENT_QUOTES, 'UTF-8') ?></p>

</a>

<?php endforeach; ?>

</div>
</div>

<?php endforeach; ?>


<!-- ── Pre-footer: How to add a new app ──────────────────────────────────── -->

<?php if(count($apps) === 0): ?>

<section class="pre-footer">
  <div class="pre-footer-inner">

    <h2 class="pre-footer-title">➕ Neue App hinzufügen</h2>
    <p class="pre-footer-intro">
      Jede App registriert sich selbst, indem sie eine <code>app.json</code>-Datei in ihr
      Unterverzeichnis legt. Beim nächsten Seitenaufruf erscheint die App automatisch im Dashboard –
      kein manuelles Eintragen notwendig.
    </p>

    <ol class="pre-footer-steps">
      <li>
        <strong>Verzeichnis anlegen</strong> –
        Erstelle ein Unterverzeichnis unterhalb des Web-Roots, z.&nbsp;B.
        <code>/var/www/html/meine-app/</code>.
      </li>
      <li>
        <strong><code>app.json</code> erstellen</strong> –
        Lege die Datei <code>meine-app/app.json</code> mit den gewünschten Metadaten an
        (alle Felder außer <code>name</code> sind optional).
      </li>
      <li>
        <strong>Icon hinzufügen (optional)</strong> –
        Platziere eine <code>icon.png</code> (32 × 32 px) im selben Verzeichnis.
        Fehlt das Icon, wird ein generisches Platzhalter-SVG verwendet.
      </li>
      <li>
        <strong>Fertig!</strong> –
        Lade das Dashboard neu – deine App erscheint sofort in der richtigen Kategorie.
      </li>
    </ol>

    <div class="pre-footer-example">
      <p class="pre-footer-example-label">Beispiel <code>app.json</code>:</p>
<pre><code>{
  "name":           "Meine App",
  "description":    "Kurze Beschreibung der App",
  "url":            "/meine-app/",
  "icon":           "/meine-app/icon.png",
  "category":       "Tools",
  "order":          10,
  "favorite":       false,
  "login_required": false,
  "color":          "#1e293b"
}</code></pre>
    </div>

  </div>
</section>

<?php else: ?>

<section class="pre-footer pre-footer--compact">
  <div class="pre-footer-inner">
    <span class="pre-footer-compact-hint">
      ➕ App hinzufügen: <code>app.json</code> ins App-Verzeichnis legen – beim nächsten Reload erscheint sie hier.
    </span>
  </div>
</section>

<?php endif; ?>


<!-- ── Footer ────────────────────────────────────────────────────────────── -->

<footer class="footer">
  <div class="footer-inner">

    <div class="footer-brand">
      <span class="footer-logo">SynoCP</span>
      <span class="footer-tagline">Self-hosted App Overview</span>
    </div>

    <div class="footer-links">
      <a class="footer-link" href="https://github.com/SynoCP/app-overview-dev" target="_blank" rel="noopener noreferrer">
        GitHub
      </a>
      <a class="footer-link" href="https://github.com/SynoCP/app-overview-dev/issues" target="_blank" rel="noopener noreferrer">
        Issues
      </a>
      <a class="footer-link" href="https://github.com/SynoCP/app-overview-dev/blob/main/README.md" target="_blank" rel="noopener noreferrer">
        Docs
      </a>
    </div>

    <div class="footer-actions">
      <a class="kofi-btn" href="https://ko-fi.com/rinws" target="_blank" rel="noopener noreferrer" aria-label="Unterstütze uns auf Ko-fi">
        <svg class="kofi-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M23.881 8.948c-.773-4.085-4.859-4.593-4.859-4.593H.723c-.604 0-.679.798-.679.798s-.082 7.324-.022 11.822c.164 2.424 2.586 2.672 2.586 2.672s8.267-.023 11.966-.049c2.438-.426 2.683-2.566 2.658-3.734 4.352.24 7.422-2.831 6.649-6.916zm-11.062 3.511c-1.246 1.453-4.011 3.976-4.011 3.976s-.121.119-.31.023c-.076-.057-.108-.09-.108-.09-.443-.441-3.368-3.049-4.034-3.954-.709-.965-1.041-2.7-.091-3.71.951-1.01 3.005-1.086 4.363.407 0 0 1.565-1.782 3.468-.963 1.904.82 1.832 2.011 1.832 2.011s2.12-.44 3.099 1.64c.247.529.242 1.143.011 1.692-.33.745-1.013 1.418-1.013 1.418l-3.206-2.45zm9.864-3.51c-.547.048-2.509.336-3.982-1.21.518-1.11.379-2.31.379-2.31s-.227.002.277-.004c.504-.006.834-.025 1.42.183 1.156.415 2.248 1.563 1.906 3.341z"/>
        </svg>
        Support us
      </a>
      <button class="settings-btn" id="open-settings" aria-label="Einstellungen öffnen">
        ⚙️ Einstellungen
      </button>
    </div>

    <div class="footer-copy">
      &copy; <?= date("Y") ?> RINws / SynoCP &mdash; MIT License
    </div>

  </div>
</footer>


<!-- ── Settings Modal ────────────────────────────────────────────────────── -->

<div class="modal-overlay" id="settings-modal" role="dialog" aria-modal="true" aria-labelledby="settings-modal-title" hidden>
  <div class="modal">

    <div class="modal-header">
      <h3 class="modal-title" id="settings-modal-title">⚙️ Einstellungen</h3>
      <button class="modal-close" aria-label="Einstellungen schließen">✕</button>
    </div>

    <div class="modal-body">

      <div class="form-group">
        <label class="form-label" for="cfg-title">Site-Titel</label>
        <input class="form-input" type="text" id="cfg-title" name="site_title" placeholder="App Server" autocomplete="off">
      </div>

      <div class="form-group">
        <label class="form-label" for="cfg-subtitle">Untertitel</label>
        <input class="form-input" type="text" id="cfg-subtitle" name="site_subtitle" placeholder="Your self-hosted application overview" autocomplete="off">
      </div>

      <div class="form-group">
        <label class="form-label">Navigation</label>
        <div id="nav-items-list" class="nav-items-list"></div>
        <button class="btn-add-nav" type="button">+ Link hinzufügen</button>
      </div>

      <div class="settings-feedback" id="settings-feedback" hidden></div>

    </div>

    <div class="modal-footer">
      <button class="btn-secondary" type="button">Abbrechen</button>
      <button class="btn-primary" type="button">Speichern</button>
    </div>

  </div>
</div>


<script src="./assets/js/app.js" defer></script>

</body>
</html>
