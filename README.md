# 🎬 CineFrame — Günlük Film Tahmin Oyunu

Her gün yeni bir film bulmacası! 6 görsel ipucuyla filmi tahmin edin.

## 📋 Özellikler

- **Günlük Bulmaca**: Her gün yeni bir film sorulur
- **6 Görsel İpucu**: Her yanlış tahminde yeni bir görsel açılır
- **TMDB Entegrasyonu**: Arama çubuğunda anlık film önerileri
- **Admin Paneli**: Film ekleme, görsel yükleme, tarih atama
- **Arşiv**: Geçmiş günlerin bulmacalarını oynayabilirsiniz
- **Responsive Tasarım**: Mobil ve masaüstü uyumlu
- **Koyu Tema**: Sinematik, premium arayüz

## 🛠️ Gereksinimler

- **PHP 7.4+** (SQLite ve PDO desteği ile)
- **XAMPP / WAMP / MAMP** veya herhangi bir PHP web sunucusu
- **TMDB API Anahtarı** (ücretsiz — [themoviedb.org](https://www.themoviedb.org/settings/api))

## 🚀 Kurulum

### 1. Projeyi Klonlayın

```bash
git clone https://github.com/KULLANICI_ADINIZ/CineFrame.git
cd CineFrame
```

XAMPP kullanıyorsanız `htdocs` klasörüne kopyalayın:

```
C:\xampp\htdocs\CineFrame\
```

### 2. Konfigürasyon Dosyasını Oluşturun

```bash
copy config.example.php config.php
```

`config.php` dosyasını açın ve TMDB API anahtarınızı yazın:

```php
define('TMDB_API_KEY', 'sizin_api_anahtariniz');
```

> ⚠️ `config.php` dosyası `.gitignore`'da listelenmiştir ve GitHub'a **yüklenmez**. API anahtarınız güvende kalır.

### 3. Veritabanını Oluşturun

Tarayıcınızda açın:

```
http://localhost/CineFrame/setup.php
```

Bu işlem:
- `database.sqlite` dosyasını oluşturur
- Gerekli tabloları kurar (movies, game_dates, movie_images, admin_users)
- Varsayılan admin kullanıcısı oluşturur (`admin` / `admin123`)
- Veritabanı **boş** oluşturulur — filmler admin panelinden eklenir

### 4. Admin Panelinden Film Ekleyin

```
http://localhost/CineFrame/admin/admin.php
```

1. `admin` / `admin123` ile giriş yapın
2. TMDB'den film aratıp veritabanına ekleyin
3. Her film için 6 adet görsel yükleyin
4. Filmlere oyun tarihi atayın

### 5. Oyunu Başlatın

```
http://localhost/CineFrame/
```

## 📁 Proje Yapısı

```
CineFrame/
├── index.php              → Ana oyun sayfası (slider UI)
├── setup.php              → Veritabanı kurulumu (bir kez çalıştırılır)
├── config.php             → Hassas ayarlar (API key) — GİT'E YÜKLENMEZ
├── config.example.php     → Konfigürasyon şablonu
├── api/
│   ├── game.php           → Backend oyun API'si
│   └── tmdb_proxy.php     → TMDB API proxy (key sunucuda kalır)
├── admin/
│   ├── admin.php          → Admin giriş sayfası
│   ├── dashboard.php      → Admin kontrol paneli
│   ├── process.php        → Admin işlem endpoint'leri
│   ├── css/admin.css      → Admin panel stilleri
│   └── js/admin.js        → Admin panel JS
├── css/style.css          → Oyun arayüzü stilleri
├── js/app.js              → Frontend mantığı (slider, state)
├── assets/uploads/        → Yüklenen görseller — GİT'E YÜKLENMEZ
├── .gitignore
└── README.md
```

## 🔐 Güvenlik

| Önlem | Açıklama |
|-------|----------|
| **API Key Gizleme** | TMDB anahtarı `config.php`'de saklanır, JS'ye hiç gönderilmez |
| **Sunucu Proxy** | TMDB istekleri `api/tmdb_proxy.php` üzerinden yapılır |
| **Şifre Hashleme** | Admin şifreleri `password_hash()` ile saklanır |
| **Session Kontrolü** | Admin paneli session tabanlı kimlik doğrulaması kullanır |
| **.gitignore** | `config.php`, `database.sqlite`, `uploads/` GitHub'a yüklenmez |

## 🎮 Nasıl Oynanır?

1. Sayfa açıldığında ilk görsel ipucu gösterilir
2. Arama çubuğuna film adı yazarak TMDB'den arayın
3. Listeden bir film seçerek tahmin edin
4. Yanlış tahminlerde yeni görsel ipuçları açılır
5. 6 tahmin hakkınız var — ne kadar az tahminde bilirseniz o kadar iyi!
6. Geçmiş bulmacaları **Arşiv** butonundan oynayabilirsiniz

## 🔧 Teknolojiler

| Katman | Teknoloji |
|--------|-----------|
| Backend | PHP 7.4+ |
| Veritabanı | SQLite (PDO) |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| API | TMDB (The Movie Database) |
| State | localStorage |

## 📝 Lisans

Bu proje eğitim amaçlıdır. Film verileri [TMDB](https://www.themoviedb.org/) tarafından sağlanmaktadır.
