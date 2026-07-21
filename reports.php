<?php
// Hata Raporlama (Geliştirme aşamasında açık kalsın, canlıya alınca kapatırsın)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'db.php';

// Giriş Kontrolü
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
$user_id = $_SESSION['user_id'];

// --- 1. ÜST KARTLAR İÇİN VERİLER ---

// A) Ortalama Ağrı
$stmt = $db->prepare("SELECT AVG(pain_level) as avg_pain FROM periods WHERE user_id = ?");
$stmt->execute([$user_id]);
$avg_pain = $stmt->fetch(PDO::FETCH_ASSOC)['avg_pain'];
$avg_pain = $avg_pain ? number_format($avg_pain, 1) : 0;

// B) En Sık Ruh Hali (Mod)
$stmt = $db->prepare("SELECT mood, COUNT(*) as sayi FROM periods WHERE user_id = ? AND mood IS NOT NULL GROUP BY mood ORDER BY sayi DESC LIMIT 1");
$stmt->execute([$user_id]);
$top_mood_data = $stmt->fetch(PDO::FETCH_ASSOC);
$top_mood = $top_mood_data ? $top_mood_data['mood'] : 'Veri Yok';

// C) Ortalama Regl Kanama Süresi (Bitiş - Başlangıç)
$stmt = $db->prepare("SELECT AVG(DATEDIFF(end_date, start_date) + 1) as avg_duration FROM periods WHERE user_id = ? AND end_date IS NOT NULL AND end_date != '0000-00-00'");
$stmt->execute([$user_id]);
$avg_duration = $stmt->fetch(PDO::FETCH_ASSOC)['avg_duration'];
$avg_duration = $avg_duration ? round($avg_duration) : 0;


// --- 2. DÜZENSİZLİK ANALİZİ (CYCLE VARIABILITY) ---
// Son 4 başlangıç tarihini çekiyoruz (3 döngü aralığı hesaplamak için)
$stmt = $db->prepare("SELECT start_date FROM periods WHERE user_id = ? ORDER BY start_date DESC LIMIT 4");
$stmt->execute([$user_id]);
$dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

$cycle_diffs = [];
$analiz_mesaji = "";
$analiz_renk = "info"; // Varsayılan renk (mavi)

if (count($dates) >= 3) {
    // Döngü günlerini hesapla (Örn: 28 gün, 32 gün)
    for ($i = 0; $i < count($dates) - 1; $i++) {
        $d1 = new DateTime($dates[$i]); // Yeni tarih
        $d2 = new DateTime($dates[$i+1]); // Eski tarih
        $diff = $d1->diff($d2)->days;
        $cycle_diffs[] = $diff;
    }

    $max_cycle = max($cycle_diffs);
    $min_cycle = min($cycle_diffs);
    $variation = $max_cycle - $min_cycle; // En uzun ve en kısa döngü arasındaki fark

    // Analiz Mantığı
    if ($variation > 7) {
        $analiz_renk = "warning"; // Sarı
        $analiz_mesaji = "⚠️ <strong>Dikkat:</strong> Son 3 ayda döngü süren <strong>$min_cycle ile $max_cycle gün</strong> arasında değişkenlik göstermiş. ($variation gün fark). Stres veya mevsim geçişleri bunu etkiliyor olabilir.";
    } else {
        $analiz_renk = "success"; // Yeşil
        $analiz_mesaji = "✅ <strong>Harika!</strong> Döngülerin son aylarda oldukça düzenli ilerliyor. (Ortalama $max_cycle günde bir).";
    }
} else {
    $analiz_mesaji = "💡 Döngü analizi yapabilmem için en az 3 adet regl kaydı girmelisin. Veri girdikçe burası güncellenecek! 🌸";
}


// --- 3. GRAFİK VERİLERİ (CHARTS) ---

// A) Ruh Hali Pastası
$moodLabels = []; $moodCounts = [];
$moodSql = $db->prepare("SELECT mood, COUNT(*) as sayi FROM periods WHERE user_id = ? AND mood IS NOT NULL GROUP BY mood");
$moodSql->execute([$user_id]);
foreach($moodSql->fetchAll(PDO::FETCH_ASSOC) as $m) {
    $moodLabels[] = $m['mood']; 
    $moodCounts[] = (int)$m['sayi']; 
}

// B) Belirtiler (Virgülle ayrılmış verileri çözme)
$symLabels = []; $symValues = [];
$symStmt = $db->prepare("SELECT symptoms FROM periods WHERE user_id = ? AND symptoms IS NOT NULL AND symptoms != ''");
$symStmt->execute([$user_id]);
$allSymptoms = $symStmt->fetchAll(PDO::FETCH_COLUMN);
$symptomCounts = [];

foreach ($allSymptoms as $s_str) {
    $list = explode(', ', $s_str); 
    foreach ($list as $item) {
        $item = trim($item);
        if (!empty($item)) {
            if (!isset($symptomCounts[$item])) $symptomCounts[$item] = 0;
            $symptomCounts[$item]++;
        }
    }
}
arsort($symptomCounts); // En çoktan aza sırala
$symLabels = array_keys($symptomCounts);
$symValues = array_values($symptomCounts);

// C) Regl Süresi Grafiği (Son 10 kayıt)
$durationLabels = []; $durationData = [];
$perSql = $db->prepare("SELECT start_date, end_date FROM periods WHERE user_id = ? AND end_date IS NOT NULL ORDER BY start_date ASC LIMIT 10");
$perSql->execute([$user_id]);
foreach($perSql->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $start = new DateTime($p['start_date']);
    $end = new DateTime($p['end_date']);
    $diff = $start->diff($end)->days + 1;
    
    $durationLabels[] = $start->format('d M'); 
    $durationData[] = $diff;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Raporlar - Regl Takip</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body { font-family: 'Quicksand', sans-serif !important; background-color: #fdfbfd; }
        .pacifico-font { font-family: 'Pacifico', cursive; }

        /* Kart Tasarımları */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); text-align: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-value { font-size: 32px; font-weight: 700; color: #e84393; margin: 10px 0; }
        .stat-label { color: #636e72; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }

        /* Uyarı / Analiz Kutusu */
        .insight-box { padding: 20px 25px; border-radius: 15px; margin-bottom: 30px; display: flex; align-items: center; gap: 20px; font-size: 15px; line-height: 1.6; }
        .insight-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .insight-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .insight-info    { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }

        /* Grafik Alanları */
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 30px; margin-bottom: 30px; }
        .chart-box { background: white; padding: 25px; border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); min-height: 350px; }
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
            <a href="add_period.php" class="menu-item"><i class="fas fa-plus-circle"></i> Kayıt Ekle</a>
            <a href="diary.php" class="menu-item"><i class="fas fa-book-open"></i> Günlüğüm</a>
            <a href="reports.php" class="menu-item active"><i class="fas fa-chart-line"></i> Raporlar</a>
            <div style="margin: 20px 0; border-top: 1px solid #f0f0f0;"></div>
            <a href="profile.php" class="menu-item"><i class="fas fa-user-cog"></i> Profil</a>
            <a href="logout.php" class="menu-item" style="color:#e85a71;"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
        </nav>
    </div>

    <div class="main-content">
        
        <div class="header-welcome" style="margin-bottom:25px;">
            <h1 class="pacifico-font" style="color:#2d3436; font-size: 32px;">Vücut Analiz Raporun 📊</h1>
            <p style="color:#636e72;">Kayıtlarına göre senin için hazırladığımız özet.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div style="font-size: 28px; margin-bottom:10px;">🔥</div>
                <div class="stat-label">ORTALAMA AĞRI</div>
                <div class="stat-value" style="color: <?php echo ($avg_pain > 6 ? '#ff7675' : '#e84393'); ?>">
                    <?php echo $avg_pain; ?>/10
                </div>
            </div>

            <div class="stat-card">
                <div style="font-size: 28px; margin-bottom:10px;">🎭</div>
                <div class="stat-label">EN SIK RUH HALİ</div>
                <div class="stat-value" style="font-size:24px;">
                    <?php echo htmlspecialchars($top_mood); ?>
                </div>
            </div>

            <div class="stat-card">
                <div style="font-size: 28px; margin-bottom:10px;">⏳</div>
                <div class="stat-label">ORT. REGL SÜRESİ</div>
                <div class="stat-value"><?php echo $avg_duration; ?> Gün</div>
            </div>
        </div>

        <div class="insight-box <?php echo 'insight-'.$analiz_renk; ?>">
            <i class="fas fa-info-circle" style="font-size:26px;"></i>
            <div>
                <?php echo $analiz_mesaji; ?>
            </div>
        </div>

        <div class="charts-grid">
            
            <div class="chart-box">
                <h3 style="color:#636e72; margin-bottom:20px; text-align:center; font-size:16px;">🧠 Ruh Hali Dağılımı</h3>
                <div style="height: 250px;">
                    <canvas id="moodChart"></canvas>
                </div>
            </div>

            <div class="chart-box">
                <h3 style="color:#636e72; margin-bottom:20px; text-align:center; font-size:16px;">🤕 En Sık Görülen Belirtiler</h3>
                <div style="height: 250px;">
                    <canvas id="symptomChart"></canvas>
                </div>
            </div>

            <div class="chart-box">
                <h3 style="color:#636e72; margin-bottom:20px; text-align:center; font-size:16px;">📅 Regl Süresi (Gün)</h3>
                <div style="height: 250px;">
                    <canvas id="durationChart"></canvas>
                </div>
            </div>
            
        </div>
    </div>

    <script>
        // PHP verilerini JS'ye aktar
        const moodLabels = <?php echo json_encode($moodLabels); ?>;
        const moodData = <?php echo json_encode($moodCounts); ?>;
        const symLabels = <?php echo json_encode($symLabels); ?>;
        const symValues = <?php echo json_encode($symValues); ?>;
        const durationLabels = <?php echo json_encode($durationLabels); ?>;
        const durationData = <?php echo json_encode($durationData); ?>;

        // 1. MOOD CHART
        const ctxMood = document.getElementById('moodChart').getContext('2d');
        if (moodData.length > 0) {
            new Chart(ctxMood, {
                type: 'doughnut',
                data: {
                    labels: moodLabels,
                    datasets: [{
                        data: moodData,
                        backgroundColor: ['#ff9a9e', '#a18cd1', '#fad0c4', '#fbc2eb', '#84fab0'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } }
                }
            });
        } else { showNoData(ctxMood, "Veri Yok"); }

        // 2. SYMPTOM CHART
        const ctxSym = document.getElementById('symptomChart').getContext('2d');
        if (symValues.length > 0) {
            new Chart(ctxSym, {
                type: 'bar',
                data: {
                    labels: symLabels,
                    datasets: [{
                        label: 'Kez Görüldü',
                        data: symValues,
                        backgroundColor: '#fab1a0',
                        borderRadius: 6
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                    plugins: { legend: { display: false } }
                }
            });
        } else { showNoData(ctxSym, "Belirti Yok"); }

        // 3. DURATION CHART
        const ctxDur = document.getElementById('durationChart').getContext('2d');
        if (durationData.length > 0) {
            new Chart(ctxDur, {
                type: 'bar',
                data: {
                    labels: durationLabels,
                    datasets: [{
                        label: 'Gün Süresi',
                        data: durationData,
                        backgroundColor: '#74b9ff',
                        borderRadius: 6
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    scales: { y: { beginAtZero: true } },
                    plugins: { legend: { display: false } }
                }
            });
        } else { showNoData(ctxDur, "Veri Yok"); }

        function showNoData(ctx, msg) {
            ctx.font = "14px Quicksand";
            ctx.fillStyle = "#b2bec3";
            ctx.textAlign = "center";
            ctx.fillText(msg, ctx.canvas.width/2, ctx.canvas.height/2);
        }
    </script>
</body>
</html>