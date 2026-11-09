<?php

session_start();

require_once "config/conexao.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$email = trim($_POST["email"] ?? "");
$senha = $_POST["senha"] ?? "";

if (empty($email) || empty($senha)) {
    $_SESSION["erro_login"] = "Preencha todos os campos.";
    header("Location: login.php");
    exit;
}

$sql = "SELECT id, nome, sobrenome, email, senha, ativo
        FROM usuarios
        WHERE email = :email
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(["email" => $email]);

$usuario = $stmt->fetch();

if (!$usuario || !password_verify($senha, $usuario["senha"])) {
    $_SESSION["erro_login"] = "E-mail ou senha incorretos.";
    header("Location: login.php");
    exit;
}

if (!$usuario["ativo"]) {
    $_SESSION["erro_login"] = "Sua conta está desativada.";
    header("Location: login.php");
    exit;
}

session_regenerate_id(true);

$_SESSION["usuario_id"] = $usuario["id"];
$_SESSION["usuario_nome"] = $usuario["nome"];
$_SESSION["usuario_email"] = $usuario["email"];

header("Location: index.php");
exit;