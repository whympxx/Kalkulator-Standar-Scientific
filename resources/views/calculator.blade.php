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
    <section class="calculator" aria-label="Kalkulator">
        <div class="mode-switch" role="tablist" aria-label="Mode kalkulator">
            <button type="button" role="tab" class="mode-btn active" data-mode="standard" aria-selected="true">
                <i class="fa-solid fa-calculator"></i> Standar
            </button>
            <button type="button" role="tab" class="mode-btn" data-mode="scientific" aria-selected="false">
                <i class="fa-solid fa-flask"></i> Scientific
            </button>
        </div>

        <div class="display" aria-live="polite">
            <div class="display-status">
                <span id="angleIndicator" class="badge" hidden>DEG</span>
                <span id="memoryIndicator" class="badge" hidden title="Ada nilai di memori">M</span>
            </div>
            <div class="display-expression" id="displayExpression">&nbsp;</div>
            <div class="display-value" id="displayValue">0</div>
        </div>

        <div class="keypad-wrapper">
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

        <p class="hint"><i class="fa-regular fa-keyboard"></i> Bisa pakai keyboard: angka, + − * / ^ ( ) ! %, Enter, Backspace, Esc.</p>
    </section>

    <aside class="history" aria-label="Riwayat perhitungan">
        <div class="history-header">
            <h2><i class="fa-solid fa-clock-rotate-left"></i> Riwayat</h2>
            <span class="history-count" id="historyCount"></span>
        </div>
        <p class="history-empty" id="historyEmpty">Belum ada perhitungan.</p>
        <ul class="history-list" id="historyList"></ul>
        <button type="button" class="btn-clear-history" id="clearHistory">
            <i class="fa-solid fa-trash"></i> Hapus Riwayat
        </button>
        <p class="history-note">Klik salah satu riwayat untuk memakai hasilnya lagi. Menyimpan {{ $historyLimit }} perhitungan terakhir.</p>
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
        },
    };
</script>
<script src="{{ asset('js/calculator.js') }}"></script>
</body>
</html>
