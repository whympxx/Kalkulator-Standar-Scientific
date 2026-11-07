<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Kalkulator Modern') }}</title>
    <script>
        // Terapkan tema sebelum render agar tidak berkedip.
        try {
            const t = localStorage.getItem('calc.theme');
            if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.dataset.theme = 'dark';
            }
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/calculator.css') }}">
</head>
<body>
{{-- Kubus 3D dekoratif di latar belakang --}}
<div class="bg-scene" aria-hidden="true">
    @foreach ([
        ['s' => 90,  'x' => '6%',  'y' => '14%', 't' => '26s', 'd' => '0s'],
        ['s' => 60,  'x' => '86%', 'y' => '10%', 't' => '20s', 'd' => '-4s'],
        ['s' => 120, 'x' => '78%', 'y' => '68%', 't' => '34s', 'd' => '-9s'],
        ['s' => 50,  'x' => '14%', 'y' => '78%', 't' => '18s', 'd' => '-2s'],
        ['s' => 70,  'x' => '48%', 'y' => '88%', 't' => '24s', 'd' => '-12s'],
    ] as $cube)
        <div class="cube-float" style="--x: {{ $cube['x'] }}; --y: {{ $cube['y'] }}; --d: {{ $cube['d'] }};">
            <div class="cube" style="--s: {{ $cube['s'] }}px; --t: {{ $cube['t'] }};">
                <span></span><span></span><span></span><span></span><span></span><span></span>
            </div>
        </div>
    @endforeach
</div>

<header class="topbar">
    <h1 class="brand"><i class="fa-solid fa-calculator"></i> {{ config('app.name', 'Kalkulator Modern') }}</h1>
    <div class="topbar-actions">
        <button type="button" class="icon-btn" id="soundToggle" title="Suara tombol" aria-pressed="true">
            <i class="fa-solid fa-volume-high"></i>
        </button>
        <button type="button" class="icon-btn" id="themeToggle" title="Ganti tema terang/gelap">
            <i class="fa-solid fa-moon"></i>
        </button>
    </div>
</header>

<main class="layout">
    <section class="calculator tilt" data-tilt="8" aria-label="Kalkulator">
        <div class="mode-switch" role="tablist" aria-label="Mode kalkulator">
            <button type="button" role="tab" class="mode-btn active" data-mode="standard" aria-selected="true">
                <i class="fa-solid fa-calculator"></i> Standar
            </button>
            <button type="button" role="tab" class="mode-btn" data-mode="scientific" aria-selected="false">
                <i class="fa-solid fa-flask"></i> Scientific
            </button>
            <button type="button" role="tab" class="mode-btn" data-mode="crypto" aria-selected="false">
                <i class="fa-solid fa-key"></i> Kriptografi
            </button>
        </div>

        {{-- Panel kriptografi --}}
        <div class="crypto" id="cryptoPanel" hidden>
            <div class="crypto-tools" role="tablist" aria-label="Alat kriptografi">
                <button type="button" class="tool-btn active" data-tool="modular"><i class="fa-solid fa-percent"></i> Modular</button>
                <button type="button" class="tool-btn" data-tool="rsa"><i class="fa-solid fa-key"></i> RSA</button>
                <button type="button" class="tool-btn" data-tool="caesar"><i class="fa-solid fa-rotate"></i> Caesar</button>
                <button type="button" class="tool-btn" data-tool="vigenere"><i class="fa-solid fa-table-cells"></i> Vigenère</button>
                <button type="button" class="tool-btn" data-tool="affine"><i class="fa-solid fa-wave-square"></i> Affine</button>
                <button type="button" class="tool-btn" data-tool="hash"><i class="fa-solid fa-fingerprint"></i> Hash</button>
            </div>

            {{-- Aritmetika modular --}}
            <form class="crypto-form" data-tool="modular" data-endpoint="modular" novalidate>
                <label class="field">
                    <span>Operasi</span>
                    <select name="operation" data-switch>
                        <option value="powmod">Pangkat modular: aᵇ mod n</option>
                        <option value="mod">Sisa bagi: a mod n</option>
                        <option value="inverse">Invers modular: a⁻¹ mod n</option>
                        <option value="gcd">GCD &amp; Euclid diperluas</option>
                        <option value="prime">Cek bilangan prima</option>
                        <option value="totient">Fungsi Euler φ(n)</option>
                    </select>
                </label>
                <div class="field-row">
                    <label class="field"><span data-label-a>a</span><input name="a" inputmode="numeric" autocomplete="off" placeholder="mis. 4"></label>
                    <label class="field" data-for="powmod gcd"><span data-label-b>b</span><input name="b" inputmode="numeric" autocomplete="off" placeholder="mis. 13"></label>
                    <label class="field" data-for="mod powmod inverse"><span>n (modulus)</span><input name="n" inputmode="numeric" autocomplete="off" placeholder="mis. 497"></label>
                </div>
                <div class="crypto-actions">
                    <button type="submit" class="btn-crypto primary">Hitung</button>
                </div>
            </form>

            {{-- RSA --}}
            <form class="crypto-form" data-tool="rsa" data-endpoint="rsa" novalidate hidden>
                <div class="field-row">
                    <label class="field"><span>p (prima)</span><input name="p" inputmode="numeric" autocomplete="off" placeholder="61"></label>
                    <label class="field"><span>q (prima)</span><input name="q" inputmode="numeric" autocomplete="off" placeholder="53"></label>
                    <label class="field"><span>e (opsional)</span><input name="e" inputmode="numeric" autocomplete="off" placeholder="otomatis"></label>
                </div>
                <label class="field">
                    <span>Format pesan</span>
                    <select name="format">
                        <option value="number">Angka (m &lt; n)</option>
                        <option value="text">Teks (per karakter)</option>
                    </select>
                </label>
                <label class="field"><span>Pesan / cipherteks</span><textarea name="message" rows="2" data-main placeholder="mis. 65 — atau angka cipherteks dipisah spasi"></textarea></label>
                <div class="crypto-actions">
                    <button type="submit" class="btn-crypto" name="action" value="keys">Buat Kunci</button>
                    <button type="submit" class="btn-crypto primary" name="action" value="encrypt"><i class="fa-solid fa-lock"></i> Enkripsi</button>
                    <button type="submit" class="btn-crypto primary" name="action" value="decrypt"><i class="fa-solid fa-lock-open"></i> Dekripsi</button>
                    <button type="button" class="btn-crypto ghost" data-example='{"p":"61","q":"53","e":"17","message":"65"}'>Contoh</button>
                </div>
            </form>

            {{-- Caesar --}}
            <form class="crypto-form" data-tool="caesar" data-endpoint="cipher" novalidate hidden>
                <input type="hidden" name="cipher" value="caesar">
                <label class="field"><span>Teks</span><textarea name="text" rows="3" data-main placeholder="Hello, World!"></textarea></label>
                <label class="field field-small"><span>Geseran (k)</span><input name="shift" type="number" value="3"></label>
                <div class="crypto-actions">
                    <button type="submit" class="btn-crypto primary" name="action" value="encrypt"><i class="fa-solid fa-lock"></i> Enkripsi</button>
                    <button type="submit" class="btn-crypto primary" name="action" value="decrypt"><i class="fa-solid fa-lock-open"></i> Dekripsi</button>
                    <button type="submit" class="btn-crypto" name="action" value="bruteforce"><i class="fa-solid fa-hammer"></i> Brute force</button>
                </div>
            </form>

            {{-- Vigenère --}}
            <form class="crypto-form" data-tool="vigenere" data-endpoint="cipher" novalidate hidden>
                <input type="hidden" name="cipher" value="vigenere">
                <label class="field"><span>Teks</span><textarea name="text" rows="3" data-main placeholder="ATTACK AT DAWN"></textarea></label>
                <label class="field"><span>Kunci</span><input name="key" autocomplete="off" placeholder="LEMON"></label>
                <div class="crypto-actions">
                    <button type="submit" class="btn-crypto primary" name="action" value="encrypt"><i class="fa-solid fa-lock"></i> Enkripsi</button>
                    <button type="submit" class="btn-crypto primary" name="action" value="decrypt"><i class="fa-solid fa-lock-open"></i> Dekripsi</button>
                </div>
            </form>

            {{-- Affine --}}
            <form class="crypto-form" data-tool="affine" data-endpoint="cipher" novalidate hidden>
                <input type="hidden" name="cipher" value="affine">
                <label class="field"><span>Teks</span><textarea name="text" rows="3" data-main placeholder="AFFINE CIPHER"></textarea></label>
                <div class="field-row">
                    <label class="field"><span>a (koprima 26)</span><input name="a" type="number" value="5"></label>
                    <label class="field"><span>b</span><input name="b" type="number" value="8"></label>
                </div>
                <div class="crypto-actions">
                    <button type="submit" class="btn-crypto primary" name="action" value="encrypt"><i class="fa-solid fa-lock"></i> Enkripsi</button>
                    <button type="submit" class="btn-crypto primary" name="action" value="decrypt"><i class="fa-solid fa-lock-open"></i> Dekripsi</button>
                </div>
            </form>

            {{-- Hash & encoding --}}
            <form class="crypto-form" data-tool="hash" data-endpoint="hash" novalidate hidden>
                <label class="field"><span>Teks</span><textarea name="text" rows="3" data-main placeholder="abc"></textarea></label>
                <div class="crypto-actions">
                    <button type="submit" class="btn-crypto primary" name="action" value="hash"><i class="fa-solid fa-fingerprint"></i> Hash</button>
                    <button type="submit" class="btn-crypto" name="action" value="base64_encode">Base64 →</button>
                    <button type="submit" class="btn-crypto" name="action" value="base64_decode">← Base64</button>
                    <button type="submit" class="btn-crypto" name="action" value="hex_encode">Hex →</button>
                    <button type="submit" class="btn-crypto" name="action" value="hex_decode">← Hex</button>
                </div>
            </form>

            <div class="crypto-error" id="cryptoError" role="alert" hidden></div>

            <div class="crypto-output" id="cryptoOutput" hidden>
                <div class="crypto-output-head">
                    <span><i class="fa-solid fa-square-check"></i> Hasil</span>
                    <div>
                        <button type="button" class="icon-btn small" id="cryptoReuse" title="Pakai hasil sebagai input"><i class="fa-solid fa-arrow-turn-up"></i></button>
                        <button type="button" class="icon-btn small" id="cryptoCopy" title="Salin hasil"><i class="fa-regular fa-copy"></i></button>
                    </div>
                </div>
                <output class="crypto-result" id="cryptoResult"></output>
                <dl class="crypto-details" id="cryptoDetails"></dl>
                <details class="crypto-steps" id="cryptoStepsBox" open>
                    <summary>Langkah pengerjaan</summary>
                    <ol id="cryptoSteps"></ol>
                </details>
            </div>
        </div>

        <div class="display" id="calcDisplay" aria-live="polite">
            <div class="display-status">
                <span id="angleIndicator" class="badge" hidden>DEG</span>
                <span id="memoryIndicator" class="badge" hidden title="Ada nilai di memori">M</span>
            </div>
            <div class="display-expression" id="displayExpression">&nbsp;</div>
            <div class="display-value" id="displayValue">0</div>
        </div>

        <div class="keypad-wrapper" id="keypadWrapper">
            {{-- Keypad standar --}}
            <div class="keypad keypad-standard" id="keypadStandard" data-keypad="standard">
                <button class="key key-clear" data-action="clear" title="Hapus semua (Esc)">AC</button>
                <button class="key key-fn" data-action="backspace" title="Hapus satu (Backspace)"><i class="fa-solid fa-delete-left"></i></button>
                <button class="key key-fn" data-insert="%" title="Persen">%</button>
                <button class="key key-op" data-insert="/" title="Bagi">÷</button>

                <button class="key" data-insert="7">7</button>
                <button class="key" data-insert="8">8</button>
                <button class="key" data-insert="9">9</button>
                <button class="key key-op" data-insert="*" title="Kali">×</button>

                <button class="key" data-insert="4">4</button>
                <button class="key" data-insert="5">5</button>
                <button class="key" data-insert="6">6</button>
                <button class="key key-op" data-insert="-" title="Kurang">−</button>

                <button class="key" data-insert="1">1</button>
                <button class="key" data-insert="2">2</button>
                <button class="key" data-insert="3">3</button>
                <button class="key key-op" data-insert="+" title="Tambah">+</button>

                <button class="key key-fn" data-action="sign" title="Ubah tanda">±</button>
                <button class="key" data-insert="0">0</button>
                <button class="key" data-insert=".">.</button>
                <button class="key key-equal" data-action="equals" title="Hitung (Enter)">=</button>
            </div>

            {{-- Keypad scientific --}}
            <div class="keypad keypad-scientific" id="keypadScientific" data-keypad="scientific" hidden>
                <button class="key key-mem" data-action="mc" title="Hapus memori">MC</button>
                <button class="key key-mem" data-action="mr" title="Panggil memori">MR</button>
                <button class="key key-mem" data-action="mplus" title="Tambah ke memori">M+</button>
                <button class="key key-mem" data-action="mminus" title="Kurangi dari memori">M−</button>
                <button class="key key-mem" data-action="angle" id="angleToggle" title="Ganti satuan sudut">DEG</button>

                <button class="key key-fn" data-insert="sin(">sin</button>
                <button class="key key-fn" data-insert="cos(">cos</button>
                <button class="key key-fn" data-insert="tan(">tan</button>
                <button class="key key-fn" data-insert="(">(</button>
                <button class="key key-fn" data-insert=")">)</button>

                <button class="key key-fn" data-insert="asin(">sin⁻¹</button>
                <button class="key key-fn" data-insert="acos(">cos⁻¹</button>
                <button class="key key-fn" data-insert="atan(">tan⁻¹</button>
                <button class="key key-fn" data-insert="^" title="Pangkat">xʸ</button>
                <button class="key key-fn" data-insert="!" title="Faktorial">n!</button>

                <button class="key key-fn" data-insert="sqrt(" title="Akar kuadrat">√</button>
                <button class="key key-fn" data-insert="^2" title="Kuadrat">x²</button>
                <button class="key key-fn" data-insert="log(" title="Logaritma basis 10">log</button>
                <button class="key key-fn" data-insert="ln(" title="Logaritma natural">ln</button>
                <button class="key key-fn" data-insert="exp(" title="e pangkat x">eˣ</button>

                <button class="key key-fn" data-insert="pi">π</button>
                <button class="key key-fn" data-insert="e">e</button>
                <button class="key key-fn" data-action="reciprocal" title="Satu per x">1/x</button>
                <button class="key key-fn" data-action="sign" title="Ubah tanda">±</button>
                <button class="key key-fn" data-insert="%" title="Persen">%</button>

                <button class="key" data-insert="7">7</button>
                <button class="key" data-insert="8">8</button>
                <button class="key" data-insert="9">9</button>
                <button class="key key-clear" data-action="clear" title="Hapus semua (Esc)">AC</button>
                <button class="key key-fn" data-action="backspace" title="Hapus satu (Backspace)"><i class="fa-solid fa-delete-left"></i></button>

                <button class="key" data-insert="4">4</button>
                <button class="key" data-insert="5">5</button>
                <button class="key" data-insert="6">6</button>
                <button class="key key-op" data-insert="*" title="Kali">×</button>
                <button class="key key-op" data-insert="/" title="Bagi">÷</button>

                <button class="key" data-insert="1">1</button>
                <button class="key" data-insert="2">2</button>
                <button class="key" data-insert="3">3</button>
                <button class="key key-op" data-insert="+" title="Tambah">+</button>
                <button class="key key-op" data-insert="-" title="Kurang">−</button>

                <button class="key span-2" data-insert="0">0</button>
                <button class="key" data-insert=".">.</button>
                <button class="key key-equal span-2" data-action="equals" title="Hitung (Enter)">=</button>
            </div>
        </div>

        <p class="hint" id="keyboardHint"><i class="fa-regular fa-keyboard"></i> Bisa pakai keyboard: angka, + − * / ^ ( ) ! %, Enter, Backspace, Esc.</p>
    </section>

    <aside class="history tilt" data-tilt="4" aria-label="Riwayat perhitungan">
        <div class="history-header">
            <h2><i class="fa-solid fa-clock-rotate-left"></i> Riwayat</h2>
            <span class="history-count" id="historyCount"></span>
        </div>
        <p class="history-empty" id="historyEmpty">Belum ada perhitungan.</p>
        <ul class="history-list" id="historyList"></ul>
        <button type="button" class="btn-clear-history" id="clearHistory">
            <i class="fa-solid fa-trash"></i> Hapus Riwayat
        </button>
        <p class="history-note">Klik riwayat untuk memakai hasilnya lagi (riwayat kriptografi disalin ke clipboard). Menyimpan {{ $historyLimit }} perhitungan terakhir.</p>
    </aside>
</main>

<div class="toast" id="toast" role="status" hidden></div>

<script>
    window.CALCULATOR = {
        history: @json($history),
        historyLimit: {{ $historyLimit }},
        routes: {
            calculate: @json(route('calculator.calculate', absolute: false)),
            history: @json(route('calculator.history', absolute: false)),
            crypto: {
                modular: @json(route('crypto.modular', absolute: false)),
                rsa: @json(route('crypto.rsa', absolute: false)),
                cipher: @json(route('crypto.cipher', absolute: false)),
                hash: @json(route('crypto.hash', absolute: false)),
            },
        },
    };
</script>
<script src="{{ asset('js/calculator.js') }}"></script>
</body>
</html>
