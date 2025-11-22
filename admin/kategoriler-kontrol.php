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

// Kategori ekleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    try {
        $query = "INSERT INTO categories (name, description) VALUES (:name, :description)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        if ($stmt->execute()) {
            $message = 'Kategori eklendi!';
        }
    } catch (PDOException $e) {
        $message = 'Ekleme hatası!';
    }
}

// Kategori güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_category'])) {
    $id = (int)$_POST['category_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    try {
        $query = "UPDATE categories SET name = :name, description = :description WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':id', $id);
        if ($stmt->execute()) {
            $message = 'Kategori güncellendi!';
        }
    } catch (PDOException $e) {
        $message = 'Güncelleme hatası!';
    }
}

// Kategori silme
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    try {
        $query = "DELETE FROM categories WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_GET['id']);
        if ($stmt->execute()) {
            $message = 'Kategori silindi!';
        }
    } catch (PDOException $e) {
        $message = 'Silme hatası! Bu kategoride haberler olabilir.';
    }
}

// Kategorileri listele
try {
    $query = "SELECT c.*, COUNT(n.id) as news_count 
              FROM categories c 
              LEFT JOIN news n ON c.id = n.category_id 
              GROUP BY c.id 
              ORDER BY c.name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Yönetimi - ONVIBES Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--red:#d2232a;--dark:#2c3e50;--light:#fff;--border:#e0e0e0;--surface:#f8f9fa;--text:#333;--gray:#666;--green:#0a8c2f;--blue:#3498db}
        .dark-mode{--red:#ff6b6b;--dark:#0a0a0a;--light:#1a1a1a;--border:#333;--surface:#1a1a1a;--text:#f0f0f0;--gray:#a0a0a0}
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
        body{background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);color:var(--text);min-height:100vh;background-attachment:fixed}
        .dark-mode body{background:linear-gradient(135deg,#0a0a0a 0%,#1a1a1a 100%)}
        .container{max-width:1200px;margin:0 auto;padding:0 15px}
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
        .grid-2{display:grid;grid-template-columns:1fr 2fr;gap:30px}
        .card{background:var(--light);padding:25px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08)}
        .dark-mode .card{background:var(--light)}
        .card-title{font-size:20px;font-weight:700;margin-bottom:20px;color:var(--dark);border-bottom:3px solid var(--red);padding-bottom:10px}
        .dark-mode .card-title{color:var(--text)}
        .form-group{margin-bottom:20px}
        .form-group label{display:block;margin-bottom:8px;font-weight:600;font-size:14px}
        .form-group input,.form-group textarea{width:100%;padding:12px 15px;border:2px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-size:14px}
        .form-group textarea{min-height:80px;resize:vertical}
        .btn{padding:12px 24px;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:8px}
        .btn-primary{background:var(--red);color:#fff}
        .btn-success{background:var(--green);color:#fff}
        .btn-danger{background:#e74c3c;color:#fff}
        .btn-info{background:var(--blue);color:#fff}
        .btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.15)}
        .btn-sm{padding:6px 12px;font-size:12px}
        .category-item{padding:15px;border-bottom:1px solid var(--border);transition:all .3s}
        .category-item:hover{background:var(--surface)}
        .category-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
        .category-name{font-weight:700;font-size:16px;color:var(--dark)}
        .dark-mode .category-name{color:var(--text)}
        .category-count{background:var(--red);color:#fff;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600}
        .category-desc{color:var(--gray);font-size:13px;margin-bottom:10px}
        .action-buttons{display:flex;gap:8px}
        @media (max-width:768px){.grid-2{grid-template-columns:1fr}}
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
        <h1 class="page-title"><i class="fas fa-folder-tree"></i> Kategori Yönetimi</h1>
        <div class="breadcrumb">
            <a href="admin.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>Kategoriler</span>
        </div>

        <?php if($message): ?>
        <div class="alert"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <div class="card">
                <h2 class="card-title"><i class="fas fa-plus-circle"></i> Yeni Kategori Ekle</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Kategori Adı</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Açıklama</label>
                        <textarea name="description"></textarea>
                    </div>
                    <button type="submit" name="add_category" class="btn btn-primary">
                        <i class="fas fa-save"></i> Kategori Ekle
                    </button>
                </form>
            </div>

            <div class="card">
                <h2 class="card-title"><i class="fas fa-list"></i> Mevcut Kategoriler (<?php echo count($categories); ?>)</h2>
                <?php foreach ($categories as $category): ?>
                <div class="category-item">
                    <div class="category-header">
                        <span class="category-name">
                            <i class="fas fa-folder"></i> <?php echo htmlspecialchars($category['name']); ?>
                        </span>
                        <span class="category-count">
                            <?php echo $category['news_count']; ?> haber
                        </span>
                    </div>
                    <?php if ($category['description']): ?>
                    <div class="category-desc">
                        <?php echo htmlspecialchars($category['description']); ?>
                    </div>
                    <?php endif; ?>
                    <div class="action-buttons">
                        <button onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)" class="btn btn-info btn-sm">
                            <i class="fas fa-edit"></i> Düzenle
                        </button>
                        <a href="?action=delete&id=<?php echo $category['id']; ?>" 
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?')">
                            <i class="fas fa-trash"></i> Sil
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="editModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);z-index:2000;align-items:center;justify-content:center">
        <div style="background:var(--light);padding:30px;border-radius:16px;max-width:500px;width:90%">
            <h2 style="font-size:24px;font-weight:700;margin-bottom:20px">Kategori Düzenle</h2>
            <form method="POST">
                <input type="hidden" name="category_id" id="edit_id">
                <div class="form-group">
                    <label>Kategori Adı</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Açıklama</label>
                    <textarea name="description" id="edit_description"></textarea>
                </div>
                <div style="display:flex;gap:10px">
                    <button type="submit" name="update_category" class="btn btn-success">
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
        function editCategory(cat) {
            document.getElementById('edit_id').value = cat.id;
            document.getElementById('edit_name').value = cat.name;
            document.getElementById('edit_description').value = cat.description || '';
            document.getElementById('editModal').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
    </script>
</body>
</html>
