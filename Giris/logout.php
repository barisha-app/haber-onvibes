<?php
// logout.php - Çıkış Sistemi
session_start();
include '../config.php';

// Karanlık mod kontrolü - session destroy'dan önce kaydet
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Çıkış işlemi
if(isset($_SESSION['loggedin'])) {
    $username = $_SESSION['username'];
    session_destroy();
    // Session yok olduktan sonra dark mode için yeni session başlat
    session_start();
    $_SESSION['dark_mode'] = $dark_mode;
    $logout_success = true;
} else {
    header('Location: ../index.php');
    exit();
}

// Karanlık mod toggle (logout sayfasında da çalışsın)
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çıkış Yapılıyor - ONVIBES</title>
    
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

        /* Logout Container - Premium */
        .logout-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
            min-height: calc(100vh - 200px);
        }

        .logout-container {
            background: var(--light);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            position: relative;
            border: 1px solid var(--border);
            text-align: center;
        }

        .dark-mode .logout-container {
            background: var(--light);
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }

        .logout-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--red), var(--orange), var(--red));
        }

        .logout-header {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            padding: 2.5rem 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .logout-header::before {
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

        .logout-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            animation: bounce 2s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        .logout-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .logout-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
            font-weight: 500;
        }

        .logout-content {
            padding: 2.5rem 2rem;
            position: relative;
            z-index: 1;
        }

        .logout-message {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: var(--text);
            font-weight: 600;
        }

        .username {
            color: var(--red);
            font-weight: 800;
            font-size: 1.3rem;
        }

        .logout-info {
            background: var(--surface);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--green);
        }

        .dark-mode .logout-info {
            background: var(--surface);
        }

        .info-text {
            font-size: 0.95rem;
            color: var(--gray);
            line-height: 1.5;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(210, 35, 42, 0.4);
        }

        .btn-secondary {
            background: var(--surface);
            color: var(--text);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: var(--red);
        }

        .countdown {
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--gray);
            font-weight: 600;
        }

        .countdown-number {
            color: var(--red);
            font-weight: 800;
            font-size: 1.1rem;
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

            .logout-container {
                margin: 1rem;
                max-width: 100%;
            }
            
            .logout-content {
                padding: 2rem 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 250px;
                justify-content: center;
            }

            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .footer-content {
                grid-template-columns: 1fr;
            }
            
            .logout-header {
                padding: 2rem 1.5rem;
            }
            
            .logout-content {
                padding: 1.5rem 1rem;
            }
            
            .logout-title {
                font-size: 1.7rem;
            }
            
            .logout-icon {
                font-size: 3rem;
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
                            <a href="login.php">
                                <i class="fas fa-sign-in-alt"></i>
                                <span>Giriş Yap</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Logout Content -->
    <main class="logout-main">
        <div class="logout-container">
            <div class="logout-header">
                <div class="logout-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <h1 class="logout-title">Çıkış Yapıldı</h1>
                <p class="logout-subtitle">Güvenli çıkış işlemi tamamlandı</p>
            </div>

            <div class="logout-content">
                <?php if(isset($logout_success) && $logout_success): ?>
                    <div class="logout-message">
                        Hoşçakal <span class="username"><?php echo htmlspecialchars($username); ?></span>!<br>
                        Başarıyla çıkış yaptınız.
                    </div>

                    <div class="logout-info">
                        <p class="info-text">
                            <i class="fas fa-shield-check"></i> 
                            Oturumunuz güvenli bir şekilde sonlandırıldı. 
                            Hesabınıza tekrar giriş yapmak için giriş sayfasını kullanabilirsiniz.
                        </p>
                    </div>

                    <div class="action-buttons">
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i>
                            Tekrar Giriş Yap
                        </a>
                        <a href="../index.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i>
                            Ana Sayfaya Dön
                        </a>
                    </div>

                    <div class="countdown">
                        <i class="fas fa-clock"></i>
                        <span id="countdown-text">Ana sayfaya yönlendiriliyorsunuz: </span>
                        <span class="countdown-number" id="countdown">5</span> saniye
                    </div>
                <?php else: ?>
                    <div class="logout-message">
                        <i class="fas fa-exclamation-triangle"></i><br>
                        Çıkış işlemi başarısız!
                    </div>

                    <div class="action-buttons">
                        <a href="../index.php" class="btn btn-primary">
                            <i class="fas fa-home"></i>
                            Ana Sayfaya Dön
                        </a>
                    </div>
                <?php endif; ?>
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
        // Otomatik yönlendirme sayacı
        document.addEventListener('DOMContentLoaded', function() {
            <?php if(isset($logout_success) && $logout_success): ?>
                let countdown = 5;
                const countdownElement = document.getElementById('countdown');
                const countdownInterval = setInterval(function() {
                    countdown--;
                    countdownElement.textContent = countdown;
                    
                    if (countdown <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = '../index.php';
                    }
                }, 1000);

                // Kullanıcı herhangi bir yere tıklarsa yönlendirmeyi iptal et
                document.addEventListener('click', function() {
                    clearInterval(countdownInterval);
                    document.getElementById('countdown-text').textContent = 'Yönlendirme iptal edildi';
                    countdownElement.style.display = 'none';
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>
<?php
// logout.php - Çıkış Sistemi
session_start();
include 'config.php';

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Çıkış işlemi
if(isset($_SESSION['loggedin'])) {
    $username = $_SESSION['username'];
    session_destroy();
    $logout_success = true;
} else {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çıkış Yapılıyor - ONVIBES</title>
    
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

        /* Logout Container - Premium */
        .logout-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
            min-height: calc(100vh - 200px);
        }

        .logout-container {
            background: var(--light);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            position: relative;
            border: 1px solid var(--border);
            text-align: center;
        }

        .dark-mode .logout-container {
            background: var(--light);
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }

        .logout-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--red), var(--orange), var(--red));
        }

        .logout-header {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            padding: 2.5rem 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .logout-header::before {
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

        .logout-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            animation: bounce 2s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        .logout-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .logout-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
            font-weight: 500;
        }

        .logout-content {
            padding: 2.5rem 2rem;
            position: relative;
            z-index: 1;
        }

        .logout-message {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: var(--text);
            font-weight: 600;
        }

        .username {
            color: var(--red);
            font-weight: 800;
            font-size: 1.3rem;
        }

        .logout-info {
            background: var(--surface);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--green);
        }

        .dark-mode .logout-info {
            background: var(--surface);
        }

        .info-text {
            font-size: 0.95rem;
            color: var(--gray);
            line-height: 1.5;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(210, 35, 42, 0.4);
        }

        .btn-secondary {
            background: var(--surface);
            color: var(--text);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: var(--red);
        }

        .countdown {
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--gray);
            font-weight: 600;
        }

        .countdown-number {
            color: var(--red);
            font-weight: 800;
            font-size: 1.1rem;
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

            .logout-container {
                margin: 1rem;
                max-width: 100%;
            }
            
            .logout-content {
                padding: 2rem 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 250px;
                justify-content: center;
            }

            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .footer-content {
                grid-template-columns: 1fr;
            }
            
            .logout-header {
                padding: 2rem 1.5rem;
            }
            
            .logout-content {
                padding: 1.5rem 1rem;
            }
            
            .logout-title {
                font-size: 1.7rem;
            }
            
            .logout-icon {
                font-size: 3rem;
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
                        <a href="index.php" title="ONVIBES">
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
                            <a href="login.php">
                                <i class="fas fa-sign-in-alt"></i>
                                <span>Giriş Yap</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Logout Content -->
    <main class="logout-main">
        <div class="logout-container">
            <div class="logout-header">
                <div class="logout-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <h1 class="logout-title">Çıkış Yapıldı</h1>
                <p class="logout-subtitle">Güvenli çıkış işlemi tamamlandı</p>
            </div>

            <div class="logout-content">
                <?php if(isset($logout_success) && $logout_success): ?>
                    <div class="logout-message">
                        Hoşçakal <span class="username"><?php echo htmlspecialchars($username); ?></span>!<br>
                        Başarıyla çıkış yaptınız.
                    </div>

                    <div class="logout-info">
                        <p class="info-text">
                            <i class="fas fa-shield-check"></i> 
                            Oturumunuz güvenli bir şekilde sonlandırıldı. 
                            Hesabınıza tekrar giriş yapmak için giriş sayfasını kullanabilirsiniz.
                        </p>
                    </div>

                    <div class="action-buttons">
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i>
                            Tekrar Giriş Yap
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i>
                            Ana Sayfaya Dön
                        </a>
                    </div>

                    <div class="countdown">
                        <i class="fas fa-clock"></i>
                        <span id="countdown-text">Ana sayfaya yönlendiriliyorsunuz: </span>
                        <span class="countdown-number" id="countdown">5</span> saniye
                    </div>
                <?php else: ?>
                    <div class="logout-message">
                        <i class="fas fa-exclamation-triangle"></i><br>
                        Çıkış işlemi başarısız!
                    </div>

                    <div class="action-buttons">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-home"></i>
                            Ana Sayfaya Dön
                        </a>
                    </div>
                <?php endif; ?>
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
                        <li><a href="hakkimizda.php">Hakkımızda</a></li>
                        <li><a href="iletisim.php">İletişim</a></li>
                        <li><a href="kariyer.php">Kariyer</a></li>
                        <li><a href="reklam.php">Reklam</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Kategoriler</h3>
                    <ul class="footer-links">
                        <li><a href="index.php?category=1">Gündem</a></li>
                        <li><a href="index.php?category=2">Spor</a></li>
                        <li><a href="index.php?category=3">Magazin</a></li>
                        <li><a href="index.php?category=4">Teknoloji</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Yardım</h3>
                    <ul class="footer-links">
                        <li><a href="sss.php">SSS</a></li>
                        <li><a href="kullanim.php">Kullanım Koşulları</a></li>
                        <li><a href="gizlilik.php">Gizlilik Politikası</a></li>
                        <li><a href="cerez.php">Çerez Politikası</a></li>
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
        // Otomatik yönlendirme sayacı
        document.addEventListener('DOMContentLoaded', function() {
            <?php if(isset($logout_success) && $logout_success): ?>
                let countdown = 5;
                const countdownElement = document.getElementById('countdown');
                const countdownInterval = setInterval(function() {
                    countdown--;
                    countdownElement.textContent = countdown;
                    
                    if (countdown <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = 'index.php';
                    }
                }, 1000);

                // Kullanıcı herhangi bir yere tıklarsa yönlendirmeyi iptal et
                document.addEventListener('click', function() {
                    clearInterval(countdownInterval);
                    document.getElementById('countdown-text').textContent = 'Yönlendirme iptal edildi';
                    countdownElement.style.display = 'none';
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>
