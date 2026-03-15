<div align="center">

# SynoCP – App Overview

**A lightweight, zero-dependency PHP dashboard that auto-discovers all your self-hosted applications.**

Every app registers itself with a single `app.json` file. The dashboard reads those files at runtime and renders a clean, searchable, categorised card grid — no database, no configuration file to maintain manually.

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-22c55e?style=flat-square)](LICENSE)
[![Release](https://img.shields.io/github/v/release/SynoCP/app?style=flat-square&color=3b82f6)](https://github.com/SynoCP/app/releases)

</div>

---

## ✨ Features

| Feature | Description |
|---|---|
| 🔍 **Auto-discovery** | Scans subdirectories for `app.json` files — no manual list to maintain |
| 🗂 **Categories** | Apps are automatically grouped by the `category` field |
| ⭐ **Favorites** | Apps marked `"favorite": true` appear in a pinned section at the top |
| 🕘 **Recent apps** | The last 6 visited apps are remembered per-browser |
| 🔎 **Live search** | Filter all visible apps instantly by name or description |
| 🌓 **Dark / Light theme** | One-click toggle; preference is persisted across sessions |
| 🔒 **Login indicator** | Apps with `"login_required": true` are labelled with a 🔒 icon |
| 🎨 **Custom card colors** | Each app can define a hex or `rgb()` background color |
| 🖼 **Custom icons** | Per-app icon images with automatic SVG fallback |
| ⚡ **Optional cache** | App list can be cached to a JSON file to reduce filesystem reads |
| 📱 **Responsive layout** | Fluid grid adapts down to 2 columns on mobile |
| ⚙️ **Settings modal** | In-browser UI to configure the site title, subtitle, and navigation links |
| 🔗 **Navigation bar** | Configurable quick-links rendered in the sticky top bar |
| 🔐 **Security headers** | CSP with per-request nonce, `X-Frame-Options`, `Referrer-Policy`, and more |

---

## 📸 Screenshots

> _Screenshots will be added in a future release. To preview the dashboard, follow the [Quick Start](#-quick-start) guide below._

---

## 🚀 Quick Start

```bash
# 1. Download the latest release
#    (or clone the repository)

# 2. Place the files in your web root
#    e.g. /var/www/html/

# 3. Start the built-in PHP development server
php -S localhost:8080

# 4. Open the dashboard
#    http://localhost:8080
```

The dashboard is immediately usable — apps are discovered automatically. No database setup and no additional packages are required.

---

## 📦 Installation

### Requirements

- **PHP 7.4** or newer (PHP 8.x fully supported)
- A web server pointing to the project root — see the server configs below
- **Write permission** on `assets/` for the web-server user (needed for the Settings modal to persist changes)

### Option A — Apache

1. Copy all files to your virtual-host document root (e.g. `/var/www/html/`).
2. Enable `mod_rewrite` if you intend to use pretty URLs (not required for basic usage).
3. Ensure the web-server user (`www-data`) can write to `assets/`:

```bash
chown -R www-data:www-data /var/www/html/assets
```

Example minimal virtual host:

```apacheconf
<VirtualHost *:80>
    ServerName apps.example.com
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Option B — Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name apps.example.com;
    root /var/www/html;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

Give the `www-data` user write access to `assets/`:

```bash
chown -R www-data:www-data /var/www/html/assets
```

### Option C — Caddy

```caddyfile
apps.example.com {
    root * /var/www/html
    php_fastcgi unix//run/php/php8.2-fpm.sock
    file_server
}
```

### Option D — Docker (quick demo)

```bash
docker run --rm -p 8080:80 \
  -v "$PWD":/var/www/html \
  php:8.2-apache
```

> The web server user inside the container must be able to write to `assets/` for the Settings modal to work.

---

## 🗂 Directory Structure

```
/                          ← web root (where index.php lives)
├── index.php              # Dashboard entry point – renders the HTML page
├── src/
│   └── functions.php      # Core PHP logic (discovery, config, sanitisation)
├── api/
│   └── config.php         # REST endpoint – GET/POST for assets/config.json
├── assets/
│   ├── config.json        # Site configuration (title, subtitle, nav links)
│   ├── css/
│   │   └── style.css      # All styles (dark/light theme, grid, cards)
│   ├── js/
│   │   └── app.js         # All client-side logic (search, theme, recent, settings)
│   └── img/
│       └── app-icon-fallback.svg   # Fallback icon when an app provides none
├── test-app/              # Example app – demonstrates the expected layout
│   ├── app.json           # App metadata
│   └── health.php         # Minimal health-check endpoint → {"status":"ok"}
└── LICENSE                # MIT
```

Each of your self-hosted applications lives in its own subdirectory **alongside** `index.php`:

```
/                          ← web root
├── index.php
├── assets/
├── my-app/
│   ├── app.json           ← required
│   └── icon.png           ← optional (used as the default icon)
├── another-app/
│   └── app.json
└── ...
```

---

## ⚙️ Configuration

### Site configuration — `assets/config.json`

The site title, subtitle, and navigation links are stored in `assets/config.json`.  
They can be changed in two ways:

**1. Settings modal (in the browser)**
Click the **⚙️ Einstellungen** (Settings) button in the footer. Changes are saved instantly via `api/config.php` — no file editing required.

**2. Direct file edit**
Open `assets/config.json` with any text editor:

```json
{
  "site_title":    "App Server",
  "site_subtitle": "Your self-hosted application overview",
  "nav": [
    { "label": "HOME", "url": "https://example.com" },
    { "label": "WIKI", "url": "https://wiki.example.com" }
  ]
}
```

| Key | Type | Default | Description |
|---|---|---|---|
| `site_title` | string | `"App Server"` | Title shown in the browser tab and the page header banner |
| `site_subtitle` | string | `""` | Sub-headline shown below the title (hidden when empty) |
| `nav` | array | `[]` | Array of `{ "label": string, "url": string }` objects rendered as sticky navigation links |

If `assets/config.json` is missing or contains invalid JSON, safe built-in defaults are used automatically.

### Discovery and cache settings — `index.php`

Two variables at the top of `index.php` control how apps are discovered:

| Variable | Default | Description |
|---|---|---|
| `$cache_mode` | `false` | Set to `true` to cache the discovered app list in `apps_cache.json` |
| `$apps_parent_dir` | `"/"` | Subdirectory (relative to `index.php`) to scan for `app.json` files |

**Cache behaviour:**
When `$cache_mode` is `true`, the app list is written to `apps_cache.json` in the project root and re-used for 60 seconds. To force an immediate rebuild without waiting for the cache to expire, open the dashboard with the `?refresh` query parameter:

```
https://apps.example.com/?refresh
```

**Scanning a sub-path:**  
To limit discovery to apps inside a specific subdirectory (e.g. `/apps/`), update `$apps_parent_dir`:

```php
$apps_parent_dir = "/apps/";
```

---

## ➕ Adding an App

Create a directory for the app next to `index.php` and place an `app.json` file inside it. The dashboard will pick it up on the next page load — no restart or manual registration needed.

```
my-app/
├── app.json     ← required
└── icon.png     ← optional
```

### `app.json` reference

```json
{
  "name":            "My App",
  "description":     "Short description shown on the card",
  "url":             "/my-app/",
  "icon":            "/my-app/icon.png",
  "category":        "Infrastructure",
  "order":           10,
  "favorite":        false,
  "login_required":  false,
  "color":           "#0ea5e9",
  "status_endpoint": "/my-app/health.php",
  "tags":            ["server", "example"]
}
```

| Field | Required | Type | Default | Description |
|---|---|---|---|---|
| `name` | No | string | Directory name | Display name shown on the card |
| `description` | No | string | `""` | Short text shown below the name |
| `url` | No | string | `/<folder>/` | Link target when the card is clicked |
| `icon` | No | string | `/<folder>/icon.png` | Path to the icon image; automatically falls back to the built-in SVG if the file does not exist |
| `category` | No | string | `"Other"` | Groups cards under a shared section heading |
| `order` | No | number | `100` | Sort position within a category — lower numbers appear first |
| `favorite` | No | bool | `false` | `true` pins the app to the **⭐ Favorites** section at the top |
| `login_required` | No | bool | `false` | `true` adds a 🔒 icon next to the app name |
| `color` | No | string | `"#1e293b"` | CSS background color of the card — accepts hex (`#rgb`, `#rrggbb`) and `rgb()`/`rgba()` |
| `status_endpoint` | No | string | – | URL for a health-check endpoint (reserved for future use) |
| `tags` | No | array | – | Array of tag strings (reserved for future filtering) |

### Icon guidelines

- **Recommended size:** 64 × 64 px (or any square size — the CSS clips to a circle)
- **Supported formats:** PNG, SVG, JPEG, WebP — any format the browser can display
- **Fallback:** if `icon` is omitted or the file does not exist, the built-in `app-icon-fallback.svg` is used automatically
- **Path traversal protection:** icon paths are validated server-side; paths that escape the web root are silently replaced with the fallback

### Card color

The `color` field sets the card background. Any hex color or `rgb()`/`rgba()` value is accepted:

```json
"color": "#0ea5e9"
"color": "#1e293b"
"color": "rgb(30, 41, 59)"
"color": "rgba(14, 165, 233, 0.85)"
```

Invalid values are rejected and replaced with the default `#1e293b`.

---

## 🌓 Theme

The dashboard ships with a **dark theme** (default) and a **light theme**.

- Click the **🌓** button in the top navigation bar to toggle between themes.
- The preference is saved in `localStorage` under the key `theme` and restored on every subsequent visit.
- The light theme is activated by adding the `.light` class to the `<body>` element; CSS custom properties handle the rest.

---

## 🔗 Settings Modal

The **⚙️ Einstellungen** (Settings) button in the footer opens an in-browser settings panel where you can:

- Change the **site title** and **subtitle** without editing any files.
- Add, edit, or remove **navigation links** (label + URL pairs).
- Save all changes instantly — the page reflects the new configuration after the next reload.

Changes are sent to `api/config.php` via `POST` and written to `assets/config.json`. The web-server user must have **write permission** on the `assets/` directory for this to work. If saving fails, an error message is displayed inside the modal.

---

## 📡 API Reference

### `GET /api/config.php`

Returns the current site configuration as a JSON object.

**Response** `200 OK`:
```json
{
  "site_title":    "App Server",
  "site_subtitle": "Your self-hosted application overview",
  "nav": [
    { "label": "HOME", "url": "https://example.com" }
  ]
}
```

### `POST /api/config.php`

Saves a new configuration. The request body must be a JSON object with the same structure as the GET response.

**Request body:**
```json
{
  "site_title":    "My Dashboard",
  "site_subtitle": "All apps at a glance",
  "nav": [
    { "label": "HOME", "url": "https://example.com" },
    { "label": "WIKI", "url": "https://wiki.example.com" }
  ]
}
```

**Response** `200 OK`:
```json
{ "success": true }
```

**Error responses:**

| Status | Body | Cause |
|---|---|---|
| `400 Bad Request` | `{ "error": "Invalid JSON body" }` | Request body is not valid JSON |
| `500 Internal Server Error` | `{ "error": "Failed to write config file" }` | Web server lacks write permission on `assets/` |
| `405 Method Not Allowed` | `{ "error": "Method not allowed" }` | HTTP method other than GET or POST was used |

---

## 🔐 Security

SynoCP App Overview applies several layers of protection:

| Protection | Details |
|---|---|
| **Content Security Policy** | A per-request nonce is generated for the single inline `<script>` block. All other inline scripts are blocked. |
| **X-Frame-Options: DENY** | Prevents the dashboard from being embedded in iframes (clickjacking protection). |
| **X-Content-Type-Options: nosniff** | Prevents MIME-type sniffing by the browser. |
| **Referrer-Policy** | Set to `strict-origin-when-cross-origin` to limit referrer leakage. |
| **URL sanitisation** | All URLs from `app.json` and `config.json` are validated — `javascript:`, `data:`, and other dangerous schemes are replaced with `#`. |
| **Color sanitisation** | CSS color values from `app.json` are validated against a strict allowlist of formats before being rendered. |
| **Icon path traversal guard** | Icon paths are resolved with `realpath()` and checked to remain inside the web root. |
| **Output escaping** | All user-controlled values echoed into HTML are passed through `htmlspecialchars()`. |

---

## 🩺 Troubleshooting

### The dashboard is empty — no apps appear

1. Make sure each app directory contains an `app.json` file.
2. Confirm the `$apps_parent_dir` variable in `index.php` points to the correct subdirectory (default: `"/"`).
3. Check that `app.json` is valid JSON — a syntax error causes the file to be silently skipped. Use a JSON validator to check it.
4. If caching is enabled (`$cache_mode = true`), open `?refresh` to force a rebuild.

### The Settings modal shows an error when saving

The web-server process must have **write permission** on the `assets/` directory:

```bash
# Apache / Nginx (typical Linux setup)
chown www-data:www-data /var/www/html/assets
chmod 755 /var/www/html/assets
```

### My app icon is not showing

- Verify the `icon` path in `app.json` is correct and relative to the web root (e.g. `/my-app/icon.png`).
- The path must stay **inside** the web root — paths containing `..` that escape the root are rejected.
- Supported formats: PNG, SVG, JPEG, WebP.
- If the file does not exist, the fallback SVG is used automatically.

### The card background color is not applied

Only hex colors (`#rgb`, `#rrggbb`) and `rgb()`/`rgba()` values are accepted. Named CSS colors (e.g. `"red"`) and `hsl()` are not supported and will be silently replaced with the default `#1e293b`.

### Navigation links are not saved

Ensure the `url` field in each nav item uses `http://` or `https://`. Relative paths (`/path`) are also accepted.

---

## 🗺 Roadmap

The following features are planned or reserved for future releases:

- **Health-check badges** — Live status indicators powered by the `status_endpoint` field
- **Tag-based filtering** — Filter cards by the `tags` array
- **Multi-language UI** — Full internationalisation of the dashboard interface
- **Docker Compose example** — Ready-to-use compose file

---

## 🤝 Contributing

Contributions, bug reports, and feature requests are welcome!

1. Open an [Issue](https://github.com/SynoCP/app/issues) to discuss what you would like to change before submitting a pull request.
2. Fork the repository and create a feature branch from `main`.
3. Make your changes and submit a pull request against `main`.

Please keep pull requests focused on a single change to make reviews as straightforward as possible.

---

## 📄 License

MIT — see [LICENSE](LICENSE) for the full text.

---

<div align="center">

Made with ❤️ by [RINws / SynoCP](https://github.com/SynoCP)

</div>
