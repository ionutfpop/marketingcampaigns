<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "marketing";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexiune eșuată: " . $conn->connect_error);
}

// Funcție pentru export CSV
function exportCSV($filename, $data, $header) {
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $f = fopen('php://output', 'w');
    fputcsv($f, $header);
    foreach ($data as $row) {
        fputcsv($f, $row);
    }
    fclose($f);
    exit;
}

// Export campanii
if (isset($_GET['export']) && $_GET['export'] === 'campaigns') {
  $res = $conn->query("SELECT id, nume_campanie, subiect, data_trimitere, total_sent, data_creare FROM campaigns");
    $data = $res->fetch_all(MYSQLI_ASSOC);
    exportCSV("campanii.csv", $data, ["ID", "Campanie", "Subiect", "Trimis La", "Total Trimise", "Creat La"]);
}

// Export loguri
if (isset($_GET['export']) && $_GET['export'] === 'logs') {
    $res = $conn->query("SELECT id, name, email, status, timestamp FROM logs");
    $data = $res->fetch_all(MYSQLI_ASSOC);
    exportCSV("loguri_email.csv", $data, ["ID", "Nume", "Email", "Status", "Data/Ora"]);
}

// Export contacte
if (isset($_GET['export']) && $_GET['export'] === 'contacts') {
    $res = $conn->query("SELECT id, nume, prenume, email FROM contacts");
    $data = $res->fetch_all(MYSQLI_ASSOC);
    exportCSV("contacte.csv", $data, ["ID", "Nume", "Prenume", "Email"]);
}
?>

<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Exportă Date</title>
  <link rel="stylesheet" href="css/foundation.css">
</head>
<body>
<div class="grid-container">
  <div class="grid-x grid-padding-x">
    <div class="cell small-3">
      <?php include("meniu.php"); ?>
    </div>
    <div class="cell small-9">
      <h2>📤 Exportă Date</h2>
      <p>Apasă pe unul dintre butoanele de mai jos pentru a exporta fișierul CSV:</p>
      <div class="grid-x grid-padding-x">
        <div class="cell small-4">
          <a href="?export=campaigns" class="button">📄 Exportă Campanii</a>
        </div>
        <div class="cell small-4">
          <a href="?export=logs" class="button">📬 Exportă Loguri Email</a>
        </div>
        <div class="cell small-4">
          <a href="?export=contacts" class="button">👤 Exportă Contacte</a>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
