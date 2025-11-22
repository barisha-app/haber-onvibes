<?php
session_start();

// Giriş kontrolü
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: Giris/login.php');
    exit();
}

// Yazar kontrolü - SADECE YAZARLAR BU SAYFAYI GÖREBİLİR
if(!isset($_SESSION['role']) || ($_SESSION['role'] != 'writer' && $_SESSION['role'] != 'admin')) {
    header('Location: index.php?error=access_denied');
    exit();
}

include 'config.php';
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Kullanıcı bilgilerini çek
try {
    $query = "SELECT * FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user_data = [];
}

// İstatistikler
try {
    // Toplam haber sayısı
    $total_news = $db->prepare("SELECT COUNT(*) FROM news WHERE author_id = ?");
    $total_news->execute([$user_id]);
    $news_count = $total_news->fetchColumn() ?: 0;
    
    // Toplam köşe yazısı
    $total_columns = $db->prepare("SELECT COUNT(*) FROM columns WHERE author_id = ?");
    $total_columns->execute([$user_id]);
    $columns_count = $total_columns->fetchColumn() ?: 0;
    
    // Toplam görüntülenme
    $total_views_news = $db->prepare("SELECT COALESCE(SUM(view_count), 0) FROM news WHERE author_id = ?");
    $total_views_news->execute([$user_id]);
    $views_news = $total_views_news->fetchColumn() ?: 0;
    
    $total_views_columns = $db->prepare("SELECT COALESCE(SUM(view_count), 0) FROM columns WHERE author_id = ?");
    $total_views_columns->execute([$user_id]);
    $views_columns = $total_views_columns->fetchColumn() ?: 0;
    
    $total_views = $views_news + $views_columns;
    
    // Bekleyen içerikler
    $pending_news = $db->prepare("SELECT COUNT(*) FROM news WHERE author_id = ? AND status = 'pending'");
    $pending_news->execute([$user_id]);
    $pending_news_count = $pending_news->fetchColumn() ?: 0;
    
    $pending_columns = $db->prepare("SELECT COUNT(*) FROM columns WHERE author_id = ? AND status = 'pending'");
    $pending_columns->execute([$user_id]);
    $pending_columns_count = $pending_columns->fetchColumn() ?: 0;
    
} catch (PDOException $e) {
    $news_count = $columns_count = $total_views = $pending_news_count = $pending_columns_count = 0;
}

// Son haberler
try {
    $recent_news = $db->prepare("SELECT * FROM news WHERE author_id = ? ORDER BY created_at DESC LIMIT 5");
    $recent_news->execute([$user_id]);
    $news_list = $recent_news->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $news_list = [];
}

// Son köşe yazıları
try {
    $recent_columns = $db->prepare("SELECT * FROM columns WHERE author_id = ? ORDER BY created_at DESC LIMIT 5");
    $recent_columns->execute([$user_id]);
    $columns_list = $recent_columns->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $columns_list = [];
}

function time_elapsed_string($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' yıl önce';
    if ($diff->m > 0) return $diff->m . ' ay önce';
    if ($diff->d > 0) return $diff->d . ' gün önce';
    if ($diff->h > 0) return $diff->h . ' saat önce';
    if ($diff->i > 0) return $diff->i . ' dakika önce';
    return 'az önce';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yazar Paneli - ONVIBES</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
:root{--red:#d2232a;--dark:#2c3e50;--light:#fff;--border:#e0e0e0;--surface:#f8f9fa;--text:#333;--gray:#666;--green:#0a8c2f;--blue:#3498db;--orange:#e67e22;--purple:#9b59b6}
.dark-mode{--red:#ff6b6b;--dark:#0a0a0a;--light:#1a1a1a;--border:#333;--surface:#1a1a1a;--text:#f0f0f0;--gray:#a0a0a0}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
body{background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);color:var(--text);line-height:1.6;min-height:100vh}
.dark-mode body{background:linear-gradient(135deg,#0a0a0a 0%,#1a1a1a 100%)}
.container{max-width:1400px;margin:0 auto;padding:0 15px}
.top-bar{background:linear-gradient(135deg,var(--red) 0%,#b81d24 100%);color:#fff;padding:10px 0}
.top-bar .container{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px}
.logo-text{color:#fff;font-size:24px;font-weight:800;text-transform:uppercase;text-decoration:none}
.writer-badge{background:rgba(255,255,255,.2);padding:6px 15px;border-radius:20px;font-size:13px;font-weight:600}
.top-links{display:flex;gap:10px;align-items:center}
.top-links button,.top-links a{background:rgba(255,255,255,.15);border:none;color:#fff;text-decoration:none;padding:8px 16px;border-radius:25px;font-size:13px;font-weight:600;cursor:pointer;transition:all .3s}
.top-links button:hover,.top-links a:hover{background:rgba(255,255,255,.25);transform:translateY(-2px)}
.main-content{padding:30px 0}
.page-title{font-size:32px;font-weight:800;color:var(--dark);margin-bottom:30px}
.dark-mode .page-title{color:var(--text)}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:40px}
.stat-card{background:var(--light);padding:25px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);border-left:4px solid var(--purple)}
.dark-mode .stat-card{background:var(--light)}
.stat-icon{font-size:36px;color:var(--purple);margin-bottom:10px}
.stat-number{font-size:32px;font-weight:800;color:var(--purple);margin-bottom:5px}
.stat-label{color:var(--gray);font-size:13px;font-weight:600;text-transform:uppercase}
.quick-actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:40px}
.action-card{background:var(--light);padding:25px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);text-align:center;text-decoration:none;color:var(--text);transition:all .3s;display:block}
.dark-mode .action-card{background:var(--light)}
.action-card:hover{transform:translateY(-5px);box-shadow:0 8px 24px rgba(0,0,0,.15)}
.action-icon{font-size:48px;margin-bottom:15px;color:var(--purple)}
.action-title{font-size:18px;font-weight:700;color:var(--dark);margin-bottom:5px}
.dark-mode .action-title{color:var(--text)}
.action-desc{font-size:13px;color:var(--gray)}
.content-section{background:var(--light);padding:25px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);margin-bottom:30px}
.dark-mode .content-section{background:var(--light)}
.section-header{font-size:20px;font-weight:700;color:var(--dark);margin-bottom:20px;display:flex;align-items:center;gap:10px}
.dark-mode .section-header{color:var(--text)}
.content-item{padding:15px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;transition:all .3s}
.content-item:hover{background:var(--surface)}
.content-item:last-child{border-bottom:none}
.item-info h3{font-size:16px;font-weight:600;color:var(--dark);margin-bottom:5px}
.dark-mode .item-info h3{color:var(--text)}
.item-meta{font-size:13px;color:var(--gray)}
.status-badge{padding:4px 10px;border-radius:15px;font-size:11px;font-weight:600;display:inline-block;margin-left:10px}
.status-pending{background:#ffc107;color:#000}
.status-approved{background:#28a745;color:#fff}
.status-rejected{background:#dc3545;color:#fff}
.empty-msg{text-align:center;padding:40px;color:var(--gray)}
footer{background:var(--dark);color:#fff;padding:30px 0;text-align:center;margin-top:50px}
@media (max-width:768px){.stats-grid,.quick-actions{grid-template-columns:1fr}.page-title{font-size:24px}}
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <header>
        <div class="top-bar">
            <div class="container">
                <div style="display:flex;align-items:center;gap:15px">
                    <a href="index.php" class="logo-text">HABER|ONVIBES</a>
                    <span class="writer-badge"><i class="fas fa-pen-fancy"></i> Yazar Paneli</span>
                </div>
                <div class="top-links">
                    <form method="POST" style="margin:0">
                        <button type="submit" name="toggle_theme">
                            <i class="fas fa-<?php echo $dark_mode ? 'sun' : 'moon'; ?>"></i>
                        </button>
                    </form>
                    <a href="index.php"><i class="fas fa-home"></i> Ana Sayfa</a>
                    <a href="Profil/profil.php"><i class="fas fa-user"></i> Profil</a>
                    <a href="Giris/logout.php"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-content container">
        <h1 class="page-title">
            <i class="fas fa-user-edit"></i> Hoş Geldin, <?php echo htmlspecialchars($user_data['full_name'] ?: $username); ?>!
        </h1>

        <!-- İstatistikler -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-newspaper"></i></div>
                <div class="stat-number"><?php echo $news_count; ?></div>
                <div class="stat-label">Toplam Haber</div>
            </div>
            <div class="stat-card" style="border-left-color:var(--green)">
                <div class="stat-icon" style="color:var(--green)"><i class="fas fa-pen-fancy"></i></div>
                <div class="stat-number" style="color:var(--green)"><?php echo $columns_count; ?></div>
                <div class="stat-label">Köşe Yazısı</div>
            </div>
            <div class="stat-card" style="border-left-color:var(--blue)">
                <div class="stat-icon" style="color:var(--blue)"><i class="fas fa-eye"></i></div>
                <div class="stat-number" style="color:var(--blue)"><?php echo number_format($total_views); ?></div>
                <div class="stat-label">Toplam Görüntülenme</div>
            </div>
            <div class="stat-card" style="border-left-color:var(--orange)">
                <div class="stat-icon" style="color:var(--orange)"><i class="fas fa-clock"></i></div>
                <div class="stat-number" style="color:var(--orange)"><?php echo $pending_news_count + $pending_columns_count; ?></div>
                <div class="stat-label">Bekleyen Onay</div>
            </div>
        </div>

        <!-- Hızlı İşlemler -->
        <div class="quick-actions">
            <a href="Veri/haber-edit.php" class="action-card">
                <div class="action-icon"><i class="fas fa-newspaper"></i></div>
                <div class="action-title">Haber Yaz</div>
                <div class="action-desc">Yeni haber ekle ve yayınla</div>
            </a>
            <a href="kose-yazisi-edit.php" class="action-card">
                <div class="action-icon" style="color:var(--green)"><i class="fas fa-pen-fancy"></i></div>
                <div class="action-title">Köşe Yazısı Yaz</div>
                <div class="action-desc">Yeni köşe yazısı oluştur</div>
            </a>
            <a href="kose-yazilari.php" class="action-card">
                <div class="action-icon" style="color:var(--blue)"><i class="fas fa-book-open"></i></div>
                <div class="action-title">Köşe Yazıları</div>
                <div class="action-desc">Tüm köşe yazılarını görüntüle</div>
            </a>
            <a href="Profil/profil-duzenle.php" class="action-card">
                <div class="action-icon" style="color:var(--orange)"><i class="fas fa-user-cog"></i></div>
                <div class="action-title">Profil Ayarları</div>
                <div class="action-desc">Profil bilgilerini düzenle</div>
            </a>
        </div>

        <!-- Son Haberler -->
        <div class="content-section">
            <div class="section-header">
                <i class="fas fa-newspaper"></i> Son Haberlerim
            </div>
            <?php if (empty($news_list)): ?>
                <div class="empty-msg">Henüz haber eklemediniz</div>
            <?php else: ?>
                <?php foreach ($news_list as $news): ?>
                <div class="content-item">
                    <div class="item-info">
                        <h3><?php echo htmlspecialchars($news['title']); ?></h3>
                        <div class="item-meta">
                            <i class="far fa-clock"></i> <?php echo time_elapsed_string($news['created_at']); ?>
                            <span class="status-badge status-<?php echo $news['status']; ?>">
                                <?php 
                                if($news['status'] == 'pending') echo 'Bekliyor';
                                elseif($news['status'] == 'approved') echo 'Yayında';
                                else echo 'Reddedildi';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Son Köşe Yazıları -->
        <div class="content-section">
            <div class="section-header">
                <i class="fas fa-pen-fancy"></i> Son Köşe Yazılarım
            </div>
            <?php if (empty($columns_list)): ?>
                <div class="empty-msg">Henüz köşe yazısı eklemediniz</div>
            <?php else: ?>
                <?php foreach ($columns_list as $column): ?>
                <div class="content-item">
                    <div class="item-info">
                        <h3><?php echo htmlspecialchars($column['title']); ?></h3>
                        <div class="item-meta">
                            <i class="far fa-clock"></i> <?php echo time_elapsed_string($column['created_at']); ?>
                            <span class="status-badge status-<?php echo $column['status']; ?>">
                                <?php 
                                if($column['status'] == 'pending') echo 'Bekliyor';
                                elseif($column['status'] == 'approved') echo 'Yayında';
                                else echo 'Reddedildi';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; 2024 ONVIBES - Tüm hakları saklıdır.</p>
        </div>
    </footer>
</body>
</html>
