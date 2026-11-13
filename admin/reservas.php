<?php

require_once "../config/conexao.php";
require_once "proteger.php";

function h($valor)
{
  return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

$erro = "";
$mensagem = "";

$statusPermitidos = [
  "pendente",
  "aguardando_pagamento",
  "confirmada",
  "cancelada",
  "reembolsada"
];

$nomesStatus = [
  "pendente" => "Pendente",
  "aguardando_pagamento" => "Aguardando pagamento",
  "confirmada" => "Confirmada",
  "cancelada" => "Cancelada",
  "reembolsada" => "Reembolsada"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $acao = $_POST["acao"] ?? "";

  try {
    if ($acao === "salvar") {
      $id = (int) ($_POST["id"] ?? 0);
      $status = $_POST["status"] ?? "";

      if ($id <= 0) {
        throw new Exception("Reserva inválida.");
      }

      if (!in_array($status, $statusPermitidos, true)) {
        throw new Exception("Status inválido.");
      }

      $stmt = $pdo->prepare("
                UPDATE reservas
                SET status = ?, atualizado_em = NOW()
                WHERE id = ?
            ");

      $stmt->execute([$status, $id]);

      header("Location: reservas.php?sucesso=salvo");
      exit;
    }
  } catch (PDOException $e) {
    $erro = "Não foi possível atualizar a reserva.";
  } catch (Throwable $e) {
    $erro = $e->getMessage();
  }
}

if (isset($_GET["sucesso"])) {
  $mensagem = "Status da reserva atualizado com sucesso!";
}

$filtro = $_GET["status"] ?? "";
$busca = trim($_GET["busca"] ?? "");

$sql = "
    SELECT
        r.*,
        u.nome AS usuario_nome,
        u.sobrenome AS usuario_sobrenome,
        u.email AS usuario_email,
        p.nome AS pacote_nome
    FROM reservas r
    LEFT JOIN usuarios u ON u.id = r.usuario_id
    LEFT JOIN pacotes p ON p.id = r.pacote_id
    WHERE 1 = 1
";

$params = [];

if ($filtro !== "") {
  if (!in_array($filtro, $statusPermitidos, true)) {
    $erro = "Filtro de status inválido.";
  } else {
    $sql .= " AND r.status = ?";
    $params[] = $filtro;
  }
}

if ($busca !== "") {
  $sql .= " AND (
        r.codigo LIKE ?
        OR u.nome LIKE ?
        OR u.sobrenome LIKE ?
        OR u.email LIKE ?
    )";

  $termo = "%$busca%";

  $params[] = $termo;
  $params[] = $termo;
  $params[] = $termo;
  $params[] = $termo;
}

$sql .= " ORDER BY r.data_criacao DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservas = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reservas | Moovplus</title>
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
        <a href="cupons.php">Cupons</a>
        <a href="reservas.php" class="ativo">Reservas</a>
        <a href="usuarios.php">Usuários</a>
        <a href="pagamentos.php">Pagamentos</a>
      </nav>

      <a href="sair.php" class="sair">Sair do painel</a>
    </aside>

    <main class="conteudo">

      <header class="topo">
        <div>
          <h1>Reservas</h1>
          <p>Consulte e gerencie as reservas dos clientes.</p>
        </div>
      </header>

      <?php if ($mensagem): ?>
        <div class="mensagem"><?= h($mensagem) ?></div>
      <?php endif; ?>

      <?php if ($erro): ?>
        <div class="mensagem erro"><?= h($erro) ?></div>
      <?php endif; ?>

      <section class="card-tabela">

        <h2>Reservas cadastradas (<?= count($reservas) ?>)</h2>

        <form method="GET" class="form-grid">

          <div class="campo">
            <label>Pesquisar reserva</label>
            <input
              type="text"
              name="busca"
              placeholder="Código, nome ou e-mail"
              value="<?= h($busca) ?>">
          </div>

          <div class="campo">
            <label>Status</label>
            <select name="status">
              <option value="">Todos os status</option>

              <?php foreach ($statusPermitidos as $status): ?>
                <option
                  value="<?= h($status) ?>"
                  <?= $filtro === $status ? "selected" : "" ?>>
                  <?= h($nomesStatus[$status]) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="acoes-form">
            <button type="submit" class="botao">Pesquisar</button>
            <a href="reservas.php" class="botao botao-secundario">Limpar</a>
          </div>

        </form>

        <div class="tabela-responsiva">
          <table>

            <thead>
              <tr>
                <th>Código</th>
                <th>Cliente</th>
                <th>Tipo</th>
                <th>Pacote</th>
                <th>Valor total</th>
                <th>Data</th>
                <th>Status</th>
                <th>Ações</th>
              </tr>
            </thead>

            <tbody>

              <?php foreach ($reservas as $r): ?>
                <tr>

                  <td><?= h($r["codigo"]) ?></td>

                  <td>
                    <?= h(trim(
                      ($r["usuario_nome"] ?? "") . " " .
                        ($r["usuario_sobrenome"] ?? "")
                    )) ?>
                    <br>
                    <small><?= h($r["usuario_email"] ?? "") ?></small>
                  </td>

                  <td><?= h(ucfirst($r["tipo"])) ?></td>

                  <td><?= h($r["pacote_nome"] ?? "Não se aplica") ?></td>

                  <td>
                    R$ <?= number_format(
                          (float) $r["valor_total"],
                          2,
                          ",",
                          "."
                        ) ?>
                  </td>

                  <td>
                    <?= date("d/m/Y H:i", strtotime($r["data_criacao"])) ?>
                  </td>

                  <td>
                    <?= h($nomesStatus[$r["status"]] ?? $r["status"]) ?>
                  </td>

                  <td>
                    <div class="acoes-tabela">

                      <form method="POST">

                        <input type="hidden" name="acao" value="salvar">
                        <input type="hidden" name="id" value="<?= (int) $r["id"] ?>">

                        <select name="status">
                          <?php foreach ($statusPermitidos as $status): ?>
                            <option
                              value="<?= h($status) ?>"
                              <?= $r["status"] === $status ? "selected" : "" ?>>
                              <?= h($nomesStatus[$status]) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>

                        <button type="submit" class="botao">
                          Salvar
                        </button>

                      </form>

                    </div>
                  </td>

                </tr>
              <?php endforeach; ?>

              <?php if (!$reservas): ?>
                <tr>
                  <td colspan="8" class="vazio">
                    Nenhuma reserva cadastrada.
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