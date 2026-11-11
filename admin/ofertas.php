
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
            $titulo = trim($_POST["titulo"] ?? "");
            $descricao = trim($_POST["descricao"] ?? "");
            $destinoId = (int) ($_POST["destino_id"] ?? 0);
            $hotelId = (int) ($_POST["hotel_id"] ?? 0);
            $pacoteId = (int) ($_POST["pacote_id"] ?? 0);
            $preco = $_POST["preco_promocional"] ?? "";
            $inicio = $_POST["validade_inicio"] ?? "";
            $fim = $_POST["validade_fim"] ?? "";
            $imagem = trim($_POST["imagem"] ?? "");
            $ativo = isset($_POST["ativo"]) ? 1 : 0;

            if ($titulo === "") {
                throw new Exception("Informe o título da oferta.");
            }

            if ($preco === "" || !is_numeric($preco) || $preco < 0) {
                throw new Exception("Informe um preço promocional válido.");
            }

            $relacionamentos = (int) ($destinoId > 0)
                + (int) ($hotelId > 0)
                + (int) ($pacoteId > 0);

            if ($relacionamentos !== 1) {
                throw new Exception("Selecione apenas um destino, hotel ou pacote para relacionar à oferta.");
            }

            if ($inicio === "" || $fim === "") {
                throw new Exception("Informe a data inicial e final da oferta.");
            }

            $dataInicio = DateTime::createFromFormat("Y-m-d", $inicio);
            $dataFim = DateTime::createFromFormat("Y-m-d", $fim);

            if (!$dataInicio || !$dataFim || $dataFim < $dataInicio) {
                throw new Exception("A data final deve ser igual ou posterior à data inicial.");
            }

            if ($destinoId > 0) {
                $stmt = $pdo->prepare("SELECT id FROM destinos WHERE id = ?");
                $stmt->execute([$destinoId]);

                if (!$stmt->fetch()) {
                    throw new Exception("Destino não encontrado.");
                }
            }

            if ($hotelId > 0) {
                $stmt = $pdo->prepare("SELECT id FROM hoteis WHERE id = ?");
                $stmt->execute([$hotelId]);

                if (!$stmt->fetch()) {
                    throw new Exception("Hotel não encontrado.");
                }
            }

            if ($pacoteId > 0) {
                $stmt = $pdo->prepare("SELECT id FROM pacotes WHERE id = ?");
                $stmt->execute([$pacoteId]);

                if (!$stmt->fetch()) {
                    throw new Exception("Pacote não encontrado.");
                }
            }

            $destinoId = $destinoId ?: null;
            $hotelId = $hotelId ?: null;
            $pacoteId = $pacoteId ?: null;

            if ($id > 0) {
                $sql = "UPDATE ofertas SET
                            titulo = ?, descricao = ?, destino_id = ?,
                            hotel_id = ?, pacote_id = ?,
                            preco_promocional = ?, validade_inicio = ?,
                            validade_fim = ?, imagem = ?, ativo = ?
                        WHERE id = ?";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $titulo, $descricao ?: null, $destinoId,
                    $hotelId, $pacoteId, $preco, $inicio,
                    $fim, $imagem ?: null, $ativo, $id
                ]);
            } else {
                $sql = "INSERT INTO ofertas
                        (titulo, descricao, destino_id, hotel_id,
                         pacote_id, preco_promocional, validade_inicio,
                         validade_fim, imagem, ativo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $titulo, $descricao ?: null, $destinoId,
                    $hotelId, $pacoteId, $preco, $inicio,
                    $fim, $imagem ?: null, $ativo
                ]);
            }

            header("Location: ofertas.php?sucesso=salvo");
            exit;
        }

        if ($acao === "excluir") {
            $stmt = $pdo->prepare("DELETE FROM ofertas WHERE id = ?");
            $stmt->execute([(int) ($_POST["id"] ?? 0)]);

            header("Location: ofertas.php?sucesso=excluido");
            exit;
        }

    } catch (PDOException $e) {
        $erro = "Não foi possível concluir a operação. Verifique os dados informados.";
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

if (isset($_GET["sucesso"])) {
    $mensagem = $_GET["sucesso"] === "excluido"
        ? "Oferta excluída com sucesso!"
        : "Oferta salva com sucesso!";
}

$editando = null;

if (isset($_GET["editar"])) {
    $stmt = $pdo->prepare("SELECT * FROM ofertas WHERE id = ?");
    $stmt->execute([(int) $_GET["editar"]]);
    $editando = $stmt->fetch();

    if (!$editando) {
        $erro = "Oferta não encontrada.";
    }
}

$destinos = $pdo->query(
    "SELECT id, nome, cidade FROM destinos ORDER BY nome"
)->fetchAll();

$hoteis = $pdo->query(
    "SELECT id, nome FROM hoteis ORDER BY nome"
)->fetchAll();

$pacotes = $pdo->query(
    "SELECT id, nome FROM pacotes ORDER BY nome"
)->fetchAll();

$ofertas = $pdo->query(
    "SELECT o.*,
        d.nome AS destino_nome,
        h.nome AS hotel_nome,
        p.nome AS pacote_nome
     FROM ofertas o
     LEFT JOIN destinos d ON d.id = o.destino_id
     LEFT JOIN hoteis h ON h.id = o.hotel_id
     LEFT JOIN pacotes p ON p.id = o.pacote_id
     ORDER BY o.criado_em DESC"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ofertas | Moovplus</title>
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
            <a href="ofertas.php" class="ativo">Ofertas</a>
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
                <h1>Ofertas</h1>
                <p>Crie e gerencie as promoções do Moovplus.</p>
            </div>
        </header>

        <?php if ($mensagem): ?>
            <div class="mensagem"><?= h($mensagem) ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= h($erro) ?></div>
        <?php endif; ?>

        <section class="card-form">
            <h2><?= $editando ? "Editar oferta" : "Cadastrar oferta" ?></h2>

            <form method="POST">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= (int) ($editando["id"] ?? 0) ?>">

                <div class="form-grid">

                    <div class="campo campo-completo">
                        <label>Título da oferta *</label>
                        <input type="text" name="titulo" required
                            value="<?= h($editando["titulo"] ?? "") ?>"
                            placeholder="Ex.: Promoção especial para o Rio de Janeiro">
                    </div>

                    <div class="campo">
                        <label>Destino</label>
                        <select name="destino_id" id="destino_id">
                            <option value="0">Nenhum destino</option>
                            <?php foreach ($destinos as $d): ?>
                                <option value="<?= (int) $d["id"] ?>"
                                    <?= (int) ($editando["destino_id"] ?? 0) === (int) $d["id"] ? "selected" : "" ?>>
                                    <?= h($d["nome"] . " - " . $d["cidade"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label>Hotel</label>
                        <select name="hotel_id" id="hotel_id">
                            <option value="0">Nenhum hotel</option>
                            <?php foreach ($hoteis as $hotel): ?>
                                <option value="<?= (int) $hotel["id"] ?>"
                                    <?= (int) ($editando["hotel_id"] ?? 0) === (int) $hotel["id"] ? "selected" : "" ?>>
                                    <?= h($hotel["nome"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label>Pacote</label>
                        <select name="pacote_id" id="pacote_id">
                            <option value="0">Nenhum pacote</option>
                            <?php foreach ($pacotes as $p): ?>
                                <option value="<?= (int) $p["id"] ?>"
                                    <?= (int) ($editando["pacote_id"] ?? 0) === (int) $p["id"] ? "selected" : "" ?>>
                                    <?= h($p["nome"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label>Preço promocional (R$) *</label>
                        <input type="number" name="preco_promocional"
                            min="0" step="0.01" required
                            value="<?= h($editando["preco_promocional"] ?? "") ?>">
                    </div>

                    <div class="campo">
                        <label>Início da oferta *</label>
                        <input type="date" name="validade_inicio" required
                            value="<?= h($editando["validade_inicio"] ?? "") ?>">
                    </div>

                    <div class="campo">
                        <label>Fim da oferta *</label>
                        <input type="date" name="validade_fim" required
                            value="<?= h($editando["validade_fim"] ?? "") ?>">
                    </div>

                    <div class="campo campo-completo">
                        <label>Imagem da oferta (URL ou caminho da imagem)</label>
                        <input type="text" name="imagem"
                            value="<?= h($editando["imagem"] ?? "") ?>"
                            placeholder="Ex.: assets/img/ofertas/praia.jpg">
                    </div>

                    <div class="campo campo-completo">
                        <label>Descrição</label>
                        <textarea name="descricao"><?= h($editando["descricao"] ?? "") ?></textarea>
                    </div>

                    <div class="campo-checkbox">
                        <input type="checkbox" name="ativo" id="ativo"
                            <?= !$editando || !empty($editando["ativo"]) ? "checked" : "" ?>>
                        <label for="ativo">Oferta ativa</label>
                    </div>

                </div>

                <div class="acoes-form">
                    <button type="submit" class="botao">Salvar oferta</button>

                    <?php if ($editando): ?>
                        <a href="ofertas.php" class="botao botao-secundario">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card-tabela">
            <h2>Ofertas cadastradas (<?= count($ofertas) ?>)</h2>

            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Relacionado a</th>
                            <th>Preço promocional</th>
                            <th>Validade</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($ofertas as $o): ?>
                        <?php
                        if ($o["destino_id"]) {
                            $relacionado = $o["destino_nome"];
                        } elseif ($o["hotel_id"]) {
                            $relacionado = $o["hotel_nome"];
                        } elseif ($o["pacote_id"]) {
                            $relacionado = $o["pacote_nome"];
                        } else {
                            $relacionado = "Sem relacionamento";
                        }
                        ?>

                        <tr>
                            <td><?= h($o["titulo"]) ?></td>
                            <td><?= h($relacionado) ?></td>
                            <td>R$ <?= number_format((float) $o["preco_promocional"], 2, ",", ".") ?></td>
                            <td>
                                <?= date("d/m/Y", strtotime($o["validade_inicio"])) ?>
                                até
                                <?= date("d/m/Y", strtotime($o["validade_fim"])) ?>
                            </td>
                            <td><?= $o["ativo"] ? "Ativa" : "Inativa" ?></td>
                            <td>
                                <div class="acoes-tabela">
                                    <a class="botao botao-secundario"
                                       href="ofertas.php?editar=<?= (int) $o["id"] ?>">Editar</a>

                                    <form method="POST" onsubmit="return confirm('Deseja excluir esta oferta?')">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $o["id"] ?>">
                                        <button type="submit" class="botao botao-excluir">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$ofertas): ?>
                        <tr><td colspan="6" class="vazio">Nenhuma oferta cadastrada.</td></tr>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

<script>
const destino = document.getElementById("destino_id");
const hotel = document.getElementById("hotel_id");
const pacote = document.getElementById("pacote_id");

function atualizarRelacionamentos(alterado) {
    if (alterado === destino && destino.value !== "0") {
        hotel.value = "0";
        pacote.value = "0";
    }

    if (alterado === hotel && hotel.value !== "0") {
        destino.value = "0";
        pacote.value = "0";
    }

    if (alterado === pacote && pacote.value !== "0") {
        destino.value = "0";
        hotel.value = "0";
    }
}

destino.addEventListener("change", () => atualizarRelacionamentos(destino));
hotel.addEventListener("change", () => atualizarRelacionamentos(hotel));
pacote.addEventListener("change", () => atualizarRelacionamentos(pacote));
</script>

</body>
</html>
