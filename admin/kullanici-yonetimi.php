<?php
// kullanici-yonetimi.php - Kullanıcı Yönetimi ve Görev Atama
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

// Kullanıcı güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $update_user_id = (int)$_POST['user_id'];
    $role = $_POST['role'];
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $is_writer = isset($_POST['is_writer']) ? 1 : 0;
    $is_reporter = isset($_POST['is_reporter']) ? 1 : 0;
    
    try {
        // Önce hangi kolonların mevcut olduğunu kontrol et
        $columns_query = "SHOW COLUMNS FROM users";
        $columns_stmt = $db->query($columns_query);
        $existing_columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Dinamik olarak UPDATE query'si oluştur
        $update_fields = [];
        $params = [':user_id' => $update_user_id];
        
        if (in_array('role', $existing_columns)) {
            $update_fields[] = "role = :role";
            $params[':role'] = $role;
        }
        if (in_array('is_admin', $existing_columns)) {
            $update_fields[] = "is_admin = :is_admin";
            $params[':is_admin'] = $is_admin;
        }
        if (in_array('is_writer', $existing_columns)) {
            $update_fields[] = "is_writer = :is_writer";
            $params[':is_writer'] = $is_writer;
        }
        if (in_array('is_reporter', $existing_columns)) {
            $update_fields[] = "is_reporter = :is_reporter";
            $params[':is_reporter'] = $is_reporter;
        }
        
        if (empty($update_fields)) {
            $error = 'Güncellenecek kolon bulunamadı! Veritabanı şemasını kontrol edin.';
        } else {
            $query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = :user_id";
            
            $stmt = $db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            if($stmt->execute()) {
                $message = 'Kullanıcı başarıyla güncellendi!';
            } else {
                $error = 'Güncelleme başarısız oldu!';
            }
        }
    } catch(PDOException $e) {
        $error = 'Güncelleme sırasında hata oluştu: ' . $e->getMessage();
    }
}

// Kullanıcı silme
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_user_id = (int)$_GET['id'];
    
    if ($delete_user_id != $user_id) {
        try {
            $query = "DELETE FROM users WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $delete_user_id);
            if ($stmt->execute()) {
                $message = 'Kullanıcı silindi!';
            }
        } catch(PDOException $e) {
            $error = 'Silme sırasında hata oluştu!';
        }
    } else {
        $error = 'Kendi hesabınızı silemezsiniz!';
    }
}

// Kullanıcılar listesi
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';

try {
    $query = "SELECT * FROM users WHERE 1=1";
    
    if (!empty($search)) {
        $query .= " AND (username LIKE :search OR email LIKE :search OR full_name LIKE :search)";
    }
    
    if ($role_filter != 'all') {
        $query .= " AND role = :role";
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $db->prepare($query);
    
    if (!empty($search)) {
        $search_param = "%{$search}%";
        $stmt->bindParam(':search', $search_param);
    }
    
    if ($role_filter != 'all') {
        $stmt->bindParam(':role', $role_filter);
    }
    
    $stmt->execute();
    $users_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Kullanıcılar yüklenirken hata oluştu!';
    $users_list = [];
}

// İstatistikler
try {
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // Kolon varlığını kontrol et
    $columns_query = "SHOW COLUMNS FROM users";
    $columns_stmt = $db->query($columns_query);
    $existing_columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('is_admin', $existing_columns)) {
        $admin_count = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn() ?: 0;
    } else {
        $admin_count = 0;
    }
    
    if (in_array('is_writer', $existing_columns)) {
        $writer_count = $db->query("SELECT COUNT(*) FROM users WHERE is_writer = 1")->fetchColumn() ?: 0;
    } else {
        $writer_count = 0;
    }
    
    if (in_array('is_reporter', $existing_columns)) {
        $reporter_count = $db->query("SELECT COUNT(*) FROM users WHERE is_reporter = 1")->fetchColumn() ?: 0;
    } else {
        $reporter_count = 0;
    }
} catch (PDOException $e) {
    $total_users = $admin_count = $writer_count = $reporter_count = 0;
    $error = 'İstatistikler yüklenirken hata: ' . $e->getMessage();
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
    <title>Kullanıcı Yönetimi - ONVIBES Admin</title>
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
        .filter-group input,.filter-group select{width:100%;padding:10px 15px;border:2px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-size:14px}
        .btn{padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
        .btn-primary{background:var(--red);color:#fff}
        .btn-success{background:var(--green);color:#fff}
        .btn-danger{background:#e74c3c;color:#fff}
        .btn-info{background:var(--blue);color:#fff}
        .btn-warning{background:var(--orange);color:#fff}
        .btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.15)}
        .btn-sm{padding:5px 10px;font-size:12px}
        .news-table{background:var(--light);border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);overflow:hidden}
        .dark-mode .news-table{background:var(--light)}
        table{width:100%;border-collapse:collapse}
        thead{background:var(--dark);color:#fff}
        th{padding:15px;text-align:left;font-weight:700;font-size:13px;text-transform:uppercase}
        td{padding:15px;border-bottom:1px solid var(--border)}
        tbody tr:hover{background:var(--surface)}
        .role-badge{padding:4px 10px;border-radius:15px;font-size:11px;font-weight:600;display:inline-block;margin-right:5px}
        .role-admin{background:#e74c3c;color:#fff}
        .role-writer{background:var(--purple);color:#fff}
        .role-reporter{background:var(--blue);color:#fff}
        .role-user{background:var(--gray);color:#fff}
        .action-buttons{display:flex;gap:5px}
        .empty-state{text-align:center;padding:60px 20px;color:var(--gray)}
        .empty-state i{font-size:64px;margin-bottom:20px;opacity:.3}
        .modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);z-index:2000;align-items:center;justify-content:center}
        .modal.active{display:flex}
        .modal-content{background:var(--light);padding:30px;border-radius:16px;max-width:600px;width:90%;max-height:80vh;overflow-y:auto}
        .modal-header{font-size:24px;font-weight:700;margin-bottom:20px;color:var(--dark)}
        .dark-mode .modal-header{color:var(--text)}
        .form-group{margin-bottom:20px}
        .form-group label{display:block;margin-bottom:8px;font-weight:600;font-size:14px}
        .form-group input,.form-group select{width:100%;padding:12px 15px;border:2px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-size:14px}
        .checkbox-group{display:flex;gap:20px;flex-wrap:wrap}
        .checkbox-group label{display:flex;align-items:center;gap:8px;cursor:pointer}
        .checkbox-group input{width:auto}
        .modal-footer{display:flex;gap:10px;margin-top:30px}
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
        <h1 class="page-title"><i class="fas fa-users-cog"></i> Kullanıcı Yönetimi</h1>
        <div class="breadcrumb">
            <a href="admin.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>Kullanıcı Yönetimi</span>
        </div>

        <?php if($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-label">Toplam Kullanıcı</div>
            </div>
            <div class="stat-card" style="border-left-color:#e74c3c">
                <div class="stat-number" style="color:#e74c3c"><?php echo $admin_count; ?></div>
                <div class="stat-label">Admin</div>
            </div>
            <div class="stat-card" style="border-left-color:var(--purple)">
                <div class="stat-number" style="color:var(--purple)"><?php echo $writer_count; ?></div>
                <div class="stat-label">Yazar</div>
            </div>
            <div class="stat-card" style="border-left-color:var(--blue)">
                <div class="stat-number" style="color:var(--blue)"><?php echo $reporter_count; ?></div>
                <div class="stat-label">Muhabir</div>
            </div>
        </div>

        <form method="GET" class="filters">
            <div class="filter-group">
                <label><i class="fas fa-filter"></i> Rol</label>
                <select name="role" onchange="this.form.submit()">
                    <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>Tümü</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Adminler</option>
                    <option value="writer" <?php echo $role_filter == 'writer' ? 'selected' : ''; ?>>Yazarlar</option>
                    <option value="reporter" <?php echo $role_filter == 'reporter' ? 'selected' : ''; ?>>Muhabirler</option>
                    <option value="user" <?php echo $role_filter == 'user' ? 'selected' : ''; ?>>Kullanıcılar</option>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-search"></i> Arama</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Kullanıcı adı, e-posta...">
            </div>
            <div class="filter-group" style="flex:0">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Ara</button>
            </div>
        </form>

        <div class="news-table">
            <?php if (empty($users_list)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p style="font-size:18px;font-weight:600">Kullanıcı bulunamadı</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı</th>
                            <th>E-posta</th>
                            <th>Roller</th>
                            <th>Kayıt Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users_list as $user): ?>
                        <tr>
                            <td><strong>#<?php echo $user['id']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                <?php if ($user['full_name']): ?>
                                <br><small style="color:var(--gray)"><?php echo htmlspecialchars($user['full_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php if ($user['is_admin']): ?>
                                <span class="role-badge role-admin"><i class="fas fa-crown"></i> Admin</span>
                                <?php endif; ?>
                                <?php if ($user['role'] == 'writer'): ?>
                                <span class="role-badge role-writer"><i class="fas fa-pen-fancy"></i> Yazar</span>
                                <?php endif; ?>
                                <?php if ($user['role'] == 'reporter'): ?>
                                <span class="role-badge role-reporter"><i class="fas fa-microphone"></i> Muhabir</span>
                                <?php endif; ?>
                                <?php if ($user['role'] == 'user'): ?>
                                <span class="role-badge role-user"><i class="fas fa-user"></i> Kullanıcı</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo time_elapsed_string($user['created_at']); ?></small></td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                            class="btn btn-info btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != $user_id): ?>
                                    <a href="?action=delete&id=<?php echo $user['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header"><i class="fas fa-user-edit"></i> Kullanıcı Düzenle</h2>
            <form method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label>Kullanıcı Adı</label>
                    <input type="text" id="edit_username" disabled>
                </div>

                <div class="form-group">
                    <label>Rol</label>
                    <select name="role" id="edit_role">
                        <option value="user">Kullanıcı</option>
                        <option value="writer">Yazar</option>
                        <option value="reporter">Muhabir</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Yetkiler</label>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="is_admin" id="edit_is_admin">
                            <i class="fas fa-crown"></i> Admin
                        </label>
                        <label>
                            <input type="checkbox" name="is_writer" id="edit_is_writer">
                            <i class="fas fa-pen-fancy"></i> Yazar
                        </label>
                        <label>
                            <input type="checkbox" name="is_reporter" id="edit_is_reporter">
                            <i class="fas fa-microphone"></i> Muhabir
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="update_user" class="btn btn-success">
                        <i class="fas fa-save"></i> Kaydet
                    </button>
                    <button type="button" onclick="closeModal()" class="btn btn-danger">
                        <i class="fas fa-times"></i> İptal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_role').value = user.role || 'user';
            document.getElementById('edit_is_admin').checked = user.role == 'admin';
            document.getElementById('edit_is_writer').checked = user.role == 'writer';
            document.getElementById('edit_is_reporter').checked = user.role == 'reporter';
            document.getElementById('editModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
