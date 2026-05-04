/**
 * CineFrame Admin — JavaScript
 * ==============================
 * TMDB film arama (sunucu proxy üzerinden), görsel upload önizleme.
 * API anahtarı JS'de TUTULMAZ — güvenlik için config.php'de saklanır.
 */

// =============================================
// API Ayarları (proxy üzerinden — key sunucuda kalır)
// =============================================
const TMDB_PROXY_URL = '../api/tmdb_proxy.php';
const TMDB_IMG_BASE = 'https://image.tmdb.org/t/p/w92';

// Debounce timer
let debounceTimer = null;

// =============================================
// Başlatma
// =============================================
document.addEventListener('DOMContentLoaded', () => {
    // TMDB arama input'u
    const searchInput = document.getElementById('admin-tmdb-search');
    if (searchInput) {
        searchInput.addEventListener('input', onTmdbSearch);
    }

    // Görsel upload önizleme
    setupImagePreviews();
});

// =============================================
// TMDB Film Arama
// =============================================

/** Arama input'u değiştiğinde (debounce ile) */
function onTmdbSearch() {
    const query = this.value.trim();
    const resultsDiv = document.getElementById('admin-tmdb-results');

    clearTimeout(debounceTimer);

    if (query.length < 2) {
        resultsDiv.innerHTML = '';
        return;
    }

    // Yükleniyor göster
    resultsDiv.innerHTML = '<div style="text-align:center;padding:1rem"><div class="spinner"></div></div>';

    debounceTimer = setTimeout(() => searchTMDB(query), 400);
}

/** TMDB API'den film ara (proxy üzerinden — API key gönderilmez) */
async function searchTMDB(query) {
    const resultsDiv = document.getElementById('admin-tmdb-results');

    try {
        // Proxy üzerinden TMDB arama
        const url = `${TMDB_PROXY_URL}?action=search&query=${encodeURIComponent(query)}`;
        const res = await fetch(url);
        const data = await res.json();

        if (data.error) {
            resultsDiv.innerHTML = `<p style="color:var(--red);text-align:center">⚠️ ${data.error}</p>`;
            return;
        }

        if (!data.results || data.results.length === 0) {
            resultsDiv.innerHTML = '<p style="color:var(--text-muted);text-align:center">Sonuç bulunamadı.</p>';
            return;
        }

        // Sonuçları listele
        resultsDiv.innerHTML = '';
        for (const movie of data.results.slice(0, 8)) {
            const year = movie.release_date ? movie.release_date.substring(0, 4) : '—';
            const poster = movie.poster_path ? `${TMDB_IMG_BASE}${movie.poster_path}` : '';
            const title = movie.title || movie.original_title;
            const origTitle = movie.original_title || title;

            const item = document.createElement('div');
            item.className = 'tmdb-result-item';

            item.innerHTML = `
                ${poster ? `<img class="tmdb-result-poster" src="${poster}" alt="">` : '<div class="tmdb-result-poster"></div>'}
                <div class="tmdb-result-info">
                    <div class="tmdb-result-title">${escapeHtml(title)}</div>
                    <div class="tmdb-result-meta">${origTitle} • ${year}</div>
                </div>
                <button class="tmdb-result-btn" data-tmdb-id="${movie.id}">+ Ekle</button>
            `;

            // Ekle butonuna tıklama
            const btn = item.querySelector('.tmdb-result-btn');
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                addMovieFromTMDB(movie.id, btn);
            });

            resultsDiv.appendChild(item);
        }

    } catch (err) {
        console.error('TMDB arama hatası:', err);
        resultsDiv.innerHTML = '<p style="color:var(--red);text-align:center">Arama hatası.</p>';
    }
}

/**
 * TMDB ID ile film detaylarını çekip veritabanına ekle.
 * Tüm TMDB istekleri proxy üzerinden yapılır — API key güvende kalır.
 */
async function addMovieFromTMDB(tmdbId, btn) {
    btn.disabled = true;
    btn.textContent = '⏳';

    try {
        // Film detaylarını çek (Türkçe) — proxy üzerinden
        const detailRes = await fetch(`${TMDB_PROXY_URL}?action=detail&id=${tmdbId}`);
        const detail = await detailRes.json();

        // İngilizce başlığı al — proxy üzerinden
        const detailEnRes = await fetch(`${TMDB_PROXY_URL}?action=detail_en&id=${tmdbId}`);
        const detailEn = await detailEnRes.json();

        // Yönetmen bilgisini al — proxy üzerinden
        const creditsRes = await fetch(`${TMDB_PROXY_URL}?action=credits&id=${tmdbId}`);
        const credits = await creditsRes.json();
        const director = credits.crew
            ? credits.crew.find(c => c.job === 'Director')
            : null;

        // Backend'e gönder
        const payload = {
            title: detail.title || detailEn.title,
            title_en: detailEn.title || detail.original_title,
            year: detail.release_date ? parseInt(detail.release_date.substring(0, 4)) : 0,
            director: director ? director.name : 'Bilinmiyor',
            tmdb_id: tmdbId,
            overview: detail.overview || detailEn.overview || ''
        };

        const saveRes = await fetch('process.php?action=add_movie', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const saveData = await saveRes.json();

        if (saveData.success) {
            btn.textContent = '✅ Eklendi';
            btn.style.background = 'rgba(46,204,113,0.2)';
            // 2 saniye sonra sayfayı yenile (select listelerini güncellemek için)
            setTimeout(() => window.location.reload(), 1500);
        } else {
            btn.textContent = '❌ ' + (saveData.error || 'Hata');
            btn.style.background = 'rgba(231,76,60,0.2)';
            btn.disabled = false;
        }

    } catch (err) {
        console.error('Film ekleme hatası:', err);
        btn.textContent = '❌ Hata';
        btn.disabled = false;
    }
}

// =============================================
// Görsel Upload Önizleme
// =============================================

/** Her upload input'una önizleme event'i ekle */
function setupImagePreviews() {
    for (let i = 1; i <= 6; i++) {
        const input = document.getElementById(`image-${i}`);
        const preview = document.getElementById(`preview-${i}`);

        if (input && preview) {
            input.addEventListener('change', function () {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        // Önizleme görseli oluştur
                        preview.classList.add('has-image');
                        // Mevcut içeriği temizle (SVG ve text)
                        const svg = preview.querySelector('svg');
                        const span = preview.querySelector('span');
                        if (svg) svg.style.display = 'none';
                        if (span) span.style.display = 'none';

                        // Önizleme img ekle
                        let img = preview.querySelector('img');
                        if (!img) {
                            img = document.createElement('img');
                            preview.appendChild(img);
                        }
                        img.src = e.target.result;
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    }
}

// =============================================
// Yardımcı
// =============================================

/** XSS koruması */
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
