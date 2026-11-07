<?php
// config/ai.php : configuration FACULTATIVE de l'analyse par IA (assistant portfolio).
// Sans clé, le site fonctionne normalement : seul le diagnostic à règles est affiché.
// Pour activer l'analyse IA : mettez votre clé d'API Anthropic ci-dessous (ne la publiez jamais sur GitHub).
'api_key' => 'VOTRE_CLE_API',                 // ex : 'sk-ant-...'
const AI_MODEL   = 'claude-sonnet-5-5'; // modèle utilisé pour l'analyse
