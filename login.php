<?php
session_start();
$message = "";

// Conectare la baza de date
$conn = new mysqli("localhost", "root", "", "marketing");
if ($conn->connect_error) {
    die("Eroare DB: " . $conn->connect_error);
}

// Verificare autentificare
if (isset($_POST['login'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $parola = $_POST['parola'];

    $res = $conn->query("SELECT id, parola, nume FROM utilizatori WHERE email = '$email'");
    if ($res && $res->num_rows === 1) {
        $user = $res->fetch_assoc();
        if (password_verify($parola, $user['parola'])) {
            $_SESSION['user'] = $user['nume'] ?? $email;
            header("Location: index.php");
            exit;
        } else {
            $message = "❌ Parolă incorectă.";
        }
    } else {
        $message = "❌ Email inexistent.";
    }
}
$conn->close();
?>

<!doctype html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title>Autentificare</title>
  <link rel="stylesheet" href="css/foundation.css">
  <style>
    body { background-color: #f3f3f3; }
    .login-box { max-width: 400px; margin: 5rem auto; padding: 2rem; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
  </style>
</head>
<body>
<div class="login-box">
  <h3>🔐 Autentificare</h3>
  <?php if ($message): ?>
    <div class="callout alert"><?= $message ?></div>
  <?php endif; ?>
  <form method="post">
    <label>Email:
      <input type="email" name="email" required>
    </label>
    <label>Parolă:
      <input type="password" name="parola" required>
    </label>
    <button type="submit" name="login" class="button expanded">➡️ Conectează-te</button>
  </form>
</div>
</body>
</html>
