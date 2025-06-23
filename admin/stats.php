<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth();

$logs = load_logs();
$links = load_links();

// Filter berdasarkan hash jika ada
$filter_hash = $_GET['hash'] ?? null;
$filtered_logs = $logs;

if ($filter_hash) {
    $filtered_logs = array_filter($logs, function($log) use ($filter_hash) {
        return $log['hash'] === $filter_hash;
    });
}

// Hitung statistik
$total_clicks = count($filtered_logs);
$human_clicks = count(array_filter($filtered_logs, function($log) {
    return !$log['is_bot'];
}));
$bot_clicks = $total_clicks - $human_clicks;

// Hitung berdasarkan negara
$countries = [];
foreach ($filtered_logs as $log) {
    $country = $log['country'];
    if (!isset($countries[$country])) {
        $countries[$country] = 0;
    }
    $countries[$country]++;
}
arsort($countries);

// Hitung berdasarkan device
$devices = ['Mobile' => 0, 'Desktop' => 0];
foreach ($filtered_logs as $log) {
    $devices[$log['device']]++;
}

// Hitung berdasarkan waktu
$daily_clicks = [];
foreach ($filtered_logs as $log) {
    $date = substr($log['date'], 0, 10);
    if (!isset($daily_clicks[$date])) {
        $daily_clicks[$date] = 0;
    }
    $daily_clicks[$date]++;
}
ksort($daily_clicks);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - Heresce Shorturl System</title>
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
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--neon-blue);
            box-shadow: 0 5px 20px rgba(5, 217, 232, 0.3);
        }

        .stat-card h3 {
            margin-top: 0;
            color: var(--neon-cyan);
            font-size: 1.2rem;
        }

        .stat-card .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(90deg, var(--neon-pink), var(--neon-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 10px 0;
        }

        .filter-form {
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid var(--neon-purple);
        }

        .filter-form .form-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-form label {
            color: var(--neon-cyan);
            font-weight: 500;
        }

        .filter-form select {
            flex: 1;
            min-width: 200px;
            padding: 10px;
            background-color: var(--card-bg);
            border: 1px solid var(--neon-blue);
            border-radius: 4px;
            color: var(--text-primary);
            font-size: 16px;
        }

        .filter-form select:focus {
            outline: none;
            border-color: var(--neon-pink);
            box-shadow: 0 0 8px rgba(255, 42, 109, 0.5);
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

        .chart-container {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid var(--neon-blue);
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
            
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
            
            .table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 480px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .filter-form .form-group {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-form select {
                width: 100%;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <h1 class="neon-text">Link Statistics</h1>
        
        <div class="admin-panel">
            <nav>
                <a href="/admin/" class="btn">Dashboard</a>
                <a href="/admin/links.php" class="btn">Manage Links</a>
                <a href="/admin/stats.php" class="btn">Statistics</a>
                <a href="/logout.php" class="btn">Logout</a>
            </nav>
            
            <form method="get" class="filter-form">
                <div class="form-group">
                    <label for="hash">Filter by Link:</label>
                    <select id="hash" name="hash">
                        <option value="">All Links</option>
                        <?php foreach ($links as $link): ?>
                        <option value="<?= $link['hash'] ?>" <?= ($filter_hash === $link['hash']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($link['name']) ?> (/go/<?= $link['hash'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn">Filter</button>
                </div>
            </form>
            
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Total Clicks</h3>
                    <div class="stat-value"><?= $total_clicks ?></div>
                    <p>All time visitors</p>
                </div>
                
                <div class="stat-card">
                    <h3>Human Clicks</h3>
                    <div class="stat-value"><?= $human_clicks ?></div>
                    <p>Legitimate traffic</p>
                </div>
                
                <div class="stat-card">
                    <h3>Bot Clicks</h3>
                    <div class="stat-value"><?= $bot_clicks ?></div>
                    <p>Blocked attempts</p>
                </div>
            </div>
            
            <h2>Statistics by Country</h2>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>Clicks</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($countries as $country => $count): ?>
                        <tr>
                            <td><?= $country ?></td>
                            <td><?= $count ?></td>
                            <td><?= $total_clicks > 0 ? round(($count / $total_clicks) * 100, 2) : 0 ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <h2>Statistics by Device</h2>
            <div class="chart-container">
                <canvas id="deviceChart"></canvas>
            </div>
            
            <h2>Daily Clicks</h2>
            <div class="chart-container">
                <canvas id="dailyChart"></canvas>
            </div>
            
            <h2>Recent Clicks</h2>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Link</th>
                            <th>IP</th>
                            <th>Country</th>
                            <th>Device</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice(array_reverse($filtered_logs), 0, 10) as $log): ?>
                        <tr>
                            <td><?= $log['date'] ?></td>
                            <td>/go/<?= $log['hash'] ?></td>
                            <td><?= $log['ip'] ?></td>
                            <td><?= $log['country'] ?></td>
                            <td><?= $log['device'] ?></td>
                            <td><?= $log['is_bot'] ? 'Bot' : 'Human' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Custom neon chart styling
        const neonPink = 'rgba(255, 42, 109, 0.8)';
        const neonBlue = 'rgba(5, 217, 232, 0.8)';
        const neonPurple = 'rgba(211, 0, 197, 0.8)';
        const textColor = '#e0e0e0';
        const gridColor = 'rgba(255, 255, 255, 0.1)';
        
        // Device chart
        const deviceCtx = document.getElementById('deviceChart').getContext('2d');
        const deviceChart = new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Mobile', 'Desktop'],
                datasets: [{
                    data: [<?= $devices['Mobile'] ?>, <?= $devices['Desktop'] ?>],
                    backgroundColor: [neonPink, neonBlue],
                    borderColor: ['#fff', '#fff'],
                    borderWidth: 1,
                    hoverOffset: 10,
                    hoverBackgroundColor: [neonPurple, neonBlue]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: {
                            color: textColor,
                            font: {
                                size: 14
                            }
                        }
                    },
                    tooltip: {
                        bodyColor: textColor,
                        titleColor: textColor,
                        backgroundColor: 'rgba(30, 30, 30, 0.9)',
                        borderColor: neonPink,
                        borderWidth: 1
                    }
                },
                cutout: '70%'
            }
        });
        
        // Daily chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_keys($daily_clicks)) ?>,
                datasets: [{
                    label: 'Daily Clicks',
                    data: <?= json_encode(array_values($daily_clicks)) ?>,
                    borderColor: neonPink,
                    borderWidth: 2,
                    backgroundColor: 'rgba(255, 42, 109, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: neonPink,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: {
                            color: textColor,
                            font: {
                                size: 14
                            }
                        }
                    },
                    tooltip: {
                        bodyColor: textColor,
                        titleColor: textColor,
                        backgroundColor: 'rgba(30, 30, 30, 0.9)',
                        borderColor: neonPink,
                        borderWidth: 1
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: textColor
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: textColor
                        }
                    }
                }
            }
        });

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
        });
    </script>
</body>
</html>