<?php

session_start();

unset(
    $_SESSION["administrador_id"],
    $_SESSION["administrador_nome"],
    $_SESSION["administrador_email"],
    $_SESSION["administrador_nivel"]
);

header("Location: login.php");
exit;