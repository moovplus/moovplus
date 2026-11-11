
<?php

require_once "../config/conexao.php";
require_once "proteger.php";

function h($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

$erro = "";
$mensagem = "";

$statusPermitidos = [
    "pendente",
    "aprovado",
    "recusado",
    "estornado",
    "cancelado"
];

$nomesStatus = [
    "pendente" => "Pendente",
    "aprovado" => "Aprovado",
    "recusado" => "Recusado",
    "estornado" => "Estornado",
    "cancelado" => "Cancelado"
];

$metodosPagamento = [
    "pix" => "PIX",
    "cartao_credito" => "Cartão de crédito",
    "cartao_debito" => "Cartão de débito",
    "boleto" => "Boleto",
    "outro" => "Outro"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";

    try {
        if ($acao === "salvar") {
            $id = (int) ($_POST["id"] ?? 0);
            $status = $_POST["status"] ?? "";

            if ($id <= 0) {
                throw new Exception("Pagamento inválido.");
            }

            if (!in_array($status, $statusPermitidos, true)) {
                throw new Exception("Status de pagamento inválido.");
            }

            if ($status === "aprovado") {
                $stmt = $pdo->prepare("
                    UPDATE pagamentos
                    SET status = ?,
                        pago_em = COALESCE(pago_em, NOW())
                    WHERE id = ?
                ");

                $stmt->execute([$status, $id]);

            } else {
                $stmt = $pdo->prepare("
                    UPDATE pagamentos
                    SET status = ?
                    WHERE id = ?
                ");

                $stmt->execute([$status, $id]);
            }

            header("Location: pagamentos.php?sucesso=salvo");
            exit;
        }

    } catch (PDOException $e) {
        $erro = "Não foi possível atualizar o pagamento.";
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

if (isset($_GET["sucesso"])) {
    $mensagem = "Pagamento atualizado com sucesso!";
}

$editando = null;

if (isset($_GET["editar"])) {
    $stmt = $pdo->prepare("
        SELECT
            pg.*,
            r.codigo AS reserva_codigo,
            r.tipo AS reserva_tipo,
            u.nome AS usuario_nome,
            u.sobrenome AS usuario_sobrenome,
            u.email AS usuario_email
        FROM pagamentos pg
        INNER JOIN reservas r ON r.id = pg.reserva_id
        LEFT JOIN usuarios u ON u.id = r.usuario_id
        WHERE pg.id = ?
    ");

    $stmt->execute([(int) $_GET["editar"]]);
    $editando = $stmt->fetch();

    if (!$editando) {
        $erro = "Pagamento não encontrado.";
    }
}

$stmt = $pdo->query("
    SELECT
        pg.*,
        r.codigo AS reserva_codigo,
        r.tipo AS reserva_tipo,
        u.nome AS usuario_nome,
        u.sobrenome AS usuario_sobrenome,
        u.email AS usuario_email
    FROM pagamentos pg
    INNER JOIN reservas r ON r.id = pg.reserva_id
    LEFT JOIN usuarios u ON u.id = r.usuario_id
    ORDER BY pg.criado_em DESC
");

$pagamentos = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamentos | Moovplus</title>
    <link rel="stylesheet" href="assets/css/cadastros.css">
</head>
<body>

<div class="admin-layout">

    <aside class="sidebar">
        <h2>Moov+</h2>
        <p class="sidebar-descricao">Painel administrativo</p>

         <nav>
            <a href="index.php">Dashboard</a>
            <a href="destinos.php">Destinos</a>
            <a href="aeroportos.php">Aeroportos</a>
            <a href="companhias.php">Companhias aéreas</a>
            <a href="voos.php">Voos</a>
            <a href="hoteis.php">Hotéis</a>
            <a href="comodidades.php">Comodidades</a>
            <a href="quartos.php">Quartos</a>
            <a href="pacotes.php">Pacotes</a>
            <a href="ofertas.php">Ofertas</a>
            <a href="cupons.php">Cupons</a>
            <a href="reservas.php">Reservas</a>
            <a href="usuarios.php">Usuários</a>
            <a href="pagamentos.php" class="ativo">Pagamentos</a>
        </nav>

        <a href="sair.php" class="sair">Sair do painel</a>
    </aside>

    <main class="conteudo">

        <header class="topo">
            <div>
                <h1>Pagamentos</h1>
                <p>Consulte e gerencie os pagamentos das reservas.</p>
            </div>
        </header>

        <?php if ($mensagem): ?>
            <div class="mensagem"><?= h($mensagem) ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= h($erro) ?></div>
        <?php endif; ?>

        <?php if ($editando): ?>

            <section class="card-form">

                <h2>Editar pagamento</h2>

                <div class="form-grid">

                    <div class="campo">
                        <label>Código da reserva</label>
                        <input
                            type="text"
                            value="<?= h($editando["reserva_codigo"]) ?>"
                            disabled
                        >
                    </div>

                    <div class="campo">
                        <label>Cliente</label>
                        <input
                            type="text"
                            value="<?= h(trim(($editando["usuario_nome"] ?? "") . " " . ($editando["usuario_sobrenome"] ?? ""))) ?>"
                            disabled
                        >
                    </div>

                    <div class="campo">
                        <label>Método de pagamento</label>
                        <input
                            type="text"
                            value="<?= h($metodosPagamento[$editando["metodo"]] ?? $editando["metodo"]) ?>"
                            disabled
                        >
                    </div>

                    <div class="campo">
                        <label>Valor</label>
                        <input
                            type="text"
                            value="R$ <?= number_format((float) $editando["valor"], 2, ",", ".") ?>"
                            disabled
                        >
                    </div>

                    <div class="campo">
                        <label>Parcelas</label>
                        <input
                            type="text"
                            value="<?= (int) ($editando["parcelas"] ?? 1) ?>"
                            disabled
                        >
                    </div>

                    <div class="campo">
                        <label>Identificador da transação</label>
                        <input
                            type="text"
                            value="<?= h($editando["identificador_transacao"] ?: "Não informado") ?>"
                            disabled
                        >
                    </div>

                </div>

                <form method="POST">

                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="id" value="<?= (int) $editando["id"] ?>">

                    <div class="form-grid">

                        <div class="campo">
                            <label>Status do pagamento</label>

                            <select name="status" required>
                                <?php foreach ($statusPermitidos as $status): ?>
                                    <option
                                        value="<?= h($status) ?>"
                                        <?= $editando["status"] === $status ? "selected" : "" ?>
                                    >
                                        <?= h($nomesStatus[$status]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <div class="acoes-form">

                        <button type="submit" class="botao">
                            Salvar pagamento
                        </button>

                        <a href="pagamentos.php" class="botao botao-secundario">
                            Cancelar
                        </a>

                    </div>

                </form>

            </section>

        <?php endif; ?>

        <section class="card-tabela">

            <h2>Pagamentos cadastrados (<?= count($pagamentos) ?>)</h2>

            <div class="tabela-responsiva">

                <table>

                    <thead>
                        <tr>
                            <th>Reserva</th>
                            <th>Cliente</th>
                            <th>Método</th>
                            <th>Valor</th>
                            <th>Parcelas</th>
                            <th>Status</th>
                            <th>Data do pagamento</th>
                            <th>Ações</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($pagamentos as $pg): ?>

                            <tr>

                                <td><?= h($pg["reserva_codigo"]) ?></td>

                                <td>
                                    <?= h(trim(
                                        ($pg["usuario_nome"] ?? "") . " " .
                                        ($pg["usuario_sobrenome"] ?? "")
                                    )) ?>
                                    <br>
                                    <small><?= h($pg["usuario_email"] ?? "") ?></small>
                                </td>

                                <td>
                                    <?= h($metodosPagamento[$pg["metodo"]] ?? $pg["metodo"]) ?>
                                </td>

                                <td>
                                    R$ <?= number_format(
                                        (float) $pg["valor"],
                                        2,
                                        ",",
                                        "."
                                    ) ?>
                                </td>

                                <td><?= (int) ($pg["parcelas"] ?? 1) ?>x</td>

                                <td>
                                    <?= h($nomesStatus[$pg["status"]] ?? $pg["status"]) ?>
                                </td>

                                <td>
                                    <?= $pg["pago_em"]
                                        ? date("d/m/Y H:i", strtotime($pg["pago_em"]))
                                        : "Não pago" ?>
                                </td>

                                <td>
                                    <div class="acoes-tabela">

                                        <a
                                            href="pagamentos.php?editar=<?= (int) $pg["id"] ?>"
                                            class="botao botao-secundario"
                                        >
                                            Editar
                                        </a>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (!$pagamentos): ?>
                            <tr>
                                <td colspan="8" class="vazio">
                                    Nenhum pagamento cadastrado.
                                </td>
                            </tr>
                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

</body>
</html>
