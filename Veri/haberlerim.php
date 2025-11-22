<?php
// haberlerim.php - Kullanıcının haberlerini listeleme sayfası
session_start();

// Giriş kontrolü
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../Giris/login.php');
    exit();
}

include '../config.php';
$database = new Database();
$db = $database->getConnection();

// Kullanıcı bilgileri
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Haberlerim - ONVIBES</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --text-color: #2c3e50;
            --bg-color: #ecf0f1;
            --card-bg: #ffffff;
            --border-color: #bdc3c7;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --info-color: #3498db;
        }

        .dark-mode {
            --primary-color: #34495e;
            --secondary-color: #2980b9;
            --accent-color: #c0392b;
            --text-color: #ecf0f1;
            --bg-color: #2c3e50;
            --card-bg: #34495e;
            --border-color: #4a6278;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            --success-color: #2ecc71;
            --warning-color: #e67e22;
            --info-color: #3498db;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        /* HEADER */
        header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 1rem 2rem;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }

        .user-welcome {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .btn {
            padding: 0.5rem 1.5rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-login {
            background: transparent;
            border: 2px solid white;
            color: white;
        }

        .btn-register {
            background: white;
            color: var(--primary-color);
        }

        .btn-profile {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
        }

        .btn-logout {
            background: var(--accent-color);
            color: white;
            border: 2px solid var(--accent-color);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        .btn-back {
            background: var(--accent-color);
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* İSTATİSTİKLER */
        .stats-section {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
            background: var(--bg-color);
            border-radius: 12px;
            border-left: 4px solid var(--secondary-color);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-color);
            opacity: 0.8;
            font-weight: 500;
        }

        /* FİLTRE VE ARAMA */
        .filters-section {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .filters-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .search-box {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 3rem;
            border: 1px solid var(--border-color);
            border-radius: 25px;
            background: var(--bg-color);
            color: var(--text-color);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-color);
            opacity: 0.7;
        }

        .filter-select {
            padding: 0.8rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 25px;
            background: var(--bg-color);
            color: var(--text-color);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .filter-btn {
            padding: 0.8rem 1.5rem;
            background: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
        }

        /* HABER KARTLARI */
        .news-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 2rem;
            text-align: center;
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .news-card {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .news-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }

        .news-image {
            width: 100%;
            height: 200px;
            overflow: hidden;
            position: relative;
        }

        .news-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .news-card:hover .news-image img {
            transform: scale(1.1);
        }

        .news-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            color: white;
        }

        .status-approved {
            background: var(--success-color);
        }

        .status-pending {
            background: var(--warning-color);
        }

        .status-rejected {
            background: var(--accent-color);
        }

        .news-content {
            padding: 1.5rem;
        }

        .news-title {
            font-size: 1.3rem;
            margin-bottom: 0.8rem;
            line-height: 1.4;
            color: var(--primary-color);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .news-summary {
            color: var(--text-color);
            opacity: 0.8;
            line-height: 1.6;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .news-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: var(--text-color);
            opacity: 0.7;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .news-stats {
            display: flex;
            gap: 1rem;
        }

        .news-author {
            font-weight: 500;
            color: var(--secondary-color);
        }

        /* AKSIYON BUTONLARI */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-edit {
            background: var(--info-color);
            color: white;
        }

        .btn-view {
            background: var(--secondary-color);
            color: white;
        }

        .btn-delete {
            background: var(--accent-color);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* SAYFALAMA */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 3rem;
        }

        .page-btn {
            padding: 0.7rem 1.2rem;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .page-btn:hover {
            background: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }

        .page-btn.active {
            background: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }

        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* TEMA BUTONU */
        .theme-toggle {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--secondary-color);
            color: white;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: var(--shadow);
        }

        .theme-toggle:hover {
            background: var(--primary-color);
            transform: scale(1.1);
        }

        /* BOŞ DURUM */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-color);
            opacity: 0.7;
        }

        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        /* MOBİL UYUMLULUK */
        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                gap: 1rem;
            }

            .filters-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .news-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-buttons {
                flex-direction: column;
            }

            .news-meta {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-start;
            }

            .news-stats {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-top">
            <a href="../index.php" class="logo">ONVIBES</a>
            <div class="auth-buttons">
                <?php if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                    <div class="user-menu">
                        <div class="user-welcome">
                            🎉 Hoş geldin, <strong><?php echo htmlspecialchars($username); ?></strong>!
                        </div>
                        <a href="../profil.php" class="btn btn-profile">Profilim</a>
                        <a href="haber-ekle.php" class="btn btn-profile">Haber Ekle</a>
                        <a href="../Giris/logout.php" class="btn btn-logout">Çıkış</a>
                    </div>
                <?php else: ?>
                    <a href="../Giris/login.php" class="btn btn-login">Giriş Yap</a>
                    <a href="../Giris/register.php" class="btn btn-register">Kayıt Ol</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- ANA İÇERİK -->
    <div class="news-container">
        <a href="../index.php" class="btn-back">← Ana Sayfaya Dön</a>
        
        <h1 class="page-title">
            📰 <?php echo htmlspecialchars($username); ?> - Haberlerim
        </h1>

        <!-- İSTATİSTİKLER -->
        <div class="stats-section">
            <?php
            if($db) {
                // İstatistikler
                $stats_query = "SELECT 
                                COUNT(*) as total_news,
                                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_news,
                                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_news,
                                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_news,
                                COALESCE(SUM(views), 0) as total_views
                                FROM news WHERE author_id = ?";
                $stats_stmt = $db->prepare($stats_query);
                $stats_stmt->execute([$user_id]);
                $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_news']; ?></div>
                    <div class="stat-label">Toplam Haber</div>
                </div>
                <div class="stat-card" style="border-left-color: var(--success-color);">
                    <div class="stat-number" style="color: var(--success-color);"><?php echo $stats['approved_news']; ?></div>
                    <div class="stat-label">Yayınlanan</div>
                </div>
                <div class="stat-card" style="border-left-color: var(--warning-color);">
                    <div class="stat-number" style="color: var(--warning-color);"><?php echo $stats['pending_news']; ?></div>
                    <div class="stat-label">Beklemede</div>
                </div>
                <div class="stat-card" style="border-left-color: var(--accent-color);">
                    <div class="stat-number" style="color: var(--accent-color);"><?php echo $stats['rejected_news']; ?></div>
                    <div class="stat-label">Reddedilen</div>
                </div>
                <div class="stat-card" style="border-left-color: var(--info-color);">
                    <div class="stat-number" style="color: var(--info-color);"><?php echo number_format($stats['total_views']); ?></div>
                    <div class="stat-label">Toplam Görüntülenme</div>
                </div>
            </div>
            <?php } ?>
        </div>

        <!-- FİLTRE VE ARAMA -->
        <div class="filters-section">
            <div class="filters-row">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" class="search-input" id="searchInput" placeholder="Haberlerimde ara..." value="<?php echo isset($_GET['ara']) ? htmlspecialchars($_GET['ara']) : ''; ?>">
                </div>
                
                <select class="filter-select" id="statusFilter">
                    <option value="">Tüm Durumlar</option>
                    <option value="approved" <?php echo (isset($_GET['durum']) && $_GET['durum'] == 'approved') ? 'selected' : ''; ?>>Yayınlanan</option>
                    <option value="pending" <?php echo (isset($_GET['durum']) && $_GET['durum'] == 'pending') ? 'selected' : ''; ?>>Beklemede</option>
                    <option value="rejected" <?php echo (isset($_GET['durum']) && $_GET['durum'] == 'rejected') ? 'selected' : ''; ?>>Reddedilen</option>
                </select>
                
                <select class="filter-select" id="sortFilter">
                    <option value="newest" <?php echo (!isset($_GET['sirala']) || $_GET['sirala'] == 'newest') ? 'selected' : ''; ?>>En Yeni</option>
                    <option value="oldest" <?php echo (isset($_GET['sirala']) && $_GET['sirala'] == 'oldest') ? 'selected' : ''; ?>>En Eski</option>
                    <option value="popular" <?php echo (isset($_GET['sirala']) && $_GET['sirala'] == 'popular') ? 'selected' : ''; ?>>En Popüler</option>
                </select>
                
                <button class="filter-btn" onclick="applyFilters()">Filtrele</button>
            </div>
        </div>

        <!-- HABER LİSTESİ -->
        <div class="news-grid" id="newsGrid">
            <?php
            if($db) {
                // Sayfalama ayarları
                $page = isset($_GET['sayfa']) ? max(1, intval($_GET['sayfa'])) : 1;
                $limit = 12;
                $offset = ($page - 1) * $limit;

                // Filtreleme koşulları
                $where_conditions = ["n.author_id = ?"];
                $params = [$user_id];

                // Arama filtresi
                if(isset($_GET['ara']) && !empty(trim($_GET['ara']))) {
                    $search_term = '%' . trim($_GET['ara']) . '%';
                    $where_conditions[] = "(n.title LIKE ? OR n.summary LIKE ? OR n.content LIKE ?)";
                    $params[] = $search_term;
                    $params[] = $search_term;
                    $params[] = $search_term;
                }

                // Durum filtresi
                if(isset($_GET['durum']) && !empty($_GET['durum'])) {
                    $where_conditions[] = "n.status = ?";
                    $params[] = $_GET['durum'];
                }

                // Sıralama
                $order_by = "n.created_at DESC";
                if(isset($_GET['sirala'])) {
                    switch($_GET['sirala']) {
                        case 'oldest':
                            $order_by = "n.created_at ASC";
                            break;
                        case 'popular':
                            $order_by = "n.views DESC";
                            break;
                    }
                }

                $where_clause = implode(' AND ', $where_conditions);

                // Toplam kayıt sayısı
                $count_query = "SELECT COUNT(*) FROM news n WHERE $where_clause";
                $count_stmt = $db->prepare($count_query);
                $count_stmt->execute($params);
                $total_news = $count_stmt->fetchColumn();
                $total_pages = ceil($total_news / $limit);

                // Haberleri getir
                $news_query = "SELECT n.*, c.name as category_name 
                              FROM news n 
                              LEFT JOIN categories c ON n.category_id = c.id 
                              WHERE $where_clause 
                              ORDER BY $order_by 
                              LIMIT $limit OFFSET $offset";
                $news_stmt = $db->prepare($news_query);
                $news_stmt->execute($params);
                $news_list = $news_stmt->fetchAll(PDO::FETCH_ASSOC);

                if(empty($news_list)) {
                    echo '<div class="empty-state">
                            <div class="icon">📝</div>
                            <h3>Henüz haber bulunamadı</h3>
                            <p>İlk haberinizi yazarak başlayın!</p>
                            <a href="haber-ekle.php" class="btn" style="background: var(--secondary-color); color: white; padding: 1rem 2rem; border-radius: 25px; text-decoration: none; display: inline-block; margin-top: 1rem;">✏️ İlk Haberimi Yaz</a>
                          </div>';
                } else {
                    foreach($news_list as $news):
                        // Durum rengi
                        $status_class = '';
                        $status_text = '';
                        switch($news['status']) {
                            case 'approved':
                                $status_class = 'status-approved';
                                $status_text = 'Yayınlanan';
                                break;
                            case 'pending':
                                $status_class = 'status-pending';
                                $status_text = 'Beklemede';
                                break;
                            case 'rejected':
                                $status_class = 'status-rejected';
                                $status_text = 'Reddedilen';
                                break;
                        }
                        
                        // Tarih formatı
                        $created_date = date('d.m.Y H:i', strtotime($news['created_at']));
            ?>
                        <div class="news-card" onclick="window.location.href='haber-detay.php?id=<?php echo $news['id']; ?>'">
                            <div class="news-image">
                                <?php if(!empty($news['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($news['image']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>" onerror="this.style.display='none'">
                                <?php endif; ?>
                                <div class="news-status <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </div>
                            </div>
                            <div class="news-content">
                                <h3 class="news-title"><?php echo htmlspecialchars($news['title']); ?></h3>
                                <p class="news-summary"><?php echo htmlspecialchars($news['summary']); ?></p>
                                <div class="news-meta">
                                    <div class="news-stats">
                                        <span>👁️ <?php echo number_format($news['views']); ?></span>
                                        <span>📅 <?php echo $created_date; ?></span>
                                    </div>
                                    <div class="news-author">
                                        <?php echo htmlspecialchars($news['category_name']); ?>
                                    </div>
                                </div>
                                <div class="action-buttons">
                                    <a href="haber-detay.php?id=<?php echo $news['id']; ?>" class="action-btn btn-view" onclick="event.stopPropagation();">
                                        👁️ Görüntüle
                                    </a>
                                    <?php if($news['status'] == 'approved' || $news['status'] == 'rejected'): ?>
                                        <a href="haber-edit.php?id=<?php echo $news['id']; ?>" class="action-btn btn-edit" onclick="event.stopPropagation();">
                                            ✏️ Düzenle
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
            <?php
                    endforeach;
                }
            } else {
                echo '<div class="empty-state">
                        <div class="icon">⚠️</div>
                        <h3>Veritabanı bağlantı hatası</h3>
                        <p>Haberleriniz yüklenemedi. Lütfen daha sonra tekrar deneyin.</p>
                      </div>';
            }
            ?>
        </div>

        <!-- SAYFALAMA -->
        <?php if(isset($total_pages) && $total_pages > 1): ?>
        <div class="pagination">
            <button class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" 
                    onclick="changePage(<?php echo $page - 1; ?>)" 
                    <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                ← Önceki
            </button>
            
            <?php
            // Sayfa numaraları
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for($i = $start_page; $i <= $end_page; $i++):
            ?>
                <button class="page-btn <?php echo $i == $page ? 'active' : ''; ?>" 
                        onclick="changePage(<?php echo $i; ?>)">
                    <?php echo $i; ?>
                </button>
            <?php endfor; ?>
            
            <button class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
                    onclick="changePage(<?php echo $page + 1; ?>)" 
                    <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                Sonraki →
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- TEMA BUTONU -->
    <button class="theme-toggle" onclick="toggleTheme()">🌙</button>

    <script>
        // TEMA DEĞİŞTİRME
        function toggleTheme() {
            document.body.classList.toggle('dark-mode');
            const themeToggle = document.querySelector('.theme-toggle');
            themeToggle.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        }

        // FİLTRE UYGULAMA
        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            const sort = document.getElementById('sortFilter').value;
            
            let url = 'haberlerim.php?';
            const params = [];
            
            if(search) params.push(`ara=${encodeURIComponent(search)}`);
            if(status) params.push(`durum=${encodeURIComponent(status)}`);
            if(sort && sort !== 'newest') params.push(`sirala=${sort}`);
            
            window.location.href = url + params.join('&');
        }

        // SAYFA DEĞİŞTİRME
        function changePage(newPage) {
            const url = new URL(window.location.href);
            url.searchParams.set('sayfa', newPage);
            window.location.href = url.toString();
        }

        // ENTER TUŞU İLE ARAMA
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if(e.key === 'Enter') {
                applyFilters();
            }
        });

        // SAYFA YÜKLENDİĞİNDE
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            if(savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                document.querySelector('.theme-toggle').textContent = '☀️';
            }
        });
    </script>
</body>
</html>
