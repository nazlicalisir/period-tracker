<?php
session_start();
require 'db.php';

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Form gönderildiğinde çalışacak kısım
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'];
    $end_date   = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $mood       = $_POST['mood'];
    $pain_level = $_POST['pain_level'];
    $notes      = $_POST['notes'];
    $user_id    = $_SESSION['user_id'];
    
    // Belirtileri (Checkbox'ları) metne çevir
    $symptoms = isset($_POST['symptoms']) ? implode(', ', $_POST['symptoms']) : '';

    // Dosya Yükleme İşlemi
    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir);
        $fileName = time() . '_' . $_FILES['photo']['name'];
        $uploadFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadFile)) {
            $photo_path = $uploadFile;
        }
    }

    // Veritabanına Kayıt
    $stmt = $db->prepare("INSERT INTO periods (user_id, start_date, end_date, mood, pain_level, symptoms, notes, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([$user_id, $start_date, $end_date, $mood, $pain_level, $symptoms, $notes, $photo_path]);

    if ($result) {
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Bir hata oluştu, lütfen tekrar dene.";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kayıt Ekle - Regl Takip</title>
    <link rel="stylesheet" href="style.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Quicksand', sans-serif !important; background-color: #fdfbfd; }
        .pacifico-font { font-family: 'Pacifico', cursive; }
        
        /* Form Konteyner Özelleştirme */
        .form-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            max-width: 600px;
            margin: 0 auto; /* Ortalamak için */
        }
        
        /* Input stilleri */
        input[type="date"], select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #f1f2f6;
            border-radius: 10px;
            font-family: 'Quicksand', sans-serif;
            margin-top: 5px;
            box-sizing: border-box; /* Taşmayı engeller */
        }
        input:focus, select:focus, textarea:focus {
            border-color: #ff9a9e;
            outline: none;
        }

        .btn-submit {
            background: linear-gradient(45deg, #ff9a9e, #fad0c4);
            border: none;
            color: white;
            padding: 15px;
            width: 100%;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 20px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 154, 158, 0.4);
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand" style="font-size: 24px; color: #e84393; font-family: 'Quicksand', sans-serif; font-weight:700;">
            <i class="fas fa-leaf"></i> ReglTakip
        </div>
        <nav>
            <a href="dashboard.php" class="menu-item"><i class="fas fa-th-large"></i> Genel Bakış</a>
            <a href="calendar.php" class="menu-item"><i class="fas fa-calendar-alt"></i> Takvim</a>
            
            <a href="add_period.php" class="menu-item active"><i class="fas fa-plus-circle"></i> Kayıt Ekle</a>
            
            <a href="diary.php" class="menu-item"><i class="fas fa-book-open"></i> Günlüğüm</a>
            <a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i> Raporlar</a>
            <div style="margin: 20px 0; border-top: 1px solid #f0f0f0;"></div>
            <a href="profile.php" class="menu-item"><i class="fas fa-user-cog"></i> Profil</a>
            <a href="logout.php" class="menu-item" style="color:#e85a71;"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
        </nav>
    </div>

    <div class="main-content">
        
        <div class="form-container">
            
            <div class="form-header" style="text-align:center; margin-bottom:30px;">
                <h2 style="color:#e84393; font-size:28px;"><i class="fas fa-plus-circle"></i> Yeni Kayıt Ekle</h2>
                <p style="color:#636e72;">Bugün nasıl hissediyorsun?</p>
            </div>

            <?php if(isset($error)): ?>
                <div class="error-msg" style="background:#ffeaa7; color:#d63031; padding:10px; border-radius:10px; margin-bottom:20px; text-align:center;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                
                <div class="form-group" style="margin-bottom:20px;">
                    <label style="font-weight:600; color:#2d3436;">Başlangıç Tarihi:</label>
                    <input type="date" name="start_date" required>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label style="font-weight:600; color:#2d3436;">Bitiş Tarihi (Bittiyse gir):</label>
                    <input type="date" name="end_date">
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label style="font-weight:600; color:#2d3436;">Ağrı Seviyesi (1-10): <span id="painVal" style="color:#e84393; font-weight:bold;">5</span></label>
                    <input type="range" name="pain_level" min="1" max="10" value="5" oninput="document.getElementById('painVal').innerText = this.value" style="width:100%; margin-top:10px; accent-color: #e84393;">
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label style="font-weight:600; color:#2d3436;">Ruh Hali:</label>
                    <select name="mood">
    <option value="Mutlu">😊 Mutlu</option>
    <option value="Normal">😐 Normal</option>
    <option value="Gergin">😬 Gergin</option> <option value="Hassas">🥺 Hassas</option>
    <option value="Sinirli">😠 Sinirli</option>
    <option value="Yorgun">😴 Yorgun</option>
    <option value="Ağrılı">🤕 Ağrılı</option>
</select>
                </div>

                <div class="form-group" style="margin-top:20px; background:#fff5f7; padding:20px; border-radius:15px; border:1px dashed #ffccee;">
                    <label style="font-weight:600; color:#e84393; display:block; margin-bottom:15px;">
                        Vücudunda neler hissediyorsun? (İstediklerini Seç)
                    </label>
                    
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <label style="background: #fff; padding: 8px 15px; border-radius: 20px; border: 1px solid #ddd; cursor: pointer; display:flex; align-items:center; transition:0.2s;">
                            <input type="checkbox" name="symptoms[]" value="Baş Ağrısı" style="margin-right: 8px;"> 🤕 Baş Ağrısı
                        </label>

                        <label style="background: #fff; padding: 8px 15px; border-radius: 20px; border: 1px solid #ddd; cursor: pointer; display:flex; align-items:center; transition:0.2s;">
                            <input type="checkbox" name="symptoms[]" value="Şişkinlik" style="margin-right: 8px;"> 🎈 Şişkinlik
                        </label>

                        <label style="background: #fff; padding: 8px 15px; border-radius: 20px; border: 1px solid #ddd; cursor: pointer; display:flex; align-items:center; transition:0.2s;">
                            <input type="checkbox" name="symptoms[]" value="Bel Ağrısı" style="margin-right: 8px;"> 🦴 Bel Ağrısı
                        </label>

                        <label style="background: #fff; padding: 8px 15px; border-radius: 20px; border: 1px solid #ddd; cursor: pointer; display:flex; align-items:center; transition:0.2s;">
                            <input type="checkbox" name="symptoms[]" value="Mide Bulantısı" style="margin-right: 8px;"> 🤢 Mide Bulantısı
                        </label>
                        
                        <label style="background: #fff; padding: 8px 15px; border-radius: 20px; border: 1px solid #ddd; cursor: pointer; display:flex; align-items:center; transition:0.2s;">
                            <input type="checkbox" name="symptoms[]" value="Yorgunluk" style="margin-right: 8px;"> 😴 Yorgunluk
                        </label>
                        
                        <label style="background: #fff; padding: 8px 15px; border-radius: 20px; border: 1px solid #ddd; cursor: pointer; display:flex; align-items:center; transition:0.2s;">
                            <input type="checkbox" name="symptoms[]" value="Tatlı İsteği" style="margin-right: 8px;"> 🍫 Tatlı Kriz
                        </label>

                         <label style="background: #fff; padding: 8px 15px; border-radius: 20px; border: 1px solid #ddd; cursor: pointer; display:flex; align-items:center; transition:0.2s;">
                            <input type="checkbox" name="symptoms[]" value="Sivilce" style="margin-right: 8px;"> 🔴 Sivilce
                        </label>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top:20px;">
                    <label style="font-weight:600; color:#2d3436;">Notlar (İsteğe bağlı):</label>
                    <textarea name="notes" rows="4" placeholder="Bugün neler yaşadın?"></textarea>
                </div>

                <div class="form-group" style="margin-top:20px;">
                    <label style="font-weight:600; color:#2d3436;">Fotoğraf/Dosya (İsteğe bağlı):</label>
                    <input type="file" name="photo">
                </div>

                <button type="submit" class="btn-submit">Kaydet 💖</button>
            </form>
        </div>
    </div>

    <script>
        const checkboxes = document.querySelectorAll('input[name="symptoms[]"]');
        checkboxes.forEach(box => {
            box.addEventListener('change', function() {
                const parentLabel = this.parentElement;
                if(this.checked) {
                    parentLabel.style.backgroundColor = '#fff0f3';
                    parentLabel.style.borderColor = '#ff9a9e';
                    parentLabel.style.color = '#e84393';
                    parentLabel.style.fontWeight = '600';
                } else {
                    parentLabel.style.backgroundColor = '#fff';
                    parentLabel.style.borderColor = '#ddd';
                    parentLabel.style.color = '#555';
                    parentLabel.style.fontWeight = '400';
                }
            });
        });
    </script>
</body>
</html>