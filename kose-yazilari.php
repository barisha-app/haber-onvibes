<?php
session_start();
include 'config.php';

$database = new Database();
$db = $database->getConnection();

if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Filtreleme
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Kategorileri çek
try {
    $categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Köşe yazılarını çek
try {
    $query = "SELECT c.*, u.username, u.full_name, u.profile_photo 
              FROM columns c 
              LEFT JOIN users u ON c.author_id = u.id 
              WHERE c.status = 'approved'";
    
    if ($category_filter != 'all') {
        $query .= " AND c.category_id = :category_id";
    }
    
    if ($date_filter == 'today') {
        $query .= " AND DATE(c.created_at) = CURDATE()";
    } elseif ($date_filter == 'week') {
        $query .= " AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($date_filter == 'month') {
        $query .= " AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
    
    if (!empty($search)) {
        $query .= " AND (c.title LIKE :search OR c.content LIKE :search OR u.username LIKE :search)";
    }
    
    $query .= " ORDER BY c.created_at DESC";
    
    $stmt = $db->prepare($query);
    
    if ($category_filter != 'all') {
        $stmt->bindParam(':category_id', $category_filter, PDO::PARAM_INT);
    }
    
    if (!empty($search)) {
        $search_param = "%{$search}%";
        $stmt->bindParam(':search', $search_param);
    }
    
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $columns = [];
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
    <title>Köşe Yazıları - ONVIBES</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
:root{--red:#d2232a;--dark:#2c3e50;--light:#fff;--border:#e0e0e0;--surface:#f8f9fa;--text:#333;--gray:#666;--green:#0a8c2f;--blue:#3498db;--orange:#e67e22;--purple:#9b59b6}
.dark-mode{--red:#ff6b6b;--dark:#0a0a0a;--light:#1a1a1a;--border:#333;--surface:#1a1a1a;--text:#f0f0f0;--gray:#a0a0a0}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
body{background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);color:var(--text);line-height:1.6;min-height:100vh}
.dark-mode body{background:linear-gradient(135deg,#0a0a0a 0%,#1a1a1a 100%)}
.container{max-width:1200px;margin:0 auto;padding:0 15px}
.top-bar{background:linear-gradient(135deg,var(--red) 0%,#b81d24 100%);color:#fff;padding:10px 0}
.top-bar .container{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px}
.logo-text{color:#fff;font-size:24px;font-weight:800;text-transform:uppercase;text-decoration:none}
.top-links{display:flex;gap:10px;align-items:center}
.top-links button,.top-links a{background:rgba(255,255,255,.15);border:none;color:#fff;text-decoration:none;padding:8px 16px;border-radius:25px;font-size:13px;font-weight:600;cursor:pointer;transition:all .3s}
.top-links button:hover,.top-links a:hover{background:rgba(255,255,255,.25);transform:translateY(-2px)}
.page-header{background:var(--light);padding:30px 0;margin-bottom:30px;box-shadow:0 4px 12px rgba(0,0,0,.08)}
.dark-mode .page-header{background:var(--light)}
.page-title{font-size:36px;font-weight:800;color:var(--dark);margin-bottom:10px}
.dark-mode .page-title{color:var(--text)}
.page-subtitle{color:var(--gray);font-size:16px}
.filters{background:var(--light);padding:20px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);margin-bottom:30px;display:flex;gap:15px;flex-wrap:wrap;align-items:center}
.dark-mode .filters{background:var(--light)}
.filter-group{flex:1;min-width:200px}
.filter-group label{display:block;margin-bottom:8px;font-weight:600;font-size:13px;color:var(--dark)}
.dark-mode .filter-group label{color:var(--text)}
.filter-group select,.filter-group input{width:100%;padding:10px 15px;border:2px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-size:14px}
.btn{padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.btn-primary{background:var(--red);color:#fff}
.btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.15)}
.columns-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:25px;margin-bottom:30px}
.column-card{background:var(--light);border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.08);transition:all .3s}
.dark-mode .column-card{background:var(--light)}
.column-card:hover{transform:translateY(-5px);box-shadow:0 8px 24px rgba(0,0,0,.15)}
.column-image{width:100%;height:200px;object-fit:cover;background:var(--surface)}
.column-content{padding:20px}
.column-category{display:inline-block;background:var(--purple);color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:10px}
.column-title{font-size:20px;font-weight:700;color:var(--dark);margin-bottom:10px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.dark-mode .column-title{color:var(--text)}
.column-excerpt{color:var(--gray);font-size:14px;line-height:1.6;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:15px}
.column-meta{display:flex;align-items:center;justify-content:space-between;padding-top:15px;border-top:1px solid var(--border)}
.author-info{display:flex;align-items:center;gap:10px}
.author-photo{width:35px;height:35px;border-radius:50%;object-fit:cover;background:var(--surface)}
.author-name{font-weight:600;font-size:14px;color:var(--dark)}
.dark-mode .author-name{color:var(--text)}
.column-date{font-size:12px;color:var(--gray)}
.empty-state{text-align:center;padding:80px 20px;color:var(--gray)}
.empty-state i{font-size:64px;margin-bottom:20px;opacity:.3}
.write-btn{position:fixed;bottom:30px;right:30px;width:60px;height:60px;border-radius:50%;background:var(--purple);color:#fff;border:none;font-size:24px;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,.2);transition:all .3s;z-index:999}
.write-btn:hover{transform:scale(1.1);box-shadow:0 6px 20px rgba(0,0,0,.3)}
footer{background:var(--dark);color:#fff;padding:30px 0;text-align:center;margin-top:50px}
@media (max-width:768px){.columns-grid{grid-template-columns:1fr}.page-title{font-size:28px}}
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <header>
        <div class="top-bar">
            <div class="container">
                <a href="index.php" class="logo-text">HABER|ONVIBES</a>
                <div class="top-links">
                    <form method="POST" style="margin:0">
                        <button type="submit" name="toggle_theme">
                            <i class="fas fa-<?php echo $dark_mode ? 'sun' : 'moon'; ?>"></i>
                        </button>
                    </form>
                    <a href="index.php"><i class="fas fa-home"></i> Ana Sayfa</a>
                    <?php if(isset($_SESSION['loggedin']) && $_SESSION['loggedin']): ?>
                        <a href="Profil/profil.php"><i class="fas fa-user"></i> Profil</a>
                        <a href="Giris/logout.php"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
                    <?php else: ?>
                        <a href="Giris/login.php"><i class="fas fa-sign-in-alt"></i> Giriş</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="page-header">
        <div class="container">
            <h1 class="page-title"><i class="fas fa-pen-fancy"></i> Köşe Yazıları</h1>
            <p class="page-subtitle">Yazarlarımızın kaleme aldığı düşündürücü yazılar</p>
        </div>
    </div>

    <div class="container">
        <form method="GET" class="filters">
            <div class="filter-group">
                <label><i class="fas fa-folder"></i> Kategori</label>
                <select name="category" onchange="this.form.submit()">
                    <option value="all" <?php echo $category_filter == 'all' ? 'selected' : ''; ?>>Tüm Kategoriler</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Tarih</label>
                <select name="date" onchange="this.form.submit()">
                    <option value="all" <?php echo $date_filter == 'all' ? 'selected' : ''; ?>>Tüm Zamanlar</option>
                    <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Bugün</option>
                    <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>Bu Hafta</option>
                    <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>Bu Ay</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-search"></i> Arama</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Başlık veya yazar...">
            </div>
            
            <div class="filter-group" style="flex:0">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Ara</button>
            </div>
        </form>

        <?php if (empty($columns)): ?>
            <div class="empty-state">
                <i class="fas fa-pen-fancy"></i>
                <p style="font-size:20px;font-weight:600">Henüz köşe yazısı bulunmuyor</p>
                <p>Filtreleri değiştirerek tekrar deneyin</p>
            </div>
        <?php else: ?>
            <div class="columns-grid">
                <?php foreach ($columns as $column): ?>
                <div class="column-card">
                    <?php if ($column['image']): ?>
                    <img src="<?php echo htmlspecialchars($column['image']); ?>" alt="<?php echo htmlspecialchars($column['title']); ?>" class="column-image">
                    <?php endif; ?>
                    
                    <div class="column-content">
                        <span class="column-category"><i class="fas fa-tag"></i> Köşe Yazısı</span>
                        <h2 class="column-title"><?php echo htmlspecialchars($column['title']); ?></h2>
                        <p class="column-excerpt"><?php echo htmlspecialchars(mb_substr(strip_tags($column['content']), 0, 150)); ?>...</p>
                        
                        <div class="column-meta">
                            <div class="author-info">
                                <?php if ($column['profile_photo']): ?>
                                <img src="<?php echo htmlspecialchars($column['profile_photo']); ?>" alt="<?php echo htmlspecialchars($column['username']); ?>" class="author-photo">
                                <?php else: ?>
                                <div class="author-photo" style="display:flex;align-items:center;justify-content:center;background:var(--purple);color:#fff;font-weight:bold;">
                                    <?php echo strtoupper(substr($column['username'], 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <div class="author-name"><?php echo htmlspecialchars($column['full_name'] ?: $column['username']); ?></div>
                                    <div class="column-date"><i class="far fa-clock"></i> <?php echo time_elapsed_string($column['created_at']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if(isset($_SESSION['loggedin']) && (isset($_SESSION['is_writer']) && $_SESSION['is_writer'] == 1 || isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1)): ?>
    <a href="kose-yazisi-edit.php" class="write-btn" title="Köşe Yazısı Yaz">
        <i class="fas fa-pen"></i>
    </a>
    <?php endif; ?>

    <footer>
        <div class="container">
            <p>&copy; 2024 ONVIBES - Tüm hakları saklıdır.</p>
        </div>
    </footer>
</body>
</html>
