
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
            $destinoId = (int) ($_POST["destino_id"] ?? 0);
            $nome = trim($_POST["nome"] ?? "");
            $descricao = trim($_POST["descricao"] ?? "");
            $endereco = trim($_POST["endereco"] ?? "");
            $estrelas = $_POST["estrelas"] ?? "";
            $preco = $_POST["preco_diaria"] ?? "";
            $latitude = $_POST["latitude"] ?? "";
            $longitude = $_POST["longitude"] ?? "";
            $ativo = isset($_POST["ativo"]) ? 1 : 0;

            if ($nome === "" || $destinoId <= 0 || $endereco === "") {
                throw new Exception("Preencha nome, destino e endereço.");
            }

            $destinoExiste = $pdo->prepare("SELECT id FROM destinos WHERE id = ?");
            $destinoExiste->execute([$destinoId]);

            if (!$destinoExiste->fetch()) {
                throw new Exception("Destino selecionado não encontrado.");
            }

            if ($estrelas !== "" && (!is_numeric($estrelas) || $estrelas < 0 || $estrelas > 5)) {
                throw new Exception("Informe uma classificação entre 0 e 5 estrelas.");
            }

            if ($preco === "" || !is_numeric($preco) || $preco < 0) {
                throw new Exception("Informe um preço diário válido.");
            }

            if (($latitude !== "" && !is_numeric($latitude)) ||
                ($longitude !== "" && !is_numeric($longitude))) {
                throw new Exception("Latitude e longitude devem ser números.");
            }

            if ($id > 0) {
                $sql = "UPDATE hoteis SET
                            destino_id = ?, nome = ?, descricao = ?,
                            endereco = ?, estrelas = ?, preco_diaria = ?,
                            latitude = ?, longitude = ?, ativo = ?
                        WHERE id = ?";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $destinoId, $nome, $descricao ?: null, $endereco,
                    $estrelas !== "" ? $estrelas : null, $preco,
                    $latitude !== "" ? $latitude : null,
                    $longitude !== "" ? $longitude : null,
                    $ativo, $id
                ]);
            } else {
                $sql = "INSERT INTO hoteis
                        (destino_id, nome, descricao, endereco, estrelas,
                         preco_diaria, latitude, longitude, ativo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $destinoId, $nome, $descricao ?: null, $endereco,
                    $estrelas !== "" ? $estrelas : null, $preco,
                    $latitude !== "" ? $latitude : null,
                    $longitude !== "" ? $longitude : null,
                    $ativo
                ]);

                $id = (int) $pdo->lastInsertId();
            }

            if (isset($_FILES["imagem"]) && $_FILES["imagem"]["error"] !== UPLOAD_ERR_NO_FILE) {
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

                $pasta = __DIR__ . "/uploads/hoteis/";

                if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) {
                    throw new Exception("Não foi possível criar a pasta de imagens.");
                }

                $nomeArquivo = bin2hex(random_bytes(16)) . "." . $extensoes[$mime];

                if (!move_uploaded_file($_FILES["imagem"]["tmp_name"], $pasta . $nomeArquivo)) {
                    throw new Exception("Não foi possível salvar a imagem.");
                }

                $caminho = "admin/uploads/hoteis/" . $nomeArquivo;

                $pdo->prepare(
                    "UPDATE hotel_imagens SET imagem_principal = 0 WHERE hotel_id = ?"
                )->execute([$id]);

                $stmt = $pdo->prepare(
                    "INSERT INTO hotel_imagens
                     (hotel_id, caminho_imagem, texto_alternativo, imagem_principal, ordem)
                     VALUES (?, ?, ?, 1, 0)"
                );

                $stmt->execute([$id, $caminho, $nome]);
            }

            header("Location: hoteis.php?sucesso=salvo");
            exit;
        }

        if ($acao === "excluir") {
            $stmt = $pdo->prepare("DELETE FROM hoteis WHERE id = ?");
            $stmt->execute([(int) ($_POST["id"] ?? 0)]);

            header("Location: hoteis.php?sucesso=excluido");
            exit;
        }

    } catch (PDOException $e) {
        $erro = "Não foi possível concluir a operação. Verifique se o hotel está relacionado a quartos, pacotes ou reservas.";
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

if (isset($_GET["sucesso"])) {
    $mensagem = $_GET["sucesso"] === "excluido"
        ? "Hotel excluído com sucesso!"
        : "Hotel salvo com sucesso!";
}

$editando = null;

if (isset($_GET["editar"])) {
    $stmt = $pdo->prepare("SELECT * FROM hoteis WHERE id = ?");
    $stmt->execute([(int) $_GET["editar"]]);
    $editando = $stmt->fetch();

    if (!$editando) {
        $erro = "Hotel não encontrado.";
    }
}

$destinos = $pdo->query("SELECT id, nome, cidade FROM destinos ORDER BY nome")->fetchAll();

$hoteis = $pdo->query(
    "SELECT h.*,
        d.nome AS destino_nome,
        (SELECT caminho_imagem
         FROM hotel_imagens i
         WHERE i.hotel_id = h.id
         ORDER BY i.imagem_principal DESC, i.ordem
         LIMIT 1) AS imagem
     FROM hoteis h
     INNER JOIN destinos d ON d.id = h.destino_id
     ORDER BY h.nome"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotéis | Moovplus</title>
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
            <a href="hoteis.php" class="ativo">Hotéis</a>
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
                <h1>Hotéis</h1>
                <p>Cadastre e gerencie os hotéis disponíveis.</p>
            </div>
        </header>

        <?php if ($mensagem): ?>
            <div class="mensagem"><?= h($mensagem) ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= h($erro) ?></div>
        <?php endif; ?>

        <section class="card-form">
            <h2><?= $editando ? "Editar hotel" : "Cadastrar hotel" ?></h2>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= (int) ($editando["id"] ?? 0) ?>">

                <div class="form-grid">

                    <div class="campo">
                        <label>Nome do hotel *</label>
                        <input type="text" name="nome" required value="<?= h($editando["nome"] ?? "") ?>">
                    </div>

                    <div class="campo">
                        <label>Destino *</label>
                        <select name="destino_id" required>
                            <option value="">Selecione um destino</option>
                            <?php foreach ($destinos as $d): ?>
                                <option value="<?= (int) $d["id"] ?>"
                                    <?= (int) ($editando["destino_id"] ?? 0) === (int) $d["id"] ? "selected" : "" ?>>
                                    <?= h($d["nome"]) ?> - <?= h($d["cidade"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo campo-completo">
                        <label>Endereço *</label>
                        <input type="text" name="endereco" required value="<?= h($editando["endereco"] ?? "") ?>">
                    </div>

                    <div class="campo">
                        <label>Quantidade de estrelas</label>
                        <input type="number" name="estrelas" min="0" max="5" step="0.5"
                            value="<?= h($editando["estrelas"] ?? "") ?>">
                    </div>

                    <div class="campo">
                        <label>Preço da diária (R$) *</label>
                        <input type="number" name="preco_diaria" min="0" step="0.01" required
                            value="<?= h($editando["preco_diaria"] ?? "") ?>">
                    </div>

                    <div class="campo">
                        <label>Latitude</label>
                        <input type="number" name="latitude" step="any"
                            value="<?= h($editando["latitude"] ?? "") ?>">
                    </div>

                    <div class="campo">
                        <label>Longitude</label>
                        <input type="number" name="longitude" step="any"
                            value="<?= h($editando["longitude"] ?? "") ?>">
                    </div>

                    <div class="campo campo-completo">
                        <label>Descrição</label>
                        <textarea name="descricao"><?= h($editando["descricao"] ?? "") ?></textarea>
                    </div>

                    <div class="campo campo-completo">
                        <label>Imagem principal (JPG, PNG ou WEBP, até 5 MB)</label>
                        <input type="file" name="imagem" accept=".jpg,.jpeg,.png,.webp">
                    </div>

                    <div class="campo-checkbox">
                        <input type="checkbox" name="ativo" id="ativo"
                            <?= !$editando || !empty($editando["ativo"]) ? "checked" : "" ?>>
                        <label for="ativo">Hotel ativo</label>
                    </div>

                </div>

                <div class="acoes-form">
                    <button type="submit" class="botao">Salvar hotel</button>

                    <?php if ($editando): ?>
                        <a href="hoteis.php" class="botao botao-secundario">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card-tabela">
            <h2>Hotéis cadastrados (<?= count($hoteis) ?>)</h2>

            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th>Imagem</th>
                            <th>Hotel</th>
                            <th>Destino</th>
                            <th>Estrelas</th>
                            <th>Diária</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($hoteis as $hotel): ?>
                        <tr>
                            <td>
                                <?php if ($hotel["imagem"]): ?>
                                    <img class="imagem-destino" src="../<?= h($hotel["imagem"]) ?>" alt="">
                                <?php else: ?>
                                    Sem imagem
                                <?php endif; ?>
                            </td>
                            <td><?= h($hotel["nome"]) ?></td>
                            <td><?= h($hotel["destino_nome"]) ?></td>
                            <td><?= h($hotel["estrelas"] ?? "-") ?></td>
                            <td>R$ <?= number_format((float) $hotel["preco_diaria"], 2, ",", ".") ?></td>
                            <td><?= $hotel["ativo"] ? "Ativo" : "Inativo" ?></td>
                            <td>
                                <div class="acoes-tabela">
                                    <a class="botao botao-secundario"
                                       href="hoteis.php?editar=<?= (int) $hotel["id"] ?>">Editar</a>

                                    <form method="POST" onsubmit="return confirm('Deseja excluir este hotel?')">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $hotel["id"] ?>">
                                        <button class="botao botao-excluir" type="submit">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$hoteis): ?>
                        <tr><td colspan="7" class="vazio">Nenhum hotel cadastrado.</td></tr>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

</body>
</html>
