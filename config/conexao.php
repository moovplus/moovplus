
<?php
$host = "localhost";
$banco = "moovplus";
$usuario = "root";
$senha = "1234";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=3308;dbname=$banco;charset=utf8mb4",
        $usuario,
        $senha
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao conectar com o banco de dados.");
}
