<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

$error = '';

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_id'] = $admin['id'];

            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password! Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Routine Control Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600&family=Space+Grotesk:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --ink: #161d18;
      --paper: #0f1512;
      --card: #18221b;
      --line: rgba(232, 228, 216, 0.14);
      --text: #e8e4d8;
      --text-dim: #9b9788;
      --amber: #e3a13d;
      --amber-glow: rgba(227, 161, 61, 0.18);
      --error: #e06c75;
      --error-bg: rgba(224, 108, 117, 0.12);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: var(--paper);
      color: var(--text);
      font-family: 'Space Grotesk', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      position: relative;
    }
    body::before {
      content: "";
      position: fixed; inset: 0;
      background: radial-gradient(ellipse at 50% 10%, rgba(227,161,61,0.09), transparent 60%);
      pointer-events: none;
    }
    .login-box {
      width: 100%;
      max-width: 440px;
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 2.5rem 2.2rem;
      box-shadow: 0 20px 45px rgba(0,0,0,0.45);
      position: relative;
      z-index: 1;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.76rem;
      color: var(--amber);
      letter-spacing: 0.12em;
      text-transform: uppercase;
      margin-bottom: 0.8rem;
    }
    .brand-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--amber);
      box-shadow: 0 0 10px var(--amber);
    }
    h1 {
      font-family: 'Fraunces', serif;
      font-weight: 500;
      font-size: 2.1rem;
      line-height: 1.15;
      color: var(--text);
      margin-bottom: 0.5rem;
    }
    p.subtitle {
      font-size: 0.9rem;
      color: var(--text-dim);
      margin-bottom: 2rem;
      line-height: 1.45;
    }
    .alert-error {
      background: var(--error-bg);
      border: 1px solid rgba(224, 108, 117, 0.35);
      color: var(--error);
      padding: 0.75rem 1rem;
      border-radius: 8px;
      font-size: 0.86rem;
      margin-bottom: 1.4rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .form-group {
      margin-bottom: 1.35rem;
    }
    label {
      display: block;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.75rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--text-dim);
      margin-bottom: 0.45rem;
    }
    .input-wrapper {
      position: relative;
    }
    input[type="text"],
    input[type="password"] {
      width: 100%;
      background: #121814;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 0.85rem 1rem;
      color: var(--text);
      font-family: inherit;
      font-size: 0.95rem;
      transition: all 0.2s ease;
      outline: none;
    }
    input[type="text"]:focus,
    input[type="password"]:focus {
      border-color: var(--amber);
      box-shadow: 0 0 0 3px var(--amber-glow);
    }
    .toggle-pass {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--text-dim);
      cursor: pointer;
      font-size: 0.8rem;
      font-family: 'JetBrains Mono', monospace;
      padding: 4px;
    }
    .btn-submit {
      width: 100%;
      background: var(--amber);
      color: #161d18;
      border: none;
      border-radius: 8px;
      padding: 0.9rem;
      font-family: 'Space Grotesk', sans-serif;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      margin-top: 0.6rem;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }
    .btn-submit:hover {
      background: #f0b04c;
      transform: translateY(-1px);
      box-shadow: 0 4px 15px rgba(227, 161, 61, 0.35);
    }
    .footer-links {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 1.8rem;
      padding-top: 1.4rem;
      border-top: 1px solid var(--line);
      font-size: 0.82rem;
    }
    .footer-links a {
      color: var(--text-dim);
      text-decoration: none;
      transition: color 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
    }
    .footer-links a:hover {
      color: var(--amber);
    }
    .hint-box {
      margin-top: 1.2rem;
      padding: 0.75rem 0.9rem;
      background: rgba(227, 161, 61, 0.08);
      border: 1px dashed rgba(227, 161, 61, 0.3);
      border-radius: 8px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.72rem;
      color: var(--text-dim);
      line-height: 1.4;
    }
    .hint-box strong {
      color: var(--amber);
    }
  </style>
</head>
<body>

<div class="login-box">
  <div class="brand">
    <span class="brand-dot"></span>
    <span>Control Portal</span>
  </div>

  <h1>Sign In</h1>
  <p class="subtitle">Admin portal to manage deadlines, courses, and routine ledger.</p>

  <?php if (!empty($error)): ?>
    <div class="alert-error">
      <span>⚠️</span>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" action="login.php" autocomplete="off">
    <div class="form-group">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" placeholder="Enter username" autocomplete="off" required autofocus value="">
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <div class="input-wrapper">
        <input type="password" id="password" name="password" placeholder="Enter password" autocomplete="new-password" required value="">
        <button type="button" class="toggle-pass" id="togglePassBtn" onclick="togglePass()">Show</button>
      </div>
    </div>

    <button type="submit" class="btn-submit">
      <span>Sign In</span>
      <span>→</span>
    </button>
  </form>

  <div class="hint-box">
    Default Login: Username: <strong>admin</strong> | Password: <strong>admin123</strong><br>
    (You can change your password anytime in the portal settings)
  </div>

  <div class="footer-links">
    <a href="../index.php">← Main Website</a>
    <span style="color: var(--text-dim); font-size:0.75rem;">Maut Ka Khel</span>
  </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    const u = document.getElementById('username');
    const p = document.getElementById('password');
    if (u) u.value = '';
    if (p) p.value = '';
  }, 60);
});

function togglePass() {
  const p = document.getElementById('password');
  const btn = document.getElementById('togglePassBtn');
  if (p.type === 'password') {
    p.type = 'text';
    btn.textContent = 'Hide';
  } else {
    p.type = 'password';
    btn.textContent = 'Show';
  }
}
</script>

</body>
</html>
