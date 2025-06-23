<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth();

$links = load_links();
$logs = load_logs();

$total_links = count($links);
$total_clicks = count($logs);
$total_bot_clicks = count(array_filter($logs, function($log) {
    return $log['is_bot'];
}));
$total_human_clicks = $total_clicks - $total_bot_clicks;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Heresce Shorturl System</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        :root {
            --bg-dark: #121212;
            --bg-darker: #0a0a0a;
            --neon-pink: #ff2a6d;
            --neon-purple: #d300c5;
            --neon-blue: #05d9e8;
            --text-primary: #e0e0e0;
            --text-secondary: #b0b0b0;
            --card-bg: #1e1e1e;
            --card-border: #2a2a2a;
            --success: #00e676;
            --warning: #ff9100;
            --danger: #ff1744;
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
            text-shadow: 0 0 5px var(--neon-pink), 0 0 10px rgba(255, 42, 109, 0.5);
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
            box-shadow: 0 0 15px rgba(255, 42, 109, 0.2);
            padding: 25px;
            margin-top: 20px;
            border: 1px solid var(--card-border);
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
            box-shadow: 0 0 10px rgba(255, 42, 109, 0.3);
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(255, 42, 109, 0.5);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--card-border);
            transition: all 0.3s ease;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(255, 42, 109, 0.3);
            border: 1px solid var(--neon-pink);
        }

        .stat-card h3 {
            margin-top: 0;
            color: var(--text-secondary);
            font-size: 1.2rem;
            text-shadow: none;
        }

        .stat-card .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(90deg, var(--neon-pink), var(--neon-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 10px 0;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
        }

        .table th, .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--card-border);
        }

        .table th {
            background: linear-gradient(90deg, var(--neon-pink), var(--neon-purple));
            color: white;
            font-weight: 600;
        }

        .table tr:hover {
            background-color: rgba(255, 42, 109, 0.05);
        }

        .table a {
            color: var(--neon-blue);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .table a:hover {
            color: var(--neon-pink);
            text-shadow: 0 0 5px rgba(5, 217, 232, 0.3);
        }

        .status-active {
            color: var(--success);
        }

        .status-inactive {
            color: var(--danger);
        }

        /* Animations */
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 42, 109, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 42, 109, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 42, 109, 0); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
            
            nav {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Dashboard</h1>
        
        <div class="admin-panel">
            <nav>
                <a href="/admin/" class="btn pulse">Dashboard</a>
                <a href="/admin/links.php" class="btn">Manage Links</a>
                <a href="/admin/stats.php" class="btn">Statistics</a>
                <a href="/logout.php" class="btn">Logout</a>
            </nav>
            
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Total Links</h3>
                    <div class="stat-value"><?= $total_links ?></div>
                    <p>Active rotation links</p>
                </div>
                
                <div class="stat-card">
                    <h3>Total Clicks</h3>
                    <div class="stat-value"><?= $total_clicks ?></div>
                    <p>All time visitors</p>
                </div>
                
                <div class="stat-card">
                    <h3>Human Clicks</h3>
                    <div class="stat-value"><?= $total_human_clicks ?></div>
                    <p>Legitimate traffic</p>
                </div>
                
                <div class="stat-card">
                    <h3>Bot Clicks</h3>
                    <div class="stat-value"><?= $total_bot_clicks ?></div>
                    <p>Blocked attempts</p>
                </div>
            </div>
            
            <h2>Recent Links</h2>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Short URL</th>
                            <th>Targets</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($links, 0, 5) as $link): ?>
                        <tr>
                            <td><?= htmlspecialchars($link['name']) ?></td>
                            <td><a href="/go/<?= $link['hash'] ?>" target="_blank">/go/<?= $link['hash'] ?></a></td>
                            <td><?= count($link['targets']) ?> URLs</td>
                            <td class="<?= $link['active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $link['active'] ? '🟢 Active' : '🔴 Inactive' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Tambahkan efek interaktif
        document.addEventListener('DOMContentLoaded', function() {
            // Efek hover untuk stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 5px 15px rgba(255, 42, 109, 0.3)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });

            // Animasi teks neon
            const neonElements = document.querySelectorAll('h1, .stat-value');
            neonElements.forEach(el => {
                el.addEventListener('mouseover', function() {
                    this.style.textShadow = '0 0 10px var(--neon-pink)';
                });
                
                el.addEventListener('mouseout', function() {
                    this.style.textShadow = '';
                });
            });
        });
    </script>
</body>
</html>