<?php
// edit_period.php - Tasarımı Yenilenmiş Düzenleme Sayfası
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
$user_id = $_SESSION['user_id'];

// Düzenlenecek ID gelmediyse geri at
if (!isset($_GET['id'])) { header("Location: dashboard.php"); exit(); }
$period_id = $_GET['id'];

// --- MEVCUT VERİYİ ÇEK ---
$stmt = $db->prepare("SELECT * FROM periods WHERE id = ? AND user_id = ?");
$stmt->execute([$period_id, $user_id]);
$kayit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$kayit) { echo "Kayıt bulunamadı!"; exit(); }

// --- GÜNCELLEME İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'];
    $end_date   = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $pain_level = $_POST['pain_level'];
    $mood       = $_POST['mood'];
    $notes      = $_POST['notes'];
    
    // Dosya İşlemleri
    $file_path = $kayit['file_path']; 
    if (isset($_FILES['document']) && $_FILES['document']['error'] === 0) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = time() . '_' . $_FILES['document']['name'];
        $uploadFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['document']['tmp_name'], $uploadFile)) {
            $file_path = $uploadFile;
        }
    }

    $updateSql = $db->prepare("UPDATE periods SET start_date=?, end_date=?, pain_level=?, mood=?, notes=?, file_path=? WHERE id=? AND user_id=?");
    $updateSql->execute([$start_date, $end_date, $pain_level, $mood, $notes, $file_path, $period_id, $user_id]);

    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kaydı Düzenle - Regl Takip</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Bu sayfaya özel stil düzeltmeleri */
        .edit-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .form-card {
            background: white;
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(255, 154, 158, 0.15);
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-sizing: border-box; /* Taşmayı engeller */
        }
        .form-control:focus {
            border-color: #ff9a9e;
            outline: none;
            background: #fffafa;
        }
        
        /* Range Slider Özelleştirme */
        input[type=range] {
            width: 100%; 
            accent-color: #ff9a9e;
            cursor: pointer;
        }

        .btn-save {
            width: 100%;
            background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
            margin-top: 20px;
        }
        .btn-save:hover { transform: scale(1.02); }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #888;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover { color: #ff9a9e; }
        
        .current-file {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand"><i class="fas fa-spa" style="color:#ff9a9e;"></i> ReglTakip</div>
        <nav>
            <a href="dashboard.php" class="menu-item"><i class="fas fa-th-large"></i> Genel Bakış</a>
            <a href="add_period.php" class="menu-item"><i class="fas fa-plus-circle"></i> Kayıt Ekle</a>
            <a href="diary.php" class="menu-item"><i class="fas fa-book-open"></i> Günlüğüm</a>
            <a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i> Raporlar</a>
            <div style="margin: 20px 0; border-top: 1px solid #f0f0f0;"></div>
            <a href="profile.php" class="menu-item"><i class="fas fa-user-cog"></i> Profil</a>
            <a href="logout.php" class="menu-item" style="color:#e85a71;"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
        </nav>
    </div>

    <div class="main-content">
        
        <div class="edit-container">
            <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Vazgeç ve Geri Dön</a>
            
            <div class="form-card">
                <div style="text-align:center; margin-bottom:30px;">
                    <div style="width:60px; height:60px; background:#fff0f3; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; margin-bottom:15px;">
                        <i class="fas fa-edit" style="color:#ff9a9e; font-size:24px;"></i>
                    </div>
                    <h2 style="color:#333; margin:0;">Kaydı Düzenle</h2>
                    <p style="color:#999; font-size:14px;">Geçmiş verini güncellemek mi istiyorsun?</p>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    
                    <div style="display:flex; gap:20px; margin-bottom:20px;">
                        <div style="flex:1;">
                            <label style="font-size:13px; color:#777; font-weight:bold; margin-bottom:5px; display:block;">Başlangıç</label>
                            <input type="date" name="start_date" class="form-control" required value="<?php echo $kayit['start_date']; ?>">
                        </div>
                        <div style="flex:1;">
                            <label style="font-size:13px; color:#777; font-weight:bold; margin-bottom:5px; display:block;">Bitiş (Opsiyonel)</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo $kayit['end_date']; ?>">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:20px;">
                        <label>Ruh Halin Nasıldı?</label>
                        <select name="mood" class="form-control">
                            <option value="Mutlu" <?php if($kayit['mood']=='Mutlu') echo 'selected'; ?>>😊 Mutlu - Harika hissettim</option>
                            <option value="Normal" <?php if($kayit['mood']=='Normal') echo 'selected'; ?>>😐 Normal - Her zamanki gibi</option>
                            <option value="Hassas" <?php if($kayit['mood']=='Hassas') echo 'selected'; ?>>🥺 Hassas - Duygusalım</option>
                            <option value="Sinirli" <?php if($kayit['mood']=='Sinirli') echo 'selected'; ?>>😠 Sinirli - Gerginim</option>
                            <option value="Ağrılı" <?php if($kayit['mood']=='Ağrılı') echo 'selected'; ?>>🤕 Ağrılı - Çok zorlandım</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:20px;">
                        <label style="display:flex; justify-content:space-between;">
                            <span>Ağrı Seviyesi</span>
                            <span id="painVal" style="color:#ff9a9e; font-weight:bold;"><?php echo $kayit['pain_level']; ?>/10</span>
                        </label>
                        <input type="range" name="pain_level" min="0" max="10" value="<?php echo $kayit['pain_level']; ?>" oninput="document.getElementById('painVal').innerText = this.value + '/10'">
                        <div style="display:flex; justify-content:space-between; font-size:12px; color:#ccc; margin-top:5px;">
                            <span>Yok</span><span>Çok Şiddetli</span>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:20px;">
                        <label>Notlar</label>
                        <textarea name="notes" rows="4" class="form-control" placeholder="Örn: İlaç aldım, sıcak su torbası iyi geldi..."><?php echo htmlspecialchars($kayit['notes']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Dosya / Fotoğraf</label>
                        <?php if($kayit['file_path']): ?>
                            <div class="current-file">
                                <i class="fas fa-file-image" style="color:#ff9a9e;"></i>
                                <a href="<?php echo $kayit['file_path']; ?>" target="_blank" style="text-decoration:none; color:#555;">Mevcut Dosyayı Gör</a>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="document" class="form-control" style="padding:10px;">
                        <small style="color:#999;">Değiştirmek istemiyorsan boş bırak.</small>
                    </div>

                    <button type="submit" class="btn-save">
                        <i class="fas fa-check-circle"></i> Değişiklikleri Kaydet
                    </button>
                </form>
            </div>
        </div>
    </div>

</body>
</html>