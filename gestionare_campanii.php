<?php
session_start();
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "marketing";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Eroare DB: " . $conn->connect_error);
}

$campaigns = [];
$selected_campaign_id = $_POST['campaign_id'] ?? ($_SESSION['last_selected_campaign'] ?? '');
unset($_SESSION['last_selected_campaign']);
$selected_template_id = null;
$message = '';
$template_content = '';

// Setări SMTP
$smtp_config = ["server" => "", "port" => "587", "user" => "", "pass" => ""];
if (file_exists("config.json")) {
    $smtp_config = json_decode(file_get_contents("config.json"), true);
}

// Emailuri disponibile
$emails = [];
$emailRes = $conn->query("SELECT email FROM contacts ORDER BY email ASC");
if ($emailRes) {
    while ($row = $emailRes->fetch_assoc()) {
        $emails[] = $row['email'];
    }
}

// Template-uri disponibile
$templates = [];
$templateRes = $conn->query("SELECT id, denumire FROM templates ORDER BY denumire ASC");
if ($templateRes) {
    while ($row = $templateRes->fetch_assoc()) {
        $templates[] = $row;
    }
}

// Toggle activ/inactiv
if (isset($_POST['toggle']) && !empty($selected_campaign_id)) {
    $conn->query("UPDATE campaigns SET activ = IF(activ = 1, 0, 1), data_creare = NOW() WHERE id = $selected_campaign_id");
    $_SESSION['last_selected_campaign'] = $selected_campaign_id;
    header("Location: gestionare_campanii.php");
    exit;
}

// Creare campanie nouă
if (isset($_POST['create']) && !empty($_POST['new_name']) && !empty($_POST['subject']) && !empty($_POST['template_id'])) {
    $name = $conn->real_escape_string($_POST['new_name']);
    $subject = $conn->real_escape_string($_POST['subject']);
    $template_id = (int)$_POST['template_id'];
    $user_id = $_SESSION['user']['id'] ?? 1;
    $conn->query("INSERT INTO campaigns (user_id, nume_campanie, subiect, data_creare, total_sent, template_id, activ) VALUES ($user_id, '$name', '$subject', NOW(), 0, $template_id, 1)");
    header("Location: gestionare_campanii.php");
    exit;
}

// Ștergere campanie
if (isset($_POST['delete']) && !empty($selected_campaign_id)) {
    $conn->query("DELETE FROM campaigns WHERE id = $selected_campaign_id");
    header("Location: gestionare_campanii.php");
    exit;
}

// Vizualizare / Editare / Trimitere (pregătește conținut și id template)
if ((isset($_POST['view']) || isset($_POST['edit']) || isset($_POST['send_form'])) && !empty($selected_campaign_id)) {
    $res = $conn->query("SELECT t.content, t.id as template_id FROM campaigns c JOIN templates t ON c.template_id = t.id WHERE c.id = $selected_campaign_id");
    if ($row = $res->fetch_assoc()) {
        $template_content = $row['content'] ?? '';
        $selected_template_id = $row['template_id'] ?? null;
    }
}

// Salvare modificări
if (isset($_POST['save']) && !empty($_POST['edited_content']) && !empty($selected_campaign_id)) {
    $content = $conn->real_escape_string($_POST['edited_content']);
    $conn->query("UPDATE templates t JOIN campaigns c ON c.template_id = t.id SET t.content = '$content' WHERE c.id = $selected_campaign_id");
    $message = "✅ Campania a fost actualizată.";
}

// Trimitere emailuri
if (isset($_POST['send']) && !empty($selected_campaign_id) && !empty($_POST['smtp_user'])) {
    $res = $conn->query("SELECT t.content, c.nume_campanie, c.subiect, c.template_id FROM campaigns c JOIN templates t ON c.template_id = t.id WHERE c.id = $selected_campaign_id AND c.activ = 1");
    if ($res && $row = $res->fetch_assoc()) {
        $template_content = $row['content'];
        $subject = $row['subiect'];

        $smtp_server = $_POST['smtp_server'];
        $smtp_port = $_POST['smtp_port'];
        $smtp_user = $_POST['smtp_user'];
        $smtp_pass = $_POST['smtp_pass'];
        $email_custom = $_POST['email_custom'] ?? '';

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtp_server;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
        $mail->Port = $smtp_port;
        $mail->isHTML(true);
        $mail->Subject = $subject;

        $mail->SMTPDebug = 0;
        $mail->Debugoutput = function ($str, $level) {
            file_put_contents('mail_debug.log', "[$level] $str\n", FILE_APPEND);
        };

        $contacts = [];
        if ($email_custom) {
            $contacts = [["nume" => "Persoana", "email" => $email_custom]];
        } else {
            $res = $conn->query("SELECT nume, email FROM contacts");
            while ($row = $res->fetch_assoc()) {
                $contacts[] = $row;
            }
        }

        $total_sent = 0;
        foreach ($contacts as $c) {
            $status = "Success";
            try {
                $mail->clearAddresses();
                $mail->addAddress($c['email'], $c['nume']);
                $body = str_replace("{{name}}", $c['nume'], $template_content);
                $mail->Body = $body;
                $mail->send();
            } catch (Exception $e) {
                $status = "Failure";
            }
            $nume = $conn->real_escape_string($c['nume']);
            $email = $conn->real_escape_string($c['email']);
            $conn->query("INSERT INTO logs (name, email, status, timestamp) VALUES ('$nume', '$email', '$status', NOW())");
            $event = ($status === "Success") ? "opened" : "failure";

            // obține ID-ul contactului după email
            $res_id = $conn->query("SELECT id FROM contacts WHERE email = '$email' LIMIT 1");
            if ($res_id && $row_id = $res_id->fetch_assoc()) {
                $recipient_id = (int)$row_id['id'];

                // evită duplicarea înregistrărilor
                $check = $conn->query("SELECT 1 FROM email_events WHERE campanie_id = $selected_campaign_id AND recipient_email_id = $recipient_id AND event_type = '$event'");
                if ($check && $check->num_rows === 0) {
                    $conn->query("INSERT INTO email_events (campanie_id, recipient_email_id, event_type, event_time) VALUES ($selected_campaign_id, $recipient_id, '$event', NOW())");
                }
            }
            if ($status === "Success") $total_sent++;
        }

        $conn->query("UPDATE campaigns SET total_sent = total_sent + $total_sent, data_trimitere = NOW() WHERE id = $selected_campaign_id");
        $message = "📤 Emailuri trimise cu succes către $total_sent destinatar(i).";
    } else {
        $message = "⚠️ Campania este inactivă sau nu a fost găsită.";
    }
}

$result = $conn->query("SELECT c.id, c.nume_campanie, c.activ FROM campaigns c ORDER BY c.id DESC");
if ($result) {
    $campaigns = $result->fetch_all(MYSQLI_ASSOC);
}
?>


<!doctype html>
<html class="no-js" lang="ro">
<head>
  <meta charset="utf-8">
  <title>Gestionare Campanii Newsletter</title>
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
      <h1>📬 Gestionare Campanii Newsletter</h1>

      <?php if ($message): ?>
        <div class="callout success"><?= $message ?></div>
      <?php endif; ?>

      <form method="post">
        <label for="campaign_id">Alege o campanie:</label>
        <select name="campaign_id" id="campaign_id" required>
          <option value="">-- Selectează --</option>
          <?php foreach ($campaigns as $camp): ?>
            <option value="<?= $camp['id'] ?>" <?= ($camp['id'] == $selected_campaign_id ? 'selected' : '') ?>>
              <?= htmlspecialchars($camp['nume_campanie']) ?> <?= $camp['activ'] ? '(activă)' : '(inactivă)' ?>
            </option>
          <?php endforeach; ?>
        </select>

        <div class="grid-x grid-padding-x">
          <div class="cell small-2"><button type="submit" name="view" class="button">🔍 Vizualizează</button></div>
          <div class="cell small-2"><button type="submit" name="edit" class="button">✏️ Editează</button></div>
          <div class="cell small-2"><button type="submit" name="create_form" class="button">➕ Creează Nou</button></div>
          <div class="cell small-2"><button type="submit" name="delete" class="button alert">🗑️ Șterge</button></div>
          <div class="cell small-2"><button type="submit" name="send_form" class="button success">📤 Trimite</button></div>
          <div class="cell small-2"><button type="submit" name="toggle" class="button warning">🔁 Activează/Dezactivează</button></div>
        </div>
      </form>

      <hr>

      <?php if (isset($_POST['create_form'])): ?>
        <form method="post">
          <label>Denumire campanie:</label>
          <input type="text" name="new_name" required>
          <label>Subiect:</label>
          <input type="text" name="subject" required>
          <label>Template asociat:</label>
          <select name="template_id" required>
            <option value="">-- Selectează Template --</option>
            <?php foreach ($templates as $tpl): ?>
              <option value="<?= $tpl['id'] ?>"><?= htmlspecialchars($tpl['denumire']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" name="create" class="button success">💾 Salvează</button>
        </form>
      <?php endif; ?>

      <?php if (isset($_POST['view']) && $template_content): ?>
        <div class="callout primary">
          <?= $template_content ?>
        </div>
      <?php endif; ?>

      <?php if (isset($_POST['edit']) && $template_content): ?>
        <form method="post">
          <textarea name="edited_content" rows="10" style="width:100%;"><?= htmlspecialchars($template_content) ?></textarea>
          <input type="hidden" name="template_id" value="<?= $selected_template_id ?>">
          <button type="submit" name="save" class="button">💾 Salvează modificările</button>
        </form>
      <?php endif; ?>

      <?php if (isset($_POST['send_form']) && $selected_template_id): ?>
        <form method="post">
          <input type="hidden" name="template_id" value="<?= $selected_template_id ?>">
          <label>Email destinat specific (opțional):</label>
          <select name="email_custom">
            <option value="">-- Selectează --</option>
            <?php foreach ($emails as $e): ?>
              <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
            <?php endforeach; ?>
          </select>
          <label>SMTP Server:</label>
          <input type="text" name="smtp_server" value="<?= htmlspecialchars($smtp_config['server']) ?>">
          <label>Port:</label>
          <input type="number" name="smtp_port" value="<?= htmlspecialchars($smtp_config['port']) ?>">
          <label>Email expeditor:</label>
          <input type="text" name="smtp_user" value="<?= htmlspecialchars($smtp_config['user']) ?>">
          <label>Parolă:</label>
          <input type="password" name="smtp_pass" value="<?= htmlspecialchars($smtp_config['pass']) ?>">
          <label>Subiect:</label>
          <input type="text" name="subject" value="Newsletter">
          <button type="submit" name="send" class="button success">📨 Trimite Campanie</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="js/vendor/jquery.js"></script>
<script src="js/vendor/what-input.js"></script>
<script src="js/vendor/foundation.js"></script>
<script src="js/app.js"></script>
</body>
</html>
