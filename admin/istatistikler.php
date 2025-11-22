<?php
session_start();
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../Giris/login.php');
    exit();
}
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../index.php');
    exit();
}

include '../config.php';
$database = new Database();
$db = $database->getConnection();

if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// İstatistikler
try {
    $total_news = $db->query("SELECT COUNT(*) FROM news")->fetchColumn();
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_comments = $db->query("SELECT COUNT(*) FROM comments")->fetchColumn() ?: 0;
    $total_views = $db->query("SELECT SUM(view_count) FROM news")->fetchColumn() ?: 0;
    
    // Bugünkü istatistikler
    $today_news = $db->query("SELECT COUNT(*) FROM news WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $today_users = $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $today_comments = $db->query("SELECT COUNT(*) FROM comments WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
    
    // En çok okunan haberler
    $top_news = $db->query("SELECT title, view_count FROM news ORDER BY view_count DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
    // Kategori dağılımı
    $cat_stats = $db->query("SELECT c.name, COUNT(n.id) as count FROM categories c LEFT JOIN news n ON c.id = n.category_id GROUP BY c.id")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $total_news = $total_users = $total_comments = $total_views = 0;
    $today_news = $today_users = $today_comments = 0;
    $top_news = [];
    $cat_stats = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İstatistikler - ONVIBES Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--red:#d2232a;--dark:#2c3e50;--light:#fff;--border:#e0e0e0;--surface:#f8f9fa;--text:#333;--gray:#666;--green:#0a8c2f;--blue:#3498db;--orange:#e67e22;--purple:#9b59b6}
        .dark-mode{--red:#ff6b6b;--dark:#0a0a0a;--light:#1a1a1a;--border:#333;--surface:#1a1a1a;--text:#f0f0f0;--gray:#a0a0a0}
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
        body{background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);color:var(--text);min-height:100vh;background-attachment:fixed}
        .dark-mode body{background:linear-gradient(135deg,#0a0a0a 0%,#1a1a1a 100%)}
        .container{max-width:1400px;margin:0 auto;padding:0 15px}
        .main-menu{background:var(--light);box-shadow:0 4px 20px rgba(0,0,0,.08);position:sticky;top:0;z-index:1000}
        .top-bar{background:linear-gradient(135deg,var(--red) 0%,#b81d24 100%);color:#fff;padding:10px 0}
        .top-bar .container{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px}
        .logo-text{color:#fff;font-size:24px;font-weight:800;text-transform:uppercase}
        .admin-badge{background:rgba(255,255,255,.2);padding:6px 15px;border-radius:20px;font-size:13px;font-weight:600}
        .top-links{display:flex;gap:10px;align-items:center}
        .top-links button,.top-links a{background:rgba(255,255,255,.15);border:none;color:#fff;text-decoration:none;padding:8px 16px;border-radius:25px;font-size:13px;font-weight:600;cursor:pointer;transition:all .3s}
        .main-content{padding:30px 0}
        .page-title{font-size:32px;font-weight:800;color:var(--dark);margin-bottom:10px}
        .dark-mode .page-title{color:var(--text)}
        .breadcrumb{display:flex;gap:10px;align-items:center;color:var(--gray);font-size:14px;margin-bottom:30px}
        .breadcrumb a{color:var(--red);text-decoration:none}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:30px}
        .stat-card{background:var(--light);padding:25px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);text-align:center;border-top:4px solid var(--red)}
        .dark-mode .stat-card{background:var(--light)}
        .stat-icon{font-size:48px;margin-bottom:15px}
        .stat-number{font-size:36px;font-weight:800;margin-bottom:8px}
        .stat-label{color:var(--gray);font-size:13px;font-weight:600;text-transform:uppercase}
        .stat-change{margin-top:10px;font-size:12px;font-weight:600}
        .card{background:var(--light);padding:25px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);margin-bottom:25px}
        .dark-mode .card{background:var(--light)}
        .card-title{font-size:20px;font-weight:700;margin-bottom:20px;color:var(--dark);border-bottom:3px solid var(--red);padding-bottom:10px}
        .dark-mode .card-title{color:var(--text)}
        .top-list{list-style:none}
        .top-list li{padding:12px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
        .top-list li:hover{background:var(--surface)}
        .rank{background:var(--red);color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px}
        .grid-2{display:grid;grid-template-columns:2fr 1fr;gap:25px}
        @media (max-width:768px){.grid-2{grid-template-columns:1fr}}
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <header class="main-menu">
        <div class="top-bar">
            <div class="container">
                <div style="display:flex;align-items:center;gap:15px">
                    <span class="logo-text">ONVIBES</span>
                    <span class="admin-badge"><i class="fas fa-crown"></i> Admin Panel</span>
                </div>
                <div class="top-links">
                    <form method="POST" style="margin:0">
                        <button type="submit" name="toggle_theme">
                            <i class="fas fa-<?php echo $dark_mode ? 'sun' : 'moon'; ?>"></i>
                        </button>
                    </form>
                    <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="../index.php"><i class="fas fa-home"></i> Ana Sayfa</a>
                    <a href="../Giris/logout.php"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-content container">
        <h1 class="page-title"><i class="fas fa-chart-line"></i> İstatistikler ve Raporlar</h1>
        <div class="breadcrumb">
            <a href="admin.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>İstatistikler</span>
        </div>

        <h3 style="font-size:18px;font-weight:700;margin-bottom:15px;color:var(--dark)">
            <i class="fas fa-globe"></i> Genel İstatistikler
        </h3>
        <div class="stats-grid">
            <div class="stat-card" style="border-top-color:var(--blue)">
                <div class="stat-icon" style="color:var(--blue)"><i class="fas fa-newspaper"></i></div>
                <div class="stat-number" style="color:var(--blue)"><?php echo number_format($total_news); ?></div>
                <div class="stat-label">Toplam Haber</div>
                <div class="stat-change" style="color:var(--green)"><i class="fas fa-arrow-up"></i> +<?php echo $today_news; ?> bugün</div>
            </div>
            <div class="stat-card" style="border-top-color:var(--green)">
                <div class="stat-icon" style="color:var(--green)"><i class="fas fa-users"></i></div>
                <div class="stat-number" style="color:var(--green)"><?php echo number_format($total_users); ?></div>
                <div class="stat-label">Toplam Kullanıcı</div>
                <div class="stat-change" style="color:var(--green)"><i class="fas fa-arrow-up"></i> +<?php echo $today_users; ?> bugün</div>
            </div>
            <div class="stat-card" style="border-top-color:var(--orange)">
                <div class="stat-icon" style="color:var(--orange)"><i class="fas fa-comments"></i></div>
                <div class="stat-number" style="color:var(--orange)"><?php echo number_format($total_comments); ?></div>
                <div class="stat-label">Toplam Yorum</div>
                <div class="stat-change" style="color:var(--green)"><i class="fas fa-arrow-up"></i> +<?php echo $today_comments; ?> bugün</div>
            </div>
            <div class="stat-card" style="border-top-color:var(--purple)">
                <div class="stat-icon" style="color:var(--purple)"><i class="fas fa-eye"></i></div>
                <div class="stat-number" style="color:var(--purple)"><?php echo number_format($total_views); ?></div>
                <div class="stat-label">Toplam Görüntülenme</div>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <h2 class="card-title"><i class="fas fa-fire"></i> En Çok Okunan Haberler</h2>
                <ul class="top-list">
                    <?php $rank = 1; foreach ($top_news as $news): ?>
                    <li>
                        <div style="display:flex;align-items:center;gap:12px;flex:1">
                            <span class="rank"><?php echo $rank++; ?></span>
                            <span style="font-weight:600"><?php echo htmlspecialchars(substr($news['title'], 0, 50)); ?>...</span>
                        </div>
                        <span style="color:var(--red);font-weight:700">
                            <i class="far fa-eye"></i> <?php echo number_format($news['view_count']); ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="card">
                <h2 class="card-title"><i class="fas fa-folder"></i> Kategori Dağılımı</h2>
                <ul class="top-list">
                    <?php foreach ($cat_stats as $cat): ?>
                    <li>
                        <span style="font-weight:600"><?php echo htmlspecialchars($cat['name']); ?></span>
                        <span style="background:var(--red);color:#fff;padding:4px 12px;border-radius:12px;font-size:13px;font-weight:700">
                            <?php echo $cat['count']; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
