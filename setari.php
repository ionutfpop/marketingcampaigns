<?php
$message = "";

$conn = new mysqli("localhost", "root", "", "marketing");
if ($conn->connect_error) die("Eroare DB: " . $conn->connect_error);

// Obține campaniile existente din tabela templates
$campaigns = [];
$campRes = $conn->query("SELECT denumire FROM templates ORDER BY denumire ASC");
if ($campRes) {
    while ($row = $campRes->fetch_assoc()) {
        $campaigns[] = $row['denumire'];
    }
}

// Adăugare contact manual
if (isset($_POST['add_contact'])) {
    $nume = $conn->real_escape_string($_POST['nume']);
    $prenume = $conn->real_escape_string($_POST['prenume']);
    $email = $conn->real_escape_string($_POST['email']);
    $template = $conn->real_escape_string($_POST['template_name']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "❌ Email invalid.";
    } else {
        $res = $conn->query("SELECT id FROM contacts WHERE email = '$email'");
        if ($res->num_rows > 0) {
            $message = "⚠️ Acest email există deja.";
        } else {
            $conn->query("INSERT INTO contacts (nume, prenume, email, template_name) VALUES ('$nume', '$prenume', '$email', '$template')");
            $message = "✅ Contact adăugat cu succes.";
        }
    }
}

// Salvare setări SMTP
if (isset($_POST['save_smtp'])) {
    $smtp_config = [
        "server" => $_POST['smtp_server'],
        "port" => $_POST['smtp_port'],
        "user" => $_POST['smtp_user'],
        "pass" => $_POST['smtp_pass'],
    ];
    file_put_contents("config.json", json_encode($smtp_config, JSON_PRETTY_PRINT));
    $message = "✅ Setările SMTP au fost salvate.";
}

// Resetare date
if (isset($_POST['reset_logs'])) {
    $conn->query("DELETE FROM logs");
    $message = "🗑️ Toate logurile au fost șterse.";
}
if (isset($_POST['reset_contacts'])) {
    $conn->query("DELETE FROM contacts");
    $message = "🗑️ Toți contactele au fost șterse.";
}

// Creare utilizator nou (admin)
if (isset($_POST['create_user'])) {
    $email = $conn->real_escape_string($_POST['new_user_email']);
    $nume = $conn->real_escape_string($_POST['new_user_name']);
    $parola = password_hash($_POST['new_user_pass'], PASSWORD_DEFAULT);

    $res = $conn->query("SELECT id FROM utilizatori WHERE email = '$email'");
    if ($res->num_rows > 0) {
        $message = "⚠️ Utilizatorul există deja.";
    } else {
        $conn->query("INSERT INTO utilizatori (email, parola, nume) VALUES ('$email', '$parola', '$nume')");
        $message = "✅ Utilizator creat: $email";
    }
}

$conn->close();
?>

<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Setări</title>
  <link rel="stylesheet" href="css/foundation.css">
</head>
<body>
<div class="grid-container">
  <div class="grid-x grid-padding-x">
    <div class="cell small-3">
      <?php include("meniu.php"); ?>
    </div>
    <div class="cell small-9">
      <h2>⚙️ Setări</h2>

      <?php if ($message): ?>
        <div class="callout primary"><?= $message ?></div>
      <?php endif; ?>

      <h4>➕ Adaugă contact manual</h4>
      <form method="post">
        <label>Nume:
          <input type="text" name="nume" required>
        </label>
        <label>Prenume:
          <input type="text" name="prenume" required>
        </label>
        <label>Email:
          <input type="email" name="email" required>
        </label>
        <label>Campanie (template_name):
          <select name="template_name" required>
            <option value="">-- Selectează --</option>
            <?php foreach ($campaigns as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit" name="add_contact" class="button">💾 Salvează Contact</button>
      </form>

      <hr>

      <h4>✉️ Configurare SMTP implicit</h4>
      <form method="post">
        <label>Server SMTP:
          <input type="text" name="smtp_server" required>
        </label>
        <label>Port:
          <input type="number" name="smtp_port" value="587" required>
        </label>
        <label>Utilizator:
          <input type="text" name="smtp_user" required>
        </label>
        <label>Parolă:
          <input type="password" name="smtp_pass" required>
        </label>
        <button type="submit" name="save_smtp" class="button success">💾 Salvează Setări</button>
      </form>

      <hr>

      <h4>👤 Creare utilizator nou</h4>
      <form method="post">
        <label>Nume complet:
          <input type="text" name="new_user_name" required>
        </label>
        <label>Email:
          <input type="email" name="new_user_email" required>
        </label>
        <label>Parolă:
          <input type="password" name="new_user_pass" required>
        </label>
        <button type="submit" name="create_user" class="button">➕ Creează utilizator</button>
      </form>

      <hr>

      <h4>🗑️ Resetare date (cu atenție)</h4>
      <form method="post" onsubmit="return confirm('Ești sigur că vrei să ștergi aceste date?');">
        <button type="submit" name="reset_logs" class="button alert">Șterge toate logurile</button>
        <button type="submit" name="reset_contacts" class="button alert">Șterge toate contactele</button>
      </form>
    </div>
  </div>
</div>  
</body>
</html>
