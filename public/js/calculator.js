/**
 * Kalkulator Modern — logika antarmuka.
 * Perhitungan dilakukan di server (Laravel) lewat POST /calculate,
 * jadi halaman tidak pernah reload dan riwayat tersimpan di session.
 */
(function () {
    'use strict';

    const config = window.CALCULATOR || { history: [], routes: {} };
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const OPERATORS = ['+', '-', '*', '/', '^'];
    const FUNCTIONS = ['asin(', 'acos(', 'atan(', 'sqrt(', 'sin(', 'cos(', 'tan(', 'log(', 'exp(', 'ln(', 'abs('];

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const el = {
        card: document.querySelector('.calculator'),
        expression: document.getElementById('displayExpression'),
        value: document.getElementById('displayValue'),
        angleIndicator: document.getElementById('angleIndicator'),
        memoryIndicator: document.getElementById('memoryIndicator'),
        angleToggle: document.getElementById('angleToggle'),
        keypads: {
            standard: document.getElementById('keypadStandard'),
            scientific: document.getElementById('keypadScientific'),
        },
        modeButtons: document.querySelectorAll('.mode-btn'),
        historyList: document.getElementById('historyList'),
        historyEmpty: document.getElementById('historyEmpty'),
        historyCount: document.getElementById('historyCount'),
        clearHistory: document.getElementById('clearHistory'),
        soundToggle: document.getElementById('soundToggle'),
        themeToggle: document.getElementById('themeToggle'),
        toast: document.getElementById('toast'),
        calcDisplay: document.getElementById('calcDisplay'),
        keypadWrapper: document.getElementById('keypadWrapper'),
        keyboardHint: document.getElementById('keyboardHint'),
        cryptoPanel: document.getElementById('cryptoPanel'),
        graphPanel: document.getElementById('graphPanel'),
        preview: document.getElementById('displayPreview'),
        shareBtn: document.getElementById('shareBtn'),
        exportHistory: document.getElementById('exportHistory'),
    };

    const MODES = ['standard', 'scientific', 'crypto', 'graph'];
    /** Mode yang memakai panel formulir, bukan keypad. */
    const isPanelMode = (mode) => mode === 'crypto' || mode === 'graph';

    const storage = {
        get(key, fallback) {
            try {
                const v = localStorage.getItem('calc.' + key);
                return v === null ? fallback : JSON.parse(v);
            } catch (e) {
                return fallback;
            }
        },
        set(key, value) {
            try { localStorage.setItem('calc.' + key, JSON.stringify(value)); } catch (e) { /* abaikan */ }
        },
    };

    const state = {
        expr: '',            // ekspresi internal (ASCII): 2*sin(30)+pi
        lastExpr: '',        // ekspresi terakhir yang dihitung
        result: null,        // hasil terakhir (string)
        error: null,
        justEvaluated: false,
        busy: false,
        mode: storage.get('mode', 'standard'),
        angle: storage.get('angle', 'deg'),
        memory: Number(storage.get('memory', 0)) || 0,
        sound: storage.get('sound', true),
        history: Array.isArray(config.history) ? config.history : [],
    };

    // ---------- Util ekspresi ----------

    /** Ubah ekspresi internal menjadi teks yang enak dibaca. */
    function pretty(expr) {
        return expr
            .replace(/sqrt\(/g, '√(')
            .replace(/asin\(/g, 'sin⁻¹(')
            .replace(/acos\(/g, 'cos⁻¹(')
            .replace(/atan\(/g, 'tan⁻¹(')
            .replace(/pi/g, 'π')
            .replace(/\*/g, '×')
            .replace(/\//g, '÷')
            .replace(/(\d)e([+-])(\d)/g, '$1E$2$3') // notasi ilmiah: 1E+21
            .replace(/-/g, '−');
    }

    const lastChar = () => state.expr.slice(-1);
    const endsWithOperator = () => OPERATORS.includes(lastChar());
    const endsWithOpenParen = () => lastChar() === '(';
    const openParens = () => (state.expr.match(/\(/g) || []).length - (state.expr.match(/\)/g) || []).length;

    /** Angka terakhir di ujung ekspresi (mis. "12.5" pada "3+12.5"). */
    function trailingNumber() {
        const m = state.expr.match(/(?:\d+\.?\d*(?:e[+-]?\d+)?|\.\d+)$/);
        return m ? m[0] : '';
    }

    /** Ekspresi berupa satu angka saja (boleh negatif)? */
    function isPlainNumber(s) {
        return /^-?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?$/.test(s);
    }

    /** Mulai dari hasil sebelumnya bila baru saja menekan "=". */
    function continueFromResult() {
        if (state.justEvaluated) {
            state.expr = state.result !== null ? state.result : '';
            state.justEvaluated = false;
        }
        state.error = null;
    }

    /** Mulai ekspresi baru bila baru saja menekan "=". */
    function startFresh() {
        if (state.justEvaluated) {
            state.expr = '';
            state.justEvaluated = false;
        }
        state.error = null;
    }

    // ---------- Aksi tombol ----------

    function insert(token) {
        if (state.busy) return;

        if (/^\d$/.test(token)) return insertDigit(token);
        if (token === '.') return insertDot();
        if (OPERATORS.includes(token)) return insertOperator(token);
        if (token === '^2') {
            insertPostfixLike('^2');
            return;
        }
        if (token === '!' || token === '%') return insertPostfixLike(token);
        if (token === ')') return insertCloseParen();

        // Fungsi, "(", konstanta π / e → mulai baru jika setelah "=".
        startFresh();
        if (token === 'e' && /\d$/.test(state.expr)) {
            state.expr += '*'; // cegah "2e" terbaca sebagai notasi ilmiah
        }
        state.expr += token;
        render();
    }

    function insertDigit(d) {
        startFresh();
        const num = trailingNumber();
        if (num === '0') {
            state.expr = state.expr.slice(0, -1); // hindari "007"
        } else if (/[)!%]$|pi$|(?:^|[^a-z])e$/.test(state.expr)) {
            state.expr += '*'; // 2π lalu 3 → 2π×3
        }
        state.expr += d;
        render();
    }

    function insertDot() {
        startFresh();
        const num = trailingNumber();
        if (num.includes('.') || num.includes('e')) return;
        if (num === '') {
            if (/[)!%]$|pi$|e$/.test(state.expr)) state.expr += '*';
            state.expr += '0';
        }
        state.expr += '.';
        render();
    }

    function insertOperator(op) {
        continueFromResult();

        if (state.expr === '') {
            if (op === '-') state.expr = '-';
            else if (state.result !== null) state.expr = state.result + op;
            render();
            return;
        }

        if (endsWithOpenParen()) {
            if (op === '-') state.expr += op; // minus di awal kurung: (−5
            render();
            return;
        }

        if (endsWithOperator()) {
            const prev = lastChar();
            // Izinkan minus setelah ×, ÷, ^ (contoh 2×−3), selain itu ganti operator.
            if (op === '-' && ['*', '/', '^'].includes(prev)) {
                state.expr += op;
            } else {
                state.expr = state.expr.replace(/[+\-*/^]+$/, '');
                if (state.expr === '' && op !== '-') {
                    render();
                    return;
                }
                state.expr += op;
            }
            render();
            return;
        }

        if (lastChar() === '.') state.expr += '0';
        state.expr += op;
        render();
    }

    function insertPostfixLike(token) {
        continueFromResult();
        if (state.expr === '' || endsWithOperator() || endsWithOpenParen()) return;
        state.expr += token;
        render();
    }

    function insertCloseParen() {
        startFresh();
        if (openParens() <= 0 || endsWithOperator() || endsWithOpenParen()) return;
        state.expr += ')';
        render();
    }

    function backspace() {
        if (state.busy) return;
        if (state.justEvaluated) {
            // Kembali mengedit ekspresi sebelumnya.
            state.expr = state.lastExpr;
            state.justEvaluated = false;
        }
        state.error = null;
        const fn = FUNCTIONS.find((f) => state.expr.endsWith(f));
        if (fn) state.expr = state.expr.slice(0, -fn.length);
        else if (state.expr.endsWith('pi')) state.expr = state.expr.slice(0, -2);
        else state.expr = state.expr.slice(0, -1);
        render();
    }

    function clearAll() {
        if (state.busy) return;
        state.expr = '';
        state.lastExpr = '';
        state.result = null;
        state.error = null;
        state.justEvaluated = false;
        render();
    }

    function toggleSign() {
        if (state.busy) return;
        continueFromResult();

        if (state.expr === '') {
            state.expr = '-';
            render();
            return;
        }

        const num = trailingNumber() || (state.expr.match(/(?:pi|e)$/) || [''])[0];
        if (!num) return; // tidak ada angka di ujung (mis. setelah ")")

        const start = state.expr.length - num.length;
        const before = state.expr.slice(0, start);
        const prevChar = before.slice(-1);
        const unaryMinus = prevChar === '-' && (before.length === 1 || /[+\-*/^(]$/.test(before.slice(0, -1)));

        state.expr = unaryMinus
            ? before.slice(0, -1) + num
            : before + '-' + num;
        render();
    }

    function reciprocal() {
        if (state.busy) return;
        continueFromResult();
        state.expr = state.expr === '' ? '1/(' : '1/(' + state.expr + ')';
        render();
    }

    async function equals() {
        if (state.busy || state.justEvaluated) return state.result;

        let expr = state.expr;
        if (expr === '' || expr === '-') return null;

        // Rapikan: buang operator menggantung & tutup kurung otomatis.
        expr = tidyExpr(expr);
        if (expr === '') return null;

        state.busy = true;
        state.expr = expr;
        render();

        try {
            const data = await api(config.routes.calculate, 'POST', {
                expression: expr,
                mode: state.mode,
                angle: state.angle,
            });
            state.lastExpr = expr;
            state.result = data.result;
            state.error = null;
            state.justEvaluated = true;
            state.history = data.history;
            renderHistory();
            replayAnimation(el.value, 'anim-result');
            return data.result;
        } catch (err) {
            state.error = err.message;
            state.justEvaluated = false;
            replayAnimation(el.value, 'anim-error');
            return null;
        } finally {
            state.busy = false;
            render();
            setTimeout(drainQueue, 0);
        }
    }

    // ---------- Antrian input ----------
    // Tombol yang ditekan saat menunggu server tidak hilang, tapi dijalankan berurutan.
    const queue = [];

    function dispatch(fn) {
        if (state.busy || queue.length) {
            queue.push(fn);
            return;
        }
        fn();
    }

    async function drainQueue() {
        while (!state.busy && queue.length) {
            await queue.shift()();
        }
    }

    // ---------- Memori ----------

    async function currentValue() {
        if (state.justEvaluated) return state.result;
        if (state.expr === '') return state.result !== null ? state.result : '0';
        if (isPlainNumber(state.expr)) return state.expr;
        return equals(); // hitung dulu ekspresinya
    }

    async function memoryAdd(sign) {
        const v = await currentValue();
        if (v === null) return;
        state.memory += sign * Number(v);
        // Bulatkan galat floating point kecil.
        state.memory = Number(state.memory.toPrecision(12));
        storage.set('memory', state.memory);
        showToast('Memori: ' + formatNumber(state.memory));
        render();
    }

    function memoryRecall() {
        if (state.busy) return;
        startFresh();
        const value = formatNumber(state.memory);
        if (/[\d)!%]$|pi$|e$/.test(state.expr)) state.expr += '*';
        state.expr += value.startsWith('-') && endsWithOperator() ? '(' + value + ')' : value;
        render();
    }

    function memoryClear() {
        state.memory = 0;
        storage.set('memory', 0);
        showToast('Memori dihapus');
        render();
    }

    function formatNumber(n) {
        return String(Number(n.toPrecision(12)));
    }

    // ---------- Server ----------

    async function api(url, method, body) {
        let response;
        try {
            response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body ? JSON.stringify(body) : undefined,
                credentials: 'same-origin',
            });
        } catch (e) {
            throw new Error('Tidak bisa terhubung ke server.');
        }

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 419) throw new Error('Sesi kedaluwarsa, muat ulang halaman.');
            if (response.status === 429) throw new Error('Terlalu banyak permintaan, tunggu sebentar.');
            throw new Error(data.message || 'Terjadi kesalahan (' + response.status + ').');
        }
        return data;
    }

    async function clearHistory() {
        if (state.history.length === 0) return;
        if (!confirm('Apakah Anda yakin ingin menghapus seluruh riwayat?')) return;
        try {
            const data = await api(config.routes.history, 'DELETE');
            state.history = data.history;
            renderHistory();
            showToast('Riwayat dihapus');
        } catch (err) {
            showToast(err.message);
        }
    }

    // ---------- Tampilan ----------

    function render() {
        const { expression, value } = el;
        value.classList.toggle('is-error', !!state.error);
        value.classList.toggle('is-busy', state.busy);

        if (state.error) {
            expression.textContent = pretty(state.expr) || ' ';
            value.textContent = state.error;
        } else if (state.justEvaluated) {
            expression.textContent = pretty(state.lastExpr) + ' =';
            value.textContent = pretty(state.result);
        } else {
            expression.textContent = state.result !== null ? 'Ans = ' + pretty(state.result) : ' ';
            value.textContent = pretty(state.expr) || '0';
        }

        // Kecilkan huruf untuk ekspresi panjang dan gulir ke ujung.
        const len = value.textContent.length;
        value.style.fontSize = len > 24 ? '1.3rem' : len > 16 ? '1.75rem' : '';
        value.scrollLeft = value.scrollWidth;

        updatePreview();

        el.memoryIndicator.hidden = state.memory === 0;
        el.angleIndicator.hidden = state.mode !== 'scientific';
        el.angleIndicator.textContent = state.angle.toUpperCase();
        el.angleToggle.textContent = state.angle.toUpperCase();
    }

    function renderHistory() {
        const list = el.historyList;
        list.replaceChildren();

        state.history.forEach((item, idx) => {
            const li = document.createElement('li');
            li.className = 'history-item' + (idx === 0 ? ' is-new' : '');
            li.tabIndex = 0;
            li.title = 'Pakai hasil ini';

            const isCrypto = item.mode === 'crypto';
            if (isCrypto) {
                li.classList.add('is-crypto');
                li.title = 'Salin hasil';
            }

            const icon = document.createElement('span');
            icon.className = 'history-icon';
            icon.innerHTML = {
                scientific: '<i class="fa-solid fa-flask"></i>',
                crypto: '<i class="fa-solid fa-key"></i>',
            }[item.mode] || '<i class="fa-solid fa-calculator"></i>';

            const body = document.createElement('span');
            body.className = 'history-body';

            const exprEl = document.createElement('span');
            exprEl.className = 'history-expression';
            exprEl.textContent = isCrypto ? item.expression : pretty(item.expression) + ' =';

            const resEl = document.createElement('span');
            resEl.className = 'history-result';
            resEl.textContent = isCrypto ? item.result : pretty(item.result);

            body.append(exprEl, resEl);
            li.append(icon, body);

            const use = isCrypto
                ? () => copyText(item.result)
                : () => dispatch(() => {
                    if (isPanelMode(state.mode)) setMode(item.mode, true);
                    state.expr = item.result;
                    state.error = null;
                    state.justEvaluated = false;
                    render();
                });
            li.addEventListener('click', use);
            li.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    e.stopPropagation();
                    use();
                }
            });
            list.appendChild(li);
        });

        const empty = state.history.length === 0;
        el.historyEmpty.hidden = !empty;
        el.clearHistory.disabled = empty;
        el.exportHistory.classList.toggle('is-disabled', empty);
        el.exportHistory.setAttribute('aria-disabled', String(empty));
        el.historyCount.textContent = empty ? '' : state.history.length + '/' + config.historyLimit;
    }

    function setMode(mode, animate) {
        state.mode = MODES.includes(mode) ? mode : 'standard';
        storage.set('mode', state.mode);

        el.modeButtons.forEach((btn) => {
            const active = btn.dataset.mode === state.mode;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', String(active));
        });

        const showKeypad = () => {
            const panel = isPanelMode(state.mode);
            Object.entries(el.keypads).forEach(([name, pad]) => { pad.hidden = name !== state.mode; });
            el.calcDisplay.hidden = panel;
            el.keypadWrapper.hidden = panel;
            el.keyboardHint.hidden = panel;
            el.cryptoPanel.hidden = state.mode !== 'crypto';
            el.graphPanel.hidden = state.mode !== 'graph';
            // Lepas fokus dari kolom formulir agar shortcut keyboard kalkulator langsung aktif.
            const active = document.activeElement;
            if (!panel && (el.cryptoPanel.contains(active) || el.graphPanel.contains(active))) active.blur();
            render();
            if (state.mode === 'graph') graph.show();
        };

        if (!animate || reduceMotion) {
            showKeypad();
            return;
        }

        // Flip kartu 3D: putar keluar → ganti keypad → putar masuk.
        el.card.classList.remove('card-flip-in', 'is-tilting');
        el.card.classList.add('card-flip-out');
        afterAnimation(el.card, () => {
            el.card.classList.remove('card-flip-out');
            showKeypad();
            el.card.classList.add('card-flip-in');
            afterAnimation(el.card, () => el.card.classList.remove('card-flip-in'));
        });
    }

    /** Jalankan fn setelah animasi CSS elemen ini selesai (abaikan animasi anak). */
    function afterAnimation(node, fn) {
        let done = false;
        const finish = () => {
            if (done) return;
            done = true;
            node.removeEventListener('animationend', onEnd);
            fn();
        };
        const onEnd = (e) => { if (e.target === node) finish(); };
        node.addEventListener('animationend', onEnd);
        setTimeout(finish, 700); // cadangan bila animasi tidak berjalan
    }

    /** Ulang animasi CSS dengan memasang ulang class-nya. */
    function replayAnimation(node, cls) {
        node.classList.remove('anim-result', 'anim-error');
        void node.offsetWidth;
        node.classList.add(cls);
    }

    // ---------- Efek miring 3D mengikuti kursor ----------

    function setupTilt() {
        const canTilt = window.matchMedia('(hover: hover) and (pointer: fine)').matches && !reduceMotion;
        if (!canTilt) return;

        document.querySelectorAll('.tilt').forEach((card) => {
            const max = Number(card.dataset.tilt) || 6;
            let rect = null;

            card.addEventListener('pointerenter', () => { rect = card.getBoundingClientRect(); });
            card.addEventListener('pointermove', (e) => {
                if (card.classList.contains('card-flip-out') || card.classList.contains('card-flip-in')) return;
                // Jangan goyangkan formulir saat mengetik di mode kriptografi.
                if (card === el.card && isPanelMode(state.mode)) {
                    card.classList.remove('is-tilting');
                    return;
                }
                rect = rect || card.getBoundingClientRect();
                const x = (e.clientX - rect.left) / rect.width;   // 0..1
                const y = (e.clientY - rect.top) / rect.height;   // 0..1
                card.style.setProperty('--ry', ((x - 0.5) * 2 * max).toFixed(2) + 'deg');
                card.style.setProperty('--rx', ((0.5 - y) * 2 * max).toFixed(2) + 'deg');
                card.style.setProperty('--gx', (x * 100).toFixed(1) + '%');
                card.style.setProperty('--gy', (y * 100).toFixed(1) + '%');
                card.classList.add('is-tilting');
            });
            card.addEventListener('pointerleave', () => {
                rect = null;
                card.classList.remove('is-tilting');
            });
        });

        window.addEventListener('scroll', () => {
            document.querySelectorAll('.tilt').forEach((c) => c.classList.remove('is-tilting'));
        }, { passive: true });
    }

    function toggleAngle() {
        state.angle = state.angle === 'deg' ? 'rad' : 'deg';
        storage.set('angle', state.angle);
        showToast('Satuan sudut: ' + (state.angle === 'deg' ? 'derajat' : 'radian'));
        render();
    }

    let toastTimer;
    function showToast(message) {
        el.toast.textContent = message;
        el.toast.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { el.toast.hidden = true; }, 1800);
    }

    // ---------- Suara & tema ----------

    let audioCtx = null;
    function playClick() {
        if (!state.sound) return;
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'triangle';
            osc.frequency.value = 660;
            gain.gain.setValueAtTime(0.08, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.06);
            osc.connect(gain).connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.07);
        } catch (e) { /* browser tanpa Web Audio */ }
    }

    function renderSoundToggle() {
        el.soundToggle.setAttribute('aria-pressed', String(state.sound));
        el.soundToggle.innerHTML = state.sound
            ? '<i class="fa-solid fa-volume-high"></i>'
            : '<i class="fa-solid fa-volume-xmark"></i>';
    }

    function renderThemeToggle() {
        const dark = document.documentElement.dataset.theme === 'dark';
        el.themeToggle.innerHTML = dark ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
    }

    // ---------- Event ----------

    function handleAction(action) {
        switch (action) {
            case 'clear': return clearAll();
            case 'backspace': return backspace();
            case 'equals': return equals();
            case 'sign': return toggleSign();
            case 'reciprocal': return reciprocal();
            case 'mc': return memoryClear();
            case 'mr': return memoryRecall();
            case 'mplus': return memoryAdd(1);
            case 'mminus': return memoryAdd(-1);
            case 'angle': return toggleAngle();
        }
    }

    document.querySelectorAll('.keypad .key').forEach((key) => {
        const press = () => {
            playClick();
            if (key.dataset.insert !== undefined) dispatch(() => insert(key.dataset.insert));
            else if (key.dataset.action) dispatch(() => handleAction(key.dataset.action));
        };
        let pointerPressedAt = 0;

        // Mouse: proses saat ditekan. Tombol bergerak (efek 3D) saat ditekan,
        // sehingga "click" bisa hilang bila mouse dilepas di luar tombol.
        key.addEventListener('pointerdown', (e) => {
            if (e.pointerType !== 'mouse' || e.button !== 0) return;
            e.preventDefault(); // jangan pindahkan fokus
            pointerPressedAt = Date.now();
            press();
        });
        // Sentuh, pena, dan keyboard (Enter/Spasi pada tombol) tetap lewat "click".
        key.addEventListener('click', () => {
            if (Date.now() - pointerPressedAt < 1000) return; // sudah diproses di pointerdown
            press();
        });
    });

    el.modeButtons.forEach((btn) => btn.addEventListener('click', () => {
        if (btn.dataset.mode !== state.mode) {
            playClick();
            setMode(btn.dataset.mode, true);
        }
    }));

    el.clearHistory.addEventListener('click', clearHistory);

    el.soundToggle.addEventListener('click', () => {
        state.sound = !state.sound;
        storage.set('sound', state.sound);
        renderSoundToggle();
        playClick();
    });

    el.themeToggle.addEventListener('click', () => {
        const dark = document.documentElement.dataset.theme !== 'dark';
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        storage.set('theme', dark ? 'dark' : 'light');
        renderThemeToggle();
        if (state.mode === 'graph') graph.draw();
    });

    const KEY_MAP = {
        '+': '+', '-': '-', '*': '*', x: '*', X: '*', '/': '/', '^': '^',
        '(': '(', ')': ')', '!': '!', '%': '%', '.': '.', ',': '.',
        p: 'pi', e: 'e',
    };

    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey || e.metaKey || e.altKey) return;
        const tag = (e.target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
        if (isPanelMode(state.mode)) return; // keyboard hanya untuk kalkulator angka

        let handled = true;
        if (/^\d$/.test(e.key)) dispatch(() => insert(e.key));
        else if (KEY_MAP[e.key] !== undefined) dispatch(() => insert(KEY_MAP[e.key]));
        else if (e.key === 'Enter' || e.key === '=') {
            // Enter pada tombol yang sedang fokus jangan ikut menekan tombol itu.
            if (e.target.closest && e.target.closest('button, .history-item')) e.target.blur();
            dispatch(equals);
        }
        else if (e.key === 'Backspace') dispatch(backspace);
        else if (e.key === 'Escape' || e.key === 'Delete') dispatch(clearAll);
        else handled = false;

        if (handled) {
            e.preventDefault();
            playClick();
        }
    });

    // ---------- Kriptografi ----------

    async function copyText(text, message = 'Disalin ke clipboard') {
        try {
            await navigator.clipboard.writeText(text);
        } catch (e) {
            // Cadangan untuk browser/HTTP tanpa Clipboard API.
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (err) { /* abaikan */ }
            ta.remove();
        }
        showToast(message);
    }

    function setupCrypto() {
        const panel = el.cryptoPanel;
        const forms = panel.querySelectorAll('.crypto-form');
        const toolButtons = panel.querySelectorAll('.tool-btn');
        const out = {
            box: document.getElementById('cryptoOutput'),
            error: document.getElementById('cryptoError'),
            result: document.getElementById('cryptoResult'),
            details: document.getElementById('cryptoDetails'),
            steps: document.getElementById('cryptoSteps'),
            stepsBox: document.getElementById('cryptoStepsBox'),
            copy: document.getElementById('cryptoCopy'),
            reuse: document.getElementById('cryptoReuse'),
        };
        let lastResult = '';
        let busy = false;

        const activeForm = () => [...forms].find((f) => !f.hidden);

        function clearOutput() {
            out.box.hidden = true;
            out.error.hidden = true;
        }

        function setTool(tool) {
            toolButtons.forEach((b) => b.classList.toggle('active', b.dataset.tool === tool));
            forms.forEach((f) => { f.hidden = f.dataset.tool !== tool; });
            storage.set('cryptoTool', tool);
            clearOutput();
        }

        // Tampilkan hanya kolom yang dipakai operasi modular terpilih.
        function updateFields(form) {
            const select = form.querySelector('[data-switch]');
            if (!select) return;
            const op = select.value;
            form.querySelectorAll('[data-for]').forEach((field) => {
                field.hidden = !field.dataset.for.split(' ').includes(op);
            });
            const labelA = form.querySelector('[data-label-a]');
            if (labelA) labelA.textContent = op === 'totient' || op === 'prime' ? 'n' : 'a';
            const labelB = form.querySelector('[data-label-b]');
            if (labelB) labelB.textContent = op === 'powmod' ? 'b (eksponen)' : 'b';
        }

        function renderOutput(data) {
            lastResult = data.result;
            out.result.textContent = data.result;
            out.details.replaceChildren();
            (data.details || []).forEach((d) => {
                const dt = document.createElement('dt');
                dt.textContent = d.label;
                const dd = document.createElement('dd');
                dd.textContent = d.value;
                dd.title = 'Klik untuk menyalin';
                dd.addEventListener('click', () => copyText(d.value));
                out.details.append(dt, dd);
            });
            out.steps.replaceChildren();
            (data.steps || []).forEach((s) => {
                const li = document.createElement('li');
                li.textContent = s;
                out.steps.appendChild(li);
            });
            out.stepsBox.hidden = !(data.steps || []).length;
            out.error.hidden = true;
            out.box.hidden = false;
            replayAnimation(out.result, 'anim-result');
        }

        function showError(message) {
            out.box.hidden = true;
            out.error.textContent = message;
            out.error.hidden = false;
            replayAnimation(out.error, 'anim-error');
        }

        forms.forEach((form) => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (busy) return;

                const payload = Object.fromEntries(new FormData(form).entries());
                // Tombol yang diklik menentukan aksi (Enkripsi/Dekripsi/…).
                const submitter = e.submitter || form.querySelector('[type="submit"]');
                if (submitter && submitter.name) payload[submitter.name] = submitter.value;

                busy = true;
                form.classList.add('is-busy');
                try {
                    const data = await api(config.routes.crypto[form.dataset.endpoint], 'POST', payload);
                    renderOutput(data);
                    state.history = data.history;
                    renderHistory();
                } catch (err) {
                    showError(err.message);
                } finally {
                    busy = false;
                    form.classList.remove('is-busy');
                }
            });

            const select = form.querySelector('[data-switch]');
            if (select) {
                select.addEventListener('change', () => { updateFields(form); clearOutput(); });
                updateFields(form);
            }

            const swap = form.querySelector('[data-swap]');
            if (swap) {
                swap.addEventListener('click', () => {
                    const { from_base: fromSel, to_base: toSel } = form.elements;
                    [fromSel.value, toSel.value] = [toSel.value, fromSel.value];
                });
            }

            const example = form.querySelector('[data-example]');
            if (example) {
                example.addEventListener('click', () => {
                    Object.entries(JSON.parse(example.dataset.example)).forEach(([name, value]) => {
                        if (form.elements[name]) form.elements[name].value = value;
                    });
                    if (form.elements.format) form.elements.format.value = 'number';
                });
            }
        });

        toolButtons.forEach((btn) => btn.addEventListener('click', () => setTool(btn.dataset.tool)));

        out.copy.addEventListener('click', () => copyText(lastResult));
        out.reuse.addEventListener('click', () => {
            const form = activeForm();
            const target = form && (form.querySelector('[data-main]') || form.elements.a);
            if (!target) return;
            target.value = lastResult;
            target.focus();
            showToast('Hasil dipindah ke kolom input');
        });

        const hasTool = (tool) => [...toolButtons].some((b) => b.dataset.tool === tool);
        const savedTool = storage.get('cryptoTool', 'modular');
        setTool(hasTool(savedTool) ? savedTool : 'modular');

        return {
            currentTool: () => activeForm().dataset.tool,
            setTool: (tool) => { if (hasTool(tool)) setTool(tool); },
        };
    }

    // ---------- Pratinjau hasil saat mengetik ----------

    /** Buang operator menggantung dan tutup kurung yang masih terbuka. */
    function tidyExpr(expr) {
        const trimmed = expr.replace(/[+\-*/^]+$/, '');
        const open = (trimmed.match(/\(/g) || []).length - (trimmed.match(/\)/g) || []).length;
        return trimmed + ')'.repeat(Math.max(0, open));
    }

    let previewTimer = null;
    let previewKey = null;
    let previewSeq = 0;

    function updatePreview() {
        const expr = state.expr;
        const wanted = !state.error && !state.justEvaluated && !state.busy
            && expr !== '' && !isPlainNumber(expr) && tidyExpr(expr) !== '';

        if (!wanted) {
            clearTimeout(previewTimer);
            previewKey = null;
            el.preview.textContent = '';
            return;
        }

        const key = expr + '|' + state.angle;
        if (key === previewKey) return; // sudah dihitung / sedang menunggu
        previewKey = key;
        el.preview.classList.add('is-stale');

        clearTimeout(previewTimer);
        previewTimer = setTimeout(async () => {
            const seq = ++previewSeq;
            try {
                const data = await api(config.routes.preview, 'POST', { expression: tidyExpr(expr), angle: state.angle });
                if (seq !== previewSeq || state.expr !== expr || state.justEvaluated) return;
                el.preview.textContent = data.result !== null ? '= ' + pretty(data.result) : '';
                el.preview.classList.remove('is-stale');
            } catch (e) { /* pratinjau bersifat opsional */ }
        }, 250);
    }

    // ---------- Grafik fungsi ----------

    function setupGraph() {
        const form = document.getElementById('graphForm');
        const canvas = document.getElementById('graphCanvas');
        const wrap = document.getElementById('graphWrap');
        const tooltip = document.getElementById('graphTooltip');
        const errorBox = document.getElementById('graphError');
        const ctx = canvas.getContext('2d');
        const NAMES = ['f1', 'f2', 'f3'];
        const LABELS = ['f', 'g', 'h'];
        const COLORS = ['#6366f1', '#f97316', '#10b981'];
        const LIMIT = 1e6;

        let data = null;        // { x: [], series: [{ expression, y, error, color, label }] }
        let view = null;        // { xMin, xMax, yMin, yMax }
        let hoverIndex = null;
        let drag = null;
        let seq = 0;
        let fetchTimer = null;
        let loaded = false;

        const range = () => ({
            from: parseFloat(form.elements.from.value),
            to: parseFloat(form.elements.to.value),
        });

        function setRange(from, to) {
            form.elements.from.value = Number(from.toPrecision(6));
            form.elements.to.value = Number(to.toPrecision(6));
        }

        function showError(message) {
            errorBox.textContent = message;
            errorBox.hidden = false;
        }

        async function fetchData() {
            const { from, to } = range();
            if (!(Number.isFinite(from) && Number.isFinite(to) && to > from)) {
                showError('Rentang x tidak valid: x max harus lebih besar dari x min.');
                return;
            }
            const functions = NAMES.map((n) => form.elements[n].value.trim());
            if (!functions.some(Boolean)) {
                showError('Isi minimal satu fungsi, contoh: sin(x)');
                return;
            }
            storage.set('graph', { functions, from, to });

            const used = functions.map((f, i) => (f ? i : -1)).filter((i) => i >= 0);
            const samples = Math.max(100, Math.min(1000, Math.round(wrap.clientWidth || 400)));
            const mySeq = ++seq;
            wrap.classList.add('is-busy');
            try {
                const res = await api(config.routes.plot, 'POST', { functions, from, to, samples });
                if (mySeq !== seq) return;
                res.series.forEach((s, i) => {
                    s.color = COLORS[used[i]];
                    s.label = LABELS[used[i]];
                });
                data = res;
                computeView(from, to);
                const errors = res.series.filter((s) => s.error).map((s) => `${s.label}(x) = ${s.expression}: ${s.error}`);
                errorBox.hidden = errors.length === 0;
                errorBox.textContent = errors.join('\n');
                draw();
            } catch (err) {
                if (mySeq === seq) showError(err.message);
            } finally {
                if (mySeq === seq) wrap.classList.remove('is-busy');
            }
        }

        function scheduleFetch() {
            clearTimeout(fetchTimer);
            fetchTimer = setTimeout(fetchData, 200);
        }

        /** Sumbu y otomatis; nilai ekstrem (mis. dekat asimtot 1/x) dipangkas. */
        function computeView(from, to) {
            const ys = [];
            data.series.forEach((s) => (s.y || []).forEach((v) => { if (v !== null && Number.isFinite(v)) ys.push(v); }));
            let yMin = -1;
            let yMax = 1;
            if (ys.length) {
                ys.sort((a, b) => a - b);
                const q = (p) => ys[Math.round(p * (ys.length - 1))];
                yMin = q(0.02);
                yMax = q(0.98);
                const spread = yMax - yMin;
                if (ys[0] >= yMin - spread) yMin = ys[0];
                if (ys[ys.length - 1] <= yMax + spread) yMax = ys[ys.length - 1];
                if (yMax - yMin < 1e-9) { yMin -= 1; yMax += 1; }
                const pad = (yMax - yMin) * 0.08;
                yMin -= pad;
                yMax += pad;
            }
            view = { xMin: from, xMax: to, yMin, yMax };
        }

        function sizeCanvas() {
            const dpr = window.devicePixelRatio || 1;
            const w = wrap.clientWidth || 300;
            const h = Math.round(Math.min(360, Math.max(220, w * 0.72)));
            canvas.style.height = h + 'px';
            canvas.width = Math.round(w * dpr);
            canvas.height = Math.round(h * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            return { w, h };
        }

        function niceStep(span, count) {
            const raw = span / Math.max(1, count);
            const mag = 10 ** Math.floor(Math.log10(raw));
            const n = raw / mag;
            return (n < 1.5 ? 1 : n < 3.5 ? 2 : n < 7.5 ? 5 : 10) * mag;
        }

        function fmtTick(v, step) {
            if (Math.abs(v) < step / 2) return '0';
            const decimals = Math.min(6, Math.max(0, -Math.floor(Math.log10(step))));
            return v.toFixed(decimals);
        }

        function fmtValue(v) {
            if (v === null || !Number.isFinite(v)) return 'tidak terdefinisi';
            return String(Number(v.toPrecision(6)));
        }

        function draw() {
            const { w, h } = sizeCanvas();
            const css = getComputedStyle(document.documentElement);
            const colText = css.getPropertyValue('--text-muted').trim();
            const colGrid = css.getPropertyValue('--border').trim();
            const colAxis = css.getPropertyValue('--text').trim();
            ctx.clearRect(0, 0, w, h);
            if (!view) return;

            const X = (x) => ((x - view.xMin) / (view.xMax - view.xMin)) * w;
            const Y = (y) => h - ((y - view.yMin) / (view.yMax - view.yMin)) * h;
            const sx = niceStep(view.xMax - view.xMin, w / 70);
            const sy = niceStep(view.yMax - view.yMin, h / 45);
            const xTicks = [];
            const yTicks = [];
            for (let x = Math.ceil(view.xMin / sx) * sx; x <= view.xMax; x += sx) xTicks.push(x);
            for (let y = Math.ceil(view.yMin / sy) * sy; y <= view.yMax; y += sy) yTicks.push(y);

            // Kisi
            ctx.lineWidth = 1;
            ctx.strokeStyle = colGrid;
            ctx.beginPath();
            xTicks.forEach((x) => { const px = Math.round(X(x)) + 0.5; ctx.moveTo(px, 0); ctx.lineTo(px, h); });
            yTicks.forEach((y) => { const py = Math.round(Y(y)) + 0.5; ctx.moveTo(0, py); ctx.lineTo(w, py); });
            ctx.stroke();

            // Sumbu
            ctx.strokeStyle = colAxis;
            ctx.globalAlpha = 0.75;
            ctx.beginPath();
            if (view.yMin <= 0 && view.yMax >= 0) { const py = Math.round(Y(0)) + 0.5; ctx.moveTo(0, py); ctx.lineTo(w, py); }
            if (view.xMin <= 0 && view.xMax >= 0) { const px = Math.round(X(0)) + 0.5; ctx.moveTo(px, 0); ctx.lineTo(px, h); }
            ctx.stroke();
            ctx.globalAlpha = 1;

            // Label angka
            ctx.font = '11px system-ui, sans-serif';
            ctx.fillStyle = colText;
            const labelY = Math.min(Math.max(Y(0) + 13, 13), h - 4);
            ctx.textAlign = 'center';
            // Label yang terlalu dekat tepi kanvas dilewati agar tidak terpotong.
            xTicks.forEach((x) => {
                const px = X(x);
                if (Math.abs(x) >= sx / 2 && px > 14 && px < w - 14) ctx.fillText(fmtTick(x, sx), px, labelY);
            });
            const labelX = Math.min(Math.max(X(0) + 4, 4), w - 44);
            ctx.textAlign = 'left';
            yTicks.forEach((y) => {
                const py = Y(y);
                if (Math.abs(y) >= sy / 2 && py > 14 && py < h - 4) ctx.fillText(fmtTick(y, sy), labelX, py - 3);
            });

            // Kurva
            if (!data) return;
            const span = view.yMax - view.yMin;
            data.series.forEach((s) => {
                if (!s.y) return;
                ctx.strokeStyle = s.color;
                ctx.lineWidth = 2.2;
                ctx.lineJoin = 'round';
                ctx.beginPath();
                let pen = false;
                let prevY = null;
                let prevPy = null;
                s.y.forEach((y, i) => {
                    if (y === null || !Number.isFinite(y)) { pen = false; prevY = null; return; }
                    const px = X(data.x[i]);
                    const py = Y(y);
                    // Putuskan garis di asimtot (mis. tan, 1/x) agar tidak ada garis tegak palsu.
                    if (prevY !== null) {
                        const crossed = (prevPy < 0 && py > h) || (prevPy > h && py < 0);
                        if (crossed || Math.abs(y - prevY) > span * 3) pen = false;
                    }
                    const cy = Math.max(-h, Math.min(2 * h, py));
                    if (pen) ctx.lineTo(px, cy); else ctx.moveTo(px, cy);
                    pen = true;
                    prevY = y;
                    prevPy = py;
                });
                ctx.stroke();
            });

            // Garis bantu & titik saat kursor di atas grafik
            if (hoverIndex !== null && data.x[hoverIndex] !== undefined) {
                const px = X(data.x[hoverIndex]);
                ctx.strokeStyle = colText;
                ctx.setLineDash([4, 4]);
                ctx.beginPath();
                ctx.moveTo(px, 0);
                ctx.lineTo(px, h);
                ctx.stroke();
                ctx.setLineDash([]);
                data.series.forEach((s) => {
                    const y = s.y && s.y[hoverIndex];
                    if (y === null || y === undefined || !Number.isFinite(y)) return;
                    ctx.fillStyle = s.color;
                    ctx.beginPath();
                    ctx.arc(px, Y(y), 4.5, 0, Math.PI * 2);
                    ctx.fill();
                });
            }
        }

        function updateTooltip(clientX, clientY) {
            if (hoverIndex === null || !data) {
                tooltip.hidden = true;
                return;
            }
            tooltip.replaceChildren();
            const head = document.createElement('div');
            head.textContent = 'x = ' + fmtValue(data.x[hoverIndex]);
            tooltip.appendChild(head);
            data.series.filter((s) => s.y).forEach((s) => {
                const row = document.createElement('div');
                const dot = document.createElement('span');
                dot.className = 'swatch';
                dot.style.setProperty('--c', s.color);
                row.append(dot, `${s.label}(x) = ${fmtValue(s.y[hoverIndex])}`);
                tooltip.appendChild(row);
            });
            const r = wrap.getBoundingClientRect();
            const left = clientX - r.left;
            tooltip.hidden = false;
            tooltip.style.left = Math.min(left + 12, r.width - tooltip.offsetWidth - 4) + 'px';
            tooltip.style.top = Math.max(4, clientY - r.top - tooltip.offsetHeight - 10) + 'px';
        }

        function zoom(factor, anchorFraction = 0.5) {
            const { from, to } = range();
            if (!(to > from)) return;
            const anchor = from + (to - from) * anchorFraction;
            let nextFrom = anchor - (anchor - from) * factor;
            let nextTo = anchor + (to - anchor) * factor;
            if (nextTo - nextFrom < 1e-6) return;
            nextFrom = Math.max(-LIMIT, nextFrom);
            nextTo = Math.min(LIMIT, nextTo);
            setRange(nextFrom, nextTo);
            if (view) { view.xMin = nextFrom; view.xMax = nextTo; draw(); }
            scheduleFetch();
        }

        // ----- Event -----
        form.addEventListener('submit', (e) => { e.preventDefault(); fetchData(); });
        form.querySelectorAll('[data-zoom]').forEach((btn) => btn.addEventListener('click', () => {
            if (btn.dataset.zoom === 'reset') { setRange(-10, 10); fetchData(); return; }
            zoom(btn.dataset.zoom === 'in' ? 1 / 1.5 : 1.5);
        }));

        canvas.addEventListener('wheel', (e) => {
            if (!view) return;
            e.preventDefault();
            const r = canvas.getBoundingClientRect();
            zoom(e.deltaY > 0 ? 1.2 : 1 / 1.2, (e.clientX - r.left) / r.width);
        }, { passive: false });

        canvas.addEventListener('pointerdown', (e) => {
            if (!view) return;
            const { from, to } = range();
            drag = { x: e.clientX, from, to, moved: false };
            canvas.setPointerCapture(e.pointerId);
        });

        canvas.addEventListener('pointermove', (e) => {
            const r = canvas.getBoundingClientRect();
            if (drag) {
                const dx = e.clientX - drag.x;
                if (Math.abs(dx) > 2) drag.moved = true;
                if (drag.moved) {
                    const shift = (-dx / r.width) * (drag.to - drag.from);
                    const nextFrom = Math.max(-LIMIT, drag.from + shift);
                    const nextTo = nextFrom + (drag.to - drag.from);
                    setRange(nextFrom, nextTo);
                    view.xMin = nextFrom;
                    view.xMax = nextTo;
                    hoverIndex = null;
                    tooltip.hidden = true;
                    draw();
                    return;
                }
            }
            if (!data) return;
            const fx = (e.clientX - r.left) / r.width;
            const xValue = view.xMin + fx * (view.xMax - view.xMin);
            const step = (data.x[data.x.length - 1] - data.x[0]) / (data.x.length - 1);
            const idx = Math.round((xValue - data.x[0]) / step);
            hoverIndex = idx >= 0 && idx < data.x.length ? idx : null;
            draw();
            updateTooltip(e.clientX, e.clientY);
        });

        const endDrag = () => {
            if (drag && drag.moved) fetchData();
            drag = null;
        };
        canvas.addEventListener('pointerup', endDrag);
        canvas.addEventListener('pointercancel', endDrag);
        canvas.addEventListener('pointerleave', () => {
            if (drag) return;
            hoverIndex = null;
            tooltip.hidden = true;
            draw();
        });

        window.addEventListener('resize', () => { if (state.mode === 'graph') draw(); });

        function load(functions, from, to) {
            NAMES.forEach((n, i) => { form.elements[n].value = functions[i] || ''; });
            const a = parseFloat(from);
            const b = parseFloat(to);
            if (Number.isFinite(a) && Number.isFinite(b) && b > a) setRange(a, b);
        }

        return {
            draw,
            load(functions, from, to) {
                load(functions, from, to);
                loaded = false;
                this.fromUrl = true;
            },
            show() {
                if (!loaded) {
                    loaded = true;
                    const saved = storage.get('graph', null);
                    if (!this.fromUrl && saved && Array.isArray(saved.functions)) load(saved.functions, saved.from, saved.to);
                    fetchData();
                } else {
                    draw();
                }
            },
            params() {
                const out = [];
                NAMES.forEach((n) => { const v = form.elements[n].value.trim(); if (v) out.push(['f', v]); });
                const { from, to } = range();
                out.push(['from', String(from)], ['to', String(to)]);
                return out;
            },
        };
    }

    // ---------- Link berbagi ----------

    function buildShareUrl() {
        const params = new URLSearchParams();
        params.set('mode', state.mode);
        if (state.mode === 'standard' || state.mode === 'scientific') {
            const expr = state.justEvaluated ? state.lastExpr : state.expr;
            if (expr) params.set('expr', expr);
            if (state.mode === 'scientific') params.set('angle', state.angle);
        } else if (state.mode === 'graph') {
            graph.params().forEach(([k, v]) => params.append(k, v));
        } else if (state.mode === 'crypto') {
            params.set('tool', crypto.currentTool());
        }
        return location.origin + location.pathname + '?' + params.toString();
    }

    function loadFromUrl() {
        const params = new URLSearchParams(location.search);
        if (!params.toString()) return;

        const mode = params.get('mode');
        if (MODES.includes(mode)) state.mode = mode;
        const angle = params.get('angle');
        if (angle === 'deg' || angle === 'rad') state.angle = angle;
        const expr = params.get('expr');
        if (expr) {
            state.expr = expr.slice(0, 200);
            state.justEvaluated = false;
            state.error = null;
        }
        const tool = params.get('tool');
        if (tool) crypto.setTool(tool);
        if (params.has('f') || params.has('from')) graph.load(params.getAll('f'), params.get('from'), params.get('to'));

        // Bersihkan alamat agar refresh tidak memuat ulang link.
        history.replaceState(null, '', location.pathname);
    }

    el.shareBtn.addEventListener('click', () => copyText(buildShareUrl(), 'Link disalin — tempel untuk membagikan'));

    // ---------- Inisialisasi ----------
    renderSoundToggle();
    renderThemeToggle();
    renderHistory();
    const crypto = setupCrypto();
    const graph = setupGraph();
    loadFromUrl();
    setMode(state.mode, false);
    setupTilt();
})();
