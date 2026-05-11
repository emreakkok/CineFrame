<?php
/**
 * CineFrame - Veritabanı Kurulum Dosyası (Production)
 * =====================================================
 * Bu dosyayı tarayıcıda BİR KEZ çalıştırarak veritabanını oluşturun.
 * Sadece tablolar ve admin kullanıcısı kurulur.
 * Film ve görsel verisi admin panelinden yönetilir — dummy data eklenmez.
 * 
 * Kullanım: http://localhost/CineFrame/setup.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbPath = __DIR__ . '/database.sqlite';
$dbExists = file_exists($dbPath);

try {
    // =============================================
    // 1. SQLite Veritabanı Bağlantısı (PDO)
    // =============================================
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    // =============================================
    // 2. Tabloları Oluştur (Veri eklenmez!)
    // =============================================

    // Filmler tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS movies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            title_en TEXT NOT NULL,
            year INTEGER NOT NULL,
            director TEXT NOT NULL,
            tmdb_id INTEGER,
            overview TEXT,
            image_path TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Oyun Tarihleri tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS game_dates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            movie_id INTEGER NOT NULL,
            game_date DATE NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
        )
    ");

    // Poster Oyun Tarihleri tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS poster_game_dates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            movie_id INTEGER NOT NULL,
            game_date DATE NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
        )
    ");

    // Kadro (Cast) Oyun Tarihleri tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cast_game_dates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            movie_id INTEGER NOT NULL,
            game_date DATE NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
        )
    ");

    // Görseller tablosu (her film için 6 görsel ipucu)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS movie_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            movie_id INTEGER NOT NULL,
            image_order INTEGER NOT NULL CHECK(image_order BETWEEN 1 AND 6),
            image_path TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
            UNIQUE(movie_id, image_order)
        )
    ");

    // Admin kullanıcıları tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Varsayılan admin kullanıcısı (şifre: admin123)
    $adminCount = $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    if ($adminCount == 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmtAdmin = $pdo->prepare("INSERT INTO admin_users (username, password) VALUES (:u, :p)");
        $stmtAdmin->execute([':u' => 'admin', ':p' => $hashedPassword]);
    }

    // =============================================
    // 3. Gerekli Dizinleri Oluştur
    // =============================================
    $uploadDir = __DIR__ . '/assets/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // =============================================
    // 4. Sonuç Raporu
    // =============================================
    $movieCount = $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn();
    $dateCount  = $pdo->query("SELECT COUNT(*) FROM game_dates")->fetchColumn();
    $imageCount = $pdo->query("SELECT COUNT(*) FROM movie_images")->fetchColumn();
    $adminCount = $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();

} catch (PDOException $e) {
    die("<h1>Veritabanı Hatası</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineFrame - Kurulum</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #0a0a0f 0%, #1a1a2e 100%);
            color: #e0e0e0; min-height: 100vh;
            display: flex; align-items: center; justify-content: center; padding: 2rem;
        }
        .setup-container {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px; padding: 2.5rem; max-width: 600px; width: 100%;
            backdrop-filter: blur(10px);
        }
        h1 { font-size: 2rem; margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #e2b340, #f0d060);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .status-item {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.75rem 1rem; background: rgba(255,255,255,0.03);
            border-radius: 8px; margin-bottom: 0.5rem; border-left: 3px solid #e2b340;
        }
        .summary { margin-top: 1.5rem; padding: 1rem;
            background: rgba(226,179,64,0.1); border: 1px solid rgba(226,179,64,0.3); border-radius: 8px;
        }
        .summary p { margin: 0.3rem 0; }
        .info { margin-top: 1rem; padding: 0.75rem 1rem;
            background: rgba(52,152,219,0.1); border: 1px solid rgba(52,152,219,0.3);
            border-radius: 8px; color: #3498db; font-size: 0.9rem;
        }
        .btn-row { display: flex; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap; }
        .btn-start, .btn-admin {
            display: inline-block; padding: 0.75rem 2rem; border-radius: 8px;
            font-weight: 600; font-size: 1rem; text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-start { background: linear-gradient(135deg, #e2b340, #d4a030); color: #0a0a0f; }
        .btn-admin { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); color: #e0e0e0; }
        .btn-start:hover, .btn-admin:hover {
            transform: translateY(-2px); box-shadow: 0 4px 20px rgba(226,179,64,0.3);
        }
        .warning { margin-top: 1rem; padding: 0.75rem 1rem;
            background: rgba(231,76,60,0.1); border: 1px solid rgba(231,76,60,0.3);
            border-radius: 8px; color: #e74c3c; font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>🎬 CineFrame Kurulum</h1>

        <?php if ($dbExists): ?>
            <div class="warning">⚠️ Veritabanı zaten mevcut. Tablolar tekrar oluşturulmadı.</div>
        <?php endif; ?>

        <div class="status-item">
            <span>✅</span><span>Veritabanı oluşturuldu (database.sqlite)</span>
        </div>
        <div class="status-item">
            <span>✅</span><span><strong>movies</strong> tablosu hazır</span>
        </div>
        <div class="status-item">
            <span>✅</span><span><strong>game_dates</strong> tablosu hazır</span>
        </div>
        <div class="status-item">
            <span>✅</span><span><strong>movie_images</strong> tablosu hazır</span>
        </div>
        <div class="status-item">
            <span>✅</span><span><strong>admin_users</strong> tablosu hazır</span>
        </div>

        <div class="summary">
            <p>🎞️ <strong><?= $movieCount ?></strong> film</p>
            <p>📅 <strong><?= $dateCount ?></strong> oyun tarihi</p>
            <p>🖼️ <strong><?= $imageCount ?></strong> görsel kaydı</p>
            <p>👤 <strong><?= $adminCount ?></strong> admin kullanıcısı (admin / admin123)</p>
        </div>

        <div class="info">
            ℹ️ Veritabanı boş olarak oluşturuldu. Film eklemek, görsel yüklemek ve oyun tarihi atamak için <strong>Admin Paneli</strong>'ni kullanın.
        </div>

        <div class="btn-row">
            <a href="index.php" class="btn-start">🎮 Oyuna Git</a>
            <a href="admin/admin.php" class="btn-admin">⚙️ Admin Paneli</a>
        </div>
    </div>
</body>
</html>
