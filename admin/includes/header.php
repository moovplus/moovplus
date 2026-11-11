
<?php
$tituloPagina = $tituloPagina ?? "Painel administrativo";
$descricaoPagina = $descricaoPagina ?? "";
$paginaAtual = basename($_SERVER["PHP_SELF"]);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina, ENT_QUOTES, "UTF-8") ?> | Moovplus</title>
    <link rel="stylesheet" href="assets/css/cadastros.css">
</head>
<body>

<div class="admin-layout">

    <?php require __DIR__ . "/sidebar.php"; ?>

    <main class="conteudo">

        <header class="topo">
            <div>
                <h1><?= htmlspecialchars($tituloPagina, ENT_QUOTES, "UTF-8") ?></h1>
                <p><?= htmlspecialchars($descricaoPagina, ENT_QUOTES, "UTF-8") ?></p>
            </div>
        </header>
