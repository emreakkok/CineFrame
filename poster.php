<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CineFrame Poster Modu - Film afişinden filmi tahmin et!">
    <title>CineFrame — Poster Modu</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/poster.css">
</head>
<body>

    <header id="main-header">
        <div class="header-inner">
            <div class="logo" id="logo">
                <span class="logo-icon">🎬</span>
                <h1>CineFrame <span style="font-size: 0.8rem; background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 4px; vertical-align: middle;">POSTER</span></h1>
            </div>
            <nav class="header-nav">
                <a href="index.php" class="nav-btn mode-switch" title="Frame Moduna Geç">🖼️ Frame Modu</a>
                <a href="cast.php" class="nav-btn mode-switch" title="Kadro Moduna Geç">🎭 Kadro Modu</a>
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

    <main id="game-container">
        <div id="game-date-display" class="game-date"></div>

        <div id="empty-state" class="empty-state hidden">
            <div class="empty-state-card">
                <div class="empty-state-icon">🖼️</div>
                <h2 id="empty-state-title" class="empty-state-title">Henüz bir oyun hazırlanmadı</h2>
                <p id="empty-state-message" class="empty-state-message">
                    Bugün için atanmış bir Poster bulmacası bulunmuyor. Lütfen daha sonra tekrar deneyin!
                </p>
            </div>
        </div>

        <!-- Poster Alanı -->
        <section id="poster-area" class="poster-area" aria-label="Film Posteri İpucu">
            <div class="poster-wrapper" id="poster-wrapper">
                <!-- Poster görseli TMDB'den yüklenecek -->
                <img id="poster-image" class="poster-image" src="" alt="Film posteri">
                
                <!-- Pikselleme / Gizleme Katmanı -->
                <div id="poster-overlay" class="poster-overlay blur-level-1"></div>
            </div>
        </section>

        <!-- Tahmin Hakkı Göstergesi -->
        <div id="guess-counter" class="guess-counter">
            <div id="guess-dots" class="guess-dots"></div>
            <span id="guess-text" class="guess-text"></span>
        </div>

        <div id="search-container" class="search-container">
            <div class="search-wrapper">
                <div class="search-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </div>
                <input type="text" id="search-input" class="search-input" placeholder="Film adı yazarak arayın..." autocomplete="off">
                <button id="btn-skip" class="btn-skip" title="Bu turu geç">Geç</button>
            </div>
            <ul id="autocomplete-list" class="autocomplete-list" role="listbox"></ul>
        </div>

        <section id="guess-history" class="guess-history">
            <ul id="guess-list" class="guess-list"></ul>
        </section>

    </main>

    <!-- SONUÇ MODAL -->
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
            <button id="btn-close-result" class="btn-primary">Tamam</button>
        </div>
    </div>

    <!-- ARŞİV MODAL -->
    <div id="archive-modal" class="modal-overlay hidden" role="dialog" aria-modal="true">
        <div class="modal-content archive-modal-content">
            <div class="modal-header">
                <h2>📅 Poster Arşivi</h2>
                <button id="btn-close-archive" class="modal-close">&times;</button>
            </div>
            <div id="archive-list" class="archive-list"></div>
        </div>
    </div>

    <!-- NASIL OYNANIR MODAL -->
    <div id="howto-modal" class="modal-overlay hidden" role="dialog" aria-modal="true">
        <div class="modal-content howto-modal-content">
            <div class="modal-header">
                <h2>🎬 Poster Modu Nasıl Oynanır?</h2>
                <button id="btn-close-howto" class="modal-close">&times;</button>
            </div>
            <div class="howto-body">
                <p>Posteri bulanık veya gizli olan filmi tahmin etmeye çalışın. Her yanlış tahminde poster biraz daha netleşir / açılır!</p>
            </div>
        </div>
    </div>

    <footer id="main-footer">
        <p>CineFrame &copy; 2026 — Film verileri <a href="https://www.themoviedb.org/" target="_blank">TMDB</a> tarafından sağlanmaktadır.</p>
    </footer>

    <script src="js/poster.js"></script>
</body>
</html>
