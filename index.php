<?php
session_start();
require 'db.php';

// Zaten giriş yapıldıysa panele at
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$hata = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']); // Username yerine Email alıyoruz
    $password = $_POST['password'];

    // Veritabanında E-POSTA'yı ara
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Kullanıcı var mı ve Şifre doğru mu?
    if ($user && password_verify($password, $user['password'])) {
        // Giriş Başarılı! Oturum verilerini sakla
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name']; // İsim bilgisini sakla
        $_SESSION['user_email'] = $user['email'];

        header("Location: dashboard.php");
        exit();
    } else {
        $hata = "E-posta adresi veya şifre hatalı. Lütfen tekrar dene. 🌸";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Giriş Yap - Regl Takip</title>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Modern ve Soft Tasarım CSS */
        body {
            font-family: 'Quicksand', sans-serif;
            background-color: #ffeaa7;
            background-image: linear-gradient(315deg, #ffeaa7 0%, #ff748b 74%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }

        .auth-card {
            background: white;
            width: 800px;
            height: 500px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            display: flex;
            overflow: hidden;
        }

        /* Sol Taraf (Görsel Alan) */
        .auth-visual {
            flex: 1;
            background: linear-gradient(135deg, #e84393, #ff7675);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px;
            position: relative;
        }
        
        .auth-visual i { font-size: 60px; margin-bottom: 20px; opacity: 0.9; }
        .auth-visual h2 { font-size: 28px; margin-bottom: 10px; font-weight: 700; }
        .auth-visual p { font-size: 16px; line-height: 1.6; opacity: 0.9; font-style: italic; }

        /* Sağ Taraf (Form Alanı) */
        .auth-form-side {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        h3 { color: #2d3436; font-size: 24px; margin-bottom: 30px; text-align: center; }

        .input-wrapper {
            position: relative;
            margin-bottom: 20px;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 15px 15px 15px 45px; /* İkon için soldan boşluk */
            border: 2px solid #dfe6e9;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Quicksand', sans-serif;
            box-sizing: border-box;
            transition: 0.3s;
            outline: none;
        }
        
        .input-wrapper input:focus { border-color: #e84393; background: #fffdfd; }
        
        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #b2bec3;
            transition: 0.3s;
        }

        .input-wrapper input:focus + i { color: #e84393; }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #e84393;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(232, 67, 147, 0.2);
        }
        .btn-submit:hover { background: #d63031; transform: translateY(-2px); }

        .error-msg {
            background: #ffe6e6; color: #d63031;
            padding: 12px; border-radius: 10px;
            font-size: 13px; text-align: center; margin-bottom: 20px;
            border: 1px solid #ffcccc;
        }

        .bottom-text { text-align: center; margin-top: 25px; font-size: 13px; color: #636e72; }
        .bottom-text a { color: #e84393; text-decoration: none; font-weight: bold; }
        .bottom-text a:hover { text-decoration: underline; }

        /* Mobil Uyum */
        @media (max-width: 768px) {
            .auth-card { flex-direction: column; width: 90%; height: auto; }
            .auth-visual { padding: 30px; display: none; } /* Mobilde görseli gizle */
            .auth-form-side { padding: 30px; }
        }
    </style>
</head>
<body class="auth-page">

    <div class="auth-card">
        
        <div class="auth-visual">
            <i class="fas fa-spa"></i>
            <h2>Kendine İyi Bak</h2>
            <p>"Vücudunu dinle, döngünü takip et.<br>Sağlığın senin en değerli hazinen."</p>
        </div>

        <div class="auth-form-side">
            <h3>Tekrar Hoş Geldin! 👋</h3>
            
            <?php if($hata): ?>
                <div class="error-msg">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $hata; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="input-wrapper">
                    <input type="email" name="email" placeholder="E-posta Adresin (Gmail)" required autocomplete="off">
                    <i class="fas fa-envelope"></i>
                </div>

                <div class="input-wrapper">
                    <input type="password" name="password" placeholder="Şifren" required>
                    <i class="fas fa-lock"></i>
                </div>

                <button type="submit" class="btn-submit">GİRİŞ YAP</button>
            </form>

            <div class="bottom-text">
                Hesabın yok mu? <a href="register.php">Hemen Kayıt Ol</a>
            </div>
        </div>
    </div>

</body>
</html>