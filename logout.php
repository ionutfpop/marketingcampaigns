<?php
session_start();
session_unset();      // Șterge toate variabilele de sesiune
session_destroy();    // Închide sesiunea
header("Location: login.php"); // Redirecționează către pagina de logare
exit;
?>
