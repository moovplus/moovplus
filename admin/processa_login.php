<?php

session_start();

require_once "../config/conexao.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: login.php");
  exit;
}

$email = trim($_POST["email"] ?? "");
$senha = $_POST["senha"] ?? "";

if (empty($email) || empty($senha)) {
  $_SESSION["erro_admin"] = "Preencha todos os campos.";
  header("Location: login.php");
  exit;
}

$sql = "SELECT id, nome, email, senha, nivel, ativo
        FROM administradores
        WHERE email = :email
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(["email" => $email]);

$admin = $stmt->fetch();

if (!$admin || !password_verify($senha, $admin["senha"])) {
  $_SESSION["erro_admin"] = "E-mail ou senha incorretos.";
  header("Location: login.php");
  exit;
}

if (!$admin["ativo"]) {
  $_SESSION["erro_admin"] = "Este administrador está desativado.";
  header("Location: login.php");
  exit;
}

session_regenerate_id(true);

$_SESSION["administrador_id"] = $admin["id"];
$_SESSION["administrador_nome"] = $admin["nome"];
$_SESSION["administrador_email"] = $admin["email"];
$_SESSION["administrador_nivel"] = $admin["nivel"];

header("Location: index.php");
exit;
