<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "marketing";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Eroare la conectare: " . $conn->connect_error);
}

// Date pentru bara: număr emailuri/zi (ultimele 7 zile) >= NOW() - INTERVAL 7 DAY
$bar_labels = [];
$bar_data = [];

$bar_sql = "
  SELECT DATE(timestamp) as ziua, COUNT(*) as total
  FROM logs
  WHERE timestamp 
  GROUP BY ziua
  ORDER BY ziua ASC
";
$result = $conn->query($bar_sql);
while ($row = $result->fetch_assoc()) {
    $bar_labels[] = $row['ziua'];
    $bar_data[] = (int)$row['total'];
}

// Date pentru pie chart: success vs failure
$success = 0;
$failure = 0;
$pie_sql = "
  SELECT status, COUNT(*) as total
  FROM logs
  GROUP BY status
";
$result = $conn->query($pie_sql);
while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'Success') $success = $row['total'];
    if ($row['status'] === 'Failure') $failure = $row['total'];
}

$conn->close();
?>

<!doctype html>
<html class="no-js" lang="ro" dir="ltr">
<head>
  <meta charset="utf-8">
  <title>Statistici Email Marketing</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/foundation.css">
  <link rel="stylesheet" href="css/app.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="grid-container">
  <div class="grid-x grid-padding-x">
    <!-- Meniul din stânga -->
    <div class="cell small-3">
      <?php include("meniu.php"); ?>
    </div>

    <!-- Conținutul principal -->
    <div class="cell small-9">
      <h1>📈 Statistici Emailuri</h1>

      <div class="grid-x grid-padding-x">
        <div class="cell medium-6">
          <h4>📊 Emailuri trimise pe zile</h4>
          <canvas id="barChart"></canvas>
        </div>
        <div class="cell medium-6">
          <h4>✅ Succes vs ❌ Eșec</h4>
          <canvas id="pieChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Bar chart
  const barCtx = document.getElementById('barChart').getContext('2d');
  const barChart = new Chart(barCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($bar_labels) ?>,
      datasets: [{
        label: 'Emailuri trimise',
        data: <?= json_encode($bar_data) ?>,
        backgroundColor: '#2199e8'
      }]
    }
  });

  // Pie chart
  const pieCtx = document.getElementById('pieChart').getContext('2d');
  const pieChart = new Chart(pieCtx, {
    type: 'pie',
    data: {
      labels: ['Success', 'Failure'],
      datasets: [{
        data: [<?= $success ?>, <?= $failure ?>],
        backgroundColor: ['#3adb76', '#ec5840']
      }]
    }
  });
</script>

<script src="js/vendor/jquery.js"></script>
<script src="js/vendor/what-input.js"></script>
<script src="js/vendor/foundation.js"></script>
<script src="js/app.js"></script>
</body>
</html>
