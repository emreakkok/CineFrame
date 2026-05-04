/**
 * CineFrame — Ana JavaScript Dosyası
 * ====================================
 * Oyun mantığı, slider navigasyonu, TMDB autocomplete, localStorage state yönetimi
 */

// =============================================
// API Ayarları
// =============================================
// TMDB istekleri sunucu tarafındaki proxy üzerinden yapılır.
// API anahtarı JS'de TUTULMAZ — güvenlik için config.php'de saklanır.
const TMDB_PROXY_URL = 'api/tmdb_proxy.php';
const TMDB_IMG_BASE = 'https://image.tmdb.org/t/p/w92';

// =============================================
// Sabitler
// =============================================
const MAX_GUESSES = 6;
const DEBOUNCE_MS = 350;
const STORAGE_PREFIX = 'cineframe_';

// =============================================
// DOM Referansları
// =============================================
const DOM = {
    // Slider elemanları
    sliderImage: document.getElementById('slider-image'),
    sliderPrev: document.getElementById('btn-slider-prev'),
    sliderNext: document.getElementById('btn-slider-next'),
    sliderCounter: document.getElementById('slider-counter'),
    sliderDots: document.getElementById('slider-dots'),
    // Arama ve tahmin
    searchInput: document.getElementById('search-input'),
    autocomplete: document.getElementById('autocomplete-list'),
    guessList: document.getElementById('guess-list'),
    guessDots: document.getElementById('guess-dots'),
    guessText: document.getElementById('guess-text'),
    gameDate: document.getElementById('game-date-display'),
    btnSkip: document.getElementById('btn-skip'),
    // Header
    btnArchive: document.getElementById('btn-archive'),
    btnHowTo: document.getElementById('btn-how-to-play'),
    logo: document.getElementById('logo'),
    // Sonuç modal
    resultModal: document.getElementById('result-modal'),
    resultIcon: document.getElementById('result-icon'),
    resultTitle: document.getElementById('result-title'),
    resultMovieImg: document.getElementById('result-movie-image'),
    resultMovieTitle: document.getElementById('result-movie-title'),
    resultMovieYear: document.getElementById('result-movie-year'),
    resultMovieDir: document.getElementById('result-movie-director'),
    resultMovieOver: document.getElementById('result-movie-overview'),
    statGuesses: document.getElementById('stat-guesses'),
    statImages: document.getElementById('stat-images'),
    btnCloseResult: document.getElementById('btn-close-result'),
    resultAnim: document.getElementById('result-animation'),
    // Arşiv modal
    archiveModal: document.getElementById('archive-modal'),
    archiveList: document.getElementById('archive-list'),
    btnCloseArchive: document.getElementById('btn-close-archive'),
    // Nasıl oynanır modal
    howtoModal: document.getElementById('howto-modal'),
    btnCloseHowto: document.getElementById('btn-close-howto'),
    // Empty state (oyun yok durumu)
    emptyState: document.getElementById('empty-state'),
    emptyStateTitle: document.getElementById('empty-state-title'),
    emptyStateMessage: document.getElementById('empty-state-message'),
    // Oyun alanı container'ları (gizleme/gösterme için)
    imageSlider: document.getElementById('image-slider'),
    guessCounter: document.getElementById('guess-counter'),
    searchContainer: document.getElementById('search-container'),
    guessHistory: document.getElementById('guess-history')
};

// =============================================
// Oyun State'i
// =============================================
let gameState = {
    movieId: null,
    gameDate: null,
    images: [],        // Sunucudan gelen 6 görsel yolu dizisi
    guesses: [],
    revealedCount: 1,  // Kaç görsel açık (1'den başlar)
    isCompleted: false,
    isWon: false
};

// =============================================
// Slider State'i
// =============================================
/** Şu anda görüntülenen görselin index'i (0 tabanlı) */
let currentImageIndex = 0;

/** Erişilebilir en yüksek görsel index'i (0 tabanlı) — kilitli görsellere geçiş engellenir */
let maxUnlockedImageIndex = 0;

// Diğer state değişkenleri
let selectedMovie = null;    // Autocomplete'den seçilen film
let debounceTimer = null;    // TMDB arama debounce
let autocompleteIndex = -1;  // Klavye navigasyonu için

// =============================================
// Başlatma
// =============================================
document.addEventListener('DOMContentLoaded', init);

function init() {
    bindEvents();
    loadGame();
}

/** Tüm event listener'ları bağla */
function bindEvents() {
    // Slider navigasyonu
    DOM.sliderPrev.addEventListener('click', () => navigateSlider(-1));
    DOM.sliderNext.addEventListener('click', () => navigateSlider(1));

    // Arama ve autocomplete
    DOM.searchInput.addEventListener('input', onSearchInput);
    DOM.searchInput.addEventListener('keydown', onSearchKeydown);
    DOM.searchInput.addEventListener('focus', () => {
        if (DOM.autocomplete.children.length > 0) showAutocomplete();
    });
    document.addEventListener('click', (e) => {
        if (!DOM.searchInput.contains(e.target) && !DOM.autocomplete.contains(e.target)) {
            hideAutocomplete();
        }
    });

    // Geç butonu
    DOM.btnSkip.addEventListener('click', onSkip);

    // Modal butonları
    DOM.btnArchive.addEventListener('click', openArchive);
    DOM.btnCloseArchive.addEventListener('click', () => closeModal(DOM.archiveModal));
    DOM.btnHowTo.addEventListener('click', () => openModal(DOM.howtoModal));
    DOM.btnCloseHowto.addEventListener('click', () => closeModal(DOM.howtoModal));
    DOM.btnCloseResult.addEventListener('click', () => closeModal(DOM.resultModal));

    // Logo tıkla → bugünün oyununa dön
    DOM.logo.addEventListener('click', () => loadGame());

    // Modal dışına tıklayarak kapatma
    [DOM.archiveModal, DOM.howtoModal, DOM.resultModal].forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal(modal);
        });
    });
}

// =============================================
// Oyun Yükleme
// =============================================

/**
 * Bugünün veya belirli bir tarihin oyununu yükle.
 * Eğer o güne ait atanmış oyun yoksa, empty state kartı gösterilir
 * ve slider + arama alanları gizlenir.
 */
async function loadGame(date = null) {
    const ts = new Date().getTime();
    const url = date
        ? `api/game.php?action=game&date=${date}&_=${ts}`
        : `api/game.php?action=today&_=${ts}`;

    try {
        const res = await fetch(url);
        const data = await res.json();

        // Eğer önceki oyundan açık kalmış modal varsa kapat (Örn: oyun bitiminden sonra arşive tıklanırsa)
        closeModal(DOM.resultModal);

        if (!data.success) {
            // === OYUN YOK — Empty State göster ===
            // Bugün mü yoksa arşivden mi geldiğini kontrol et
            const isToday = !date;
            showEmptyState(
                isToday
                    ? 'Bugün için henüz bir oyun hazırlanmadı'
                    : 'Bu tarihe ait bir oyun bulunamadı',
                isToday
                    ? 'Bugün için atanmış bir film bulmacası bulunmuyor. Lütfen daha sonra tekrar deneyin!'
                    : 'Seçtiğiniz tarihe ait bir bulmaca mevcut değil. Başka bir tarih deneyin veya bugünün oyununu oynayın.'
            );
            return;
        }

        // === OYUN VAR — Oyun alanını göster, empty state gizle ===
        showGameArea();

        // State'i ayarla
        gameState.movieId = data.data.movieId;
        gameState.gameDate = data.data.gameDate;
        gameState.images = data.data.images;

        // localStorage'dan önceki durumu yükle
        loadStateFromStorage();

        // Slider index'lerini ayarla
        currentImageIndex = 0;
        maxUnlockedImageIndex = gameState.revealedCount - 1;

        // UI'ı güncelle
        renderGameDate();
        renderSlider();
        renderSliderDots();
        updateSliderArrows();
        renderGuessDots();
        renderGuessHistory();

        // Tamamlandıysa input'u kapat
        if (gameState.isCompleted) {
            disableInput();
        } else {
            enableInput();
        }
    } catch (err) {
        console.error('Oyun yükleme hatası:', err);
        DOM.gameDate.textContent = 'Bağlantı hatası. Lütfen sayfayı yenileyin.';
    }
}

// =============================================
// localStorage State Yönetimi
// =============================================

/** Kayıtlı state'i localStorage'dan yükle */
function loadStateFromStorage() {
    const key = STORAGE_PREFIX + gameState.gameDate;
    const saved = localStorage.getItem(key);

    if (saved) {
        const parsed = JSON.parse(saved);
        gameState.guesses = parsed.guesses || [];
        gameState.revealedCount = parsed.revealedCount || 1;
        gameState.isCompleted = parsed.isCompleted || false;
        gameState.isWon = parsed.isWon || false;
    } else {
        // Yeni oyun — sıfırla
        gameState.guesses = [];
        gameState.revealedCount = 1;
        gameState.isCompleted = false;
        gameState.isWon = false;
    }
}

/** State'i localStorage'a kaydet */
function saveStateToStorage() {
    const key = STORAGE_PREFIX + gameState.gameDate;
    localStorage.setItem(key, JSON.stringify({
        gameDate: gameState.gameDate,
        movieId: gameState.movieId,
        guesses: gameState.guesses,
        revealedCount: gameState.revealedCount,
        isCompleted: gameState.isCompleted,
        isWon: gameState.isWon
    }));
}

// =============================================
// Slider Render & Navigasyon
// =============================================

/**
 * Slider'daki görseli güncelle.
 * currentImageIndex'e göre doğru görseli gösterir.
 */
function renderSlider() {
    if (gameState.images.length === 0) return;

    // Gösterilecek görselin yolunu belirle
    const imageData = gameState.images[currentImageIndex];
    const imgSrc = imageData ? imageData.image_path : '';

    // Fade-out → src değiştir → fade-in animasyonu
    DOM.sliderImage.classList.add('fading');
    setTimeout(() => {
        DOM.sliderImage.src = imgSrc;
        DOM.sliderImage.alt = `Film ipucu görseli ${currentImageIndex + 1}`;
        DOM.sliderImage.classList.remove('fading');
    }, 200);

    // Sayaç güncelle
    DOM.sliderCounter.textContent = `${currentImageIndex + 1} / ${MAX_GUESSES}`;
}

/**
 * Slider navigasyon noktalarını oluştur.
 * Her nokta bir görseli temsil eder: aktif, açık veya kilitli.
 */
function renderSliderDots() {
    DOM.sliderDots.innerHTML = '';

    for (let i = 0; i < MAX_GUESSES; i++) {
        const dot = document.createElement('span');
        dot.className = 'slider-dot';

        if (i === currentImageIndex) {
            // Şu anda görüntülenen
            dot.classList.add('active');
        } else if (i <= maxUnlockedImageIndex) {
            // Açılmış ama şu anda görüntülenmeyen — tıklanabilir
            dot.classList.add('unlocked');
        } else {
            // Henüz açılmamış — kilitli
            dot.classList.add('locked');
        }

        // Sadece açık görsellere tıklanabilir
        if (i <= maxUnlockedImageIndex) {
            dot.addEventListener('click', () => {
                currentImageIndex = i;
                renderSlider();
                renderSliderDots();
                updateSliderArrows();
            });
        }

        DOM.sliderDots.appendChild(dot);
    }
}

/**
 * Slider ok butonlarının aktifliğini güncelle.
 * Prev: currentImageIndex > 0 ise aktif
 * Next: currentImageIndex < maxUnlockedImageIndex ise aktif
 */
function updateSliderArrows() {
    DOM.sliderPrev.disabled = (currentImageIndex <= 0);
    DOM.sliderNext.disabled = (currentImageIndex >= maxUnlockedImageIndex);
}

/**
 * Slider'da önceki veya sonraki görsele git.
 * @param {number} direction — -1 (önceki) veya +1 (sonraki)
 */
function navigateSlider(direction) {
    const newIndex = currentImageIndex + direction;

    // Sınır kontrolü: 0'dan küçük veya açılmış maksimumdan büyük olamaz
    if (newIndex < 0 || newIndex > maxUnlockedImageIndex) return;

    currentImageIndex = newIndex;
    renderSlider();
    renderSliderDots();
    updateSliderArrows();
}

// =============================================
// UI Render Fonksiyonları
// =============================================

/** Oyun tarihini göster */
function renderGameDate() {
    const d = new Date(gameState.gameDate + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const gameDay = new Date(gameState.gameDate + 'T00:00:00');

    const options = { day: 'numeric', month: 'long', year: 'numeric', weekday: 'long' };
    let label = d.toLocaleDateString('tr-TR', options);

    if (gameDay.getTime() === today.getTime()) {
        label = '📅 Bugün — ' + label;
    } else {
        label = '📅 ' + label;
    }

    DOM.gameDate.textContent = label;
}

/** Tahmin hakkı dot'larını göster */
function renderGuessDots() {
    DOM.guessDots.innerHTML = '';

    for (let i = 0; i < MAX_GUESSES; i++) {
        const dot = document.createElement('span');
        dot.className = 'guess-dot';

        if (i < gameState.guesses.length) {
            if (gameState.isWon && i === gameState.guesses.length - 1) {
                dot.classList.add('correct');
            } else {
                dot.classList.add('used');
            }
        } else if (i === gameState.guesses.length && !gameState.isCompleted) {
            dot.classList.add('active');
        }

        DOM.guessDots.appendChild(dot);
    }

    // Kalan hak metni
    const remaining = MAX_GUESSES - gameState.guesses.length;
    if (gameState.isCompleted) {
        DOM.guessText.textContent = gameState.isWon ? '🎉 Doğru!' : '😔 Bitti';
    } else {
        DOM.guessText.textContent = `${remaining} hak kaldı`;
    }
}

/** Tahmin geçmişini listele */
function renderGuessHistory() {
    DOM.guessList.innerHTML = '';

    gameState.guesses.forEach((guess, i) => {
        const li = document.createElement('li');
        li.className = 'guess-list-item';

        const isLast = i === gameState.guesses.length - 1;
        if (gameState.isWon && isLast) {
            li.classList.add('correct');
        } else if (guess === '⏭️ Geçildi') {
            li.classList.add('skipped');
        }

        li.innerHTML = `
            <span class="guess-number">#${i + 1}</span>
            <span class="guess-name">${escapeHtml(guess)}</span>
            <span class="guess-icon">${(gameState.isWon && isLast) ? '✅' : (guess === '⏭️ Geçildi' ? '⏭️' : '❌')}</span>
        `;

        DOM.guessList.appendChild(li);
    });
}

// =============================================
// Tahmin İşlemleri
// =============================================

/** Tahmin gönder */
async function submitGuess(movieTitle) {
    if (gameState.isCompleted || !movieTitle) return;

    try {
        const url = `api/game.php?action=check&movie_id=${gameState.movieId}&guess=${encodeURIComponent(movieTitle)}`;
        const res = await fetch(url);
        const data = await res.json();

        if (!data.success) {
            console.error('Tahmin hatası:', data.error);
            return;
        }

        // Tahmini kaydet
        gameState.guesses.push(movieTitle);

        if (data.isCorrect) {
            // === DOĞRU TAHMİN ===
            gameState.isWon = true;
            gameState.isCompleted = true;
            gameState.revealedCount = MAX_GUESSES;
            maxUnlockedImageIndex = MAX_GUESSES - 1;

            saveStateToStorage();
            renderSliderDots();
            updateSliderArrows();
            renderGuessDots();
            renderGuessHistory();
            disableInput();
            showResultModal(true, data.movie);
        } else {
            // === YANLIŞ TAHMİN ===
            if (gameState.guesses.length >= MAX_GUESSES) {
                // Haklar bitti — oyun kaybedildi
                gameState.isCompleted = true;
                gameState.revealedCount = MAX_GUESSES;
                maxUnlockedImageIndex = MAX_GUESSES - 1;

                saveStateToStorage();
                renderSliderDots();
                updateSliderArrows();
                renderGuessDots();
                renderGuessHistory();
                disableInput();

                // Film bilgilerini al ve göster
                const revealRes = await fetch(`api/game.php?action=reveal&movie_id=${gameState.movieId}`);
                const revealData = await revealRes.json();
                showResultModal(false, revealData.data);
            } else {
                // Yeni görsel aç ve o görsele otomatik geç
                gameState.revealedCount = gameState.guesses.length + 1;
                maxUnlockedImageIndex = gameState.revealedCount - 1;
                currentImageIndex = maxUnlockedImageIndex; // Yeni açılan görsele git

                saveStateToStorage();
                renderSlider();
                renderSliderDots();
                updateSliderArrows();
                renderGuessDots();
                renderGuessHistory();
            }
        }

        // Input'u temizle
        DOM.searchInput.value = '';
        selectedMovie = null;
        hideAutocomplete();

    } catch (err) {
        console.error('Tahmin gönderme hatası:', err);
    }
}

/** Geç (skip) butonu */
function onSkip() {
    if (gameState.isCompleted) return;
    submitGuess('⏭️ Geçildi');
}

// =============================================
// TMDB Autocomplete
// =============================================

/** Arama input değiştiğinde (debounce ile) */
function onSearchInput() {
    const query = DOM.searchInput.value.trim();
    selectedMovie = null;
    autocompleteIndex = -1;

    clearTimeout(debounceTimer);

    if (query.length < 2) {
        hideAutocomplete();
        return;
    }

    // Yükleniyor göster
    DOM.autocomplete.innerHTML = '<li class="autocomplete-loading"><div class="spinner"></div></li>';
    showAutocomplete();

    debounceTimer = setTimeout(() => searchTMDB(query), DEBOUNCE_MS);
}

/** TMDB API'den film ara (sunucu proxy üzerinden — API key gönderilmez) */
async function searchTMDB(query) {
    try {
        // Proxy üzerinden TMDB'ye istek at
        const url = `${TMDB_PROXY_URL}?action=search&query=${encodeURIComponent(query)}`;
        const res = await fetch(url);
        const data = await res.json();

        if (!data.results || data.results.length === 0) {
            DOM.autocomplete.innerHTML = '<li class="autocomplete-empty">Sonuç bulunamadı</li>';
            showAutocomplete();
            return;
        }

        // İlk 8 sonucu göster
        DOM.autocomplete.innerHTML = '';
        data.results.slice(0, 8).forEach((movie, i) => {
            const li = document.createElement('li');
            li.className = 'autocomplete-item';
            li.dataset.index = i;
            li.setAttribute('role', 'option');

            const year = movie.release_date ? movie.release_date.substring(0, 4) : '—';
            const poster = movie.poster_path
                ? `${TMDB_IMG_BASE}${movie.poster_path}`
                : '';
            const title = movie.title || movie.original_title;

            li.innerHTML = `
                ${poster ? `<img class="autocomplete-poster" src="${poster}" alt="">` : '<div class="autocomplete-poster"></div>'}
                <div class="autocomplete-info">
                    <div class="autocomplete-title">${escapeHtml(title)}</div>
                    <div class="autocomplete-year">${year}</div>
                </div>
            `;

            li.addEventListener('click', () => selectAutocomplete(title));
            DOM.autocomplete.appendChild(li);
        });

        showAutocomplete();

    } catch (err) {
        console.error('TMDB arama hatası:', err);
        DOM.autocomplete.innerHTML = '<li class="autocomplete-empty">Arama hatası</li>';
        showAutocomplete();
    }
}

/** Autocomplete'den film seç ve tahmini gönder */
function selectAutocomplete(title) {
    DOM.searchInput.value = title;
    selectedMovie = title;
    hideAutocomplete();
    submitGuess(title);
}

/** Klavye navigasyonu (yukarı/aşağı/enter) */
function onSearchKeydown(e) {
    const items = DOM.autocomplete.querySelectorAll('.autocomplete-item');

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        autocompleteIndex = Math.min(autocompleteIndex + 1, items.length - 1);
        updateAutocompleteHighlight(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        autocompleteIndex = Math.max(autocompleteIndex - 1, 0);
        updateAutocompleteHighlight(items);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (autocompleteIndex >= 0 && items[autocompleteIndex]) {
            const title = items[autocompleteIndex].querySelector('.autocomplete-title').textContent;
            selectAutocomplete(title);
        } else if (DOM.searchInput.value.trim().length >= 2) {
            submitGuess(DOM.searchInput.value.trim());
        }
    } else if (e.key === 'Escape') {
        hideAutocomplete();
    }
}

/** Autocomplete highlight güncelle */
function updateAutocompleteHighlight(items) {
    items.forEach((item, i) => {
        item.classList.toggle('active', i === autocompleteIndex);
    });
}

function showAutocomplete() { DOM.autocomplete.classList.add('visible'); }
function hideAutocomplete() {
    DOM.autocomplete.classList.remove('visible');
    autocompleteIndex = -1;
}

// =============================================
// Sonuç Modal
// =============================================

/** Kazanma/Kaybetme modalını göster */
function showResultModal(isWin, movie) {
    DOM.resultIcon.textContent = isWin ? '🎉' : '😔';
    DOM.resultTitle.textContent = isWin ? 'Tebrikler!' : 'Maalesef!';
    DOM.resultTitle.className = 'result-title ' + (isWin ? 'win' : 'lose');

    if (movie) {
        DOM.resultMovieImg.src = movie.imagePath || '';
        DOM.resultMovieImg.alt = movie.title || '';
        DOM.resultMovieTitle.textContent = `${movie.title} (${movie.titleEn})`;
        DOM.resultMovieYear.textContent = `📅 ${movie.year}`;
        DOM.resultMovieDir.textContent = `🎬 ${movie.director}`;
        DOM.resultMovieOver.textContent = movie.overview || '';
    }

    DOM.statGuesses.textContent = gameState.guesses.length;
    DOM.statImages.textContent = gameState.revealedCount;

    // Confetti animasyonu (kazandıysa)
    DOM.resultAnim.innerHTML = '';
    if (isWin) {
        createConfetti();
    }

    openModal(DOM.resultModal);
}

/** Basit confetti efekti */
function createConfetti() {
    const colors = ['#e2b340', '#f0d060', '#2ecc71', '#e74c3c', '#3498db', '#9b59b6'];
    for (let i = 0; i < 40; i++) {
        const dot = document.createElement('div');
        dot.className = 'confetti';
        dot.style.left = Math.random() * 100 + '%';
        dot.style.top = '-10px';
        dot.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        dot.style.animationDelay = (Math.random() * 1.5) + 's';
        dot.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
        dot.style.width = (Math.random() * 8 + 4) + 'px';
        dot.style.height = (Math.random() * 8 + 4) + 'px';
        DOM.resultAnim.appendChild(dot);
    }
}

// =============================================
// Arşiv
// =============================================

/** Arşiv modalını aç ve tarihleri yükle */
async function openArchive() {
    try {
        const res = await fetch('api/game.php?action=archive');
        const data = await res.json();

        DOM.archiveList.innerHTML = '';

        if (!data.success || data.data.length === 0) {
            DOM.archiveList.innerHTML = '<p class="archive-empty">Henüz arşiv yok.</p>';
            openModal(DOM.archiveModal);
            return;
        }

        const today = new Date().toISOString().split('T')[0];

        data.data.forEach(item => {
            const div = document.createElement('div');
            div.className = 'archive-item';

            // localStorage'dan durum kontrolü
            const saved = localStorage.getItem(STORAGE_PREFIX + item.game_date);
            let statusText = 'Oynanmadı';
            let statusClass = '';

            if (item.game_date === today) {
                statusText = 'Bugün';
                statusClass = 'today';
            }

            if (saved) {
                const parsed = JSON.parse(saved);
                if (parsed.isCompleted) {
                    statusText = parsed.isWon
                        ? `✅ ${parsed.guesses.length}/${MAX_GUESSES}`
                        : '❌ Kaybedildi';
                    statusClass = parsed.isWon ? 'won' : 'lost';
                } else {
                    statusText = `⏳ ${parsed.guesses.length}/${MAX_GUESSES}`;
                }
            }

            // Tarih formatla
            const d = new Date(item.game_date + 'T00:00:00');
            const formatted = d.toLocaleDateString('tr-TR', {
                day: 'numeric', month: 'long', year: 'numeric'
            });

            div.innerHTML = `
                <span class="archive-date">${formatted}</span>
                <span class="archive-status ${statusClass}">${statusText}</span>
            `;

            div.addEventListener('click', () => {
                closeModal(DOM.archiveModal);
                loadGame(item.game_date);
            });

            DOM.archiveList.appendChild(div);
        });

        openModal(DOM.archiveModal);

    } catch (err) {
        console.error('Arşiv yükleme hatası:', err);
    }
}

// =============================================
// Yardımcı Fonksiyonlar
// =============================================

function openModal(modal) { modal.classList.remove('hidden'); }
function closeModal(modal) { modal.classList.add('hidden'); }

function disableInput() {
    DOM.searchInput.disabled = true;
    DOM.btnSkip.disabled = true;
}

function enableInput() {
    DOM.searchInput.disabled = false;
    DOM.btnSkip.disabled = false;
    DOM.searchInput.value = '';
}

/**
 * Empty state kartını göster ve oyun alanını gizle.
 * Oyun yokken çağrılır — slider, arama, tahmin alanları gizlenir.
 * @param {string} title — Kart başlığı
 * @param {string} message — Kart mesajı
 */
function showEmptyState(title, message) {
    // Empty state kartını güncelle ve göster
    DOM.emptyStateTitle.textContent = title;
    DOM.emptyStateMessage.textContent = message;
    DOM.emptyState.classList.remove('hidden');

    // Oyun alanlarını gizle
    DOM.imageSlider.style.display = 'none';
    DOM.guessCounter.style.display = 'none';
    DOM.searchContainer.style.display = 'none';
    DOM.guessHistory.style.display = 'none';

    // Tarih gösterimini temizle
    DOM.gameDate.textContent = '';
}

/**
 * Oyun alanını göster ve empty state kartını gizle.
 * Admin panelinden oyun atandığında otomatik olarak çalışır.
 */
function showGameArea() {
    // Empty state'i gizle
    DOM.emptyState.classList.add('hidden');

    // Oyun alanlarını göster
    DOM.imageSlider.style.display = '';
    DOM.guessCounter.style.display = '';
    DOM.searchContainer.style.display = '';
    DOM.guessHistory.style.display = '';
}

/** XSS koruması için HTML escape */
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
