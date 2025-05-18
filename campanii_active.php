<?php
session_start();
require 'vendor/autoload.php';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "marketing";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexiune eșuată: " . $conn->connect_error);
}

// Campanii active (ultimele 7 zile și activ = 1)
$sql = "
  SELECT c.id, c.nume_campanie, COUNT(l.id) AS total_trimise, MAX(l.timestamp) AS ultima_trimitere
  FROM campaigns c
  JOIN logs l ON l.email IN (
      SELECT email FROM contacts
  )
  WHERE l.timestamp >= NOW() - INTERVAL 7 DAY
    AND c.activ = 1
  GROUP BY c.id, c.nume_campanie
  ORDER BY ultima_trimitere DESC
";

$result = $conn->query($sql);
?>

<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Campanii Active</title>
  <link rel="stylesheet" href="css/foundation.css">
</head>
<body>

<div class="grid-container">
  <div class="grid-x grid-padding-x">
    <div class="cell small-3">
      <?php include("meniu.php"); ?>
    </div>

    <div class="cell small-9">
      <h2>📣 Campanii Active</h2>

      <?php if ($result && $result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Denumire Campanie</th>
              <th>Emailuri Trimise</th>
              <th>Ultima Trimitere</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['nume_campanie']) ?></td>
                <td><?= $row['total_trimise'] ?></td>
                <td><?= $row['ultima_trimitere'] ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>⚠️ Nu există campanii active în ultimele 7 zile.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>
