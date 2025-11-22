<?php
// arama.php - Arama Sonuçları Sayfası
session_start();

include '../config.php';
$database = new Database();
$db = $database->getConnection();

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    $redirect_url = $_SERVER['PHP_SELF'];
    if(!empty($_GET['q'])) {
        $redirect_url .= '?q=' . urlencode($_GET['q']);
    }
    header('Location: ' . $redirect_url);
    exit();
}
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Arama terimi
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$category_filter = isset($_GET['category']) && is_numeric($_GET['category']) ? $_GET['category'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';

$results = [];
$total_results = 0;

// Kategorileri çek
try {
    $cat_query = "SELECT * FROM categories ORDER BY name ASC";
    $cat_stmt = $db->prepare($cat_query);
    $cat_stmt->execute();
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $categories = [];
}

// Arama yap
if(!empty($search_query)) {
    try {
        $sql = "SELECT n.*, c.name as category_name, c.id as category_id, u.username, u.full_name
                FROM news n
                LEFT JOIN categories c ON n.category_id = c.id
                LEFT JOIN users u ON n.author_id = u.id
                WHERE n.status = 'published' 
                AND (n.title LIKE :search OR n.content LIKE :search)";
        
        if(!empty($category_filter)) {
            $sql .= " AND n.category_id = :category_id";
        }
        
        // Sıralama
        switch($sort_by) {
            case 'date':
                $sql .= " ORDER BY n.created_at DESC";
                break;
            case 'views':
                $sql .= " ORDER BY n.views DESC";
                break;
            case 'title':
                $sql .= " ORDER BY n.title ASC";
                break;
            default:
                $sql .= " ORDER BY n.created_at DESC";
        }
        
        $sql .= " LIMIT 100";
        
        $stmt = $db->prepare($sql);
        $search_term = '%' . $search_query . '%';
        $stmt->bindParam(':search', $search_term);
        
        if(!empty($category_filter)) {
            $stmt->bindParam(':category_id', $category_filter);
        }
        
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_results = count($results);
        
    } catch(PDOException $e) {
        $error = 'Arama yapılırken hata oluştu: ' . $e->getMessage();
    }
} else {
    $error = 'Arama terimi boş olamaz.';
}

// Popüler aramalar (rastgele haberlerden örnek başlıklar)
try {
    $popular_query = "SELECT DISTINCT SUBSTRING_INDEX(title, ' ', 3) as keyword 
                      FROM news 
                      WHERE status = 'published' 
                      ORDER BY views DESC 
                      LIMIT 10";
    $popular_stmt = $db->prepare($popular_query);
    $popular_stmt->execute();
    $popular_searches = $popular_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $popular_searches = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo !empty($search_query) ? htmlspecialchars($search_query) . ' - Arama Sonuçları' : 'Arama'; ?> - ONVIBES</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --red: #e50914;
            --dark: #141414;
            --light: #ffffff;
            --border: #e0e0e0;
            --surface: #f8f9fa;
            --text: #333333;
            --gray: #666666;
        }

        .dark-mode {
            --light: #1f1f1f;
            --dark: #f0f0f0;
            --border: #333333;
            --surface: #1a1a1a;
            --text: #f0f0f0;
            --gray: #a0a0a0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Header */
        .main-menu {
            background: var(--light);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .top-bar {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 10px 0;
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
            background: rgba(255,255,255,0.15);
            border: none;
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .top-links button:hover,
        .top-links a:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }

        /* Main Content */
        .main-content {
            padding: 30px 0;
        }

        /* Search Header */
        .search-header {
            background: var(--light);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .search-title {
            font-size: 28px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-title i {
            color: var(--red);
        }

        /* Search Form */
        .search-form {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .search-input-group {
            flex: 1;
            position: relative;
        }

        .search-input-group input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            border: 2px solid var(--border);
            border-radius: 25px;
            background: var(--surface);
            color: var(--text);
            font-size: 15px;
            transition: all 0.3s;
        }

        .search-input-group input:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(229,9,20,0.1);
        }

        .search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .search-btn:hover {
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 4px 12px rgba(229,9,20,0.3);
        }

        /* Filters */
        .search-filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
        }

        .filter-group select {
            padding: 8px 15px;
            border: 2px solid var(--border);
            border-radius: 20px;
            background: var(--surface);
            color: var(--text);
            font-size: 13px;
            cursor: pointer;
        }

        /* Search Results Info */
        .results-info {
            background: var(--surface);
            padding: 15px 20px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .results-count {
            font-size: 14px;
            color: var(--gray);
        }

        .results-count strong {
            color: var(--red);
            font-size: 16px;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }

        /* Results List */
        .results-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .result-item {
            background: var(--light);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 4px solid var(--red);
        }

        .result-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.12);
        }

        .result-header {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .result-image {
            width: 150px;
            height: 100px;
            border-radius: 10px;
            object-fit: cover;
        }

        .result-info {
            flex: 1;
        }

        .result-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .result-title a {
            color: var(--dark);
            text-decoration: none;
        }

        .result-title a:hover {
            color: var(--red);
        }

        .result-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 10px;
        }

        .result-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .category-badge {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .result-excerpt {
            color: var(--text);
            font-size: 14px;
            line-height: 1.6;
            margin-top: 10px;
        }

        /* No Results */
        .no-results {
            background: var(--light);
            border-radius: 15px;
            padding: 60px 30px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .no-results i {
            font-size: 64px;
            color: var(--gray);
            opacity: 0.5;
            margin-bottom: 20px;
        }

        .no-results h3 {
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .no-results p {
            color: var(--gray);
            font-size: 14px;
        }

        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .sidebar-card {
            background: var(--light);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--red);
        }

        /* Popular Searches */
        .popular-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .popular-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: var(--surface);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text);
            transition: all 0.3s;
        }

        .popular-item:hover {
            background: var(--red);
            color: white;
            transform: translateX(5px);
        }

        .popular-item i {
            color: var(--red);
        }

        .popular-item:hover i {
            color: white;
        }

        /* Categories List */
        .categories-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .category-item {
            padding: 10px 15px;
            background: var(--surface);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .category-item:hover {
            background: var(--red);
            color: white;
            transform: translateX(5px);
        }

        .category-item.active {
            background: var(--red);
            color: white;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .search-header {
                padding: 20px;
            }

            .search-title {
                font-size: 22px;
            }

            .search-form {
                flex-direction: column;
            }

            .search-filters {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group select {
                width: 100%;
            }

            .result-header {
                flex-direction: column;
            }

            .result-image {
                width: 100%;
                height: 200px;
            }
        }

        @media (max-width: 480px) {
            .top-bar .container {
                flex-direction: column;
            }

            .results-info {
                flex-direction: column;
                align-items: stretch;
            }

            .result-title {
                font-size: 18px;
            }
        }
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <!-- Header -->
    <header class="main-menu">
        <div class="top-bar">
            <div class="container">
                <a href="../index.php" class="logo-text">ONVIBES</a>
                <div class="top-links">
                    <form method="post" style="display: inline;">
                        <button type="submit" name="toggle_theme">
                            <i class="fas fa-<?php echo $dark_mode ? 'sun' : 'moon'; ?>"></i>
                            <?php echo $dark_mode ? 'Açık Mod' : 'Karanlık Mod'; ?>
                        </button>
                    </form>
                    <?php if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                        <a href="../profil.php">
                            <i class="fas fa-user"></i> Profil
                        </a>
                        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                            <a href="../admin.php">
                                <i class="fas fa-cog"></i> Admin
                            </a>
                        <?php endif; ?>
                        <a href="../Giris/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Çıkış
                        </a>
                    <?php else: ?>
                        <a href="../Giris/login.php">
                            <i class="fas fa-sign-in-alt"></i> Giriş
                        </a>
                        <a href="../Giris/register.php">
                            <i class="fas fa-user-plus"></i> Kayıt
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Search Header -->
            <div class="search-header">
                <h1 class="search-title">
                    <i class="fas fa-search"></i>
                    Arama
                </h1>

                <!-- Search Form -->
                <form method="get" class="search-form" id="searchForm">
                    <div class="search-input-group">
                        <input type="text" name="q" 
                               value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Aramak istediğiniz kelimeyi girin..."
                               required>
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>

                <!-- Filters -->
                <div class="search-filters">
                    <div class="filter-group">
                        <label for="category">Kategori:</label>
                        <select name="category" id="category" onchange="applyFilters()">
                            <option value="">Tüm Kategoriler</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"
                                        <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="sort">Sıralama:</label>
                        <select name="sort" id="sort" onchange="applyFilters()">
                            <option value="relevance" <?php echo $sort_by == 'relevance' ? 'selected' : ''; ?>>
                                İlgililik
                            </option>
                            <option value="date" <?php echo $sort_by == 'date' ? 'selected' : ''; ?>>
                                En Yeni
                            </option>
                            <option value="views" <?php echo $sort_by == 'views' ? 'selected' : ''; ?>>
                                En Popüler
                            </option>
                            <option value="title" <?php echo $sort_by == 'title' ? 'selected' : ''; ?>>
                                Alfabetik
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <?php if(!empty($search_query)): ?>
                <!-- Error Message -->
                <?php if(isset($error)): ?>
                    <div style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #c62828;">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Results Info -->
                <div class="results-info">
                    <div class="results-count">
                        "<strong><?php echo htmlspecialchars($search_query); ?></strong>" için 
                        <strong><?php echo $total_results; ?></strong> sonuç bulundu
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="content-grid">
                    <!-- Results List -->
                    <div class="results-list">
                        <?php if($total_results > 0): ?>
                            <?php foreach($results as $result): ?>
                                <article class="result-item">
                                    <div class="result-header">
                                        <?php if(!empty($result['image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($result['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($result['title']); ?>" 
                                                 class="result-image">
                                        <?php endif; ?>
                                        
                                        <div class="result-info">
                                            <h2 class="result-title">
                                                <a href="haber-detay.php?id=<?php echo $result['id']; ?>">
                                                    <?php 
                                                    // Arama terimini vurgula
                                                    $highlighted_title = str_ireplace(
                                                        $search_query,
                                                        '<mark style="background: yellow; color: #000; padding: 2px 4px; border-radius: 3px;">' . $search_query . '</mark>',
                                                        htmlspecialchars($result['title'])
                                                    );
                                                    echo $highlighted_title;
                                                    ?>
                                                </a>
                                            </h2>
                                            
                                            <div class="result-meta">
                                                <a href="?q=<?php echo urlencode($search_query); ?>&category=<?php echo $result['category_id']; ?>" 
                                                   class="category-badge">
                                                    <?php echo htmlspecialchars($result['category_name']); ?>
                                                </a>
                                                <span>
                                                    <i class="fas fa-user"></i>
                                                    <?php echo htmlspecialchars($result['full_name'] ?? $result['username']); ?>
                                                </span>
                                                <span>
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <?php echo date('d.m.Y', strtotime($result['created_at'])); ?>
                                                </span>
                                                <span>
                                                    <i class="fas fa-eye"></i>
                                                    <?php echo number_format($result['views']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="result-excerpt">
                                        <?php 
                                        $excerpt = strip_tags($result['content']);
                                        $excerpt = substr($excerpt, 0, 200) . '...';
                                        // Arama terimini vurgula
                                        $highlighted_excerpt = str_ireplace(
                                            $search_query,
                                            '<mark style="background: yellow; color: #000; padding: 2px 4px; border-radius: 3px;">' . $search_query . '</mark>',
                                            htmlspecialchars($excerpt)
                                        );
                                        echo $highlighted_excerpt;
                                        ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-results">
                                <i class="fas fa-search-minus"></i>
                                <h3>Sonuç Bulunamadı</h3>
                                <p>"<?php echo htmlspecialchars($search_query); ?>" için sonuç bulunamadı. Farklı anahtar kelimelerle tekrar deneyin.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar -->
                    <aside class="sidebar">
                        <!-- Categories -->
                        <div class="sidebar-card">
                            <h3 class="sidebar-title">Kategoriler</h3>
                            <div class="categories-list">
                                <a href="?q=<?php echo urlencode($search_query); ?>" 
                                   class="category-item <?php echo empty($category_filter) ? 'active' : ''; ?>">
                                    <span>Tüm Kategoriler</span>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                                <?php foreach($categories as $cat): ?>
                                    <a href="?q=<?php echo urlencode($search_query); ?>&category=<?php echo $cat['id']; ?>" 
                                       class="category-item <?php echo $category_filter == $cat['id'] ? 'active' : ''; ?>">
                                        <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Popular Searches -->
                        <?php if(count($popular_searches) > 0): ?>
                            <div class="sidebar-card">
                                <h3 class="sidebar-title">Popüler Aramalar</h3>
                                <div class="popular-list">
                                    <?php foreach($popular_searches as $popular): ?>
                                        <a href="?q=<?php echo urlencode($popular['keyword']); ?>" class="popular-item">
                                            <i class="fas fa-fire"></i>
                                            <span><?php echo htmlspecialchars($popular['keyword']); ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </aside>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>Arama Başlatın</h3>
                    <p>Yukarıdaki arama kutusunu kullanarak haber, makale ve içeriklerde arama yapabilirsiniz.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function applyFilters() {
            const form = document.getElementById('searchForm');
            const category = document.getElementById('category').value;
            const sort = document.getElementById('sort').value;
            const searchQuery = form.querySelector('input[name="q"]').value;
            
            let url = '?q=' + encodeURIComponent(searchQuery);
            if(category) url += '&category=' + category;
            if(sort) url += '&sort=' + sort;
            
            window.location.href = url;
        }
    </script>
</body>
</html>
