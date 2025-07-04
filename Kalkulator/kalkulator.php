<?php
session_start();
if (!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['expression']) && isset($_POST['result'])) {
    $expression = trim($_POST['expression']);
    $result = trim($_POST['result']);
    // Cek jika ekspresi dan hasil valid
    if ($expression !== '' && $result !== '' && $result !== 'Err: ÷0') {
        array_unshift($_SESSION['history'], "$expression = $result");
        if (count($_SESSION['history']) > 7) {
            array_pop($_SESSION['history']);
        }
    }
    // Redirect agar tidak resubmit saat refresh
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clear_history'])) {
    $_SESSION['history'] = [];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalkulator Modern</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="kalkulator.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="calc-flex-wrapper d-flex flex-row align-items-start gap-4">
        <?php if (!empty($_SESSION['history'])): ?>
        <div class="history custom-history" style="min-width:340px; max-width:420px; width:100%; max-height:500px;">
            <div class="history-title"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Perhitungan:</div>
            <div class="history-list">
            <?php foreach ($_SESSION['history'] as $idx => $item): 
                $parts = explode('=', $item, 2);
                $expression = isset($parts[0]) ? trim($parts[0]) : '';
                $result = isset($parts[1]) ? trim($parts[1]) : '';
            ?>
                <div class="history-item<?php echo $idx === 0 ? ' animated' : ''; ?>">
                    <span class="history-icon"><i class="fa-solid fa-calculator"></i></span>
                    <span class="history-expression" style="font-size:1.25em;"><?php echo htmlspecialchars($expression); ?></span>
                    <span class="history-equals" style="font-size:1.25em;">=</span>
                    <span class="history-result" style="font-size:1.35em; font-weight:bold;"><?php echo htmlspecialchars($result); ?></span>
                </div>
                <?php if ($idx < count($_SESSION['history']) - 1): ?>
                    <div class="history-divider"></div>
                <?php endif; ?>
            <?php endforeach; ?>
            </div>
            <form method="POST" style="margin-top: 1.2rem;">
                <button type="submit" name="clear_history" value="1" class="btn-clear-history">
                    <i class="fa-solid fa-trash"></i>
                    <span>Hapus Riwayat</span>
                </button>
            </form>
        </div>
        <?php endif; ?>
        <div style="width:100%">
            <button id="flipBtn" class="btn btn-primary mb-3 w-100" type="button"><i class="fa-solid fa-retweet"></i> Flip ke Scientific</button>
            <div id="standardCalc" class="calculator-3d shadow-lg">
                <div id="display" class="display mb-3">0</div>
                <form method="POST" id="hiddenForm" style="display:none;">
                    <input type="hidden" name="expression" id="expressionInput">
                    <input type="hidden" name="result" id="resultInput">
                </form>
                <div class="button-grid mb-2">
                    <button class="btn-calc clear" data-action="clear">AC</button>
                    <button class="btn-calc sign" data-action="sign">+/-</button>
                    <button class="btn-calc percent" data-action="percent">%</button>
                    <button class="btn-calc op" data-action="divide">÷</button>
                    <button class="btn-calc" data-num="7">7</button>
                    <button class="btn-calc" data-num="8">8</button>
                    <button class="btn-calc" data-num="9">9</button>
                    <button class="btn-calc op" data-action="multiply">×</button>
                    <button class="btn-calc" data-num="4">4</button>
                    <button class="btn-calc" data-num="5">5</button>
                    <button class="btn-calc" data-num="6">6</button>
                    <button class="btn-calc op" data-action="subtract">-</button>
                    <button class="btn-calc" data-num="1">1</button>
                    <button class="btn-calc" data-num="2">2</button>
                    <button class="btn-calc" data-num="3">3</button>
                    <button class="btn-calc op" data-action="add">+</button>
                    <button class="btn-calc zero" data-num="0" style="grid-column: span 2;">0</button>
                    <button class="btn-calc" data-num=".">.</button>
                    <button class="btn-calc equal" data-action="equal" style="grid-column: span 2;">=</button>
                </div>
            </div>
            <!-- Kalkulator Scientific (awalnya hidden) -->
            <div id="scientificCalc" class="calculator-3d shadow-lg" style="display:none;">
                <div id="displaySci" class="display mb-3">0</div>
                <form method="POST" id="hiddenFormSci" style="display:none;">
                    <input type="hidden" name="expression" id="expressionInputSci">
                    <input type="hidden" name="result" id="resultInputSci">
                </form>
                <div class="sci-memory-row mb-2">
                    <button class="btn-calc" data-action="mc">MC</button>
                    <button class="btn-calc" data-action="mr">MR</button>
                    <button class="btn-calc" data-action="mplus">M+</button>
                    <button class="btn-calc" data-action="mminus">M-</button>
                </div>
                <div class="sci-grid mb-2">
                    <button class="btn-calc clear" data-action="clear">AC</button>
                    <button class="btn-calc" data-action="sqrt">√</button>
                    <button class="btn-calc" data-action="square">x²</button>
                    <button class="btn-calc" data-action="fact">n!</button>
                    <button class="btn-calc op" data-action="divide">÷</button>
                    <button class="btn-calc" data-action="sin">sin</button>
                    <button class="btn-calc" data-action="cos">cos</button>
                    <button class="btn-calc" data-action="tan">tan</button>
                    <button class="btn-calc" data-action="log">log</button>
                    <button class="btn-calc" data-action="ln">ln</button>
                    <button class="btn-calc" data-action="exp">eˣ</button>
                    <button class="btn-calc op" data-action="multiply">×</button>
                    <button class="btn-calc op" data-action="subtract">-</button>
                    <button class="btn-calc op" data-action="add">+</button>
                    <button class="btn-calc" data-action="pi">π</button>
                </div>
                <div class="sci-grid mb-2">
                    <button class="btn-calc" data-num="7">7</button>
                    <button class="btn-calc" data-num="8">8</button>
                    <button class="btn-calc" data-num="9">9</button>
                    <button class="btn-calc" data-action="e">e</button>
                    <span></span>
                    <button class="btn-calc" data-num="4">4</button>
                    <button class="btn-calc" data-num="5">5</button>
                    <button class="btn-calc" data-num="6">6</button>
                    <button class="btn-calc" data-num=".">.</button>
                    <span></span>
                    <button class="btn-calc" data-num="1">1</button>
                    <button class="btn-calc" data-num="2">2</button>
                    <button class="btn-calc" data-num="3">3</button>
                    <button class="btn-calc zero" data-num="0" style="grid-column: span 2;">0</button>
                    <button class="btn-calc equal" data-action="equal" style="grid-column: span 2;">=</button>
                </div>
                <div id="sciHistoryBox" class="sci-history" style="display:none;">
                    <div class="sci-history-title"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Scientific:</div>
                    <div class="sci-history-list" id="sciHistoryList"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<audio id="buttonSound" src="https://cdn.pixabay.com/audio/2022/10/16/audio_12b6b6b6b6.mp3"></audio>
<script>
    // Kalkulator modern dengan JS
    let display = document.getElementById('display');
    let current = '';
    let operator = '';
    let operand = '';
    let justEvaluated = false;

    function updateDisplay(val) {
        display.textContent = val;
        display.classList.remove('pop');
        void display.offsetWidth; // trigger reflow
        display.classList.add('pop');
    }

    // Tambahkan animasi pop pada display
    const style = document.createElement('style');
    style.innerHTML = `.display.pop { animation: displayPop 0.4s; }`;
    document.head.appendChild(style);

    function resetCalc() {
        current = '';
        operator = '';
        operand = '';
        justEvaluated = false;
        updateDisplay('0');
    }

    function appendNum(num) {
        if (justEvaluated) {
            current = '';
            justEvaluated = false;
        }
        if (num === '.' && current.includes('.')) return;
        if (current === '0' && num !== '.') current = '';
        current += num;
        updateDisplay(current);
    }

    function setOperator(op) {
        if (current === '' && operand === '') return;
        if (operand !== '' && current !== '') {
            calculate();
        }
        operator = op;
        operand = current !== '' ? current : operand;
        current = '';
        justEvaluated = false;
    }

    function calculate() {
        if (operator === '' || operand === '' || current === '') return;
        let a = parseFloat(operand);
        let b = parseFloat(current);
        let res = 0;
        let opSymbol = '';
        switch (operator) {
            case 'add':
                res = a + b;
                opSymbol = '+';
                break;
            case 'subtract':
                res = a - b;
                opSymbol = '-';
                break;
            case 'multiply':
                res = a * b;
                opSymbol = '×';
                break;
            case 'divide':
                if (b === 0) {
                    updateDisplay('Err: ÷0');
                    resetCalc();
                    return;
                }
                res = a / b;
                opSymbol = '÷';
                break;
        }
        res = Math.round(res * 1000000) / 1000000;
        updateDisplay(res);

        // CEGAH submit jika operand, operator, atau current kosong
        if (!operand || !opSymbol || !current || isNaN(res)) return;

        // CEGAH submit jika hasil error (walau sudah dicegah di atas, ini ekstra)
        if (res === 'Err: ÷0') return;

        // Kirim ke PHP untuk riwayat
        document.getElementById('expressionInput').value = `${operand} ${opSymbol} ${current}`;
        document.getElementById('resultInput').value = res;
        document.getElementById('hiddenForm').submit();
        operand = res.toString();
        current = '';
        operator = '';
        justEvaluated = true;
    }

    function playButtonSound() {
        const audio = document.getElementById('buttonSound');
        if (audio) {
            audio.currentTime = 0;
            audio.volume = 1.0; // pastikan volume maksimal
            audio.play();
        }
    }

    document.querySelectorAll('.btn, .btn-calc').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            playButtonSound(); // Tambahkan suara
            e.preventDefault();
            if (btn.dataset.num !== undefined) {
                appendNum(btn.dataset.num);
            } else if (btn.dataset.action) {
                switch (btn.dataset.action) {
                    case 'clear':
                        resetCalc();
                        break;
                    case 'add':
                    case 'subtract':
                    case 'multiply':
                    case 'divide':
                        setOperator(btn.dataset.action);
                        break;
                    case 'equal':
                        calculate();
                        break;
                    case 'sign':
                        if (current !== '') {
                            if (current.startsWith('-')) {
                                current = current.substring(1);
                            } else {
                                current = '-' + current;
                            }
                            updateDisplay(current);
                        }
                        break;
                    case 'percent':
                        if (current !== '') {
                            current = (parseFloat(current) / 100).toString();
                            updateDisplay(current);
                        }
                        break;
                }
            }
        });
    });
    // Inisialisasi
    resetCalc();

    // Tambahkan animasi pada item riwayat baru
    window.addEventListener('DOMContentLoaded', function() {
        const historyItems = document.querySelectorAll('.history-item');
        if (historyItems.length > 0) {
            historyItems[0].classList.add('animated');
        }
    });

    // FLIP LOGIC
    const flipBtn = document.getElementById('flipBtn');
    const standardCalc = document.getElementById('standardCalc');
    const scientificCalc = document.getElementById('scientificCalc');
    
    function flipCalculator() {
        // Tambahkan animasi pada tombol flip
        flipBtn.classList.add('flip-animate', 'flipping');
        //
        // Animasi ikon dan teks berjalan bersamaan dengan animasi kalkulator
        setTimeout(() => {
            flipBtn.classList.remove('flip-animate');
        }, 450);
        setTimeout(() => {
            flipBtn.classList.remove('flipping');
        }, 250);
        if (standardCalc.style.display !== 'none') {
            // Animasi keluar standar
            standardCalc.classList.remove('flip-fade-in');
            standardCalc.classList.add('flip-fade-out');
            setTimeout(() => {
                standardCalc.style.display = 'none';
                standardCalc.classList.remove('flip-fade-out');
                // Animasi masuk scientific
                scientificCalc.style.display = '';
                scientificCalc.classList.add('flip-fade-in');
                setTimeout(() => {
                    scientificCalc.classList.remove('flip-fade-in');
                }, 450);
                flipBtn.innerHTML = '<span class="flip-icon"><i class="fa-solid fa-retweet"></i></span> <span class="flip-text">Flip ke Standar</span>';
            }, 450);
        } else {
            // Animasi keluar scientific
            scientificCalc.classList.remove('flip-fade-in');
            scientificCalc.classList.add('flip-fade-out');
            setTimeout(() => {
                scientificCalc.style.display = 'none';
                scientificCalc.classList.remove('flip-fade-out');
                // Animasi masuk standar
                standardCalc.style.display = '';
                standardCalc.classList.add('flip-fade-in');
                setTimeout(() => {
                    standardCalc.classList.remove('flip-fade-in');
                }, 450);
                flipBtn.innerHTML = '<span class="flip-icon"><i class="fa-solid fa-retweet"></i></span> <span class="flip-text">Flip ke Scientific</span>';
            }, 450);
        }
    }
    // Set konten awal tombol agar animasi berjalan sejak awal
    flipBtn.innerHTML = '<span class="flip-icon"><i class="fa-solid fa-retweet"></i></span> <span class="flip-text">Flip ke Scientific</span>';
    flipBtn.addEventListener('click', flipCalculator);

    // Kalkulator Scientific
    let displaySci = document.getElementById('displaySci');
    let sciCurrent = '';
    let sciOperator = '';
    let sciOperand = '';
    let sciJustEvaluated = false;
    let sciMemory = 0;

    function updateDisplaySci(val) {
        displaySci.textContent = val;
        displaySci.classList.remove('pop');
        void displaySci.offsetWidth;
        displaySci.classList.add('pop');
    }

    function resetSciCalc() {
        sciCurrent = '';
        sciOperator = '';
        sciOperand = '';
        sciJustEvaluated = false;
        updateDisplaySci('0');
    }

    function appendNumSci(num) {
        if (sciJustEvaluated) {
            sciCurrent = '';
            sciJustEvaluated = false;
        }
        if (num === '.' && sciCurrent.includes('.')) return;
        if (sciCurrent === '0' && num !== '.') sciCurrent = '';
        sciCurrent += num;
        updateDisplaySci(sciCurrent);
    }

    function setOperatorSci(op) {
        if (sciCurrent === '' && sciOperand === '') return;
        if (sciOperand !== '' && sciCurrent !== '') {
            calculateSci();
        }
        sciOperator = op;
        sciOperand = sciCurrent !== '' ? sciCurrent : sciOperand;
        sciCurrent = '';
        sciJustEvaluated = false;
    }

    function calculateSci() {
        if (sciOperator === '' || sciOperand === '' || sciCurrent === '') return;
        let a = parseFloat(sciOperand);
        let b = parseFloat(sciCurrent);
        let res = 0;
        let opSymbol = '';
        switch (sciOperator) {
            case 'add':
                res = a + b;
                opSymbol = '+';
                break;
            case 'subtract':
                res = a - b;
                opSymbol = '-';
                break;
            case 'multiply':
                res = a * b;
                opSymbol = '×';
                break;
            case 'divide':
                if (b === 0) {
                    updateDisplaySci('Err: ÷0');
                    resetSciCalc();
                    return;
                }
                res = a / b;
                opSymbol = '÷';
                break;
        }
        res = Math.round(res * 1000000) / 1000000;
        updateDisplaySci(res);
        // Kirim ke PHP untuk riwayat
        sendSciToHistory(`${a} ${opSymbol} ${b}`, res);
        sciOperand = res.toString();
        sciCurrent = '';
        sciOperator = '';
        sciJustEvaluated = true;
    }

    function factorial(n) {
        if (n < 0) return NaN;
        if (n === 0 || n === 1) return 1;
        let f = 1;
        for (let i = 2; i <= n; i++) f *= i;
        return f;
    }

    function applySciFunction(func) {
        let val = parseFloat(sciCurrent !== '' ? sciCurrent : (sciOperand !== '' ? sciOperand : '0'));
        let res = 0;
        let expr = '';
        switch (func) {
            case 'sqrt':
                res = Math.sqrt(val);
                expr = `√(${val})`;
                break;
            case 'square':
                res = Math.pow(val, 2);
                expr = `sqr(${val})`;
                break;
            case 'sin':
                res = Math.sin(val * Math.PI / 180);
                expr = `sin(${val})`;
                break;
            case 'cos':
                res = Math.cos(val * Math.PI / 180);
                expr = `cos(${val})`;
                break;
            case 'tan':
                res = Math.tan(val * Math.PI / 180);
                expr = `tan(${val})`;
                break;
            case 'log':
                res = Math.log10(val);
                expr = `log(${val})`;
                break;
            case 'ln':
                res = Math.log(val);
                expr = `ln(${val})`;
                break;
            case 'exp':
                res = Math.exp(val);
                expr = `e^(${val})`;
                break;
            case 'pi':
                res = Math.PI;
                expr = `π`;
                break;
            case 'e':
                res = Math.E;
                expr = `e`;
                break;
            case 'fact':
                res = factorial(val);
                expr = `${val}!`;
                break;
        }
        res = Math.round(res * 1000000) / 1000000;
        sciCurrent = res.toString();
        sciOperand = '';
        sciOperator = '';
        updateDisplaySci(sciCurrent);
        sciJustEvaluated = true;
        // Kirim ke PHP untuk riwayat
        sendSciToHistory(expr, res);
    }

    // --- Scientific History ---
    let sciHistory = [];
    function addSciHistory(expr, res) {
        sciHistory.unshift({expr, res});
        if (sciHistory.length > 7) sciHistory.pop();
        renderSciHistory();
    }
    function renderSciHistory() {
        const box = document.getElementById('sciHistoryBox');
        const list = document.getElementById('sciHistoryList');
        if (sciHistory.length === 0) {
            box.style.display = 'none';
            return;
        }
        box.style.display = '';
        list.innerHTML = '';
        sciHistory.forEach((item, idx) => {
            const div = document.createElement('div');
            div.className = 'sci-history-item' + (idx === 0 ? ' animated' : '');
            div.innerHTML = `<span class="sci-history-icon"><i class='fa-solid fa-flask'></i></span><span class="sci-history-expression">${item.expr}</span><span class="sci-history-equals">=</span><span class="sci-history-result">${item.res}</span>`;
            list.appendChild(div);
            if (idx < sciHistory.length - 1) {
                const divider = document.createElement('div');
                divider.className = 'sci-history-divider';
                list.appendChild(divider);
            }
        });
    }
    // ---
    function sendSciToHistory(expr, res) {
        addSciHistory(expr, res);
        document.getElementById('expressionInputSci').value = expr;
        document.getElementById('resultInputSci').value = res;
        document.getElementById('hiddenFormSci').submit();
    }

    // Event listener untuk tombol scientific
    document.querySelectorAll('#scientificCalc .btn-calc').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            playButtonSound(); // Tambahkan suara
            e.preventDefault();
            if (btn.dataset.num !== undefined) {
                appendNumSci(btn.dataset.num);
            } else if (btn.dataset.action) {
                switch (btn.dataset.action) {
                    case 'clear':
                        resetSciCalc();
                        break;
                    case 'add':
                    case 'subtract':
                    case 'multiply':
                    case 'divide':
                        setOperatorSci(btn.dataset.action);
                        break;
                    case 'equal':
                        calculateSci();
                        break;
                    case 'sqrt':
                    case 'square':
                    case 'sin':
                    case 'cos':
                    case 'tan':
                    case 'log':
                    case 'ln':
                    case 'exp':
                    case 'pi':
                    case 'e':
                    case 'fact':
                        applySciFunction(btn.dataset.action);
                        break;
                    case 'mc':
                        sciMemory = 0;
                        break;
                    case 'mr':
                        sciCurrent = sciMemory.toString();
                        sciOperand = '';
                        sciOperator = '';
                        updateDisplaySci(sciCurrent);
                        sciJustEvaluated = false;
                        break;
                    case 'mplus':
                        sciMemory += parseFloat(sciCurrent || '0');
                        break;
                    case 'mminus':
                        sciMemory -= parseFloat(sciCurrent || '0');
                        break;
                }
            }
        });
    });

    // Inisialisasi scientific
    resetSciCalc();
    renderSciHistory();

    // Tambahkan konfirmasi sebelum hapus riwayat
    const clearBtn = document.querySelector('form button.btn-clear-history');
    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            if (!confirm('Apakah Anda yakin ingin menghapus seluruh riwayat?')) {
                e.preventDefault();
            }
        });
    }
</script>
</body>
</html>
