
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

    <title>Área administrativa | Moovplus</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/auth.css">
</head>

<body class="pagina-auth">

    <header class="auth-topo">

        <a href="../index.php" class="logo">Moovplus</a>

        <a href="../index.php" class="voltar">
            Voltar ao site
        </a>

    </header>

    <main class="auth-container">

        <section class="auth-card">

            <a href="../index.php"
               class="auth-fechar"
               aria-label="Fechar e voltar ao site">

                <svg viewBox="0 0 28 28"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.6"
                     stroke-linecap="round"
                     aria-hidden="true">

                    <path d="M3 3l22 22M25 3L3 25"/>

                </svg>

            </a>

            <div class="auth-conteudo">

                <h1>Área <strong>administrativa</strong></h1>

                <p class="auth-descricao">
                    Acesso restrito aos administradores Moovplus.
                </p>

                <?php if ($erro): ?>

                    <div class="auth-mensagem erro" role="alert">
                        <?= htmlspecialchars($erro) ?>
                    </div>

                <?php endif; ?>

                <form action="processa_login.php"
                      method="POST"
                      class="auth-form">

                    <div class="auth-input">

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.8"
                             aria-hidden="true">

                            <circle cx="12" cy="12" r="10"/>
                            <circle cx="12" cy="9.5" r="3.2"
                                    fill="currentColor"
                                    stroke="none"/>

                            <path d="M5.8 18.5c1.4-3 3.6-4.2 6.2-4.2s4.8 1.2 6.2 4.2"
                                  fill="currentColor"
                                  stroke="none"/>

                        </svg>

                        <label for="email" class="sr-only">
                            E-mail administrativo
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="E-mail administrativo"
                            autocomplete="username"
                            required
                        >

                    </div>

                    <div class="auth-input">

                        <svg viewBox="0 0 24 24"
                             fill="currentColor"
                             aria-hidden="true">

                            <path d="M7 10V7.5a5 5 0 0 1 10 0V10h.5A1.5 1.5 0 0 1 19 11.5v8a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 5 19.5v-8A1.5 1.5 0 0 1 6.5 10H7Zm2 0h6V7.5a3 3 0 0 0-6 0V10Zm3 3.5a1.6 1.6 0 0 0-.8 3v1.5h1.6v-1.5a1.6 1.6 0 0 0-.8-3Z"/>

                        </svg>

                        <label for="senha" class="sr-only">
                            Senha administrativa
                        </label>

                        <input
                            type="password"
                            id="senha"
                            name="senha"
                            placeholder="Senha"
                            autocomplete="current-password"
                            required
                        >

                    </div>

                    <button type="submit" class="auth-botao">
                        Acessar painel
                    </button>

                </form>

            </div>

            <div class="auth-rodape">

                <span>Área exclusiva Moovplus</span>

                <a href="../index.php">
                    Voltar ao site
                </a>

            </div>

        </section>

    </main>

</body>

</html>
