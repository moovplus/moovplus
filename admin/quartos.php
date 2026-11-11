
<?php

require_once "../config/conexao.php";
require_once "proteger.php";

function h($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

$erro = "";
$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";

    try {
        if ($acao === "salvar") {
            $id = (int) ($_POST["id"] ?? 0);
            $hotelId = (int) ($_POST["hotel_id"] ?? 0);
            $nome = trim($_POST["nome"] ?? "");
            $descricao = trim($_POST["descricao"] ?? "");
            $capacidade = (int) ($_POST["capacidade"] ?? 0);
            $quantidade = (int) ($_POST["quantidade"] ?? 0);
            $preco = $_POST["preco_diaria"] ?? "";
            $cancelamentoGratis = isset($_POST["cancelamento_gratis"]) ? 1 : 0;
            $ativo = isset($_POST["ativo"]) ? 1 : 0;

            if ($hotelId <= 0 || $nome === "") {
                throw new Exception("Selecione um hotel e informe o nome do quarto.");
            }

            if ($capacidade < 1 || $quantidade < 0) {
                throw new Exception("A capacidade deve ser maior que zero e a quantidade não pode ser negativa.");
            }

            if ($preco === "" || !is_numeric($preco) || $preco < 0) {
                throw new Exception("Informe um preço diário válido.");
            }

            $hotelExiste = $pdo->prepare("SELECT id FROM hoteis WHERE id = ?");
            $hotelExiste->execute([$hotelId]);

            if (!$hotelExiste->fetch()) {
                throw new Exception("Hotel selecionado não encontrado.");
            }

            if ($id > 0) {
                $sql = "UPDATE quartos SET
                            hotel_id = ?, nome = ?, descricao = ?,
                            capacidade = ?, quantidade = ?, preco_diaria = ?,
                            cancelamento_gratis = ?, ativo = ?
                        WHERE id = ?";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $hotelId, $nome, $descricao ?: null,
                    $capacidade, $quantidade, $preco,
                    $cancelamentoGratis, $ativo, $id
                ]);
            } else {
                $sql = "INSERT INTO quartos
                        (hotel_id, nome, descricao, capacidade, quantidade,
                         preco_diaria, cancelamento_gratis, ativo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $hotelId, $nome, $descricao ?: null,
                    $capacidade, $quantidade, $preco,
                    $cancelamentoGratis, $ativo
                ]);
            }

            header("Location: quartos.php?sucesso=salvo");
            exit;
        }

        if ($acao === "excluir") {
            $stmt = $pdo->prepare("DELETE FROM quartos WHERE id = ?");
            $stmt->execute([(int) ($_POST["id"] ?? 0)]);

            header("Location: quartos.php?sucesso=excluido");
            exit;
        }

    } catch (PDOException $e) {
        $erro = "Não foi possível concluir a operação. Verifique se o quarto está relacionado a alguma reserva.";
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

if (isset($_GET["sucesso"])) {
    $mensagem = $_GET["sucesso"] === "excluido"
        ? "Quarto excluído com sucesso!"
        : "Quarto salvo com sucesso!";
}

$editando = null;

if (isset($_GET["editar"])) {
    $stmt = $pdo->prepare("SELECT * FROM quartos WHERE id = ?");
    $stmt->execute([(int) $_GET["editar"]]);
    $editando = $stmt->fetch();

    if (!$editando) {
        $erro = "Quarto não encontrado.";
    }
}

$hoteis = $pdo->query(
    "SELECT id, nome FROM hoteis ORDER BY nome"
)->fetchAll();

$quartos = $pdo->query(
    "SELECT q.*, h.nome AS hotel_nome
     FROM quartos q
     INNER JOIN hoteis h ON h.id = q.hotel_id
     ORDER BY h.nome, q.nome"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quartos | Moovplus</title>
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
            <a href="quartos.php" class="ativo">Quartos</a>
            <a href="pacotes.php">Pacotes</a>
            <a href="ofertas.php">Ofertas</a>
            <a href="cupons.php">Cupons</a>
            <a href="reservas.php">Reservas</a>
            <a href="usuarios.php">Usuários</a>
            <a href="pagamentos.php">Pagamentos</a>
        </nav>

        <a href="sair.php" class="sair">Sair do painel</a>
    </aside>

    <main class="conteudo">

        <header class="topo">
            <div>
                <h1>Quartos</h1>
                <p>Cadastre os tipos de quartos disponíveis em cada hotel.</p>
            </div>
        </header>

        <?php if ($mensagem): ?>
            <div class="mensagem"><?= h($mensagem) ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= h($erro) ?></div>
        <?php endif; ?>

        <section class="card-form">
            <h2><?= $editando ? "Editar quarto" : "Cadastrar quarto" ?></h2>

            <?php if (!$hoteis): ?>
                <p class="vazio">Cadastre um hotel antes de cadastrar seus quartos.</p>
                <a href="hoteis.php" class="botao">Cadastrar hotel</a>
            <?php else: ?>

            <form method="POST">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= (int) ($editando["id"] ?? 0) ?>">

                <div class="form-grid">

                    <div class="campo">
                        <label>Hotel *</label>
                        <select name="hotel_id" required>
                            <option value="">Selecione um hotel</option>
                            <?php foreach ($hoteis as $hotel): ?>
                                <option value="<?= (int) $hotel["id"] ?>"
                                    <?= (int) ($editando["hotel_id"] ?? 0) === (int) $hotel["id"] ? "selected" : "" ?>>
                                    <?= h($hotel["nome"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label>Nome do quarto *</label>
                        <input type="text" name="nome" required
                            value="<?= h($editando["nome"] ?? "") ?>"
                            placeholder="Ex.: Suíte Deluxe">
                    </div>

                    <div class="campo">
                        <label>Capacidade de pessoas *</label>
                        <input type="number" name="capacidade" min="1" required
                            value="<?= h($editando["capacidade"] ?? "2") ?>">
                    </div>

                    <div class="campo">
                        <label>Quantidade de quartos *</label>
                        <input type="number" name="quantidade" min="0" required
                            value="<?= h($editando["quantidade"] ?? "1") ?>">
                    </div>

                    <div class="campo">
                        <label>Preço da diária (R$) *</label>
                        <input type="number" name="preco_diaria" min="0" step="0.01" required
                            value="<?= h($editando["preco_diaria"] ?? "") ?>">
                    </div>

                    <div class="campo campo-completo">
                        <label>Descrição</label>
                        <textarea name="descricao"><?= h($editando["descricao"] ?? "") ?></textarea>
                    </div>

                    <div class="campo-checkbox">
                        <input type="checkbox" name="cancelamento_gratis" id="cancelamento_gratis"
                            <?= !empty($editando["cancelamento_gratis"]) ? "checked" : "" ?>>
                        <label for="cancelamento_gratis">Permite cancelamento grátis</label>
                    </div>

                    <div class="campo-checkbox">
                        <input type="checkbox" name="ativo" id="ativo"
                            <?= !$editando || !empty($editando["ativo"]) ? "checked" : "" ?>>
                        <label for="ativo">Quarto ativo</label>
                    </div>

                </div>

                <div class="acoes-form">
                    <button type="submit" class="botao">Salvar quarto</button>

                    <?php if ($editando): ?>
                        <a href="quartos.php" class="botao botao-secundario">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php endif; ?>
        </section>

        <section class="card-tabela">
            <h2>Quartos cadastrados (<?= count($quartos) ?>)</h2>

            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th>Quarto</th>
                            <th>Hotel</th>
                            <th>Capacidade</th>
                            <th>Quantidade</th>
                            <th>Diária</th>
                            <th>Cancelamento grátis</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($quartos as $q): ?>
                        <tr>
                            <td><?= h($q["nome"]) ?></td>
                            <td><?= h($q["hotel_nome"]) ?></td>
                            <td><?= (int) $q["capacidade"] ?> pessoa(s)</td>
                            <td><?= (int) $q["quantidade"] ?></td>
                            <td>R$ <?= number_format((float) $q["preco_diaria"], 2, ",", ".") ?></td>
                            <td><?= $q["cancelamento_gratis"] ? "Sim" : "Não" ?></td>
                            <td><?= $q["ativo"] ? "Ativo" : "Inativo" ?></td>
                            <td>
                                <div class="acoes-tabela">
                                    <a class="botao botao-secundario"
                                       href="quartos.php?editar=<?= (int) $q["id"] ?>">Editar</a>

                                    <form method="POST" onsubmit="return confirm('Deseja excluir este quarto?')">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $q["id"] ?>">
                                        <button class="botao botao-excluir" type="submit">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$quartos): ?>
                        <tr><td colspan="8" class="vazio">Nenhum quarto cadastrado.</td></tr>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

</body>
</html>
