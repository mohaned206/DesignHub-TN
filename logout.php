<?php
// logout.php : déconnexion (par POST + jeton CSRF, pour qu'un lien externe ne puisse pas déconnecter l'utilisateur)
require_once __DIR__ . '/includes/functions.php';

csrf_check();

$_SESSION = [];                 // vide les données de session
session_destroy();              // détruit la session côté serveur

session_start();                // nouvelle session propre, juste pour afficher le message
set_flash('success', 'Vous êtes déconnecté.');
redirect('index.php');
