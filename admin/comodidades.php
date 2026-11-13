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
      $icone = trim($_POST["icone"] ?? "");

      if ($nome === "") {
        throw new Exception("Informe o nome da comodidade.");
      }

      if ($id > 0) {
        $stmt = $pdo->prepare(
          "UPDATE comodidades SET nome = ?, icone = ? WHERE id = ?"
        );
        $stmt->execute([$nome, $icone ?: null, $id]);
      } else {
        $stmt = $pdo->prepare(
          "INSERT INTO comodidades (nome, icone) VALUES (?, ?)"
        );
        $stmt->execute([$nome, $icone ?: null]);
      }

      header("Location: comodidades.php?sucesso=salvo");
      exit;
    }

    if ($acao === "excluir") {
      $stmt = $pdo->prepare("DELETE FROM comodidades WHERE id = ?");
      $stmt->execute([(int) ($_POST["id"] ?? 0)]);

      header("Location: comodidades.php?sucesso=excluido");
      exit;
    }
  } catch (PDOException $e) {
    $erro = "Não foi possível concluir a operação. Verifique se essa comodidade já existe ou está vinculada a algum hotel.";
  } catch (Throwable $e) {
    $erro = $e->getMessage();
  }
}

if (isset($_GET["sucesso"])) {
  $mensagem = $_GET["sucesso"] === "excluido"
    ? "Comodidade excluída com sucesso!"
    : "Comodidade salva com sucesso!";
}

$editando = null;

if (isset($_GET["editar"])) {
  $stmt = $pdo->prepare("SELECT * FROM comodidades WHERE id = ?");
  $stmt->execute([(int) $_GET["editar"]]);
  $editando = $stmt->fetch();

  if (!$editando) {
    $erro = "Comodidade não encontrada.";
  }
}

$comodidades = $pdo->query(
  "SELECT * FROM comodidades ORDER BY nome"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Comodidades | Moovplus</title>
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
        <a href="comodidades.php" class="ativo">Comodidades</a>
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
          <h1>Comodidades</h1>
          <p>Cadastre os serviços e recursos oferecidos pelos hotéis.</p>
        </div>
      </header>

      <?php if ($mensagem): ?>
        <div class="mensagem"><?= h($mensagem) ?></div>
      <?php endif; ?>

      <?php if ($erro): ?>
        <div class="mensagem erro"><?= h($erro) ?></div>
      <?php endif; ?>

      <section class="card-form">
        <h2><?= $editando ? "Editar comodidade" : "Cadastrar comodidade" ?></h2>

        <form method="POST">
          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int) ($editando["id"] ?? 0) ?>">

          <div class="form-grid">

            <div class="campo">
              <label>Nome da comodidade *</label>
              <input type="text" name="nome" required
                value="<?= h($editando["nome"] ?? "") ?>"
                placeholder="Ex.: Wi-Fi gratuito">
            </div>

            <div class="campo">
              <label>Ícone</label>
              <input type="text" name="icone"
                value="<?= h($editando["icone"] ?? "") ?>"
                placeholder="Ex.: wifi">
            </div>

          </div>

          <div class="acoes-form">
            <button type="submit" class="botao">Salvar comodidade</button>

            <?php if ($editando): ?>
              <a href="comodidades.php" class="botao botao-secundario">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="card-tabela">
        <h2>Comodidades cadastradas (<?= count($comodidades) ?>)</h2>

        <div class="tabela-responsiva">
          <table>
            <thead>
              <tr>
                <th>Nome</th>
                <th>Ícone</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>

              <?php foreach ($comodidades as $c): ?>
                <tr>
                  <td><?= h($c["nome"]) ?></td>
                  <td><?= h($c["icone"] ?? "Não informado") ?></td>
                  <td>
                    <div class="acoes-tabela">
                      <a class="botao botao-secundario"
                        href="comodidades.php?editar=<?= (int) $c["id"] ?>">Editar</a>

                      <form method="POST" onsubmit="return confirm('Deseja excluir esta comodidade?')">
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id" value="<?= (int) $c["id"] ?>">
                        <button class="botao botao-excluir" type="submit">Excluir</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>

              <?php if (!$comodidades): ?>
                <tr>
                  <td colspan="3" class="vazio">Nenhuma comodidade cadastrada.</td>
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