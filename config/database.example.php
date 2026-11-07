<?php
// config/database.php
// Connexion à MySQL avec PDO. Valeurs par défaut de XAMPP : utilisateur "root", sans mot de passe.
// Si votre configuration est différente, modifiez uniquement les 4 lignes ci-dessous.

const DB_HOST = 'localhost';
const DB_NAME = 'designhub_tn';
const DB_USER = 'root';
const DB_PASS = '';

// Retourne l'objet PDO (la connexion est créée une seule fois par requête)
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // erreurs SQL = exceptions
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // résultats sous forme de tableaux
                    PDO::ATTR_EMULATE_PREPARES   => false,                    // vraies requêtes préparées
                ]
            );
        } catch (PDOException $e) {
            // Message simple : on n'affiche jamais les détails techniques (identifiants...) au visiteur
            http_response_code(500);
            exit('Connexion à la base de données impossible. Vérifiez que MySQL est démarré dans XAMPP '
               . 'et que la base est installée (ouvrez install.php).');
        }
    }

    return $pdo;
}