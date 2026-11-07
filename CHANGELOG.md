# 📋 Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### 🚀 Planned Features
- Gesture support for mobile
- Export/Import calculations
- PWA (Progressive Web App) support

## [3.1.0] - 2026-11-07

### ✨ Added
- **Mode Kriptografi**: aritmetika modular (mod, pangkat modular, invers, GCD/Euclid diperluas, cek prima, φ(n)), RSA (kunci, enkripsi, dekripsi angka/teks), Caesar (+ brute force), Vigenère, Affine, hash (MD5, SHA-1, SHA-256, SHA-512, SHA3-256, CRC32), Base64 dan Hex. Setiap hasil disertai langkah pengerjaan dan masuk riwayat.
- **Animasi 3D**: kartu miring mengikuti kursor dengan efek parallax, flip kartu saat ganti mode, hasil berputar seperti jam flip, kubus 3D di latar belakang.

### 🐛 Fixed
- Klik mouse bisa tidak terbaca ketika tombol bergerak (efek 3D) saat ditekan; tombol kini merespons pada saat mouse ditekan.

## [3.0.0] - 2026-11-07

### 🔧 Changed
- **Dibangun ulang dengan Laravel 12.** Versi PHP lama dipindah ke folder `legacy/`.
- Perhitungan dilakukan di server oleh parser ekspresi yang aman (tanpa `eval`) lewat `POST /calculate` (AJAX). Halaman tidak lagi reload setiap menekan `=`.
- Mode Standar dan Scientific memakai satu layar dan satu riwayat (ditandai ikon mode). Riwayat menyimpan 10 perhitungan terakhir.
- Bootstrap dihapus; CSS ditulis ulang dengan variabel tema.

### ✨ Added
- Tema gelap/terang, dukungan keyboard, tombol backspace (⌫), kurung `( )`, pangkat `xʸ`, `1/x`, `sin⁻¹ cos⁻¹ tan⁻¹`, mode sudut DEG/RAD, indikator memori, klik riwayat untuk memakai hasilnya lagi.
- Persen bergaya kalkulator: `200 + 10% = 220`.
- Test otomatis (PHPUnit) untuk mesin hitung dan endpoint.

### 🐛 Fixed
- Setiap `=` dan setiap fungsi scientific me-reload halaman: hasil langsung hilang, mode kembali ke Standar, memori dan riwayat scientific terhapus.
- Tombol Scientific juga ikut diproses kalkulator Standar (event listener ganda), sehingga satu klik diproses dua kali.
- Pesan `Err: ÷0` tidak pernah terlihat karena langsung ditimpa `0`.
- File suara tidak ada (404) sehingga muncul error di console setiap klik; diganti suara Web Audio + tombol mute.
- Faktorial desimal/negatif salah, dan faktorial angka besar membuat browser hang.
- `tan(90)` menghasilkan angka raksasa, hasil `NaN` ikut tersimpan ke riwayat, angka sangat kecil dibulatkan menjadi 0.
- Grid tombol Scientific berantakan (baris terakhir meluber), CSS Bootstrap menimpa CSS kustom, dan konten terpotong di layar kecil karena `body` dikunci `height: 100vh`.
- Tombol ± dan % tidak bisa dipakai pada hasil perhitungan sebelumnya.
- Riwayat bisa diisi data sembarang lewat POST tanpa validasi/CSRF.

## [2.0.0] - 2024-12-12

### ✨ Added
- **Scientific Calculator Mode**: Complete scientific functions
  - Trigonometric functions (sin, cos, tan)
  - Logarithmic functions (log, ln)
  - Exponential functions (e^x)
  - Factorial calculations (n!)
  - Memory functions (MC, MR, M+, M-)
  - Mathematical constants (π, e)
- **Dual Mode System**: Easy flip between Standard and Scientific modes
- **Enhanced History System**: Separate history for each mode
- **3D Animations**: Smooth flip animations between modes
- **Sound Effects**: Audio feedback for button interactions
- **Advanced Error Handling**: Better error messages and recovery

### 🔧 Changed
- **Complete UI Overhaul**: Modern 3D design with depth effects
- **Improved Responsive Design**: Better mobile and tablet experience
- **Performance Optimizations**: Faster calculations and smoother animations
- **Enhanced Button Feedback**: More satisfying tactile response

### 🐛 Fixed
- Division by zero error handling
- Decimal point multiple input prevention
- Memory overflow protection
- Animation timing issues

## [1.5.0] - 2024-11-15

### ✨ Added
- **Animated History Panel**: Smooth slide-in animations for new calculations
- **Confirmation Dialogs**: Confirmation before clearing history
- **Improved Visual Feedback**: Better button press animations
- **Mobile Optimization**: Enhanced touch targets and responsive layout

### 🔧 Changed
- **History Layout**: Better organization and visual hierarchy
- **Color Scheme**: More modern and accessible color palette
- **Typography**: Improved font choices for better readability

### 🐛 Fixed
- Session management issues
- Mobile layout inconsistencies
- Animation stuttering on slower devices

## [1.0.0] - 2024-10-20

### ✨ Added
- **Standard Calculator**: Basic arithmetic operations (+, -, ×, ÷)
- **Real-time History**: Session-based calculation history
- **Modern UI**: Clean, modern interface with 3D effects
- **Responsive Design**: Works on desktop, tablet, and mobile
- **Error Handling**: Graceful error handling for invalid operations
- **Session Management**: PHP session-based state management

### 🔧 Technical Details
- **Frontend**: HTML5, CSS3, JavaScript ES6+
- **Backend**: PHP 7.0+ with session management
- **Styling**: Custom CSS with Bootstrap 5 framework
- **Icons**: Font Awesome 6.4.0
- **Animations**: CSS3 animations with smooth transitions

### 🎨 Design Features
- **3D Perspective**: Modern depth effects
- **Gradient Backgrounds**: Beautiful color gradients
- **Smooth Animations**: Buttery smooth micro-interactions
- **Accessible Design**: High contrast and readable fonts

## [0.5.0-beta] - 2024-09-15

### ✨ Added
- **Initial Beta Release**: Basic calculator functionality
- **Core Operations**: Addition, subtraction, multiplication, division
- **Basic UI**: Simple calculator interface
- **History Feature**: Basic calculation history

### 🔧 Technical Implementation
- **PHP Backend**: Server-side calculation processing
- **JavaScript Frontend**: Client-side user interactions
- **CSS Styling**: Basic responsive design

---

## 🏷️ Version Tags

| Version | Date | Type | Description |
|---------|------|------|-------------|
| **2.0.0** | 2024-12-12 | Major | Scientific calculator + dual mode |
| **1.5.0** | 2024-11-15 | Minor | Enhanced UI/UX and animations |
| **1.0.0** | 2024-10-20 | Major | Initial stable release |
| **0.5.0-beta** | 2024-09-15 | Beta | Initial beta release |

## 📈 Statistics

### Features Evolution
- **v0.5.0**: 4 basic operations
- **v1.0.0**: + History system, Modern UI
- **v1.5.0**: + Enhanced animations, Mobile optimization
- **v2.0.0**: + Scientific mode, Sound effects, Advanced features

### Code Growth
- **Lines of Code**: 150 → 620+ (314% increase)
- **Features**: 4 → 25+ (525% increase)
- **Supported Operations**: 4 → 15+ (275% increase)

## 🎯 Migration Guide

### From v1.x to v2.0.0

#### New Features Available
- Access scientific mode via "Flip" button
- Memory functions in scientific mode
- Enhanced history with separate tracking

#### Breaking Changes
- None - fully backward compatible

#### Recommended Actions
1. Clear browser cache for best experience
2. Test scientific functions
3. Explore new animation effects

### From v0.x to v1.0.0

#### Major Changes
- Complete UI redesign
- History system introduction
- Responsive design implementation

#### Migration Steps
1. No data migration needed
2. Update bookmarks to new interface
3. Familiarize with new features

## 🔮 Future Roadmap

### Version 3.0.0 (Planned Q1 2025)
- **Theme System**: Multiple color themes
- **PWA Support**: Install as app
- **Cloud Sync**: Sync history across devices
- **Advanced Functions**: Matrix operations, unit conversions

### Version 2.5.0 (Planned Q4 2024)
- **Keyboard Shortcuts**: Full keyboard navigation
- **Gesture Support**: Swipe gestures for mobile
- **Export Features**: Export calculations to PDF/CSV
- **Accessibility**: Full WCAG compliance

### Version 2.1.0 (Planned Q3 2024)
- **Bug Fixes**: Performance improvements
- **New Functions**: Hyperbolic functions, probability
- **UI Polish**: Micro-animation improvements

---

## 🤝 Contributing to Changelog

When contributing to this project, please:

1. **Follow the format**: Use the established emoji and structure
2. **Categorize changes**: Use Added, Changed, Fixed, Removed sections
3. **Be descriptive**: Explain the impact of changes
4. **Add technical details**: Include relevant technical information

### Emoji Guide
- ✨ **Added**: New features
- 🔧 **Changed**: Changes in existing functionality
- 🐛 **Fixed**: Bug fixes
- 🗑️ **Removed**: Removed features
- 🚀 **Performance**: Performance improvements
- 🎨 **Design**: UI/UX changes
- 📚 **Documentation**: Documentation updates

---

*This changelog is automatically updated with each release. For more detailed information about specific changes, check the [commit history](https://github.com/whympxx/Kalkulator-Standar-Scientific/commits/main).*
