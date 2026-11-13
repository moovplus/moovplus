<?php

session_start();

if (isset($_SESSION["administrador_id"])) {
  header("Location: index.php");
  exit;
}

$erro = $_SESSION["erro_admin"] ?? "";
unset($_SESSION["erro_admin"]);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Área administrativa | Rotas</title>

  <link rel="stylesheet" href="../assets/css/auth.css">
</head>

<body>

  <header>
    <a href="../index.php" class="logo">Rotas</a>

    <a href="../index.php" class="voltar">
      Voltar ao site
    </a>
  </header>

  <main class="container-login">

    <div class="card-login">

      <div class="icone-admin">🛡️</div>

      <h1>Área administrativa</h1>

      <p class="descricao">
        Acesso restrito aos administradores.
      </p>

      <?php if ($erro): ?>
        <div class="erro">
          <?= htmlspecialchars($erro) ?>
        </div>
      <?php endif; ?>

      <form action="processa_login.php" method="POST">

        <div class="grupo">
          <label for="email">E-mail administrativo</label>

          <input
            type="email"
            id="email"
            name="email"
            placeholder="Digite seu e-mail"
            required>
        </div>

        <div class="grupo">
          <label for="senha">Senha</label>

          <input
            type="password"
            id="senha"
            name="senha"
            placeholder="Digite sua senha"
            required>
        </div>

        <button type="submit" class="botao">
          Acessar painel
        </button>

      </form>

    </div>

  </main>

</body>

</html>