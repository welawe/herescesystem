<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth();

$links = load_links();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        // Buat link baru
        $new_link = [
            'hash' => generate_hash(),
            'name' => $_POST['name'],
            'targets' => array_filter(array_map('trim', explode("\n", $_POST['targets']))),
            'rotation_method' => $_POST['rotation_method'],
            'active' => isset($_POST['active']),
            'created_at' => time(),
            'blocked_countries' => array_filter(array_map('trim', explode(",", $_POST['blocked_countries']))),
            'blocked_ips' => array_filter(array_map('trim', explode(",", $_POST['blocked_ips']))),
            'allowed_referers' => array_filter(array_map('trim', explode(",", $_POST['allowed_referers']))),
            'utm_params' => [],
            'redirect_style' => $_POST['redirect_style'] ?? 'direct', // 'direct' atau 'interstitial'
            'interstitial_html' => $_POST['interstitial_html'] ?? null

        ];
        
        // Parse UTM params
        if (!empty($_POST['utm_source'])) $new_link['utm_params']['utm_source'] = $_POST['utm_source'];
        if (!empty($_POST['utm_medium'])) $new_link['utm_params']['utm_medium'] = $_POST['utm_medium'];
        if (!empty($_POST['utm_campaign'])) $new_link['utm_params']['utm_campaign'] = $_POST['utm_campaign'];
        if (!empty($_POST['utm_term'])) $new_link['utm_params']['utm_term'] = $_POST['utm_term'];
        if (!empty($_POST['utm_content'])) $new_link['utm_params']['utm_content'] = $_POST['utm_content'];
        
        $links[] = $new_link;
        save_links($links);
        $message = "Link created successfully!";
    } elseif ($action === 'update') {
        // Update link
        $hash = $_POST['hash'];
        
        foreach ($links as &$link) {
            if ($link['hash'] === $hash) {
                $link['name'] = $_POST['name'];
                $link['targets'] = array_filter(array_map('trim', explode("\n", $_POST['targets'])));
                $link['rotation_method'] = $_POST['rotation_method'];
                $link['active'] = isset($_POST['active']);
                $link['blocked_countries'] = array_filter(array_map('trim', explode(",", $_POST['blocked_countries'])));
                $link['blocked_ips'] = array_filter(array_map('trim', explode(",", $_POST['blocked_ips'])));
                $link['allowed_referers'] = array_filter(array_map('trim', explode(",", $_POST['allowed_referers'])));
                
                // Update UTM params
                $link['utm_params'] = [];
                if (!empty($_POST['utm_source'])) $link['utm_params']['utm_source'] = $_POST['utm_source'];
                if (!empty($_POST['utm_medium'])) $link['utm_params']['utm_medium'] = $_POST['utm_medium'];
                if (!empty($_POST['utm_campaign'])) $link['utm_params']['utm_campaign'] = $_POST['utm_campaign'];
                if (!empty($_POST['utm_term'])) $link['utm_params']['utm_term'] = $_POST['utm_term'];
                if (!empty($_POST['utm_content'])) $link['utm_params']['utm_content'] = $_POST['utm_content'];
                
                break;
            }
        }
        
        save_links($links);
        $message = "Link updated successfully!";
    } elseif ($action === 'delete') {
        // Hapus link
        $hash = $_POST['hash'];
        $links = array_filter($links, function($link) use ($hash) {
            return $link['hash'] !== $hash;
        });
        save_links($links);
        $message = "Link deleted successfully!";
    }     elseif ($action === 'check_phishing') {
        $hash = $_POST['hash'];
        $link = get_link_by_hash($hash);
        
        if ($link) {
            $phishing_urls = [];
            
            foreach ($link['targets'] as $url) {
                if (is_phishing_page($url)) {
                    $phishing_urls[] = $url;
                }
            }
            
            if (!empty($phishing_urls)) {
                // AUTO-REMOVE: Nonaktifkan link otomatis
                foreach ($links as &$l) {
                    if ($l['hash'] === $hash) {
                        $l['active'] = false;
                        break;
                    }
                }
                save_links($links);
                
                // KIRIM NOTIFIKASI
                send_phishing_alert($link['name'], $phishing_urls);
                
                $message = "🚨 Phishing detected! Link dinonaktifkan otomatis. URL berbahaya: " . 
                          implode(", ", $phishing_urls);
            } else {
                $message = "✅ Tidak ada URL phishing yang terdeteksi";
            }
        }
    }
}
    
function is_phishing_page($url) {
    // 1. Ambil konten HTML
    $html = @file_get_contents($url);
    if ($html === false) return false;

    // 2. Deteksi pola peringatan phishing
    $phishing_signs = [
        'Deceptive site ahead',
        'This site may be hacked',
        'Back to safety',
        'Situs ini menipu',
        'dangerous site'
    ];

    foreach ($phishing_signs as $sign) {
        if (stripos($html, $sign) !== false) return true;
    }

    // 3. Deteksi struktur HTML mencurigakan
    if (preg_match('/<div[^>]+class=".*?(warning|danger|phishing).*?>/i', $html)) {
        return true;
    }

    return false;
}

$edit_link = null;
if (isset($_GET['edit'])) {
    $edit_link = get_link_by_hash($_GET['edit']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Links - Link Rotator</title>
    <style>
        :root {
            --bg-dark: #121212;
            --bg-darker: #0a0a0a;
            --neon-pink: #ff2a6d;
            --neon-purple: #d300c5;
            --neon-blue: #05d9e8;
            --neon-cyan: #00f7ff;
            --text-primary: #e0e0e0;
            --text-secondary: #b0b0b0;
            --card-bg: #1e1e1e;
            --card-border: #2a2a2a;
            --success: #00e676;
            --warning: #ff9100;
            --danger: #ff1744;
            --glow: 0 0 10px rgba(255, 42, 109, 0.7);
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        h1, h2, h3 {
            color: var(--neon-pink);
            text-shadow: var(--glow);
            margin-bottom: 20px;
        }

        h1 {
            font-size: 2.5rem;
            text-align: center;
            background: linear-gradient(90deg, var(--neon-pink), var(--neon-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--neon-pink);
        }

        .admin-panel {
            background-color: var(--bg-darker);
            border-radius: 8px;
            box-shadow: var(--glow);
            padding: 25px;
            margin-top: 20px;
            border: 1px solid var(--neon-pink);
        }

        nav {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, var(--neon-pink), var(--neon-purple));
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--glow);
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(255, 42, 109, 0.9);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--neon-cyan);
            font-weight: 500;
        }

        .form-group input[type="text"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            background-color: var(--card-bg);
            border: 1px solid var(--neon-blue);
            border-radius: 4px;
            color: var(--text-primary);
            font-size: 16px;
            transition: all 0.3s;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-group input[type="text"]:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--neon-pink);
            box-shadow: 0 0 8px rgba(255, 42, 109, 0.5);
        }

        .form-actions {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
        }

        .table th {
            background: linear-gradient(90deg, var(--neon-pink), var(--neon-purple));
            color: white;
            font-weight: 600;
            padding: 15px;
            text-align: left;
        }

        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--card-border);
        }

        .table tr:hover {
            background-color: rgba(255, 42, 109, 0.1);
        }

        .table a {
            color: var(--neon-blue);
            text-decoration: none;
            transition: all 0.3s;
        }

        .table a:hover {
            color: var(--neon-cyan);
            text-shadow: 0 0 8px rgba(5, 217, 232, 0.7);
        }

        .status-active {
            color: var(--success);
            font-weight: 600;
        }

        .status-inactive {
            color: var(--danger);
            font-weight: 600;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: 500;
        }

        .alert-success {
            background-color: rgba(0, 230, 118, 0.1);
            border-left: 4px solid var(--success);
            color: var(--success);
        }

        @keyframes neon-glow {
            0% { text-shadow: 0 0 5px var(--neon-pink), 0 0 10px rgba(255, 42, 109, 0.5); }
            50% { text-shadow: 0 0 10px var(--neon-pink), 0 0 20px rgba(255, 42, 109, 0.7); }
            100% { text-shadow: 0 0 5px var(--neon-pink), 0 0 10px rgba(255, 42, 109, 0.5); }
        }

        .neon-text {
            animation: neon-glow 2s infinite alternate;
        }

        @media (max-width: 768px) {
            nav {
                flex-direction: column;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 480px) {
            .form-group input[type="text"],
            .form-group textarea,
            .form-group select {
                padding: 10px;
            }
            
            .btn {
                padding: 10px 15px;
                font-size: 14px;
            }
        }
        .phishing-alert {
            background: linear-gradient(90deg, #ff0000, #ff6b6b);
            color: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 0, 0, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 0, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 0, 0, 0); }
        }
    </style>
</head>
<body>
    <div class="container">
         <?php if (isset($message) && strpos($message, 'Phishing detected') !== false): ?>
        <div class="phishing-alert">
            <strong>⚠️ PERINGATAN RF!</strong> <?= htmlspecialchars($message) ?>
        </div>
        <script>
            Swal.fire({
                title: 'PHISHING DETECTED!',
                text: '<?= addslashes(str_replace("🚨", "", $message)) ?>',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        </script>
        <?php endif; ?>
        <h1 class="neon-text">Manage Links</h1>
        
        <div class="admin-panel">
            <nav>
                <a href="/admin/" class="btn">Dashboard</a>
                <a href="/admin/links.php" class="btn">Manage Links</a>
                <a href="/admin/stats.php" class="btn">Statistics</a>
                <a href="/logout.php" class="btn">Logout</a>
            </nav>
            
            <?php if (isset($message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <h2><?= $edit_link ? 'Edit Link' : 'Create New Link' ?></h2>
            <form method="post">
                <input type="hidden" name="action" value="<?= $edit_link ? 'update' : 'create' ?>">
                <?php if ($edit_link): ?>
                <input type="hidden" name="hash" value="<?= $edit_link['hash'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">Link Name</label>
                    <input type="text" id="name" name="name" required 
                           value="<?= $edit_link ? htmlspecialchars($edit_link['name']) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="targets">Target URLs (one per line)</label>
                    <textarea id="targets" name="targets" rows="5" required><?= $edit_link ? htmlspecialchars(implode("\n", $edit_link['targets'])) : '' ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="rotation_method">Rotation Method</label>
                    <select id="rotation_method" name="rotation_method" required>
                        <option value="random" <?= ($edit_link && $edit_link['rotation_method'] === 'random') ? 'selected' : '' ?>>Random</option>
                        <option value="round-robin" <?= ($edit_link && $edit_link['rotation_method'] === 'round-robin') ? 'selected' : '' ?>>Round Robin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="active" <?= ($edit_link && $edit_link['active']) ? 'checked' : 'checked' ?>>
                        Active
                    </label>
                </div>
                
                <h3>Security Settings</h3>
                
                <div class="form-group">
                    <label for="blocked_countries">Blocked Countries (comma separated)</label>
                    <input type="text" id="blocked_countries" name="blocked_countries" 
                           value="<?= $edit_link ? htmlspecialchars(implode(", ", $edit_link['blocked_countries'])) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="blocked_ips">Blocked IPs (comma separated)</label>
                    <input type="text" id="blocked_ips" name="blocked_ips" 
                           value="<?= $edit_link ? htmlspecialchars(implode(", ", $edit_link['blocked_ips'])) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="allowed_referers">Allowed Referers (comma separated)</label>
                    <input type="text" id="allowed_referers" name="allowed_referers" 
                           value="<?= $edit_link ? htmlspecialchars(implode(", ", $edit_link['allowed_referers'])) : '' ?>">
                </div>
                
                <h3>UTM Parameters</h3>
                
                <div class="form-group">
                    <label for="utm_source">UTM Source</label>
                    <input type="text" id="utm_source" name="utm_source" 
                           value="<?= $edit_link ? htmlspecialchars($edit_link['utm_params']['utm_source'] ?? '') : '' ?>">
                </div>
                <div class="form-group">
    <label for="redirect_style">Redirect Style</label>
    <select id="redirect_style" name="redirect_style" class="form-control">
        <option value="direct" <?= ($edit_link['redirect_style'] ?? 'direct') === 'direct' ? 'selected' : '' ?>>Direct Redirect</option>
        <option value="interstitial" <?= ($edit_link['redirect_style'] ?? '') === 'interstitial' ? 'selected' : '' ?>>Interstitial Page (Like Cloudflare)</option>
    </select>
</div>

<div id="interstitial_settings" style="<?= ($edit_link['redirect_style'] ?? 'direct') === 'interstitial' ? '' : 'display:none' ?>">
    <div class="form-group">
        <label for="interstitial_html">Custom Interstitial HTML</label>
        <textarea id="interstitial_html" name="interstitial_html" rows="5" class="form-control"><?= 
            htmlspecialchars($edit_link['interstitial_html'] ?? '<!DOCTYPE html>
<html>
<head>
    <title>Redirecting...</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .countdown { font-size: 24px; margin: 20px; }
    </style>
</head>
<body>
    <h1>You are being redirected</h1>
    <p>Safety check in progress...</p>
    <div class="countdown">Redirecting in <span id="count">5</span> seconds</div>
    <a href="#" id="proceed-link">Proceed now</a>
</body>
</html>') 
        ?></textarea>
    </div>
</div>


                
                <div class="form-group">
                    <label for="utm_medium">UTM Medium</label>
                    <input type="text" id="utm_medium" name="utm_medium" 
                           value="<?= $edit_link ? htmlspecialchars($edit_link['utm_params']['utm_medium'] ?? '') : '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="utm_campaign">UTM Campaign</label>
                    <input type="text" id="utm_campaign" name="utm_campaign" 
                           value="<?= $edit_link ? htmlspecialchars($edit_link['utm_params']['utm_campaign'] ?? '') : '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="utm_term">UTM Term</label>
                    <input type="text" id="utm_term" name="utm_term" 
                           value="<?= $edit_link ? htmlspecialchars($edit_link['utm_params']['utm_term'] ?? '') : '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="utm_content">UTM Content</label>
                    <input type="text" id="utm_content" name="utm_content" 
                           value="<?= $edit_link ? htmlspecialchars($edit_link['utm_params']['utm_content'] ?? '') : '' ?>">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn"><?= $edit_link ? 'Update Link' : 'Create Link' ?></button>
                    <?php if ($edit_link): ?>
                    <a href="/admin/links.php" class="btn">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
            
            <h2>Existing Links</h2>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Short URL</th>
                            <th>Targets</th>
                            <th>Rotation</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($links as $link): ?>
                        <tr>
                            <td><?= htmlspecialchars($link['name']) ?></td>
                            <td><a href="/go/<?= $link['hash'] ?>" target="_blank">/go/<?= $link['hash'] ?></a></td>
                            <td><?= count($link['targets']) ?> URLs</td>
                            <td><?= ucfirst($link['rotation_method']) ?></td>
                            <td class="<?= $link['active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $link['active'] ? '🟢 Active' : '🔴 Inactive' ?>
                            </td>
                            <td>
                                <a href="/admin/links.php?edit=<?= $link['hash'] ?>" class="btn">Edit</a>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="hash" value="<?= $link['hash'] ?>">
                                    <button type="submit" class="btn" onclick="return confirm('Are you sure?')">Delete</button>
                                </form>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="check_phishing">
                                    <input type="hidden" name="hash" value="<?= $link['hash'] ?>">
                                    <button type="submit" class="btn">Check Phishing</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Add interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Add glow effect to buttons on hover
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.style.boxShadow = '0 0 20px rgba(255, 42, 109, 0.9)';
                });
                btn.addEventListener('mouseleave', function() {
                    this.style.boxShadow = '0 0 10px rgba(255, 42, 109, 0.7)';
                });
            });

            // Add pulse effect to form inputs on focus
            const inputs = document.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.boxShadow = '0 0 15px rgba(5, 217, 232, 0.5)';
                });
                input.addEventListener('blur', function() {
                    this.style.boxShadow = '';
                });
            });

            // Confirm before deleting
            const deleteForms = document.querySelectorAll('form[action="delete"]');
            deleteForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Are you sure you want to delete this link?')) {
                        e.preventDefault();
                    }
                });
            });
        });
      
             document.getElementById('redirect_style').addEventListener('change', function() {
               document.getElementById('interstitial_settings').style.display = 
             this.value === 'interstitial' ? 'block' : 'none';
});

    </script>
</body>
</html>