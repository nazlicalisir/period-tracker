<?php
try {
    $host = 'YOUR_DATABASE_HOST';
$dbname = 'YOUR_DATABASE_NAME';
$username = 'YOUR_DATABASE_USERNAME';
$password = 'YOUR_DATABASE_PASSWORD';

    // DÜZELTME 1: Bağlantı cümlesinde charset=utf8mb4 yapıldı.
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // DÜZELTME 2: Emojiler için garanti ayar eklendi.
    $db->exec("SET NAMES 'utf8mb4'");
    
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Oturum başlat
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // --- GÜVENLİK: OTOMATİK ÇIKIŞ (30 DAKİKA) ---
    // Kullanıcı giriş yapmışsa ve 30 dakikadır (1800 saniye) hareketsizse çıkış yap.
    $zaman_asimi = 1800; 

    if (isset($_SESSION['user_id']) && isset($_SESSION['son_aktivite'])) {
        $gecen_sure = time() - $_SESSION['son_aktivite'];

        if ($gecen_sure > $zaman_asimi) {
            // Süre doldu, oturumu güvenli şekilde sonlandır
            session_unset();
            session_destroy();
            
            // Giriş sayfasına at (adres çubuğuna bilgi notu ekleyerek)
            header("Location: index.php?timeout=1");
            exit;
        }
    }

    // Kullanıcının son işlem zamanını şu an olarak güncelle
    $_SESSION['son_aktivite'] = time();

    // (Not: Tema kodları silindi, sistem artık varsayılan olarak ferah/light çalışacak.)

} catch (PDOException $e) {
    // Hata mesajını ekrana bas ve durdur
    die("Bağlantı hatası: " . $e->getMessage());
}
?>