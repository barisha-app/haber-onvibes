<?php
// config.php - Database ve Genel Ayarlar
error_reporting(E_ALL);
ini_set('display_errors', 1);

class Database {
    private $host = "localhost";
    private $db_name = "onvibes_online_barisha";
    private $username = "onvib_barisha";
    private $password = "!Cpc8zP2?pSvaev1";
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            // Detaylı hata mesajı
            die("Veritabanı Bağlantı Hatası: " . $e->getMessage() . "<br>Kod: " . $e->getCode());
        }
        
        return $this->conn;
    }
}

// Site Ayarları
class SiteConfig {
    public static $siteName = "ONVIBES";
    public static $siteSlogan = "Güncel Haberler ve Köşe Yazıları";
    public static $siteUrl = "https://onvibes.online";
    public static $adminEmail = "admin@onvibes.online";
    
    // Dosya yükleme ayarları
    public static $uploadPath = "uploads/";
    public static $newsImagePath = "uploads/news/";
    public static $profileImagePath = "uploads/profiles/";
    public static $maxFileSize = 5242880; // 5MB
    public static $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    // Sayfalama ayarları
    public static $newsPerPage = 12;
    public static $commentsPerPage = 10;
    public static $searchResultsPerPage = 15;
    
    // Sosyal medya
    public static $facebookUrl = "https://facebook.com/onvibes";
    public static $twitterUrl = "https://twitter.com/onvibes";
    public static $instagramUrl = "https://instagram.com/onvibes";
    public static $youtubeUrl = "https://youtube.com/onvibes";
}

// Yardımcı Fonksiyonlar
class Helper {
    // XSS koruması
    public static function sanitize($data) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    // Tarih formatı
    public static function formatDate($date, $format = 'd.m.Y H:i') {
        return date($format, strtotime($date));
    }
    
    // Göreli zaman (örn: 2 saat önce)
    public static function timeAgo($datetime) {
        $timestamp = strtotime($datetime);
        $difference = time() - $timestamp;
        
        if ($difference < 60) {
            return 'Az önce';
        } elseif ($difference < 3600) {
            $minutes = floor($difference / 60);
            return $minutes . ' dakika önce';
        } elseif ($difference < 86400) {
            $hours = floor($difference / 3600);
            return $hours . ' saat önce';
        } elseif ($difference < 604800) {
            $days = floor($difference / 86400);
            return $days . ' gün önce';
        } else {
            return date('d.m.Y H:i', $timestamp);
        }
    }
    
    // Metin kısaltma
    public static function excerpt($text, $length = 150) {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . '...';
    }
    
    // URL slug oluşturma
    public static function createSlug($text) {
        $turkish = array('ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç');
        $english = array('i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c');
        $text = str_replace($turkish, $english, $text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        $text = trim($text, '-');
        return $text;
    }
    
    // Görüntülenme sayısı formatı (1.2K, 1.5M vb.)
    public static function formatNumber($number) {
        if ($number < 1000) {
            return $number;
        } elseif ($number < 1000000) {
            return round($number / 1000, 1) . 'K';
        } else {
            return round($number / 1000000, 1) . 'M';
        }
    }
    
    // Kullanıcı rolü kontrol
    public static function hasRole($requiredRole) {
        if (!isset($_SESSION['role'])) {
            return false;
        }
        
        $roles = ['admin' => 4, 'yazar' => 3, 'muhabir' => 2, 'user' => 1];
        $userRoleLevel = $roles[$_SESSION['role']] ?? 0;
        $requiredRoleLevel = $roles[$requiredRole] ?? 5;
        
        return $userRoleLevel >= $requiredRoleLevel;
    }
    
    // Resim yükleme
    public static function uploadImage($file, $path) {
        if (!isset($file) || $file['error'] !== 0) {
            return false;
        }
        
        // Dosya tipi kontrolü
        if (!in_array($file['type'], SiteConfig::$allowedImageTypes)) {
            return false;
        }
        
        // Dosya boyutu kontrolü
        if ($file['size'] > SiteConfig::$maxFileSize) {
            return false;
        }
        
        // Klasör yoksa oluştur
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        
        // Dosya adı oluştur
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $path . $filename;
        
        // Dosyayı yükle
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return $filename;
        }
        
        return false;
    }
    
    // Kategori rengi
    public static function getCategoryColor($categoryName) {
        $colors = [
            'Politika' => '#e74c3c',
            'Ekonomi' => '#3498db',
            'Spor' => '#2ecc71',
            'Teknoloji' => '#9b59b6',
            'Kültür' => '#f39c12',
            'Sağlık' => '#1abc9c',
            'Dünya' => '#34495e',
            'Gündem' => '#e50914',
        ];
        
        return $colors[$categoryName] ?? '#95a5a6';
    }
}
?>
