<?php
session_start();
require 'db.php';

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// --- AY ve YIL AYARLARI ---
// URL'den ay/yıl gelirse onları al, yoksa şu anki tarihi kullan
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Önceki ve Sonraki ay butonları için hesaplama
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// --- VERİTABANI VERİLERİNİ ÇEK ---
// 1. Kullanıcı döngü bilgileri
$uStmt = $db->prepare("SELECT cycle_length, period_duration FROM users WHERE id = ?");
$uStmt->execute([$user_id]);
$uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
$cycle_days = ($uRow && $uRow['cycle_length']) ? $uRow['cycle_length'] : 28;
$duration = ($uRow && $uRow['period_duration']) ? $uRow['period_duration'] : 5;

// 2. Kayıtlı regl dönemlerini çek
$stmt = $db->prepare("SELECT start_date, end_date FROM periods WHERE user_id = ?");
$stmt->execute([$user_id]);
$periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- GÜNLERİ İŞARETLEME MANTIĞI ---
$marked_days = []; // Hangi gün ne renk olacak?

// A) Geçmiş Kayıtları İşle (KOYU RENK)
foreach ($periods as $p) {
    $start = new DateTime($p['start_date']);
    // Bitiş tarihi yoksa varsayılan süreyi ekle
    if ($p['end_date'] && $p['end_date'] != '0000-00-00') {
        $end = new DateTime($p['end_date']);
    } else {
        $end = clone $start;
        $end->modify("+" . ($duration - 1) . " days");
    }

    // Aradaki günleri doldur
    while ($start <= $end) {
        $marked_days[$start->format('Y-m-d')] = 'real'; // 'real' = gerçek kayıt
        $start->modify('+1 day');
    }
}

// B) Tahminleri İşle (AÇIK RENK)
// En son kaydı bulup geleceğe doğru tahmin yürütelim
if (count($periods) > 0) {
    // Tarihe göre sırala ki en sonuncuyu bulalım
    usort($periods, function($a, $b) {
        return strtotime($b['start_date']) - strtotime($a['start_date']);
    });
    
    $last_start = new DateTime($periods[0]['start_date']);
    
    // Gelecek 12 ay için tahmin üret
    for ($i = 0; $i < 12; $i++) {
        $last_start->modify("+$cycle_days days"); // Döngü kadar ileri git
        
        $temp_start = clone $last_start;
        // Tahmini bitiş
        $temp_end = clone $temp_start;
        $temp_end->modify("+" . ($duration - 1) . " days");
        
        // Tahmin günlerini diziye ekle (eğer zaten gerçek kayıt yoksa)
        while ($temp_start <= $temp_end) {
            $key = $temp_start->format('Y-m-d');
            if (!isset($marked_days[$key])) {
                $marked_days[$key] = 'predicted';
            }
            $temp_start->modify('+1 day');
        }
    }
}

// --- TAKVİM ÇİZİM HAZIRLIĞI ---
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDayName = date('w', strtotime("$year-$month-01")); // 0=Pazar, 1=Ptesi...
// Pazar(0) ise onu en sona (7. sıraya) atmak için, çünkü takvimimiz Ptesi başlasın istiyoruz:
// Ptesi=1, ... Pazar=7 olsun.
$startDayOffset = ($firstDayName == 0) ? 6 : $firstDayName - 1;

// Ay İsimleri Türkçeleştirme
$aylar = [
    1=>'Ocak', 2=>'Şubat', 3=>'Mart', 4=>'Nisan', 5=>'Mayıs', 6=>'Haziran',
    7=>'Temmuz', 8=>'Ağustos', 9=>'Eylül', 10=>'Ekim', 11=>'Kasım', 12=>'Aralık'
];
?>

<!DOCTYPE html>
<html lang="tr" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <title>Takvim - Regl Takip</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Quicksand', sans-serif !important; background-color: #fdfbfd; }
        .pacifico-font { font-family: 'Pacifico', cursive; }
        
        /* Takvim Özel Stilleri */
        .calendar-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .month-title {
            font-size: 28px;
            font-weight: 700;
            color: #e84393;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-nav {
            background: #fff0f3;
            border: none;
            color: #e84393;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            transition: 0.3s;
        }
        .btn-nav:hover { background: #e84393; color: white; }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }

        .day-name {
            text-align: center;
            font-weight: 700;
            color: #b2bec3;
            padding-bottom: 10px;
            font-size: 14px;
        }

        .day-cell {
            height: 80px; /* Kutucuk yüksekliği */
            border-radius: 12px;
            border: 1px solid #f1f2f6;
            padding: 10px;
            position: relative;
            background: #fff;
            transition: 0.2s;
        }
        
        .day-cell:hover { border-color: #fab1a0; }

        .day-number {
            font-weight: 600;
            color: #2d3436;
        }

        /* GERÇEK REGL GÜNÜ (Koyu Pembe) */
        .day-real {
            background: #e84393;
            border-color: #e84393;
            color: white !important;
            box-shadow: 0 4px 10px rgba(232, 67, 147, 0.4);
        }
        .day-real .day-number { color: white; }

        /* TAHMİNİ REGL GÜNÜ (Açık/Soluk Pembe) */
        .day-predicted {
            background: repeating-linear-gradient(
                45deg,
                #ffeaa7,
                #ffeaa7 10px,
                #fffdcb 10px,
                #fffdcb 20px
            );
            border: 2px dashed #fdcb6e;
        }
        /* Tahmin için ikon */
        .status-icon { position: absolute; bottom: 8px; right: 8px; font-size: 14px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand" style="font-size: 24px; color: #e84393; font-family: 'Quicksand', sans-serif; font-weight:700;">
            <i class="fas fa-leaf"></i> ReglTakip
        </div>
        <nav>
            <a href="dashboard.php" class="menu-item"><i class="fas fa-th-large"></i> Genel Bakış</a>
            <a href="calendar.php" class="menu-item active"><i class="fas fa-calendar-alt"></i> Takvim</a>
            
            <a href="add_period.php" class="menu-item"><i class="fas fa-plus-circle"></i> Kayıt Ekle</a>
            <a href="diary.php" class="menu-item"><i class="fas fa-book-open"></i> Günlüğüm</a>
            <a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i> Raporlar</a>
            <div style="margin: 20px 0; border-top: 1px solid #f0f0f0;"></div>
            <a href="profile.php" class="menu-item"><i class="fas fa-user-cog"></i> Profil</a>
            <a href="logout.php" class="menu-item" style="color:#e85a71;"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
        </nav>
    </div>

    <div class="main-content">
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 class="pacifico-font" style="color:#2d3436; font-size:32px;">Takvimim 📅</h2>
        </div>

        <div class="calendar-container">
            
            <div class="calendar-header">
                <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="btn-nav">
                    <i class="fas fa-chevron-left" style="line-height:40px;"></i>
                </a>
                
                <div class="month-title">
                    <?php echo $aylar[(int)$month] . " " . $year; ?>
                </div>
                
                <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="btn-nav">
                    <i class="fas fa-chevron-right" style="line-height:40px;"></i>
                </a>
            </div>

            <div class="calendar-grid">
                <div class="day-name">Pazartesi</div>
                <div class="day-name">Salı</div>
                <div class="day-name">Çarşamba</div>
                <div class="day-name">Perşembe</div>
                <div class="day-name">Cuma</div>
                <div class="day-name">Cumartesi</div>
                <div class="day-name">Pazar</div>

                <?php
                for ($k = 0; $k < $startDayOffset; $k++) {
                    echo "<div></div>";
                }

                // GÜNLERİ DÖNGÜYE AL
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    // Şu anki günün tam tarihi (Örn: 2025-12-25)
                    $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    
                    // Sınıf belirleme (Gelen veriye göre)
                    $extraClass = '';
                    $icon = '';
                    
                    if (isset($marked_days[$currentDate])) {
                        if ($marked_days[$currentDate] == 'real') {
                            $extraClass = 'day-real';
                            $icon = '<i class="fas fa-tint status-icon"></i>';
                        } elseif ($marked_days[$currentDate] == 'predicted') {
                            $extraClass = 'day-predicted';
                            $icon = '<i class="far fa-clock status-icon" style="color:#fdcb6e;"></i>';
                        }
                    }

                    // BUGÜN İSE ÇERÇEVELE
                    $isToday = ($currentDate == date('Y-m-d')) ? 'border: 2px solid #0984e3;' : '';

                    echo "<div class='day-cell $extraClass' style='$isToday'>";
                    echo "<span class='day-number'>$day</span>";
                    echo $icon;
                    echo "</div>";
                }
                ?>
            </div>
            
            <div style="margin-top: 30px; display:flex; gap:20px; justify-content:center; font-size:14px; color:#636e72;">
                <div style="display:flex; align-items:center; gap:5px;">
                    <div style="width:15px; height:15px; background:#e84393; border-radius:3px;"></div> Gerçek Regl
                </div>
                <div style="display:flex; align-items:center; gap:5px;">
                    <div style="width:15px; height:15px; background:#ffeaa7; border:1px dashed #fdcb6e; border-radius:3px;"></div> Tahmini Regl
                </div>
                <div style="display:flex; align-items:center; gap:5px;">
                    <div style="width:15px; height:15px; border:2px solid #0984e3; border-radius:3px;"></div> Bugün
                </div>
            </div>

        </div>
    </div>

</body>
</html>