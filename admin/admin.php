<?php
/**
 * CineFrame Admin — Giriş Sayfası
 * =================================
 * Basit session tabanlı admin girişi.
 * Varsayılan kullanıcı: admin / admin123
 */
session_start();

// Zaten giriş yapmışsa dashboard'a yönlendir
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Hata mesajı
$error = '';

// Form gönderildiyse
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Kullanıcı adı ve şifre gereklidir.';
    } else {
        // Veritabanı bağlantısı
        $dbPath = __DIR__ . '/../database.sqlite';

        if (!file_exists($dbPath)) {
            $error = 'Veritabanı bulunamadı. Lütfen önce setup.php çalıştırın.';
        } else {
            try {
                $pdo = new PDO('sqlite:' . $dbPath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Kullanıcıyı bul
                $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = :u LIMIT 1");
                $stmt->execute([':u' => $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    // Giriş başarılı
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $user['username'];
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Geçersiz kullanıcı adı veya şifre.';
                }
            } catch (PDOException $e) {
                $error = 'Veritabanı hatası.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineFrame Admin — Giriş</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <span class="login-icon">🎬</span>
                <h1>CineFrame Admin</h1>
                <p>Yönetim paneline giriş yapın</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username">Kullanıcı Adı</label>
                    <input type="text" id="username" name="username" placeholder="admin" required
                           value="<?= htmlspecialchars($username ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="password">Şifre</label>
                    <input type="password" id="password" name="password" placeholder="••••••" required>
                </div>
                <button type="submit" class="btn-login">Giriş Yap</button>
            </form>

            <a href="../index.php" class="back-link">← Oyuna Dön</a>
        </div>
    </div>
</body>
</html>
