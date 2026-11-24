# ENVIRONMENT SETUP — Windows Lokal

## 1. Persiapan Database PostgreSQL 18
Pastikan layanan Windows `postgresql-x64-18` dalam status `Running`. Jika database belum ada, buat melalui `psql`:
```powershell
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h 127.0.0.1 -c "CREATE DATABASE affiliate_ai_tiktok ENCODING 'UTF8';"
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h 127.0.0.1 -c "CREATE DATABASE affiliate_ai_tiktok_test ENCODING 'UTF8';"
```

## 2. Konfigurasi `.env`
Salin `.env.example` menjadi `.env`, lalu sesuaikan:
- `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=affiliate_ai_tiktok`, `DB_USERNAME=postgres`
- `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`
- `QUEUE_CONNECTION=database`
- Generate `APP_KEY` bila belum ada: `php artisan key:generate`
- **Jangan pernah commit `.env` ke Git.**

## 3. Instalasi Dependensi & Build Frontend
```powershell
composer install
npm install
npm run build
php artisan migrate --seed
```

## 4. Menjalankan Layanan Harian (3 Terminal PowerShell)
- **Server Web:** `php artisan serve --host=127.0.0.1 --port=8000`
- **Queue Worker:** `php artisan queue:work --tries=3`
- **Scheduler (Asia/Jakarta):** `php artisan schedule:work`

## 5. Pengujian Otomatis
```powershell
php artisan test
```
Pengujian berjalan langsung menggunakan database PostgreSQL `affiliate_ai_tiktok_test`.
