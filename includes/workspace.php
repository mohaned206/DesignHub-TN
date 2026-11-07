<?php
// includes/workspace.php : barre latérale commune aux espaces de travail (designer, client, administrateur).
// $items : liste de [clé, lien, icône, libellé, compteur|null] ; "-" comme clé = séparateur.

function ws_sidebar(array $user, string $subtitle, ?string $avatarUrl, array $items, string $active, string $tipTitle = '', string $tipHtml = '', string $extraHtml = ''): string
{
    $html = '<aside class="ws-side"><div class="ws-id">' . avatar_html($user['full_name'], $avatarUrl, 'lg')
          . '<h2>' . e($user['full_name']) . '</h2><p>' . e($subtitle) . '</p>' . $extraHtml . '</div>'
          . '<nav class="ws-nav" aria-label="Espace de travail">';
    foreach ($items as $it) {
        if ($it[0] === '-') {
            $html .= '<hr>';
            continue;
        }
        [$key, $href, $ico, $label] = $it;
        $count = $it[4] ?? null;
        $html .= '<a href="' . e($href) . '" class="' . ($key === $active ? 'active' : '') . '">' . icon($ico) . '<span>' . e($label) . '</span>'
               . ($count ? '<span class="count">' . (int) $count . '</span>' : '') . '</a>';
    }
    $html .= '</nav>';
    if ($tipTitle !== '') {
        $html .= '<div class="ws-tip"><strong>' . e($tipTitle) . '</strong>' . $tipHtml . '</div>';
    }
    return $html . '</aside>';
}

// Carte « indicateur » : icône, valeur (animée), libellé. $href rend la carte cliquable.
function kpi_card(string $icon, string $tone, $value, string $label, ?string $href = null, string $suffix = '', ?string $note = null, bool $warn = false): string
{
    $tag = $href ? 'a' : 'div';
    $num = is_numeric($value) ? '<strong data-count="' . e($value) . '"' . ($suffix !== '' ? ' data-suffix="' . e($suffix) . '"' : '') . '>'
        . e(number_format((float) $value, 0, ',', ' ')) . e($suffix) . '</strong>' : '<strong>' . e($value) . '</strong>';
    return '<' . $tag . ($href ? ' href="' . e($href) . '"' : '') . ' class="kpi">' . icon_tile($icon, $tone)
        . ($note ? '<span class="kpi-note' . ($warn ? ' warn' : '') . '">' . e($note) . '</span>' : '')
        . '<div>' . $num . '<span class="lbl">' . e($label) . '</span></div></' . $tag . '>';
}

// État vide : icône, titre, texte et action facultative
function empty_state(string $icon, string $title, string $text, ?string $href = null, string $cta = ''): string
{
    return '<div class="empty-state">' . icon_tile($icon, 'blue') . '<strong>' . e($title) . '</strong><div>' . $text . '</div>'
        . ($href ? '<a class="btn btn-small" href="' . e($href) . '">' . e($cta) . '</a>' : '') . '</div>';
}
