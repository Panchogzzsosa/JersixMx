<?php
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

// Resto del código... 