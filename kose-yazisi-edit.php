<?php
session_start();

// Giriş kontrolü
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: Giris/login.php');
    exit();
}

// Köşe yazısı tablosu yok - geçici olarak devre dışı
die('
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Köşe Yazısı Düzenle - ONVIBES</title>
</head>
<body style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
    <h2>Köşe Yazısı Düzenle Modülü</h2>
    <p style="color: #666;">Bu modül şu anda aktif değil.</p>
    <p>Veritabanında "columns" tablosu bulunamadı.</p>
    <a href="index.php" style="color: #e50914; text-decoration: none;">← Ana Sayfaya Dön</a>
</body>
</html>
');

// Yazar veya Admin kontrolü - SADECE YAZARLAR VE ADMİNLER KÖŞE YAZISI YAZABİLİR
if(!isset($_SESSION['is_writer']) && !isset($_SESSION['is_admin'])) {
    header('Location: index.php');
    exit();
}

if((!isset($_SESSION['is_writer']) || $_SESSION['is_writer'] != 1) && (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1)) {
    header('Location: index.php');
    exit();
}

include 'config.php';
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
    $check_table = $db->query("SHOW TABLES LIKE 'columns'");
    if($check_table->rowCount() == 0) {
        $create_table = "CREATE TABLE columns (
            id INT PRIMARY KEY AUTO_INCREMENT,
            author_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            image VARCHAR(500),
            category_id INT,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            view_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_author (author_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        )";
        $db->exec($create_table);
    }
} catch(PDOException $e) {
    $error = 'Tablo kontrolü hatası: ' . $e->getMessage();
}

// Kategorileri çek
try {
    $categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Köşe yazısı ekleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_column'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $category_id = (int)$_POST['category_id'];
    $image = trim($_POST['image']);
    
    if(empty($title) || empty($content)) {
        $error = 'Başlık ve içerik zorunludur!';
    } else {
        try {
            $query = "INSERT INTO columns (author_id, title, content, image, category_id, status) 
                      VALUES (:author_id, :title, :content, :image, :category_id, 'pending')";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':author_id', $user_id);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':image', $image);
            $stmt->bindParam(':category_id', $category_id);
            
            if($stmt->execute()) {
                $message = 'Köşe yazınız başarıyla gönderildi! Admin onayından sonra yayınlanacaktır.';
            }
        } catch(PDOException $e) {
            $error = 'Ekleme sırasında hata: ' . $e->getMessage();
        }
    }
}

// Köşe yazısı güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_column'])) {
    $column_id = (int)$_POST['column_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $category_id = (int)$_POST['category_id'];
    $image = trim($_POST['image']);
    
    if(empty($title) || empty($content)) {
        $error = 'Başlık ve içerik zorunludur!';
    } else {
        try {
            $query = "UPDATE columns SET 
                      title = :title,
                      content = :content,
                      image = :image,
                      category_id = :category_id,
                      status = 'pending'
                      WHERE id = :column_id AND author_id = :author_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':image', $image);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':column_id', $column_id);
            $stmt->bindParam(':author_id', $user_id);
            
            if($stmt->execute()) {
                $message = 'Köşe yazınız güncellendi! Admin onayı bekleniyor.';
            }
        } catch(PDOException $e) {
            $error = 'Güncelleme sırasında hata: ' . $e->getMessage();
        }
    }
}

// Köşe yazısı silme
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $column_id = (int)$_GET['id'];
    
    try {
        $query = "DELETE FROM columns WHERE id = :column_id AND author_id = :author_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':column_id', $column_id);
        $stmt->bindParam(':author_id', $user_id);
        
        if ($stmt->execute()) {
            $message = 'Köşe yazısı silindi!';
        }
    } catch(PDOException $e) {
        $error = 'Silme sırasında hata: ' . $e->getMessage();
    }
}

// Kullanıcının köşe yazılarını çek
try {
    $query = "SELECT c.*, cat.name as category_name 
              FROM columns c 
              LEFT JOIN categories cat ON c.category_id = cat.id 
              WHERE c.author_id = :author_id 
              ORDER BY c.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':author_id', $user_id);
    $stmt->execute();
    $my_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $my_columns = [];
    $error = 'Veriler yüklenirken hata: ' . $e->getMessage();
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
    <title>Köşe Yazısı Düzenle - ONVIBES</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
:root{--red:#d2232a;--dark:#2c3e50;--light:#fff;--border:#e0e0e0;--surface:#f8f9fa;--text:#333;--gray:#666;--green:#0a8c2f;--blue:#3498db;--orange:#e67e22;--purple:#9b59b6}
.dark-mode{--red:#ff6b6b;--dark:#0a0a0a;--light:#1a1a1a;--border:#333;--surface:#1a1a1a;--text:#f0f0f0;--gray:#a0a0a0}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
body{background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);color:var(--text);line-height:1.6;min-height:100vh}
.dark-mode body{background:linear-gradient(135deg,#0a0a0a 0%,#1a1a1a 100%)}
.container{max-width:1200px;margin:0 auto;padding:0 15px}
.top-bar{background:linear-gradient(135deg,var(--red) 0%,#b81d24 100%);color:#fff;padding:10px 0}
.top-bar .container{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px}
.logo-text{color:#fff;font-size:24px;font-weight:800;text-transform:uppercase;text-decoration:none}
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
.form-card{background:var(--light);padding:30px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);margin-bottom:30px}
.dark-mode .form-card{background:var(--light)}
.form-group{margin-bottom:20px}
.form-group label{display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:var(--dark)}
.dark-mode .form-group label{color:var(--text)}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:12px 15px;border:2px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-size:14px}
.form-group textarea{min-height:200px;resize:vertical;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
.btn{padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.btn-primary{background:var(--red);color:#fff}
.btn-success{background:var(--green);color:#fff}
.btn-danger{background:#e74c3c;color:#fff}
.btn-info{background:var(--blue);color:#fff}
.btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.15)}
.btn-sm{padding:6px 12px;font-size:12px}
.columns-table{background:var(--light);border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);overflow:hidden;margin-bottom:30px}
.dark-mode .columns-table{background:var(--light)}
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
.modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);z-index:2000;align-items:center;justify-content:center;overflow-y:auto;padding:20px}
.modal.active{display:flex}
.modal-content{background:var(--light);padding:30px;border-radius:16px;max-width:800px;width:100%;max-height:90vh;overflow-y:auto;margin:auto}
.modal-header{font-size:24px;font-weight:700;margin-bottom:20px;color:var(--dark)}
.dark-mode .modal-header{color:var(--text)}
.modal-footer{display:flex;gap:10px;margin-top:30px}
footer{background:var(--dark);color:#fff;padding:30px 0;text-align:center;margin-top:50px}
@media (max-width:768px){.page-title{font-size:24px}table{font-size:12px}th,td{padding:10px}}
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <header>
        <div class="top-bar">
            <div class="container">
                <a href="index.php" class="logo-text">HABER|ONVIBES</a>
                <div class="top-links">
                    <form method="POST" style="margin:0">
                        <button type="submit" name="toggle_theme">
                            <i class="fas fa-<?php echo $dark_mode ? 'sun' : 'moon'; ?>"></i>
                        </button>
                    </form>
                    <a href="kose-yazilari.php"><i class="fas fa-pen-fancy"></i> Köşe Yazıları</a>
                    <a href="index.php"><i class="fas fa-home"></i> Ana Sayfa</a>
                    <a href="Profil/profil.php"><i class="fas fa-user"></i> Profil</a>
                    <a href="Giris/logout.php"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-content container">
        <h1 class="page-title"><i class="fas fa-pen-fancy"></i> Köşe Yazılarım</h1>
        <div class="breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> Ana Sayfa</a>
            <i class="fas fa-chevron-right"></i>
            <a href="kose-yazilari.php">Köşe Yazıları</a>
            <i class="fas fa-chevron-right"></i>
            <span>Yazılarım</span>
        </div>

        <?php if($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Yeni Köşe Yazısı Formu -->
        <div class="form-card">
            <h2 style="font-size:20px;font-weight:700;margin-bottom:20px;color:var(--dark)">
                <i class="fas fa-plus-circle"></i> Yeni Köşe Yazısı
            </h2>
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Başlık</label>
                    <input type="text" name="title" placeholder="Köşe yazınızın başlığı" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-folder"></i> Kategori</label>
                    <select name="category_id">
                        <option value="">Kategori Seçin</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-image"></i> Görsel URL (Opsiyonel)</label>
                    <input type="text" name="image" placeholder="https://example.com/image.jpg">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> İçerik</label>
                    <textarea name="content" placeholder="Köşe yazınızın içeriğini buraya yazın..." required></textarea>
                </div>

                <button type="submit" name="add_column" class="btn btn-success">
                    <i class="fas fa-paper-plane"></i> Yayınla (Admin Onayı Bekleyecek)
                </button>
            </form>
        </div>

        <!-- Mevcut Köşe Yazıları -->
        <h2 style="font-size:24px;font-weight:700;margin-bottom:20px;color:var(--dark)">
            <i class="fas fa-list"></i> Köşe Yazılarım
        </h2>

        <div class="columns-table">
            <?php if (empty($my_columns)): ?>
                <div class="empty-state">
                    <i class="fas fa-pen-fancy"></i>
                    <p style="font-size:18px;font-weight:600">Henüz köşe yazınız yok</p>
                    <p>Yukarıdaki formu kullanarak ilk köşe yazınızı oluşturun</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Başlık</th>
                            <th>Kategori</th>
                            <th>Tarih</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_columns as $column): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($column['title']); ?></strong>
                                <br><small style="color:var(--gray)"><?php echo htmlspecialchars(mb_substr(strip_tags($column['content']), 0, 80)); ?>...</small>
                            </td>
                            <td><?php echo htmlspecialchars($column['category_name'] ?: '-'); ?></td>
                            <td><small><?php echo time_elapsed_string($column['created_at']); ?></small></td>
                            <td>
                                <?php if ($column['status'] == 'pending'): ?>
                                <span class="status-badge status-pending"><i class="fas fa-clock"></i> Bekliyor</span>
                                <?php elseif ($column['status'] == 'approved'): ?>
                                <span class="status-badge status-approved"><i class="fas fa-check"></i> Yayında</span>
                                <?php else: ?>
                                <span class="status-badge status-rejected"><i class="fas fa-times"></i> Reddedildi</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($column)); ?>)" class="btn btn-info btn-sm">
                                        <i class="fas fa-edit"></i> Düzenle
                                    </button>
                                    <a href="?action=delete&id=<?php echo $column['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Bu köşe yazısını silmek istediğinize emin misiniz?')">
                                        <i class="fas fa-trash"></i> Sil
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

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header"><i class="fas fa-edit"></i> Köşe Yazısını Düzenle</h2>
            <form method="POST">
                <input type="hidden" name="column_id" id="edit_column_id">
                
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Başlık</label>
                    <input type="text" name="title" id="edit_title" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-folder"></i> Kategori</label>
                    <select name="category_id" id="edit_category_id">
                        <option value="">Kategori Seçin</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-image"></i> Görsel URL</label>
                    <input type="text" name="image" id="edit_image">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> İçerik</label>
                    <textarea name="content" id="edit_content" required></textarea>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="update_column" class="btn btn-success">
                        <i class="fas fa-save"></i> Güncelle
                    </button>
                    <button type="button" onclick="closeModal()" class="btn btn-danger">
                        <i class="fas fa-times"></i> İptal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; 2024 ONVIBES - Tüm hakları saklıdır.</p>
        </div>
    </footer>

    <script>
        function openEditModal(column) {
            document.getElementById('edit_column_id').value = column.id;
            document.getElementById('edit_title').value = column.title;
            document.getElementById('edit_category_id').value = column.category_id || '';
            document.getElementById('edit_image').value = column.image || '';
            document.getElementById('edit_content').value = column.content;
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
