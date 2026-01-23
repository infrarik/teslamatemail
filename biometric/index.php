<?php
session_start();
$setupFile = "/var/www/html/cgi-bin/setup";
$accessCode = null;

if (file_exists($setupFile)) {
    $lines = file($setupFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'code=') === 0) {
            $parts = explode('=', $line);
            $accessCode = isset($parts[1]) ? trim($parts[1]) : null;
            break;
        }
    }
}

if (isset($_SESSION['authorized'])) {
    header('Location: testla.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>TESLAMATE-MAIL</title>
    <style>
        body { background: #000; color: #fff; font-family: sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; margin: 0; overflow: hidden; }
        .container { display: flex; flex-direction: column; align-items: center; width: 100%; max-width: 320px; }
        .logo { max-width: 100px; mix-blend-mode: screen; margin-bottom: 10px; }
        .dots { display: flex; gap: 15px; margin-bottom: 20px; }
        .dot { width: 15px; height: 15px; border: 2px solid #2e7d32; border-radius: 50%; }
        .dot.active { background: #2e7d32; box-shadow: 0 0 10px #2e7d32; }
        .numpad { display: grid; grid-template-columns: repeat(3, 70px); gap: 15px; }
        .num-btn { width: 70px; height: 70px; border-radius: 50%; border: 1px solid #333; background: #111; color: #fff; font-size: 1.5rem; cursor: pointer; -webkit-tap-highlight-color: transparent; }
        .num-btn:active { background: #2e7d32; }
        #bio-btn { margin-top: 25px; background: none; border: 1px solid #2e7d32; color: #2e7d32; padding: 10px 20px; border-radius: 20px; cursor: pointer; display: none; align-items: center; gap: 8px; font-size: 0.9rem; text-transform: uppercase; }
        .error { color: #ff5252; margin-top: 15px; font-size: 0.8rem; visibility: hidden; }
    </style>
</head>
<body>
    <div class="container">
        <img src="logoteslamatemail.png" class="logo">
        <div class="dots">
            <div id="d1" class="dot"></div><div id="d2" class="dot"></div>
            <div id="d3" class="dot"></div><div id="d4" class="dot"></div>
        </div>
        <div class="numpad">
            <?php for($i=1; $i<=9; $i++) echo "<button class='num-btn' onclick='press($i)'>$i</button>"; ?>
            <button class="num-btn" onclick="clearPin()">C</button>
            <button class="num-btn" onclick="press(0)">0</button>
            <button class="num-btn" onclick="checkPin()">OK</button>
        </div>

        <button id="bio-btn" onclick="tryBiometry()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 11c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM5 20v-1a7 7 0 0 1 7-7v0a7 7 0 0 1 7 7v1"/></svg>
            Biométrie
        </button>

        <div id="err" class="error">Code incorrect</div>
    </div>

    <script>
        let input = "";
        const bioBtn = document.getElementById('bio-btn');

        // Détection du capteur (uniquement en HTTPS)
        if (window.PublicKeyCredential) {
            PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().then(avail => {
                if(avail && localStorage.getItem('bio_registered')) bioBtn.style.display = 'flex';
            });
        }

        function press(n) {
            if (input.length < 4) {
                input += n;
                document.getElementById('d' + input.length).classList.add('active');
                if (input.length === 4) setTimeout(checkPin, 200);
            }
        }

        function clearPin() {
            input = "";
            for(let i=1; i<=4; i++) document.getElementById('d'+i).classList.remove('active');
            document.getElementById('err').style.visibility = 'hidden';
        }

        async function checkPin() {
            try {
                const res = await fetch(`auth.php?code=${input}`);
                const data = await res.json();
                if (data.success) {
                    // Si code OK et pas encore de biométrie enregistrée
                    if (window.PublicKeyCredential && !localStorage.getItem('bio_registered')) {
                        if (confirm("Voulez-vous activer FaceID / Empreinte ?")) {
                            await registerBiometry();
                        }
                    }
                    window.location.href = "testla.php";
                } else {
                    document.getElementById('err').style.visibility = 'visible';
                    clearPin();
                }
            } catch (e) { alert("Erreur serveur"); }
        }

        async function registerBiometry() {
            try {
                const challenge = new Uint8Array(32); window.crypto.getRandomValues(challenge);
                const credential = await navigator.credentials.create({
                    publicKey: {
                        challenge: challenge,
                        rp: { name: "TeslaMate" },
                        user: { id: new Uint8Array([1]), name: "tmatemail", displayName: "tmatemail" },
                        pubKeyCredParams: [{ alg: -7, type: "public-key" }],
                        authenticatorSelection: { userVerification: "required" },
                        timeout: 60000
                    }
                });
                if (credential) {
                    localStorage.setItem('bio_registered', 'true');
                    alert("Biométrie activée !");
                }
            } catch (e) { console.error("Erreur enregistrement:", e); }
        }

        async function tryBiometry() {
            try {
                const challenge = new Uint8Array(32); window.crypto.getRandomValues(challenge);
                const assertion = await navigator.credentials.get({
                    publicKey: { challenge: challenge, userVerification: "required" }
                });
                if (assertion) {
                    const res = await fetch("auth.php?bypass=true");
                    const data = await res.json();
                    if (data.success) window.location.href = "testla.php";
                }
            } catch (e) { alert("Échec biométrie"); }
        }
    </script>
</body>
</html>
