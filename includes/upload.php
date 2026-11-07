<?php
// includes/upload.php : envoi et stockage sécurisé des fichiers (aperçus et fichiers sources).
//  - uploads/previews/ : images publiques (JPG, PNG, WebP) affichées sur le site
//  - uploads/files/    : fichiers sources (PSD, ZIP...) PRIVÉS : bloqués par .htaccess,
//                        servis uniquement par download.php après vérification des droits.
// Les fichiers sont renommés avec un nom aléatoire : le nom d'origine n'est jamais utilisé sur le disque.

const PREVIEW_DIR   = __DIR__ . '/../uploads/previews/';
const SOURCE_DIR    = __DIR__ . '/../uploads/files/';
const PREVIEW_MAX   = 5 * 1024 * 1024;      // 5 Mo
const SOURCE_MAX    = 100 * 1024 * 1024;    // 100 Mo (la limite du serveur PHP peut être plus basse)
const SOURCE_EXT    = ['psd', 'zip', 'rar', '7z', 'ai', 'fig', 'xd', 'sketch', 'svg', 'pdf', 'png', 'jpg', 'jpeg'];
const PREVIEW_TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

function upload_error_message(int $code): ?string
{
    switch ($code) {
        case UPLOAD_ERR_OK:
            return null;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'fichier trop volumineux pour la configuration du serveur (upload_max_filesize = ' . ini_get('upload_max_filesize') . ').';
        case UPLOAD_ERR_PARTIAL:
            return 'envoi interrompu, réessayez.';
        default:
            return 'erreur lors de l\'envoi (code ' . $code . ').';
    }
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function random_name(string $ext): string
{
    return bin2hex(random_bytes(12)) . '.' . $ext;
}

// Nom de fichier d'origine « nettoyé » (affiché et utilisé au téléchargement)
function clean_filename(string $name): string
{
    $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($name));
    return mb_substr($name, 0, 140);
}

// Aperçu : retourne le chemin relatif (ex. uploads/previews/ab12.jpg), ou null si aucun fichier.
// En cas de problème, ajoute un message à $errors.
function store_preview(array $file, array &$errors): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($msg = upload_error_message((int) $file['error'])) {
        $errors[] = 'Aperçu : ' . $msg;
        return null;
    }
    if ($file['size'] > PREVIEW_MAX) {
        $errors[] = 'Aperçu : image trop lourde (5 Mo maximum).';
        return null;
    }
    // On vérifie le CONTENU réel du fichier (pas seulement son extension)
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !isset(PREVIEW_TYPES[$info[2]])) {
        $errors[] = 'Aperçu : formats acceptés JPG, PNG ou WebP.';
        return null;
    }
    ensure_dir(PREVIEW_DIR);
    $name = random_name(PREVIEW_TYPES[$info[2]]);
    if (!move_uploaded_file($file['tmp_name'], PREVIEW_DIR . $name)) {
        $errors[] = 'Aperçu : impossible d\'enregistrer le fichier (droits du dossier uploads/).';
        return null;
    }
    return 'uploads/previews/' . $name;
}

// Fichier source : retourne ['stored' => nom aléatoire, 'name' => nom d'origine, 'size' => octets] ou null
function store_source(array $file, array &$errors): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($msg = upload_error_message((int) $file['error'])) {
        $errors[] = 'Fichier source : ' . $msg;
        return null;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, SOURCE_EXT, true)) {
        $errors[] = 'Fichier source : formats acceptés ' . strtoupper(implode(', ', SOURCE_EXT)) . '.';
        return null;
    }
    if ($file['size'] > SOURCE_MAX) {
        $errors[] = 'Fichier source : 100 Mo maximum.';
        return null;
    }
    ensure_dir(SOURCE_DIR);
    $stored = random_name($ext);
    if (!move_uploaded_file($file['tmp_name'], SOURCE_DIR . $stored)) {
        $errors[] = 'Fichier source : impossible d\'enregistrer le fichier (droits du dossier uploads/).';
        return null;
    }
    return ['stored' => $stored, 'name' => clean_filename($file['name']), 'size' => (int) $file['size']];
}

// Supprime du disque les fichiers liés à une ressource (ligne de la table resources)
function delete_resource_files(array $res): void
{
    if (!empty($res['image_url']) && strpos($res['image_url'], 'uploads/previews/') === 0) {
        @unlink(PREVIEW_DIR . basename($res['image_url']));
    }
    if (!empty($res['file_path'])) {
        @unlink(SOURCE_DIR . basename($res['file_path']));
    }
}

// Taille lisible : 1 258 291 -> « 1,2 Mo »
function format_size($bytes): string
{
    $bytes = (int) $bytes;
    if ($bytes >= 1048576) { return number_format($bytes / 1048576, 1, ',', ' ') . ' Mo'; }
    if ($bytes >= 1024)    { return number_format($bytes / 1024, 0, ',', ' ') . ' Ko'; }
    return $bytes . ' o';
}
