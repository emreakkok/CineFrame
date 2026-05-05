<?php
/**
 * CineFrame - Poster API Endpoint'leri
 * =====================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

error_reporting(E_ALL);
ini_set('display_errors', 0);

$dbPath = __DIR__ . '/../database.sqlite';

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Veritabanı bulunamadı.']);
    exit;
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Veritabanı bağlantı hatası.']);
    exit;
}

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
        echo json_encode(['success' => false, 'error' => 'Geçersiz action parametresi.']);
}

function handleToday(PDO $pdo): void
{
    $today = date('Y-m-d');
    $game = getPosterGameByDate($pdo, $today);

    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Bugün için atanmış bir Poster oyunu bulunamadı.']);
        return;
    }

    echo json_encode(['success' => true, 'data' => $game]);
}

function handleGame(PDO $pdo): void
{
    $date = $_GET['date'] ?? '';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Geçersiz tarih formatı.']);
        return;
    }

    $game = getPosterGameByDate($pdo, $date);

    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Bu tarih için atanmış bir Poster oyunu bulunamadı.']);
        return;
    }

    echo json_encode(['success' => true, 'data' => $game]);
}

function handleCheck(PDO $pdo): void
{
    $movieId = intval($_GET['movie_id'] ?? 0);
    $guess   = trim($_GET['guess'] ?? '');

    if ($movieId <= 0 || empty($guess)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'movie_id ve guess gerekli.']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id, title, title_en FROM movies WHERE id = :id");
    $stmt->execute([':id' => $movieId]);
    $movie = $stmt->fetch();

    if (!$movie) {
        echo json_encode(['success' => false, 'error' => 'Film bulunamadı.']);
        return;
    }

    $guessLower   = mb_strtolower($guess, 'UTF-8');
    $titleLower   = mb_strtolower($movie['title'], 'UTF-8');
    $titleEnLower = mb_strtolower($movie['title_en'], 'UTF-8');

    $isCorrect = ($guessLower === $titleLower || $guessLower === $titleEnLower);

    $response = [
        'success'   => true,
        'isCorrect' => $isCorrect,
        'guess'     => $guess
    ];

    if ($isCorrect) {
        $response['movie'] = getMovieDetails($pdo, $movieId);
    }

    echo json_encode($response);
}

function handleArchive(PDO $pdo): void
{
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT gd.game_date, gd.movie_id
        FROM poster_game_dates gd
        WHERE gd.game_date <= :today
        ORDER BY gd.game_date DESC
    ");
    $stmt->execute([':today' => $today]);
    $dates = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $dates]);
}

function handleReveal(PDO $pdo): void
{
    $movieId = intval($_GET['movie_id'] ?? 0);

    if ($movieId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'movie_id gerekli.']);
        return;
    }

    $movie = getMovieDetails($pdo, $movieId);

    if (!$movie) {
        echo json_encode(['success' => false, 'error' => 'Film bulunamadı.']);
        return;
    }

    echo json_encode(['success' => true, 'data' => $movie]);
}

function getPosterGameByDate(PDO $pdo, string $date): ?array
{
    $stmt = $pdo->prepare("
        SELECT gd.movie_id, gd.game_date, m.tmdb_id
        FROM poster_game_dates gd
        JOIN movies m ON gd.movie_id = m.id
        WHERE gd.game_date = :date
    ");
    $stmt->execute([':date' => $date]);
    $gameDate = $stmt->fetch();

    if (!$gameDate) {
        return null;
    }

    return [
        'movieId'  => (int)$gameDate['movie_id'],
        'gameDate' => $gameDate['game_date'],
        'tmdbId'   => (int)$gameDate['tmdb_id']
    ];
}

function getMovieDetails(PDO $pdo, int $movieId): ?array
{
    $stmt = $pdo->prepare("SELECT id, title, title_en, year, director, overview, image_path FROM movies WHERE id = :id");
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
