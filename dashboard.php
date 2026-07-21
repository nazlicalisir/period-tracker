<?php
// 1. AYARLAR VE BAĞLANTILAR
require 'db.php'; 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Hata Raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// --- TEMA AYARINI GARANTİYE ALALIM ---
if (!isset($currentTheme)) {
    $currentTheme = 'light';
    try {
        $themeStmt = $db->prepare("SELECT theme FROM users WHERE id = ?");
        $themeStmt->execute([$user_id]);
        $res = $themeStmt->fetch(PDO::FETCH_ASSOC);
        if ($res && !empty($res['theme'])) {
            $currentTheme = $res['theme'];
        }
    } catch (Exception $e) {}
}

// --- KULLANICI BİLGİLERİNİ ÇEK ---
$uStmt = $db->prepare("SELECT name, cycle_length, period_length FROM users WHERE id = ?");
$uStmt->execute([$user_id]);
$user_info = $uStmt->fetch(PDO::FETCH_ASSOC);

$username = !empty($user_info['name']) ? $user_info['name'] : 'Güzellik';
$cycle_days = ($user_info && !empty($user_info['cycle_length'])) ? $user_info['cycle_length'] : 28;
$period_days = ($user_info && !empty($user_info['period_length'])) ? $user_info['period_length'] : 6;

// --- EKLENTİ 1: MOTİVASYON SÖZLERİ ---
$sozler = [
    "Bugün kendine nazik ol 🤍",
    "Gülümsemen en güzel aksesuarın ✨",
    "Zor günleri atlatacak kadar güçlüsün 💪",
    "Vücudunu dinle, o senin en iyi dostun 🌸",
    "Biraz dinlenmeyi hak ediyorsun ☕",
    "Her şey geçici, sen kalıcısın 💖"
];
$gunun_sozu = $sozler[array_rand($sozler)];

// --- BİLDİRİM SİSTEMİ MANTIĞI ---
$bildirimler = [];

// 1. KONTROL: Regl Yaklaşıyor mu?
try {
    $lastPeriodSql = $db->prepare("SELECT start_date FROM periods WHERE user_id = ? ORDER BY start_date DESC LIMIT 1");
    $lastPeriodSql->execute([$user_id]);
    $lastPeriodDate = $lastPeriodSql->fetchColumn();

    if ($lastPeriodDate) {
        $lastDateObj = new DateTime($lastPeriodDate);
        $today = new DateTime();
        $today->setTime(0,0,0);
        
        // --- GÜNCELLENDİ: Akıllı Tarih Hesaplama ---
        // Eğer son regl tarihi çok eskiyse, döngüyü bugüne taşı
        $nextPeriodDate = clone $lastDateObj;
        $nextPeriodDate->modify("+$cycle_days days");

        while($nextPeriodDate < $today) {
            $nextPeriodDate->modify("+$cycle_days days");
        }

        $fark = $today->diff($nextPeriodDate);
        $kalanGun = (int)$fark->format('%r%a'); 

        if ($kalanGun >= 0 && $kalanGun <= 3) {
            $bildirimler[] = [
                'type' => 'warning',
                'icon' => 'fa-clock',
                'msg' => "⏳ Regl dönemin çok yaklaştı! ($kalanGun gün kaldı). Hazırlıklı olmayı unutma."
            ];
        }
    }
} catch (Exception $e) { }

// 2. KONTROL: Bugün Günlük Girişi Yapıldı mı?
try {
    $todayStr = date('Y-m-d');
    $checkLog = $db->prepare("SELECT COUNT(*) FROM diary_entries WHERE user_id = ? AND date = ?");
    $checkLog->execute([$user_id, $todayStr]);
    $hasDiary = $checkLog->fetchColumn();

    $checkPeriod = $db->prepare("SELECT COUNT(*) FROM periods WHERE user_id = ? AND start_date = ?");
    $checkPeriod->execute([$user_id, $todayStr]);
    $hasPeriod = $checkPeriod->fetchColumn();

    if ($hasDiary == 0 && $hasPeriod == 0) {
        $bildirimler[] = [
            'type' => 'info',
            'icon' => 'fa-pen-fancy',
            'msg' => "📝 Bugün henüz giriş yapmadın. Günün nasıl geçti? Not almak ister misin?"
        ];
    }
} catch (Exception $e) { }

// 3. KONTROL: Yüksek Ağrı Uyarısı (Bildirim paneli için)
try {
    $painSql = $db->prepare("SELECT pain_level FROM periods WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $painSql->execute([$user_id]);
    $lastPain = $painSql->fetchColumn();

    if ($lastPain && $lastPain >= 8) {
        $bildirimler[] = [
            'type' => 'danger',
            'icon' => 'fa-heart-broken',
            'msg' => "💗 Son kaydında ağrın yüksekti. Lütfen bugün bol su iç ve dinlenmeyi unutma."
        ];
    }
} catch (Exception $e) { }


// --- DASHBOARD VERİLERİ ---
$stmt = $db->prepare("SELECT * FROM periods WHERE user_id = ? ORDER BY start_date DESC");
$stmt->execute([$user_id]);
$kayitlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- EKLENTİ 2: AĞRI KARTI İÇİN HAZIRLIK ---
$son_agri_deger = 0;
$agri_kart_border = "transparent";
$agri_kart_renk = "var(--text-color)"; // Temaya uygun varsayılan
$agri_ikon = "fa-fire";

if (!empty($kayitlar)) {
    $son_agri_deger = $kayitlar[0]['pain_level'];
    if ($son_agri_deger >= 8) {
        // Eğer ağrı 8 ve üstü ise KIRMIZI olsun
        $agri_kart_border = "#d63031"; // Kırmızı çerçeve
        $agri_kart_renk = "#d63031";   // Kırmızı yazı
        $agri_ikon = "fa-exclamation-circle";
    }
}

// Grafik Verisi
$grafik_labels = [];
$grafik_data = [];
$ters_kayitlar = array_reverse($kayitlar); 

foreach ($ters_kayitlar as $k) {
    $tarih_format = date('d.m', strtotime($k['start_date']));
    $grafik_labels[] = $tarih_format;
    $grafik_data[] = $k['pain_level'];
}

// --- GÜNCELLENDİ: Tahmin Mesajı Mantığı ---
$tahmin_mesaj = "Veri Yok";
$tahmini_tarih_str = "";

if (count($kayitlar) > 0) {
    $son_baslangic = $kayitlar[0]['start_date'];
    
    // Tarih nesnelerini oluştur
    $tahmin_obj = new DateTime($son_baslangic);
    $bugun_obj = new DateTime();
    $bugun_obj->setTime(0,0,0); // Saatleri sıfırla

    // İlk döngüyü ekle
    $tahmin_obj->modify("+$cycle_days days");

    // Eğer tahmin edilen tarih geçmişte kaldıysa, bugünü geçene kadar ekle
    while ($tahmin_obj < $bugun_obj) {
        $tahmin_obj->modify("+$cycle_days days");
    }

    $tahmini_tarih = $tahmin_obj->format('Y-m-d');
    
    // Türkçe Tarih
    $gunler = [
        'Sunday' => 'Pazar', 'Monday' => 'Pazartesi', 'Tuesday' => 'Salı', 
        'Wednesday' => 'Çarşamba', 'Thursday' => 'Perşembe', 'Friday' => 'Cuma', 'Saturday' => 'Cumartesi'
    ];
    $aylar = [
        'January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart', 'April' => 'Nisan', 
        'May' => 'Mayıs', 'June' => 'Haziran', 'July' => 'Temmuz', 'August' => 'Ağustos', 
        'September' => 'Eylül', 'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'
    ];
    
    $gun_adi = $gunler[$tahmin_obj->format('l')];
    $ay_adi = $aylar[$tahmin_obj->format('F')];
    $gun_sayi = $tahmin_obj->format('d');
    $yil = $tahmin_obj->format('Y');
    
    $tahmini_tarih_str = "$gun_sayi $ay_adi $yil";
    
    // Kalan gün hesapla
    $fark = $bugun_obj->diff($tahmin_obj);
    $gun_sayisi = $fark->days;
    
    if ($bugun_obj > $tahmin_obj) {
        // Normalde while döngüsü bunu engeller ama güvenlik için:
        $tahmin_mesaj = "⚠️ $gun_sayisi gün gecikti!";
    } elseif ($bugun_obj == $tahmin_obj) {
        $tahmin_mesaj = "🩸 Bugün başlaması bekleniyor!";
    } else {
        $tahmin_mesaj = "⏳ $gun_sayisi gün kaldı";
    }
} else {
    $tahmin_mesaj = "İlk kaydını ekle 🌸";
}
?>

<!DOCTYPE html>
<html lang="tr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <title>Panel - Regl Takip</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { font-family: 'Quicksand', sans-serif !important; }
        h1, h2, h3, .brand { font-family: 'Quicksand', sans-serif; font-weight: 700; }
        .pacifico-font { font-family: 'Pacifico', cursive; }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
            background: var(--card-bg); 
            color: var(--text-color); 
            border-radius: 10px; 
            overflow: hidden; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
        }
        th { background: #ff9a9e; color: white; padding: 15px; text-align: left; font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid var(--input-border); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        
        [data-theme="light"] tr:hover { background-color: #fff0f6; }
        [data-theme="dark"] tr:hover { background-color: #4b5a68; }

        .notification-wrapper { width: 100%; margin-bottom: 25px; }
        .notify-card {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--card-bg);
            color: var(--text-color);
            font-family: 'Quicksand', sans-serif;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            animation: slideDown 0.5s ease-out;
            width: 100%; 
            box-sizing: border-box; 
        }
        
        .notify-warning { background: #fff3cd; color: #856404; border-left: 5px solid #ffeeba; }
        .notify-info { background: #d1ecf1; color: #0c5460; border-left: 5px solid #bee5eb; }
        .notify-danger { background: #f8d7da; color: #721c24; border-left: 5px solid #f5c6cb; }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card {
            flex:1; 
            text-align:center; 
            padding: 20px; 
            background: var(--card-bg); 
            color: var(--text-color); 
            border-radius: 15px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        /* Motivasyon Yazısı Animasyonu */
        .quote-text {
            color: var(--muted-text); 
            margin: 5px 0 0 0; 
            font-style: italic; 
            font-size: 15px;
            animation: fadeIn 1.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand" style="font-size: 24px; color: #e84393;">
            <i class="fas fa-leaf"></i> ReglTakip
        </div>
        <nav>
            <a href="dashboard.php" class="menu-item active"><i class="fas fa-th-large"></i> Genel Bakış</a>
            <a href="calendar.php" class="menu-item"><i class="fas fa-calendar-alt"></i> Takvim</a>
            <a href="add_period.php" class="menu-item"><i class="fas fa-plus-circle"></i> Kayıt Ekle</a>
            <a href="diary.php" class="menu-item"><i class="fas fa-book-open"></i> Günlüğüm</a>
            <a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i> Raporlar</a>
            <div style="margin: 20px 0; border-top: 1px solid #f0f0f0;"></div>
            <a href="profile.php" class="menu-item"><i class="fas fa-user-cog"></i> Profil</a>
            <a href="logout.php" class="menu-item" style="color:#e85a71;"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
        </nav>
    </div>

    <div class="main-content">
        
        <div class="notification-wrapper">
            <?php if (!empty($bildirimler)): ?>
                <h3 style="font-size:15px; color:var(--muted-text); margin-bottom:10px; font-weight:600;">
                    <i class="fas fa-bell"></i> Hatırlatmalar
                </h3>
                <?php foreach ($bildirimler as $notif): ?>
                    <div class="notify-card notify-<?php echo $notif['type']; ?>">
                        <div style="font-size: 20px;">
                            <i class="fas <?php echo $notif['icon']; ?>"></i>
                        </div>
                        <div style="font-weight: 500; font-size: 14px; line-height: 1.4;">
                            <?php echo $notif['msg']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="width: 100%; margin-bottom: 30px;">
            <div class="card c-gradient" style="background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); color:white; display:flex; align-items:center; justify-content:space-between; padding:30px; border-radius: 15px; box-shadow: 0 10px 20px rgba(161, 140, 209, 0.3);">
                <div>
                    <h3 style="color:rgba(255,255,255,0.9); margin:0 0 10px; font-weight: 500;">Sonraki Regl Tahmini</h3>
                    <div style="font-size:32px; font-weight:700; letter-spacing: -1px;">
                        <?php echo $tahmin_mesaj; ?>
                    </div>
                    <?php if($tahmini_tarih_str): ?>
                    <div style="font-size:15px; margin-top:5px; opacity:0.9;">
                        <i class="far fa-calendar-alt"></i> Başlangıç: <strong><?php echo $tahmini_tarih_str; ?></strong>
                    </div>
                    <div style="font-size:14px; margin-top:2px; opacity:0.8;">
                        <i class="fas fa-history"></i> Tahmini Süre: <strong><?php echo $period_days; ?> Gün</strong>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="font-size:50px; opacity:0.3;">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1 class="pacifico-font" style="color: #e84393; font-weight: 400; font-size: 36px; margin: 0;">Merhaba, <?php echo htmlspecialchars($username); ?>! 👋</h1>
                <p class="quote-text"><?php echo $gunun_sozu; ?></p>
            </div>
            
            <a href="add_period.php" class="btn-add-new" style="background:#ff9a9e; color: white; padding: 12px 25px; text-decoration: none; border-radius: 25px; display: inline-block; font-weight: 600; box-shadow: 0 4px 10px rgba(255, 154, 158, 0.4);">
                <i class="fas fa-plus"></i> Yeni Giriş
            </a>
        </div>

        <div style="display: flex; gap: 30px; margin-bottom: 40px; align-items: stretch; flex-wrap: wrap;">
            
            <div style="flex: 1; display: flex; flex-direction: column; gap: 20px; min-width: 200px;">
                <div class="stat-card">
                    <div class="card-icon" style="color: #fd79a8; font-size: 24px; margin-bottom: 10px;"><i class="fas fa-heartbeat"></i></div>
                    <h3 style="color: var(--muted-text); font-size: 16px;">Son Ruh Halin</h3>
                    <p style="font-size:24px; font-weight:bold; color: var(--text-color); margin: 5px 0;">
                        <?php echo !empty($kayitlar[0]['mood']) ? $kayitlar[0]['mood'] : '-'; ?>
                    </p>
                </div>

                <div class="stat-card" style="border: 2px solid <?php echo $agri_kart_border; ?>;">
                    <div class="card-icon" style="color: <?php echo ($son_agri_deger >= 8) ? '#d63031' : '#6c5ce7'; ?>; font-size: 24px; margin-bottom: 10px;">
                        <i class="fas <?php echo $agri_ikon; ?>"></i>
                    </div>
                    <h3 style="color: var(--muted-text); font-size: 16px;">Son Ağrı</h3>
                    <p style="font-size:24px; font-weight:bold; color: <?php echo $agri_kart_renk; ?>; margin: 5px 0;">
                        <?php echo ($son_agri_deger > 0) ? $son_agri_deger.'/10' : '-'; ?>
                    </p>
                    <?php if($son_agri_deger >= 8): ?>
                        <div style="font-size:11px; color:#d63031; font-weight:bold; margin-top:5px;">
                            <i class="fas fa-first-aid"></i> Dinlenmelisin!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="table-wrapper" style="flex: 3; padding: 25px; background: var(--card-bg); border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); min-height: 300px;">
                <h4 style="margin:0 0 20px; color:var(--muted-text);"><i class="fas fa-chart-area" style="color:#ff9a9e;"></i> Ağrı Geçmişi</h4>
                <div style="position: relative; height: 250px; width: 100%;">
                    <canvas id="myChart"></canvas>
                </div>
            </div>
        </div>

        <div class="table-wrapper">
            <div class="section-title" style="font-size: 20px; font-weight: 700; color: var(--text-color); margin-bottom: 15px;">
                📋 Geçmiş Kayıtların
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 20%;">TARİH</th>
                        <th>RUH HALİ</th>
                        <th>AĞRI</th>
                        <th>BELİRTİLER</th>
                        <th>NOTLAR</th>
                        <th>FOTO</th>
                        <th>İŞLEM</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($kayitlar) > 0): ?>
                        <?php foreach ($kayitlar as $kayit): ?>
                            <tr>
                                <td>
                                    <?php 
                                        $baslangic = date("d.m.Y", strtotime($kayit['start_date']));
                                        if (!empty($kayit['end_date']) && $kayit['end_date'] != '0000-00-00') {
                                            $bitis = date("d.m.Y", strtotime($kayit['end_date']));
                                            echo "<div style='font-weight:700; color:var(--text-color);'>$baslangic</div>";
                                            echo "<div style='font-size:12px; color:var(--muted-text);'>⬇ $bitis</div>";
                                        } else {
                                            echo "<div style='font-weight:700;'>$baslangic</div><div style='font-size:12px; color:#ff7675; font-weight:bold;'>Devam Ediyor</div>";
                                        }
                                    ?>
                                </td>
                                
                                <td>
                                    <span style="background:#fff0f3; color:#e84393; padding:5px 15px; border-radius:20px; font-size:13px; font-weight:600;">
                                        <?php echo htmlspecialchars($kayit['mood']); ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <div style="width: 30px; height: 30px; border-radius: 50%; background: <?php echo ($kayit['pain_level'] >= 8) ? '#d63031' : (($kayit['pain_level'] > 4) ? '#fdcb6e' : '#55efc4'); ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px;">
                                        <?php echo $kayit['pain_level']; ?>
                                    </div>
                                </td>

                                <td style="font-size: 13px; color: var(--muted-text); max-width: 150px;">
                                    <?php echo !empty($kayit['symptoms']) ? htmlspecialchars($kayit['symptoms']) : '-'; ?>
                                </td>

                                <td style="font-size: 13px; color: var(--muted-text);">
                                    <?php echo htmlspecialchars(mb_strimwidth($kayit['notes'], 0, 20, "...")); ?>
                                </td>

                                <td>
                                    <?php if (!empty($kayit['photo_path'])): ?>
                                        <a href="<?php echo $kayit['photo_path']; ?>" target="_blank" style="color:#e84393; font-size: 18px;">
                                            <i class="fas fa-image"></i>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--muted-text);">-</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <a href="edit_period.php?id=<?php echo $kayit['id']; ?>" style="color:#74b9ff; font-size:16px; margin-right: 15px;" title="Düzenle">
                                        <i class="fas fa-pen"></i>
                                    </a>

                                    <a href="delete_period.php?id=<?php echo $kayit['id']; ?>" onclick="return confirm('Silmek istiyor musun?');" style="color:#fab1a0; font-size:16px;" title="Sil">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding: 50px 20px; color: var(--muted-text);">
                                <div style="font-size: 50px; color: #ffeaa7; margin-bottom: 15px;">
                                    <i class="fas fa-seedling"></i>
                                </div>
                                <div style="font-size: 16px; font-weight: 600; color: var(--text-color);">
                                    Henüz kayıt yok, ilk kaydını ekle 🌸
                                </div>
                                <div style="font-size: 13px; margin-top: 5px;">
                                    "Yeni Giriş" butonuna tıklayarak başlayabilirsin.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('myChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($grafik_labels); ?>,
                datasets: [{
                    label: 'Ağrı Seviyesi', 
                    data: <?php echo json_encode($grafik_data); ?>,
                    borderColor: '#ff9a9e', 
                    backgroundColor: 'rgba(255, 154, 158, 0.2)', 
                    fill: true, 
                    tension: 0.4,
                    pointBackgroundColor: '#e84393',
                    pointRadius: 4
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                scales: { 
                    y: { beginAtZero: true, max: 10, ticks: { stepSize: 1, color: '#b2bec3' } }, 
                    x: { grid: { display: false }, ticks: { color: '#b2bec3' } } 
                }, 
                plugins: { legend: { display: false } } 
            }
        });
    </script>
</body>
</html>