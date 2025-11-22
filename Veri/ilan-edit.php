<?php
// ilan-edit.php - İlan Ekleme/Düzenleme/Silme Sayfası
session_start();

// Giriş kontrolü ve yetki kontrolü
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../Giris/login.php');
    exit();
}

// Sadece yazar, muhabir ve admin yetkisi olanlar erişebilir
if(!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'writer', 'reporter'])) {
    header('Location: ../index.php?error=access_denied');
    exit();
}

include '../config.php';
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

$message = '';
$error = '';
$edit_announcement = null;

// Düzenleme modu kontrolü
$edit_mode = isset($_GET['edit']) && is_numeric($_GET['edit']);
if($edit_mode) {
    $edit_id = $_GET['edit'];
    
    try {
        // Admin tüm ilanları, diğerleri sadece kendi ilanlarını düzenleyebilir
        if($user_role == 'admin') {
            $query = "SELECT * FROM announcements WHERE id = :id";
        } else {
            $query = "SELECT * FROM announcements WHERE id = :id AND created_by = :created_by";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $edit_id);
        if($user_role != 'admin') {
            $stmt->bindParam(':created_by', $user_id);
        }
        $stmt->execute();
        
        $edit_announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$edit_announcement) {
            $error = 'Bu ilanı düzenleme yetkiniz yok!';
            $edit_mode = false;
        }
    } catch(PDOException $e) {
        $error = 'İlan yüklenirken hata oluştu: ' . $e->getMessage();
        $edit_mode = false;
    }
}

// İlan silme
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    try {
        // Admin tüm ilanları, diğerleri sadece kendi ilanlarını silebilir
        if($user_role == 'admin') {
            $query = "DELETE FROM announcements WHERE id = :id";
        } else {
            $query = "DELETE FROM announcements WHERE id = :id AND created_by = :created_by";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $delete_id);
        if($user_role != 'admin') {
            $stmt->bindParam(':created_by', $user_id);
        }
        
        if($stmt->execute() && $stmt->rowCount() > 0) {
            $message = 'İlan başarıyla silindi!';
        } else {
            $error = 'İlan silinemedi veya bu işlem için yetkiniz yok!';
        }
    } catch(PDOException $e) {
        $error = 'İlan silinirken hata oluştu: ' . $e->getMessage();
    }
}

// İlan ekleme/güncelleme
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_announcement'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $type = $_POST['type'];
    $priority = $_POST['priority'];
    $status = $_POST['status'];
    
    if(empty($title) || empty($content)) {
        $error = 'Başlık ve içerik alanları zorunludur!';
    } else {
        try {
            if($edit_mode && $edit_announcement) {
                // Güncelleme
                $query = "UPDATE announcements SET 
                          title = :title,
                          content = :content,
                          type = :type,
                          priority = :priority,
                          status = :status,
                          updated_at = NOW()
                          WHERE id = :id";
                
                if($user_role != 'admin') {
                    $query .= " AND created_by = :created_by";
                }
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':content', $content);
                $stmt->bindParam(':type', $type);
                $stmt->bindParam(':priority', $priority);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':id', $edit_announcement['id']);
                if($user_role != 'admin') {
                    $stmt->bindParam(':created_by', $user_id);
                }
                
                if($stmt->execute()) {
                    $message = 'İlan başarıyla güncellendi!';
                    // Güncel veriyi yeniden çek
                    $edit_announcement['title'] = $title;
                    $edit_announcement['content'] = $content;
                    $edit_announcement['type'] = $type;
                    $edit_announcement['priority'] = $priority;
                    $edit_announcement['status'] = $status;
                }
            } else {
                // Yeni ekleme
                $query = "INSERT INTO announcements (title, content, type, priority, status, created_by, created_at) 
                          VALUES (:title, :content, :type, :priority, :status, :created_by, NOW())";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':content', $content);
                $stmt->bindParam(':type', $type);
                $stmt->bindParam(':priority', $priority);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':created_by', $user_id);
                
                if($stmt->execute()) {
                    $message = 'İlan başarıyla eklendi!';
                }
            }
        } catch(PDOException $e) {
            $error = 'İlan kaydedilirken hata oluştu: ' . $e->getMessage();
        }
    }
}

// Filtreleme
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// İlanları listele
try {
    $list_query = "SELECT a.*, u.username, u.full_name 
                   FROM announcements a
                   LEFT JOIN users u ON a.created_by = u.id
                   WHERE 1=1";
    
    // Admin tüm ilanları, diğerleri sadece kendi ilanlarını görür
    if($user_role != 'admin') {
        $list_query .= " AND a.created_by = :created_by";
    }
    
    // Filtreleme
    if(!empty($filter_type)) {
        $list_query .= " AND a.type = :type";
    }
    
    if(!empty($filter_status)) {
        $list_query .= " AND a.status = :status";
    }
    
    if(!empty($search)) {
        $list_query .= " AND (a.title LIKE :search OR a.content LIKE :search)";
    }
    
    $list_query .= " ORDER BY a.created_at DESC LIMIT 50";
    
    $list_stmt = $db->prepare($list_query);
    
    if($user_role != 'admin') {
        $list_stmt->bindParam(':created_by', $user_id);
    }
    
    if(!empty($filter_type)) {
        $list_stmt->bindParam(':type', $filter_type);
    }
    
    if(!empty($filter_status)) {
        $list_stmt->bindParam(':status', $filter_status);
    }
    
    if(!empty($search)) {
        $search_term = '%' . $search . '%';
        $list_stmt->bindParam(':search', $search_term);
    }
    
    $list_stmt->execute();
    $announcements_list = $list_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $announcements_list = [];
    $error = 'İlanlar yüklenirken hata oluştu: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İlan Yönetimi - ONVIBES</title>
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
            text-decoration: none;
        }

        .user-badge {
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

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            color: var(--gray);
            font-size: 14px;
        }

        .breadcrumb a {
            color: var(--red);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            animation: slideInDown 0.3s ease;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        /* Form Card */
        .form-card {
            background: var(--light);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .form-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--red);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-title i {
            color: var(--red);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text);
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(229,9,20,0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: var(--gray);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(229,9,20,0.3);
        }

        .btn-secondary {
            background: var(--gray);
            color: white;
        }

        .btn-secondary:hover {
            background: #555;
            transform: translateY(-2px);
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

        /* Filters */
        .filters {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--dark);
        }

        .filter-group input,
        .filter-group select {
            padding: 10px;
            border: 2px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text);
            font-size: 13px;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-filter {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-apply {
            background: var(--red);
            color: white;
        }

        .btn-apply:hover {
            background: #b81d24;
        }

        .btn-reset {
            background: var(--surface);
            color: var(--text);
            border: 2px solid var(--border);
        }

        .btn-reset:hover {
            background: var(--border);
        }

        /* Announcements List */
        .announcements-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }

        .announcement-item {
            background: var(--surface);
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid var(--red);
            transition: all 0.3s;
        }

        .announcement-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .announcement-item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
            gap: 15px;
        }

        .announcement-item-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--dark);
            flex: 1;
        }

        .announcement-badges {
            display: flex;
            gap: 8px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-announcement {
            background: #d1ecf1;
            color: #0c5460;
        }

        .type-event {
            background: #fff3cd;
            color: #856404;
        }

        .type-notice {
            background: #d4edda;
            color: #155724;
        }

        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }

        .priority-low {
            background: #d4edda;
            color: #155724;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .announcement-item-content {
            color: var(--text);
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .announcement-item-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }

        .announcement-item-meta {
            font-size: 12px;
            color: var(--gray);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .announcement-item-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .announcement-item-actions {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            color: white;
        }

        .btn-edit {
            background: #17a2b8;
        }

        .btn-edit:hover {
            background: #138496;
            transform: scale(1.1);
        }

        .btn-delete {
            background: #dc3545;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        .no-announcements {
            text-align: center;
            padding: 40px;
            color: var(--gray);
        }

        .no-announcements i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .sidebar {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .sidebar-card {
                flex: 1;
                min-width: 300px;
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 24px;
            }

            .form-card,
            .sidebar-card {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .announcement-item-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .announcement-item-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .sidebar {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .top-bar .container {
                flex-direction: column;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
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
                    <a href="../index.php" class="logo-text">ONVIBES</a>
                    <span class="user-badge">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                        (<?php echo ucfirst($_SESSION['role']); ?>)
                    </span>
                </div>
                <div class="top-links">
                    <form method="post" style="display: inline;">
                        <button type="submit" name="toggle_theme">
                            <i class="fas fa-<?php echo $dark_mode ? 'sun' : 'moon'; ?>"></i>
                            <?php echo $dark_mode ? 'Açık Mod' : 'Karanlık Mod'; ?>
                        </button>
                    </form>
                    <a href="../profil.php">
                        <i class="fas fa-user"></i> Profil
                    </a>
                    <a href="ilan.php">
                        <i class="fas fa-bullhorn"></i> İlanlar
                    </a>
                    <?php if($_SESSION['role'] == 'admin'): ?>
                        <a href="../admin.php">
                            <i class="fas fa-cog"></i> Admin
                        </a>
                    <?php endif; ?>
                    <a href="../index.php">
                        <i class="fas fa-home"></i> Ana Sayfa
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <?php echo $edit_mode ? 'İlan Düzenle' : 'Yeni İlan Ekle'; ?>
                </h1>
                <div class="breadcrumb">
                    <a href="../index.php"><i class="fas fa-home"></i> Ana Sayfa</a>
                    <span>/</span>
                    <a href="ilan.php">İlanlar</a>
                    <span>/</span>
                    <span>İlan Yönetimi</span>
                </div>
            </div>

            <!-- Alert Messages -->
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

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Form -->
                <div class="form-card">
                    <h2 class="form-title">
                        <i class="fas fa-<?php echo $edit_mode ? 'edit' : 'plus-circle'; ?>"></i>
                        <?php echo $edit_mode ? 'İlan Bilgileri' : 'Yeni İlan Oluştur'; ?>
                    </h2>

                    <form method="post">
                        <div class="form-group">
                            <label for="title">İlan Başlığı *</label>
                            <input type="text" id="title" name="title" required 
                                   value="<?php echo $edit_mode ? htmlspecialchars($edit_announcement['title']) : ''; ?>"
                                   placeholder="İlan başlığını girin...">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="type">İlan Türü *</label>
                                <select id="type" name="type" required>
                                    <option value="announcement" <?php echo ($edit_mode && $edit_announcement['type'] == 'announcement') ? 'selected' : ''; ?>>
                                        Duyuru
                                    </option>
                                    <option value="event" <?php echo ($edit_mode && $edit_announcement['type'] == 'event') ? 'selected' : ''; ?>>
                                        Etkinlik
                                    </option>
                                    <option value="notice" <?php echo ($edit_mode && $edit_announcement['type'] == 'notice') ? 'selected' : ''; ?>>
                                        Bildirim
                                    </option>
                                </select>
                                <small>İlanın türünü seçin</small>
                            </div>

                            <div class="form-group">
                                <label for="priority">Öncelik *</label>
                                <select id="priority" name="priority" required>
                                    <option value="low" <?php echo ($edit_mode && $edit_announcement['priority'] == 'low') ? 'selected' : ''; ?>>
                                        Düşük
                                    </option>
                                    <option value="medium" <?php echo ($edit_mode && $edit_announcement['priority'] == 'medium') ? 'selected' : ''; ?>>
                                        Orta
                                    </option>
                                    <option value="high" <?php echo ($edit_mode && $edit_announcement['priority'] == 'high') ? 'selected' : ''; ?>>
                                        Yüksek
                                    </option>
                                </select>
                                <small>Yüksek öncelikli ilanlar üstte görünür</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="content">İlan İçeriği *</label>
                            <textarea id="content" name="content" required 
                                      placeholder="İlan içeriğini buraya yazın..."><?php echo $edit_mode ? htmlspecialchars($edit_announcement['content']) : ''; ?></textarea>
                            <small>İlanın detaylı açıklamasını yazın</small>
                        </div>

                        <div class="form-group">
                            <label for="status">Durum *</label>
                            <select id="status" name="status" required>
                                <option value="active" <?php echo ($edit_mode && $edit_announcement['status'] == 'active') ? 'selected' : ''; ?>>
                                    Aktif
                                </option>
                                <option value="inactive" <?php echo ($edit_mode && $edit_announcement['status'] == 'inactive') ? 'selected' : ''; ?>>
                                    Pasif
                                </option>
                            </select>
                            <small>Sadece aktif ilanlar görüntülenir</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="save_announcement" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                <?php echo $edit_mode ? 'Değişiklikleri Kaydet' : 'İlan Yayınla'; ?>
                            </button>
                            <?php if($edit_mode): ?>
                                <a href="ilan-edit.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> İptal
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Sidebar -->
                <aside class="sidebar">
                    <!-- Filters -->
                    <div class="sidebar-card">
                        <h3 class="sidebar-title">
                            <i class="fas fa-filter"></i> Filtreler
                        </h3>
                        <form method="get" class="filters">
                            <div class="filter-group">
                                <label for="search">Ara</label>
                                <input type="text" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Başlık veya içerik...">
                            </div>
                            
                            <div class="filter-group">
                                <label for="filter_type">Tür</label>
                                <select id="filter_type" name="type">
                                    <option value="">Tümü</option>
                                    <option value="announcement" <?php echo $filter_type == 'announcement' ? 'selected' : ''; ?>>
                                        Duyuru
                                    </option>
                                    <option value="event" <?php echo $filter_type == 'event' ? 'selected' : ''; ?>>
                                        Etkinlik
                                    </option>
                                    <option value="notice" <?php echo $filter_type == 'notice' ? 'selected' : ''; ?>>
                                        Bildirim
                                    </option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="filter_status">Durum</label>
                                <select id="filter_status" name="status">
                                    <option value="">Tümü</option>
                                    <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>
                                        Aktif
                                    </option>
                                    <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>
                                        Pasif
                                    </option>
                                </select>
                            </div>
                            
                            <div class="filter-actions">
                                <button type="submit" class="btn-filter btn-apply">
                                    <i class="fas fa-search"></i> Filtrele
                                </button>
                                <a href="ilan-edit.php" class="btn-filter btn-reset">
                                    <i class="fas fa-redo"></i> Sıfırla
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Quick Guide -->
                    <div class="sidebar-card">
                        <h3 class="sidebar-title">
                            <i class="fas fa-info-circle"></i> Hızlı Kılavuz
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13px; color: var(--gray);">
                            <p><strong>İlan Türleri:</strong></p>
                            <ul style="margin-left: 20px;">
                                <li><strong>Duyuru:</strong> Genel bilgilendirmeler</li>
                                <li><strong>Etkinlik:</strong> Organizasyon ve etkinlikler</li>
                                <li><strong>Bildirim:</strong> Önemli hatırlatmalar</li>
                            </ul>
                            <p style="margin-top: 10px;"><strong>Öncelik Seviyeleri:</strong></p>
                            <ul style="margin-left: 20px;">
                                <li><strong>Yüksek:</strong> Acil ve önemli ilanlar</li>
                                <li><strong>Orta:</strong> Standart ilanlar</li>
                                <li><strong>Düşük:</strong> Genel bilgilendirmeler</li>
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>

            <!-- Announcements List -->
            <div class="form-card" style="margin-top: 30px;">
                <h2 class="form-title">
                    <i class="fas fa-list"></i>
                    İlanlarım (<?php echo count($announcements_list); ?>)
                </h2>

                <div class="announcements-list">
                    <?php if(count($announcements_list) > 0): ?>
                        <?php foreach($announcements_list as $announcement): ?>
                            <div class="announcement-item">
                                <div class="announcement-item-header">
                                    <div class="announcement-item-title">
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                    </div>
                                    <div class="announcement-badges">
                                        <span class="badge type-<?php echo $announcement['type']; ?>">
                                            <?php 
                                            $types = ['announcement' => 'Duyuru', 'event' => 'Etkinlik', 'notice' => 'Bildirim'];
                                            echo $types[$announcement['type']] ?? $announcement['type'];
                                            ?>
                                        </span>
                                        <span class="badge priority-<?php echo $announcement['priority']; ?>">
                                            <?php 
                                            $priorities = ['high' => 'Yüksek', 'medium' => 'Orta', 'low' => 'Düşük'];
                                            echo $priorities[$announcement['priority']] ?? $announcement['priority'];
                                            ?>
                                        </span>
                                        <span class="badge status-<?php echo $announcement['status']; ?>">
                                            <?php echo $announcement['status'] == 'active' ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="announcement-item-content">
                                    <?php echo htmlspecialchars(substr($announcement['content'], 0, 150)) . (strlen($announcement['content']) > 150 ? '...' : ''); ?>
                                </div>
                                
                                <div class="announcement-item-footer">
                                    <div class="announcement-item-meta">
                                        <span>
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php echo date('d.m.Y H:i', strtotime($announcement['created_at'])); ?>
                                        </span>
                                        <?php if($user_role == 'admin'): ?>
                                            <span>
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($announcement['full_name'] ?? $announcement['username']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="announcement-item-actions">
                                        <a href="?edit=<?php echo $announcement['id']; ?>" 
                                           class="btn-icon btn-edit" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $announcement['id']; ?>" 
                                           class="btn-icon btn-delete" title="Sil"
                                           onclick="return confirm('Bu ilanı silmek istediğinizden emin misiniz?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-announcements">
                            <i class="fas fa-bullhorn"></i>
                            <p>Henüz ilan bulunmuyor. Hemen yeni bir ilan ekleyin!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
