
<?php
session_start();

require_once __DIR__ . "/config/conexao.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: cadastro.php");
    exit;
}

$nome = trim($_POST["nome"] ?? "");
$sobrenome = trim($_POST["sobrenome"] ?? "");
$email = trim($_POST["email"] ?? "");
$cpf = preg_replace("/\D/", "", $_POST["cpf"] ?? "");
$telefone = trim($_POST["telefone"] ?? "");
$dataNascimento = trim($_POST["data_nascimento"] ?? "");
$senha = $_POST["senha"] ?? "";
$confirmarSenha = $_POST["confirmar_senha"] ?? "";

if ($nome === "" || $sobrenome === "" || $email === "" || $senha === "") {
    $_SESSION["erro_cadastro"] = "Preencha todos os campos obrigatórios.";
    header("Location: cadastro.php");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION["erro_cadastro"] = "Digite um e-mail válido.";
    header("Location: cadastro.php");
    exit;
}

if (strlen($senha) < 8) {
    $_SESSION["erro_cadastro"] = "A senha deve ter pelo menos 8 caracteres.";
    header("Location: cadastro.php");
    exit;
}

if ($senha !== $confirmarSenha) {
    $_SESSION["erro_cadastro"] = "As senhas não são iguais.";
    header("Location: cadastro.php");
    exit;
}

if ($cpf !== "" && strlen($cpf) !== 11) {
    $_SESSION["erro_cadastro"] = "Digite um CPF válido.";
    header("Location: cadastro.php");
    exit;
}

try {
    $consulta = $pdo->prepare("
        SELECT id
        FROM usuarios
        WHERE email = :email
        LIMIT 1
    ");

    $consulta->execute([
        ":email" => $email
    ]);

    if ($consulta->fetch()) {
        $_SESSION["erro_cadastro"] = "Este e-mail já está cadastrado.";
        header("Location: cadastro.php");
        exit;
    }

    if ($cpf !== "") {
        $consulta = $pdo->prepare("
            SELECT id
            FROM usuarios
            WHERE cpf = :cpf
            LIMIT 1
        ");

        $consulta->execute([
            ":cpf" => $cpf
        ]);

        if ($consulta->fetch()) {
            $_SESSION["erro_cadastro"] = "Este CPF já está cadastrado.";
            header("Location: cadastro.php");
            exit;
        }
    }

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $inserir = $pdo->prepare("
        INSERT INTO usuarios (
            nome,
            sobrenome,
            email,
            senha,
            cpf,
            telefone,
            data_nascimento
        ) VALUES (
            :nome,
            :sobrenome,
            :email,
            :senha,
            :cpf,
            :telefone,
            :data_nascimento
        )
    ");

    $inserir->execute([
        ":nome" => $nome,
        ":sobrenome" => $sobrenome,
        ":email" => $email,
        ":senha" => $senhaHash,
        ":cpf" => $cpf !== "" ? $cpf : null,
        ":telefone" => $telefone !== "" ? $telefone : null,
        ":data_nascimento" => $dataNascimento !== "" ? $dataNascimento : null
    ]);

    $_SESSION["sucesso_cadastro"] = "Conta criada com sucesso! Faça login para continuar.";
    header("Location: login.php");
    exit;

} catch (PDOException $e) {
    $_SESSION["erro_cadastro"] = "Não foi possível realizar o cadastro. Tente novamente.";
    header("Location: cadastro.php");
    exit;
}
