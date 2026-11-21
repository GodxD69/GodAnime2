<?php
require_once __DIR__ . '/domain_manager.php';

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'set_domain') {
        $newDomain = trim($_POST['domain'] ?? '');
        if (reanime_set_domain($newDomain)) {
            $message = "Successfully updated active domain to: " . htmlspecialchars($newDomain);
            $messageType = 'success';
        } else {
            $message = "Invalid domain URL format. Please start with http:// or https://";
            $messageType = 'error';
        }
    } elseif ($action === 'test_auto') {
        $active = reanime_get_domain(true);
        $message = "Auto-detection completed! Selected healthiest domain: " . htmlspecialchars($active);
        $messageType = 'success';
    } elseif ($action === 'toggle_auto') {
        $config = reanime_domain_load_config();
        $config['auto_update'] = !($config['auto_update'] ?? true);
        reanime_domain_save_config($config);
        $message = "Auto-update setting changed to: " . ($config['auto_update'] ? 'Enabled' : 'Disabled');
        $messageType = 'success';
    }
}

$config = reanime_domain_load_config();
$currentDomain = reanime_get_domain(false);
$probe = reanime_domain_probe($currentDomain, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GodAnime — Domain & Backend Control Center</title>
    <link rel="icon" href="/public/logo/favicon-32x32.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #09090b;
            --card-bg: #121217;
            --border: #27272a;
            --primary: #A855F7;
            --primary-glow: rgba(168, 85, 247, 0.35);
            --text: #f4f4f5;
            --text-muted: #a1a1aa;
            --success: #10b981;
            --error: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2.5rem 1rem;
        }
        .container {
            width: 100%;
            max-width: 780px;
        }
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo-wrap {
            margin-bottom: 1rem;
            display: inline-block;
        }
        .logo-wrap img, .logo-wrap svg {
            height: 64px;
            width: auto;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #fff 40%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        p.subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        .alert {
            padding: 0.9rem 1.2rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .alert-success { background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert-error { background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        .alert-info { background: rgba(168, 85, 247, 0.12); border: 1px solid rgba(168, 85, 247, 0.3); color: #c084fc; }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }
        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .badge-online { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); }
        .badge-offline { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .stat-item {
            background: #09090c;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 0.75rem;
            padding: 1rem;
        }
        .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }
        .stat-value {
            font-size: 1rem;
            font-weight: 700;
            word-break: break-all;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .form-row {
            display: flex;
            gap: 0.75rem;
        }
        input[type="text"] {
            flex: 1;
            background: #09090c;
            border: 1px solid var(--border);
            color: #fff;
            padding: 0.85rem 1rem;
            border-radius: 0.65rem;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s;
        }
        input[type="text"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }
        button, .btn {
            background: linear-gradient(135deg, #9333ea, #7928ca);
            color: #fff;
            border: none;
            padding: 0.85rem 1.4rem;
            border-radius: 0.65rem;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        button:hover, .btn:hover {
            filter: brightness(1.15);
            box-shadow: 0 0 20px var(--primary-glow);
            transform: translateY(-1px);
        }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.05);
            box-shadow: none;
        }

        .candidates-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .candidate-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #09090c;
            border: 1px solid rgba(255, 255, 255, 0.04);
            padding: 0.75rem 1rem;
            border-radius: 0.65rem;
            font-size: 0.9rem;
        }
        .candidate-name {
            font-family: monospace;
            font-size: 0.92rem;
        }
        .nav-back {
            text-align: center;
            margin-top: 1.5rem;
        }
        .nav-back a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        .nav-back a:hover {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-wrap">
                <img src="/public/logo/godanime_logo.svg" alt="GodAnime">
            </div>
            <h1>Backend & Domain Control</h1>
            <p class="subtitle">Live domain tracking & mirror routing for GodAnime</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Active Domain Status -->
        <div class="card">
            <div class="card-title">
                <span>Active Streaming Backend</span>
                <span class="badge <?= $probe['ok'] ? 'badge-online' : 'badge-offline' ?>">
                    <?= $probe['ok'] ? '● Online (' . $probe['latency_ms'] . 'ms)' : '● Inactive (HTTP ' . $probe['code'] . ')' ?>
                </span>
            </div>

            <div class="stat-grid">
                <div class="stat-item">
                    <div class="stat-label">Active Domain</div>
                    <div class="stat-value" style="color: #38bdf8;"><?= htmlspecialchars($currentDomain) ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Auto Domain Tracking</div>
                    <div class="stat-value" style="color: <?= ($config['auto_update'] ?? true) ? '#34d399' : '#f87171' ?>;">
                        <?= ($config['auto_update'] ?? true) ? 'Enabled (Auto-Recovers)' : 'Manual Only' ?>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="test_auto">
                    <button type="submit" class="btn">
                        ⚡ Scan & Pick Healthiest Mirror
                    </button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="toggle_auto">
                    <button type="submit" class="btn btn-outline">
                        <?= ($config['auto_update'] ?? true) ? 'Disable Auto Tracking' : 'Enable Auto Tracking' ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Manual Domain Switcher -->
        <div class="card">
            <div class="card-title">Manual Domain Override</div>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 1rem;">
                Whenever ReAnime changes their domain, enter the new domain here. Every single page, scraper, asset proxy, and stream link in GodAnime updates instantly to use it.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="set_domain">
                <div class="form-row">
                    <input type="text" name="domain" value="<?= htmlspecialchars($currentDomain) ?>" placeholder="https://reanime.cz" required>
                    <button type="submit">Update Domain</button>
                </div>
            </form>
        </div>

        <!-- Known Candidate Mirrors -->
        <div class="card">
            <div class="card-title">Known Mirror Candidates</div>
            <ul class="candidates-list">
                <?php foreach ($config['candidate_domains'] ?? [] as $cand): ?>
                    <li class="candidate-item">
                        <span class="candidate-name"><?= htmlspecialchars($cand) ?></span>
                        <?php if (strcasecmp($cand, $currentDomain) === 0): ?>
                            <span class="badge badge-online">ACTIVE</span>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="set_domain">
                                <input type="hidden" name="domain" value="<?= htmlspecialchars($cand) ?>">
                                <button type="submit" class="btn btn-outline" style="padding: 0.35rem 0.8rem; font-size: 0.8rem;">Switch</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="nav-back">
            <a href="/">← Return to GodAnime Streaming App</a>
        </div>
    </div>
</body>
</html>

