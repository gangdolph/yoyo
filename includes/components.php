<?php
function render_button(string $href, string $label, array $attrs = []): string {
    $attrString = '';
    foreach ($attrs as $key => $value) {
        $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
    }
    return '<a class="btn" href="' . htmlspecialchars($href) . '"' . $attrString . '>' . htmlspecialchars($label) . '</a>';
}

function render_card(?string $title, string $body): string {
    $html = '<div class="card">';
    if ($title) {
        $html .= '<h3>' . htmlspecialchars($title) . '</h3>';
    }
    $html .= $body . '</div>';
    return $html;
}
?>
