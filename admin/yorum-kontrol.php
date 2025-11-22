<?php
session_start();
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../Giris/login.php');
    exit();
}
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../index.php');
    exit();
}

include '../config.php';
$database = new Database();
$db = $database->getConnection();

if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

$message = '';

// Yorum silme
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    try {
        $query = "DELETE FROM comments WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_GET['id']);
        if ($stmt->execute()) {
            $message = 'Yorum silindi!';
        }
    } catch (PDOException $e) {
        $message = 'Silme hatası!';
    }
}

// Yorumlar listesi
try {
    $query = "SELECT c.*, u.username, n.title as news_title 
              FROM comments c 
              LEFT JOIN users u ON c.user_id = u.id 
              LEFT JOIN news n ON c.news_id = n.id 
              ORDER BY c.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_comments = count($comments);
} catch (PDOException $e) {
    $comments = [];
    $total_comments = 0;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yorum Kontrol - ONVIBES Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--red:#d2232a;--dark:#2c3e50;--light:#fff;--border:#e0e0e0;--surface:#f8f9fa;--text:#333;--gray:#666;--green:#0a8c2f}
        .dark-mode{--red:#ff6b6b;--dark:#0a0a0a;--light:#1a1a1a;--border:#333;--surface:#1a1a1a;--text:#f0f0f0;--gray:#a0a0a0}
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
        body{background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);color:var(--text);min-height:100vh;background-attachment:fixed}
        .dark-mode body{background:linear-gradient(135deg,#0a0a0a 0%,#1a1a1a 100%)}
        .container{max-width:1400px;margin:0 auto;padding:0 15px}
        .main-menu{background:var(--light);box-shadow:0 4px 20px rgba(0,0,0,.08);position:sticky;top:0;z-index:1000}
        .top-bar{background:linear-gradient(135deg,var(--red) 0%,#b81d24 100%);color:#fff;padding:10px 0}
        .top-bar .container{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px}
        .logo-text{color:#fff;font-size:24px;font-weight:800;text-transform:uppercase}
        .admin-badge{background:rgba(255,255,255,.2);padding:6px 15px;border-radius:20px;font-size:13px;font-weight:600}
        .top-links{display:flex;gap:10px;align-items:center}
        .top-links button,.top-links a{background:rgba(255,255,255,.15);border:none;color:#fff;text-decoration:none;padding:8px 16px;border-radius:25px;font-size:13px;font-weight:600;cursor:pointer;transition:all .3s}
        .main-content{padding:30px 0}
        .page-title{font-size:32px;font-weight:800;color:var(--dark);margin-bottom:10px}
        .dark-mode .page-title{color:var(--text)}
        .breadcrumb{display:flex;gap:10px;align-items:center;color:var(--gray);font-size:14px;margin-bottom:30px}
        .breadcrumb a{color:var(--red);text-decoration:none}
        .alert{padding:15px 20px;border-radius:12px;margin-bottom:25px;background:#d4edda;color:#155724;font-weight:600}
        .stat-card{background:var(--light);padding:20px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);margin-bottom:30px;text-align:center}
        .stat-number{font-size:48px;font-weight:800;color:var(--red)}
        .stat-label{color:var(--gray);font-size:14px;font-weight:600;text-transform:uppercase;margin-top:10px}
        .comments-list{background:var(--light);border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);padding:20px}
        .comment-item{padding:20px;border-bottom:1px solid var(--border);transition:all .3s}
        .comment-item:hover{background:var(--surface)}
        .comment-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
        .comment-user{font-weight:700;color:var(--dark)}
        .dark-mode .comment-user{color:var(--text)}
        .comment-date{color:var(--gray);font-size:13px}
        .comment-news{color:var(--red);font-size:13px;margin-bottom:10px;font-weight:600}
        .comment-text{color:var(--text);line-height:1.6;margin-bottom:10px}
        .btn-danger{background:#e74c3c;color:#fff;padding:8px 16px;border:none;border-radius:8px;cursor:pointer;font-weight:600;transition:all .3s}
        .btn-danger:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.15)}
        .empty-state{text-align:center;padding:60px 20px;color:var(--gray)}
        .empty-state i{font-size:64px;margin-bottom:20px;opacity:.3}
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <header class="main-menu">
        <div class="top-bar">
            <div class="container">
                <div style="display:flex;align-items:center;gap:15px">
                    <span class="logo-text">ONVIBES</span>
                    <span class="admin-badge"><i class="fas fa-crown"></i> Admin Panel</span>
                </div>
                <div class="top-links">
                    <form method="POST" style="margin:0">
                        <button type="submit" name="toggle_theme">
                            <i class="fas fa-<?php echo $dark_mode ? 'sun' : 'moon'; ?>"></i>
                        </button>
                    </form>
                    <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="../index.php"><i class="fas fa-home"></i> Ana Sayfa</a>
                    <a href="../Giris/logout.php"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-content container">
        <h1 class="page-title"><i class="fas fa-comments"></i> Yorum Yönetimi</h1>
        <div class="breadcrumb">
            <a href="admin.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>Yorum Kontrol</span>
        </div>

        <?php if($message): ?>
        <div class="alert"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>

        <div class="stat-card">
            <div class="stat-number"><?php echo $total_comments; ?></div>
            <div class="stat-label"><i class="fas fa-comments"></i> Toplam Yorum</div>
        </div>

        <div class="comments-list">
            <?php if (empty($comments)): ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <p style="font-size:18px;font-weight:600">Henüz yorum yok</p>
                </div>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                <div class="comment-item">
                    <div class="comment-header">
                        <div>
                            <span class="comment-user">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($comment['username'] ?? 'Anonim'); ?>
                            </span>
                        </div>
                        <span class="comment-date">
                            <i class="far fa-clock"></i> <?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?>
                        </span>
                    </div>
                    <div class="comment-news">
                        <i class="fas fa-newspaper"></i> Haber: <?php echo htmlspecialchars($comment['news_title'] ?? 'Bilinmiyor'); ?>
                    </div>
                    <div class="comment-text">
                        <?php echo htmlspecialchars($comment['comment']); ?>
                    </div>
                    <a href="?action=delete&id=<?php echo $comment['id']; ?>" 
                       class="btn-danger"
                       onclick="return confirm('Bu yorumu silmek istediğinize emin misiniz?')">
                        <i class="fas fa-trash"></i> Sil
                    </a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
