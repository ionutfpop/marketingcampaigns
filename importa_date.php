<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "marketing";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexiune eșuată: " . $conn->connect_error);
}

$message = "";

if (isset($_POST["import"]) && isset($_FILES["csv_file"])) {
    $file = $_FILES["csv_file"]["tmp_name"];
    $importate = 0;
    $ignorate = 0;

    if (($handle = fopen($file, "r")) !== false) {
        $header = fgetcsv($handle, 1000, ",");

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            if (count($data) >= 4) {
                $nume = $conn->real_escape_string($data[0]);
                $prenume = $conn->real_escape_string($data[1]);
                $email = $conn->real_escape_string($data[2]);
                $template_name = $conn->real_escape_string($data[3]);

                // Validare email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $ignorate++;
                    continue;
                }

                // Verificare duplicat
                $check = $conn->query("SELECT id FROM contacts WHERE email = '$email'");
                if ($check->num_rows > 0) {
                    $ignorate++;
                    continue;
                }

                $conn->query("INSERT INTO contacts (nume, prenume, email, template_name)
                              VALUES ('$nume', '$prenume', '$email', '$template_name')");
                $importate++;
            }
        }

        fclose($handle);
        $message = "✅ $importate contacte importate. ❌ $ignorate ignorate (email invalid sau duplicat).";
    } else {
        $message = "❌ Eroare la citirea fișierului.";
    }
}
?>


<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Importă Contacte</title>
  <link rel="stylesheet" href="css/foundation.css">
</head>
<body>

<div class="grid-container">
  <div class="grid-x grid-padding-x">
    <div class="cell small-3">
      <?php include("meniu.php"); ?>
    </div>

    <div class="cell small-9">
      <h2>📥 Importă Contacte CSV</h2>

      <?php if ($message): ?>
        <div class="callout primary"><?= $message ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <label>Alege fișierul CSV:</label>
        <input type="file" name="csv_file" accept=".csv" required>
        <p class="help-text">Coloane CSV: <strong>nume, prenume, email, template_name</strong></p>
        <button type="submit" name="import" class="button success">📤 Importă</button>
      </form>
    </div>
  </div>
</div>

</body>
</html>
