<?php
session_start();

if (isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

$erro = $_SESSION["erro_login"] ?? "";
$sucesso = $_SESSION["sucesso_cadastro"] ?? "";

unset($_SESSION["erro_login"], $_SESSION["sucesso_cadastro"]);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar | Moovplus</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
    <style>
    :root {
      --azul-escuro: #0B1F33;
      --azul: #0B5FFF;
      --azul-hover: #084dcc;
      --amarelo: #FFB703;
      --fundo: #F4F7FA;
      --borda: #DDE5EE;
      --texto-suave: #47596B;

      --login-fundo: #DDEBF8;
      --login-borda-campo: #C9D3DF;
      --login-texto-campo: #0B4F8A;
      --login-rodape: #F5FAFF;
    }

    * {
      box-sizing: border-box;
    }

    body.pagina-auth {
      margin: 0;
      min-height: 100vh;
      font-family: Inter, system-ui, sans-serif;
      background: var(--fundo);
      color: var(--azul-escuro);
    }

    .pagina-auth a {
      text-decoration: none;
    }

    .pagina-auth :focus-visible {
      outline: 3px solid var(--azul);
      outline-offset: 2px;
    }

    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }

    .auth-topo {
      height: 76px;
      padding: 0 7%;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #fff;
      border-bottom: 1px solid var(--borda);
    }

    .auth-topo .logo {
      font-family: "Space Grotesk", sans-serif;
      font-size: 28px;
      font-weight: 700;
      color: var(--azul-escuro);
    }

    .auth-topo .voltar {
      color: var(--texto-suave);
      font-size: 14px;
      font-weight: 600;
    }

    .auth-topo .voltar:hover {
      color: var(--azul);
    }

    .auth-container {
      min-height: calc(100vh - 76px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
    }

    .auth-card {
      position: relative;
      width: 100%;
      max-width: 420px;
      background: var(--login-fundo);
      border-radius: 14px;
      box-shadow: 0 18px 50px rgba(11, 31, 51, 0.18);
      overflow: hidden;
    }

    .auth-fechar {
      position: absolute;
      top: 14px;
      right: 14px;
      width: 30px;
      height: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--azul-escuro);
      border-radius: 8px;
    }

    .auth-fechar svg {
      width: 20px;
      height: 20px;
    }

    .auth-fechar:hover {
      background: rgba(11, 31, 51, 0.08);
    }

    .auth-conteudo {
      padding: 44px 32px 28px;
    }

    .auth-conteudo h1 {
      margin: 0 0 12px;
      font-family: "Space Grotesk", sans-serif;
      font-size: 26px;
      font-weight: 400;
      line-height: 1.2;
      text-align: center;
      color: var(--azul-escuro);
    }

    .auth-conteudo h1 strong {
      font-weight: 700;
    }

    .auth-descricao {
      margin: 0 auto 26px;
      max-width: 290px;
      text-align: center;
      color: var(--texto-suave);
      font-size: 14px;
      line-height: 1.5;
    }

    .auth-mensagem {
      padding: 13px 15px;
      border-radius: 9px;
      margin-bottom: 20px;
      font-size: 14px;
    }

    .auth-mensagem.erro {
      background: #fee2e2;
      border: 1px solid #fecaca;
      color: #991b1b;
    }

    .auth-mensagem.sucesso {
      background: #dcfce7;
      border: 1px solid #bbf7d0;
      color: #166534;
    }

    .auth-form {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .auth-input {
      height: 50px;
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 0 14px;
      background: #fff;
      border: 1px solid var(--login-borda-campo);
      border-radius: 10px;
      color: var(--login-texto-campo);
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .auth-input:focus-within {
      border-color: var(--azul);
      box-shadow: 0 0 0 3px rgba(11, 95, 255, 0.15);
    }

    .auth-input svg {
      width: 18px;
      height: 18px;
      flex-shrink: 0;
    }

    .auth-input input {
      width: 100%;
      height: 100%;
      border: 0;
      outline: 0;
      background: transparent;
      color: var(--azul-escuro);
      font: 400 15px Inter, sans-serif;
    }

    .auth-input input::placeholder {
      color: var(--login-texto-campo);
      opacity: 1;
    }

    .auth-botao {
      width: 100%;
      height: 46px;
      margin-top: 6px;
      border: 0;
      border-radius: 6px;
      background: var(--azul);
      color: #fff;
      font: 600 15px Inter, sans-serif;
      cursor: pointer;
      transition: background 0.2s;
    }

    .auth-botao:hover {
      background: var(--azul-hover);
    }

    .auth-esqueceu {
      align-self: center;
      margin-top: 2px;
      padding-bottom: 1px;
      border-bottom: 1px solid var(--azul-escuro);
      color: var(--azul-escuro);
      font-size: 13px;
    }

    .auth-esqueceu:hover {
      color: var(--azul);
      border-color: var(--azul);
    }

    .auth-rodape {
      display: flex;
      justify-content: center;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      padding: 14px 20px;
      background: var(--login-rodape);
      color: var(--azul-escuro);
      font-size: 14px;
    }

    .auth-rodape a {
      padding: 8px 16px;
      border: 1px solid var(--azul-escuro);
      border-radius: 8px;
      background: #fff;
      color: var(--azul-escuro);
      font-size: 14px;
    }

    .auth-rodape a:hover {
      background: var(--azul-escuro);
      color: #fff;
    }

    @media (max-width: 500px) {
      .auth-topo {
        padding: 0 20px;
      }

      .auth-container {
        padding: 24px 12px;
      }

      .auth-conteudo {
        padding: 44px 20px 24px;
      }

      .auth-conteudo h1 {
        font-size: 24px;
      }

      .auth-rodape {
        font-size: 13px;
      }

      .auth-rodape a {
        font-size: 13px;
      }
    }
    </style>
</head>
<body class="pagina-auth">

    <header class="auth-topo">
        <a href="index.php" class="logo">Moovplus</a>
        <a href="index.php" class="voltar">Voltar ao site</a>
    </header>

    <main class="auth-container">
        <section class="auth-card">

            <a href="index.php" class="auth-fechar" aria-label="Fechar e voltar ao site">
                <svg width="20" height="20" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                    <path d="M3 3l22 22M25 3L3 25"/>
                </svg>
            </a>

            <div class="auth-conteudo">
                <h1>Login <strong>Moovplus</strong></h1>
                <p class="auth-descricao">
                    Acesse sua conta e continue planejando sua próxima viagem.
                </p>

                <?php if ($erro): ?>
                    <div class="auth-mensagem erro" role="alert">
                        <?= htmlspecialchars($erro) ?>
                    </div>
                <?php endif; ?>

                <?php if ($sucesso): ?>
                    <div class="auth-mensagem sucesso" role="status">
                        <?= htmlspecialchars($sucesso) ?>
                    </div>
                <?php endif; ?>

                <form action="processa_login.php" method="POST" class="auth-form">
                    <div class="auth-input">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/>
                            <circle cx="12" cy="9.5" r="3.2" fill="currentColor" stroke="none"/>
                            <path d="M5.8 18.5c1.4-3 3.6-4.2 6.2-4.2s4.8 1.2 6.2 4.2" fill="currentColor" stroke="none"/>
                        </svg>
                        <label for="email" class="sr-only">E-mail</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="E-mail"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="auth-input">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M7 10V7.5a5 5 0 0 1 10 0V10h.5A1.5 1.5 0 0 1 19 11.5v8a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 5 19.5v-8A1.5 1.5 0 0 1 6.5 10H7Zm2 0h6V7.5a3 3 0 0 0-6 0V10Zm3 3.5a1.6 1.6 0 0 0-.8 3v1.5h1.6v-1.5a1.6 1.6 0 0 0-.8-3Z"/>
                        </svg>
                        <label for="senha" class="sr-only">Senha</label>
                        <input
                            type="password"
                            id="senha"
                            name="senha"
                            placeholder="Senha"
                            autocomplete="current-password"
                            required
                        >
                    </div>

                    <button type="submit" class="auth-botao">Entrar</button>

                    <a href="recuperar_senha.php" class="auth-esqueceu">Esqueci minha senha</a>
                </form>
            </div>

            <div class="auth-rodape">
                <span>Ainda não possui conta?</span>
                <a href="cadastro.php">Fazer cadastro</a>
            </div>
        </section>
    </main>

</body>
</html>