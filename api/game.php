<?php
/**
 * CineFrame - API Endpoint'leri
 * ==============================
 * Tüm oyun verilerini JSON olarak dönen API.
 * 
 * Endpoint'ler:
 *   ?action=today                     → Bugünün oyun verisi
 *   ?action=game&date=YYYY-MM-DD      → Belirli tarihin oyun verisi
 *   ?action=check&movie_id=X&guess=Y  → Tahmin kontrolü
 *   ?action=archive                   → Geçmiş oyun listesi
 *   ?action=reveal&movie_id=X         → Film detayları (oyun bitince)
 */

// CORS ve JSON header ayarları
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Hata raporlama (production'da kapatılmalı)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// =============================================
// Veritabanı Bağlantısı
// =============================================
$dbPath = __DIR__ . '/../database.sqlite';

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Veritabanı bulunamadı. Lütfen önce setup.php dosyasını çalıştırın.'
    ]);
    exit;
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Veritabanı bağlantı hatası.'
    ]);
    exit;
}

// =============================================
// Router: action parametresine göre yönlendir
// =============================================
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'today':
        handleToday($pdo);
        break;
    case 'game':
        handleGame($pdo);
        break;
    case 'check':
        handleCheck($pdo);
        break;
    case 'archive':
        handleArchive($pdo);
        break;
    case 'reveal':
        handleReveal($pdo);
        break;
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Geçersiz action parametresi. Geçerli: today, game, check, archive, reveal'
        ]);
}

// =============================================
// Handler Fonksiyonları
// =============================================

/**
 * Bugünün oyun verisini döner.
 * Film ID, görsel yolları ve tarih bilgisi içerir.
 * Dikkat: Film adı veya cevap asla gönderilmez!
 */
function handleToday(PDO $pdo): void
{
    $today = date('Y-m-d');
    $game = getGameByDate($pdo, $today);

    if (!$game) {
        echo json_encode([
            'success' => false,
            'error'   => 'Bugün için atanmış bir oyun bulunamadı.'
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'data'    => $game
    ]);
}

/**
 * Belirli bir tarihin oyun verisini döner.
 * Arşivden geçmiş oyunları oynamak için kullanılır.
 */
function handleGame(PDO $pdo): void
{
    $date = $_GET['date'] ?? '';

    // Tarih formatı doğrulama (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Geçersiz tarih formatı. YYYY-MM-DD formatında olmalı.'
        ]);
        return;
    }

    $game = getGameByDate($pdo, $date);

    if (!$game) {
        echo json_encode([
            'success' => false,
            'error'   => 'Bu tarih için atanmış bir oyun bulunamadı.'
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'data'    => $game
    ]);
}

/**
 * Kullanıcının tahminini kontrol eder.
 * Film adı sunucu tarafında doğrulanır — güvenlik için.
 */
function handleCheck(PDO $pdo): void
{
    $movieId = intval($_GET['movie_id'] ?? 0);
    $guess   = trim($_GET['guess'] ?? '');

    if ($movieId <= 0 || empty($guess)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'movie_id ve guess parametreleri gerekli.'
        ]);
        return;
    }

    // Filmi veritabanından çek
    $stmt = $pdo->prepare("SELECT id, title, title_en FROM movies WHERE id = :id");
    $stmt->execute([':id' => $movieId]);
    $movie = $stmt->fetch();

    if (!$movie) {
        echo json_encode([
            'success' => false,
            'error'   => 'Film bulunamadı.'
        ]);
        return;
    }

    // Tahmin kontrolü: Türkçe veya İngilizce başlık ile eşleştir
    $guessLower   = mb_strtolower($guess, 'UTF-8');
    $titleLower   = mb_strtolower($movie['title'], 'UTF-8');
    $titleEnLower = mb_strtolower($movie['title_en'], 'UTF-8');

    $isCorrect = ($guessLower === $titleLower || $guessLower === $titleEnLower);

    $response = [
        'success'   => true,
        'isCorrect' => $isCorrect,
        'guess'     => $guess
    ];

    // Doğru bilindiyse film bilgilerini de gönder
    if ($isCorrect) {
        $response['movie'] = getMovieDetails($pdo, $movieId);
    }

    echo json_encode($response);
}

/**
 * Arşiv listesini döner.
 * Geçmiş tüm oyun tarihlerini (bugün dahil) listeler.
 */
function handleArchive(PDO $pdo): void
{
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT 
            gd.game_date,
            gd.movie_id
        FROM game_dates gd
        WHERE gd.game_date <= :today
        ORDER BY gd.game_date DESC
    ");
    $stmt->execute([':today' => $today]);
    $dates = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data'    => $dates
    ]);
}

/**
 * Oyun bittiğinde film detaylarını döner.
 * Sadece oyun tamamlandıktan sonra çağrılmalı.
 */
function handleReveal(PDO $pdo): void
{
    $movieId = intval($_GET['movie_id'] ?? 0);

    if ($movieId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'movie_id parametresi gerekli.'
        ]);
        return;
    }

    $movie = getMovieDetails($pdo, $movieId);

    if (!$movie) {
        echo json_encode([
            'success' => false,
            'error'   => 'Film bulunamadı.'
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'data'    => $movie
    ]);
}

// =============================================
// Yardımcı Fonksiyonlar
// =============================================

/**
 * Belirli bir tarih için oyun verisini çeker.
 * Film ID ve görsel yollarını döner, film adını ASLA döndürmez.
 */
function getGameByDate(PDO $pdo, string $date): ?array
{
    // Oyun tarihini ve film ID'sini çek
    $stmt = $pdo->prepare("
        SELECT gd.movie_id, gd.game_date
        FROM game_dates gd
        WHERE gd.game_date = :date
    ");
    $stmt->execute([':date' => $date]);
    $gameDate = $stmt->fetch();

    if (!$gameDate) {
        return null;
    }

    // Film görsellerini çek (sıralı)
    $stmtImg = $pdo->prepare("
        SELECT image_order, image_path
        FROM movie_images
        WHERE movie_id = :movie_id
        ORDER BY image_order ASC
    ");
    $stmtImg->execute([':movie_id' => $gameDate['movie_id']]);
    $images = $stmtImg->fetchAll();

    return [
        'movieId'  => (int)$gameDate['movie_id'],
        'gameDate' => $gameDate['game_date'],
        'images'   => $images
    ];
}

/**
 * Film detaylarını döner (oyun bittikten sonra kullanılır).
 */
function getMovieDetails(PDO $pdo, int $movieId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, title, title_en, year, director, overview, image_path
        FROM movies
        WHERE id = :id
    ");
    $stmt->execute([':id' => $movieId]);
    $movie = $stmt->fetch();

    if (!$movie) {
        return null;
    }

    return [
        'id'        => (int)$movie['id'],
        'title'     => $movie['title'],
        'titleEn'   => $movie['title_en'],
        'year'      => (int)$movie['year'],
        'director'  => $movie['director'],
        'overview'  => $movie['overview'],
        'imagePath' => $movie['image_path']
    ];
}
