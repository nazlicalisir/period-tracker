<?php
session_start();
require 'db.php';

// Güvenlik: Giriş yapmayan veya ID göndermeyen giremez
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$kayit_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// SADECE kendi kaydını silebilirsin! (Güvenlik Önlemi)
// Başkasının ID'sini adres çubuğuna yazarak silemesin diye kontrol ediyoruz.
$sorgu = $db->prepare("DELETE FROM periods WHERE id = ? AND user_id = ?");
$sonuc = $sorgu->execute([$kayit_id, $user_id]);

// İşlem bitince Dashboard'a geri dön
header("Location: dashboard.php");
exit();
?>