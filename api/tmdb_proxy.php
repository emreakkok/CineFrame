<?php
/**
 * CineFrame — TMDB API Proxy
 * ============================
 * TMDB API anahtarını sunucu tarafında tutar, front-end'e hiç göndermez.
 * JS dosyaları bu proxy üzerinden TMDB'ye istek atar.
 * 
 * Endpoint'ler:
 *   ?action=search&query=Film+Adı      → Film arama
 *   ?action=detail&id=12345            → Film detayı (TR)
 *   ?action=detail_en&id=12345         → Film detayı (EN)
 *   ?action=credits&id=12345           → Film ekibi (yönetmen)
 */

header('Content-Type: application/json; charset=utf-8');

// Config dosyasından API anahtarını al
$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'config.php bulunamadı. Lütfen config.example.php dosyasını config.php olarak kopyalayıp TMDB API anahtarınızı girin.']);
    exit;
}
require_once $configPath;

if (!defined('TMDB_API_KEY') || TMDB_API_KEY === 'BURAYA_TMDB_API_KEY_YAZIN') {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'TMDB API anahtarı ayarlanmamış. config.php dosyasını kontrol edin.']);
    exit;
}

$action = $_GET['action'] ?? '';
$apiKey = TMDB_API_KEY;
$baseUrl = 'https://api.themoviedb.org/3';

switch ($action) {
    case 'search':
        // Film arama
        $query = $_GET['query'] ?? '';
        if (strlen($query) < 2) {
            echo json_encode(['results' => []]);
            exit;
        }
        $url = "{$baseUrl}/search/movie?api_key={$apiKey}&query=" . urlencode($query) . "&language=tr-TR&page=1";
        break;

    case 'detail':
        // Film detayı (Türkçe)
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['error' => 'Geçersiz ID']); exit; }
        $url = "{$baseUrl}/movie/{$id}?api_key={$apiKey}&language=tr-TR";
        break;

    case 'detail_en':
        // Film detayı (İngilizce — orijinal başlık için)
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['error' => 'Geçersiz ID']); exit; }
        $url = "{$baseUrl}/movie/{$id}?api_key={$apiKey}&language=en-US";
        break;

    case 'credits':
        // Film ekibi (yönetmen bilgisi için)
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['error' => 'Geçersiz ID']); exit; }
        $url = "{$baseUrl}/movie/{$id}/credits?api_key={$apiKey}";
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Geçersiz action. Geçerli: search, detail, detail_en, credits']);
        exit;
}

// TMDB API'ye istek at ve yanıtı ilet
$response = @file_get_contents($url);
if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'TMDB API isteği başarısız.']);
    exit;
}

// Yanıtı doğrudan ilet
echo $response;
