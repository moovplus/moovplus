<?php
// Funções usadas em várias páginas.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Escreve um texto na tela com segurança (evita injeção de HTML).
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// 1234.5 -> "R$ 1.234,50"
function moeda($valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

// Confere se o arquivo da imagem existe na pasta do site.
function imagem_existe($caminho): bool
{
    return !empty($caminho) && is_file(__DIR__ . '/../' . $caminho);
}

// Para onde uma oferta deve levar: pacote, hotel ou destino.
function link_oferta(array $o): string
{
    if (!empty($o['pacote_id'])) return 'pacote.php?id=' . (int) $o['pacote_id'];
    if (!empty($o['hotel_id']))  return 'hotel.php?id='  . (int) $o['hotel_id'];
    return 'destino.php?id=' . (int) $o['destino_id'];
}
