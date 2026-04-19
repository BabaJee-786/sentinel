<?php
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/session_auth.php';

ensure_session_started();
ensure_default_dashboard_user($pdo);

if (get_authenticated_user()) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = SENTINEL_DEFAULT_USER_EMAIL;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Enter both email and password.';
    } elseif (!attempt_login($pdo, $email, $password)) {
        $error = 'Invalid login credentials.';
    } else {
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign in | Sentinel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .auth-body {
            background: var(--bg);
        }

        .auth-container {
            display: flex;
            min-height: 100vh;
        }

        .auth-left {
            flex: 1;
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px 40px;
            background: linear-gradient(135deg, #172033 0%, #0f1d31 100%);
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .auth-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(29, 78, 216, 0.15), transparent 70%);
            pointer-events: none;
        }

        .auth-left::after {
            content: '';
            position: absolute;
            bottom: -20%;
            left: -5%;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(15, 159, 110, 0.08), transparent 70%);
            pointer-events: none;
        }

        .auth-left-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 480px;
        }

        .auth-left h2 {
            margin: 0 0 16px;
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: -0.04em;
        }

        .auth-left p {
            margin: 0;
            font-size: 1.05rem;
            line-height: 1.6;
            color: rgba(232, 238, 249, 0.85);
        }

        .auth-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 60px;
            background: var(--bg);
        }

        .auth-card {
            width: 100%;
            max-width: 420px;
            padding: 40px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid var(--panel-border);
            box-shadow: 0 25px 50px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(12px);
        }

        .auth-header {
            margin-bottom: 32px;
            text-align: center;
        }

        .auth-marker {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            border-radius: 20px;
            background: linear-gradient(135deg, #60a5fa 0%, #2563eb 100%);
            color: #fff;
            font-weight: 700;
            font-size: 1.3rem;
            letter-spacing: 0.08em;
            box-shadow: 0 20px 40px rgba(37, 99, 235, 0.3);
        }

        .auth-title {
            margin: 0 0 8px;
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: -0.04em;
            color: var(--text);
        }

        .auth-subtitle {
            margin: 0;
            font-size: 0.95rem;
            color: var(--muted);
            line-height: 1.5;
        }

        .auth-form {
            display: grid;
            gap: 18px;
            margin-bottom: 0;
        }

        .form-group {
            display: grid;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group input {
            width: 100%;
            min-height: 48px;
            padding: 12px 16px;
            border: 1.5px solid rgba(148, 163, 184, 0.3);
            border-radius: 14px;
            background: #fff;
            color: var(--text);
            font: inherit;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-group input::placeholder {
            color: var(--muted);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.12);
            transform: translateY(-1px);
        }

        .auth-submit {
            width: 100%;
            min-height: 48px;
            margin-top: 8px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-strong) 100%);
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 16px 28px rgba(29, 78, 216, 0.22);
            transition: all 0.3s ease;
        }

        .auth-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(29, 78, 216, 0.28);
        }

        .auth-submit:active {
            transform: translateY(0);
        }

        .message-box {
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 18px;
        }

        .message-error {
            background: rgba(254, 226, 226, 0.95);
            border: 1px solid rgba(220, 38, 38, 0.16);
            color: #8f1d1d;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 24px 0;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(15, 23, 42, 0.08);
        }

        .auth-hint {
            padding: 16px;
            border-radius: 14px;
            background: rgba(239, 246, 255, 0.8);
            border: 1px solid rgba(59, 130, 246, 0.16);
            font-size: 0.9rem;
            line-height: 1.55;
            color: #1e3a8a;
        }

        .auth-hint strong {
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1e40af;
        }

        .auth-hint span {
            display: block;
            font-family: monospace;
            margin: 4px 0;
            padding: 4px 8px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 6px;
            word-break: break-all;
        }

        @media (max-width: 1024px) {
            .auth-left {
                display: none;
            }

            .auth-right {
                padding: 32px 20px;
            }

            .auth-card {
                padding: 32px 24px;
            }
        }

        @media (max-width: 640px) {
            .auth-right {
                padding: 24px 16px;
            }

            .auth-card {
                padding: 28px 20px;
                border-radius: 24px;
            }

            .auth-title {
                font-size: 1.6rem;
            }

            .auth-form {
                gap: 16px;
            }

            .form-group input {
                min-height: 44px;
            }

            .auth-submit {
                min-height: 44px;
            }
        }
    </style>
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-left">
            <div class="auth-left-content">
                <h2>Sentinel</h2>
                <p>Advanced threat detection and device management system for enterprise security</p>
            </div>
        </div>

        <div class="auth-right">
            <section class="auth-card">
                <div class="auth-header">
                    <div class="auth-marker">SN</div>
                    <h1 class="auth-title">Welcome back</h1>
                    <p class="auth-subtitle">Sign in to your Sentinel dashboard</p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="message-box message-error">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input 
                            type="email" 
                            id="email"
                            name="email" 
                            value="<?php echo htmlspecialchars($email); ?>" 
                            placeholder="Enter your email"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password"
                            name="password" 
                            placeholder="Enter your password"
                            required
                        >
                    </div>

                    <button type="submit" class="auth-submit">Sign In</button>
                </form>

                
            </section>
        </div>
    </div>
</body>
</html>
