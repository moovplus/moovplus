<?php

require_once "../config/conexao.php";

$nome = "Administrador";
$email = "admin@rotas.com";
$senha = "12345678";

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

$sql = "INSERT INTO administradores (nome, email, senha)
        VALUES (:nome, :email, :senha)";

$stmt = $pdo->prepare($sql);

$stmt->execute([
  "nome" => $nome,
  "email" => $email,
  "senha" => $senhaHash
]);

echo "Administrador criado com sucesso!";
