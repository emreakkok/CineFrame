/**
 * CineFrame — Kadro (Cast) Modu JavaScript Dosyası
 * ====================================
 */

const TMDB_PROXY_URL = 'api/tmdb_proxy.php';
const TMDB_IMG_BASE = 'https://image.tmdb.org/t/p/w92';

const MAX_GUESSES = 6;
const DEBOUNCE_MS = 350;
const STORAGE_PREFIX = 'cineframe_cast_';

const DOM = {
    // Clues
    cluesContainer: document.getElementById('cast-clues-container'),
    clueItems: [
        document.getElementById('clue-1'),
        document.getElementById('clue-2'),
        document.getElementById('clue-3'),
        document.getElementById('clue-4'),
        document.getElementById('clue-5')
    ],
    // Search
    searchInput: document.getElementById('search-input'),
    autocomplete: document.getElementById('autocomplete-list'),
    guessList: document.getElementById('guess-list'),
    guessDots: document.getElementById('guess-dots'),
    guessText: document.getElementById('guess-text'),
    gameDate: document.getElementById('game-date-display'),
    btnSkip: document.getElementById('btn-skip'),
    // Modals
    btnArchive: document.getElementById('btn-archive'),
    btnHowTo: document.getElementById('btn-how-to-play'),
    logo: document.getElementById('logo'),
    resultModal: document.getElementById('result-modal'),
    resultIcon: document.getElementById('result-icon'),
    resultTitle: document.getElementById('result-title'),
    resultMovieImg: document.getElementById('result-movie-image'),
    resultMovieTitle: document.getElementById('result-movie-title'),
    resultMovieYear: document.getElementById('result-movie-year'),
    resultMovieDir: document.getElementById('result-movie-director'),
    resultMovieOver: document.getElementById('result-movie-overview'),
    statGuesses: document.getElementById('stat-guesses'),
    statImages: document.getElementById('stat-images'), // Actually clues in this mode
    btnCloseResult: document.getElementById('btn-close-result'),
    resultAnim: document.getElementById('result-animation'),
    archiveModal: document.getElementById('archive-modal'),
    archiveList: document.getElementById('archive-list'),
    btnCloseArchive: document.getElementById('btn-close-archive'),
    howtoModal: document.getElementById('howto-modal'),
    btnCloseHowto: document.getElementById('btn-close-howto'),
    // Empty state
    emptyState: document.getElementById('empty-state'),
    emptyStateTitle: document.getElementById('empty-state-title'),
    emptyStateMessage: document.getElementById('empty-state-message'),
    // Containers
    guessCounter: document.getElementById('guess-counter'),
    searchContainer: document.getElementById('search-container'),
    guessHistory: document.getElementById('guess-history')
};

const CLUE_KEYS = ['year_genre', 'director', 'cast_third', 'cast_second', 'cast_lead'];

let gameState = {
    movieId: null,
    gameDate: null,
    clues: {}, // { year_genre, director, cast_third, cast_second, cast_lead }
    guesses: [],
    revealedCount: 0, // Start with 0. First wrong/skip reveals first clue.
    isCompleted: false,
    isWon: false
};

let selectedMovie = null;
let debounceTimer = null;
let autocompleteIndex = -1;

document.addEventListener('DOMContentLoaded', init);

function init() {
    bindEvents();
    loadGame();
}

function bindEvents() {
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

    DOM.btnSkip.addEventListener('click', onSkip);

    DOM.btnArchive.addEventListener('click', openArchive);
    DOM.btnCloseArchive.addEventListener('click', () => closeModal(DOM.archiveModal));
    DOM.btnHowTo.addEventListener('click', () => openModal(DOM.howtoModal));
    DOM.btnCloseHowto.addEventListener('click', () => closeModal(DOM.howtoModal));
    DOM.btnCloseResult.addEventListener('click', () => closeModal(DOM.resultModal));

    DOM.logo.addEventListener('click', () => loadGame());

    [DOM.archiveModal, DOM.howtoModal, DOM.resultModal].forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal(modal);
        });
    });
}

async function loadGame(date = null) {
    const ts = new Date().getTime();
    const url = date
        ? `api/cast_game.php?action=game&date=${date}&_=${ts}`
        : `api/cast_game.php?action=today&_=${ts}`;

    try {
        const res = await fetch(url);
        const data = await res.json();

        closeModal(DOM.resultModal);

        if (!data.success) {
            const isToday = !date;
            showEmptyState(
                isToday
                    ? 'Bugün için henüz bir Kadro oyunu hazırlanmadı'
                    : 'Bu tarihe ait bir Kadro oyunu bulunamadı',
                isToday
                    ? 'Bugün için atanmış bir Kadro bulmacası bulunmuyor. Lütfen daha sonra tekrar deneyin!'
                    : 'Seçtiğiniz tarihe ait bir Kadro bulmacası mevcut değil. Başka bir tarih deneyin.'
            );
            return;
        }

        showGameArea();

        gameState.movieId = data.data.movieId;
        gameState.gameDate = data.data.gameDate;
        gameState.clues = data.data.clues;

        loadStateFromStorage();

        renderGameDate();
        renderClues();
        renderGuessDots();
        renderGuessHistory();

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

function loadStateFromStorage() {
    const key = STORAGE_PREFIX + gameState.gameDate;
    const saved = localStorage.getItem(key);

    if (saved) {
        const parsed = JSON.parse(saved);
        gameState.guesses = parsed.guesses || [];
        const guessedCount = (parsed.guesses || []).length;
        const savedRevealed = Number.isInteger(parsed.revealedCount) ? parsed.revealedCount : guessedCount;
        gameState.revealedCount = Math.max(0, Math.min(savedRevealed, CLUE_KEYS.length));
        gameState.isCompleted = parsed.isCompleted || false;
        gameState.isWon = parsed.isWon || false;
    } else {
        gameState.guesses = [];
        gameState.revealedCount = 0;
        gameState.isCompleted = false;
        gameState.isWon = false;
    }
}

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

function renderClues() {
    const openClues = Math.max(0, Math.min(gameState.revealedCount, CLUE_KEYS.length));

    for (let i = 0; i < DOM.clueItems.length; i++) {
        const clueEl = DOM.clueItems[i];
        if (!clueEl) continue; // null safety
        const valueEl = clueEl.querySelector('.clue-value');
        
        if (i < openClues) {
            // Kademeli açılış: her kart öncekinden 120ms sonra belirsin
            const delay = i * 120;
            setTimeout(() => {
                clueEl.classList.add('revealed');
                valueEl.classList.remove('skeleton');
                valueEl.textContent = gameState.clues[CLUE_KEYS[i]] || 'Bilinmiyor';
            }, clueEl.classList.contains('revealed') ? 0 : delay);
        } else {
            clueEl.classList.remove('revealed');
            valueEl.classList.add('skeleton');
            valueEl.textContent = '????';
        }
    }
}

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

    const remaining = MAX_GUESSES - gameState.guesses.length;
    if (gameState.isCompleted) {
        DOM.guessText.textContent = gameState.isWon ? '🎉 Doğru!' : '😔 Bitti';
    } else {
        DOM.guessText.textContent = `${remaining} hak kaldı`;
    }
}

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

async function submitGuess(movieTitle) {
    if (gameState.isCompleted || !movieTitle) return;

    try {
        const url = `api/cast_game.php?action=check&movie_id=${gameState.movieId}&guess=${encodeURIComponent(movieTitle)}`;
        const res = await fetch(url);
        const data = await res.json();

        if (!data.success) {
            console.error('Tahmin hatası:', data.error);
            return;
        }

        gameState.guesses.push(movieTitle);

        if (data.isCorrect) {
            gameState.isWon = true;
            gameState.isCompleted = true;
            gameState.revealedCount = CLUE_KEYS.length;

            saveStateToStorage();
            renderClues();
            renderGuessDots();
            renderGuessHistory();
            disableInput();
            showResultModal(true, data.movie);
        } else {
            if (gameState.guesses.length >= MAX_GUESSES) {
                gameState.isCompleted = true;
                gameState.revealedCount = CLUE_KEYS.length;

                saveStateToStorage();
                renderClues();
                renderGuessDots();
                renderGuessHistory();
                disableInput();

                const revealRes = await fetch(`api/cast_game.php?action=reveal&movie_id=${gameState.movieId}`);
                const revealData = await revealRes.json();
                showResultModal(false, revealData.data);
            } else {
                gameState.revealedCount = Math.min(gameState.guesses.length, CLUE_KEYS.length);

                saveStateToStorage();
                renderClues();
                renderGuessDots();
                renderGuessHistory();
            }
        }

        DOM.searchInput.value = '';
        selectedMovie = null;
        hideAutocomplete();

    } catch (err) {
        console.error('Tahmin gönderme hatası:', err);
    }
}

function onSkip() {
    if (gameState.isCompleted) return;
    submitGuess('⏭️ Geçildi');
}

function onSearchInput() {
    const query = DOM.searchInput.value.trim();
    selectedMovie = null;
    autocompleteIndex = -1;

    clearTimeout(debounceTimer);

    if (query.length < 2) {
        hideAutocomplete();
        return;
    }

    DOM.autocomplete.innerHTML = '<li class="autocomplete-loading"><div class="spinner"></div></li>';
    showAutocomplete();

    debounceTimer = setTimeout(() => searchTMDB(query), DEBOUNCE_MS);
}

async function searchTMDB(query) {
    try {
        const url = `${TMDB_PROXY_URL}?action=search&query=${encodeURIComponent(query)}`;
        const res = await fetch(url);
        const data = await res.json();

        if (!data.results || data.results.length === 0) {
            DOM.autocomplete.innerHTML = '<li class="autocomplete-empty">Sonuç bulunamadı</li>';
            showAutocomplete();
            return;
        }

        DOM.autocomplete.innerHTML = '';
        data.results.slice(0, 8).forEach((movie, i) => {
            const li = document.createElement('li');
            li.className = 'autocomplete-item';
            li.dataset.index = i;
            li.setAttribute('role', 'option');

            const year = movie.release_date ? movie.release_date.substring(0, 4) : '—';
            const poster = movie.poster_path ? `${TMDB_IMG_BASE}${movie.poster_path}` : '';
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

function selectAutocomplete(title) {
    DOM.searchInput.value = title;
    selectedMovie = title;
    hideAutocomplete();
    submitGuess(title);
}

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

function showResultModal(isWin, movie) {
    DOM.resultIcon.textContent = isWin ? '🎉' : '😔';
    DOM.resultTitle.textContent = isWin ? 'Tebrikler!' : 'Maalesef!';
    DOM.resultTitle.className = 'result-title ' + (isWin ? 'win' : 'lose');

    if (movie) {
        DOM.resultMovieImg.src = movie.imagePath || '';
        DOM.resultMovieImg.alt = movie.title || '';
        DOM.resultMovieTitle.textContent = `${movie.title} (${movie.titleEn})`;
        
        let metaText = `📅 ${movie.year}`;
        if (movie.runtime) metaText += ` • ⏱️ ${movie.runtime} dk`;
        DOM.resultMovieYear.textContent = metaText;
        
        DOM.resultMovieDir.textContent = `🎬 ${movie.director}`;
        DOM.resultMovieOver.textContent = movie.overview || '';
    }

    DOM.statGuesses.textContent = gameState.guesses.length;
    DOM.statImages.textContent = gameState.revealedCount; // Clues revealed, max 5

    DOM.resultAnim.innerHTML = '';
    if (isWin) {
        createConfetti();
    }

    openModal(DOM.resultModal);
}

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

async function openArchive() {
    try {
        const res = await fetch('api/cast_game.php?action=archive');
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

function showEmptyState(title, message) {
    DOM.emptyStateTitle.textContent = title;
    DOM.emptyStateMessage.textContent = message;
    DOM.emptyState.classList.remove('hidden');

    DOM.cluesContainer.style.display = 'none';
    DOM.guessCounter.style.display = 'none';
    DOM.searchContainer.style.display = 'none';
    DOM.guessHistory.style.display = 'none';
    DOM.gameDate.textContent = '';
}

function showGameArea() {
    DOM.emptyState.classList.add('hidden');

    DOM.cluesContainer.classList.remove('hidden');
    DOM.guessCounter.classList.remove('hidden');
    DOM.searchContainer.classList.remove('hidden');
    DOM.guessHistory.classList.remove('hidden');

    DOM.cluesContainer.style.display = '';
    DOM.guessCounter.style.display = '';
    DOM.searchContainer.style.display = '';
    DOM.guessHistory.style.display = '';
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
