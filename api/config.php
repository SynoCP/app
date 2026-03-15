<?php

/*
 *  SynoCP / App Übersicht – Config API
 *
 *  GET  /api/config.php  → Returns the current config.json as JSON
 *  POST /api/config.php  → Accepts a JSON body, validates it, and writes
 *                          the result back to assets/config.json
 */

require_once __DIR__ . "/../src/functions.php";

$config_file = __DIR__ . "/../assets/config.json";

header("Content-Type: application/json; charset=UTF-8");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Cache-Control: no-store");

// ── GET ───────────────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    echo json_encode(load_config($config_file), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $raw  = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON body"]);
        exit;
    }

    if (save_config($config_file, $data)) {
        echo json_encode(["success" => true]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to write config file"]);
    }
    exit;
}

// ── Method not allowed ────────────────────────────────────────────────────────
http_response_code(405);
header("Allow: GET, POST");
echo json_encode(["error" => "Method not allowed"]);
