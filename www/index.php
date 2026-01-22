<?php
// --- CONFIGURATION ---
$setupFile = 'cgi-bin/setup';
$accessCode = null;

if (file_exists($setupFile)) {
    $lines = file($setupFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'code=') === 0) {
            $parts = explode('=', $line);
            if (isset($parts[1]) && trim($parts[1]) !== '') {
                $accessCode = trim($parts[1]);
            }
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>TESLAMATE-MAIL</title>
    <style>
        body {
            background-color: #000000;
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100dvh;
            margin: 0;
            padding: 10px;
            box-sizing: border-box;
            overflow: hidden; /* Empêche le scroll parasite */
        }

        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            max-width: 350px;
            gap: 5px;
        }

        .logo {
            max-width: 140px; /* Réduction drastique pour libérer de l'espace */
            height: auto;
            mix-blend-mode: screen;
            filter: brightness(1.1) contrast(1.1);
        }

        h1 {
            font-size: 1.4rem; /* Plus petit pour mobile */
            letter-spacing: 2px;
            margin: 5px 0;
            font-weight: 900;
            text-transform: uppercase;
        }

        .copyright {
            font-size: 0.65rem;
            color: #666;
            margin-bottom: 10px;
        }

        /* Clavier Numérique */
        #pincode-container {
            display: <?php echo $accessCode ? 'flex' : 'none'; ?>;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }

        .dots {
            display: flex;
            gap: 12px;
            margin-bottom: 15px;
        }

        .dot {
            width: 10px;
            height: 10px;
            border: 2px solid #2e7d32;
            border-radius: 50%;
        }

        .dot.active {
            background-color: #2e7d32;
        }

        .numpad {
            display: grid;
            grid-template-columns: repeat(3, 60px);
            gap: 10px;
        }

        .num-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 1px solid #333;
            background: #111;
            color: white;
            font-size: 1.3rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-tap-highlight-color: transparent;
        }

        .num-btn:active {
            background: #2e7d32;
        }

        .error-msg {
            color: #ff5252;
            margin-top: 8px;
            height: 15px;
            font-size: 0.75rem;
            visibility: hidden;
        }

        .btn-entree {
            background-color: #2e7d32; 
            color: white;
            padding: 15px 50px;
            font-size: 1.1rem;
            font-weight: bold;
            text-decoration: none;
            border-radius: 5px;
            text-transform: uppercase;
        }

        /* Ajustements pour écrans très courts */
        @media (max-height: 600px) {
            .logo { max-width: 100px; }
            h1 { font-size: 1.1rem; margin: 2px 0; }
            .num-btn { width: 50px; height: 50px; font-size: 1.1rem; }
            .numpad { gap: 8px; }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="logo-link">
            <img src="logoteslamatemail.png" alt="Logo" class="logo">
        </div>
        
        <h1>TESLAMATE-MAIL</h1>
        <div class="copyright">© monwifi.fr 2026</div>

        <?php if (!$accessCode): ?>
            <a href="tesla.php" class="btn-entree">ENTREE</a>
        <?php else: ?>
            <div id="pincode-container">
                <div class="dots">
                    <div id="dot-1" class="dot"></div>
                    <div id="dot-2" class="dot"></div>
                    <div id="dot-3" class="dot"></div>
                    <div id="dot-4" class="dot"></div>
                </div>

                <div class="numpad">
                    <button class="num-btn" onclick="press('1')">1</button>
                    <button class="num-btn" onclick="press('2')">2</button>
                    <button class="num-btn" onclick="press('3')">3</button>
                    <button class="num-btn" onclick="press('4')">4</button>
                    <button class="num-btn" onclick="press('5')">5</button>
                    <button class="num-btn" onclick="press('6')">6</button>
                    <button class="num-btn" onclick="press('7')">7</button>
                    <button class="num-btn" onclick="press('8')">8</button>
                    <button class="num-btn" onclick="press('9')">9</button>
                    <button class="num-btn" onclick="clearPin()">C</button>
                    <button class="num-btn" onclick="press('0')">0</button>
                    <button class="num-btn" style="font-size: 0.8rem;" onclick="checkPin()">OK</button>
                </div>
                <div id="error" class="error-msg">Code incorrect</div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const correctCode = "<?php echo $accessCode; ?>";
        let currentInput = "";

        function press(num) {
            if (currentInput.length < 4) {
                currentInput += num;
                updateDots();
                document.getElementById('error').style.visibility = 'hidden';
                if (currentInput.length === 4) setTimeout(checkPin, 250);
            }
        }

        function updateDots() {
            for (let i = 1; i <= 4; i++) {
                const dot = document.getElementById('dot-' + i);
                dot.classList.toggle('active', i <= currentInput.length);
            }
        }

        function clearPin() {
            currentInput = "";
            updateDots();
            document.getElementById('error').style.visibility = 'hidden';
        }

        function checkPin() {
            if (currentInput === correctCode) {
                window.location.href = "tesla.php";
            } else {
                document.getElementById('error').style.visibility = 'visible';
                currentInput = "";
                setTimeout(updateDots, 300);
            }
        }
    </script>
</body>
</html>

