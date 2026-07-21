<?php
session_start();
require 'db.php';

// Güvenlik
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$kayit_id = $_GET['id'];
$mesaj = "";

// 1. Mevcut bilgileri veritabanından çekip forma yazacağız
$sorgu = $db->prepare("SELECT * FROM periods WHERE id = ? AND user_id = ?");
$sorgu->execute([$kayit_id, $user_id]);
$kayit = $sorgu->fetch(PDO::FETCH_ASSOC);

if (!$kayit) {
    echo "Kayıt bulunamadı!";
    exit();
}

// 2. Form gönderilince güncelleme yap
if ($_POST) {
    $baslangic = $_POST['start_date'];
    $bitis = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $notlar = $_POST['notes'];
    
    // Eski dosya yolunu koru, yenisi gelirse değiştir
    $dosya_yolu = $kayit['file_path']; 

    // Yeni dosya yüklendi mi?
    if (isset($_FILES['dosya']) && $_FILES['dosya']['error'] == 0) {
        $hedef_klasor = "uploads/";
        $yeni_isim = $hedef_klasor . time() . "_" . basename($_FILES["dosya"]["name"]);
        
        if (move_uploaded_file($_FILES["dosya"]["tmp_name"], $yeni_isim)) {
            // İstersen burada eski dosyayı sildirebilirsin (unlink komutu ile)
            $dosya_yolu = $yeni_isim;
        }
    }

    // Güncelleme Sorgusu
    $guncelle = $db->prepare("UPDATE periods SET start_date=?, end_date=?, notes=?, file_path=? WHERE id=? AND user_id=?");
    $sonuc = $guncelle->execute([$baslangic, $bitis, $notlar, $dosya_yolu, $kayit_id, $user_id]);

    if ($sonuc) {
        header("Location: dashboard.php"); // Başarılıysa dön
        exit();
    } else {
        $mesaj = "Güncelleme başarısız!";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kaydı Düzenle</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; padding-top: 50px; background-color: #f4f4f9; }
        form { background: white; padding: 30px; border-radius: 8px; width: 400px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        label { display: block; margin-top: 15px; font-weight: bold; color: #555; }
        input, textarea { width: 100%; padding: 10px; margin-top: 5px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
        button { width: 100%; padding: 12px; background: #2196F3; color: white; border: none; margin-top: 20px; cursor: pointer; border-radius: 4px; font-size: 16px;}
        button:hover { background: #1976D2; }
        a { display: block; text-align: center; margin-top: 15px; color: #666; }
        .img-preview { margin-top: 10px; max-width: 100px; display: block; }
    </style>
</head>
<body>

    <form method="post" enctype="multipart/form-data">
        <h2 style="text-align:center; color:#2196F3;">Kaydı Düzenle ✏️</h2>
        
        <?php if($mesaj): ?>
            <p style="color:red; text-align:center;"><?php echo $mesaj; ?></p>
        <?php endif; ?>

        <label>Başlangıç Tarihi:</label>
        <input type="date" name="start_date" required value="<?php echo $kayit['start_date']; ?>">

        <label>Bitiş Tarihi:</label>
        <input type="date" name="end_date" value="<?php echo $kayit['end_date']; ?>">

        <label>Notlar:</label>
        <textarea name="notes" rows="4"><?php echo $kayit['notes']; ?></textarea>

        <label>Dosya (Değiştirmek istersen seç):</label>
        <?php if($kayit['file_path']): ?>
            <small>Şu anki dosya: <a href="<?php echo $kayit['file_path']; ?>" target="_blank">Görüntüle</a></small>
        <?php endif; ?>
        <input type="file" name="dosya">

        <button type="submit">GÜNCELLE</button>
        <a href="dashboard.php">Vazgeç</a>
    </form>

</body>
</html>