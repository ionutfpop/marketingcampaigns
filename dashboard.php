<?php
$data = array();
$total = 0;
$success = 0;
$failures = 0;
$header = [];

if (($handle = fopen("log.csv", "r")) !== FALSE) {
    $header = fgetcsv($handle, 1000, ",");
    if ($header !== false) {
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Ignoră rândul dacă numărul de coloane nu corespunde
            if (count($row) !== count($header)) {
                continue;
            }

            $rowData = array_combine($header, $row);
            $data[] = $rowData;
            $total++;
            if (isset($rowData["Status"])) {
                if ($rowData["Status"] === "Success") $success++;
                if ($rowData["Status"] === "Failure") $failures++;
            }
        }
    }
    fclose($handle);
}

?>
<!doctype html>
<html class="no-js" lang="en" dir="ltr">
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
    <div class="cell">
      <h1>📈 Dashboard Statistici Email Marketing</h1>
    </div>
  </div>

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
            <?php foreach ($header as $col): ?>
              <th><?= htmlspecialchars($col) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data as $row): ?>
            <tr>
              <?php foreach ($row as $value): ?>
                <td><?= htmlspecialchars($value) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
    <div class="callout alert text-center">
      <p>⚠️ Nu există date în fișierul log.csv.</p>
    </div>
  <?php endif; ?>
</div>

<script src="js/vendor/jquery.js"></script>
<script src="js/vendor/what-input.js"></script>
<script src="js/vendor/foundation.js"></script>
<script src="js/app.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>
