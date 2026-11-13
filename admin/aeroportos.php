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
      $nome = trim($_POST["nome"] ?? "");
      $codigo = strtoupper(trim($_POST["codigo_iata"] ?? ""));
      $cidade = trim($_POST["cidade"] ?? "");
      $estado = trim($_POST["estado"] ?? "");
      $pais = trim($_POST["pais"] ?? "");
      $destinoId = (int) ($_POST["destino_id"] ?? 0);
      $ativo = isset($_POST["ativo"]) ? 1 : 0;

      if ($nome === "" || $cidade === "" || $pais === "") {
        throw new Exception("Preencha o nome, cidade e país.");
      }

      if (!preg_match("/^[A-Z]{3}$/", $codigo)) {
        throw new Exception("O código IATA deve conter exatamente 3 letras.");
      }

      if ($destinoId > 0) {
        $verifica = $pdo->prepare("SELECT id FROM destinos WHERE id = ?");
        $verifica->execute([$destinoId]);

        if (!$verifica->fetch()) {
          throw new Exception("Destino selecionado não encontrado.");
        }
      } else {
        $destinoId = null;
      }

      if ($id > 0) {
        $sql = "UPDATE aeroportos SET
                            nome = ?, codigo_iata = ?, cidade = ?,
                            estado = ?, pais = ?, destino_id = ?, ativo = ?
                        WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          $nome,
          $codigo,
          $cidade,
          $estado ?: null,
          $pais,
          $destinoId,
          $ativo,
          $id
        ]);
      } else {
        $sql = "INSERT INTO aeroportos
                        (nome, codigo_iata, cidade, estado, pais, destino_id, ativo)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          $nome,
          $codigo,
          $cidade,
          $estado ?: null,
          $pais,
          $destinoId,
          $ativo
        ]);
      }

      header("Location: aeroportos.php?sucesso=salvo");
      exit;
    }

    if ($acao === "excluir") {
      $id = (int) ($_POST["id"] ?? 0);

      $stmt = $pdo->prepare("DELETE FROM aeroportos WHERE id = ?");
      $stmt->execute([$id]);

      header("Location: aeroportos.php?sucesso=excluido");
      exit;
    }
  } catch (PDOException $e) {
    $erro = "Não foi possível salvar. Confira se o código IATA já existe ou se o aeroporto está sendo utilizado em algum voo.";
  } catch (Throwable $e) {
    $erro = $e->getMessage();
  }
}

if (isset($_GET["sucesso"])) {
  $mensagem = $_GET["sucesso"] === "excluido"
    ? "Aeroporto excluído com sucesso!"
    : "Aeroporto salvo com sucesso!";
}

$editando = null;

if (isset($_GET["editar"])) {
  $stmt = $pdo->prepare("SELECT * FROM aeroportos WHERE id = ?");
  $stmt->execute([(int) $_GET["editar"]]);
  $editando = $stmt->fetch();

  if (!$editando) {
    $erro = "Aeroporto não encontrado.";
  }
}

$destinos = $pdo->query(
  "SELECT id, nome, cidade, pais FROM destinos ORDER BY nome"
)->fetchAll();

$aeroportos = $pdo->query(
  "SELECT a.*, d.nome AS destino_nome
     FROM aeroportos a
     LEFT JOIN destinos d ON d.id = a.destino_id
     ORDER BY a.cidade, a.nome"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aeroportos | Moovplus</title>
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
        <a href="aeroportos.php" class="ativo">Aeroportos</a>
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
          <h1>Aeroportos</h1>
          <p>Cadastre os aeroportos disponíveis para os voos.</p>
        </div>
      </header>

      <?php if ($mensagem): ?>
        <div class="mensagem"><?= h($mensagem) ?></div>
      <?php endif; ?>

      <?php if ($erro): ?>
        <div class="mensagem erro"><?= h($erro) ?></div>
      <?php endif; ?>

      <section class="card-form">

        <h2><?= $editando ? "Editar aeroporto" : "Cadastrar aeroporto" ?></h2>

        <form method="POST">

          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int) ($editando["id"] ?? 0) ?>">

          <div class="form-grid">

            <div class="campo">
              <label>Nome do aeroporto *</label>
              <input type="text" name="nome" required
                value="<?= h($editando["nome"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>Código IATA *</label>
              <input type="text" name="codigo_iata" maxlength="3"
                minlength="3" required placeholder="Ex.: GRU"
                value="<?= h($editando["codigo_iata"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>Cidade *</label>
              <input type="text" name="cidade" required
                value="<?= h($editando["cidade"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>Estado</label>
              <input type="text" name="estado"
                value="<?= h($editando["estado"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>País *</label>
              <input type="text" name="pais" required
                value="<?= h($editando["pais"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>Destino relacionado</label>
              <select name="destino_id">
                <option value="0">Nenhum destino</option>

                <?php foreach ($destinos as $d): ?>
                  <option value="<?= (int) $d["id"] ?>"
                    <?= (int) ($editando["destino_id"] ?? 0) === (int) $d["id"] ? "selected" : "" ?>>
                    <?= h($d["nome"]) ?> - <?= h($d["cidade"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="campo-checkbox">
              <input type="checkbox" name="ativo" id="ativo"
                <?= !$editando || !empty($editando["ativo"]) ? "checked" : "" ?>>
              <label for="ativo">Aeroporto ativo</label>
            </div>

          </div>

          <div class="acoes-form">
            <button type="submit" class="botao">Salvar aeroporto</button>

            <?php if ($editando): ?>
              <a href="aeroportos.php" class="botao botao-secundario">Cancelar</a>
            <?php endif; ?>
          </div>

        </form>

      </section>

      <section class="card-tabela">

        <h2>Aeroportos cadastrados (<?= count($aeroportos) ?>)</h2>

        <div class="tabela-responsiva">
          <table>
            <thead>
              <tr>
                <th>Nome</th>
                <th>Código IATA</th>
                <th>Cidade</th>
                <th>País</th>
                <th>Destino</th>
                <th>Status</th>
                <th>Ações</th>
              </tr>
            </thead>

            <tbody>

              <?php foreach ($aeroportos as $a): ?>
                <tr>
                  <td><?= h($a["nome"]) ?></td>
                  <td><?= h($a["codigo_iata"]) ?></td>
                  <td><?= h($a["cidade"]) ?></td>
                  <td><?= h($a["pais"]) ?></td>
                  <td><?= h($a["destino_nome"] ?? "Não relacionado") ?></td>
                  <td><?= $a["ativo"] ? "Ativo" : "Inativo" ?></td>

                  <td>
                    <div class="acoes-tabela">

                      <a class="botao botao-secundario"
                        href="aeroportos.php?editar=<?= (int) $a["id"] ?>">
                        Editar
                      </a>

                      <form method="POST"
                        onsubmit="return confirm('Deseja excluir este aeroporto?')">

                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id" value="<?= (int) $a["id"] ?>">

                        <button type="submit" class="botao botao-excluir">
                          Excluir
                        </button>
                      </form>

                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>

              <?php if (!$aeroportos): ?>
                <tr>
                  <td colspan="7" class="vazio">Nenhum aeroporto cadastrado.</td>
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