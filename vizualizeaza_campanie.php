<?php
$conn = new mysqli("localhost", "root", "", "marketing");
if ($conn->connect_error) die("Eroare: " . $conn->connect_error);

$id = (int) $_GET['id'];
$result = $conn->query("SELECT content FROM templates WHERE id = $id LIMIT 1");

if ($result && $row = $result->fetch_assoc()) {
    echo $row['content']; // conținutul HTML complet
} else {
    echo "Campania nu a fost găsită.";
}
$conn->close();
?>
