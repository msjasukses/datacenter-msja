<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Instalasi & Deployment (Virtualmin - smpn40.educore.web.id/datacenter)

App ini dilayani nginx lewat subpath `/datacenter` (bukan domain/subdomain sendiri), via `alias` ke folder `public/`. Path server: `/home/smpn40.educore.web.id/apps/datacenter`.

### 0. Prasyarat: Node.js versi baru

Project ini pakai **Vite 8**, yang mensyaratkan **Node.js 20.19+ atau 22.12+**. Cek dulu versi Node di server:

```bash
node -v
```

Kalau versinya di bawah itu (mis. Node 18.x bawaan server), `npm run dev`/`npm run build` akan gagal dengan error `Vite requires Node.js version 20.19+ or 22.12+` atau `CustomEvent is not defined`. Install Node yang lebih baru pakai **nvm** (tidak butuh akses root):

```bash
# 1. Install nvm (Node Version Manager) di home user ini
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash

# 2. Reload shell agar nvm terbaca
source ~/.bashrc
# atau kalau tidak ada .bashrc: source ~/.nvm/nvm.sh

# 3. Install & pakai Node 20 LTS
nvm install 20
nvm use 20
nvm alias default 20

# 4. Pastikan versinya sudah benar
node -v   # harus 20.x
```

> `nvm use`/`nvm alias default` berlaku per user shell. Kalau nanti buka terminal baru dan `node -v` balik ke versi lama, jalankan `nvm use 20` lagi (atau pastikan `nvm alias default 20` sudah tersimpan).

### 1. Install dependency & build

```bash
cd /home/smpn40.educore.web.id/apps/datacenter

# 1. Install dependency PHP & JS
composer install --no-dev --optimize-autoloader
npm install
npm run build   # BUKAN "npm run dev" — itu untuk development, bukan produksi

# 2. Setup environment (skip jika .env sudah ada & sudah dikonfigurasi)
cp .env.example .env
php artisan key:generate

# 3. Edit .env, pastikan minimal:
#    APP_ENV=production
#    APP_DEBUG=false
#    APP_URL=https://smpn40.educore.web.id/datacenter   <-- WAJIB pakai path /datacenter
#    ASSET_URL=https://smpn40.educore.web.id/datacenter
#    DB_DATABASE, DB_USERNAME, DB_PASSWORD sesuai database Virtualmin

# 4. Link storage publik (upload, dsb)
php artisan storage:link

# 5. Migrasi database
php artisan migrate --force

# 6. Cache config/route/view untuk performa produksi
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Pastikan folder ini writable oleh user PHP-FPM (mis. www-data / user virtualmin)
chmod -R 775 storage bootstrap/cache
```

> Catatan: `APP_URL` **harus** menyertakan `/datacenter` karena app ini diakses lewat subfolder di domain utama, bukan root. Tanpa ini, link (`url()`, `asset()`, redirect) yang di-generate Laravel akan salah (mengarah ke root domain, bukan ke `/datacenter`).

Setelah itu reload PHP-FPM/nginx bila perlu:
```bash
sudo systemctl reload nginx
```

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
