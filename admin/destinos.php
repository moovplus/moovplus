<?php

require_once "../config/conexao.php";
require_once "proteger.php";

function h($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

$mensagem = "";
$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";

    try {
        if ($acao === "salvar") {
            $id = (int) ($_POST["id"] ?? 0);
            $nome = trim($_POST["nome"] ?? "");
            $cidade = trim($_POST["cidade"] ?? "");
            $estado = trim($_POST["estado"] ?? "");
            $pais = trim($_POST["pais"] ?? "");
            $descricao = trim($_POST["descricao"] ?? "");
            $melhorEpoca = trim($_POST["melhor_epoca"] ?? "");
            $comoChegar = trim($_POST["como_chegar"] ?? "");
            $preco = $_POST["preco_inicial"] ?? "";
            $destaque = isset($_POST["destaque"]) ? 1 : 0;
            $ativo = isset($_POST["ativo"]) ? 1 : 0;

            if ($nome === "" || $cidade === "" || $pais === "") {
                throw new Exception("Preencha nome, cidade e país.");
            }

            if ($preco !== "" && (!is_numeric($preco) || $preco < 0)) {
                throw new Exception("Informe um preço inicial válido.");
            }

            $pdo->beginTransaction();

            if ($id > 0) {
                $sql = "UPDATE destinos SET
                            nome = ?, cidade = ?, estado = ?, pais = ?,
                            descricao = ?, melhor_epoca = ?, como_chegar = ?,
                            preco_inicial = ?, destaque = ?, ativo = ?
                        WHERE id = ?";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nome, $cidade, $estado ?: null, $pais,
                    $descricao ?: null, $melhorEpoca ?: null,
                    $comoChegar ?: null, $preco !== "" ? $preco : null,
                    $destaque, $ativo, $id
                ]);
            } else {
                $sql = "INSERT INTO destinos
                        (nome, cidade, estado, pais, descricao,
                         melhor_epoca, como_chegar, preco_inicial,
                         destaque, ativo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nome, $cidade, $estado ?: null, $pais,
                    $descricao ?: null, $melhorEpoca ?: null,
                    $comoChegar ?: null, $preco !== "" ? $preco : null,
                    $destaque, $ativo
                ]);

                $id = (int) $pdo->lastInsertId();
            }

            if (
                isset($_FILES["imagem"]) &&
                $_FILES["imagem"]["error"] !== UPLOAD_ERR_NO_FILE
            ) {
                if ($_FILES["imagem"]["error"] !== UPLOAD_ERR_OK) {
                    throw new Exception("Erro ao enviar a imagem.");
                }

                if ($_FILES["imagem"]["size"] > 5 * 1024 * 1024) {
                    throw new Exception("A imagem deve ter no máximo 5 MB.");
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES["imagem"]["tmp_name"]);

                $extensoes = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                if (!isset($extensoes[$mime])) {
                    throw new Exception("Use uma imagem JPG, PNG ou WEBP.");
                }

                $pasta = __DIR__ . "/uploads/destinos/";

                if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) {
                    throw new Exception("Não foi possível criar a pasta de imagens.");
                }

                $nomeArquivo = bin2hex(random_bytes(16)) . "." . $extensoes[$mime];
                $destinoArquivo = $pasta . $nomeArquivo;

                if (!move_uploaded_file($_FILES["imagem"]["tmp_name"], $destinoArquivo)) {
                    throw new Exception("Não foi possível salvar a imagem.");
                }

                $caminho = "admin/uploads/destinos/" . $nomeArquivo;

                $pdo->prepare(
                    "UPDATE destino_imagens
                     SET imagem_principal = 0
                     WHERE destino_id = ?"
                )->execute([$id]);

                $stmt = $pdo->prepare(
                    "INSERT INTO destino_imagens
                     (destino_id, caminho_imagem, texto_alternativo,
                      imagem_principal, ordem)
                     VALUES (?, ?, ?, 1, 0)"
                );

                $stmt->execute([$id, $caminho, $nome]);
            }

            $pdo->commit();

            header("Location: destinos.php?sucesso=salvo");
            exit;
        }

        if ($acao === "excluir") {
            $id = (int) ($_POST["id"] ?? 0);

            $stmt = $pdo->prepare("DELETE FROM destinos WHERE id = ?");
            $stmt->execute([$id]);

            header("Location: destinos.php?sucesso=excluido");
            exit;
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $erro = $e instanceof PDOException
            ? "Não foi possível salvar. Verifique se o destino está sendo utilizado por outros registros."
            : $e->getMessage();
    }
}

if (isset($_GET["sucesso"])) {
    $mensagem = $_GET["sucesso"] === "excluido"
        ? "Destino excluído com sucesso!"
        : "Destino salvo com sucesso!";
}

$editando = null;

if (isset($_GET["editar"])) {
    $stmt = $pdo->prepare("SELECT * FROM destinos WHERE id = ?");
    $stmt->execute([(int) $_GET["editar"]]);
    $editando = $stmt->fetch();

    if (!$editando) {
        $erro = "Destino não encontrado.";
    }
}

$destinos = $pdo->query(
    "SELECT d.*,
        (SELECT caminho_imagem
         FROM destino_imagens i
         WHERE i.destino_id = d.id
         ORDER BY i.imagem_principal DESC, i.ordem
         LIMIT 1) AS imagem
     FROM destinos d
     ORDER BY d.criado_em DESC"
)->fetchAll();

function valor($campo, $editando) {
    return h($editando[$campo] ?? "");
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Destinos | Moovplus</title>
    <link rel="stylesheet" href="assets/css/cadastros.css">
</head>
<body>

<div class="admin-layout">

    <aside class="sidebar">
        <h2>Moov+</h2>
        <p class="sidebar-descricao">Painel administrativo</p>

         <nav>
            <a href="index.php">Dashboard</a>
            <a href="destinos.php" class="ativo">Destinos</a>
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
            <a href="pagamentos.php">Pagamentos</a>
        </nav>

        <a href="sair.php" class="sair">Sair do painel</a>
    </aside>

    <main class="conteudo">

        <header class="topo">
            <div>
                <h1>Destinos</h1>
                <p>Cadastre e gerencie os destinos do Moovplus.</p>
            </div>
        </header>

        <?php if ($mensagem): ?>
            <div class="mensagem"><?= h($mensagem) ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= h($erro) ?></div>
        <?php endif; ?>

        <section class="card-form">

            <h2><?= $editando ? "Editar destino" : "Cadastrar destino" ?></h2>

            <form method="POST" enctype="multipart/form-data">

                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= (int) ($editando["id"] ?? 0) ?>">

                <div class="form-grid">

                    <div class="campo">
                        <label>Nome do destino *</label>
                        <input type="text" name="nome" required value="<?= valor("nome", $editando) ?>">
                    </div>

                    <div class="campo">
                        <label>Cidade *</label>
                        <input type="text" name="cidade" required value="<?= valor("cidade", $editando) ?>">
                    </div>

                    <div class="campo">
                        <label>Estado</label>
                        <input type="text" name="estado" value="<?= valor("estado", $editando) ?>">
                    </div>

                    <div class="campo">
                        <label>País *</label>
                        <input type="text" name="pais" required value="<?= valor("pais", $editando) ?>">
                    </div>

                    <div class="campo">
                        <label>Preço inicial</label>
                        <input type="number" name="preco_inicial" min="0" step="0.01"
                            value="<?= valor("preco_inicial", $editando) ?>">
                    </div>

                    <div class="campo">
                        <label>Melhor época para visitar</label>
                        <input type="text" name="melhor_epoca" value="<?= valor("melhor_epoca", $editando) ?>">
                    </div>

                    <div class="campo campo-completo">
                        <label>Descrição</label>
                        <textarea name="descricao"><?= valor("descricao", $editando) ?></textarea>
                    </div>

                    <div class="campo campo-completo">
                        <label>Como chegar</label>
                        <textarea name="como_chegar"><?= valor("como_chegar", $editando) ?></textarea>
                    </div>

                    <div class="campo campo-completo">
                        <label>Imagem principal (JPG, PNG ou WEBP, até 5 MB)</label>
                        <input type="file" name="imagem" accept=".jpg,.jpeg,.png,.webp">
                    </div>

                    <div class="campo-checkbox">
                        <input type="checkbox" name="destaque" id="destaque"
                            <?= !empty($editando["destaque"]) ? "checked" : "" ?>>
                        <label for="destaque">Mostrar nos destinos populares</label>
                    </div>

                    <div class="campo-checkbox">
                        <input type="checkbox" name="ativo" id="ativo"
                            <?= !$editando || !empty($editando["ativo"]) ? "checked" : "" ?>>
                        <label for="ativo">Destino ativo</label>
                    </div>

                </div>

                <div class="acoes-form">
                    <button type="submit" class="botao">Salvar destino</button>

                    <?php if ($editando): ?>
                        <a href="destinos.php" class="botao botao-secundario">Cancelar</a>
                    <?php endif; ?>
                </div>

            </form>

        </section>

        <section class="card-tabela">

            <h2>Destinos cadastrados (<?= count($destinos) ?>)</h2>

            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th>Imagem</th>
                            <th>Nome</th>
                            <th>Localização</th>
                            <th>Preço inicial</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($destinos as $d): ?>
                        <tr>
                            <td>
                                <?php if ($d["imagem"]): ?>
                                    <img class="imagem-destino"
                                         src="../<?= h($d["imagem"]) ?>" alt="">
                                <?php else: ?>
                                    Sem imagem
                                <?php endif; ?>
                            </td>

                            <td><?= h($d["nome"]) ?></td>
                            <td><?= h($d["cidade"]) ?> - <?= h($d["pais"]) ?></td>
                            <td>
                                <?= $d["preco_inicial"] !== null
                                    ? "R$ " . number_format((float) $d["preco_inicial"], 2, ",", ".")
                                    : "Não informado" ?>
                            </td>
                            <td><?= $d["ativo"] ? "Ativo" : "Inativo" ?></td>

                            <td>
                                <div class="acoes-tabela">
                                    <a class="botao botao-secundario"
                                       href="destinos.php?editar=<?= (int) $d["id"] ?>">
                                        Editar
                                    </a>

                                    <form method="POST"
                                          onsubmit="return confirm('Deseja realmente excluir este destino?')">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $d["id"] ?>">
                                        <button class="botao botao-excluir" type="submit">
                                            Excluir
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$destinos): ?>
                        <tr>
                            <td colspan="6" class="vazio">Nenhum destino cadastrado.</td>
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