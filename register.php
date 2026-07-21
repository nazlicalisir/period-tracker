<?php
require 'db.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // --- 1. GMAIL KONTROLÜ ---
    // E-posta @gmail.com ile bitmiyorsa hata ver
    if (!preg_match("/@gmail\.com$/", $email)) {
        $error = "Üzgünüz, kayıt olmak için sadece @gmail.com adresi kullanabilirsin.";
    }
    // --- 2. ŞİFRE GÜVENLİK KONTROLÜ ---
    elseif (strlen($password) < 6) {
        $error = "Şifren çok kısa! En az 6 karakter olmalı.";
    }
    elseif (!preg_match("/[A-Z]/", $password)) {
        $error = "Şifrende en az bir adet BÜYÜK harf olmalı.";
    }
    elseif (!preg_match("/[0-9]/", $password)) {
        $error = "Şifrende en az bir adet RAKAM olmalı.";
    }
    else {
        // --- 3. E-POSTA VAR MI KONTROLÜ ---
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        
        if ($check->rowCount() > 0) {
            $error = "Bu e-posta adresi zaten kullanılıyor. Giriş yapmayı dene!";
        } else {
            // --- 4. KAYIT İŞLEMİ ---
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // cycle_duration (döngü süresi) varsayılan olarak 28 gün ekleniyor
            $sql = "INSERT INTO users (name, email, password, cycle_duration) VALUES (?, ?, ?, 28)";
            $stmt = $db->prepare($sql);
            
            if ($stmt->execute([$name, $email, $hashedPassword])) {
                $success = "Harika! Kaydın başarıyla oluşturuldu. Yönlendiriliyorsun...";
            } else {
                $error = "Bir hata oluştu, lütfen tekrar dene.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kayıt Ol - Regl Takip</title>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Quicksand', sans-serif;
            background-color: #ffeaa7;
            background-image: linear-gradient(315deg, #ffeaa7 0%, #ff748b 74%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .login-card {
            background: white;
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            width: 380px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        /* Başlık Stili */
        h2 { 
            color: #e84393; 
            margin-bottom: 25px; 
            font-weight: 700;
            font-size: 26px;
        }
        
        /* Input Alanları */
        .input-group { 
            position: relative; 
            margin-bottom: 20px; 
            text-align: left; 
        }
        
        .input-group label { 
            font-size: 13px; 
            color: #636e72; 
            font-weight: 600; 
            margin-left: 10px; 
            display: block;
            margin-bottom: 5px;
        }
        
        .input-group input {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #dfe6e9;
            border-radius: 12px;
            box-sizing: border-box;
            font-family: 'Quicksand', sans-serif;
            font-size: 15px;
            transition: all 0.3s ease;
            outline: none;
            background: #fdfbfd;
        }
        
        /* Inputa tıklayınca olan efekt */
        .input-group input:focus { 
            border-color: #e84393; 
            background: #fff;
            box-shadow: 0 0 0 4px rgba(232, 67, 147, 0.1); 
        }
        
        /* Buton Stili */
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(45deg, #e84393, #ff7675);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 5px 15px rgba(232, 67, 147, 0.3);
        }
        .btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 20px rgba(232, 67, 147, 0.4);
        }
        
        /* Hata ve Başarı Mesajları */
        .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 600; }
        .alert-error { background: #ffe6e6; color: #d63031; border: 1px solid #ffcccc; }
        .alert-success { background: #e3f9e5; color: #27ae60; border: 1px solid #c3e6cb; }

        /* CANLI ŞİFRE KURALLARI LİSTESİ */
        .pass-rules { 
            list-style: none; 
            padding: 0; 
            margin: 0 0 20px 0; 
            text-align: left;
            padding-left: 5px;
        }
        
        .pass-rules li { 
            font-size: 12px; 
            color: #b2bec3; 
            margin-bottom: 4px; 
            transition: color 0.3s; 
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Kurallar Sağlandığında */
        .valid { color: #00b894 !important; font-weight: bold; } /* Yeşil */
        .invalid { color: #b2bec3; } /* Gri (Pasif) */
        
        .valid i { content: "\f058"; } /* Check işareti fontawesome */
        
    </style>
</head>
<body>

    <div class="login-card">
        <h2><i class="fas fa-venus" style="margin-right:10px;"></i>Aramıza Katıl</h2>
        
        <?php if($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <script>setTimeout(function(){ window.location.href = 'index.php'; }, 2000);</script>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>İsim Soyisim</label>
                <input type="text" name="name" placeholder="Örn: Ayşe Yılmaz" required autocomplete="off">
            </div>
            
            <div class="input-group">
                <label>E-posta (Sadece Gmail)</label>
                <input type="email" name="email" placeholder="ornek@gmail.com" required autocomplete="off">
            </div>
            
            <div class="input-group">
                <label>Şifre Oluştur</label>
                <input type="password" name="password" id="passInput" placeholder="******" required>
            </div>
            
            <ul class="pass-rules">
                <li id="rule-length"><i class="fas fa-circle" style="font-size:6px;"></i> En az 6 karakter</li>
                <li id="rule-upper"><i class="fas fa-circle" style="font-size:6px;"></i> En az 1 BÜYÜK harf</li>
                <li id="rule-number"><i class="fas fa-circle" style="font-size:6px;"></i> En az 1 Rakam</li>
            </ul>

            <button type="submit" class="btn">Kayıt Ol</button>
        </form>
        
        <p style="font-size:13px; margin-top:25px; color:#636e72;">
            Zaten hesabın var mı? <br>
            <a href="index.php" style="color:#e84393; text-decoration:none; font-weight:bold; font-size:15px;">Giriş Yap &rarr;</a>
        </p>
    </div>

    <script>
        const passInput = document.getElementById('passInput');
        const ruleLength = document.getElementById('rule-length');
        const ruleUpper = document.getElementById('rule-upper');
        const ruleNumber = document.getElementById('rule-number');

        passInput.addEventListener('input', function() {
            const val = passInput.value;

            // 1. Uzunluk Kontrolü (6 karakter)
            if (val.length >= 6) {
                ruleLength.className = 'valid';
                ruleLength.innerHTML = '<i class="fas fa-check"></i> En az 6 karakter';
            } else {
                ruleLength.className = 'invalid';
                ruleLength.innerHTML = '<i class="fas fa-circle" style="font-size:6px;"></i> En az 6 karakter';
            }

            // 2. Büyük Harf Kontrolü
            if (/[A-Z]/.test(val)) {
                ruleUpper.className = 'valid';
                ruleUpper.innerHTML = '<i class="fas fa-check"></i> En az 1 BÜYÜK harf';
            } else {
                ruleUpper.className = 'invalid';
                ruleUpper.innerHTML = '<i class="fas fa-circle" style="font-size:6px;"></i> En az 1 BÜYÜK harf';
            }

            // 3. Rakam Kontrolü
            if (/[0-9]/.test(val)) {
                ruleNumber.className = 'valid';
                ruleNumber.innerHTML = '<i class="fas fa-check"></i> En az 1 Rakam';
            } else {
                ruleNumber.className = 'invalid';
                ruleNumber.innerHTML = '<i class="fas fa-circle" style="font-size:6px;"></i> En az 1 Rakam';
            }
        });
    </script>
</body>
</html>