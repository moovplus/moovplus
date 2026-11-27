<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}


function moeda($valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}


function imagem_existe($caminho): bool
{
    return !empty($caminho) && is_file(__DIR__ . '/../' . $caminho);
}


function link_oferta(array $o): string
{
    if (!empty($o['pacote_id'])) return 'pacote.php?id=' . (int) $o['pacote_id'];
    if (!empty($o['hotel_id']))  return 'hotel.php?id='  . (int) $o['hotel_id'];
    return 'destino.php?id=' . (int) $o['destino_id'];
}
