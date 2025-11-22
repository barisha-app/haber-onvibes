<?php
// admin-profil.php - Admin Profil Yönetimi
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

// Profil güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $bio = trim($_POST['bio']);
    
    // Sosyal medya alanları kaldırıldı
    
    try {
        $query = "UPDATE users SET 
                  email = :email,
                  full_name = :full_name,
                  bio = :bio
                  WHERE id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':bio', $bio);
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
} catch(PDOException $e) {
    $error = 'Kullanıcı bilgileri alınamadı!';
    $user_data = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profil - ONVIBES</title>
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
            background: var(--red);
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

        .btn-secondary {
            background: var(--gray);
            color: white;
        }

        .btn-secondary:hover {
            background: #555;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        /* Stats */
        .stats-mini {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 30px;
        }

        .stat-mini {
            background: var(--surface);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-mini-number {
            font-size: 28px;
            font-weight: 800;
            color: var(--red);
            margin-bottom: 5px;
        }

        .stat-mini-label {
            font-size: 12px;
            color: var(--gray);
            text-transform: uppercase;
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }

            .stats-mini {
                grid-template-columns: 1fr;
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
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-user-shield"></i> Admin Profili
            </h1>
            <div class="breadcrumb">
                <a href="admin.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Admin Profil</span>
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
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                            <div class="user-role">
                                <i class="fas fa-crown"></i> Administrator
                            </div>
                        </div>
                    </div>

                    <div class="stats-mini">
                        <div class="stat-mini">
                            <div class="stat-mini-number">
                                <?php 
                                try {
                                    $news_count = $db->query("SELECT COUNT(*) FROM news WHERE author_id = $user_id")->fetchColumn();
                                    echo $news_count;
                                } catch(PDOException $e) {
                                    echo '0';
                                }
                                ?>
                            </div>
                            <div class="stat-mini-label">Haberler</div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-number">
                                <?php 
                                try {
                                    $total_views = $db->query("SELECT COALESCE(SUM(view_count), 0) FROM news WHERE author_id = $user_id")->fetchColumn();
                                    echo number_format($total_views);
                                } catch(PDOException $e) {
                                    echo '0';
                                }
                                ?>
                            </div>
                            <div class="stat-mini-label">Görüntülenme</div>
                        </div>
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

                        <h3 style="font-size: 16px; font-weight: 700; margin: 25px 0 15px; color: var(--dark);">
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
</body>
</html>
