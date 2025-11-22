<?php
// haber-kontrol.php - Haber Onaylama ve Yönetimi
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

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

$message = '';
$error = '';

// Haber onaylama
if (isset($_GET['action']) && isset($_GET['id'])) {
    $news_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    try {
        if ($action == 'approve') {
            $query = "UPDATE news SET status = 'approved' WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $news_id);
            if ($stmt->execute()) {
                $message = 'Haber başarıyla onaylandı!';
            }
        } elseif ($action == 'reject') {
            $query = "UPDATE news SET status = 'rejected' WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $news_id);
            if ($stmt->execute()) {
                $message = 'Haber reddedildi!';
            }
        } elseif ($action == 'delete') {
            $query = "DELETE FROM news WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $news_id);
            if ($stmt->execute()) {
                $message = 'Haber silindi!';
            }
        }
    } catch (PDOException $e) {
        $error = 'İşlem sırasında hata oluştu: ' . $e->getMessage();
    }
}

// Filtreleme
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Haberler listesi
try {
    $query = "SELECT n.*, u.username as author_name, c.name as category_name 
              FROM news n 
              LEFT JOIN users u ON n.author_id = u.id 
              LEFT JOIN categories c ON n.category_id = c.id 
              WHERE 1=1";
    
    if ($status_filter != 'all') {
        $query .= " AND n.status = :status";
    }
    
    if (!empty($search)) {
        $query .= " AND (n.title LIKE :search OR n.content LIKE :search)";
    }
    
    $query .= " ORDER BY n.created_at DESC";
    
    $stmt = $db->prepare($query);
    
    if ($status_filter != 'all') {
        $stmt->bindParam(':status', $status_filter);
    }
    
    if (!empty($search)) {
        $search_param = "%{$search}%";
        $stmt->bindParam(':search', $search_param);
    }
    
    $stmt->execute();
    $news_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Haberler yüklenirken hata oluştu!';
    $news_list = [];
}

// İstatistikler
try {
    $total_news = $db->query("SELECT COUNT(*) FROM news")->fetchColumn();
    $pending_news = $db->query("SELECT COUNT(*) FROM news WHERE status = 'pending'")->fetchColumn();
    $approved_news = $db->query("SELECT COUNT(*) FROM news WHERE status = 'approved'")->fetchColumn();
    $rejected_news = $db->query("SELECT COUNT(*) FROM news WHERE status = 'rejected'")->fetchColumn();
} catch (PDOException $e) {
    $total_news = $pending_news = $approved_news = $rejected_news = 0;
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
    <title>Haber Kontrol - ONVIBES Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        }

        .dark-mode {
            --red: #ff6b6b;
            --dark: #0a0a0a;
            --light: #1a1a1a;
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
            max-width: 1400px;
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
        }

        .admin-badge {
            background: rgba(255,255,255,0.2);
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
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

        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .dark-mode .page-title {
            color: var(--text);
        }

        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 30px;
        }

        .breadcrumb a {
            color: var(--red);
            text-decoration: none;
        }

        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--light);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-left: 4px solid var(--red);
        }

        .dark-mode .stat-card {
            background: var(--light);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: var(--red);
        }

        .stat-label {
            color: var(--gray);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 5px;
        }

        /* Filters */
        .filters {
            background: var(--light);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .dark-mode .filters {
            background: var(--light);
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 13px;
            color: var(--dark);
        }

        .dark-mode .filter-group label {
            color: var(--text);
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text);
            font-size: 14px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--red);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--red);
            color: white;
        }

        .btn-success {
            background: var(--green);
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-info {
            background: var(--blue);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* News Table */
        .news-table {
            background: var(--light);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .dark-mode .news-table {
            background: var(--light);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--dark);
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
        }

        tbody tr:hover {
            background: var(--surface);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
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

        @media (max-width: 1024px) {
            .news-table {
                overflow-x: auto;
            }
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
                    <form method="POST" style="margin: 0;">
                        <button type="submit" name="toggle_theme">
                            <i class="fas fa-<?php echo $dark_mode ? 'sun' : 'moon'; ?>"></i>
                        </button>
                    </form>
                    <a href="admin.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
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

    <!-- Main Content -->
    <div class="main-content container">
        <h1 class="page-title">
            <i class="fas fa-newspaper"></i> Haber Kontrol Paneli
        </h1>
        <div class="breadcrumb">
            <a href="admin.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>Haber Kontrol</span>
        </div>

        <?php if($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- İstatistikler -->
        <div class="stats-row">
            <div class="stat-card" style="border-left-color: var(--blue);">
                <div class="stat-number"><?php echo $total_news; ?></div>
                <div class="stat-label">Toplam Haber</div>
            </div>
            <div class="stat-card" style="border-left-color: var(--orange);">
                <div class="stat-number"><?php echo $pending_news; ?></div>
                <div class="stat-label">Bekleyen</div>
            </div>
            <div class="stat-card" style="border-left-color: var(--green);">
                <div class="stat-number"><?php echo $approved_news; ?></div>
                <div class="stat-label">Onaylı</div>
            </div>
            <div class="stat-card" style="border-left-color: #e74c3c;">
                <div class="stat-number"><?php echo $rejected_news; ?></div>
                <div class="stat-label">Reddedilen</div>
            </div>
        </div>

        <!-- Filtreler -->
        <form method="GET" class="filters">
            <div class="filter-group">
                <label><i class="fas fa-filter"></i> Durum</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>Tümü</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Bekleyenler</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Onaylananlar</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Reddedilenler</option>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-search"></i> Arama</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Haber başlığı veya içeriği...">
            </div>
            <div class="filter-group" style="flex: 0;">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Ara
                </button>
            </div>
        </form>

        <!-- Haberler Tablosu -->
        <div class="news-table">
            <?php if (empty($news_list)): ?>
                <div class="empty-state">
                    <i class="fas fa-newspaper"></i>
                    <p style="font-size: 18px; font-weight: 600;">Haber bulunamadı</p>
                    <p style="font-size: 14px; margin-top: 10px;">Seçili filtrelere uygun haber yok</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Başlık</th>
                            <th>Yazar</th>
                            <th>Kategori</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($news_list as $news): ?>
                        <tr>
                            <td><strong>#<?php echo $news['id']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars(substr($news['title'], 0, 50)) . (strlen($news['title']) > 50 ? '...' : ''); ?></strong>
                                <br>
                                <small style="color: var(--gray);">
                                    <i class="far fa-eye"></i> <?php echo number_format($news['view_count']); ?> görüntülenme
                                </small>
                            </td>
                            <td><?php echo htmlspecialchars($news['author_name']); ?></td>
                            <td><?php echo htmlspecialchars($news['category_name']); ?></td>
                            <td>
                                <?php
                                $status_class = 'status-' . $news['status'];
                                $status_text = [
                                    'pending' => 'Bekliyor',
                                    'approved' => 'Onaylı',
                                    'rejected' => 'Reddedildi'
                                ];
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_text[$news['status']] ?? $news['status']; ?>
                                </span>
                            </td>
                            <td>
                                <small><?php echo time_elapsed_string($news['created_at']); ?></small>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($news['status'] != 'approved'): ?>
                                    <a href="?action=approve&id=<?php echo $news['id']; ?>" 
                                       class="btn btn-success btn-sm"
                                       onclick="return confirm('Bu haberi onaylamak istediğinize emin misiniz?')">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($news['status'] != 'rejected'): ?>
                                    <a href="?action=reject&id=<?php echo $news['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Bu haberi reddetmek istediğinize emin misiniz?')">
                                        <i class="fas fa-times"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="../Veri/haber-detay.php?id=<?php echo $news['id']; ?>" 
                                       class="btn btn-info btn-sm" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <a href="?action=delete&id=<?php echo $news['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Bu haberi kalıcı olarak silmek istediğinize emin misiniz? Bu işlem geri alınamaz!')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
