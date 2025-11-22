<?php
// kariyer.php - Kariyer Sayfası
session_start();
include 'config.php';

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kariyer | ONVIBES</title>
    <meta name="description" content="ONVIBES'te kariyer fırsatları. Gazeteci, yazılım geliştirici, tasarımcı pozisyonları için başvurun.">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ONVIBES Tema Değişkenleri - Premium */
        :root {
            --red: #e74c3c;
            --dark: #2c3e50;
            --light: #ffffff;
            --border: #e0e0e0;
            --surface: #f8f9fa;
            --text: #2c3e50;
            --gray: #7f8c8d;
            --green: #27ae60;
            --blue: #3498db;
            --orange: #f39c12;
            --purple: #9b59b6;
            --background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .dark-mode {
            --red: #ff6b6b;
            --dark: #1a1a1a;
            --light: #0a0a0a;
            --border: #333333;
            --surface: #1e1e1e;
            --text: #ffffff;
            --gray: #bdc3c7;
            --green: #2ecc71;
            --blue: #3498db;
            --orange: #e67e22;
            --purple: #9b59b6;
            --background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        body {
            background: var(--background);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Animasyonlu Logo */
        .animated-logo {
            position: relative;
            display: inline-block;
        }

        .logo-text {
            color: white;
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
            background: linear-gradient(45deg, #ffffff, #ff6b6b, #ffffff);
            background-size: 200% 200%;
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: logoShimmer 3s ease-in-out infinite;
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
            animation: logoShine 2s ease-in-out infinite;
        }
        
        @keyframes logoShimmer {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        @keyframes logoShine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Header - Premium */
        .main-menu {
            background: var(--light);
            box-shadow: 0 4px 30px rgba(0,0,0,0.1), 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(20px);
        }

        .top-bar {
            background: linear-gradient(135deg, var(--red) 0%, #c0392b 100%);
            color: white;
            padding: 8px 0;
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
            animation: shimmer 4s infinite;
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
            gap: 15px;
        }

        .header-links {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
            justify-content: center;
            flex-wrap: wrap;
        }

        .header-link {
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 25px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }

        .header-link:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .header-link.active {
            background: rgba(255,255,255,0.25);
        }

        .right-nav {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-shrink: 0;
        }

        .nav-btn {
            background: rgba(255,255,255,0.15);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
            text-decoration: none;
        }

        .nav-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .nav-btn a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
        }

        /* Navigation */
        #mainnav {
            background: linear-gradient(135deg, var(--dark) 0%, #34495e 100%);
            position: relative;
            overflow: hidden;
        }

        #mainnav::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 50%, rgba(255,255,255,0.05) 0%, transparent 70%);
        }

        #mainnav .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }

        .nav-info {
            display: flex;
            align-items: center;
            gap: 20px;
            color: white;
            font-size: 13px;
            font-weight: 600;
        }

        .current-time {
            background: rgba(255,255,255,0.15);
            padding: 10px 20px;
            border-radius: 25px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.2);
            animation: pulse-glow 3s ease-in-out infinite;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .current-time i {
            color: #ffd700;
        }

        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 10px rgba(255,255,255,0.2); }
            50% { box-shadow: 0 0 20px rgba(255,255,255,0.4); }
        }

        /* Navigation Links */
        .nav-links {
            display: flex;
            list-style: none;
            flex: 1;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .nav-links::-webkit-scrollbar { display: none; }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            display: block;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }

        .nav-links a::before {
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

        .nav-links a:hover::before,
        .nav-links a.active::before {
            width: 80%;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: var(--red);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4);
        }

        /* Ana İçerik */
        .main-content {
            flex: 1;
            padding: 40px 0;
        }

        .page-header {
            text-align: center;
            margin-bottom: 50px;
            padding: 60px 0;
            background: var(--light);
            border-radius: 25px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--red), var(--orange), var(--red));
        }

        .page-title {
            font-size: 3.5rem;
            font-weight: 900;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--red) 0%, #c0392b 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            position: relative;
        }

        .page-subtitle {
            font-size: 1.3rem;
            color: var(--gray);
            font-weight: 500;
        }

        .content-section {
            background: var(--light);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1), 0 5px 20px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .content-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--blue), var(--green));
        }

        .section-title {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 25px;
            color: var(--dark);
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--red);
            border-radius: 2px;
        }

        .section-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--text);
        }

        .section-content p {
            margin-bottom: 20px;
        }

        .positions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .position-card {
            background: var(--surface);
            border-radius: 15px;
            padding: 30px;
            border: 1px solid var(--border);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .position-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            border-color: var(--red);
        }

        .position-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--dark);
        }

        .position-info {
            color: var(--gray);
            margin-bottom: 15px;
        }

        .position-desc {
            color: var(--text);
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .position-requirements {
            margin-bottom: 20px;
        }

        .position-requirements h4 {
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .position-requirements ul {
            padding-left: 20px;
            color: var(--gray);
        }

        .position-requirements li {
            margin-bottom: 5px;
        }

        .apply-btn {
            background: linear-gradient(135deg, var(--red) 0%, #c0392b 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .apply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
        }

        /* Footer - Premium */
        footer {
            background: linear-gradient(135deg, var(--dark) 0%, #34495e 100%);
            color: white;
            padding: 40px 0 20px;
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
            .mobile-menu-toggle { display: block; }
            .header-links { display: none; }
            
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .page-title {
                font-size: 2.5rem;
            }
            
            .positions-grid {
                grid-template-columns: 1fr;
            }
            
            .content-section {
                padding: 25px;
            }
        }

        @media (max-width: 480px) {
            .footer-content {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <!-- Header -->
    <header>
        <div class="main-menu">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="container">
                    <div class="header-links">
                        <a href="hakkimizda.php" class="header-link">
                            <i class="fas fa-info-circle"></i> Hakkımızda
                        </a>
                        <a href="iletisim.php" class="header-link">
                            <i class="fas fa-envelope"></i> İletişim
                        </a>
                        <a href="reklam.php" class="header-link">
                            <i class="fas fa-bullhorn"></i> Reklam
                        </a>
                        <a href="kariyer.php" class="header-link active">
                            <i class="fas fa-briefcase"></i> Kariyer
                        </a>
                    </div>
                    
                    <div class="right-nav">
                        <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                            <i class="fas fa-bars"></i>
                        </button>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="toggle_theme" class="nav-btn">
                                <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                                <span><?php echo $dark_mode ? 'Gündüz' : 'Gece'; ?></span>
                            </button>
                        </form>
                        <a href="login.php" class="nav-btn">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Giriş</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav id="mainnav">
                <div class="container">
                    <div class="nav-info">
                        <div class="current-time">
                            <i class="fas fa-clock"></i>
                            <span id="current-time"></span>
                        </div>
                    </div>
                    
                    <ul class="nav-links">
                        <li><a href="index.php">Ana Sayfa</a></li>
                        <li><a href="hakkimizda.php">Hakkımızda</a></li>
                        <li><a href="iletisim.php">İletişim</a></li>
                        <li><a href="login.php">Giriş</a></li>
                    </ul>
                </div>
            </nav>
        </div>
    </header>

    <!-- Ana İçerik -->
    <main class="main-content">
        <div class="container">
            <!-- Sayfa Başlığı -->
            <div class="page-header">
                <div class="animated-logo">
                    <h1 class="page-title">HABER | ONVIBES</h1>
                </div>
                <p class="page-subtitle">ONVIBES'te Kariyer Fırsatları - Geleceğinizi Bizimle İnşa Edin</p>
            </div>

            <!-- Kariyer Hakkında -->
            <div class="content-section">
                <h2 class="section-title">ONVIBES'te Çalışın</h2>
                <div class="section-content">
                    <p>
                        <strong>ONVIBES</strong> ekibine katılmak ister misiniz? Türkiye'nin en yenilikçi haber platformunda 
                        çalışarak, dijital medya alanında kariyerinizi geliştirebilir, deneyimli profesyonellerle birlikte 
                        çalışabilirsiniz.
                    </p>
                    <p>
                        Çalışanlarımızın kişisel ve mesleki gelişimini destekliyor, yenilikçi projelerde yer almalarını sağlıyoruz. 
                        Modern çalışma ortamı, esnek saatler ve rekabetçi maaş paketleri sunuyoruz.
                    </p>
                </div>
            </div>

            <!-- Açık Pozisyonlar -->
            <div class="content-section">
                <h2 class="section-title">Açık Pozisyonlar</h2>
                <div class="section-content">
                    <div class="positions-grid">
                        <div class="position-card">
                            <h3 class="position-title">Gazeteci / Muhabir</h3>
                            <div class="position-info">
                                <strong>Tip:</strong> Tam zamanlı | <strong>Konum:</strong> İstanbul
                            </div>
                            <div class="position-desc">
                                Gündem, spor ve teknoloji konularında haber üretimi yapacak, 
                                araştırma yapabilecek ve sosyal medya içerikleri oluşturabilecek 
                                deneyimli gazeteciler arıyoruz.
                            </div>
                            <div class="position-requirements">
                                <h4>Gereksinimler:</h4>
                                <ul>
                                    <li>Gazetecilik veya ilgili alanlarda lisans mezunu</li>
                                    <li>En az 2 yıl gazetecilik deneyimi</li>
                                    <li>Haber yazma ve editörlük becerisi</li>
                                    <li>Sosyal medya kullanım bilgisi</li>
                                    <li>İyi derece Türkçe ve İngilizce</li>
                                </ul>
                            </div>
                            <a href="iletisim.php" class="apply-btn">Başvur</a>
                        </div>
                        
                        <div class="position-card">
                            <h3 class="position-title">Web Geliştirici</h3>
                            <div class="position-info">
                                <strong>Tip:</strong> Tam zamanlı | <strong>Konum:</strong> Uzaktan/İstanbul
                            </div>
                            <div class="position-desc">
                                Platformumuzun backend ve frontend geliştirme işlemlerinden 
                                sorumlu olacak, yeni özellikler geliştirecek ve sistem 
                                optimizasyonu yapabilecek geliştiriciler arıyoruz.
                            </div>
                            <div class="position-requirements">
                                <h4>Gereksinimler:</h4>
                                <ul>
                                    <li>PHP, MySQL, JavaScript tecrübesi</li>
                                    <li>Laravel veya benzeri framework bilgisi</li>
                                    <li>Responsive tasarım deneyimi</li>
                                    <li>API geliştirme ve entegrasyon bilgisi</li>
                                    <li>Git versiyon kontrol sistemi kullanımı</li>
                                </ul>
                            </div>
                            <a href="iletisim.php" class="apply-btn">Başvur</a>
                        </div>
                        
                        <div class="position-card">
                            <h3 class="position-title">Tasarımcı / UI/UX</h3>
                            <div class="position-info">
                                <strong>Tip:</strong> Freelance | <strong>Konum:</strong> Uzaktan
                            </div>
                            <div class="position-desc">
                                Platform arayüz tasarımı, görsel kimlik çalışmaları ve 
                                kullanıcı deneyimi optimizasyonu yapabilecek yaratıcı 
                                tasarımcılarla çalışmak istiyoruz.
                            </div>
                            <div class="position-requirements">
                                <h4>Gereksinimler:</h4>
                                <ul>
                                    <li>Grafik tasarım alanında lisans mezunu</li>
                                    <li>Adobe Creative Suite bilgisi</li>
                                    <li>UI/UX tasarım deneyimi</li>
                                    <li>Figma, Sketch veya benzeri araçlar</li>
                                    <li>Responsive tasarım anlayışı</li>
                                </ul>
                            </div>
                            <a href="iletisim.php" class="apply-btn">Başvur</a>
                        </div>
                        
                        <div class="position-card">
                            <h3 class="position-title">İçerik Editörü</h3>
                            <div class="position-info">
                                <strong>Tip:</strong> Part-time | <strong>Konum:</strong> İstanbul
                            </div>
                            <div class="position-desc">
                                Haber içeriklerinin düzenlenmesi, doğrulanması ve 
                                yayın öncesi kalite kontrol işlemlerinden sorumlu olacak 
                                editörler arıyoruz.
                            </div>
                            <div class="position-requirements">
                                <h4>Gereksinimler:</h4>
                                <ul>
                                    <li>Gazetecilik veya edebiyat alanında deneyim</li>
                                    <li>Mükemmel Türkçe dil bilgisi</li>
                                    <li>İçerik editörlüğü deneyimi</li>
                                    <li>Detaycı ve analitik düşünce yapısı</li>
                                    <li>İleri düzey MS Office bilgisi</li>
                                </ul>
                            </div>
                            <a href="iletisim.php" class="apply-btn">Başvur</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Neden ONVIBES -->
            <div class="content-section">
                <h2 class="section-title">Neden ONVIBES?</h2>
                <div class="section-content">
                    <p>
                        <strong>Yenilikçi Çalışma Ortamı:</strong> Teknoloji odaklı çalışma anlayışımız ile 
                        her zaman en güncel araçları ve yöntemleri kullanıyoruz.
                    </p>
                    <p>
                        <strong>Kişisel Gelişim:</strong> Eğitim programları, konferans katılımları ve 
                        profesyonel gelişim fırsatları sunuyoruz.
                    </p>
                    <p>
                        <strong>Esnek Çalışma:</strong> Hibrit çalışma modeli ile iş-yaşam dengesini 
                        korumanızı destekliyoruz.
                    </p>
                    <p>
                        <strong>Rekabetçi Paketler:</strong> Maaş, prim, yemek ve ulaşım desteği 
                        ile yanınızdayız.
                    </p>
                </div>
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
                &copy; 2025 ONVIBES - Tüm hakları saklıdır.
            </div>
        </div>
    </footer>

    <script>
        // Canlı saat
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('tr-TR', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('current-time').textContent = timeString;
        }
        updateTime();
        setInterval(updateTime, 1000);

        // Mobile menu toggle
        function toggleMobileMenu() {
            console.log('Mobile menu toggle clicked');
        }
    </script>
</body>
</html>
