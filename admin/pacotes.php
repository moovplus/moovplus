<?php

require_once "../config/conexao.php";
require_once "proteger.php";

function h($valor)
{
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
      $hotelId = (int) ($_POST["hotel_id"] ?? 0);
      $vooIdaId = (int) ($_POST["voo_ida_id"] ?? 0);
      $vooVoltaId = (int) ($_POST["voo_volta_id"] ?? 0);
      $nome = trim($_POST["nome"] ?? "");
      $descricao = trim($_POST["descricao"] ?? "");
      $noites = (int) ($_POST["quantidade_noites"] ?? 0);
      $preco = $_POST["preco"] ?? "";
      $ativo = isset($_POST["ativo"]) ? 1 : 0;

      if (!$destinoId || !$hotelId || !$vooIdaId || $nome === "") {
        throw new Exception("Preencha nome, destino, hotel e voo de ida.");
      }

      if ($noites < 1) {
        throw new Exception("A quantidade de noites deve ser pelo menos 1.");
      }

      if ($preco === "" || !is_numeric($preco) || $preco < 0) {
        throw new Exception("Informe um preço válido para o pacote.");
      }

      $stmt = $pdo->prepare(
        "SELECT id FROM hoteis WHERE id = ? AND destino_id = ?"
      );
      $stmt->execute([$hotelId, $destinoId]);

      if (!$stmt->fetch()) {
        throw new Exception("O hotel selecionado não pertence ao destino escolhido.");
      }

      $stmt = $pdo->prepare("SELECT id FROM voos WHERE id = ?");
      $stmt->execute([$vooIdaId]);

      if (!$stmt->fetch()) {
        throw new Exception("Voo de ida não encontrado.");
      }

      if ($vooVoltaId > 0) {
        if ($vooVoltaId === $vooIdaId) {
          throw new Exception("O voo de volta não pode ser igual ao voo de ida.");
        }

        $stmt = $pdo->prepare("SELECT id FROM voos WHERE id = ?");
        $stmt->execute([$vooVoltaId]);

        if (!$stmt->fetch()) {
          throw new Exception("Voo de volta não encontrado.");
        }
      } else {
        $vooVoltaId = null;
      }

      if ($id > 0) {
        $sql = "UPDATE pacotes SET
                            destino_id = ?, hotel_id = ?,
                            voo_ida_id = ?, voo_volta_id = ?,
                            nome = ?, descricao = ?, quantidade_noites = ?,
                            preco = ?, ativo = ?
                        WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          $destinoId,
          $hotelId,
          $vooIdaId,
          $vooVoltaId,
          $nome,
          $descricao ?: null,
          $noites,
          $preco,
          $ativo,
          $id
        ]);
      } else {
        $sql = "INSERT INTO pacotes
                        (destino_id, hotel_id, voo_ida_id, voo_volta_id,
                         nome, descricao, quantidade_noites, preco, ativo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          $destinoId,
          $hotelId,
          $vooIdaId,
          $vooVoltaId,
          $nome,
          $descricao ?: null,
          $noites,
          $preco,
          $ativo
        ]);
      }

      header("Location: pacotes.php?sucesso=salvo");
      exit;
    }

    if ($acao === "excluir") {
      $stmt = $pdo->prepare("DELETE FROM pacotes WHERE id = ?");
      $stmt->execute([(int) ($_POST["id"] ?? 0)]);

      header("Location: pacotes.php?sucesso=excluido");
      exit;
    }
  } catch (PDOException $e) {
    $erro = "Não foi possível concluir a operação. Verifique se o pacote está relacionado a alguma reserva ou oferta.";
  } catch (Throwable $e) {
    $erro = $e->getMessage();
  }
}

if (isset($_GET["sucesso"])) {
  $mensagem = $_GET["sucesso"] === "excluido"
    ? "Pacote excluído com sucesso!"
    : "Pacote salvo com sucesso!";
}

$editando = null;

if (isset($_GET["editar"])) {
  $stmt = $pdo->prepare("SELECT * FROM pacotes WHERE id = ?");
  $stmt->execute([(int) $_GET["editar"]]);
  $editando = $stmt->fetch();

  if (!$editando) {
    $erro = "Pacote não encontrado.";
  }
}

$destinos = $pdo->query(
  "SELECT id, nome, cidade FROM destinos ORDER BY nome"
)->fetchAll();

$hoteis = $pdo->query(
  "SELECT h.id, h.nome, h.destino_id, d.nome AS destino_nome
     FROM hoteis h
     INNER JOIN destinos d ON d.id = h.destino_id
     WHERE h.ativo = 1
     ORDER BY h.nome"
)->fetchAll();

$voos = $pdo->query(
  "SELECT id, codigo_referencia, preco
     FROM voos
     WHERE ativo = 1
     ORDER BY id DESC"
)->fetchAll();

$pacotes = $pdo->query(
  "SELECT p.*, d.nome AS destino_nome, h.nome AS hotel_nome
     FROM pacotes p
     INNER JOIN destinos d ON d.id = p.destino_id
     INNER JOIN hoteis h ON h.id = p.hotel_id
     ORDER BY p.criado_em DESC"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pacotes | Moovplus</title>
  <link rel="stylesheet" href="assets/css/cadastros.css">
</head>

<body>

  <div class="admin-layout">

    <aside class="sidebar">
      <h2>Moovplus</h2>
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
        <a href="pacotes.php" class="ativo">Pacotes</a>
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
          <h1>Pacotes</h1>
          <p>Monte pacotes relacionando destinos, hotéis e voos.</p>
        </div>
      </header>

      <?php if ($mensagem): ?>
        <div class="mensagem"><?= h($mensagem) ?></div>
      <?php endif; ?>

      <?php if ($erro): ?>
        <div class="mensagem erro"><?= h($erro) ?></div>
      <?php endif; ?>

      <?php if (!$destinos || !$hoteis || !$voos): ?>
        <div class="mensagem erro">
          Para cadastrar um pacote, você precisa ter destinos, hotéis e voos ativos.
        </div>
      <?php endif; ?>

      <section class="card-form">
        <h2><?= $editando ? "Editar pacote" : "Cadastrar pacote" ?></h2>

        <form method="POST">
          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int) ($editando["id"] ?? 0) ?>">

          <div class="form-grid">

            <div class="campo campo-completo">
              <label>Nome do pacote *</label>
              <input type="text" name="nome" required
                value="<?= h($editando["nome"] ?? "") ?>"
                placeholder="Ex.: Férias em Fernando de Noronha">
            </div>

            <div class="campo">
              <label>Destino *</label>
              <select name="destino_id" id="destino_id" required>
                <option value="">Selecione o destino</option>
                <?php foreach ($destinos as $d): ?>
                  <option value="<?= (int) $d["id"] ?>"
                    <?= (int) ($editando["destino_id"] ?? 0) === (int) $d["id"] ? "selected" : "" ?>>
                    <?= h($d["nome"]) ?> - <?= h($d["cidade"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="campo">
              <label>Hotel *</label>
              <select name="hotel_id" id="hotel_id" required>
                <option value="">Selecione o hotel</option>
                <?php foreach ($hoteis as $h): ?>
                  <option value="<?= (int) $h["id"] ?>"
                    data-destino="<?= (int) $h["destino_id"] ?>"
                    <?= (int) ($editando["hotel_id"] ?? 0) === (int) $h["id"] ? "selected" : "" ?>>
                    <?= h($h["nome"] . " - " . $h["destino_nome"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="campo">
              <label>Voo de ida *</label>
              <select name="voo_ida_id" required>
                <option value="">Selecione o voo</option>
                <?php foreach ($voos as $v): ?>
                  <option value="<?= (int) $v["id"] ?>"
                    <?= (int) ($editando["voo_ida_id"] ?? 0) === (int) $v["id"] ? "selected" : "" ?>>
                    <?= h($v["codigo_referencia"] ?: "Voo #" . $v["id"]) ?>
                    - R$ <?= number_format((float) $v["preco"], 2, ",", ".") ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="campo">
              <label>Voo de volta</label>
              <select name="voo_volta_id">
                <option value="0">Sem voo de volta</option>
                <?php foreach ($voos as $v): ?>
                  <option value="<?= (int) $v["id"] ?>"
                    <?= (int) ($editando["voo_volta_id"] ?? 0) === (int) $v["id"] ? "selected" : "" ?>>
                    <?= h($v["codigo_referencia"] ?: "Voo #" . $v["id"]) ?>
                    - R$ <?= number_format((float) $v["preco"], 2, ",", ".") ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="campo">
              <label>Quantidade de noites *</label>
              <input type="number" name="quantidade_noites" min="1" required
                value="<?= h($editando["quantidade_noites"] ?? "1") ?>">
            </div>

            <div class="campo">
              <label>Preço total do pacote (R$) *</label>
              <input type="number" name="preco" min="0" step="0.01" required
                value="<?= h($editando["preco"] ?? "") ?>">
            </div>

            <div class="campo campo-completo">
              <label>Descrição</label>
              <textarea name="descricao"><?= h($editando["descricao"] ?? "") ?></textarea>
            </div>

            <div class="campo-checkbox">
              <input type="checkbox" name="ativo" id="ativo"
                <?= !$editando || !empty($editando["ativo"]) ? "checked" : "" ?>>
              <label for="ativo">Pacote ativo</label>
            </div>

          </div>

          <div class="acoes-form">
            <button type="submit" class="botao">Salvar pacote</button>

            <?php if ($editando): ?>
              <a href="pacotes.php" class="botao botao-secundario">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="card-tabela">
        <h2>Pacotes cadastrados (<?= count($pacotes) ?>)</h2>

        <div class="tabela-responsiva">
          <table>
            <thead>
              <tr>
                <th>Nome</th>
                <th>Destino</th>
                <th>Hotel</th>
                <th>Noites</th>
                <th>Preço</th>
                <th>Status</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>

              <?php foreach ($pacotes as $p): ?>
                <tr>
                  <td><?= h($p["nome"]) ?></td>
                  <td><?= h($p["destino_nome"]) ?></td>
                  <td><?= h($p["hotel_nome"]) ?></td>
                  <td><?= (int) $p["quantidade_noites"] ?></td>
                  <td>R$ <?= number_format((float) $p["preco"], 2, ",", ".") ?></td>
                  <td><?= $p["ativo"] ? "Ativo" : "Inativo" ?></td>
                  <td>
                    <div class="acoes-tabela">
                      <a class="botao botao-secundario"
                        href="pacotes.php?editar=<?= (int) $p["id"] ?>">Editar</a>

                      <form method="POST" onsubmit="return confirm('Deseja excluir este pacote?')">
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id" value="<?= (int) $p["id"] ?>">
                        <button type="submit" class="botao botao-excluir">Excluir</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>

              <?php if (!$pacotes): ?>
                <tr>
                  <td colspan="7" class="vazio">Nenhum pacote cadastrado.</td>
                </tr>
              <?php endif; ?>

            </tbody>
          </table>
        </div>
      </section>

    </main>
  </div>

  <script>
    const destinoSelect = document.getElementById("destino_id");
    const hotelSelect = document.getElementById("hotel_id");

    function filtrarHoteis() {
      const destinoSelecionado = destinoSelect.value;
      const hotelAtual = hotelSelect.value;

      Array.from(hotelSelect.options).forEach((option, indice) => {
        if (indice === 0) return;

        const corresponde = option.dataset.destino === destinoSelecionado;
        option.hidden = !corresponde;
        option.disabled = !corresponde;
      });

      const opcaoAtual = hotelSelect.selectedOptions[0];

      if (opcaoAtual && opcaoAtual.disabled) {
        hotelSelect.value = "";
      } else if (!destinoSelecionado) {
        hotelSelect.value = "";
      }
    }

    destinoSelect.addEventListener("change", filtrarHoteis);
    filtrarHoteis();
  </script>

</body>

</html>