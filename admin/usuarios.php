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
      $sobrenome = trim($_POST["sobrenome"] ?? "");
      $email = trim($_POST["email"] ?? "");
      $cpf = preg_replace('/\D/', '', $_POST["cpf"] ?? "");
      $telefone = trim($_POST["telefone"] ?? "");
      $dataNascimento = trim($_POST["data_nascimento"] ?? "");
      $senha = $_POST["senha"] ?? "";
      $ativo = isset($_POST["ativo"]) ? 1 : 0;

      if ($nome === "" || $sobrenome === "" || $email === "") {
        throw new Exception("Preencha nome, sobrenome e e-mail.");
      }

      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Informe um e-mail válido.");
      }

      if ($cpf !== "" && strlen($cpf) !== 11) {
        throw new Exception("O CPF deve conter 11 números.");
      }

      if ($dataNascimento !== "") {
        $data = DateTime::createFromFormat("Y-m-d", $dataNascimento);

        if (!$data || $data->format("Y-m-d") !== $dataNascimento) {
          throw new Exception("Informe uma data de nascimento válida.");
        }
      } else {
        $dataNascimento = null;
      }

      if ($id > 0) {
        if ($senha !== "" && strlen($senha) < 8) {
          throw new Exception("A senha deve ter pelo menos 8 caracteres.");
        }

        if ($senha !== "") {
          $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

          $stmt = $pdo->prepare("
                        UPDATE usuarios
                        SET nome = ?,
                            sobrenome = ?,
                            email = ?,
                            cpf = ?,
                            telefone = ?,
                            data_nascimento = ?,
                            senha = ?,
                            ativo = ?,
                            atualizado_em = NOW()
                        WHERE id = ?
                    ");

          $stmt->execute([
            $nome,
            $sobrenome,
            $email,
            $cpf !== "" ? $cpf : null,
            $telefone,
            $dataNascimento,
            $senhaHash,
            $ativo,
            $id
          ]);
        } else {
          $stmt = $pdo->prepare("
                        UPDATE usuarios
                        SET nome = ?,
                            sobrenome = ?,
                            email = ?,
                            cpf = ?,
                            telefone = ?,
                            data_nascimento = ?,
                            ativo = ?,
                            atualizado_em = NOW()
                        WHERE id = ?
                    ");

          $stmt->execute([
            $nome,
            $sobrenome,
            $email,
            $cpf !== "" ? $cpf : null,
            $telefone,
            $dataNascimento,
            $ativo,
            $id
          ]);
        }
      } else {
        if (strlen($senha) < 8) {
          throw new Exception("A senha deve ter pelo menos 8 caracteres.");
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
                    INSERT INTO usuarios (
                        nome,
                        sobrenome,
                        email,
                        senha,
                        cpf,
                        telefone,
                        data_nascimento,
                        ativo
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

        $stmt->execute([
          $nome,
          $sobrenome,
          $email,
          $senhaHash,
          $cpf !== "" ? $cpf : null,
          $telefone,
          $dataNascimento,
          $ativo
        ]);
      }

      header("Location: usuarios.php?sucesso=salvo");
      exit;
    }
  } catch (PDOException $e) {
    if ($e->getCode() === "23000") {
      $erro = "Este e-mail ou CPF já está cadastrado.";
    } else {
      $erro = "Não foi possível salvar o usuário.";
    }
  } catch (Throwable $e) {
    $erro = $e->getMessage();
  }
}

if (isset($_GET["sucesso"])) {
  $mensagem = "Usuário salvo com sucesso!";
}

$editando = null;

if (isset($_GET["editar"])) {
  $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
  $stmt->execute([(int) $_GET["editar"]]);
  $editando = $stmt->fetch();

  if (!$editando) {
    $erro = "Usuário não encontrado.";
  }
}

$stmt = $pdo->query("
    SELECT *
    FROM usuarios
    ORDER BY criado_em DESC
");

$usuarios = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Usuários | Moovplus</title>
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
        <a href="reservas.php">Reservas</a>
        <a href="usuarios.php" class="ativo">Usuários</a>
        <a href="pagamentos.php">Pagamentos</a>
      </nav>

      <a href="sair.php" class="sair">Sair do painel</a>
    </aside>

    <main class="conteudo">

      <header class="topo">
        <div>
          <h1>Usuários</h1>
          <p>Gerencie os usuários cadastrados na plataforma.</p>
        </div>
      </header>

      <?php if ($mensagem): ?>
        <div class="mensagem"><?= h($mensagem) ?></div>
      <?php endif; ?>

      <?php if ($erro): ?>
        <div class="mensagem erro"><?= h($erro) ?></div>
      <?php endif; ?>

      <section class="card-form">

        <h2><?= $editando ? "Editar usuário" : "Cadastrar usuário" ?></h2>

        <form method="POST">

          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int) ($editando["id"] ?? 0) ?>">

          <div class="form-grid">

            <div class="campo">
              <label>Nome</label>
              <input
                type="text"
                name="nome"
                required
                value="<?= h($editando["nome"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>Sobrenome</label>
              <input
                type="text"
                name="sobrenome"
                required
                value="<?= h($editando["sobrenome"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>E-mail</label>
              <input
                type="email"
                name="email"
                required
                value="<?= h($editando["email"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>CPF</label>
              <input
                type="text"
                name="cpf"
                maxlength="14"
                value="<?= h($editando["cpf"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>Telefone</label>
              <input
                type="text"
                name="telefone"
                value="<?= h($editando["telefone"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>Data de nascimento</label>
              <input
                type="date"
                name="data_nascimento"
                value="<?= h($editando["data_nascimento"] ?? "") ?>">
            </div>

            <div class="campo">
              <label>
                Senha
                <?= $editando ? "(deixe em branco para manter a atual)" : "" ?>
              </label>
              <input
                type="password"
                name="senha"
                <?= $editando ? "" : "required" ?>
                minlength="8">
            </div>

            <div class="campo-checkbox">
              <input
                type="checkbox"
                name="ativo"
                id="ativo"
                <?= !isset($editando["ativo"]) || $editando["ativo"] ? "checked" : "" ?>>
              <label for="ativo">Usuário ativo</label>
            </div>

          </div>

          <div class="acoes-form">

            <button type="submit" class="botao">
              Salvar usuário
            </button>

            <?php if ($editando): ?>
              <a href="usuarios.php" class="botao botao-secundario">
                Cancelar
              </a>
            <?php endif; ?>

          </div>

        </form>

      </section>

      <section class="card-tabela">

        <h2>Usuários cadastrados (<?= count($usuarios) ?>)</h2>

        <div class="tabela-responsiva">

          <table>

            <thead>
              <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>CPF</th>
                <th>Telefone</th>
                <th>Status</th>
                <th>Cadastro</th>
                <th>Ações</th>
              </tr>
            </thead>

            <tbody>

              <?php foreach ($usuarios as $u): ?>

                <tr>

                  <td>
                    <?= h($u["nome"] . " " . $u["sobrenome"]) ?>
                  </td>

                  <td><?= h($u["email"]) ?></td>

                  <td><?= h($u["cpf"] ?: "Não informado") ?></td>

                  <td><?= h($u["telefone"] ?: "Não informado") ?></td>

                  <td>
                    <?= $u["ativo"] ? "Ativo" : "Inativo" ?>
                  </td>

                  <td>
                    <?= date("d/m/Y", strtotime($u["criado_em"])) ?>
                  </td>

                  <td>
                    <div class="acoes-tabela">

                      <a
                        href="usuarios.php?editar=<?= (int) $u["id"] ?>"
                        class="botao botao-secundario">
                        Editar
                      </a>

                    </div>
                  </td>

                </tr>

              <?php endforeach; ?>

              <?php if (!$usuarios): ?>
                <tr>
                  <td colspan="7" class="vazio">
                    Nenhum usuário cadastrado.
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