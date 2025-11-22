<?php
// profil.php - Kullanıcı Profil Yönetimi
session_start();

// Giriş kontrolü
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: Giris/login.php');
    exit();
}

include 'config.php';
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

// Profil güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $bio = trim($_POST['bio']);
    $phone = trim($_POST['phone']);
    
    // Sosyal medya
    $facebook = trim($_POST['facebook']);
    $twitter = trim($_POST['twitter']);
    $instagram = trim($_POST['instagram']);
    $linkedin = trim($_POST['linkedin']);
    
    try {
        $query = "UPDATE users SET 
                  email = :email,
                  full_name = :full_name,
                  bio = :bio,
                  phone = :phone,
                  facebook = :facebook,
                  twitter = :twitter,
                  instagram = :instagram,
                  linkedin = :linkedin
                  WHERE id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':bio', $bio);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':facebook', $facebook);
        $stmt->bindParam(':twitter', $twitter);
        $stmt->bindParam(':instagram', $instagram);
        $stmt->bindParam(':linkedin', $linkedin);
        $stmt->bindParam(':user_id', $user_id);
        
        if($stmt->execute()) {
            $message = 'Profil başarıyla güncellendi!';
        }
    } catch(PDOException $e) {
        $error = 'Profil güncellenirken hata oluştu: ' . $e->getMessage();
    }
}

// Şifre değiştirme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if($new_password !== $confirm_password) {
        $error = 'Yeni şifreler eşleşmiyor!';
    } elseif(strlen($new_password) < 6) {
        $error = 'Yeni şifre en az 6 karakter olmalıdır!';
    } else {
        try {
            $query = "SELECT password FROM users WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update_query = "UPDATE users SET password = :password WHERE id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':password', $hashed_password);
                $update_stmt->bindParam(':user_id', $user_id);
                
                if($update_stmt->execute()) {
                    $message = 'Şifre başarıyla değiştirildi!';
                }
            } else {
                $error = 'Mevcut şifre yanlış!';
            }
        } catch(PDOException $e) {
            $error = 'Şifre değiştirilirken hata oluştu: ' . $e->getMessage();
        }
    }
}

// Kullanıcı bilgilerini çek
try {
    $query = "SELECT * FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Kullanıcı rolünü belirle
    $user_role = 'Kullanıcı';
    if(isset($user_data['is_admin']) && $user_data['is_admin'] == 1) {
        $user_role = 'Administrator';
    } elseif(isset($user_data['is_writer']) && $user_data['is_writer'] == 1) {
        $user_role = 'Yazar';
    } elseif(isset($user_data['is_reporter']) && $user_data['is_reporter'] == 1) {
        $user_role = 'Muhabir';
    }
    
} catch(PDOException $e) {
    $error = 'Kullanıcı bilgileri alınamadı!';
    $user_data = [];
}

// İstatistikler
try {
    // Toplam haber sayısı
    $news_count_query = "SELECT COUNT(*) FROM news WHERE author_id = :user_id";
    $news_stmt = $db->prepare($news_count_query);
    $news_stmt->bindParam(':user_id', $user_id);
    $news_stmt->execute();
    $news_count = $news_stmt->fetchColumn();
    
    // Toplam görüntülenme
    $views_query = "SELECT COALESCE(SUM(view_count), 0) FROM news WHERE author_id = :user_id";
    $views_stmt = $db->prepare($views_query);
    $views_stmt->bindParam(':user_id', $user_id);
    $views_stmt->execute();
    $total_views = $views_stmt->fetchColumn();
    
    // Köşe yazısı sayısı (eğer yazar ise)
    $columns_count = 0;
    if((isset($user_data['is_writer']) && $user_data['is_writer'] == 1) || 
       (isset($user_data['is_admin']) && $user_data['is_admin'] == 1)) {
        $columns_query = "SELECT COUNT(*) FROM columns WHERE author_id = :user_id";
        $columns_stmt = $db->prepare($columns_query);
        $columns_stmt->bindParam(':user_id', $user_id);
        $columns_stmt->execute();
        $columns_count = $columns_stmt->fetchColumn();
    }
    
    // Yorum sayısı
    $comments_query = "SELECT COUNT(*) FROM comments WHERE user_id = :user_id";
    $comments_stmt = $db->prepare($comments_query);
    $comments_stmt->bindParam(':user_id', $user_id);
    $comments_stmt->execute();
    $comments_count = $comments_stmt->fetchColumn();
    
} catch(PDOException $e) {
    $news_count = 0;
    $total_views = 0;
    $columns_count = 0;
    $comments_count = 0;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilim - ONVIBES</title>
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

        .dark-mode .page-title {
            color: var(--text);
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

        /* Profile Grid */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }

        .profile-card {
            background: var(--light);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .dark-mode .profile-card {
            background: var(--light);
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 25px;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--red);
        }

        .dark-mode .section-title {
            color: var(--text);
        }

        /* Avatar Section */
        .avatar-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(210, 35, 42, 0.3);
        }

        .user-info {
            text-align: center;
        }

        .user-name {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .dark-mode .user-name {
            color: var(--text);
        }

        .user-role {
            color: var(--red);
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .user-email {
            color: var(--gray);
            font-size: 13px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .dark-mode label {
            color: var(--text);
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: var(--surface);
            color: var(--text);
            transition: all 0.3s;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(210, 35, 42, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .input-icon input {
            padding-left: 45px;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--red);
            color: white;
        }

        .btn-primary:hover {
            background: #b81d24;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(210, 35, 42, 0.3);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 30px;
        }

        .stat-card {
            background: var(--surface);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 800;
            color: var(--red);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--gray);
            text-transform: uppercase;
        }

        /* Quick Links */
        .quick-links {
            display: grid;
            gap: 10px;
            margin-top: 30px;
        }

        .quick-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: var(--surface);
            border-radius: 10px;
            text-decoration: none;
            color: var(--text);
            transition: all 0.3s;
        }

        .quick-link:hover {
            background: var(--red);
            color: white;
            transform: translateX(5px);
        }

        .quick-link i {
            font-size: 20px;
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .top-bar .container {
                flex-direction: column;
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
                    <a href="index.php" class="logo-text">HABER|ONVIBES</a>
                    <span class="user-badge">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?>
                    </span>
                </div>
                <div class="top-links">
                    <form method="POST" style="margin: 0;">
                        <button type="submit" name="toggle_theme">
                            <i class="fas fa-<?php echo $dark_mode ? 'sun' : 'moon'; ?>"></i>
                        </button>
                    </form>
                    <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                    <a href="admin/admin.php">
                        <i class="fas fa-crown"></i> Admin Panel
                    </a>
                    <?php endif; ?>
                    <?php if((isset($_SESSION['is_writer']) && $_SESSION['is_writer'] == 1) || 
                             (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1)): ?>
                    <a href="yazar.php">
                        <i class="fas fa-pen"></i> Yazar Paneli
                    </a>
                    <?php endif; ?>
                    <a href="index.php">
                        <i class="fas fa-home"></i> Ana Sayfa
                    </a>
                    <a href="Giris/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Çıkış
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-user-circle"></i> Profilim
            </h1>
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i> Ana Sayfa</a>
                <i class="fas fa-chevron-right"></i>
                <span>Profil</span>
            </div>
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

        <div class="profile-grid">
            <!-- Sol Sidebar - Kullanıcı Bilgileri -->
            <div>
                <div class="profile-card">
                    <div class="avatar-section">
                        <div class="avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                            <div class="user-role">
                                <i class="fas fa-user-tag"></i> <?php echo $user_role; ?>
                            </div>
                            <div class="user-email">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user_data['email'] ?? ''); ?>
                            </div>
                        </div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $news_count; ?></div>
                            <div class="stat-label">Haberler</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo number_format($total_views); ?></div>
                            <div class="stat-label">Görüntülenme</div>
                        </div>
                        <?php if($columns_count > 0): ?>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $columns_count; ?></div>
                            <div class="stat-label">Köşe Yazıları</div>
                        </div>
                        <?php endif; ?>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $comments_count; ?></div>
                            <div class="stat-label">Yorumlar</div>
                        </div>
                    </div>

                    <div class="quick-links">
                        <a href="Veri/haber-ekle.php" class="quick-link">
                            <i class="fas fa-plus-circle"></i>
                            <span>Yeni Haber Ekle</span>
                        </a>
                        <?php if((isset($_SESSION['is_writer']) && $_SESSION['is_writer'] == 1) || 
                                 (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1)): ?>
                        <a href="kose-yazisi-edit.php" class="quick-link">
                            <i class="fas fa-pen-fancy"></i>
                            <span>Yeni Köşe Yazısı</span>
                        </a>
                        <?php endif; ?>
                        <a href="haberlerim.php" class="quick-link">
                            <i class="fas fa-newspaper"></i>
                            <span>Haberlerim</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Sağ - Form Alanları -->
            <div>
                <!-- Profil Bilgileri -->
                <div class="profile-card" style="margin-bottom: 30px;">
                    <h2 class="section-title">
                        <i class="fas fa-user-edit"></i> Profil Bilgileri
                    </h2>
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Kullanıcı Adı</label>
                            <input type="text" value="<?php echo htmlspecialchars($username); ?>" disabled>
                            <small style="color: var(--gray); font-size: 12px;">Kullanıcı adı değiştirilemez</small>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> E-posta</label>
                            <div class="input-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Ad Soyad</label>
                            <div class="input-icon">
                                <i class="fas fa-id-card"></i>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Telefon</label>
                            <div class="input-icon">
                                <i class="fas fa-phone"></i>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-info-circle"></i> Biyografi</label>
                            <textarea name="bio" placeholder="Kendiniz hakkında birkaç kelime..."><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
                        </div>

                        <h3 style="font-size: 16px; font-weight: 700; margin: 25px 0 15px; color: var(--dark);" class="dark-mode-title">
                            <i class="fas fa-share-alt"></i> Sosyal Medya Hesapları
                        </h3>

                        <div class="form-group">
                            <label><i class="fab fa-facebook"></i> Facebook</label>
                            <div class="input-icon">
                                <i class="fab fa-facebook"></i>
                                <input type="text" name="facebook" value="<?php echo htmlspecialchars($user_data['facebook'] ?? ''); ?>" placeholder="facebook.com/kullaniciadi">
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fab fa-twitter"></i> Twitter</label>
                            <div class="input-icon">
                                <i class="fab fa-twitter"></i>
                                <input type="text" name="twitter" value="<?php echo htmlspecialchars($user_data['twitter'] ?? ''); ?>" placeholder="twitter.com/kullaniciadi">
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fab fa-instagram"></i> Instagram</label>
                            <div class="input-icon">
                                <i class="fab fa-instagram"></i>
                                <input type="text" name="instagram" value="<?php echo htmlspecialchars($user_data['instagram'] ?? ''); ?>" placeholder="instagram.com/kullaniciadi">
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fab fa-linkedin"></i> LinkedIn</label>
                            <div class="input-icon">
                                <i class="fab fa-linkedin"></i>
                                <input type="text" name="linkedin" value="<?php echo htmlspecialchars($user_data['linkedin'] ?? ''); ?>" placeholder="linkedin.com/in/kullaniciadi">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Profili Güncelle
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Şifre Değiştir -->
                <div class="profile-card">
                    <h2 class="section-title">
                        <i class="fas fa-key"></i> Şifre Değiştir
                    </h2>
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Mevcut Şifre</label>
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="current_password" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Yeni Şifre</label>
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="new_password" required minlength="6">
                            </div>
                            <small style="color: var(--gray); font-size: 12px;">En az 6 karakter olmalıdır</small>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Yeni Şifre (Tekrar)</label>
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="confirm_password" required minlength="6">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key"></i> Şifreyi Değiştir
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .dark-mode .dark-mode-title {
            color: var(--text) !important;
        }
    </style>
</body>
</html>
