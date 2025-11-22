<?php
// sifre-sifirla.php - Şifre Sıfırlama Sistemi
session_start();

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Basit config dosyası
define('DB_HOST', 'localhost');
define('DB_NAME', 'onvibes_online_barisha');
define('DB_USER', 'onvib_barisha');
define('DB_PASS', '!Cpc8zP2?pSvaev1');

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$error = '';
$success = '';
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Şifre sıfırlama başvurusu
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);
    
    if(empty($email)) {
        $error = 'Lütfen e-posta adresinizi girin!';
    } else {
        try {
            // Kullanıcıyı veritabanında ara
            $query = "SELECT id, username, email FROM users WHERE email = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($user) {
                // Benzersiz token oluştur
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $created_at = date('Y-m-d H:i:s');
                
                // Önce tabloyu kontrol et
                $check_table = $db->query("SHOW TABLES LIKE 'password_resets'");
                if($check_table->rowCount() == 0) {
                    // Tablo yoksa oluştur (MySQL uyumlu)
                    $create_table = "CREATE TABLE password_resets (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        token VARCHAR(64) NOT NULL UNIQUE,
                        expires_at DATETIME NOT NULL,
                        used TINYINT DEFAULT 0,
                        created_at DATETIME
                    )";
                    $db->exec($create_table);
                }
                
                // Eski token'ları temizle
                $clean_query = "DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()";
                $clean_stmt = $db->prepare($clean_query);
                $clean_stmt->execute([$user['id']]);
                
                // Yeni token'ı kaydet
                $insert_query = "INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, ?)";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->execute([$user['id'], $token, $expires, $created_at]);
                
                // Admin'e e-posta gönder (simülasyon)
                $admin_email = "appbarisha@gmail.com";
                $subject = "Şifre Sıfırlama Başvurusu - ONVIBES";
                $message = "Yeni şifre sıfırlama başvurusu:\n\nKullanıcı: {$user['username']}\nE-posta: {$user['email']}\nToken: $token\n\nOnay URL: http://" . $_SERVER['HTTP_HOST'] . "/sifre-sifirla.php?token=$token";
                
                // E-posta gönderimini dene
                if(@mail($admin_email, $subject, $message)) {
                    $success = 'Şifre sıfırlama başvurunuz alındı. Admin onayından sonra e-posta adresinize talimatlar gönderilecektir.';
                } else {
                    $success = 'Şifre sıfırlama başvurunuz alındı. Admin onayı bekleniyor. Token: ' . $token;
                }
                
            } else {
                $error = 'Bu e-posta adresi ile kayıtlı kullanıcı bulunamadı!';
            }
        } catch(Exception $e) {
            $error = 'İşlem sırasında hata oluştu: ' . $e->getMessage();
        }
    }
}

// Token kontrolü
if(isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        // Token'ı kontrol et
        $query = "SELECT pr.*, u.username, u.email 
                  FROM password_resets pr 
                  JOIN users u ON pr.user_id = u.id 
                  WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0";
        $stmt = $db->prepare($query);
        $stmt->execute([$token]);
        $reset_request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$reset_request) {
            $error = 'Geçersiz veya süresi dolmuş şifre sıfırlama bağlantısı!';
        }
    } catch(Exception $e) {
        $error = 'Token kontrolü sırasında hata: ' . $e->getMessage();
    }
}

// Yeni şifre belirleme
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_new_password'])) {
    $token = $_POST['token'];
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if(empty($new_password) || empty($confirm_password)) {
        $error = 'Lütfen yeni şifrenizi girin!';
    } elseif($new_password !== $confirm_password) {
        $error = 'Şifreler eşleşmiyor!';
    } elseif(strlen($new_password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır!';
    } else {
        try {
            // Token'ı kontrol et
            $query = "SELECT pr.*, u.id as user_id 
                      FROM password_resets pr 
                      JOIN users u ON pr.user_id = u.id 
                      WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0";
            $stmt = $db->prepare($query);
            $stmt->execute([$token]);
            $reset_request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($reset_request) {
                // Şifreyi güncelle
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                
                if($update_stmt->execute([$hashed_password, $reset_request['user_id']])) {
                    // Token'ı kullanılmış olarak işaretle
                    $mark_used_query = "UPDATE password_resets SET used = 1 WHERE token = ?";
                    $mark_used_stmt = $db->prepare($mark_used_query);
                    $mark_used_stmt->execute([$token]);
                    
                    $success = 'Şifreniz başarıyla güncellendi! <a href="login.php" style="color: #d2232a; font-weight: bold;">Giriş yapmak için tıklayın</a>.';
                    $token = null;
                } else {
                    $error = 'Şifre güncelleme sırasında hata oluştu!';
                }
            } else {
                $error = 'Geçersiz veya süresi dolmuş şifre sıfırlama bağlantısı!';
            }
        } catch(Exception $e) {
            $error = 'Şifre güncelleme hatası: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırlama - ONVIBES</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --red: #d2232a;
            --dark: #2c3e50;
            --light: #ffffff;
            --border: #e0e0e0;
            --surface: #f8f9fa;
            --text: #333333;
            --gray: #666666;
            --green: #0a8c2f;
            --blue: #3498db;
            --orange: #e67e22;
        }

        .dark-mode {
            --red: #ff6b6b;
            --dark: #0a0a0a;
            --light: #1a1a1a;
            --border: #333333;
            --surface: #1a1a1a;
            --text: #f0f0f0;
            --gray: #a0a0a0;
            --green: #2ecc71;
            --blue: #3498db;
            --orange: #e67e22;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, var(--dark) 0%, var(--red) 100%);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .dark-mode body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Header */
        .top-bar {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 10px 0;
        }

        .top-bar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .logo-text {
            color: white;
            font-size: 22px;
            font-weight: 800;
            text-decoration: none;
        }

        .right-nav {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .right-nav button, .right-nav a {
            color: white;
            text-decoration: none;
            background: none;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .right-nav button:hover, .right-nav a:hover {
            background: rgba(255,255,255,0.2);
        }

        /* Main Content */
        .reset-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .reset-container {
            background: var(--light);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
        }

        .dark-mode .reset-container {
            background: var(--light);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .reset-header {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            padding: 2rem;
            color: white;
            text-align: center;
        }

        .reset-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .reset-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .reset-content {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text);
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            background: var(--surface);
            color: var(--text);
            transition: border-color 0.3s;
        }

        .dark-mode .form-input {
            background: var(--surface);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--red);
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background: var(--red);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .submit-btn:hover {
            background: #b81d24;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            border-left: 4px solid;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .form-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--gray);
        }

        .form-footer a {
            color: var(--red);
            text-decoration: none;
            font-weight: 600;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .token-info {
            background: var(--surface);
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border-left: 4px solid var(--blue);
            font-size: 14px;
            word-break: break-all;
        }

        .dark-mode .token-info {
            background: var(--surface);
        }

        /* Footer */
        footer {
            background: var(--dark);
            color: white;
            padding: 2rem 0;
            text-align: center;
        }

        @media (max-width: 768px) {
            .top-bar .container {
                flex-direction: column;
                gap: 10px;
            }

            .reset-container {
                margin: 1rem;
            }
            
            .reset-content {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <!-- Header -->
    <header>
        <div class="top-bar">
            <div class="container">
                <a href="index.php" class="logo-text">HABER|ONVIBES</a>
                <div class="right-nav">
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="toggle_theme">
                            <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                            <span><?php echo $dark_mode ? 'Açık' : 'Koyu'; ?></span>
                        </button>
                    </form>
                    <a href="login.php">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Giriş Yap</span>
                    </a>
                    <a href="index.php">
                        <i class="fas fa-home"></i>
                        <span>Ana Sayfa</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="reset-main">
        <div class="reset-container">
            <div class="reset-header">
                <div class="reset-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h1 class="reset-title">Şifre Sıfırlama</h1>
                <p>
                    <?php 
                    if(isset($token) && !$error) {
                        echo 'Yeni şifrenizi belirleyin';
                    } else {
                        echo 'Şifrenizi sıfırlamak için e-posta adresinizi girin';
                    }
                    ?>
                </p>
            </div>

            <div class="reset-content">
                <?php if($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($token) && !$error): ?>
                    <!-- Yeni Şifre Formu -->
                    <form method="POST">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Yeni Şifre</label>
                            <input type="password" name="new_password" class="form-input" placeholder="En az 6 karakter" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Şifre Tekrar</label>
                            <input type="password" name="confirm_password" class="form-input" placeholder="Şifreyi tekrar girin" required minlength="6">
                        </div>
                        
                        <button type="submit" name="set_new_password" class="submit-btn">
                            <i class="fas fa-save"></i> Şifreyi Güncelle
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Şifre Sıfırlama İsteği -->
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">E-posta Adresiniz</label>
                            <input type="email" name="email" class="form-input" placeholder="Kayıtlı e-posta adresiniz" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                        
                        <button type="submit" name="request_reset" class="submit-btn">
                            <i class="fas fa-paper-plane"></i> Şifre Sıfırlama İsteği Gönder
                        </button>
                    </form>

                    <div class="form-footer">
                        <a href="login.php">← Giriş sayfasına dön</a>
                    </div>

                    <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                        <div class="token-info">
                            <strong><i class="fas fa-info-circle"></i> Admin Bilgisi:</strong><br>
                            Şifre sıfırlama token'ları 1 saat geçerlidir. Kullanıcıya token'ı göndermek için:<br>
                            <code>http://<?php echo $_SERVER['HTTP_HOST']; ?>/sifre-sifirla.php?token=TOKEN_HERE</code>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>&copy; 2024 ONVIBES - Tüm hakları saklıdır.</p>
        </div>
    </footer>
</body>
</html>
