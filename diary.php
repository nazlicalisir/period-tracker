<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$user_id = $_SESSION['user_id'];
$mesaj = "";

// 1. GÜNLÜK EKLEME İŞLEMİ
if ($_POST) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $date = $_POST['entry_date'];
    $emoji = $_POST['mood_emoji']; // Formdan gelen emoji

    if(!empty($content)) {
        // SQL sorgun senin tablonla uyumlu
        $stmt = $db->prepare("INSERT INTO diary_entries (user_id, title, content, entry_date, mood_emoji) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $title, $content, $date, $emoji])) {
            header("Location: diary.php"); 
            exit();
        }
    }
}

// 2. SİLME İŞLEMİ
if (isset($_GET['del'])) {
    $del_id = $_GET['del'];
    $delStmt = $db->prepare("DELETE FROM diary_entries WHERE id = ? AND user_id = ?");
    $delStmt->execute([$del_id, $user_id]);
    header("Location: diary.php");
    exit();
}

// 3. LİSTELEME İŞLEMİ (En yeni en üstte)
$sorgu = $db->prepare("SELECT * FROM diary_entries WHERE user_id = ? ORDER BY entry_date DESC, id DESC");
$sorgu->execute([$user_id]);
$gunlukler = $sorgu->fetchAll(PDO::FETCH_ASSOC);

// Tarih Formatlayıcı
function tarihFormatla($tarih) {
    $gunler = ['Sunday'=>'Pazar', 'Monday'=>'Pazartesi', 'Tuesday'=>'Salı', 'Wednesday'=>'Çarşamba', 'Thursday'=>'Perşembe', 'Friday'=>'Cuma', 'Saturday'=>'Cumartesi'];
    $aylar = ['January'=>'Ocak', 'February'=>'Şubat', 'March'=>'Mart', 'April'=>'Nisan', 'May'=>'Mayıs', 'June'=>'Haziran', 'July'=>'Temmuz', 'August'=>'Ağustos', 'September'=>'Eylül', 'October'=>'Ekim', 'November'=>'Kasım', 'December'=>'Aralık'];
    $d = new DateTime($tarih);
    return $d->format('d') . ' ' . $aylar[$d->format('F')] . ' ' . $d->format('Y') . ', ' . $gunler[$d->format('l')];
}
?>

<!DOCTYPE html>
<html lang="tr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <title>Günlüğüm - İçini Dök</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #fdfbfd; }
        
        /* Form Kartı Stili */
        .write-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            margin-bottom: 50px;
            border: 2px solid #fff0f3;
        }

        /* Timeline (Zaman Tüneli) Ana Yapı */
        .timeline {
            position: relative;
            max-width: 900px;
            margin: 0 auto;
        }
        /* Ortadaki Çizgi (Masaüstü) */
        .timeline::after {
            content: '';
            position: absolute;
            width: 4px;
            background-color: #ffdde1;
            top: 0;
            bottom: 0;
            left: 50%;
            margin-left: -2px;
            border-radius: 2px;
            display: none; /* Mobilde gizle */
        }
        @media (min-width: 768px) {
            .timeline::after { display: block; }
        }

        /* Kutular (Sol ve Sağ) */
        .timeline-container {
            padding: 10px 40px;
            position: relative;
            background-color: inherit;
            width: 100%;
            box-sizing: border-box;
        }
        
        @media (min-width: 768px) {
            .timeline-container { width: 50%; }
            .left { left: 0; }
            .right { left: 50%; }
            
            /* Ortadaki Noktalar */
            .timeline-container::after {
                content: '';
                position: absolute;
                width: 20px; height: 20px;
                right: -10px;
                background-color: white;
                border: 4px solid #ff9a9e;
                top: 25px;
                border-radius: 50%;
                z-index: 1;
            }
            .right::after { left: -10px; }
        }

        /* İçerik Kartı */
        .content-card {
            padding: 25px;
            background-color: white;
            position: relative;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            border-bottom: 4px solid #ff9a9e;
        }
        .content-card:hover { transform: translateY(-5px); }

        .entry-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .entry-date { font-size: 13px; font-weight: bold; color: #b2bec3; }
        .entry-emoji { font-size: 28px; background: #fff0f3; padding: 5px; border-radius: 50%; width: 40px; height: 40px; text-align: center; line-height: 40px;}
        .entry-title { font-size: 18px; color: #2d3436; font-weight: 700; margin: 10px 0 5px 0; }
        .entry-text { color: #636e72; font-size: 15px; line-height: 1.6; }
        
        .delete-btn {
            color: #fab1a0;
            font-size: 14px;
            text-decoration: none;
            position: absolute;
            bottom: 20px;
            right: 20px;
            transition: 0.3s;
        }
        .delete-btn:hover { color: #d63031; transform: scale(1.1); }

        /* Form Elemanları */
        .mood-options label { cursor: pointer; transition: 0.2s; }
        .mood-options label:hover { transform: scale(1.2); }
        .mood-options input[type="radio"]:checked + span {
            background-color: #ff9a9e;
            border-radius: 50%;
            box-shadow: 0 0 10px #ff9a9e;
        }
        
        .input-stylish {
            width: 100%; padding: 12px; border: 2px solid #eee; border-radius: 10px;
            font-family: 'Quicksand', sans-serif; margin-bottom: 15px; box-sizing: border-box;
        }
        .input-stylish:focus { border-color: #ff9a9e; outline: none; }
        
        .btn-stylish {
            background: linear-gradient(45deg, #ff9a9e, #fad0c4);
            color: white; border: none; padding: 12px 30px; border-radius: 25px;
            font-weight: bold; cursor: pointer; width: 100%; font-size: 16px;
            transition: 0.3s;
        }
        .btn-stylish:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255, 154, 158, 0.4); }
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
            <a href="diary.php" class="menu-item active"><i class="fas fa-book-open"></i> Günlüğüm</a>
            <a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i> Raporlar</a>
            <div style="margin: 20px 0; border-top: 1px solid #f0f0f0;"></div>
            <a href="profile.php" class="menu-item"><i class="fas fa-user-cog"></i> Profil</a>
            <a href="logout.php" class="menu-item" style="color:#e85a71;"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
        </nav>
    </div>

    <div class="main-content">
        
        <div class="header-welcome">
            <div>
                <h1 style="color:#2d3436;">Sevgili Günlük 📔</h1>
                <p style="color:#636e72;">Bugün hissettiklerini serbest bırak, yarına hafif başla.</p>
            </div>
        </div>

        <div class="write-card">
            <h3 style="color:#e84393; margin-bottom:20px; font-weight:700;"><i class="fas fa-pen-nib"></i> Yeni Bir Anı Ekle</h3>
            
            <form method="post">
                <div style="display:flex; gap:15px; margin-bottom:15px;">
                    <div style="flex:1">
                        <label style="font-size:12px; color:#888; font-weight:bold;">TARİH</label>
                        <input type="date" name="entry_date" value="<?php echo date('Y-m-d'); ?>" class="input-stylish" required>
                    </div>
                    <div style="flex:2">
                        <label style="font-size:12px; color:#888; font-weight:bold;">MODUN</label>
                        <div class="mood-options" style="display:flex; gap:15px; padding-top:5px; font-size:24px;">
                            <label><input type="radio" name="mood_emoji" value="🤩" hidden><span>🤩</span></label>
                            <label><input type="radio" name="mood_emoji" value="😊" checked hidden><span>😊</span></label>
                            <label><input type="radio" name="mood_emoji" value="😐" hidden><span>😐</span></label>
                            <label><input type="radio" name="mood_emoji" value="😔" hidden><span>😔</span></label>
                            <label><input type="radio" name="mood_emoji" value="😠" hidden><span>😠</span></label>
                            <label><input type="radio" name="mood_emoji" value="🤕" hidden><span>🤕</span></label>
                            <label><input type="radio" name="mood_emoji" value="😴" hidden><span>😴</span></label>
                        </div>
                    </div>
                </div>

                <input type="text" name="title" placeholder="Bir başlık at... (Örn: Bugün harikaydı!)" class="input-stylish" style="font-weight:bold;">
                
                <textarea name="content" placeholder="İçinden gelenleri yaz..." rows="5" class="input-stylish" style="resize:vertical;"></textarea>

                <button type="submit" class="btn-stylish">KAYDET VE SAKLA</button>
            </form>
        </div>

        <?php if (count($gunlukler) > 0): ?>
            <div class="timeline">
                <?php foreach ($gunlukler as $index => $g): 
                    // Sırayla sağa ve sola dizmek için mantık
                    $side = ($index % 2 == 0) ? 'left' : 'right';
                ?>
                    <div class="timeline-container <?php echo $side; ?>">
                        <div class="content-card">
                            <div class="entry-header">
                                <span class="entry-date"><i class="far fa-calendar-alt"></i> <?php echo tarihFormatla($g['entry_date']); ?></span>
                                <div class="entry-emoji"><?php echo $g['mood_emoji']; ?></div>
                            </div>
                            
                            <?php if(!empty($g['title'])): ?>
                                <h4 class="entry-title"><?php echo htmlspecialchars($g['title']); ?></h4>
                            <?php endif; ?>
                            
                            <div class="entry-text">
                                <?php echo nl2br(htmlspecialchars($g['content'])); ?>
                            </div>

                            <a href="diary.php?del=<?php echo $g['id']; ?>" class="delete-btn" onclick="return confirm('Bu güzel anıyı silmek istediğine emin misin?');">
                                <i class="fas fa-trash-alt"></i> Sil
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:50px; color:#b2bec3; background:white; border-radius:20px;">
                <i class="fas fa-book" style="font-size:50px; margin-bottom:20px; opacity:0.5;"></i>
                <p>Henüz hiç günlük yazmadın. Yukarıdaki formdan ilk anını ekle! ☝️</p>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>