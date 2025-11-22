<?php
// login.php - Giriş Sistemi
session_start();
include '../config.php';

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Eğer zaten giriş yapılmışsa ana sayfaya yönlendir
if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Giriş işlemi
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if(empty($username) || empty($password)) {
        $error = 'Lütfen kullanıcı adı ve şifre girin!';
    } else {
        // Kullanıcıyı veritabanında ara
        $query = "SELECT id, username, password, email, is_admin FROM users WHERE username = ? OR email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user && password_verify($password, $user['password'])) {
            // Giriş başarılı
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'];
            
            // Son giriş zamanını güncelle
            $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$user['id']]);
            
            header('Location: ../index.php');
            exit();
        } else {
            $error = 'Geçersiz kullanıcı adı veya şifre!';
        }
    }
}

// Kayıt işlemi
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if(empty($username) || empty($email) || empty($password)) {
        $error = 'Lütfen tüm alanları doldurun!';
    } elseif($password !== $confirm_password) {
        $error = 'Şifreler eşleşmiyor!';
    } elseif(strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır!';
    } else {
        // Kullanıcı adı ve email kontrolü
        $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$username, $email]);
        
        if($check_stmt->rowCount() > 0) {
            $error = 'Bu kullanıcı adı veya email zaten kullanılıyor!';
        } else {
            // Yeni kullanıcıyı kaydet
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_query = "INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())";
            $insert_stmt = $db->prepare($insert_query);
            
            if($insert_stmt->execute([$username, $email, $hashed_password])) {
                $success = 'Kayıt başarılı! Giriş yapabilirsiniz.';
                $_POST = array(); // Formu temizle
            } else {
                $error = 'Kayıt sırasında hata oluştu!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - ONVIBES</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ONVIBES Tema Değişkenleri - Premium */
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
            --purple: #9b59b6;
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
            --purple: #9b59b6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        body {
            background: linear-gradient(135deg, var(--dark) 0%, var(--red) 100%);
            color: var(--text);
            line-height: 1.6;
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

        /* Header - Premium Upgrade */
        .main-menu {
            background: var(--light);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08), 0 1px 3px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
        }

        .dark-mode .main-menu {
            background: var(--light);
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .top-bar {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 6px 0;
            position: relative;
            overflow: hidden;
        }

        .top-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .top-bar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .logo {
            font-size: 0;
            flex-shrink: 0;
        }

        .logo a {
            text-decoration: none;
        }

        .logo-text {
            color: white;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            background: linear-gradient(45deg, #ffffff, #ff6b6b, #ffffff);
            background-size: 200% 200%;
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer-text 3s ease-in-out infinite;
            display: inline-block;
            position: relative;
        }
        
        .logo-text::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent);
            transform: translateX(-100%);
            animation: shine 2s ease-in-out infinite;
        }
        
        @keyframes shimmer-text {
            0%, 100% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
        }
        
        @keyframes shine {
            0% {
                transform: translateX(-100%);
            }
            100% {
                transform: translateX(100%);
            }
        }

        .right-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .top-links {
            display: flex;
            gap: 8px;
        }

        .top-links button,
        .top-links a {
            background: none;
            border: none;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 20px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }

        .top-links button::before,
        .top-links a::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .top-links button:hover::before,
        .top-links a:hover::before {
            width: 100%;
            height: 100%;
        }

        .top-links button:hover,
        .top-links a:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }

        /* Login Container - Premium */
        .login-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            min-height: calc(100vh - 200px);
        }

        .login-container {
            background: var(--light);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            position: relative;
            border: 1px solid var(--border);
        }

        .dark-mode .login-container {
            background: var(--light);
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--red), var(--orange), var(--red));
        }

        .login-header {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            padding: 2.5rem 2rem;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: rotate 6s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .login-logo {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .login-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
            font-weight: 500;
        }

        .login-tabs {
            display: flex;
            background: var(--dark);
            position: relative;
            z-index: 1;
        }

        .tab-btn {
            flex: 1;
            padding: 1.2rem;
            border: none;
            background: transparent;
            color: white;
            cursor: pointer;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
        }

        .tab-btn::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: var(--red);
            transition: all 0.3s;
            transform: translateX(-50%);
        }

        .tab-btn.active {
            background: rgba(210, 35, 42, 0.2);
        }

        .tab-btn.active::before {
            width: 80%;
        }

        .tab-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .login-form {
            padding: 2.5rem 2rem;
        }

        .form-group {
            margin-bottom: 1.8rem;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 0.8rem;
            font-weight: 700;
            color: var(--text);
            font-size: 15px;
            letter-spacing: 0.3px;
        }

        .form-input {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            background: var(--surface);
            color: var(--text);
        }

        .dark-mode .form-input {
            background: var(--surface);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(210, 35, 42, 0.15);
            transform: translateY(-2px);
        }

        .form-input::placeholder {
            color: var(--gray);
            font-weight: 500;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
            letter-spacing: 0.5px;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(210, 35, 42, 0.4);
        }

        .submit-btn:active {
            transform: translateY(-1px);
        }

        .alert {
            padding: 1.2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            position: relative;
            overflow: hidden;
            border: 1px solid transparent;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border-color: rgba(220, 53, 69, 0.2);
        }

        .alert-error::before {
            background: #dc3545;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-color: rgba(40, 167, 69, 0.2);
        }

        .alert-success::before {
            background: #28a745;
        }

        .form-footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }

        .form-footer a {
            color: var(--red);
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s;
            position: relative;
        }

        .form-footer a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--red);
            transition: width 0.3s;
        }

        .form-footer a:hover {
            color: var(--dark);
        }

        .form-footer a:hover::after {
            width: 100%;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease-in-out;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Footer - Premium */
        footer {
            background: linear-gradient(135deg, var(--dark) 0%, #34495e 100%);
            color: white;
            padding: 30px 0 20px;
            position: relative;
            overflow: hidden;
        }

        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--red), var(--orange), var(--red));
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin-bottom: 25px;
        }

        .footer-column h3 {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 8px;
        }

        .footer-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
            position: relative;
            padding-left: 15px;
        }

        .footer-links a::before {
            content: '→';
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            transform: translateX(-5px);
            transition: all 0.3s;
            color: var(--red);
        }

        .footer-links a:hover {
            color: white;
            transform: translateX(5px);
        }

        .footer-links a:hover::before {
            opacity: 1;
            transform: translateX(0);
        }

        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 14px;
            color: rgba(255,255,255,0.6);
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .top-bar .container {
                flex-direction: column;
                gap: 10px;
            }

            .right-nav {
                width: 100%;
                justify-content: center;
            }

            .login-container {
                margin: 1rem;
                max-width: 100%;
            }
            
            .login-form {
                padding: 2rem 1.5rem;
            }

            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .footer-content {
                grid-template-columns: 1fr;
            }
            
            .login-header {
                padding: 2rem 1.5rem;
            }
            
            .login-form {
                padding: 1.5rem 1rem;
            }
            
            .tab-btn {
                padding: 1rem;
                font-size: 14px;
            }
        }
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <!-- Header -->
    <header id="header">
        <div class="main-menu">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="container">
                    <h1 class="logo">
                        <a href="../index.php" title="ONVIBES">
                            <span class="logo-text">HABER|ONVIBES</span>
                        </a>
                    </h1>

                    <!-- Right Nav -->
                    <div class="right-nav">
                        <!-- Top Links -->
                        <div class="top-links">
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="toggle_theme">
                                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                                    <span><?php echo $dark_mode ? 'Açık' : 'Koyu'; ?></span>
                                </button>
                            </form>
                            <a href="../index.php">
                                <i class="fas fa-home"></i>
                                <span>Ana Sayfa</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Login Content -->
    <main class="login-main">
        <div class="login-container">
            <div class="login-header">
                <div class="login-logo">ONVIBES</div>
                <p class="login-subtitle">Haber ve Sohbet Platformu</p>
            </div>

            <div class="login-tabs">
                <button class="tab-btn active" onclick="showTab('login')">Giriş Yap</button>
                <button class="tab-btn" onclick="showTab('register')">Kayıt Ol</button>
            </div>

            <?php if($error): ?>
                <div class="alert alert-error" style="margin: 1.5rem 2rem 0;"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success" style="margin: 1.5rem 2rem 0;"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- GİRİŞ FORMU -->
            <div class="tab-content active" id="loginTab">
                <form method="POST" class="login-form">
                    <div class="form-group">
                        <label class="form-label">Kullanıcı Adı veya E-posta</label>
                        <input type="text" name="username" class="form-input" placeholder="Kullanıcı adınız veya e-posta" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Şifre</label>
                        <input type="password" name="password" class="form-input" placeholder="Şifreniz" required>
                    </div>
                    
                    <button type="submit" name="login" class="submit-btn">
                        <i class="fas fa-sign-in-alt"></i> Giriş Yap
                    </button>
                    
                    <div class="form-footer">
                        Hesabınız yok mu? <a href="javascript:void(0)" onclick="showTab('register')">Kayıt olun</a>
                    </div>
                </form>
            </div>

            <!-- KAYIT FORMU -->
            <div class="tab-content" id="registerTab">
                <form method="POST" class="login-form">
                    <div class="form-group">
                        <label class="form-label">Kullanıcı Adı</label>
                        <input type="text" name="username" class="form-input" placeholder="Kullanıcı adınız" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">E-posta</label>
                        <input type="email" name="email" class="form-input" placeholder="E-posta adresiniz" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Şifre</label>
                        <input type="password" name="password" class="form-input" placeholder="Şifreniz (en az 6 karakter)" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Şifre Tekrar</label>
                        <input type="password" name="confirm_password" class="form-input" placeholder="Şifrenizi tekrar girin" required>
                    </div>
                    
                    <button type="submit" name="register" class="submit-btn">
                        <i class="fas fa-user-plus"></i> Kayıt Ol
                    </button>
                    
                    <div class="form-footer">
                        Zaten hesabınız var mı? <a href="javascript:void(0)" onclick="showTab('login')">Giriş yapın</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>ONVIBES</h3>
                    <ul class="footer-links">
                        <li><a href="../hakkimizda.php">Hakkımızda</a></li>
                        <li><a href="../iletisim.php">İletişim</a></li>
                        <li><a href="../kariyer.php">Kariyer</a></li>
                        <li><a href="../reklam.php">Reklam</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Kategoriler</h3>
                    <ul class="footer-links">
                        <li><a href="../index.php?category=1">Gündem</a></li>
                        <li><a href="../index.php?category=2">Spor</a></li>
                        <li><a href="../index.php?category=3">Magazin</a></li>
                        <li><a href="../index.php?category=4">Teknoloji</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Yardım</h3>
                    <ul class="footer-links">
                        <li><a href="../sss.php">SSS</a></li>
                        <li><a href="../kullanim.php">Kullanım Koşulları</a></li>
                        <li><a href="../gizlilik.php">Gizlilik Politikası</a></li>
                        <li><a href="../cerez.php">Çerez Politikası</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Bizi Takip Edin</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                        <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                        <li><a href="#"><i class="fab fa-youtube"></i> YouTube</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; 2024 ONVIBES - Tüm hakları saklıdır.
            </div>
        </div>
    </footer>

    <script>
        function showTab(tabName) {
            // Tab butonlarını güncelle
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Tab içeriklerini güncelle
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabName + 'Tab').classList.add('active');
        }

        // Sayfa yüklendiğinde hata varsa ilgili tabı göster
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])): ?>
                showTab('register');
            <?php endif; ?>

            // Form inputlarına focus efekti
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.parentElement.classList.remove('focused');
                    }
                });
            });
        });
    </script>
</body>
</html>
