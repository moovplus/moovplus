<?php
session_start();

if (isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

$erro = $_SESSION["erro_login"] ?? "";
unset($_SESSION["erro_login"]);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar | Rotas</title>

    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>

    <header>
        <a href="index.php" class="logo">Rotas</a>

        <a href="index.php" class="voltar">
            Voltar ao site
        </a>
    </header>

    <main class="container-login">

        <div class="card-login">

            <h1>Bem-vindo de volta!</h1>

            <p class="descricao">
                Entre na sua conta para continuar.
            </p>

            <?php if ($erro): ?>
                <div class="erro">
                    <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>

            <form action="processa_login.php" method="POST">

                <div class="grupo">
                    <label for="email">E-mail</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Digite seu e-mail"
                        required
                    >
                </div>

                <div class="grupo">
                    <label for="senha">Senha</label>

                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        placeholder="Digite sua senha"
                        required
                    >
                </div>

                <a href="recuperar_senha.php" class="esqueceu">
                    Esqueceu sua senha?
                </a>

                <button type="submit" class="botao">
                    Entrar
                </button>

            </form>

            <div class="rodape-login">
                Ainda não tem conta?
                <a href="cadastro.php">Cadastre-se</a>
            </div>

        </div>

    </main>

</body>
</html>