# Sistem Monitoring K3 & Disiplin Kerja — Backend

> Capstone Project · Teknik Informatika · Laravel 11 + Sanctum + MySQL

---

## Akun Demo

Semua akun menggunakan password: **`password123`**

| Role | Email | Bisa apa |
|---|---|---|
| **Admin** | `admin@k3.com` | Manajemen user, zona, kamera, shift, lihat activity log |
| **Manager** | `manager@k3.com` | Validasi / tolak pelanggaran, lihat dashboard |
| **HR** | `hr@k3.com` | Generate laporan periode, lihat dashboard |

---

## Data Demo

Seeder menghasilkan **23 violations** lintas semua state:

| State | Jumlah | Untuk demo |
|---|---|---|
| `pending` | 8 | Login Manager → Pelanggaran → Detail → Validasi / Tolak |
| `validated` | 6 | Login HR → Laporan → Generate (periode ~7 hari ke belakang) |
| `rejected` | 4 | Filter status = Rejected di halaman Pelanggaran |
| `reported` | 5 | Dashboard KPI — sudah terhitung di grafik |

**Zona & Kamera:**
- Zona **Forklift** (CH-01): wajib helm + rompi + boots
- Zona **Empty Box** (CH-02): wajib rompi + boots, helm tidak wajib

---

## Setup A — Manual (tanpa Docker)

### Prasyarat
- PHP 8.2+ dengan extension: `pdo_mysql`, `mbstring`, `xml`, `bcmath`, `zip`, `gd`
- Composer
- MySQL 8.0+

### Langkah

```bash
# 1. Clone repo
git clone <repo-url> k3-monitoring
cd k3-monitoring

# 2. Install dependencies
composer install

# 3. Copy env
cp .env.example .env
```

Edit `.env` — sesuaikan dengan MySQL lokal:
```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=k3_db
DB_USERNAME=root
DB_PASSWORD=           # sesuaikan

SANCTUM_STATEFUL_DOMAINS=localhost:8000,127.0.0.1:8000
```

```bash
# 4. Buat database di MySQL
# CREATE DATABASE k3_db;

# 5. Generate key + migrate + seed
php artisan key:generate
php artisan migrate --seed

# 6. Jalankan
php artisan serve
```

Buka **http://localhost:8000**

---

## Setup B — Docker

### Prasyarat
- Docker Desktop 24+
- Docker Compose v2

### Langkah

```bash
# 1. Clone repo
git clone <repo-url> k3-monitoring
cd k3-monitoring

# 2. Copy env
cp .env.example .env

# 3. (Opsional) Isi Telegram credentials di .env
#    Kalau dikosongkan, sistem tetap jalan — notif tidak terkirim, dicatat di log.

# 4. Jalankan
docker compose up --build -d
```

Tunggu ±1 menit (build pertama lebih lama), lalu buka **http://localhost:8080**

Entrypoint otomatis: migrate → seed (skip jika DB sudah ada data) → config cache.

### Port Docker

| Service | Host port |
|---|---|
| App (Nginx) | `8080` |
| MySQL | `3307` |

### Perintah berguna

```bash
# Lihat log
docker compose logs -f app

# Reset total (hapus semua data)
docker compose exec app php artisan migrate:fresh --seed

# Stop
docker compose down

# Stop + hapus volume DB
docker compose down -v
```

---

## Integrasi TIF (Computer Vision)

TIF mengirim event deteksi ke endpoint berikut menggunakan `X-Service-Key` header:

```
POST /api/violations
X-Service-Key: <nilai TIF_SERVICE_KEY di .env>
Content-Type: application/json

{
  "camera_id": 1,
  "timestamp": "2025-05-05T10:30:00",
  "label": "no_helmet",
  "confidence": 0.92,
  "image_path": "violations/frame_xxx.jpg"
}
```

Label yang valid: `no_helmet` · `no_vest` · `no_boots` · `person`

Aturan otomatis:
- Confidence < 0.7 → diabaikan
- Label `person` di luar jam shift → discipline violation
- Label APD tidak sesuai aturan zona → diabaikan

---

## Telegram Notifications

Isi di `.env`:

```dotenv
TELEGRAM_BOT_TOKEN=<token dari @BotFather>
TELEGRAM_MANAGER_CHAT_ID=<chat ID Manager>
TELEGRAM_HR_CHAT_ID=<chat ID HR>
```

Cara dapat chat ID — kirim pesan ke bot, lalu buka:
```
https://api.telegram.org/bot<TOKEN>/getUpdates
```

Flow:
1. TIF kirim event → `pending` → **Telegram ke Manager**
2. Manager validasi → `validated` → **Telegram ke HR**
3. HR generate laporan → `reported`

---

## Troubleshooting

**`composer install` gagal / extension tidak ada**
Cek extension PHP yang aktif: `php -m | grep pdo`

**Seeder duplikat data**
```bash
php artisan migrate:fresh --seed
```

**Port 8080 sudah dipakai (Docker)**
Ganti di `docker-compose.yml`: `"8090:80"`, lalu update `SANCTUM_STATEFUL_DOMAINS` di `.env`.

**Notif Telegram tidak terkirim**
```bash
# Manual
tail -100 storage/logs/laravel.log | grep -i telegram

# Docker
docker compose exec app tail -100 storage/logs/laravel.log | grep -i telegram
```