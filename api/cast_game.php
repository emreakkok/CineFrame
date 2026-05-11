<?php
/**
 * CineFrame - Kadro (Cast) API Endpoint'leri
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
    $game = getCastGameByDate($pdo, $today);

    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Bugün için atanmış bir Kadro oyunu bulunamadı.']);
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

    $game = getCastGameByDate($pdo, $date);

    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Bu tarih için atanmış bir Kadro oyunu bulunamadı.']);
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
        FROM cast_game_dates gd
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

function getCastGameByDate(PDO $pdo, string $date): ?array
{
    // MİMARİ KARAR: Kadro ipuçları normalde veritabanından çekilir.
    // Ancak eski eklenmiş filmlerde bu veriler yoksa (NULL ise), anlık olarak TMDB'den çekilip
    // veritabanı GÜNCELLENİR. Böylece eski filmler de Kadro Modunda sorunsuz çalışır.
    $stmt = $pdo->prepare("
        SELECT gd.movie_id, gd.game_date, m.year, m.genre, m.director, m.cast_lead, m.cast_second, m.cast_third, m.runtime, m.tmdb_id
        FROM cast_game_dates gd
        JOIN movies m ON gd.movie_id = m.id
        WHERE gd.game_date = :date
    ");
    $stmt->execute([':date' => $date]);
    $gameDate = $stmt->fetch();

    if (!$gameDate) {
        return null;
    }

    // Eski filmler için eksik veri kontrolü ve TMDB'den otomatik çekme
    if (
        empty($gameDate['genre']) ||
        empty($gameDate['director']) ||
        empty($gameDate['cast_lead']) ||
        empty($gameDate['cast_second']) ||
        empty($gameDate['cast_third'])
    ) {
        $tmdbId = $gameDate['tmdb_id'];
        $configPath = __DIR__ . '/../config.php';
        if ($tmdbId && file_exists($configPath)) {
            require_once $configPath;
            if (defined('TMDB_API_KEY') && TMDB_API_KEY !== 'BURAYA_TMDB_API_KEY_YAZIN') {
                $apiKey = TMDB_API_KEY;
                $urlDetail = "https://api.themoviedb.org/3/movie/{$tmdbId}?api_key={$apiKey}&language=tr-TR";
                $urlCredits = "https://api.themoviedb.org/3/movie/{$tmdbId}/credits?api_key={$apiKey}";
                
                $detailRes = @file_get_contents($urlDetail);
                $creditsRes = @file_get_contents($urlCredits);

                if ($detailRes && $creditsRes) {
                    $detail = json_decode($detailRes, true);
                    $credits = json_decode($creditsRes, true);

                    $genres = $detail['genres'] ?? [];
                    $genre = (!empty($genres) && count($genres) > 0) ? implode(', ', array_column($genres, 'name')) : '';
                    $directorObj = null;
                    if (isset($credits['crew'])) {
                        foreach ($credits['crew'] as $c) {
                            if ($c['job'] === 'Director') {
                                $directorObj = $c;
                                break;
                            }
                        }
                    }
                    $director = $directorObj ? $directorObj['name'] : '';
                    
                    $cast = $credits['cast'] ?? [];
                    $castLead = $cast[0]['name'] ?? '';
                    $castSecond = $cast[1]['name'] ?? '';
                    $castThird = $cast[2]['name'] ?? '';

                    $runtime = isset($detail['runtime']) ? (int)$detail['runtime'] : 0;
                    $imagePath = isset($detail['poster_path']) ? "https://image.tmdb.org/t/p/w500{$detail['poster_path']}" : '';

                    // Veritabanını güncelle
                    $updateStmt = $pdo->prepare("
                        UPDATE movies 
                        SET genre = :g, director = :d, cast_lead = :c1, cast_second = :c2, cast_third = :c3, runtime = :r, image_path = :ip
                        WHERE id = :id
                    ");
                    $updateStmt->execute([
                        ':g' => $genre,
                        ':d' => $director,
                        ':c1' => $castLead,
                        ':c2' => $castSecond,
                        ':c3' => $castThird,
                        ':r' => $runtime,
                        ':ip' => $imagePath,
                        ':id' => $gameDate['movie_id']
                    ]);

                    // Güncel verileri diziye yansıt
                    $gameDate['genre'] = $genre;
                    $gameDate['director'] = $director;
                    $gameDate['cast_lead'] = $castLead;
                    $gameDate['cast_second'] = $castSecond;
                    $gameDate['cast_third'] = $castThird;
                    $gameDate['runtime'] = $runtime;
                }
            }
        }
    }

    return [
        'movieId'  => (int)$gameDate['movie_id'],
        'gameDate' => $gameDate['game_date'],
        'clues'    => [
            'runtime'     => $gameDate['runtime'] ? $gameDate['runtime'] . ' dk' : 'Bilinmiyor',
            'year_genre'  => $gameDate['year'] . ' • ' . ($gameDate['genre'] ?: 'Bilinmiyor'),
            'director'    => $gameDate['director'] ?: 'Bilinmiyor',
            'cast_third'  => $gameDate['cast_third'] ?: 'Bilinmiyor',
            'cast_second' => $gameDate['cast_second'] ?: 'Bilinmiyor',
            'cast_lead'   => $gameDate['cast_lead'] ?: 'Bilinmiyor'
        ]
    ];
}

function getMovieDetails(PDO $pdo, int $movieId): ?array
{
    $stmt = $pdo->prepare("SELECT id, title, title_en, year, director, overview, image_path, runtime FROM movies WHERE id = :id");
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
        'imagePath' => $movie['image_path'],
        'runtime'   => isset($movie['runtime']) ? (int)$movie['runtime'] : 0
    ];
}
