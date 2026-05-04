<?php
/**
 * CineFrame Admin — İşlem Endpoint'leri
 * =======================================
 * Logout, film ekleme, görsel yükleme, tarih atama/silme işlemleri.
 */
session_start();

// Giriş kontrolü (logout hariç)
$action = $_GET['action'] ?? '';

if ($action !== 'logout' && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    header('Location: admin.php');
    exit;
}

// Veritabanı bağlantısı
$dbPath = __DIR__ . '/../database.sqlite';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

// =============================================
// Action Router
// =============================================
switch ($action) {
    case 'logout':
        handleLogout();
        break;
    case 'add_movie':
        handleAddMovie($pdo);
        break;
    case 'upload_images':
        handleUploadImages($pdo);
        break;
    case 'assign_date':
        handleAssignDate($pdo);
        break;
    case 'delete_date':
        handleDeleteDate($pdo);
        break;
    default:
        header('Location: dashboard.php');
        exit;
}

// =============================================
// Handler Fonksiyonları
// =============================================

/** Çıkış yap */
function handleLogout(): void
{
    session_destroy();
    header('Location: admin.php');
    exit;
}

/**
 * TMDB'den seçilen filmi veritabanına ekle.
 * AJAX isteği olarak JSON döner.
 */
function handleAddMovie(PDO $pdo): void
{
    header('Content-Type: application/json; charset=utf-8');

    // POST verilerini al
    $input = json_decode(file_get_contents('php://input'), true);

    $title    = trim($input['title'] ?? '');
    $titleEn  = trim($input['title_en'] ?? '');
    $year     = intval($input['year'] ?? 0);
    $director = trim($input['director'] ?? '');
    $tmdbId   = intval($input['tmdb_id'] ?? 0);
    $overview = trim($input['overview'] ?? '');

    if (empty($title) || empty($titleEn) || $year <= 0) {
        echo json_encode(['success' => false, 'error' => 'Eksik film bilgisi.']);
        return;
    }

    // Aynı TMDB ID zaten var mı kontrol et
    if ($tmdbId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM movies WHERE tmdb_id = :tid");
        $stmt->execute([':tid' => $tmdbId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Bu film zaten veritabanında mevcut.']);
            return;
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO movies (title, title_en, year, director, tmdb_id, overview, image_path)
            VALUES (:title, :title_en, :year, :director, :tmdb_id, :overview, '')
        ");
        $stmt->execute([
            ':title'    => $title,
            ':title_en' => $titleEn,
            ':year'     => $year,
            ':director' => $director,
            ':tmdb_id'  => $tmdbId,
            ':overview' => $overview
        ]);

        $movieId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'movieId' => $movieId,
            'message' => "'$title' başarıyla eklendi."
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Veritabanı hatası: ' . $e->getMessage()]);
    }
}

/**
 * Seçilen film için 6 adet görsel yükle.
 * Görseller /assets/uploads/ klasörüne kaydedilir.
 */
function handleUploadImages(PDO $pdo): void
{
    $movieId = intval($_POST['movie_id'] ?? 0);

    if ($movieId <= 0) {
        $_SESSION['flash_error'] = 'Geçersiz film seçimi.';
        header('Location: dashboard.php');
        exit;
    }

    // Upload dizini
    $uploadDir = __DIR__ . '/../assets/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uploadedCount = 0;
    $errors = [];

    // 6 görsel için döngü
    for ($i = 1; $i <= 6; $i++) {
        if (!isset($_FILES['images']['name'][$i]) || $_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
            continue; // Bu slot için dosya yüklenmemiş, atla
        }

        $tmpName  = $_FILES['images']['tmp_name'][$i];
        $origName = $_FILES['images']['name'][$i];
        $fileSize = $_FILES['images']['size'][$i];
        $fileType = $_FILES['images']['type'][$i];

        // Dosya tipi kontrolü
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Görsel {$i}: Geçersiz dosya tipi ({$fileType}).";
            continue;
        }

        // Dosya boyutu kontrolü (5MB max)
        if ($fileSize > 5 * 1024 * 1024) {
            $errors[] = "Görsel {$i}: Dosya boyutu 5MB'ı aşıyor.";
            continue;
        }

        // Benzersiz dosya adı oluştur
        $ext = pathinfo($origName, PATHINFO_EXTENSION);
        $fileName = "movie_{$movieId}_frame_{$i}_" . time() . ".{$ext}";
        $destPath = $uploadDir . $fileName;
        $dbPath   = 'assets/uploads/' . $fileName;

        // Dosyayı taşı
        if (move_uploaded_file($tmpName, $destPath)) {
            // Veritabanına kaydet (var olanı güncelle veya yeni ekle)
            $stmt = $pdo->prepare("
                INSERT INTO movie_images (movie_id, image_order, image_path)
                VALUES (:mid, :ord, :path)
                ON CONFLICT(movie_id, image_order) DO UPDATE SET image_path = :path2
            ");
            $stmt->execute([
                ':mid'   => $movieId,
                ':ord'   => $i,
                ':path'  => $dbPath,
                ':path2' => $dbPath
            ]);

            // İlk görseli film ana görseli olarak da kaydet
            if ($i === 1) {
                $pdo->prepare("UPDATE movies SET image_path = :p WHERE id = :id")
                    ->execute([':p' => $dbPath, ':id' => $movieId]);
            }

            $uploadedCount++;
        } else {
            $errors[] = "Görsel {$i}: Dosya yüklenemedi.";
        }
    }

    if ($uploadedCount > 0) {
        $_SESSION['flash_success'] = "{$uploadedCount} görsel başarıyla yüklendi.";
    }
    if (!empty($errors)) {
        $_SESSION['flash_error'] = implode(' | ', $errors);
    }
    if ($uploadedCount === 0 && empty($errors)) {
        $_SESSION['flash_error'] = 'Yüklenecek görsel seçilmedi.';
    }

    header('Location: dashboard.php');
    exit;
}

/**
 * Filme oyun tarihi ata.
 */
function handleAssignDate(PDO $pdo): void
{
    $movieId  = intval($_POST['movie_id'] ?? 0);
    $gameDate = trim($_POST['game_date'] ?? '');

    if ($movieId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $gameDate)) {
        $_SESSION['flash_error'] = 'Geçersiz film veya tarih.';
        header('Location: dashboard.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO game_dates (movie_id, game_date)
            VALUES (:mid, :date)
        ");
        $stmt->execute([':mid' => $movieId, ':date' => $gameDate]);

        $_SESSION['flash_success'] = "Tarih başarıyla atandı: {$gameDate}";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            $_SESSION['flash_error'] = "Bu tarih ({$gameDate}) zaten başka bir filme atanmış.";
        } else {
            $_SESSION['flash_error'] = 'Veritabanı hatası: ' . $e->getMessage();
        }
    }

    header('Location: dashboard.php');
    exit;
}

/**
 * Oyun tarihi atamasını sil.
 */
function handleDeleteDate(PDO $pdo): void
{
    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        $_SESSION['flash_error'] = 'Geçersiz tarih ID.';
        header('Location: dashboard.php');
        exit;
    }

    try {
        $pdo->prepare("DELETE FROM game_dates WHERE id = :id")->execute([':id' => $id]);
        $_SESSION['flash_success'] = 'Tarih ataması silindi.';
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = 'Silme hatası: ' . $e->getMessage();
    }

    header('Location: dashboard.php');
    exit;
}
