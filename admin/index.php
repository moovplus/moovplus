
<?php

require_once "../config/conexao.php";
require_once "proteger.php";

$nome = $_SESSION["administrador_nome"] ?? "Administrador";

function totalRegistros($pdo, $tabela) {
    $tabelasPermitidas = [
        "usuarios",
        "destinos",
        "aeroportos",
        "companhias_aereas",
        "voos",
        "hoteis",
        "pacotes",
        "ofertas",
        "reservas",
        "cupons"
    ];

    if (!in_array($tabela, $tabelasPermitidas, true)) {
        return 0;
    }

    return (int) $pdo->query(
        "SELECT COUNT(*) FROM `$tabela`"
    )->fetchColumn();
}

$indicadores = [
    "Usuários" => totalRegistros($pdo, "usuarios"),
    "Destinos" => totalRegistros($pdo, "destinos"),
    "Aeroportos" => totalRegistros($pdo, "aeroportos"),
    "Companhias" => totalRegistros($pdo, "companhias_aereas"),
    "Voos" => totalRegistros($pdo, "voos"),
    "Hotéis" => totalRegistros($pdo, "hoteis"),
    "Pacotes" => totalRegistros($pdo, "pacotes"),
    "Ofertas" => totalRegistros($pdo, "ofertas"),
    "Reservas" => totalRegistros($pdo, "reservas"),
    "Cupons" => totalRegistros($pdo, "cupons")
];

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Painel administrativo | Moovplus</title>

    <link rel="stylesheet" href="assets/css/admin.css">
</head>

<body>

<div class="admin-layout">

    <aside class="sidebar">

        <h2>Moov+</h2>

        <p class="sidebar-descricao">Painel administrativo</p>

        <nav>
            <a href="index.php" class="ativo">Dashboard</a>
            <a href="destinos.php">Destinos</a>
            <a href="aeroportos.php">Aeroportos</a>
            <a href="companhias.php">Companhias aéreas</a>
            <a href="voos.php">Voos</a>
            <a href="hoteis.php">Hotéis</a>
            <a href="comodidades.php">Comodidades</a>
            <a href="pacotes.php">Pacotes</a>
            <a href="ofertas.php">Ofertas</a>
            <a href="cupons.php">Cupons</a>
            <a href="reservas.php">Reservas</a>
            <a href="usuarios.php">Usuários</a>
        </nav>

        <a href="sair.php" class="sair">Sair do painel</a>

    </aside>

    <main class="conteudo">

        <header class="topo">
            <div>
                <h1>Dashboard</h1>
                <p>Bem-vindo, <?= htmlspecialchars($nome) ?>!</p>
            </div>

            <span class="administrador">Administrador</span>
        </header>

        <section class="indicadores">

            <?php foreach ($indicadores as $titulo => $total): ?>

                <div class="card-indicador">
                    <h3><?= htmlspecialchars($titulo) ?></h3>
                    <strong><?= $total ?></strong>
                </div>

            <?php endforeach; ?>

        </section>

        <section class="boas-vindas">

            <h2>Gerencie seu site</h2>

            <p>
                Utilize o menu lateral para cadastrar e administrar
                os destinos, voos, hotéis, pacotes e demais informações
                do Moovplus.
            </p>

        </section>

    </main>

</div>

</body>
</html>