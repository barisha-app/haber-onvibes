<?php
// profil-goruntule.php - Kullanıcı Profil Görüntüleme (Herkese Açık)
session_start();

include 'config.php';
$database = new Database();
$db = $database->getConnection();

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF'] . (isset($_GET['username']) ? '?username=' . urlencode($_GET['username']) : ''));
    exit();
}
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

$error = '';
$user_data = null;

// Kullanıcı adı kontrolü
if(isset($_GET['username']) && !empty($_GET['username'])) {
    $profile_username = trim($_GET['username']);
    
    // Kullanıcı bilgilerini çek
    try {
        $query = "SELECT id, username, email, full_name, bio, 
                         role, created_at 
                  FROM users WHERE username = :username";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $profile_username);
        $stmt->execute();
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$user_data) {
            $error = 'Kullanıcı bulunamadı!';
        } else {
            // Kullanıcı rolünü belirle
            $user_role = 'Kullanıcı';
            $role_icon = 'fa-user';
            if($user_data['is_admin'] == 1) {
                $user_role = 'Administrator';
                $role_icon = 'fa-crown';
            } elseif($user_data['is_writer'] == 1) {
                $user_role = 'Yazar';
                $role_icon = 'fa-pen-fancy';
            } elseif($user_data['is_reporter'] == 1) {
                $user_role = 'Muhabir';
                $role_icon = 'fa-microphone';
            }
            
            // İstatistikler
            $profile_user_id = $user_data['id'];
            
            // Toplam haber sayısı
            $news_count_query = "SELECT COUNT(*) FROM news WHERE author_id = :user_id";
            $news_stmt = $db->prepare($news_count_query);
            $news_stmt->bindParam(':user_id', $profile_user_id);
            $news_stmt->execute();
            $news_count = $news_stmt->fetchColumn();
            
            // Toplam görüntülenme
            $views_query = "SELECT COALESCE(SUM(view_count), 0) FROM news WHERE author_id = :user_id";
            $views_stmt = $db->prepare($views_query);
            $views_stmt->bindParam(':user_id', $profile_user_id);
            $views_stmt->execute();
            $total_views = $views_stmt->fetchColumn();
            
            // Köşe yazısı sayısı (eğer yazar ise)
            $columns_count = 0;
            if($user_data['is_writer'] == 1 || $user_data['is_admin'] == 1) {
                $columns_query = "SELECT COUNT(*) FROM columns WHERE author_id = :user_id AND status = 'approved'";
                $columns_stmt = $db->prepare($columns_query);
                $columns_stmt->bindParam(':user_id', $profile_user_id);
                $columns_stmt->execute();
                $columns_count = $columns_stmt->fetchColumn();
            }
            
            // Yorum sayısı
            $comments_query = "SELECT COUNT(*) FROM comments WHERE user_id = :user_id";
            $comments_stmt = $db->prepare($comments_query);
            $comments_stmt->bindParam(':user_id', $profile_user_id);
            $comments_stmt->execute();
            $comments_count = $comments_stmt->fetchColumn();
            
            // Son haberler
            $recent_news_query = "SELECT id, title, created_at, view_count 
                                  FROM news 
                                  WHERE author_id = :user_id AND status = 'approved'
                                  ORDER BY created_at DESC 
                                  LIMIT 5";
            $recent_news_stmt = $db->prepare($recent_news_query);
            $recent_news_stmt->bindParam(':user_id', $profile_user_id);
            $recent_news_stmt->execute();
            $recent_news = $recent_news_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Son köşe yazıları (eğer yazar ise)
            $recent_columns = [];
            if($user_data['is_writer'] == 1 || $user_data['is_admin'] == 1) {
                $recent_columns_query = "SELECT id, title, created_at 
                                         FROM columns 
                                         WHERE author_id = :user_id AND status = 'approved'
                                         ORDER BY created_at DESC 
                                         LIMIT 5";
                $recent_columns_stmt = $db->prepare($recent_columns_query);
                $recent_columns_stmt->bindParam(':user_id', $profile_user_id);
                $recent_columns_stmt->execute();
                $recent_columns = $recent_columns_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
    } catch(PDOException $e) {
        $error = 'Kullanıcı bilgileri alınırken hata oluştu!';
    }
} else {
    $error = 'Geçersiz kullanıcı adı!';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $user_data ? htmlspecialchars($user_data['username']) . ' - Profil' : 'Kullanıcı Profili'; ?> - ONVIBES</title>
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

        .error-message {
            background: var(--light);
            padding: 40px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .error-icon {
            font-size: 64px;
            color: var(--red);
            margin-bottom: 20px;
        }

        /* Profile Header */
        .profile-header {
            background: var(--light);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .dark-mode .profile-header {
            background: var(--light);
        }

        .profile-header-content {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 30px;
            align-items: center;
        }

        .avatar-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            box-shadow: 0 8px 24px rgba(210, 35, 42, 0.3);
        }

        .profile-info h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .dark-mode .profile-info h1 {
            color: var(--text);
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--red);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .profile-meta {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 15px;
        }

        .profile-bio {
            color: var(--text);
            line-height: 1.8;
            margin-top: 20px;
            padding: 20px;
            background: var(--surface);
            border-radius: 12px;
            border-left: 4px solid var(--red);
        }

        /* Social Links */
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-link {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            font-size: 20px;
            transition: all 0.3s;
        }

        .social-link.facebook { background: #1877f2; }
        .social-link.twitter { background: #1da1f2; }
        .social-link.instagram { background: linear-gradient(45deg, #f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%); }
        .social-link.linkedin { background: #0077b5; }

        .social-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--light);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            text-align: center;
        }

        .dark-mode .stat-card {
            background: var(--light);
        }

        .stat-icon {
            font-size: 32px;
            color: var(--red);
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .dark-mode .stat-number {
            color: var(--text);
        }

        .stat-label {
            font-size: 13px;
            color: var(--gray);
            text-transform: uppercase;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }

        .content-card {
            background: var(--light);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .dark-mode .content-card {
            background: var(--light);
        }

        .content-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--red);
        }

        .dark-mode .content-title {
            color: var(--text);
        }

        .content-list {
            list-style: none;
        }

        .content-item {
            padding: 15px;
            background: var(--surface);
            border-radius: 10px;
            margin-bottom: 12px;
            transition: all 0.3s;
        }

        .content-item:hover {
            background: var(--red);
            color: white;
            transform: translateX(5px);
        }

        .content-item a {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .content-item-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .content-item-meta {
            font-size: 12px;
            opacity: 0.8;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .profile-header-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .avatar-large {
                margin: 0 auto;
            }
            
            .social-links {
                justify-content: center;
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
                <a href="index.php" class="logo-text">HABER|ONVIBES</a>
                <div class="top-links">
                    <form method="POST" style="margin: 0;">
                        <button type="submit" name="toggle_theme">
                            <i class="fas fa-<?php echo $dark_mode ? 'sun' : 'moon'; ?>"></i>
                        </button>
                    </form>
                    <?php if(isset($_SESSION['loggedin']) && $_SESSION['loggedin']): ?>
                    <a href="profil.php">
                        <i class="fas fa-user"></i> Profilim
                    </a>
                    <?php else: ?>
                    <a href="Giris/login.php">
                        <i class="fas fa-sign-in-alt"></i> Giriş Yap
                    </a>
                    <?php endif; ?>
                    <a href="index.php">
                        <i class="fas fa-home"></i> Ana Sayfa
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content container">
        <?php if($error || !$user_data): ?>
            <div class="error-message">
                <div class="error-icon">
                    <i class="fas fa-user-slash"></i>
                </div>
                <h2 style="color: var(--dark); margin-bottom: 15px;"><?php echo $error ?: 'Kullanıcı bulunamadı'; ?></h2>
                <p style="color: var(--gray); margin-bottom: 20px;">Aradığınız kullanıcı profili bulunamadı veya erişiminiz yok.</p>
                <a href="index.php" style="display: inline-block; background: var(--red); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-home"></i> Ana Sayfaya Dön
                </a>
            </div>
        <?php else: ?>
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-header-content">
                    <div class="avatar-large">
                        <i class="fas <?php echo $role_icon; ?>"></i>
                    </div>
                    <div class="profile-info">
                        <h1><?php echo htmlspecialchars($user_data['username']); ?></h1>
                        <div class="role-badge">
                            <i class="fas <?php echo $role_icon; ?>"></i>
                            <?php echo $user_role; ?>
                        </div>
                        <div class="profile-meta">
                            <?php if($user_data['full_name']): ?>
                                <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($user_data['full_name']); ?> &nbsp;|&nbsp; 
                            <?php endif; ?>
                            <i class="fas fa-calendar-alt"></i> Üye: <?php echo date('d.m.Y', strtotime($user_data['created_at'])); ?>
                        </div>
                        
                        <?php if($user_data['bio']): ?>
                        <div class="profile-bio">
                            <i class="fas fa-quote-left" style="color: var(--red); margin-right: 10px;"></i>
                            <?php echo nl2br(htmlspecialchars($user_data['bio'])); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($user_data['facebook'] || $user_data['twitter'] || $user_data['instagram'] || $user_data['linkedin']): ?>
                        <div class="social-links">
                            <?php if($user_data['facebook']): ?>
                            <a href="<?php echo htmlspecialchars($user_data['facebook']); ?>" target="_blank" class="social-link facebook" title="Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <?php endif; ?>
                            <?php if($user_data['twitter']): ?>
                            <a href="<?php echo htmlspecialchars($user_data['twitter']); ?>" target="_blank" class="social-link twitter" title="Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <?php endif; ?>
                            <?php if($user_data['instagram']): ?>
                            <a href="<?php echo htmlspecialchars($user_data['instagram']); ?>" target="_blank" class="social-link instagram" title="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <?php endif; ?>
                            <?php if($user_data['linkedin']): ?>
                            <a href="<?php echo htmlspecialchars($user_data['linkedin']); ?>" target="_blank" class="social-link linkedin" title="LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div class="stat-number"><?php echo $news_count; ?></div>
                    <div class="stat-label">Haber</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_views); ?></div>
                    <div class="stat-label">Görüntülenme</div>
                </div>
                <?php if($columns_count > 0): ?>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-pen-fancy"></i>
                    </div>
                    <div class="stat-number"><?php echo $columns_count; ?></div>
                    <div class="stat-label">Köşe Yazısı</div>
                </div>
                <?php endif; ?>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-number"><?php echo $comments_count; ?></div>
                    <div class="stat-label">Yorum</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Son Haberler -->
                <div class="content-card">
                    <h2 class="content-title">
                        <i class="fas fa-newspaper"></i> Son Haberler
                    </h2>
                    <?php if(count($recent_news) > 0): ?>
                    <ul class="content-list">
                        <?php foreach($recent_news as $news): ?>
                        <li class="content-item">
                            <a href="haber-detay.php?id=<?php echo $news['id']; ?>">
                                <div class="content-item-title"><?php echo htmlspecialchars($news['title']); ?></div>
                                <div class="content-item-meta">
                                    <i class="fas fa-calendar"></i> <?php echo date('d.m.Y', strtotime($news['created_at'])); ?>
                                    &nbsp;|&nbsp;
                                    <i class="fas fa-eye"></i> <?php echo number_format($news['view_count']); ?>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Henüz haber yayınlanmamış</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Son Köşe Yazıları -->
                <?php if(count($recent_columns) > 0): ?>
                <div class="content-card">
                    <h2 class="content-title">
                        <i class="fas fa-pen-fancy"></i> Son Köşe Yazıları
                    </h2>
                    <ul class="content-list">
                        <?php foreach($recent_columns as $column): ?>
                        <li class="content-item">
                            <a href="kose-yazisi-detay.php?id=<?php echo $column['id']; ?>">
                                <div class="content-item-title"><?php echo htmlspecialchars($column['title']); ?></div>
                                <div class="content-item-meta">
                                    <i class="fas fa-calendar"></i> <?php echo date('d.m.Y', strtotime($column['created_at'])); ?>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
