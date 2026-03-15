<?php

/*
 *  SynoCP / App Übersicht – Core Functions
 *
 *  Pure, testable functions extracted from index.php.
 *  All file-system paths are passed in as parameters so that tests can
 *  substitute a temporary directory without touching the real web root.
 */

/**
 * Discovers and normalises all app.json files found directly beneath
 * $base_dir . $apps_parent_dir.
 *
 * @param  string $base_dir        Absolute path to the project root (no trailing slash).
 * @param  string $apps_parent_dir Relative sub-path to scan, e.g. "/" or "/apps/".
 * @return array<int, array>       Sorted array of normalised app records.
 */
function discover_apps(string $base_dir, string $apps_parent_dir): array
{
    $apps = [];

    $base_abs = realpath($base_dir);

    foreach (glob($base_dir . $apps_parent_dir . "*/app.json") as $file) {

        $config = json_decode(file_get_contents($file), true);
        if (!$config) {
            continue;
        }

        $folder = basename(dirname($file));

        // Resolve the icon path and verify it stays inside the project root to
        // guard against path-traversal attacks in app.json ("icon": "../../etc/passwd").
        $icon_raw       = $config["icon"] ?? "/$folder/icon.png";
        $icon_candidate = $base_dir . "/" . ltrim((string) $icon_raw, "/");
        $icon_abs       = realpath($icon_candidate);

        $icon_in_webroot = (
            $icon_abs !== false
            && $base_abs !== false
            && substr($icon_abs, 0, strlen($base_abs) + 1) === $base_abs . DIRECTORY_SEPARATOR
        );

        if ($icon_in_webroot) {
            $icon = "/" . ltrim((string) $icon_raw, "/");
        } else {
            $icon = "/assets/img/app-icon-fallback.svg";
        }

        $apps[] = [
            "name"           => $config["name"]           ?? $folder,
            "description"    => $config["description"]    ?? "",
            "url"            => sanitize_url($config["url"] ?? "/$folder/"),
            "icon"           => $icon,
            "category"       => $config["category"]       ?? "Other",
            "order"          => $config["order"]          ?? 100,
            "favorite"       => $config["favorite"]       ?? false,
            "login_required" => $config["login_required"] ?? false,
            "color"          => sanitize_color($config["color"] ?? "#1e293b"),
        ];
    }

    usort($apps, fn($a, $b) => $a["order"] <=> $b["order"]);

    return $apps;
}

/**
 * Groups an array of normalised app records by their "category" field.
 *
 * @param  array<int, array> $apps
 * @return array<string, array<int, array>>
 */
function build_categories(array $apps): array
{
    $categories = [];

    foreach ($apps as $app) {
        $cat = $app["category"];
        if (!isset($categories[$cat])) {
            $categories[$cat] = [];
        }
        $categories[$cat][] = $app;
    }

    return $categories;
}

/**
 * Returns a re-indexed array containing only the apps that are marked as
 * favorites (i.e. $app["favorite"] === true).
 *
 * @param  array<int, array> $apps
 * @return array<int, array>
 */
function get_favorites(array $apps): array
{
    return array_values(array_filter($apps, fn($a) => $a["favorite"] === true));
}

/**
 * Saves the dashboard configuration to a JSON file.
 *
 * Validates and sanitises the given $data array before writing so that
 * the resulting file always contains a well-formed configuration object.
 *
 * @param  string              $config_file Absolute path to the JSON config file.
 * @param  array<string, mixed> $data        Raw input data to persist.
 * @return bool                              True on success, false on failure.
 */
function save_config(string $config_file, array $data): bool
{
    $config = [
        "site_title"    => isset($data["site_title"])    && is_string($data["site_title"])    ? trim($data["site_title"])    : "App Server",
        "site_subtitle" => isset($data["site_subtitle"]) && is_string($data["site_subtitle"]) ? trim($data["site_subtitle"]) : "",
        "nav"           => [],
    ];

    if (isset($data["nav"]) && is_array($data["nav"])) {
        foreach ($data["nav"] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = isset($item["label"]) && is_string($item["label"]) ? trim($item["label"]) : "";
            $url   = isset($item["url"])   && is_string($item["url"])   ? sanitize_url($item["url"]) : "#";
            if ($label === "") {
                continue;
            }
            $config["nav"][] = ["label" => $label, "url" => $url];
        }
    }

    $dir = dirname($config_file);
    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }

    return file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false;
}

/**
 * Loads and normalises the dashboard configuration from a JSON file.
 *
 * Returns an array with the following keys:
 *   - site_title    (string)  Display title shown in the header banner.
 *   - site_subtitle (string)  Sub-headline shown below the title.
 *   - nav           (array)   Navigation items, each with "label" and "url".
 *
 * If the file is missing or contains invalid JSON, safe defaults are returned.
 *
 * @param  string $config_file Absolute path to the JSON configuration file.
 * @return array{site_title: string, site_subtitle: string, nav: array<int, array{label: string, url: string}>}
 */
function load_config(string $config_file): array
{
    $defaults = [
        "site_title"    => "App Server",
        "site_subtitle" => "",
        "nav"           => [],
    ];

    if (!file_exists($config_file)) {
        return $defaults;
    }

    $raw = file_get_contents($config_file);
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        return $defaults;
    }

    $nav = [];
    if (isset($data["nav"]) && is_array($data["nav"])) {
        foreach ($data["nav"] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = isset($item["label"]) && is_string($item["label"]) ? trim($item["label"]) : "";
            $url   = isset($item["url"])   && is_string($item["url"])   ? sanitize_url($item["url"]) : "#";
            if ($label === "") {
                continue;
            }
            $nav[] = ["label" => $label, "url" => $url];
        }
    }

    return [
        "site_title"    => isset($data["site_title"])    && is_string($data["site_title"])    ? $data["site_title"]    : $defaults["site_title"],
        "site_subtitle" => isset($data["site_subtitle"]) && is_string($data["site_subtitle"]) ? $data["site_subtitle"] : $defaults["site_subtitle"],
        "nav"           => $nav,
    ];
}

/**
 * Validates that a URL uses an allowed scheme (http or https) or is a
 * relative path.  Returns '#' when the URL is empty or uses a disallowed
 * scheme (e.g. javascript:, data:, vbscript:) to prevent XSS via URL
 * injection in href / src attributes.
 *
 * @param  string $url Raw URL from an external source (app.json, config.json).
 * @return string      Sanitised URL safe for use in HTML href/src attributes.
 */
function sanitize_url(string $url): string
{
    $url = trim($url);

    if ($url === "") {
        return "#";
    }

    // Allow relative URLs that start with / (same-origin paths).
    // Explicitly reject protocol-relative URLs (//host) – they are absolute
    // URLs that can point to a different origin despite starting with "/".
    if ($url[0] === "/" && substr($url, 0, 2) !== "//") {
        return $url;
    }

    // Allow explicit same-directory relative paths.
    if (substr($url, 0, 2) === "./") {
        return $url;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    if (in_array($scheme, ["http", "https"], true)) {
        return $url;
    }

    // Reject javascript:, data:, vbscript:, and any other scheme.
    return "#";
}

/**
 * Validates a CSS color value and returns the default card color when the
 * given value does not match an accepted format.  Accepted formats are hex
 * colors (#rgb, #rrggbb, #rrggbbaa) and functional rgb() / rgba() notation.
 * This prevents CSS-injection via a malicious "color" field in app.json.
 *
 * @param  string $color Raw color string from app.json.
 * @return string        A validated CSS color string.
 */
function sanitize_color(string $color): string
{
    $color = trim($color);

    // Hex shorthand (#rgb or #rgba) and full form (#rrggbb or #rrggbbaa).
    if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
        $len = strlen(ltrim($color, "#"));
        if (in_array($len, [3, 4, 6, 8], true)) {
            return $color;
        }
    }

    // rgb() and rgba() with numeric components.
    if (preg_match(
        '/^rgba?\(\s*(?:\d{1,3})\s*,\s*(?:\d{1,3})\s*,\s*(?:\d{1,3})\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/',
        $color
    )) {
        return $color;
    }

    return "#1e293b";
}
