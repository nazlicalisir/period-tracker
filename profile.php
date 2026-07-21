<?php
// Oturum başlatılmamışsa başlat
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

// Giriş yapılmamışsa login sayfasına at
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit(); 
}

$user_id = $_SESSION['user_id'];
$mesaj = "";
$mesajTur = ""; // success veya error

// 1. Kullanıcının Güncel Bilgilerini Veritabanından Çek
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. En Son Regl Tarihini Bul (Bilgi Amaçlı)
$lastPeriodStmt = $db->prepare("SELECT start_date FROM periods WHERE user_id = ? ORDER BY start_date DESC LIMIT 1");
$lastPeriodStmt->execute([$user_id]);
$lastPeriod = $lastPeriodStmt->fetch(PDO::FETCH_ASSOC);
$sonReglTarihi = $lastPeriod ? date("d.m.Y", strtotime($lastPeriod['start_date'])) : "Henüz veri yok";

// 3. Form Gönderildiğinde İşlemler (İKİ AYRI İŞLEM)
if ($_POST) {
    
    // A) Sadece Kişisel Bilgileri Güncelle
    if (isset($_POST['update_info'])) {
        $name = !empty($_POST['name']) ? trim($_POST['name']) : $user['name'];
        $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : NULL;
        $cycle_length = !empty($_POST['cycle_length']) ? $_POST['cycle_length'] : 28;
        $period_length = !empty($_POST['period_length']) ? $_POST['period_length'] : 6;

        $sql = "UPDATE users SET name = ?, birth_date = ?, cycle_length = ?, period_length = ? WHERE id = ?";
        $update = $db->prepare($sql);
        
        if ($update->execute([$name, $birth_date, $cycle_length, $period_length, $user_id])) {
            $mesaj = "Bilgilerin başarıyla güncellendi! ✅";
            $mesajTur = "success";
            // Veriyi tazele
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $mesaj = "Güncelleme hatası.";
            $mesajTur = "error";
        }
    }

    // B) Sadece Şifreyi Güncelle (Güvenlik)
    if (isset($_POST['update_password'])) {
        $new_pass = $_POST['new_password'];
        if (!empty($new_pass) && strlen($new_pass) >= 4) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $update = $db->prepare($sql);
            if ($update->execute([$hashed, $user_id])) {
                $mesaj = "Şifren başarıyla değiştirildi! 🔒";
                $mesajTur = "success";
            }
        } else {
            $mesaj = "Şifre en az 4 karakter olmalı.";
            $mesajTur = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Profil ve Güvenlik - ReglTakip</title>
    <link rel="stylesheet" href="style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- GENEL STİL --- */
        body { 
            font-family: 'Quicksand', sans-serif; 
            background-color: #fdfbfd; 
            color: #2d3436;
        }
        
        /* İki Sütunlu Grid Yapısı */
        .profile-grid {
            display: grid;
            grid-template-columns: 1.6fr 1fr; /* Sol geniş, Sağ dar */
            gap: 30px;
            margin-top: 20px;
        }
        
        /* Kart Tasarımları */
        .settings-card { 
            background: #ffffff;
            padding: 35px; 
            border-radius: 25px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.03); 
            border: 1px solid #f1f2f6;
            margin-bottom: 30px; /* Kutular arası boşluk */
        }
        
        .section-header {
            font-size: 18px; font-weight: 700; color: #2d3436; margin-bottom: 25px;
            display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px;
        }

        /* --- SAĞ TARAF KARTLARI --- */
        
        /* 1. Kullanıcı Bilgi Kartı */
        .info-card { 
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); 
            color: white; padding: 30px; border-radius: 25px; 
            text-align: center; box-shadow: 0 10px 20px rgba(255, 118, 117, 0.3);
            margin-bottom: 30px;
        }
        .user-avatar { 
            width: 90px; height: 90px; background: rgba(255,255,255,0.3); 
            border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            margin: 0 auto 15px; font-size: 35px; border: 3px solid rgba(255,255,255,0.6); 
        }

        /* 2. Yeşil Gizlilik Kartı */
        .privacy-card {
            background: linear-gradient(135deg, #00b894 0%, #55efc4 100%);
            color: white; padding: 25px; border-radius: 25px; text-align: center;
            box-shadow: 0 10px 20px rgba(0, 184, 148, 0.2);
            position: relative; overflow: hidden;
        }
        .privacy-badge {
            background: rgba(255,255,255,0.25); padding: 5px 15px; border-radius: 50px;
            font-size: 11px; font-weight: bold; margin-top: 15px; display: inline-block;
        }

        /* 3. Şifre Kutusu (Özel Stil) */
        .security-box { border: 2px solid #fff0f0; }
        .security-box:hover { border-color: #ff7675; }
        .btn-security { background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%); }

        /* Inputlar */
        .input-wrapper { position: relative; margin-bottom: 20px; }
        .input-wrapper input {
            width: 100%; padding: 15px 15px 15px 45px;
            border: 2px solid #f1f2f6; background-color: #ffffff;
            color: #2d3436; border-radius: 15px; font-size: 14px;
            box-sizing: border-box; transition: 0.3s;
        }
        .input-wrapper input:focus { border-color: #ff9a9e; outline: none; }
        .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #b2bec3; }

        /* Butonlar */
        .btn-submit {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: white; border: none; padding: 15px; width: 100%;
            border-radius: 15px; font-weight: bold; cursor: pointer; transition: 0.3s;
        }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(255, 154, 158, 0.4); }

        /* Mesajlar */
        .alert { padding: 15px; border-radius: 15px; margin-bottom: 20px; font-weight: bold; }
        .alert-success { background: #e8f5e9; color: #27ae60; border: 1px solid #c8e6c9; }
        .alert-error { background: #ffe6e6; color: #d63031; border: 1px solid #ffcccc; }

        /* Sidebar & Layout */
        .sidebar { background: #ffffff; }
        .sidebar a { color: #636e72; }
        .sidebar a:hover, .sidebar a.active { background: rgba(232, 67, 147, 0.1); color: #e84393; }
        .main-content { padding: 30px; margin-left: 280px; }
        
        @media (max-width: 900px) { 
            .profile-grid { grid-template-columns: 1fr; } 
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand" style="font-size: 24px; color: #e84393; font-weight:700;">
            <i class="fas fa-leaf"></i> ReglTakip
        </div>
        <nav>
            <a href="dashboard.php" class="menu-item"><i class="fas fa-th-large"></i> Genel Bakış</a>
            <a href="calendar.php" class="menu-item"><i class="fas fa-calendar-alt"></i> Takvim</a>
            <a href="add_period.php" class="menu-item"><i class="fas fa-plus-circle"></i> Kayıt Ekle</a>
            <a href="diary.php" class="menu-item"><i class="fas fa-book-open"></i> Günlüğüm</a>
            <a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i> Raporlar</a>
            <div style="margin: 20px 0; border-top: 1px solid #f0f0f0;"></div>
            <a href="profile.php" class="menu-item active"><i class="fas fa-user-cog"></i> Profil & Güvenlik</a>
            <a href="logout.php" class="menu-item" style="color:#e85a71;"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header-welcome">
            <div>
                <h1>Hesap Ayarları ⚙️</h1>
                <p style="color:#636e72;">Kişisel bilgilerini ve güvenlik tercihlerini buradan yönet.</p>
            </div>
        </div>

        <?php if($mesaj): ?>
            <div class="alert <?php echo ($mesajTur == 'success') ? 'alert-success' : 'alert-error'; ?>">
                <?php echo $mesaj; ?>
            </div>
        <?php endif; ?>

        <div class="profile-grid">
            
            <div class="left-col">
                
                <div class="settings-card">
                    <div class="section-header"><i class="fas fa-user-circle" style="color:#ff9a9e;"></i> Kişisel Bilgiler</div>
                    <form method="post">
                        <label style="font-size:12px; margin-left:15px; color:#888; font-weight:bold;">Görünen İsim</label>
                        <div class="input-wrapper">
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            <i class="fas fa-user"></i>
                        </div>

                        <label style="font-size:12px; margin-left:15px; color:#888; font-weight:bold;">Doğum Tarihi</label>
                        <div class="input-wrapper">
                            <input type="date" name="birth_date" value="<?php echo $user['birth_date']; ?>">
                            <i class="fas fa-birthday-cake"></i>
                        </div>
                        
                        <div style="display:flex; gap:20px;">
                            <div style="flex:1;">
                                <label style="font-size:12px; margin-left:15px; color:#888; font-weight:bold;">Döngü (Gün)</label>
                                <div class="input-wrapper">
                                    <input type="number" name="cycle_length" value="<?php echo $user['cycle_length']; ?>">
                                    <i class="fas fa-sync-alt"></i>
                                </div>
                            </div>
                            <div style="flex:1;">
                                <label style="font-size:12px; margin-left:15px; color:#888; font-weight:bold;">Regl (Gün)</label>
                                <div class="input-wrapper">
                                    <input type="number" name="period_length" value="<?php echo $user['period_length']; ?>">
                                    <i class="fas fa-tint"></i>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="update_info" class="btn-submit">BİLGİLERİ KAYDET</button>
                    </form>
                </div>

                <div class="settings-card security-box">
                    <div class="section-header"><i class="fas fa-lock" style="color:#6c5ce7;"></i> Şifre Değiştir</div>
                    <form method="post">
                        <label style="font-size:12px; margin-left:15px; color:#888; font-weight:bold;">Yeni Şifren</label>
                        <div class="input-wrapper">
                            <input type="password" name="new_password" placeholder="Yeni güvenli şifreni yaz..." minlength="4">
                            <i class="fas fa-key"></i>
                        </div>
                        <button type="submit" name="update_password" class="btn-submit btn-security">ŞİFREYİ GÜNCELLE</button>
                    </form>
                </div>

            </div>

            <div class="right-col">

                <div class="privacy-card">
                    <i class="fas fa-shield-alt" style="font-size:40px; margin-bottom:10px;"></i>
                    <h3 style="margin:0 0 10px 0;">Verilerin Güvende</h3>
                    <p style="font-size:14px; opacity:0.9; line-height:1.5;">Verilerin şifreli olarak saklanır ve kimseyle paylaşılmaz.</p>
                    <div class="privacy-badge"><i class="fas fa-check-circle"></i> %100 Güvenli</div>
                </div>
                
                <div style="margin-bottom:30px;"></div> <div class="info-card">
                    <div class="user-avatar">
                        <i class="fas fa-user-astronaut"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                    <p style="opacity:0.9;"><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <div style="margin-top:20px; font-size:14px; background:rgba(255,255,255,0.2); padding:15px; border-radius:15px; text-align:left;">
                        <i class="far fa-calendar-check"></i> <b>Son Regl:</b><br>
                        <span style="font-size:16px; margin-top:5px; display:inline-block; font-weight:bold;"><?php echo $sonReglTarihi; ?></span>
                    </div>
                </div>

                <div style="text-align:center; font-size:12px; color:#b2bec3;">
                    <i class="fas fa-stopwatch"></i> Güvenlik için 30 dk hareketsizlikte otomatik çıkış yapılır.
                </div>

            </div>

        </div>
    </div>
</body>
</html>