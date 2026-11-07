<?php
// includes/ai.php : appel facultatif à une API d'IA (Anthropic) depuis le serveur.
// La clé reste côté serveur (config/ai.php) : elle n'est jamais envoyée au navigateur.
require_once __DIR__ . '/../config/ai.php';

function ai_enabled(): bool
{
    return AI_API_KEY !== '' && function_exists('curl_init');
}

// Envoie une consigne à l'IA et retourne le texte de la réponse, ou null en cas de problème
function ai_complete(string $prompt): ?string
{
    if (!ai_enabled()) {
        return null;
    }
    $payload = json_encode([
        'model'      => AI_MODEL,
        'max_tokens' => 800,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . AI_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS     => $payload,
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status !== 200) {
        return null;
    }
    $data = json_decode($response, true);
    $text = '';
    foreach (($data['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= $block['text'];
        }
    }
    return $text !== '' ? $text : null;
}
