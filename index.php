<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "marketing");
if ($conn->connect_error) die("Conexiune eșuată: " . $conn->connect_error);

$data = [];
$total = 0;
$success = 0;
$failures = 0;

$res = $conn->query("SELECT name, email, status, timestamp FROM logs ORDER BY timestamp DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $data[] = $row;
        $total++;
        if ($row['status'] === 'Success') $success++;
        if ($row['status'] === 'Failure') $failures++;
    }
}
$conn->close();
?>

<!doctype html>
<html class="no-js" lang="ro" dir="ltr">
<head>
  <meta charset="utf-8">
  <title>Dashboard Email Marketing</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/foundation.css">
  <link rel="stylesheet" href="css/app.css">
</head>
<body>
<div class="grid-container">
  <div class="grid-x grid-padding-x">
    <div class="cell small-3">
      <?php include("meniu.php"); ?>
    </div>

    <div class="cell small-9">
      <h1>📈 Dashboard Statistici Email Marketing</h1>

      <div class="grid-x grid-padding-x">
        <div class="cell medium-4">
          <div class="callout success text-center">
            <h4>📨 Emailuri Trimise</h4>
            <p><strong><?= $total ?></strong></p>
          </div>
        </div>
        <div class="cell medium-4">
          <div class="callout primary text-center">
            <h4>✅ Succes</h4>
            <p><strong><?= $success ?></strong></p>
          </div>
        </div>
        <div class="cell medium-4">
          <div class="callout alert text-center">
            <h4>❌ Eșecuri</h4>
            <p><strong><?= $failures ?></strong></p>
          </div>
        </div>
      </div>

      <?php if (!empty($data)): ?>
      <div class="grid-x grid-padding-x">
        <div class="cell">
          <h3>📋 Detalii Emailuri Trimise</h3>
          <table>
            <thead>
              <tr>
                <th>Nume</th>
                <th>Email</th>
                <th>Status</th>
                <th>Data</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($data as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['name']) ?></td>
                  <td><?= htmlspecialchars($row['email']) ?></td>
                  <td><?= htmlspecialchars($row['status']) ?></td>
                  <td><?= htmlspecialchars($row['timestamp']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php else: ?>
        <div class="callout alert text-center">
          <p>⚠️ Nu există înregistrări în tabela <code>logs</code>.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="js/vendor/jquery.js"></script>
<script src="js/vendor/what-input.js"></script>
<script src="js/vendor/foundation.js"></script>
<script src="js/app.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>
