
<?php
session_start();

require_once __DIR__ . "/config/conexao.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: cadastro.php");
    exit;
}

function cpfValido($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);

    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $soma = 0;

        for ($i = 0; $i < $t; $i++) {
            $soma += (int)$cpf[$i] * (($t + 1) - $i);
        }

        $digito = ($soma * 10) % 11;

        if ($digito === 10) {
            $digito = 0;
        }

        if ($digito !== (int)$cpf[$t]) {
            return false;
        }
    }

    return true;
}

$nome = trim($_POST["nome"] ?? "");
$sobrenome = trim($_POST["sobrenome"] ?? "");
$email = trim($_POST["email"] ?? "");
$cpf = preg_replace("/\D/", "", $_POST["cpf"] ?? "");
$telefone = trim($_POST["telefone"] ?? "");
$dataNascimento = trim($_POST["data_nascimento"] ?? "");
$senha = $_POST["senha"] ?? "";
$confirmarSenha = $_POST["confirmar_senha"] ?? "";

if (
    $nome === "" ||
    $sobrenome === "" ||
    $email === "" ||
    $senha === "" ||
    $confirmarSenha === "" ||
    $cpf === "" ||
    $dataNascimento === ""
) {
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

if (!cpfValido($cpf)) {
    $_SESSION["erro_cadastro"] = "CPF inválido. Verifique os números digitados.";
    header("Location: cadastro.php");
    exit;
}

$data = DateTime::createFromFormat("!Y-m-d", $dataNascimento);
$hoje = new DateTime("today");

if (
    !$data ||
    $data->format("Y-m-d") !== $dataNascimento ||
    $data >= $hoje
) {
    $_SESSION["erro_cadastro"] = "Data de nascimento inválida. Verifique a data digitada.";
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
        ":cpf" => $cpf,
        ":telefone" => $telefone !== "" ? $telefone : null,
        ":data_nascimento" => $dataNascimento
    ]);

    $_SESSION["sucesso_cadastro"] = "Conta criada com sucesso! Faça login para continuar.";
    header("Location: login.php");
    exit;

} catch (PDOException $e) {
    $_SESSION["erro_cadastro"] = "Não foi possível realizar o cadastro. Tente novamente.";
    header("Location: cadastro.php");
    exit;
}
