<?php
require_once __DIR__ . '/funcoes.php';
$titulo = $titulo ?? 'Moov+';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
</head>
<body>
<header class="topo">
  <div class="container topo-interno">
    <a class="logo" href="index.php">Moov+</a>
    <nav class="menu" aria-label="Menu principal">
      <a href="voos.php">Voos</a>
      <a href="hoteis.php">Hotéis</a>
      <a href="pacotes.php">Pacotes</a>
      <a href="index.php#ofertas">Ofertas</a>
    </nav>
    <?php if (!empty($_SESSION['usuario_nome'])): ?>
      <span class="ola">Olá, <?= e($_SESSION['usuario_nome']) ?></span>
      <a class="botao botao-borda" href="sair.php">Sair</a>
    <?php else: ?>
      <a class="botao botao-borda" href="login.php">Entrar</a>
    <?php endif; ?>
  </div>
</header>
<main>
