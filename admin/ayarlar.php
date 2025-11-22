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

// Site ayarlarını al
try {
    $query = "SELECT * FROM site_settings LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        // Ayarlar yoksa oluştur
        $query = "INSERT INTO site_settings (site_name, site_description, maintenance_mode) VALUES ('ONVIBES', 'Son Dakika Haberler', 0)";
        $db->query($query);
        $settings = ['site_name' => 'ONVIBES', 'site_description' => 'Son Dakika Haberler', 'maintenance_mode' => 0];
    }
} catch (PDOException $e) {
    $settings = ['site_name' => 'ONVIBES', 'site_description' => 'Son Dakika Haberler', 'maintenance_mode' => 0];
}

// Ayarları güncelle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $site_name = trim($_POST['site_name']);
    $site_description = trim($_POST['site_description']);
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    $contact_email = trim($_POST['contact_email']);
    
    try {
        $query = "UPDATE site_settings SET 
                  site_name = :site_name,
                  site_description = :site_description,
                  maintenance_mode = :maintenance_mode,
                  contact_email = :contact_email
                  WHERE id = 1";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':site_name', $site_name);
        $stmt->bindParam(':site_description', $site_description);
        $stmt->bindParam(':maintenance_mode', $maintenance_mode);
        $stmt->bindParam(':contact_email', $contact_email);
        
        if ($stmt->execute()) {
            $message = 'Ayarlar kaydedildi!';
            $settings = ['site_name' => $site_name, 'site_description' => $site_description, 'maintenance_mode' => $maintenance_mode, 'contact_email' => $contact_email];
        }
    } catch (PDOException $e) {
        $message = 'Kaydetme hatası: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Ayarları - ONVIBES Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--red:#d2232a;--dark:#2c3e50;--light:#fff;--border:#e0e0e0;--surface:#f8f9fa;--text:#333;--gray:#666;--green:#0a8c2f;--blue:#3498db;--orange:#e67e22}
        .dark-mode{--red:#ff6b6b;--dark:#0a0a0a;--light:#1a1a1a;--border:#333;--surface:#1a1a1a;--text:#f0f0f0;--gray:#a0a0a0}
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
        body{background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);color:var(--text);min-height:100vh;background-attachment:fixed}
        .dark-mode body{background:linear-gradient(135deg,#0a0a0a 0%,#1a1a1a 100%)}
        .container{max-width:1000px;margin:0 auto;padding:0 15px}
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
        .alert{padding:15px 20px;border-radius:12px;margin-bottom:25px;background:#d4edda;color:#155724;font-weight:600;display:flex;align-items:center;gap:10px}
        .settings-card{background:var(--light);padding:30px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08);margin-bottom:25px}
        .dark-mode .settings-card{background:var(--light)}
        .section-title{font-size:20px;font-weight:700;margin-bottom:20px;color:var(--dark);border-bottom:3px solid var(--red);padding-bottom:10px}
        .dark-mode .section-title{color:var(--text)}
        .form-group{margin-bottom:25px}
        .form-group label{display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:var(--dark)}
        .dark-mode .form-group label{color:var(--text)}
        .form-group input,.form-group textarea{width:100%;padding:12px 15px;border:2px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-size:14px}
        .form-group textarea{min-height:100px;resize:vertical}
        .switch-group{display:flex;align-items:center;gap:15px;padding:15px;background:var(--surface);border-radius:8px}
        .switch{position:relative;display:inline-block;width:60px;height:34px}
        .switch input{opacity:0;width:0;height:0}
        .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;transition:.4s;border-radius:34px}
        .slider:before{position:absolute;content:"";height:26px;width:26px;left:4px;bottom:4px;background:#fff;transition:.4s;border-radius:50%}
        input:checked+.slider{background:var(--green)}
        input:checked+.slider:before{transform:translateX(26px)}
        .btn{padding:12px 24px;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;transition:all .3s;display:inline-flex;align-items:center;gap:8px}
        .btn-primary{background:var(--red);color:#fff}
        .btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.15)}
        .info-box{background:linear-gradient(135deg,var(--blue) 0%,#3e80db 100%);color:#fff;padding:20px;border-radius:12px;margin-bottom:25px}
        .info-box h3{font-size:18px;margin-bottom:10px}
        .info-box p{font-size:14px;line-height:1.6;opacity:.9}
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
        <h1 class="page-title"><i class="fas fa-cog"></i> Site Ayarları</h1>
        <div class="breadcrumb">
            <a href="admin.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>Site Ayarları</span>
        </div>

        <?php if($message): ?>
        <div class="alert"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>

        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> Site Yönetimi</h3>
            <p>Buradan sitenizin genel ayarlarını, bakım modunu ve iletişim bilgilerini yönetebilirsiniz. Yaptığınız değişiklikler anında etkili olacaktır.</p>
        </div>

        <div class="settings-card">
            <h2 class="section-title"><i class="fas fa-globe"></i> Genel Ayarlar</h2>
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Site Adı</label>
                    <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? 'ONVIBES'); ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Site Açıklaması</label>
                    <textarea name="site_description" required><?php echo htmlspecialchars($settings['site_description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> İletişim E-posta</label>
                    <input type="email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? 'appbarisha@gmail.com'); ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-tools"></i> Bakım Modu</label>
                    <div class="switch-group">
                        <label class="switch">
                            <input type="checkbox" name="maintenance_mode" <?php echo isset($settings['maintenance_mode']) && $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <span style="font-weight:600">
                            <?php echo isset($settings['maintenance_mode']) && $settings['maintenance_mode'] ? 'Bakım Modu AÇIK' : 'Bakım Modu KAPALI'; ?>
                        </span>
                    </div>
                    <small style="color:var(--gray);display:block;margin-top:8px">
                        <i class="fas fa-info-circle"></i> Bakım modu açıkken site ziyaretçilere kapalıdır. Sadece adminler erişebilir.
                    </small>
                </div>

                <button type="submit" name="update_settings" class="btn btn-primary">
                    <i class="fas fa-save"></i> Ayarları Kaydet
                </button>
            </form>
        </div>

        <div class="settings-card">
            <h2 class="section-title"><i class="fas fa-database"></i> Sistem Bilgileri</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px">
                <div style="background:var(--surface);padding:20px;border-radius:8px">
                    <div style="font-size:14px;color:var(--gray);margin-bottom:5px">PHP Versiyonu</div>
                    <div style="font-size:24px;font-weight:800;color:var(--green)"><?php echo phpversion(); ?></div>
                </div>
                <div style="background:var(--surface);padding:20px;border-radius:8px">
                    <div style="font-size:14px;color:var(--gray);margin-bottom:5px">Veritabanı</div>
                    <div style="font-size:24px;font-weight:800;color:var(--blue)">MySQL</div>
                </div>
                <div style="background:var(--surface);padding:20px;border-radius:8px">
                    <div style="font-size:14px;color:var(--gray);margin-bottom:5px">Sunucu Saati</div>
                    <div style="font-size:24px;font-weight:800;color:var(--orange)"><?php echo date('H:i'); ?></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
