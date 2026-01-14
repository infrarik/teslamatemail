<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TESLAMATE-MAIL</title>
    <style>
        /* Design Premium - Fond Noir */
        body {
            background-color: #000000;
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-align: center;
        }

        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px; /* Espace réduit pour rapprocher le titre et le copyright */
        }

        /* Style du lien autour du logo */
        .logo-link {
            text-decoration: none;
            outline: none;
            margin-bottom: 10px;
        }

        .logo {
            max-width: 280px;
            height: auto;
            /* Le mode 'screen' rend le noir de l'image transparent */
            mix-blend-mode: screen;
            filter: brightness(1.1) contrast(1.1);
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.02);
        }

        h1 {
            font-size: 2.8rem;
            letter-spacing: 5px;
            margin: 10px 0 0 0; /* Pas de marge en bas pour coller le copyright */
            font-weight: 900;
            text-transform: uppercase;
        }

        .copyright {
            font-size: 0.8rem;
            color: #888888; /* Gris discret */
            margin-bottom: 40px; /* Espace avant le bouton */
            letter-spacing: 1px;
        }

        /* Style du Bouton Vert */
        .btn-entree {
            background-color: #2e7d32; 
            color: white;
            padding: 18px 65px;
            font-size: 1.3rem;
            font-weight: bold;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
            text-transform: uppercase;
        }

        .btn-entree:hover {
            background-color: #388e3c;
            transform: scale(1.03);
            box-shadow: 0 6px 20px rgba(46, 125, 50, 0.3);
        }

        .btn-entree:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body>

    <div class="container">
        <a href="tesla.php" class="logo-link">
            <img src="logoteslamatemail.png" alt="Logo Teslamate Mail" class="logo">
        </a>
        
        <h1>TESLAMATE-MAIL</h1>
        
        <div class="copyright">© monwifi.fr 2026</div>
        
        <a href="tesla.php" class="btn-entree">ENTREE</a>
    </div>

</body>
</html>
