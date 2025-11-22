<?php
// admin.php - ONVIBES Admin Panel Dashboard
session_start();

// Giriş kontrolü
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../Giris/login.php');
    exit();
}

// Admin kontrolü
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../index.php');
    exit();
}

include '../config.php';
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$is_admin = $_SESSION['is_admin'];

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// İstatistikler
try {
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_news = $db->query("SELECT COUNT(*) FROM news")->fetchColumn();
    $total_categories = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $pending_news = $db->query("SELECT COUNT(*) FROM news WHERE status = 'pending'")->fetchColumn();
    $approved_news = $db->query("SELECT COUNT(*) FROM news WHERE status = 'approved'")->fetchColumn();
    $total_views = $db->query("SELECT COALESCE(SUM(views), 0) FROM news")->fetchColumn();
    
    // Ek istatistikler
    $total_comments = $db->query("SELECT COUNT(*) FROM comments")->fetchColumn() ?: 0;
    
    // Bugünkü haberler
    $today_news = $db->query("SELECT COUNT(*) FROM news WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    
    // Aktif kullanıcılar (son 24 saat)
    $active_users = $db->query("SELECT COUNT(DISTINCT user_id) FROM user_activity WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn() ?: 0;
    
    // Şifre sıfırlama istekleri
    $pending_password_resets = 0;
    $check_table = $db->query("SHOW TABLES LIKE 'password_resets'");
    if($check_table->rowCount() > 0) {
        $pending_password_resets = $db->query("SELECT COUNT(*) FROM password_resets WHERE status = 'pending' AND expires_at > NOW()")->fetchColumn() ?: 0;
    }
    
} catch (PDOException $e) {
    $total_users = $total_news = $total_categories = $pending_news = $approved_news = $total_views = 0;
    $total_comments = $today_news = $active_users = 0;
    $pending_password_resets = 0;
}

// Son haberler
try {
    $recent_news = $db->query("
        SELECT n.*, u.username as author_name, c.name as category_name 
        FROM news n 
        LEFT JOIN users u ON n.author_id = u.id 
        LEFT JOIN categories c ON n.category_id = c.id 
        ORDER BY n.created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_news = [];
}

// Bekleyen onaylar
try {
    $pending_approvals = $db->query("
        SELECT n.*, u.username as author_name, c.name as category_name 
        FROM news n 
        LEFT JOIN users u ON n.author_id = u.id 
        LEFT JOIN categories c ON n.category_id = c.id 
        WHERE n.status = 'pending' 
        ORDER BY n.created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending_approvals = [];
}

// Son kullanıcılar
try {
    $recent_users = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_users = [];
}

// Zaman hesaplama fonksiyonu
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'yıl',
        'm' => 'ay',
        'w' => 'hafta',
        'd' => 'gün',
        'h' => 'saat',
        'i' => 'dakika',
        's' => 'saniye',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' önce' : 'şimdi';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - ONVIBES</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ONVIBES Tema Değişkenleri - Index ile Uyumlu */
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            background-attachment: fixed;
        }

        .dark-mode body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Header - Index ile Aynı Stil */
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
            padding: 10px 0;
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
            gap: 15px;
        }

        .logo-text {
            color: white;
            font-size: 24px;
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
        }
        
        @keyframes shimmer-text {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .admin-badge {
            background: rgba(255,255,255,0.2);
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .top-links {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .top-links button,
        .top-links a {
            background: rgba(255,255,255,0.15);
            border: none;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 25px;
            transition: all 0.3s;
            white-space: nowrap;
            font-weight: 600;
        }

        .top-links button:hover,
        .top-links a:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* Dashboard */
        .dashboard {
            padding: 30px 0;
        }

        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .dark-mode .page-title {
            color: var(--text);
        }

        .page-subtitle {
            color: var(--gray);
            font-size: 16px;
            margin-bottom: 30px;
        }

        /* İstatistik Kartları */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--light);
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            border-left: 5px solid var(--red);
        }

        .dark-mode .stat-card {
            background: var(--light);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            background: var(--red);
            opacity: 0.05;
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .stat-icon {
            font-size: 36px;
            color: var(--red);
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: var(--red);
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--gray);
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-change {
            margin-top: 10px;
            font-size: 12px;
            font-weight: 600;
        }

        .stat-change.positive {
            color: var(--green);
        }

        .stat-change.negative {
            color: var(--red);
        }

        /* Hızlı Erişim Kartları */
        .quick-access {
            margin-bottom: 30px;
        }

        .section-header {
            font-size: 22px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--red);
            position: relative;
        }

        .dark-mode .section-header {
            color: var(--text);
        }

        .section-header::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--orange);
        }

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .quick-card {
            background: var(--light);
            padding: 25px;
            border-radius: 16px;
            text-decoration: none;
            color: var(--text);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .dark-mode .quick-card {
            background: var(--light);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }

        .quick-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(210, 35, 42, 0.05), transparent);
            transition: left 0.6s;
        }

        .quick-card:hover::before {
            left: 100%;
        }

        .quick-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(210, 35, 42, 0.2);
            border-color: var(--red);
        }

        .quick-icon {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        .quick-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .dark-mode .quick-title {
            color: var(--text);
        }

        .quick-description {
            font-size: 13px;
            color: var(--gray);
            line-height: 1.5;
        }

        .quick-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--red);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .content-section {
            background: var(--light);
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
        }

        .dark-mode .content-section {
            background: var(--light);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }

        /* Liste Stilleri */
        .activity-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 15px;
            transition: background 0.3s;
        }

        .activity-item:hover {
            background: var(--surface);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: var(--red);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .dark-mode .activity-title {
            color: var(--text);
        }

        .activity-meta {
            font-size: 13px;
            color: var(--gray);
        }

        .activity-time {
            font-size: 12px;
            color: var(--gray);
            margin-top: 5px;
        }

        .activity-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-success {
            background: var(--green);
            color: white;
        }

        .btn-danger {
            background: var(--red);
            color: white;
        }

        .btn-primary {
            background: var(--blue);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-grid {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Scrollbar Styling */
        .activity-list::-webkit-scrollbar {
            width: 8px;
        }

        .activity-list::-webkit-scrollbar-track {
            background: var(--surface);
            border-radius: 4px;
        }

        .activity-list::-webkit-scrollbar-thumb {
            background: var(--red);
            border-radius: 4px;
        }

        .activity-list::-webkit-scrollbar-thumb:hover {
            background: #b81d24;
        }
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <!-- Header -->
    <header class="main-menu">
        <div class="top-bar">
            <div class="container">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <span class="logo-text">ONVIBES</span>
                    <span class="admin-badge">
                        <i class="fas fa-crown"></i> Admin Panel
                    </span>
                </div>
                <div class="top-links">
                    <span style="font-size: 13px; font-weight: 600;">
                        <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($username); ?>
                    </span>
                    <form method="POST" style="margin: 0;">
                        <button type="submit" name="toggle_theme">
                            <i class="fas fa-<?php echo $dark_mode ? 'sun' : 'moon'; ?>"></i>
                            <?php echo $dark_mode ? 'Aydınlık' : 'Karanlık'; ?>
                        </button>
                    </form>
                    <a href="../index.php">
                        <i class="fas fa-home"></i> Ana Sayfa
                    </a>
                    <a href="../Giris/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Çıkış
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Dashboard -->
    <div class="dashboard container">
        <h1 class="page-title">
            <i class="fas fa-tachometer-alt"></i> Kontrol Paneli
        </h1>
        <p class="page-subtitle">ONVIBES yönetim sistemi - Tüm kontroller elinizin altında</p>

        <!-- İstatistikler -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($total_users); ?></div>
                <div class="stat-label">Toplam Kullanıcı</div>
                <?php if($active_users > 0): ?>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i> <?php echo $active_users; ?> aktif (24s)
                </div>
                <?php endif; ?>
            </div>

            <div class="stat-card" style="border-left-color: var(--blue);">
                <div class="stat-icon" style="color: var(--blue);"><i class="fas fa-newspaper"></i></div>
                <div class="stat-number" style="color: var(--blue);"><?php echo number_format($total_news); ?></div>
                <div class="stat-label">Toplam Haber</div>
                <div class="stat-change positive">
                    <i class="fas fa-check"></i> <?php echo $approved_news; ?> onaylı
                </div>
            </div>

            <div class="stat-card" style="border-left-color: var(--orange);">
                <div class="stat-icon" style="color: var(--orange);"><i class="fas fa-clock"></i></div>
                <div class="stat-number" style="color: var(--orange);"><?php echo number_format($pending_news); ?></div>
                <div class="stat-label">Bekleyen Onay</div>
                <?php if($pending_news > 0): ?>
                <div class="stat-change negative">
                    <i class="fas fa-exclamation-triangle"></i> İncele
                </div>
                <?php endif; ?>
            </div>

            <div class="stat-card" style="border-left-color: var(--green);">
                <div class="stat-icon" style="color: var(--green);"><i class="fas fa-eye"></i></div>
                <div class="stat-number" style="color: var(--green);"><?php echo number_format($total_views); ?></div>
                <div class="stat-label">Toplam Görüntülenme</div>
            </div>

            <div class="stat-card" style="border-left-color: var(--purple);">
                <div class="stat-icon" style="color: var(--purple);"><i class="fas fa-comments"></i></div>
                <div class="stat-number" style="color: var(--purple);"><?php echo number_format($total_comments); ?></div>
                <div class="stat-label">Toplam Yorum</div>
            </div>

            <div class="stat-card" style="border-left-color: #e74c3c;">
                <div class="stat-icon" style="color: #e74c3c;"><i class="fas fa-pen-fancy"></i></div>
                <div class="stat-number" style="color: #e74c3c;">0</div>
                <div class="stat-label">Köşe Yazıları</div>
                <div class="stat-change" style="font-size: 12px;">
                    Modül aktif değil
                </div>
            </div>
        </div>

        <!-- Hızlı Erişim -->
        <div class="quick-access">
            <h2 class="section-header">
                <i class="fas fa-rocket"></i> Hızlı Erişim
            </h2>
            <div class="quick-grid">
                <a href="haber-kontrol.php" class="quick-card">
                    <?php if($pending_news > 0): ?>
                    <span class="quick-badge"><?php echo $pending_news; ?></span>
                    <?php endif; ?>
                    <i class="fas fa-newspaper quick-icon" style="color: var(--blue);"></i>
                    <div class="quick-title">Haber Kontrol</div>
                    <div class="quick-description">Haberleri onayla, reddet veya düzenle</div>
                </a>

                <a href="koseyazisi-kontrol.php" class="quick-card">
                    <?php if($pending_columns > 0): ?>
                    <span class="quick-badge"><?php echo $pending_columns; ?></span>
                    <?php endif; ?>
                    <i class="fas fa-pen-fancy quick-icon" style="color: var(--purple);"></i>
                    <div class="quick-title">Köşe Yazısı Kontrol</div>
                    <div class="quick-description">Köşe yazılarını yönet ve onayla</div>
                </a>

                <a href="kullanici-yonetimi.php" class="quick-card">
                    <i class="fas fa-users-cog quick-icon" style="color: var(--green);"></i>
                    <div class="quick-title">Kullanıcı Yönetimi</div>
                    <div class="quick-description">Kullanıcıları yönet ve yetki ver</div>
                </a>

                <a href="sifre-sifirlama-kontrol.php" class="quick-card">
                    <?php if($pending_password_resets > 0): ?>
                    <span class="quick-badge"><?php echo $pending_password_resets; ?></span>
                    <?php endif; ?>
                    <i class="fas fa-key quick-icon" style="color: #f39c12;"></i>
                    <div class="quick-title">Şifre Sıfırlama</div>
                    <div class="quick-description">Şifre sıfırlama taleplerini yönet</div>
                </a>

                <a href="yorum-kontrol.php" class="quick-card">
                    <i class="fas fa-comments quick-icon" style="color: var(--orange);"></i>
                    <div class="quick-title">Yorum Kontrol</div>
                    <div class="quick-description">Yorumları incele ve yönet</div>
                </a>

                <a href="kategoriler-kontrol.php" class="quick-card">
                    <i class="fas fa-folder-tree quick-icon" style="color: #e74c3c;"></i>
                    <div class="quick-title">Kategori Yönetimi</div>
                    <div class="quick-description">Kategorileri ekle, düzenle ve sil</div>
                </a>

                <a href="istatistikler.php" class="quick-card">
                    <i class="fas fa-chart-line quick-icon" style="color: #16a085;"></i>
                    <div class="quick-title">İstatistikler</div>
                    <div class="quick-description">Detaylı analiz ve raporlar</div>
                </a>

                <a href="ayarlar.php" class="quick-card">
                    <i class="fas fa-cog quick-icon" style="color: #95a5a6;"></i>
                    <div class="quick-title">Site Ayarları</div>
                    <div class="quick-description">Genel ayarlar ve bakım modu</div>
                </a>

                <a href="admin-profil.php" class="quick-card">
                    <i class="fas fa-user-shield quick-icon" style="color: var(--red);"></i>
                    <div class="quick-title">Admin Profili</div>
                    <div class="quick-description">Profil bilgilerini düzenle</div>
                </a>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Bekleyen Onaylar -->
            <div class="content-section">
                <h2 class="section-header">
                    <i class="fas fa-hourglass-half"></i> Bekleyen Onaylar
                </h2>
                <div class="activity-list">
                    <?php if(empty($pending_approvals)): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p style="font-size: 16px; font-weight: 600;">Harika! Bekleyen onay yok</p>
                            <p style="font-size: 14px; margin-top: 10px;">Tüm haberler kontrol edildi</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($pending_approvals as $news): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($news['title']); ?></div>
                                <div class="activity-meta">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($news['author_name']); ?>
                                    <i class="fas fa-folder" style="margin-left: 10px;"></i> <?php echo htmlspecialchars($news['category_name']); ?>
                                </div>
                                <div class="activity-time">
                                    <i class="far fa-clock"></i> <?php echo time_elapsed_string($news['created_at']); ?>
                                </div>
                                <div class="activity-actions">
                                    <a href="haber-kontrol.php?id=<?php echo $news['id']; ?>&action=approve" class="btn btn-success">
                                        <i class="fas fa-check"></i> Onayla
                                    </a>
                                    <a href="haber-kontrol.php?id=<?php echo $news['id']; ?>&action=view" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> İncele
                                    </a>
                                    <a href="haber-kontrol.php?id=<?php echo $news['id']; ?>&action=reject" class="btn btn-danger">
                                        <i class="fas fa-times"></i> Reddet
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Son Kullanıcılar -->
            <div class="content-section">
                <h2 class="section-header">
                    <i class="fas fa-user-plus"></i> Son Kayıt Olanlar
                </h2>
                <div class="activity-list">
                    <?php if(empty($recent_users)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p style="font-size: 16px; font-weight: 600;">Henüz kullanıcı yok</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($recent_users as $user): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: var(--green);">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($user['username']); ?></div>
                                <div class="activity-meta">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                                </div>
                                <div class="activity-time">
                                    <i class="far fa-clock"></i> <?php echo time_elapsed_string($user['created_at']); ?>
                                    <?php if($user['is_admin']): ?>
                                        <span style="color: var(--red); margin-left: 10px;">
                                            <i class="fas fa-crown"></i> Admin
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Son Haberler -->
        <div class="content-section" style="margin-bottom: 30px;">
            <h2 class="section-header">
                <i class="fas fa-newspaper"></i> Son Yayınlanan Haberler
            </h2>
            <div class="activity-list">
                <?php if(empty($recent_news)): ?>
                    <div class="empty-state">
                        <i class="fas fa-newspaper"></i>
                        <p style="font-size: 16px; font-weight: 600;">Henüz haber yok</p>
                    </div>
                <?php else: ?>
                    <?php foreach($recent_news as $news): ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background: var(--blue);">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?php echo htmlspecialchars($news['title']); ?></div>
                            <div class="activity-meta">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($news['author_name']); ?>
                                <i class="fas fa-folder" style="margin-left: 10px;"></i> <?php echo htmlspecialchars($news['category_name']); ?>
                                <i class="fas fa-eye" style="margin-left: 10px;"></i> <?php echo number_format($news['view_count']); ?> görüntülenme
                            </div>
                            <div class="activity-time">
                                <i class="far fa-clock"></i> <?php echo time_elapsed_string($news['created_at']); ?>
                                <span style="margin-left: 15px; padding: 3px 8px; background: <?php echo $news['status'] == 'approved' ? 'var(--green)' : 'var(--orange)'; ?>; color: white; border-radius: 10px; font-size: 11px;">
                                    <?php echo $news['status'] == 'approved' ? 'Yayında' : 'Beklemede'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Otomatik yenileme (5 dakikada bir)
        setInterval(function() {
            location.reload();
        }, 300000); // 5 dakika
    </script>
</body>
</html>
