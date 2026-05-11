<?php
/**
 * CineFrame Admin — Dashboard
 * =============================
 * Film ekleme (TMDB arama), tarih atama, 6 görsel yükleme arayüzü.
 */
session_start();

// Giriş kontrolü
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

// Veritabanı bağlantısı
$dbPath = __DIR__ . '/../database.sqlite';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

// Mevcut filmleri ve oyun tarihlerini çek
$movies = $pdo->query("SELECT m.*, gd.game_date FROM movies m LEFT JOIN game_dates gd ON m.id = gd.movie_id ORDER BY m.id DESC")->fetchAll();
$gameDates = $pdo->query("SELECT gd.*, m.title, m.title_en FROM game_dates gd JOIN movies m ON gd.movie_id = m.id ORDER BY gd.game_date DESC")->fetchAll();

// CREATE poster_game_dates if not exists (Migration)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS poster_game_dates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        movie_id INTEGER NOT NULL,
        game_date DATE NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
    )
");

// CREATE cast_game_dates if not exists (Migration)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS cast_game_dates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        movie_id INTEGER NOT NULL,
        game_date DATE NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
    )
");

// Add columns to movies if they don't exist
try {
    $pdo->exec("ALTER TABLE movies ADD COLUMN genre TEXT");
    $pdo->exec("ALTER TABLE movies ADD COLUMN cast_lead TEXT");
    $pdo->exec("ALTER TABLE movies ADD COLUMN cast_second TEXT");
    $pdo->exec("ALTER TABLE movies ADD COLUMN cast_third TEXT");
    $pdo->exec("ALTER TABLE movies ADD COLUMN runtime INTEGER");
} catch (PDOException $e) {
    // Columns already exist, ignore
}

$posterGameDates = $pdo->query("SELECT pgd.*, m.title, m.title_en FROM poster_game_dates pgd JOIN movies m ON pgd.movie_id = m.id ORDER BY pgd.game_date DESC")->fetchAll();
$castGameDates = $pdo->query("SELECT cgd.*, m.title, m.title_en FROM cast_game_dates cgd JOIN movies m ON cgd.movie_id = m.id ORDER BY cgd.game_date DESC")->fetchAll();

// Başarı/hata mesajları (session flash)
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineFrame Admin — Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css?v=<?= time() ?>">
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <div class="admin-header-inner">
            <div class="admin-logo">
                <span>🎬</span>
                <h1>CineFrame Admin</h1>
            </div>
            <div class="admin-nav">
                <span class="admin-user">👤 <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
                <a href="process.php?action=logout" class="btn-logout">Çıkış</a>
            </div>
        </div>
    </header>

    <main class="admin-main">

        <!-- Flash mesajları -->
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ================================== -->
        <!-- BÖLÜM 1: TMDB'den Film Ara ve Ekle -->
        <!-- ================================== -->
        <section class="admin-section">
            <h2>🔍 TMDB'den Film Ara ve Ekle</h2>
            <p class="section-desc">Film adını yazarak TMDB'den aratın, sonuçlardan birini seçerek veritabanına ekleyin.</p>

            <div class="tmdb-search-wrapper">
                <input type="text" id="admin-tmdb-search" class="admin-input" placeholder="Film adı yazın...">
                <div id="admin-tmdb-results" class="admin-tmdb-results"></div>
            </div>
        </section>

        <!-- ================================== -->
        <!-- BÖLÜM 2: Filme Görsel Yükle       -->
        <!-- ================================== -->
        <section class="admin-section">
            <h2>🖼️ Filme Görsel Yükle (6 Adet)</h2>
            <p class="section-desc">Bir film seçin ve sırasıyla 6 adet görsel yükleyin. Görseller /assets/uploads/ klasörüne kaydedilir.</p>

            <form method="POST" action="process.php?action=upload_images" enctype="multipart/form-data" class="upload-form">
                <!-- Film seçimi -->
                <div class="form-group">
                    <label for="upload-movie-id">Film Seçin</label>
                    <select id="upload-movie-id" name="movie_id" class="admin-select" required>
                        <option value="">— Film Seçin —</option>
                        <?php foreach ($movies as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?> (<?= htmlspecialchars($m['title_en']) ?>, <?= $m['year'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 6 görsel upload alanı -->
                <div class="image-upload-grid">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <div class="upload-slot">
                            <label for="image-<?= $i ?>" class="upload-label">
                                <span class="upload-number"><?= $i ?></span>
                                <div class="upload-preview" id="preview-<?= $i ?>">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <polyline points="21 15 16 10 5 21"/>
                                    </svg>
                                    <span>Görsel <?= $i ?></span>
                                </div>
                                <input type="file" id="image-<?= $i ?>" name="images[<?= $i ?>]" accept="image/*" class="upload-input">
                            </label>
                        </div>
                    <?php endfor; ?>
                </div>

                <button type="submit" class="btn-primary">📤 Görselleri Yükle</button>
            </form>
        </section>

        <section class="admin-section">
            <h2>📅 Frame Modu Tarih Atama</h2>
            <p class="section-desc">Bir film seçin ve Frame modu oynanacağı tarihi belirleyin.</p>

            <form method="POST" action="process.php?action=assign_date" class="date-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="date-movie-id">Film</label>
                        <select id="date-movie-id" name="movie_id" class="admin-select" required>
                            <option value="">— Film Seçin —</option>
                            <?php foreach ($movies as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?> (<?= $m['year'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="game-date">Tarih</label>
                        <input type="date" id="game-date" name="game_date" class="admin-input" required>
                    </div>
                    <button type="submit" class="btn-primary btn-assign">📅 Ata</button>
                </div>
            </form>
        </section>

        <!-- ================================== -->
        <!-- BÖLÜM 4: Poster Modu Tarih Atama  -->
        <!-- ================================== -->
        <section class="admin-section">
            <h2>📅 Poster Modu Tarih Atama</h2>
            <p class="section-desc">Bir film seçin ve Poster modu oynanacağı tarihi belirleyin.</p>

            <form method="POST" action="process.php?action=assign_poster_date" class="date-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="poster-date-movie-id">Film</label>
                        <select id="poster-date-movie-id" name="movie_id" class="admin-select" required>
                            <option value="">— Film Seçin —</option>
                            <?php foreach ($movies as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?> (<?= $m['year'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="poster-game-date">Tarih</label>
                        <input type="date" id="poster-game-date" name="game_date" class="admin-input" required>
                    </div>
                    <button type="submit" class="btn-primary btn-assign">📅 Poster Ata</button>
                </div>
            </form>
        </section>

        <!-- ================================== -->
        <!-- BÖLÜM 4.5: Kadro Modu Tarih Atama  -->
        <!-- ================================== -->
        <section class="admin-section">
            <h2>📅 Kadro (Cast) Modu Tarih Atama</h2>
            <p class="section-desc">Bir film seçin ve Kadro modu oynanacağı tarihi belirleyin.</p>

            <form method="POST" action="process.php?action=assign_cast_date" class="date-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="cast-date-movie-id">Film</label>
                        <select id="cast-date-movie-id" name="movie_id" class="admin-select" required>
                            <option value="">— Film Seçin —</option>
                            <?php foreach ($movies as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?> (<?= $m['year'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="cast-game-date">Tarih</label>
                        <input type="date" id="cast-game-date" name="game_date" class="admin-input" required>
                    </div>
                    <button type="submit" class="btn-primary btn-assign">📅 Kadro Ata</button>
                </div>
            </form>
        </section>

        <!-- ================================== -->
        <!-- BÖLÜM 5: Mevcut Oyun Tarihleri    -->
        <!-- ================================== -->
        <section class="admin-section">
            <h2>📋 Atanmış Frame Tarihleri</h2>
            <div class="dates-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Film (TR)</th>
                            <th>Film (EN)</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($gameDates)): ?>
                            <tr><td colspan="4" class="empty-cell">Henüz atanmış Frame tarihi yok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($gameDates as $gd): ?>
                                <tr>
                                    <td><?= htmlspecialchars($gd['game_date']) ?></td>
                                    <td><?= htmlspecialchars($gd['title']) ?></td>
                                    <td><?= htmlspecialchars($gd['title_en']) ?></td>
                                    <td>
                                        <a href="process.php?action=delete_date&id=<?= $gd['id'] ?>"
                                           class="btn-delete"
                                           onclick="return confirm('Bu Frame tarih atamasını silmek istediğinize emin misiniz?')">🗑️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <h2 style="margin-top: 2rem;">📋 Atanmış Poster Tarihleri</h2>
            <div class="dates-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Film (TR)</th>
                            <th>Film (EN)</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($posterGameDates)): ?>
                            <tr><td colspan="4" class="empty-cell">Henüz atanmış Poster tarihi yok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($posterGameDates as $pgd): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pgd['game_date']) ?></td>
                                    <td><?= htmlspecialchars($pgd['title']) ?></td>
                                    <td><?= htmlspecialchars($pgd['title_en']) ?></td>
                                    <td>
                                        <a href="process.php?action=delete_poster_date&id=<?= $pgd['id'] ?>"
                                           class="btn-delete"
                                           onclick="return confirm('Bu Poster tarih atamasını silmek istediğinize emin misiniz?')">🗑️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h2 style="margin-top: 2rem;">📋 Atanmış Kadro Tarihleri</h2>
            <div class="dates-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Film (TR)</th>
                            <th>Film (EN)</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($castGameDates)): ?>
                            <tr><td colspan="4" class="empty-cell">Henüz atanmış Kadro tarihi yok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($castGameDates as $cgd): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cgd['game_date']) ?></td>
                                    <td><?= htmlspecialchars($cgd['title']) ?></td>
                                    <td><?= htmlspecialchars($cgd['title_en']) ?></td>
                                    <td>
                                        <a href="process.php?action=delete_cast_date&id=<?= $cgd['id'] ?>"
                                           class="btn-delete"
                                           onclick="return confirm('Bu Kadro tarih atamasını silmek istediğinize emin misiniz?')">🗑️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- Admin JS -->
    <script src="js/admin.js"></script>
</body>
</html>
