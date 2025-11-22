<?php
// haberler.php - Tüm Haberler Listesi (Düzeltilmiş Versiyon)
// Bu dosya color sütun hatası çözülmüştür
session_start();
include '../config.php';

$database = new Database();
$db = $database->getConnection();

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF'] . (isset($_GET['category']) ? '?category=' . $_GET['category'] : '') . (isset($_GET['page']) ? '&page=' . $_GET['page'] : '') . (isset($_GET['search']) ? '&search=' . $_GET['search'] : ''));
    exit();
}

$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Sayfalama ayarları
$per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Filtreleme
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search_filter = isset($_GET['search']) ? trim($_GET['search']) : '';

// Kategorileri çek
try {
    $categories_query = "SELECT * FROM categories ORDER BY name ASC";
    $categories_stmt = $db->prepare($categories_query);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Haber sorgusunu hazırla
$where_conditions = ["n.status = 'published'"];
$params = [];

if (!empty($category_filter)) {
    $where_conditions[] = "n.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($search_filter)) {
    $where_conditions[] = "(n.title LIKE ? OR n.content LIKE ?)";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
}

$where_sql = implode(' AND ', $where_conditions);

// Toplam haber sayısı
$count_query = "SELECT COUNT(*) as total FROM news n WHERE $where_sql";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_news = $count_stmt->fetch()['total'];
$total_pages = ceil($total_news / $per_page);

// Haberleri çek - c.color kaldırıldı
$news_query = "SELECT n.*, c.name as category_name
               FROM news n
               LEFT JOIN categories c ON n.category_id = c.id
               LEFT JOIN users u ON n.author_id = u.id
               WHERE $where_sql
               ORDER BY n.created_at DESC
               LIMIT $per_page OFFSET $offset";
$news_stmt = $db->prepare($news_query);
$news_stmt->execute($params);
$news_list = $news_stmt->fetchAll();

// Popüler haberler (sidebar için) - c.color kaldırıldı
$popular_query = "SELECT n.*, c.name as category_name
                  FROM news n
                  LEFT JOIN categories c ON n.category_id = c.id
                  WHERE n.status = 'published'
                  ORDER BY n.views DESC
                  LIMIT 5";
$popular_stmt = $db->prepare($popular_query);
$popular_stmt->execute();
$popular_news = $popular_stmt->fetchAll();

// Son haberler (sidebar için) - c.color kaldırıldı
$recent_query = "SELECT n.*, c.name as category_name
                 FROM news n
                 LEFT JOIN categories c ON n.category_id = c.id
                 WHERE n.status = 'published'
                 ORDER BY n.created_at DESC
                 LIMIT 5";
$recent_stmt = $db->prepare($recent_query);
$recent_stmt->execute();
$recent_news = $recent_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tüm Haberler - ONVIBES</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ONVIBES Tema Değişkenleri */
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
        }

        body {
            background: var(--surface);
            color: var(--text);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Header */
        .main-menu {
            background: var(--light);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .top-bar {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 8px 0;
        }

        .top-bar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .logo-text {
            color: white;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
        }

        .top-links {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .top-links button,
        .top-links a {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .top-links button:hover,
        .top-links a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--dark) 0%, var(--red) 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 10px;
            text-align: center;
        }

        .page-subtitle {
            text-align: center;
            font-size: 16px;
            opacity: 0.9;
        }

        /* Filter Section */
        .filter-section {
            background: var(--light);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 1fr 2fr auto;
            gap: 15px;
            align-items: center;
        }

        .filter-label {
            font-weight: 700;
            color: var(--text);
            font-size: 14px;
        }

        .filter-select,
        .search-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text);
            font-size: 14px;
            transition: all 0.3s;
        }

        .filter-select:focus,
        .search-input:focus {
            outline: none;
            border-color: var(--red);
        }

        .search-box {
            display: flex;
            gap: 10px;
        }

        .search-btn,
        .reset-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .search-btn {
            background: var(--red);
            color: white;
        }

        .search-btn:hover {
            background: #b81d24;
            transform: translateY(-2px);
        }

        .reset-btn {
            background: var(--gray);
            color: white;
        }

        .reset-btn:hover {
            background: #555;
        }

        /* Main Content */
        .main-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            margin-bottom: 40px;
        }

        /* News Grid */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .news-card {
            background: var(--light);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
        }

        .news-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .news-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--dark), var(--red));
        }

        .news-content {
            padding: 20px;
        }

        .news-category {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
            color: white;
        }

        .news-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .news-excerpt {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .news-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: var(--gray);
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .news-author {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .news-date {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Sidebar */
        .sidebar {
            position: sticky;
            top: 80px;
            height: fit-content;
        }

        .sidebar-widget {
            background: var(--light);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .widget-title {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--red);
            color: var(--text);
        }

        .sidebar-news-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.3s;
        }

        .sidebar-news-item:last-child {
            border-bottom: none;
        }

        .sidebar-news-item:hover {
            background: var(--surface);
            padding-left: 5px;
        }

        .sidebar-news-image {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--dark), var(--red));
        }

        .sidebar-news-content {
            flex: 1;
        }

        .sidebar-news-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .sidebar-news-date {
            font-size: 12px;
            color: var(--gray);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 40px 0;
            flex-wrap: wrap;
        }

        .page-btn {
            padding: 10px 15px;
            background: var(--light);
            border: 2px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            color: var(--text);
            text-decoration: none;
            transition: all 0.3s;
        }

        .page-btn:hover {
            background: var(--red);
            color: white;
            border-color: var(--red);
        }

        .page-btn.active {
            background: var(--red);
            color: white;
            border-color: var(--red);
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: var(--light);
            border-radius: 12px;
            grid-column: 1 / -1;
        }

        .no-results i {
            font-size: 80px;
            color: var(--gray);
            margin-bottom: 20px;
        }

        .no-results h2 {
            font-size: 24px;
            color: var(--text);
            margin-bottom: 10px;
        }

        .no-results p {
            color: var(--gray);
            font-size: 16px;
        }

        /* Footer */
        footer {
            background: var(--dark);
            color: white;
            padding: 40px 0 20px;
            margin-top: 60px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin-bottom: 30px;
        }

        .footer-column h3 {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 15px;
            color: var(--red);
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
        }

        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }

        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.6);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
            }

            .news-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 28px;
            }

            .news-grid {
                grid-template-columns: 1fr;
            }

            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }

            .search-box {
                flex-direction: column;
            }

            .search-btn,
            .reset-btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .footer-content {
                grid-template-columns: 1fr;
            }

            .top-bar .container {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <!-- Header -->
    <header>
        <div class="main-menu">
            <div class="top-bar">
                <div class="container">
                    <a href="../index.php" class="logo-text">HABER|ONVIBES</a>
                    <div class="top-links">
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="toggle_theme">
                                <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                                <?php echo $dark_mode ? 'Açık' : 'Koyu'; ?>
                            </button>
                        </form>
                        <a href="../index.php">
                            <i class="fas fa-home"></i> Ana Sayfa
                        </a>
                        <?php if(isset($_SESSION['loggedin'])): ?>
                            <a href="../profil.php">
                                <i class="fas fa-user"></i> Profil
                            </a>
                            <a href="../Giris/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Çıkış
                            </a>
                        <?php else: ?>
                            <a href="../Giris/login.php">
                                <i class="fas fa-sign-in-alt"></i> Giriş
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1 class="page-title">
                <i class="fas fa-newspaper"></i> Tüm Haberler
            </h1>
            <p class="page-subtitle">
                <?php echo $total_news; ?> haber bulundu
                <?php if(!empty($category_filter)): 
                    $cat = array_filter($categories, function($c) use ($category_filter) {
                        return $c['id'] == $category_filter;
                    });
                    $cat = reset($cat);
                    if($cat) echo ' - ' . htmlspecialchars($cat['name']) . ' Kategorisi';
                endif; ?>
                <?php if(!empty($search_filter)): ?>
                    - "<?php echo htmlspecialchars($search_filter); ?>" araması için
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="container">
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div>
                        <label class="filter-label">Kategori Filtrele:</label>
                        <select name="category" class="filter-select" onchange="this.form.submit()">
                            <option value="">Tüm Kategoriler</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="filter-label">Arama:</label>
                        <div class="search-box">
                            <input type="text" name="search" class="search-input" placeholder="Haber ara..." value="<?php echo htmlspecialchars($search_filter); ?>">
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i> Ara
                            </button>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: flex-end;">
                        <a href="haberler.php" class="reset-btn">
                            <i class="fas fa-redo"></i> Sıfırla
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- News Grid -->
            <div>
                <?php if(count($news_list) > 0): ?>
                    <div class="news-grid">
                        <?php foreach($news_list as $news): ?>
                            <div class="news-card" onclick="window.location.href='haber-detay.php?id=<?php echo $news['id']; ?>'">
                                <?php if(!empty($news['image'])): ?>
                                    <img src="../<?php echo htmlspecialchars($news['image']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>" class="news-image">
                                <?php else: ?>
                                    <div class="news-image"></div>
                                <?php endif; ?>
                                
                                <div class="news-content">
                                    <?php if($news['category_name']): ?>
                                        <span class="news-category" style="background: <?php echo htmlspecialchars(Helper::getCategoryColor($news['category_name'])); ?>">
                                            <?php echo htmlspecialchars($news['category_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <h3 class="news-title"><?php echo htmlspecialchars($news['title']); ?></h3>
                                    
                                    <p class="news-excerpt">
                                        <?php 
                                        $excerpt = strip_tags($news['content']);
                                        echo htmlspecialchars(mb_substr($excerpt, 0, 150)) . '...'; 
                                        ?>
                                    </p>
                                    
                                    <div class="news-meta">
                                        <div class="news-author">
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($news['author_name'] ?? 'Anonim'); ?>
                                        </div>
                                        <div class="news-date">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('d.m.Y', strtotime($news['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if($page > 1): ?>
                                <a href="?page=<?php echo $page-1; ?><?php echo !empty($category_filter) ? '&category='.$category_filter : ''; ?><?php echo !empty($search_filter) ? '&search='.$search_filter : ''; ?>" class="page-btn">
                                    <i class="fas fa-chevron-left"></i> Önceki
                                </a>
                            <?php endif; ?>

                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo !empty($category_filter) ? '&category='.$category_filter : ''; ?><?php echo !empty($search_filter) ? '&search='.$search_filter : ''; ?>" 
                                       class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php elseif($i == $page - 3 || $i == $page + 3): ?>
                                    <span class="page-btn" style="border: none; cursor: default;">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if($page < $total_pages): ?>
                                <a href="?page=<?php echo $page+1; ?><?php echo !empty($category_filter) ? '&category='.$category_filter : ''; ?><?php echo !empty($search_filter) ? '&search='.$search_filter : ''; ?>" class="page-btn">
                                    Sonraki <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <h2>Sonuç Bulunamadı</h2>
                        <p>Aradığınız kriterlere uygun haber bulunamadı.</p>
                        <br>
                        <a href="haberler.php" class="search-btn">
                            <i class="fas fa-home"></i> Tüm Haberlere Dön
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <aside class="sidebar">
                <!-- Popüler Haberler -->
                <div class="sidebar-widget">
                    <h3 class="widget-title">
                        <i class="fas fa-fire"></i> Popüler Haberler
                    </h3>
                    <?php foreach($popular_news as $news): ?>
                        <div class="sidebar-news-item" onclick="window.location.href='haber-detay.php?id=<?php echo $news['id']; ?>'">
                            <?php if(!empty($news['image'])): ?>
                                <img src="../<?php echo htmlspecialchars($news['image']); ?>" alt="" class="sidebar-news-image">
                            <?php else: ?>
                                <div class="sidebar-news-image"></div>
                            <?php endif; ?>
                            <div class="sidebar-news-content">
                                <h4 class="sidebar-news-title"><?php echo htmlspecialchars($news['title']); ?></h4>
                                <p class="sidebar-news-date">
                                    <i class="fas fa-eye"></i> <?php echo $news['views']; ?> görüntülenme
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Son Haberler -->
                <div class="sidebar-widget">
                    <h3 class="widget-title">
                        <i class="fas fa-clock"></i> Son Haberler
                    </h3>
                    <?php foreach($recent_news as $news): ?>
                        <div class="sidebar-news-item" onclick="window.location.href='haber-detay.php?id=<?php echo $news['id']; ?>'">
                            <?php if(!empty($news['image'])): ?>
                                <img src="../<?php echo htmlspecialchars($news['image']); ?>" alt="" class="sidebar-news-image">
                            <?php else: ?>
                                <div class="sidebar-news-image"></div>
                            <?php endif; ?>
                            <div class="sidebar-news-content">
                                <h4 class="sidebar-news-title"><?php echo htmlspecialchars($news['title']); ?></h4>
                                <p class="sidebar-news-date">
                                    <i class="fas fa-calendar"></i> <?php echo date('d.m.Y H:i', strtotime($news['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Kategoriler -->
                <div class="sidebar-widget">
                    <h3 class="widget-title">
                        <i class="fas fa-th-large"></i> Kategoriler
                    </h3>
                    <?php foreach($categories as $cat): ?>
                        <a href="?category=<?php echo $cat['id']; ?>" style="text-decoration: none;">
                            <div class="sidebar-news-item" style="border-bottom: 1px solid var(--border);">
                                <div style="width: 40px; height: 40px; background: <?php echo htmlspecialchars(Helper::getCategoryColor($cat['name'])); ?>; border-radius: 8px; flex-shrink: 0;"></div>
                                <div class="sidebar-news-content">
                                    <h4 class="sidebar-news-title"><?php echo htmlspecialchars($cat['name']); ?></h4>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>
    </div>

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
                    <h3>Haberler</h3>
                    <ul class="footer-links">
                        <li><a href="haberler.php">Tüm Haberler</a></li>
                        <li><a href="haber-edit.php">Haber Yaz</a></li>
                        <li><a href="arama.php">Haber Ara</a></li>
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
</body>
</html>
