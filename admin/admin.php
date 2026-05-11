<?php
/**
 * CineFrame — Admin Giriş Portalı
 * ==============================
 * Bu dosya admin paneline giriş için kullanılır.
 */

session_start();

// Zaten giriş yapmışsa yönlendir
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // config.php'den şifreyi al
    $configPath = __DIR__ . '/../config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
        
        // Basit kullanıcı adı ve şifre kontrolü
        $defaultUser = 'admin'; // Sabit kullanıcı adı
        if (defined('ADMIN_DEFAULT_PASSWORD') && $username === $defaultUser && $password === ADMIN_DEFAULT_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Hatalı kullanıcı adı veya şifre.';
        }
    } else {
        $error = 'config.php dosyası bulunamadı.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineFrame | Admin Portalı</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?= time() ?>">
</head>
<body class="login-body">

    <!-- Dinamik Işık Efektleri -->
    <div class="ambient-light light-1"></div>
    <div class="ambient-light light-2"></div>

    <div class="portal-wrapper">
        <main class="portal-card">
            
            <header class="portal-header">
                <span class="portal-icon">🎬</span>
                <h1>Cine<span>Frame</span></h1>
                <p>Yönetim Portalı</p>
            </header>

            <?php if ($error): ?>
                <div class="error-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="admin.php" class="portal-form">
                <div class="input-group">
                    <input type="text" name="username" id="username" placeholder="Kullanıcı Adı" required autofocus autocomplete="username">
                    <div class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                </div>
                <div class="input-group">
                    <input type="password" name="password" id="password" placeholder="Şifre" required autocomplete="current-password">
                    <div class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Sisteme Giriş Yap</button>
            </form>

            <div class="back-nav">
                <a href="../index.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    Oyuna Dön
                </a>
            </div>

        </main>
    </div>

</body>
</html>
