<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "marketing");
if ($conn->connect_error) die("Eroare DB: " . $conn->connect_error);

// Preluare filtre
$template = $_GET['template_name'] ?? '';
$status = $_GET['status'] ?? '';
$data_start = $_GET['data_start'] ?? '';
$data_end = $_GET['data_end'] ?? '';

// Construire condiții WHERE pentru tabelul de loguri
$where = [];
if ($template) $where[] = "contacts.template_name = '" . $conn->real_escape_string($template) . "'";
if ($status) $where[] = "logs.status = '" . $conn->real_escape_string($status) . "'";
if ($data_start) $where[] = "logs.timestamp >= '" . $conn->real_escape_string($data_start) . "'";
if ($data_end) $where[] = "logs.timestamp <= '" . $conn->real_escape_string($data_end) . "'";

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Query pentru loguri emailuri + evenimente
$sql = "
  SELECT 
    logs.name, logs.email, logs.status, logs.timestamp, contacts.template_name,
    GROUP_CONCAT(DISTINCT e.event_type ORDER BY e.event_time SEPARATOR ', ') AS events
  FROM logs
  LEFT JOIN contacts ON logs.email = contacts.email
  LEFT JOIN email_events e ON logs.email = e.recipient_email_id
  $where_clause
  GROUP BY logs.email, logs.timestamp
  ORDER BY logs.timestamp DESC
";

$result = $conn->query($sql);

// Export CSV loguri
if (isset($_GET['export']) && $result && $result->num_rows > 0) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=raport_emailuri.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Nume', 'Email', 'Status', 'Data', 'Campanie', 'Evenimente']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['name'], $row['email'], $row['status'], $row['timestamp'],
            $row['template_name'], $row['events']
        ]);
    }
    fclose($output);
    exit;
}

// Dropdown cu campanii (template_name)
$templates = [];
$tpl = $conn->query("SELECT DISTINCT template_name FROM contacts ORDER BY template_name ASC");
while ($row = $tpl->fetch_assoc()) {
    $templates[] = $row['template_name'];
}

// Construire WHERE pt. filtrare campanii
$conditii_campanii = [];
if ($data_start) $conditii_campanii[] = "e.event_time >= '" . $conn->real_escape_string($data_start) . "'";
if ($data_end) $conditii_campanii[] = "e.event_time <= '" . $conn->real_escape_string($data_end) . "'";
$clauza_where_campanii = $conditii_campanii ? "WHERE " . implode(" AND ", $conditii_campanii) : "";

// Query pentru statistici campanii
$rapoarte = $conn->query("  
  SELECT c.nume_campanie AS campaign_name,
         c.total_sent,
         SUM(CASE WHEN e.event_type = 'opened' THEN 1 ELSE 0 END) as opens,
         SUM(CASE WHEN e.event_type = 'clicked' THEN 1 ELSE 0 END) as clicks,
         SUM(CASE WHEN e.event_type = 'converted' THEN 1 ELSE 0 END) as conversions,
         ROUND(SUM(CASE WHEN e.event_type = 'opened' THEN 1 ELSE 0 END) * 100.0 / NULLIF(c.total_sent, 0), 2) as open_rate,
         ROUND(SUM(CASE WHEN e.event_type = 'clicked' THEN 1 ELSE 0 END) * 100.0 / NULLIF(c.total_sent, 0), 2) as click_rate,
         ROUND(SUM(CASE WHEN e.event_type = 'converted' THEN 1 ELSE 0 END) * 100.0 / NULLIF(c.total_sent, 0), 2) as conversion_rate
  FROM campaigns c
  LEFT JOIN email_events e ON c.id = e.campanie_id
  $clauza_where_campanii
  GROUP BY c.id, c.nume_campanie, c.total_sent
");

// Export CSV campanii
if (isset($_GET['export_campanii']) && $rapoarte && $rapoarte->num_rows > 0) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=raport_campanii.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Campanie', 'Emailuri Trimise', 'Deschideri', 'Click-uri', 'Conversii', 'Rată Deschidere (%)', 'Rată Click (%)', 'Rată Conversie (%)']);
    while ($row = $rapoarte->fetch_assoc()) {
        fputcsv($output, [
            $row['campaign_name'], $row['total_sent'], $row['opens'],
            $row['clicks'], $row['conversions'],
            $row['open_rate'], $row['click_rate'], $row['conversion_rate']
        ]);
    }
    fclose($output);
    exit;
}

$conn->close();
?>

<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Raportări Emailuri</title>
  <link rel="stylesheet" href="css/foundation.css">
</head>
<body>
<div class="grid-container">
  <div class="grid-x grid-padding-x">
    <div class="cell small-3">
      <?php include("meniu.php"); ?>
    </div>
    <div class="cell small-9">
      <h2>📑 Raportări Emailuri</h2>

      <form method="get">
        <label>Campanie:
          <select name="template_name">
            <option value="">-- Toate --</option>
            <?php foreach ($templates as $t): ?>
              <option value="<?= htmlspecialchars($t) ?>" <?= $t == $template ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Status:
          <select name="status">
            <option value="">-- Toate --</option>
            <option value="Success" <?= $status == 'Success' ? 'selected' : '' ?>>Success</option>
            <option value="Failure" <?= $status == 'Failure' ? 'selected' : '' ?>>Failure</option>
          </select>
        </label>
        <label>De la:
          <input type="date" name="data_start" value="<?= htmlspecialchars($data_start) ?>">
        </label>
        <label>Până la:
          <input type="date" name="data_end" value="<?= htmlspecialchars($data_end) ?>">
        </label>
        <div class="grid-x">
          <div class="cell small-6">
            <button type="submit" class="button">🔍 Filtrează</button>
          </div>
          <div class="cell small-6 text-right">
            <button type="submit" name="export" value="1" class="button secondary">⬇️ Exportă CSV</button>
          </div>
        </div>
      </form>

      <hr>

      <?php if ($result && $result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Nume</th>
              <th>Email</th>
              <th>Status</th>
              <th>Data</th>
              <th>Campanie</th>
              <th>Evenimente</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['status']) ?></td>
                <td><?= htmlspecialchars($row['timestamp']) ?></td>
                <td><?= htmlspecialchars($row['template_name']) ?></td>
                <td><?= htmlspecialchars($row['events']) ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>⚠️ Nu s-au găsit rezultate pentru filtrele selectate.</p>
      <?php endif; ?>

      <hr>

      <h3>📊 Rata de Deschidere, Click și Conversie</h3>
      <form method="get" class="text-right">
        <input type="hidden" name="data_start" value="<?= htmlspecialchars($data_start) ?>">
        <input type="hidden" name="data_end" value="<?= htmlspecialchars($data_end) ?>">
        <button type="submit" name="export_campanii" value="1" class="button secondary">⬇️ Exportă Rapoarte Campanii</button>
      </form>
      <?php if ($rapoarte && $rapoarte->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Campanie</th>
              <th>Emailuri Trimise</th>
              <th>Deschideri</th>
              <th>Click-uri</th>
              <th>Conversii</th>
              <th>Rată Deschidere (%)</th>
              <th>Rată Click (%)</th>
              <th>Rată Conversie (%)</th>
            </tr>
          </thead>
          <tbody>
            <?php $rapoarte->data_seek(0); while ($row = $rapoarte->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['campaign_name']) ?></td>
                <td><?= $row['total_sent'] ?></td>
                <td><?= $row['opens'] ?></td>
                <td><?= $row['clicks'] ?></td>
                <td><?= $row['conversions'] ?></td>
                <td><?= $row['open_rate'] ?>%</td>
                <td><?= $row['click_rate'] ?>%</td>
                <td><?= $row['conversion_rate'] ?>%</td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>⚠️ Nu există campanii înregistrate în perioada selectată.</p>
      <?php endif; ?>

    </div>
  </div>
</div>
</body>
</html>
