<?php
// haber-ekle.php - Kullanıcı haber ekleme sayfası
session_start();

// Giriş kontrolü
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../Giris/login.php');
    exit();
}

include '../config.php';
$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Form gönderildiğinde
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $main_image = trim($_POST['main_image'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $source = trim($_POST['source'] ?? '');
    $author = trim($_POST['author'] ?? $_SESSION['username']);
    $tags = trim($_POST['tags'] ?? '');
    $image_size = trim($_POST['image_size'] ?? 'medium');
    $text_color = trim($_POST['text_color'] ?? '');
    $bg_color = trim($_POST['bg_color'] ?? '');
    
    // Validasyon
    if(empty($title) || empty($summary) || empty($content) || $category_id === 0) {
        $error = 'Lütfen zorunlu alanları doldurun! (Başlık, Özet ve İçerik)';
    } else {
        try {
            // Haberi veritabanına kaydet
            $query = "INSERT INTO news (title, summary, content, image, category_id, author_id, source, author_name, tags, image_size, text_color, bg_color, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
            $stmt = $db->prepare($query);
            
            if($stmt->execute([$title, $summary, $content, $main_image, $category_id, $_SESSION['user_id'], $source, $author, $tags, $image_size, $text_color, $bg_color])) {
                $success = 'Haberiniz başarıyla gönderildi! Admin onayından sonra yayınlanacak.';
                // Formu temizle
                $_POST = array();
            } else {
                $error = 'Haber kaydedilirken bir hata oluştu!';
            }
        } catch (PDOException $e) {
            // Eğer sütunlar hala yoksa, eski formata göre kaydet
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                $query = "INSERT INTO news (title, summary, content, image, category_id, author_id, status, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
                $stmt = $db->prepare($query);
                
                if($stmt->execute([$title, $summary, $content, $main_image, $category_id, $_SESSION['user_id']])) {
                    $success = 'Haberiniz başarıyla gönderildi! Admin onayından sonra yayınlanacak.';
                    // Formu temizle
                    $_POST = array();
                } else {
                    $error = 'Haber kaydedilirken bir hata oluştu!';
                }
            } else {
                $error = 'Veritabanı hatası: ' . $e->getMessage();
            }
        }
    }
}

// Kategorileri getir
$categories = [];
if($db) {
    try {
        $cat_query = "SELECT id, name FROM categories ORDER BY name";
        $cat_stmt = $db->prepare($cat_query);
        $cat_stmt->execute();
        $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = 'Kategoriler yüklenirken hata: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Haber Ekle - ONVIBES</title>
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
            --editor-bg: #f8f9fa;
            --preview-bg: #ffffff;
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
            --editor-bg: #2c3e50;
            --preview-bg: #34495e;
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
            padding: 1rem;
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
            gap: 1rem;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .auth-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-weight: 500;
        }

        .verified-badge {
            color: #3498db;
            font-size: 0.9rem;
        }

        .btn {
            padding: 0.4rem 1rem;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 0.9rem;
            white-space: nowrap;
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

        .theme-toggle {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .btn:hover, .theme-toggle:hover {
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
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* ANA İÇERİK - İKİ KOLON */
        .editor-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            padding: 1rem;
            max-width: 1400px;
            margin: 0 auto;
            min-height: calc(100vh - 120px);
        }

        @media (min-width: 1024px) {
            .editor-container {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* SOL TARAF - HABER EDİTÖRÜ */
        .editor-panel {
            background: var(--editor-bg);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            height: fit-content;
            position: sticky;
            top: 1rem;
        }

        .panel-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--secondary-color);
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: 600;
            font-size: 1rem;
        }

        .form-label .required {
            color: var(--accent-color);
        }

        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--card-bg);
            color: var(--text-color);
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
            line-height: 1.5;
        }

        .form-textarea.large {
            min-height: 200px;
        }

        .form-help {
            font-size: 0.8rem;
            color: var(--text-color);
            opacity: 0.7;
            margin-top: 0.3rem;
        }

        /* EK DETAYLAR GRUBU */
        .details-group {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .details-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--secondary-color);
            font-weight: 600;
            cursor: pointer;
        }

        .details-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .details-content {
                grid-template-columns: 1fr 1fr;
            }
        }

        .color-picker {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .color-input {
            width: 60px;
            height: 40px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            cursor: pointer;
        }

        .size-options {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .size-option {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-color);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.8rem;
        }

        .size-option.active {
            background: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }

        /* EDITOR ARAÇ ÇUBUĞU */
        .editor-toolbar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            padding: 0.8rem;
            background: var(--card-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .tool-btn {
            padding: 0.5rem 0.8rem;
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            color: var(--text-color);
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .tool-btn:hover {
            background: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }

        /* SAĞ TARAF - CANLI ÖNİZLEME */
        .preview-panel {
            background: var(--preview-bg);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            min-height: 600px;
            display: flex;
            flex-direction: column;
        }

        .preview-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .preview-title {
            font-size: 1.3rem;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .preview-actions {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
        }

        .action-btn {
            padding: 0.4rem 0.8rem;
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            color: var(--text-color);
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: var(--secondary-color);
            color: white;
        }

        .preview-content {
            flex: 1;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 1.5rem;
            background: var(--card-bg);
            overflow-y: auto;
            max-height: 500px;
        }

        /* HABER ÖNİZLEME STİLLERİ */
        .news-preview {
            max-width: 100%;
            line-height: 1.6;
        }

        .news-preview h1 {
            color: var(--primary-color);
            font-size: 1.8rem;
            margin-bottom: 1rem;
            line-height: 1.3;
            border-bottom: 3px solid var(--secondary-color);
            padding-bottom: 0.5rem;
        }

        .news-preview h2 {
            color: var(--secondary-color);
            font-size: 1.4rem;
            margin: 1.5rem 0 0.8rem 0;
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 0.3rem;
        }

        .news-preview h3 {
            color: var(--accent-color);
            font-size: 1.2rem;
            margin: 1.2rem 0 0.6rem 0;
        }

        .news-preview p {
            margin-bottom: 1rem;
            text-align: justify;
        }

        .news-preview strong {
            color: var(--accent-color);
            font-weight: 600;
        }

        .news-preview em {
            color: var(--text-color);
            opacity: 0.8;
            font-style: italic;
        }

        /* GÖRSEL BOYUTLARI */
        .news-preview .news-image.small {
            width: 50%;
            max-width: 300px;
            height: auto;
        }

        .news-preview .news-image.medium {
            width: 75%;
            max-width: 500px;
            height: auto;
        }

        .news-preview .news-image.large {
            width: 100%;
            max-width: 800px;
            height: auto;
        }

        .news-preview .news-image {
            display: block;
            margin: 1.5rem auto;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .news-preview .image-caption {
            text-align: center;
            font-style: italic;
            color: var(--text-color);
            opacity: 0.7;
            margin-top: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .news-preview .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .news-preview .gallery img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .news-preview .video-container {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 56.25%;
            margin: 1.5rem 0;
        }

        .news-preview .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 8px;
            border: none;
        }

        /* HABER META BİLGİLERİ */
        .news-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1.5rem 0;
            padding: 1rem;
            background: var(--bg-color);
            border-radius: 8px;
            border-left: 4px solid var(--secondary-color);
            font-size: 0.9rem;
        }

        .news-source {
            color: var(--accent-color);
            font-weight: 600;
        }

        .news-author {
            color: var(--secondary-color);
            font-style: italic;
        }

        .news-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        .news-tag {
            background: var(--secondary-color);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
        }

        /* GÖNDER BUTONU */
        .submit-btn {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .submit-btn:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* MESAJLAR */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
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

        .dark-mode .alert-success {
            background: #155724;
            color: #d4edda;
            border-color: #0c3514;
        }

        .dark-mode .alert-error {
            background: #721c24;
            color: #f8d7da;
            border-color: #491217;
        }

        /* MOBİL UYUMLULUK */
        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                gap: 1rem;
            }

            .auth-buttons {
                flex-direction: column;
                width: 100%;
            }

            .user-menu {
                flex-direction: column;
                width: 100%;
                gap: 0.5rem;
            }

            .editor-toolbar {
                justify-content: center;
            }

            .preview-header {
                flex-direction: column;
                align-items: stretch;
            }

            .preview-actions {
                margin-left: 0;
                justify-content: center;
            }

            .news-meta {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-start;
            }
        }

        @media (min-width: 769px) {
            .header-top {
                margin-bottom: 1rem;
            }

            .logo {
                font-size: 2.2rem;
            }

            .header-controls {
                gap: 1rem;
            }

            .auth-buttons {
                gap: 1rem;
            }

            .user-menu {
                gap: 1rem;
            }

            .user-info {
                padding: 0.5rem 1rem;
                border-radius: 20px;
            }

            .btn {
                padding: 0.5rem 1.5rem;
                border-radius: 25px;
                font-size: 1rem;
            }

            .theme-toggle {
                width: 50px;
                height: 50px;
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-top">
            <a href="../index.php" class="logo">ONVIBES</a>
            <div class="header-controls">
                <button class="theme-toggle" onclick="toggleTheme()">🌙</button>
                <div class="auth-buttons">
                    <?php if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                        <div class="user-menu">
                            <div class="user-info">
                                <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                                <?php if(isset($_SESSION['verified']) && $_SESSION['verified'] === true): ?>
                                    <span class="verified-badge">✓</span>
                                <?php endif; ?>
                            </div>
                            <a href="../profil.php" class="btn btn-profile">Profilim</a>
                            <a href="haberlerim.php" class="btn btn-profile">Haberlerim</a>
                            <a href="../Giris/logout.php" class="btn btn-logout">Çıkış</a>
                        </div>
                    <?php else: ?>
                        <a href="../Giris/login.php" class="btn btn-login">Giriş Yap</a>
                        <a href="../Giris/register.php" class="btn btn-register">Kayıt Ol</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- ANA İÇERİK -->
    <div class="editor-container">
        <!-- SOL PANEL - HABER EDİTÖRÜ -->
        <div class="editor-panel">
            <a href="../index.php" class="btn-back">← Ana Sayfaya Dön</a>
            
            <h2 class="panel-title">✏️ Haber Editörü</h2>

            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="newsForm">
                <!-- HABER BAŞLIĞI -->
                <div class="form-group">
                    <label class="form-label">Haber Başlığı <span class="required">*</span></label>
                    <input type="text" class="form-input" name="title" placeholder="Manşet haberi için dikkat çekici bir başlık..." 
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                </div>

                <!-- HABER ÖZETİ -->
                <div class="form-group">
                    <label class="form-label">Haber Özeti <span class="required">*</span></label>
                    <textarea class="form-textarea" name="summary" placeholder="Haberin kısa özetini yazın (maksimum 200 karakter)..." required><?php echo htmlspecialchars($_POST['summary'] ?? ''); ?></textarea>
                    <div class="form-help">Bu özet ana sayfada ve haber listelerinde görünecektir.</div>
                </div>

                <!-- ANA GÖRSEL -->
                <div class="form-group">
                    <label class="form-label">Ana Görsel URL</label>
                    <input type="url" class="form-input" name="main_image" placeholder="https://example.com/ana-gorsel.jpg" 
                           value="<?php echo htmlspecialchars($_POST['main_image'] ?? ''); ?>">
                    <div class="form-help">Haberin ana görseli için URL ekleyin</div>
                </div>

                <!-- KATEGORİ -->
                <div class="form-group">
                    <label class="form-label">Kategori <span class="required">*</span></label>
                    <select class="form-select" name="category_id" required>
                        <option value="">Kategori Seçin</option>
                        <?php foreach($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- EK DETAYLAR -->
                <div class="details-group">
                    <div class="details-header" onclick="toggleDetails()">
                        <span>⚙️ Ek Detaylar</span>
                        <span id="detailsArrow">▼</span>
                    </div>
                    <div class="details-content" id="detailsContent">
                        <!-- KAYNAK -->
                        <div class="form-group">
                            <label class="form-label">Haber Kaynağı</label>
                            <input type="text" class="form-input" name="source" placeholder="Örn: BBC News, CNN Türk..." 
                                   value="<?php echo htmlspecialchars($_POST['source'] ?? ''); ?>">
                            <div class="form-help">Haberin alındığı kaynak</div>
                        </div>

                        <!-- YAZAR -->
                        <div class="form-group">
                            <label class="form-label">Yazar</label>
                            <input type="text" class="form-input" name="author" placeholder="Haber yazarı..." 
                                   value="<?php echo htmlspecialchars($_POST['author'] ?? $_SESSION['username']); ?>">
                            <div class="form-help">Haberin yazarı (varsayılan: siz)</div>
                        </div>

                        <!-- ETİKETLER -->
                        <div class="form-group">
                            <label class="form-label">Etiketler</label>
                            <input type="text" class="form-input" name="tags" placeholder="teknoloji,spor,ekonomi..." 
                                   value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>">
                            <div class="form-help">Virgülle ayırarak etiketler ekleyin</div>
                        </div>

                        <!-- GÖRSEL BOYUTU -->
                        <div class="form-group">
                            <label class="form-label">Görsel Boyutu</label>
                            <div class="size-options">
                                <div class="size-option <?php echo ($_POST['image_size'] ?? 'medium') === 'small' ? 'active' : ''; ?>" 
                                     onclick="selectSize('small')">Küçük</div>
                                <div class="size-option <?php echo ($_POST['image_size'] ?? 'medium') === 'medium' ? 'active' : ''; ?>" 
                                     onclick="selectSize('medium')">Orta</div>
                                <div class="size-option <?php echo ($_POST['image_size'] ?? 'medium') === 'large' ? 'active' : ''; ?>" 
                                     onclick="selectSize('large')">Büyük</div>
                            </div>
                            <input type="hidden" name="image_size" id="imageSize" value="<?php echo $_POST['image_size'] ?? 'medium'; ?>">
                        </div>

                        <!-- RENK AYARLARI -->
                        <div class="form-group">
                            <label class="form-label">Metin Rengi</label>
                            <div class="color-picker">
                                <input type="color" class="color-input" name="text_color" value="<?php echo $_POST['text_color'] ?? '#2c3e50'; ?>" 
                                       onchange="updatePreview()">
                                <span>Metin rengini değiştir</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Arkaplan Rengi</label>
                            <div class="color-picker">
                                <input type="color" class="color-input" name="bg_color" value="<?php echo $_POST['bg_color'] ?? '#ffffff'; ?>" 
                                       onchange="updatePreview()">
                                <span>Arkaplan rengini değiştir</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HABER İÇERİĞİ -->
                <div class="form-group">
                    <label class="form-label">Haber İçeriği <span class="required">*</span></label>
                    <div class="editor-toolbar">
                        <button type="button" class="tool-btn" onclick="formatText('bold')"><b>B</b></button>
                        <button type="button" class="tool-btn" onclick="formatText('italic')"><i>İ</i></button>
                        <button type="button" class="tool-btn" onclick="formatText('heading')">H</button>
                        <button type="button" class="tool-btn" onclick="formatText('image')">🖼️</button>
                        <button type="button" class="tool-btn" onclick="formatText('video')">🎥</button>
                        <button type="button" class="tool-btn" onclick="formatText('gallery')">📷</button>
                        <button type="button" class="tool-btn" onclick="formatText('quote')">❝</button>
                    </div>
                    <textarea class="form-textarea large" name="content" id="contentEditor" 
                              placeholder="Haberin detaylı içeriğini yazın... Örnek kullanım:

# Ana Başlık
Haberin giriş paragrafı...

## Alt Başlık
Devam eden içerik...

![Resim Açıklaması](https://example.com/resim.jpg)
*Resim alt yazısı*

**Önemli noktalar kalın yazılabilir**
*Vurgulanacak yerler italik*

Daha fazla detay...
" required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                    <div class="form-help">
                        İpucu: # Başlık, **kalın**, *italik*, ![açıklama](url) resim, [bağlantı](url)
                    </div>
                </div>

                <button type="submit" class="submit-btn">🚀 Haberi Gönder ve Onaya İlet</button>
            </form>
        </div>

        <!-- SAĞ PANEL - CANLI ÖNİZLEME -->
        <div class="preview-panel">
            <div class="preview-header">
                <h3 class="preview-title">👁️ Canlı Önizleme</h3>
                <div class="preview-actions">
                    <button class="action-btn" onclick="refreshPreview()">🔄 Yenile</button>
                    <button class="action-btn" onclick="editInPreview()">✏️ Düzenle</button>
                    <button class="action-btn" onclick="clearPreview()">🗑️ Temizle</button>
                    <button class="action-btn" onclick="togglePreviewStyle()">🎨 Stil</button>
                </div>
            </div>
            <div class="preview-content" id="previewContent" contenteditable="false">
                <div style="text-align: center; color: var(--text-color); opacity: 0.7; padding: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📝</div>
                    <h3>Haber Önizleme</h3>
                    <p>Sol taraftaki editörde haberinizi yazmaya başlayın, burada canlı önizlemesini göreceksiniz.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // TEMA DEĞİŞTİRME
        function toggleTheme() {
            document.body.classList.toggle('dark-mode');
            const themeToggle = document.querySelector('.theme-toggle');
            themeToggle.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
            refreshPreview();
        }

        // EK DETAYLARI AÇ/KAPA
        function toggleDetails() {
            const content = document.getElementById('detailsContent');
            const arrow = document.getElementById('detailsArrow');
            if (content.style.display === 'none' || content.style.display === '') {
                content.style.display = 'grid';
                arrow.textContent = '▲';
            } else {
                content.style.display = 'none';
                arrow.textContent = '▼';
            }
        }

        // GÖRSEL BOYUTU SEÇİMİ
        function selectSize(size) {
            document.getElementById('imageSize').value = size;
            document.querySelectorAll('.size-option').forEach(option => {
                option.classList.remove('active');
            });
            event.target.classList.add('active');
            updatePreview();
        }

        // METİN BİÇİMLENDİRME
        function formatText(type) {
            const editor = document.getElementById('contentEditor');
            const start = editor.selectionStart;
            const end = editor.selectionEnd;
            const selectedText = editor.value.substring(start, end);
            let newText = '';

            switch(type) {
                case 'bold':
                    newText = `**${selectedText}**`;
                    break;
                case 'italic':
                    newText = `*${selectedText}*`;
                    break;
                case 'heading':
                    newText = `## ${selectedText}`;
                    break;
                case 'image':
                    newText = `![Resim açıklaması](${selectedText || 'https://example.com/resim.jpg'})`;
                    break;
                case 'video':
                    newText = `{{video:${selectedText || 'https://youtube.com/watch?v=...'}}}`;
                    break;
                case 'gallery':
                    newText = `{{gallery:${selectedText || 'https://example.com/resim1.jpg,https://example.com/resim2.jpg'}}}`;
                    break;
                case 'quote':
                    newText = `> ${selectedText}`;
                    break;
            }

            editor.value = editor.value.substring(0, start) + newText + editor.value.substring(end);
            editor.focus();
            editor.setSelectionRange(start + newText.length, start + newText.length);
            updatePreview();
        }

        // MARKDOWN'U HTML'E ÇEVİR
        function markdownToHtml(markdown) {
            let html = markdown;
            
            // Başlıklar
            html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
            html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
            html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>');
            
            // Kalın ve italik
            html = html.replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>');
            html = html.replace(/\*(.*?)\*/gim, '<em>$1</em>');
            
            // Resimler
            html = html.replace(/!\[(.*?)\]\((.*?)\)/gim, (match, alt, src) => {
                const size = document.getElementById('imageSize').value;
                return `<div><img src="${src}" alt="${alt}" class="news-image ${size}" onerror="this.style.display='none'"><div class="image-caption">${alt}</div></div>`;
            });
            
            // Bağlantılar
            html = html.replace(/\[(.*?)\]\((.*?)\)/gim, '<a href="$2" target="_blank">$1</a>');
            
            // Alıntılar
            html = html.replace(/^> (.*$)/gim, '<blockquote>$1</blockquote>');
            
            // Galeri
            html = html.replace(/\{\{gallery:(.*?)\}\}/gim, (match, images) => {
                const imageList = images.split(',').map(img => img.trim());
                let galleryHtml = '<div class="gallery">';
                imageList.forEach(img => {
                    galleryHtml += `<img src="${img}" alt="Galeri resmi" onerror="this.style.display='none'">`;
                });
                galleryHtml += '</div>';
                return galleryHtml;
            });
            
            // Video
            html = html.replace(/\{\{video:(.*?)\}\}/gim, (match, url) => {
                if (url.includes('youtube.com') || url.includes('youtu.be')) {
                    const videoId = extractYouTubeId(url);
                    return `<div class="video-container"><iframe src="https://www.youtube.com/embed/${videoId}" frameborder="0" allowfullscreen></iframe></div>`;
                } else {
                    return `<video controls style="width:100%;border-radius:8px;"><source src="${url}"></video>`;
                }
            });
            
            // Paragraflar
            html = html.replace(/\n\n/g, '</p><p>');
            html = html.replace(/\n/g, '<br>');
            html = '<p>' + html + '</p>';
            
            // Temizlik
            html = html.replace(/<p><\/p>/g, '');
            
            return html;
        }

        // YOUTUBE ID ÇIKARMA
        function extractYouTubeId(url) {
            const match = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
            return match ? match[1] : '';
        }

        // ÖNİZLEMEYİ GÜNCELLE
        function updatePreview() {
            const form = document.getElementById('newsForm');
            const preview = document.getElementById('previewContent');
            
            const title = form.querySelector('[name="title"]').value;
            const summary = form.querySelector('[name="summary"]').value;
            const mainImage = form.querySelector('[name="main_image"]').value;
            const content = form.querySelector('[name="content"]').value;
            const source = form.querySelector('[name="source"]').value;
            const author = form.querySelector('[name="author"]').value;
            const tags = form.querySelector('[name="tags"]').value;
            const textColor = form.querySelector('[name="text_color"]').value;
            const bgColor = form.querySelector('[name="bg_color"]').value;
            const imageSize = form.querySelector('[name="image_size"]').value;
            
            let previewHTML = '';
            
            if (title || summary || content) {
                // Özel stil uygula
                preview.style.color = textColor;
                preview.style.backgroundColor = bgColor;
                
                if (title) {
                    previewHTML += `<h1 style="color: ${textColor}">${title}</h1>`;
                }
                
                // Meta bilgileri
                if (source || author) {
                    previewHTML += `<div class="news-meta" style="border-left-color: ${textColor}">`;
                    if (source) {
                        previewHTML += `<div class="news-source">Kaynak: ${source}</div>`;
                    }
                    if (author) {
                        previewHTML += `<div class="news-author">Yazar: ${author}</div>`;
                    }
                    previewHTML += `</div>`;
                }
                
                if (summary) {
                    previewHTML += `<p style="font-size: 1.1em; color: ${textColor}; opacity: 0.8; border-left: 4px solid ${textColor}; padding-left: 1rem; margin: 1rem 0;">${summary}</p>`;
                }
                
                if (mainImage) {
                    previewHTML += `<img src="${mainImage}" alt="${title}" class="news-image ${imageSize}" onerror="this.style.display='none'">`;
                }
                
                if (content) {
                    previewHTML += markdownToHtml(content);
                }
                
                // Etiketler
                if (tags) {
                    const tagList = tags.split(',').map(tag => tag.trim()).filter(tag => tag);
                    if (tagList.length > 0) {
                        previewHTML += `<div class="news-tags">`;
                        tagList.forEach(tag => {
                            previewHTML += `<span class="news-tag">${tag}</span>`;
                        });
                        previewHTML += `</div>`;
                    }
                }
                
                preview.innerHTML = previewHTML;
            } else {
                preview.innerHTML = '<div style="text-align: center; color: var(--text-color); opacity: 0.7; padding: 2rem;"><div style="font-size: 3rem; margin-bottom: 1rem;">📝</div><h3>Haber Önizleme</h3><p>Sol taraftaki editörde haberinizi yazmaya başlayın, burada canlı önizlemesini göreceksiniz.</p></div>';
            }
        }

        // ÖNİZLEMEDE DÜZENLEME
        function editInPreview() {
            const preview = document.getElementById('previewContent');
            const isEditable = preview.contentEditable === 'true';
            preview.contentEditable = !isEditable;
            preview.style.border = preview.contentEditable === 'true' ? '2px solid var(--secondary-color)' : '2px solid var(--border-color)';
            
            if (preview.contentEditable === 'true') {
                preview.focus();
            }
        }

        // ÖNİZLEMEYİ YENİLE
        function refreshPreview() {
            updatePreview();
        }

        // ÖNİZLEMEYİ TEMİZLE
        function clearPreview() {
            if (confirm('Önizleme alanını temizlemek istediğinizden emin misiniz?')) {
                const preview = document.getElementById('previewContent');
                preview.innerHTML = '<div style="text-align: center; color: var(--text-color); opacity: 0.7; padding: 2rem;"><div style="font-size: 3rem; margin-bottom: 1rem;">📝</div><h3>Haber Önizleme</h3><p>Sol taraftaki editörde haberinizi yazmaya başlayın, burada canlı önizlemesini göreceksiniz.</p></div>';
            }
        }

        // ÖNİZLEME STİLİNİ DEĞİŞTİR
        function togglePreviewStyle() {
            const preview = document.getElementById('previewContent');
            preview.classList.toggle('custom-style');
            updatePreview();
        }

        // SAYFA YÜKLENDİĞİNDE
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            if(savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                document.querySelector('.theme-toggle').textContent = '☀️';
            }

            const form = document.getElementById('newsForm');
            const inputs = form.querySelectorAll('input, textarea, select');
            
            inputs.forEach(input => {
                input.addEventListener('input', updatePreview);
                input.addEventListener('change', updatePreview);
            });

            // İlk önizlemeyi güncelle
            updatePreview();
        });
    </script>
</body>
</html>
