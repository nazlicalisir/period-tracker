<?php
session_start();
require 'db.php';

// Giriş yapılmamışsa at
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// ID gelmiş mi kontrol et
if (isset($_GET['id'])) {
    $period_id = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    // Sadece KENDİ kaydını silebilir (Güvenlik Önlemi)
    $stmt = $db->prepare("DELETE FROM periods WHERE id = ? AND user_id = ?");
    $stmt->execute([$period_id, $user_id]);
}

// İşlem bitince ana sayfaya dön
header("Location: dashboard.php");
exit;
?>