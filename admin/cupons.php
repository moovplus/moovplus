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
      $codigo = strtoupper(trim($_POST["codigo"] ?? ""));
      $tipo = $_POST["tipo_desconto"] ?? "";
      $valor = (float) ($_POST["valor_desconto"] ?? 0);
      $inicio = $_POST["validade_inicio"] ?? "";
      $fim = $_POST["validade_fim"] ?? "";
      $limite = trim($_POST["limite_uso"] ?? "");
      $ativo = isset($_POST["ativo"]) ? 1 : 0;

      if ($codigo === "" || $valor <= 0 || $inicio === "" || $fim === "") {
        throw new Exception("Preencha todos os campos obrigatórios.");
      }

      if (!in_array($tipo, ["percentual", "valor_fixo"], true)) {
        throw new Exception("Tipo de desconto inválido.");
      }

      if ($tipo === "percentual" && $valor > 100) {
        throw new Exception("O desconto percentual não pode ultrapassar 100%.");
      }

      if ($fim < $inicio) {
        throw new Exception("A validade final não pode ser anterior à inicial.");
      }

      if ($limite !== "" && (int) $limite < 1) {
        throw new Exception("O limite de utilização deve ser maior que zero.");
      }

      $limite = $limite === "" ? null : (int) $limite;

      if ($id > 0) {
        $sql = "UPDATE cupons SET
                            codigo = ?,
                            tipo_desconto = ?,
                            valor_desconto = ?,
                            validade_inicio = ?,
                            validade_fim = ?,
                            limite_uso = ?,
                            ativo = ?
                        WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          $codigo,
          $tipo,
          $valor,
          $inicio,
          $fim,
          $limite,
          $ativo,
          $id
        ]);
      } else {
        $sql = "INSERT INTO cupons
                        (codigo, tipo_desconto, valor_desconto,
                         validade_inicio, validade_fim, limite_uso, ativo)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          $codigo,
          $tipo,
          $valor,
          $inicio,
          $fim,
          $limite,
          $ativo
        ]);
      }

      header("Location: cupons.php?sucesso=salvo");
      exit;
    }

    if ($acao === "excluir") {
      $id = (int) ($_POST["id"] ?? 0);

      $stmt = $pdo->prepare("DELETE FROM cupons WHERE id = ?");
      $stmt->execute([$id]);

      header("Location: cupons.php?sucesso=excluido");
      exit;
    }
  } catch (PDOException $e) {
    $erro = "Não foi possível salvar. Confira se o código já existe ou se o cupom está sendo utilizado em alguma reserva.";
  } catch (Throwable $e) {
    $erro = $e->getMessage();
  }
}

if (isset($_GET["sucesso"])) {
  $mensagem = $_GET["sucesso"] === "excluido"
    ? "Cupom excluído com sucesso!"
    : "Cupom salvo com sucesso!";
}

$editando = null;

if (isset($_GET["editar"])) {
  $stmt = $pdo->prepare("SELECT * FROM cupons WHERE id = ?");
  $stmt->execute([(int) $_GET["editar"]]);
  $editando = $stmt->fetch();

  if (!$editando) {
    $erro = "Cupom não encontrado.";
  }
}

$cupons = $pdo->query(
  "SELECT * FROM cupons ORDER BY criado_em DESC"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cupons | Moovplus</title>
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
        <a href="pacotes.php">Pacotes</a>
        <a href="ofertas.php">Ofertas</a>
        <a href="cupons.php" class="ativo">Cupons</a>
        <a href="reservas.php">Reservas</a>
        <a href="usuarios.php">Usuários</a>
        <a href="pagamentos.php">Pagamentos</a>
      </nav>

      <a href="sair.php" class="sair">Sair do painel</a>
    </aside>

    <main class="conteudo">

      <header class="topo">
        <div>
          <h1>Cupons</h1>
          <p>Cadastre e gerencie os cupons de desconto.</p>
        </div>
      </header>

      <?php if ($mensagem): ?>
        <div class="mensagem"><?= h($mensagem) ?></div>
      <?php endif; ?>

      <?php if ($erro): ?>
        <div class="mensagem erro"><?= h($erro) ?></div>
      <?php endif; ?>

      <section class="card-form">

        <h2><?= $editando ? "Editar cupom" : "Cadastrar cupom" ?></h2>

        <form method="POST">

          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int) ($editando["id"] ?? 0) ?>">

          <div class="form-grid">

            <div class="campo">
              <label>Código do cupom *</label>
              <input
                type="text"
                name="codigo"
                required
                placeholder="Ex.: VIAGEM10"
                value="<?= h($editando["codigo"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>Tipo de desconto *</label>
              <select name="tipo_desconto" required>
                <option value="percentual"
                  <?= ($editando["tipo_desconto"] ?? "percentual") === "percentual" ? "selected" : "" ?>>
                  Percentual (%)
                </option>

                <option value="valor_fixo"
                  <?= ($editando["tipo_desconto"] ?? "") === "valor_fixo" ? "selected" : "" ?>>
                  Valor fixo (R$)
                </option>
              </select>
            </div>

            <div class="campo">
              <label>Valor do desconto *</label>
              <input
                type="number"
                name="valor_desconto"
                min="0.01"
                step="0.01"
                required
                value="<?= h($editando["valor_desconto"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>Limite de utilizações</label>
              <input
                type="number"
                name="limite_uso"
                min="1"
                placeholder="Deixe vazio para ilimitado"
                value="<?= h($editando["limite_uso"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>Início da validade *</label>
              <input
                type="date"
                name="validade_inicio"
                required
                value="<?= h($editando["validade_inicio"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>Fim da validade *</label>
              <input
                type="date"
                name="validade_fim"
                required
                value="<?= h($editando["validade_fim"] ?? "") ?>">
            </div>

            <div class="campo-checkbox">
              <input
                type="checkbox"
                name="ativo"
                id="ativo"
                <?= !$editando || !empty($editando["ativo"]) ? "checked" : "" ?>>
              <label for="ativo">Cupom ativo</label>
            </div>

          </div>

          <div class="acoes-form">
            <button type="submit" class="botao">Salvar cupom</button>

            <?php if ($editando): ?>
              <a href="cupons.php" class="botao botao-secundario">Cancelar</a>
            <?php endif; ?>
          </div>

        </form>

      </section>

      <section class="card-tabela">

        <h2>Cupons cadastrados (<?= count($cupons) ?>)</h2>

        <div class="tabela-responsiva">
          <table>

            <thead>
              <tr>
                <th>Código</th>
                <th>Tipo</th>
                <th>Desconto</th>
                <th>Validade</th>
                <th>Utilizações</th>
                <th>Status</th>
                <th>Ações</th>
              </tr>
            </thead>

            <tbody>

              <?php foreach ($cupons as $c): ?>
                <tr>
                  <td><?= h($c["codigo"]) ?></td>

                  <td>
                    <?= $c["tipo_desconto"] === "percentual"
                      ? "Percentual"
                      : "Valor fixo" ?>
                  </td>

                  <td>
                    <?php if ($c["tipo_desconto"] === "percentual"): ?>
                      <?= number_format((float) $c["valor_desconto"], 2, ",", ".") ?>%
                    <?php else: ?>
                      R$ <?= number_format((float) $c["valor_desconto"], 2, ",", ".") ?>
                    <?php endif; ?>
                  </td>

                  <td>
                    <?= date("d/m/Y", strtotime($c["validade_inicio"])) ?>
                    até
                    <?= date("d/m/Y", strtotime($c["validade_fim"])) ?>
                  </td>

                  <td>
                    <?= (int) $c["usos_realizados"] ?>
                    /
                    <?= $c["limite_uso"] === null
                      ? "Ilimitado"
                      : (int) $c["limite_uso"] ?>
                  </td>

                  <td><?= $c["ativo"] ? "Ativo" : "Inativo" ?></td>

                  <td>
                    <div class="acoes-tabela">

                      <a
                        class="botao botao-secundario"
                        href="cupons.php?editar=<?= (int) $c["id"] ?>">
                        Editar
                      </a>

                      <form
                        method="POST"
                        onsubmit="return confirm('Deseja excluir este cupom?')">
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

              <?php if (!$cupons): ?>
                <tr>
                  <td colspan="7" class="vazio">
                    Nenhum cupom cadastrado.
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