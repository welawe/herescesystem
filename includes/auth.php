<?php
// Sistem autentikasi sederhana

session_start();

function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function login($username, $password) {
    // Hardcoded credentials - bisa diganti dengan yang lebih aman
    $valid_username = 'admin';
    $valid_password = 'admin123'; // Dalam produksi, gunakan password hash
    
    if ($username === $valid_username && $password === $valid_password) {
        $_SESSION['logged_in'] = true;
        return true;
    }
    
    return false;
}

function logout() {
    session_unset();
    session_destroy();
}

function require_auth() {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}
?>