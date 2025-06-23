<?php
define('DATA_DIR', __DIR__ . '/../data/');

function load_data($file) {
    $path = DATA_DIR . $file;
    if (!file_exists($path)) {
        file_put_contents($path, json_encode([]));
    }
    return json_decode(file_get_contents($path), true) ?: [];
}

function save_data($file, $data) {
    file_put_contents(DATA_DIR . $file, json_encode($data, JSON_PRETTY_PRINT));
}

// Fungsi khusus links dan logs
function load_links() { return load_data('links.json'); }
function save_links($data) { save_data('links.json', $data); }
function load_logs() { return load_data('logs.json'); }
function save_logs($data) { save_data('logs.json', $data); }
