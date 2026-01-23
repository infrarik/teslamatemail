<?php
session_start();

// VERROU DE SÉCURITÉ
if (!isset($_SESSION['authorized']) || $_SESSION['authorized'] !== true) {
    header('Location: index.php');
    exit;
}

// Si on arrive ici, l'utilisateur est autorisé, le reste du code s'exécute...
?>
