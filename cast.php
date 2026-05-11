<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CineFrame - Her gün yeni bir film tahmin et! Kadro ipuçlarıyla filmi bul.">
    <meta name="keywords" content="film tahmin, günlük oyun, sinema, film quiz, CineFrame, Kadro, Cast">
    <meta name="author" content="CineFrame">
    <title>CineFrame — Günlük Kadro (Cast) Tahmin Oyunu</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Stil dosyaları -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/cast.css">
</head>
<body>

    <!-- ============================================ -->
    <!-- HEADER -->
    <!-- ============================================ -->
    <header id="main-header">
        <div class="header-inner">
            <div class="logo" id="logo">
                <span class="logo-icon">🎭</span>
                <h1>CineFrame <span class="mode-badge">Kadro</span></h1>
            </div>
            <nav class="header-nav">
                <a href="index.php" class="nav-btn mode-switch" title="Frame Moduna Geç">🖼️ Frame Modu</a>
                <a href="poster.php" class="nav-btn mode-switch" title="Poster Moduna Geç">🎟️ Poster Modu</a>
                <button id="btn-how-to-play" class="nav-btn" title="Nasıl Oynanır?">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </button>
                <button id="btn-archive" class="nav-btn" title="Arşiv">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </button>
            </nav>
        </div>
    </header>

    <!-- ============================================ -->
    <!-- ANA İÇERİK -->
    <!-- ============================================ -->
    <main id="game-container">

        <!-- Oyun Tarih Gösterimi -->
        <div id="game-date-display" class="game-date"></div>

        <!-- ============================================ -->
        <!-- OYUN YOK DURUMU (Empty State) -->
        <!-- ============================================ -->
        <div id="empty-state" class="empty-state hidden">
            <div class="empty-state-card">
                <div class="empty-state-icon">🎭</div>
                <h2 id="empty-state-title" class="empty-state-title">Henüz bir Kadro oyunu hazırlanmadı</h2>
                <p id="empty-state-message" class="empty-state-message">
                    Bugün için atanmış bir Kadro bulmacası bulunmuyor. Lütfen daha sonra tekrar deneyin!
                </p>
                <div class="empty-state-strip">
                    <span></span><span></span><span></span><span></span><span></span>
                    <span></span><span></span><span></span><span></span><span></span>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- İPUÇLARI (Clues) -->
        <!-- ============================================ -->
        <section id="cast-clues-container" class="cast-clues-container hidden">
            <div class="cast-clues-header">
                <h2>Kadro İpuçları</h2>
                <p>Her yanlış tahmin veya geçme işleminde yeni bir ipucu açılır.</p>
            </div>
            <div class="cast-clue-item" id="clue-1">
                <div class="clue-label">Vizyon Yılı ve Tür</div>
                <div class="clue-value skeleton">????</div>
            </div>
            <div class="cast-clue-item" id="clue-2">
                <div class="clue-label">Yönetmen</div>
                <div class="clue-value skeleton">????</div>
            </div>
            <div class="cast-clue-item" id="clue-3">
                <div class="clue-label">Yardımcı Oyuncu</div>
                <div class="clue-value skeleton">????</div>
            </div>
            <div class="cast-clue-item" id="clue-4">
                <div class="clue-label">Yardımcı Oyuncu</div>
                <div class="clue-value skeleton">????</div>
            </div>
            <div class="cast-clue-item" id="clue-5">
                <div class="clue-label">Başrol Oyuncusu</div>
                <div class="clue-value skeleton">????</div>
            </div>
        </section>

        <!-- Tahmin Hakkı Göstergesi -->
        <div id="guess-counter" class="guess-counter hidden">
            <div id="guess-dots" class="guess-dots">
                <!-- JS tarafından 6 dot oluşturulacak -->
            </div>
            <span id="guess-text" class="guess-text"></span>
        </div>

        <!-- Arama / Tahmin Girişi -->
        <div id="search-container" class="search-container hidden">
            <div class="search-wrapper">
                <div class="search-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </div>
                <input type="text" 
                       id="search-input" 
                       class="search-input" 
                       placeholder="Film adı yazarak arayın..." 
                       autocomplete="off"
                       aria-label="Film arama">
                <button id="btn-skip" class="btn-skip" title="Bu turu geç">
                    Geç
                </button>
            </div>
            <!-- TMDB Autocomplete Listesi -->
            <ul id="autocomplete-list" class="autocomplete-list" role="listbox"></ul>
        </div>

        <!-- Tahmin Geçmişi -->
        <section id="guess-history" class="guess-history hidden">
            <ul id="guess-list" class="guess-list"></ul>
        </section>

    </main>

    <!-- ============================================ -->
    <!-- SONUÇ MODAL -->
    <!-- ============================================ -->
    <div id="result-modal" class="modal-overlay hidden" role="dialog" aria-modal="true">
        <div class="modal-content result-modal-content">
            <div id="result-animation" class="result-animation"></div>
            <div id="result-icon" class="result-icon"></div>
            <h2 id="result-title" class="result-title"></h2>
            <div id="result-movie-info" class="result-movie-info">
                <img id="result-movie-image" class="result-movie-image" src="" alt="">
                <div class="result-movie-details">
                    <h3 id="result-movie-title"></h3>
                    <p id="result-movie-year" class="result-movie-year"></p>
                    <p id="result-movie-director" class="result-movie-director"></p>
                    <p id="result-movie-overview" class="result-movie-overview"></p>
                </div>
            </div>
            <div id="result-stats" class="result-stats">
                <div class="stat-item">
                    <span class="stat-value" id="stat-guesses">0</span>
                    <span class="stat-label">Tahmin</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" id="stat-images">0</span>
                    <span class="stat-label">İpucu</span>
                </div>
            </div>
            <button id="btn-close-result" class="btn-primary">Tamam</button>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- ARŞİV MODAL -->
    <!-- ============================================ -->
    <div id="archive-modal" class="modal-overlay hidden" role="dialog" aria-modal="true">
        <div class="modal-content archive-modal-content">
            <div class="modal-header">
                <h2>📅 Arşiv — Kadro Modu</h2>
                <button id="btn-close-archive" class="modal-close" aria-label="Kapat">&times;</button>
            </div>
            <div id="archive-list" class="archive-list">
                <!-- JS tarafından doldurulacak -->
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- NASIL OYNANIR MODAL -->
    <!-- ============================================ -->
    <div id="howto-modal" class="modal-overlay hidden" role="dialog" aria-modal="true">
        <div class="modal-content howto-modal-content">
            <div class="modal-header">
                <h2>🎬 Nasıl Oynanır? (Kadro Modu)</h2>
                <button id="btn-close-howto" class="modal-close" aria-label="Kapat">&times;</button>
            </div>
            <div class="howto-body">
                <div class="howto-step">
                    <span class="step-number">1</span>
                    <p>Her gün yeni bir filmin Kadro / Ekip bulmacası yayınlanır. Bu modda görsel ipucu yoktur.</p>
                </div>
                <div class="howto-step">
                    <span class="step-number">2</span>
                    <p>İlk aşamada sadece arama çubuğu açıktır. Tahminde bulunun veya geçin.</p>
                </div>
                <div class="howto-step">
                    <span class="step-number">3</span>
                    <p>Her yanlış tahminde yeni bir bilgi (Yıl/Tür, Yönetmen, Yardımcı Oyuncular, Başrol) açılır.</p>
                </div>
                <div class="howto-step">
                    <span class="step-number">4</span>
                    <p>Toplam <strong>6 tahmin hakkınız</strong> var. İpuçlarını kullanarak filmi bulun.</p>
                </div>
                <div class="howto-step">
                    <span class="step-number">5</span>
                    <p>Geçmiş Kadro bulmacalarını <strong>Arşiv</strong> bölümünden oynayabilirsiniz.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- FOOTER -->
    <!-- ============================================ -->
    <footer id="main-footer">
        <p>CineFrame &copy; 2026 — Film verileri 
            <a href="https://www.themoviedb.org/" target="_blank" rel="noopener">TMDB</a> tarafından sağlanmaktadır.
        </p>
    </footer>

    <!-- JavaScript dosyası -->
    <script src="js/cast.js"></script>
</body>
</html>
