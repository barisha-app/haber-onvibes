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

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

$message = '';
$error = '';

// Tablo kontrolü ve oluşturma
try {
    $check_table = $db->query("SHOW TABLES LIKE 'password_resets'");
    if($check_table->rowCount() == 0) {
        $create_table = "CREATE TABLE password_resets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used TINYINT DEFAULT 0,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at DATETIME,
            approved_at DATETIME DEFAULT NULL,
            INDEX idx_token (token),
            INDEX idx_user_id (user_id)
        )";
        $db->exec($create_table);
    }
} catch(PDOException $e) {
    $error = 'Tablo kontrolü hatası: ' . $e->getMessage();
}

// Onay işlemi
if (isset($_POST['approve_reset'])) {
    $reset_id = (int)$_POST['reset_id'];
    
    try {
        $query = "UPDATE password_resets SET status = 'approved', approved_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        if($stmt->execute([$reset_id])) {
            // Kullanıcı bilgilerini al
            $user_query = "SELECT u.email, u.username, pr.token 
                          FROM password_resets pr 
                          JOIN users u ON pr.user_id = u.id 
                          WHERE pr.id = ?";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->execute([$reset_id]);
            $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            if($user_data) {
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/Giris/sifre-sifirla.php?token=" . $user_data['token'];
                $message = "Şifre sıfırlama onaylandı! Kullanıcıya gönderilebilecek link: <br><strong>" . $reset_link . "</strong>";
            } else {
                $message = 'Şifre sıfırlama onaylandı!';
            }
        }
    } catch(PDOException $e) {
        $error = 'Onay sırasında hata: ' . $e->getMessage();
    }
}

// Reddetme işlemi
if (isset($_POST['reject_reset'])) {
    $reset_id = (int)$_POST['reset_id'];
    
    try {
        $query = "UPDATE password_resets SET status = 'rejected' WHERE id = ?";
        $stmt = $db->prepare($query);
        if($stmt->execute([$reset_id])) {
            $message = 'Şifre sıfırlama talebi reddedildi!';
        }
    } catch(PDOException $e) {
        $error = 'Red sırasında hata: ' . $e->getMessage();
    }
}

// Silme işlemi
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $reset_id = (int)$_GET['id'];
    
    try {
        $query = "DELETE FROM password_resets WHERE id = ?";
        $stmt = $db->prepare($query);
        if($stmt->execute([$reset_id])) {
            $message = 'Kayıt silindi!';
        }
    } catch(PDOException $e) {
        $error = 'Silme sırasında hata: ' . $e->getMessage();
    }
}

// Filtreleme
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

try {
    $query = "SELECT pr.*, u.username, u.email, u.full_name 
              FROM password_resets pr 
              JOIN users u ON pr.user_id = u.id 
              WHERE 1=1";
    
    if ($status_filter != 'all') {
        $query .= " AND pr.status = :status";
    }
    
    $query .= " ORDER BY pr.created_at DESC";
    
    $stmt = $db->prepare($query);
    
    if ($status_filter != 'all') {
        $stmt->bindParam(':status', $status_filter);
    }
    
    $stmt->execute();
    $resets_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Veriler yüklenirken hata: ' . $e->getMessage();
    $resets_list = [];
}

// İstatistikler
try {
    $total_requests = $db->query("SELECT COUNT(*) FROM password_resets")->fetchColumn() ?: 0;
    $pending_count = $db->query("SELECT COUNT(*) FROM password_resets WHERE status = 'pending'")->fetchColumn() ?: 0;
    $approved_count = $db->query("SELECT COUNT(*) FROM password_resets WHERE status = 'approved'")->fetchColumn() ?: 0;
    $rejected_count = $db->query("SELECT COUNT(*) FROM password_resets WHERE status = 'rejected'")->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $total_requests = $pending_count = $approved_count = $rejected_count = 0;
}

function time_elapsed_string($datetime) {
    if(!$datetime) return '-';
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
    <title>Şifre Sıfırlama Kontrol - ONVIBES Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
:root{--red:#d2232a;--dark:#2c3e50;--light:#fff;--border:#e0e0e0;--surface:#f8f9fa;--text:#333;--gray:#666;--green:#0a8c2f;--blue:#3498db;--orange:#e67e22;--purple:#9b59b6}
.dark-mode{--red:#ff6b6b;--dark:#0a0a0a;--light:#1a1a1a;--border:#333;--surface:#1a1a1a;--text:#f0f0f0;--gray:#a0a0a0}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
body{background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);color:var(--text);line-height:1.6;min-height:100vh;background-attachment:fixed}
.dark-mode body{background:linear-gradient(135deg,#0a0a0a 0%,#1a1a1a 100%)}
.container{max-width:1400px;margin:0 auto;padding:0 15px}
.main-menu{background:var(--light);box-shadow:0 4px 20px rgba(0,0,0,.08);position:sticky;top:0;z-index:1000}
.top-bar{background:linear-gradient(135deg,var(--red) 0%,#b81d24 100%);color:#fff;padding:10px 0}
.top-bar .container{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px}
.logo-text{color:#fff;font-size:24px;font-weight:800;text-transform:uppercase}
.admin-badge{background:rgba(255,255,255,.2);padding:6px 15px;border-radius:20px;font-size:13px;font-weight:600}
.top-links{display:flex;gap:10px;align-items:center}
.top-links button,.top-links a{background:rgba(255,255,255,.15);border:none;color:#fff;text-decoration:none;padding:8px 16px;border-radius:25px;font-size:13px;font-weight:600;cursor:pointer;transition:all .3s}
.top-links button:hover,.top-links a:hover{background:rgba(255,255,255,.25);transform:translateY(-2px)}
.main-content{padding:30px 0}
.page-title{font-size:32px;font-weight:800;color:var(--dark);margin-bottom:10px}
.dark-mode .page-title{color:var(--text)}
.breadcrumb{display:flex;gap:10px;align-items:center;color:var(--gray);font-size:14px;margin-bottom:30px}
.breadcrumb a{color:var(--red);text-decoration:none}
.alert{padding:15px 20px;border-radius:12px;margin-bottom:25px;display:flex;align-items:center;gap:12px;font-weight:600}
.alert-success{background:#d4edda;color:#155724}
.alert-error{background:#f8d7da;color:#721c24}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:30px}
.stat-card{background:var(--light);padding:20px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);border-left:4px solid var(--green)}
.dark-mode .stat-card{background:var(--light)}
.stat-number{font-size:32px;font-weight:800;color:var(--green)}
.stat-label{color:var(--gray);font-size:13px;font-weight:600;text-transform:uppercase;margin-top:5px}
.filters{background:var(--light);padding:20px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);margin-bottom:25px;display:flex;gap:15px;flex-wrap:wrap;align-items:center}
.dark-mode .filters{background:var(--light)}
.filter-group{flex:1;min-width:200px}
.filter-group label{display:block;margin-bottom:8px;font-weight:600;font-size:13px;color:var(--dark)}
.dark-mode .filter-group label{color:var(--text)}
.filter-group select{width:100%;padding:10px 15px;border:2px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-size:14px}
.btn{padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.btn-primary{background:var(--red);color:#fff}
.btn-success{background:var(--green);color:#fff}
.btn-danger{background:#e74c3c;color:#fff}
.btn-info{background:var(--blue);color:#fff}
.btn-warning{background:var(--orange);color:#fff}
.btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.15)}
.btn-sm{padding:6px 12px;font-size:12px}
.news-table{background:var(--light);border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);overflow:hidden}
.dark-mode .news-table{background:var(--light)}
table{width:100%;border-collapse:collapse}
thead{background:var(--dark);color:#fff}
th{padding:15px;text-align:left;font-weight:700;font-size:13px;text-transform:uppercase}
td{padding:15px;border-bottom:1px solid var(--border)}
tbody tr:hover{background:var(--surface)}
.status-badge{padding:4px 10px;border-radius:15px;font-size:11px;font-weight:600;display:inline-block}
.status-pending{background:#ffc107;color:#000}
.status-approved{background:#28a745;color:#fff}
.status-rejected{background:#dc3545;color:#fff}
.action-buttons{display:flex;gap:5px;flex-wrap:wrap}
.empty-state{text-align:center;padding:60px 20px;color:var(--gray)}
.empty-state i{font-size:64px;margin-bottom:20px;opacity:.3}
.link-box{background:var(--surface);padding:10px;border-radius:5px;font-family:monospace;font-size:12px;word-break:break-all;margin-top:5px}
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
        <h1 class="page-title"><i class="fas fa-key"></i> Şifre Sıfırlama Kontrol</h1>
        <div class="breadcrumb">
            <a href="admin.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>Şifre Sıfırlama</span>
        </div>

        <?php if($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_requests; ?></div>
                <div class="stat-label">Toplam İstek</div>
            </div>
            <div class="stat-card" style="border-left-color:#ffc107">
                <div class="stat-number" style="color:#ffc107"><?php echo $pending_count; ?></div>
                <div class="stat-label">Bekleyen</div>
            </div>
            <div class="stat-card" style="border-left-color:#28a745">
                <div class="stat-number" style="color:#28a745"><?php echo $approved_count; ?></div>
                <div class="stat-label">Onaylı</div>
            </div>
            <div class="stat-card" style="border-left-color:#dc3545">
                <div class="stat-number" style="color:#dc3545"><?php echo $rejected_count; ?></div>
                <div class="stat-label">Reddedilen</div>
            </div>
        </div>

        <form method="GET" class="filters">
            <div class="filter-group">
                <label><i class="fas fa-filter"></i> Durum</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>Tümü</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Bekleyen</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Onaylı</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Reddedilen</option>
                </select>
            </div>
        </form>

        <div class="news-table">
            <?php if (empty($resets_list)): ?>
                <div class="empty-state">
                    <i class="fas fa-key"></i>
                    <p style="font-size:18px;font-weight:600">Şifre sıfırlama talebi bulunamadı</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı</th>
                            <th>E-posta</th>
                            <th>Talep Tarihi</th>
                            <th>Son Kullanma</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resets_list as $reset): ?>
                        <tr>
                            <td><strong>#<?php echo $reset['id']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($reset['username']); ?></strong>
                                <?php if ($reset['full_name']): ?>
                                <br><small style="color:var(--gray)"><?php echo htmlspecialchars($reset['full_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($reset['email']); ?></td>
                            <td><small><?php echo time_elapsed_string($reset['created_at']); ?></small></td>
                            <td><small><?php echo time_elapsed_string($reset['expires_at']); ?></small></td>
                            <td>
                                <?php if ($reset['status'] == 'pending'): ?>
                                <span class="status-badge status-pending"><i class="fas fa-clock"></i> Bekliyor</span>
                                <?php elseif ($reset['status'] == 'approved'): ?>
                                <span class="status-badge status-approved"><i class="fas fa-check"></i> Onaylı</span>
                                <?php else: ?>
                                <span class="status-badge status-rejected"><i class="fas fa-times"></i> Reddedildi</span>
                                <?php endif; ?>
                                <?php if ($reset['used']): ?>
                                <br><span class="status-badge" style="background:#6c757d;color:#fff;margin-top:5px"><i class="fas fa-check-double"></i> Kullanıldı</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($reset['status'] == 'pending' && !$reset['used']): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="reset_id" value="<?php echo $reset['id']; ?>">
                                        <button type="submit" name="approve_reset" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Onayla
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="reset_id" value="<?php echo $reset['id']; ?>">
                                        <button type="submit" name="reject_reset" class="btn btn-danger btn-sm">
                                            <i class="fas fa-times"></i> Reddet
                                        </button>
                                    </form>
                                    <?php elseif($reset['status'] == 'approved' && !$reset['used']): ?>
                                    <button onclick="copyLink('<?php echo htmlspecialchars($reset['token']); ?>')" class="btn btn-info btn-sm">
                                        <i class="fas fa-copy"></i> Link Kopyala
                                    </button>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?php echo $reset['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Bu kaydı silmek istediğinize emin misiniz?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                                <?php if ($reset['status'] == 'approved' && !$reset['used']): ?>
                                <div class="link-box">
                                    <?php echo "http://" . $_SERVER['HTTP_HOST'] . "/Giris/sifre-sifirla.php?token=" . htmlspecialchars($reset['token']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyLink(token) {
            const link = 'http://<?php echo $_SERVER['HTTP_HOST']; ?>/Giris/sifre-sifirla.php?token=' + token;
            navigator.clipboard.writeText(link).then(function() {
                alert('Link panoya kopyalandı!');
            }, function(err) {
                alert('Kopyalama başarısız: ' + err);
            });
        }
    </script>
</body>
</html>
