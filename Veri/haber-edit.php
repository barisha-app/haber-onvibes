<?php
// haber-edit.php - Haber Ekleme/Düzenleme/Silme Sayfası
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
$edit_news = null;

// Düzenleme modu kontrolü
$edit_mode = isset($_GET['edit']) && is_numeric($_GET['edit']);
if($edit_mode) {
    $edit_id = $_GET['edit'];
    
    try {
        // Admin tüm haberleri, diğerleri sadece kendi haberlerini düzenleyebilir
        if($user_role == 'admin') {
            $query = "SELECT * FROM news WHERE id = :id";
        } else {
            $query = "SELECT * FROM news WHERE id = :id AND author_id = :author_id";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $edit_id);
        if($user_role != 'admin') {
            $stmt->bindParam(':author_id', $user_id);
        }
        $stmt->execute();
        
        $edit_news = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$edit_news) {
            $error = 'Bu haberi düzenleme yetkiniz yok!';
            $edit_mode = false;
        }
    } catch(PDOException $e) {
        $error = 'Haber yüklenirken hata oluştu: ' . $e->getMessage();
        $edit_mode = false;
    }
}

// Haber silme
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    try {
        // Admin tüm haberleri, diğerleri sadece kendi haberlerini silebilir
        if($user_role == 'admin') {
            $query = "DELETE FROM news WHERE id = :id";
        } else {
            $query = "DELETE FROM news WHERE id = :id AND author_id = :author_id";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $delete_id);
        if($user_role != 'admin') {
            $stmt->bindParam(':author_id', $user_id);
        }
        
        if($stmt->execute() && $stmt->rowCount() > 0) {
            $message = 'Haber başarıyla silindi!';
        } else {
            $error = 'Haber silinemedi veya bu işlem için yetkiniz yok!';
        }
    } catch(PDOException $e) {
        $error = 'Haber silinirken hata oluştu: ' . $e->getMessage();
    }
}

// Haber ekleme/güncelleme
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_news'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $category_id = $_POST['category_id'];
    $status = $_POST['status'];
    $image = '';
    
    // Resim yükleme
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        if(in_array(strtolower($filetype), $allowed)) {
            $newname = 'news_' . time() . '.' . $filetype;
            $upload_path = '../uploads/news/' . $newname;
            
            if(!file_exists('../uploads/news')) {
                mkdir('../uploads/news', 0777, true);
            }
            
            if(move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image = 'uploads/news/' . $newname;
            }
        }
    }
    
    if(empty($title) || empty($content) || empty($category_id)) {
        $error = 'Başlık, içerik ve kategori alanları zorunludur!';
    } else {
        try {
            if($edit_mode && $edit_news) {
                // Güncelleme
                if(empty($image)) {
                    $image = $edit_news['image'];
                }
                
                $query = "UPDATE news SET 
                          title = :title,
                          content = :content,
                          category_id = :category_id,
                          image = :image,
                          status = :status,
                          updated_at = NOW()
                          WHERE id = :id";
                
                if($user_role != 'admin') {
                    $query .= " AND author_id = :author_id";
                }
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':content', $content);
                $stmt->bindParam(':category_id', $category_id);
                $stmt->bindParam(':image', $image);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':id', $edit_news['id']);
                if($user_role != 'admin') {
                    $stmt->bindParam(':author_id', $user_id);
                }
                
                if($stmt->execute()) {
                    $message = 'Haber başarıyla güncellendi!';
                    // Güncel veriyi yeniden çek
                    $edit_news['title'] = $title;
                    $edit_news['content'] = $content;
                    $edit_news['category_id'] = $category_id;
                    $edit_news['image'] = $image;
                    $edit_news['status'] = $status;
                }
            } else {
                // Yeni ekleme
                $query = "INSERT INTO news (title, content, category_id, image, author_id, status, created_at) 
                          VALUES (:title, :content, :category_id, :image, :author_id, :status, NOW())";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':content', $content);
                $stmt->bindParam(':category_id', $category_id);
                $stmt->bindParam(':image', $image);
                $stmt->bindParam(':author_id', $user_id);
                $stmt->bindParam(':status', $status);
                
                if($stmt->execute()) {
                    $message = 'Haber başarıyla eklendi!';
                }
            }
        } catch(PDOException $e) {
            $error = 'Haber kaydedilirken hata oluştu: ' . $e->getMessage();
        }
    }
}

// Kategorileri çek
try {
    $cat_query = "SELECT * FROM categories ORDER BY name ASC";
    $cat_stmt = $db->prepare($cat_query);
    $cat_stmt->execute();
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $categories = [];
}

// Filtreleme
$filter_category = isset($_GET['category']) && is_numeric($_GET['category']) ? $_GET['category'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Haberleri listele
try {
    $list_query = "SELECT n.*, c.name as category_name, u.username, u.full_name 
                   FROM news n
                   LEFT JOIN categories c ON n.category_id = c.id
                   LEFT JOIN users u ON n.author_id = u.id
                   WHERE 1=1";
    
    // Admin tüm haberleri, diğerleri sadece kendi haberlerini görür
    if($user_role != 'admin') {
        $list_query .= " AND n.author_id = :author_id";
    }
    
    // Filtreleme
    if(!empty($filter_category)) {
        $list_query .= " AND n.category_id = :category_id";
    }
    
    if(!empty($filter_date)) {
        $list_query .= " AND DATE(n.created_at) = :filter_date";
    }
    
    if(!empty($search)) {
        $list_query .= " AND (n.title LIKE :search OR n.content LIKE :search)";
    }
    
    $list_query .= " ORDER BY n.created_at DESC LIMIT 50";
    
    $list_stmt = $db->prepare($list_query);
    
    if($user_role != 'admin') {
        $list_stmt->bindParam(':author_id', $user_id);
    }
    
    if(!empty($filter_category)) {
        $list_stmt->bindParam(':category_id', $filter_category);
    }
    
    if(!empty($filter_date)) {
        $list_stmt->bindParam(':filter_date', $filter_date);
    }
    
    if(!empty($search)) {
        $search_term = '%' . $search . '%';
        $list_stmt->bindParam(':search', $search_term);
    }
    
    $list_stmt->execute();
    $news_list = $list_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $news_list = [];
    $error = 'Haberler yüklenirken hata oluştu: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Haber Yönetimi - ONVIBES</title>
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
            min-height: 200px;
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

        /* News List */
        .news-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }

        .news-item {
            background: var(--surface);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            gap: 15px;
            align-items: center;
            border-left: 4px solid var(--red);
            transition: all 0.3s;
        }

        .news-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .news-item-img {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
        }

        .news-item-info {
            flex: 1;
        }

        .news-item-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .news-item-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 12px;
            color: var(--gray);
        }

        .news-item-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-published {
            background: #d4edda;
            color: #155724;
        }

        .status-draft {
            background: #fff3cd;
            color: #856404;
        }

        .status-pending {
            background: #d1ecf1;
            color: #0c5460;
        }

        .news-item-actions {
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

        .btn-view {
            background: #28a745;
        }

        .btn-view:hover {
            background: #218838;
            transform: scale(1.1);
        }

        .no-news {
            text-align: center;
            padding: 40px;
            color: var(--gray);
        }

        .no-news i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* File Upload Preview */
        .file-preview {
            margin-top: 10px;
        }

        .file-preview img {
            max-width: 200px;
            border-radius: 8px;
            border: 2px solid var(--border);
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

            .news-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .news-item-img {
                width: 100%;
                height: 150px;
            }

            .news-item-actions {
                width: 100%;
                justify-content: flex-end;
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
                    <?php echo $edit_mode ? 'Haber Düzenle' : 'Yeni Haber Ekle'; ?>
                </h1>
                <div class="breadcrumb">
                    <a href="../index.php"><i class="fas fa-home"></i> Ana Sayfa</a>
                    <span>/</span>
                    <span>Haber Yönetimi</span>
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
                        <?php echo $edit_mode ? 'Haber Bilgileri' : 'Yeni Haber Oluştur'; ?>
                    </h2>

                    <form method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="title">Haber Başlığı *</label>
                            <input type="text" id="title" name="title" required 
                                   value="<?php echo $edit_mode ? htmlspecialchars($edit_news['title']) : ''; ?>"
                                   placeholder="Haber başlığını girin...">
                        </div>

                        <div class="form-group">
                            <label for="category_id">Kategori *</label>
                            <select id="category_id" name="category_id" required>
                                <option value="">Kategori Seçin</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"
                                            <?php echo ($edit_mode && $edit_news['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="content">Haber İçeriği *</label>
                            <textarea id="content" name="content" required 
                                      placeholder="Haber içeriğini buraya yazın..."><?php echo $edit_mode ? htmlspecialchars($edit_news['content']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="image">Haber Görseli</label>
                            <input type="file" id="image" name="image" accept="image/*">
                            <?php if($edit_mode && !empty($edit_news['image'])): ?>
                                <div class="file-preview">
                                    <img src="../<?php echo htmlspecialchars($edit_news['image']); ?>" alt="Mevcut Görsel">
                                    <p style="font-size: 12px; color: var(--gray); margin-top: 5px;">
                                        Mevcut görsel (Yeni yüklerseniz değişir)
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="status">Durum *</label>
                            <select id="status" name="status" required>
                                <option value="draft" <?php echo ($edit_mode && $edit_news['status'] == 'draft') ? 'selected' : ''; ?>>
                                    Taslak
                                </option>
                                <option value="pending" <?php echo ($edit_mode && $edit_news['status'] == 'pending') ? 'selected' : ''; ?>>
                                    Onay Bekliyor
                                </option>
                                <?php if($user_role == 'admin'): ?>
                                    <option value="published" <?php echo ($edit_mode && $edit_news['status'] == 'published') ? 'selected' : ''; ?>>
                                        Yayınlandı
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="save_news" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                <?php echo $edit_mode ? 'Değişiklikleri Kaydet' : 'Haber Yayınla'; ?>
                            </button>
                            <?php if($edit_mode): ?>
                                <a href="haber-edit.php" class="btn btn-secondary">
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
                                <label for="category">Kategori</label>
                                <select id="category" name="category">
                                    <option value="">Tüm Kategoriler</option>
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"
                                                <?php echo $filter_category == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="date">Tarih</label>
                                <input type="date" id="date" name="date" 
                                       value="<?php echo htmlspecialchars($filter_date); ?>">
                            </div>
                            
                            <div class="filter-actions">
                                <button type="submit" class="btn-filter btn-apply">
                                    <i class="fas fa-search"></i> Filtrele
                                </button>
                                <a href="haber-edit.php" class="btn-filter btn-reset">
                                    <i class="fas fa-redo"></i> Sıfırla
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Quick Stats -->
                    <div class="sidebar-card">
                        <h3 class="sidebar-title">
                            <i class="fas fa-chart-bar"></i> Özet
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <div style="padding: 10px; background: var(--surface); border-radius: 8px;">
                                <div style="font-size: 24px; font-weight: 800; color: var(--red);">
                                    <?php echo count($news_list); ?>
                                </div>
                                <div style="font-size: 12px; color: var(--gray);">Toplam Haber</div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

            <!-- News List -->
            <div class="form-card" style="margin-top: 30px;">
                <h2 class="form-title">
                    <i class="fas fa-newspaper"></i>
                    Haberlerim
                </h2>

                <div class="news-list">
                    <?php if(count($news_list) > 0): ?>
                        <?php foreach($news_list as $news): ?>
                            <div class="news-item">
                                <?php if(!empty($news['image'])): ?>
                                    <img src="../<?php echo htmlspecialchars($news['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($news['title']); ?>" 
                                         class="news-item-img">
                                <?php else: ?>
                                    <div class="news-item-img" style="background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="news-item-info">
                                    <div class="news-item-title">
                                        <?php echo htmlspecialchars($news['title']); ?>
                                    </div>
                                    <div class="news-item-meta">
                                        <span>
                                            <i class="fas fa-folder"></i>
                                            <?php echo htmlspecialchars($news['category_name']); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php echo date('d.m.Y H:i', strtotime($news['created_at'])); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-eye"></i>
                                            <?php echo number_format($news['views']); ?>
                                        </span>
                                        <span class="status-badge status-<?php echo $news['status']; ?>">
                                            <?php 
                                            $status_text = [
                                                'draft' => 'Taslak',
                                                'pending' => 'Beklemede',
                                                'published' => 'Yayında'
                                            ];
                                            echo $status_text[$news['status']] ?? $news['status'];
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="news-item-actions">
                                    <?php if($news['status'] == 'published'): ?>
                                        <a href="haber-detay.php?id=<?php echo $news['id']; ?>" 
                                           class="btn-icon btn-view" title="Görüntüle">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?edit=<?php echo $news['id']; ?>" 
                                       class="btn-icon btn-edit" title="Düzenle">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $news['id']; ?>" 
                                       class="btn-icon btn-delete" title="Sil"
                                       onclick="return confirm('Bu haberi silmek istediğinizden emin misiniz?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-news">
                            <i class="fas fa-newspaper"></i>
                            <p>Henüz haber bulunmuyor. Hemen yeni bir haber ekleyin!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Dosya önizleme
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.file-preview');
                    if (preview) {
                        const img = preview.querySelector('img');
                        if (img) {
                            img.src = e.target.result;
                        }
                    } else {
                        const newPreview = document.createElement('div');
                        newPreview.className = 'file-preview';
                        newPreview.innerHTML = '<img src="' + e.target.result + '" alt="Önizleme"><p style="font-size: 12px; color: var(--gray); margin-top: 5px;">Yeni görsel önizlemesi</p>';
                        document.getElementById('image').parentNode.appendChild(newPreview);
                    }
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
