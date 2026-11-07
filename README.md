# 🧮 Modern Scientific Calculator

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](LICENSE)

> **Kalkulator web modern berbasis Laravel dengan mode Standar dan Scientific, riwayat perhitungan, tema gelap/terang, dukungan keyboard, dan tampilan responsif.**

## 📋 Daftar Isi

- [✨ Fitur](#-fitur)
- [🚀 Instalasi](#-instalasi)
- [💻 Penggunaan](#-penggunaan)
- [🏗️ Struktur Proyek](#️-struktur-proyek)
- [🔌 Endpoint](#-endpoint)
- [🧪 Testing](#-testing)
- [📄 License](#-license)

## ✨ Fitur

### 🧮 Dua Mode Kalkulator
- **Standar**: `+ − × ÷`, persen, ±, backspace.
- **Scientific**: `sin cos tan`, `sin⁻¹ cos⁻¹ tan⁻¹`, `log ln`, `eˣ`, `√`, `x²`, `xʸ`, `n!`, `1/x`, kurung `( )`, konstanta `π` dan `e`.
- Mode sudut **DEG / RAD**.
- Memori **MC, MR, M+, M−** dengan indikator `M`.
- Pindah mode dengan animasi flip; mode terakhir diingat browser.

### 🧠 Mesin Hitung di Server
- Ekspresi dihitung oleh **parser ekspresi milik sendiri** (recursive-descent) di Laravel — tidak memakai `eval()`, jadi aman dari injeksi kode.
- Prioritas operator yang benar (`2+3×4 = 14`), pangkat asosiatif kanan, perkalian implisit (`2π`, `3(4+1)`), kurung ditutup otomatis.
- Persen bergaya kalkulator: `200 + 10% = 220`.
- Hasil bebas galat floating point (`0.1 + 0.2 = 0.3`), angka sangat besar/kecil memakai notasi ilmiah (`1E+21`).
- Pesan error yang jelas: bagi nol, akar negatif, log ≤ 0, `tan(90°)`, faktorial bukan bilangan bulat, dll.

### 📊 Riwayat
- Menyimpan **10 perhitungan terakhir** di session Laravel (tetap ada setelah halaman di-refresh).
- Klik salah satu riwayat untuk memakai hasilnya lagi.
- Hapus riwayat dengan konfirmasi.

### 🎨 Tampilan
- Tema **gelap/terang** (mengikuti sistem, bisa diganti manual).
- Responsif untuk desktop, tablet, dan HP.
- Suara klik (Web Audio, tanpa file eksternal) yang bisa dimatikan.
- Menghormati pengaturan *reduce motion*.

## 🚀 Instalasi

### Kebutuhan

| Kebutuhan | Versi |
|-----------|-------|
| PHP | 8.2+ (ekstensi `mbstring`, `openssl`, `fileinfo`) |
| Composer | 2.x |
| Browser | Chrome / Firefox / Safari / Edge modern |

Tidak perlu database dan tidak perlu Node.js/npm — CSS dan JS sudah siap di folder `public/`.

### Langkah

```bash
# 1. Clone repository
git clone https://github.com/whympxx/Kalkulator-Standar-Scientific.git
cd Kalkulator-Standar-Scientific

# 2. Install dependency PHP
composer install

# 3. Siapkan konfigurasi
cp .env.example .env        # Windows: copy .env.example .env
php artisan key:generate

# 4. Jalankan
php artisan serve
```

Buka **http://localhost:8000**.

> Memakai XAMPP/Laragon? Arahkan *document root* ke folder `public/`, bukan ke root proyek.

## 💻 Penggunaan

| Tombol / Keyboard | Fungsi |
|-------------------|--------|
| `0-9`, `.` | Input angka |
| `+` `-` `*` `/` | Operasi dasar |
| `^` | Pangkat |
| `(` `)` | Kurung |
| `!` `%` | Faktorial, persen |
| `Enter` / `=` | Hitung |
| `Backspace` | Hapus satu karakter/fungsi |
| `Esc` / `Delete` | Hapus semua (AC) |
| `p` / `e` | Konstanta π / e |

Setelah menekan `=`, menekan operator akan melanjutkan dari hasil (`Ans`), sedangkan menekan angka memulai perhitungan baru.

## 🏗️ Struktur Proyek

```
app/
├── Http/Controllers/CalculatorController.php   # halaman, hitung, riwayat
├── Http/Requests/CalculateRequest.php          # validasi input
└── Services/Calculator/
    ├── Tokenizer.php            # memecah teks jadi token
    ├── ExpressionEvaluator.php  # parser & evaluator ekspresi
    ├── ResultFormatter.php      # merapikan hasil (0.3, 1e+21, ...)
    ├── HistoryRepository.php    # riwayat di session
    └── CalculationException.php
resources/views/calculator.blade.php            # tampilan
public/css/calculator.css                       # gaya + tema
public/js/calculator.js                         # logika tombol & keyboard
routes/web.php
tests/Unit/ExpressionEvaluatorTest.php
tests/Feature/CalculatorTest.php
legacy/                                         # versi PHP lama (arsip)
```

## 🔌 Endpoint

| Method | URL | Keterangan |
|--------|-----|------------|
| `GET` | `/` | Halaman kalkulator |
| `POST` | `/calculate` | Body JSON `{ expression, mode: standard\|scientific, angle: deg\|rad }` → `{ expression, result, history }`, atau `422 { message }` bila error |
| `GET` | `/history` | Daftar riwayat |
| `DELETE` | `/history` | Hapus riwayat |

Semua endpoint memakai proteksi CSRF Laravel dan dibatasi 120 request/menit.

## 🧪 Testing

```bash
php artisan test
```

Mencakup 60+ kasus mesin hitung (prioritas operator, trigonometri, persen, error domain, input berbahaya) dan pengujian endpoint (validasi, riwayat, batas riwayat, escaping XSS).

## 📄 License

Proyek ini berlisensi **MIT** — lihat file [LICENSE](LICENSE).

---

<div align="center">

**Built with ❤️ by [whympxx](https://github.com/whympxx)**

</div>
