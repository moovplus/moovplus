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
            $nome = trim($_POST["nome"] ?? "");
            $sigla = strtoupper(trim($_POST["sigla"] ?? ""));
            $logo = trim($_POST["logo"] ?? "");
            $ativo = isset($_POST["ativo"]) ? 1 : 0;

            if ($nome === "") {
                throw new Exception("Informe o nome da companhia aérea.");
            }

            if (strlen($sigla) > 10) {
                throw new Exception("A sigla deve ter no máximo 10 caracteres.");
            }

            if ($id > 0) {
                $sql = "UPDATE companhias_aereas
                        SET nome = ?, sigla = ?, logo = ?, ativo = ?
                        WHERE id = ?";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nome,
                    $sigla ?: null,
                    $logo ?: null,
                    $ativo,
                    $id
                ]);
            } else {
                $sql = "INSERT INTO companhias_aereas
                        (nome, sigla, logo, ativo)
                        VALUES (?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nome,
                    $sigla ?: null,
                    $logo ?: null,
                    $ativo
                ]);
            }

            header("Location: companhias.php?sucesso=salvo");
            exit;
        }

        if ($acao === "excluir") {
            $id = (int) ($_POST["id"] ?? 0);

            $stmt = $pdo->prepare(
                "DELETE FROM companhias_aereas WHERE id = ?"
            );

            $stmt->execute([$id]);

            header("Location: companhias.php?sucesso=excluido");
            exit;
        }

    } catch (PDOException $e) {
        $erro = "Não foi possível salvar. Verifique se a sigla já está cadastrada ou se a companhia está vinculada a algum trecho de voo.";
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

if (isset($_GET["sucesso"])) {
    $mensagem = $_GET["sucesso"] === "excluido"
        ? "Companhia excluída com sucesso!"
        : "Companhia salva com sucesso!";
}

$editando = null;

if (isset($_GET["editar"])) {
    $stmt = $pdo->prepare(
        "SELECT * FROM companhias_aereas WHERE id = ?"
    );

    $stmt->execute([(int) $_GET["editar"]]);
    $editando = $stmt->fetch();

    if (!$editando) {
        $erro = "Companhia não encontrada.";
    }
}

$companhias = $pdo->query(
    "SELECT * FROM companhias_aereas ORDER BY nome"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Companhias aéreas | Moovplus</title>
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
            <a href="companhias.php" class="ativo">Companhias aéreas</a>
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
                <h1>Companhias aéreas</h1>
                <p>Gerencie as companhias disponíveis no Moovplus.</p>
            </div>
        </header>

        <?php if ($mensagem): ?>
            <div class="mensagem"><?= h($mensagem) ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= h($erro) ?></div>
        <?php endif; ?>

        <section class="card-form">

            <h2><?= $editando ? "Editar companhia" : "Cadastrar companhia" ?></h2>

            <form method="POST">

                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= (int) ($editando["id"] ?? 0) ?>">

                <div class="form-grid">

                    <div class="campo">
                        <label>Nome da companhia *</label>
                        <input type="text" name="nome" required
                            value="<?= h($editando["nome"] ?? "") ?>"
                            placeholder="Ex.: LATAM Airlines">
                    </div>

                    <div class="campo">
                        <label>Sigla</label>
                        <input type="text" name="sigla" maxlength="10"
                            value="<?= h($editando["sigla"] ?? "") ?>"
                            placeholder="Ex.: LA">
                    </div>

                    <div class="campo campo-completo">
                        <label>Endereço do logo (URL ou caminho da imagem)</label>
                        <input type="text" name="logo"
                            value="<?= h($editando["logo"] ?? "") ?>"
                            placeholder="Ex.: assets/img/companhias/latam.png">
                    </div>

                    <div class="campo-checkbox">
                        <input type="checkbox" name="ativo" id="ativo"
                            <?= !$editando || !empty($editando["ativo"]) ? "checked" : "" ?>>
                        <label for="ativo">Companhia ativa</label>
                    </div>

                </div>

                <div class="acoes-form">
                    <button type="submit" class="botao">Salvar companhia</button>

                    <?php if ($editando): ?>
                        <a href="companhias.php" class="botao botao-secundario">Cancelar</a>
                    <?php endif; ?>
                </div>

            </form>

        </section>

        <section class="card-tabela">

            <h2>Companhias cadastradas (<?= count($companhias) ?>)</h2>

            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Sigla</th>
                            <th>Logo</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($companhias as $c): ?>
                        <tr>
                            <td><?= h($c["nome"]) ?></td>
                            <td><?= h($c["sigla"] ?? "-") ?></td>
                            <td><?= $c["logo"] ? h($c["logo"]) : "Não informado" ?></td>
                            <td><?= $c["ativo"] ? "Ativa" : "Inativa" ?></td>

                            <td>
                                <div class="acoes-tabela">

                                    <a class="botao botao-secundario"
                                       href="companhias.php?editar=<?= (int) $c["id"] ?>">
                                        Editar
                                    </a>

                                    <form method="POST"
                                        onsubmit="return confirm('Deseja excluir esta companhia?')">

                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $c["id"] ?>">

                                        <button type="submit" class="botao botao-excluir">
                                            Excluir
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$companhias): ?>
                        <tr>
                            <td colspan="5" class="vazio">Nenhuma companhia cadastrada.</td>
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