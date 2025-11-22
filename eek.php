<?php
// eek.php - En Aktif 100 Kullanıcı (Ana Sayfa Tasarımı ile)
session_start();
include 'config.php';

$database = new Database();
$db = $database->getConnection();

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: eek.php');
    exit();
}
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Kullanıcı giriş kontrolü
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $is_logged_in ? $_SESSION['username'] : '';

// Kategorileri veritabanından çek
try {
    $categories_query = "SELECT * FROM categories ORDER BY name ASC";
    $categories_stmt = $db->prepare($categories_query);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Zaman filtresi kontrolü
$time_filter = isset($_GET['time_filter']) ? $_GET['time_filter'] : 'all';

// Mevcut tarih
$current_date = date('Y-m-d');

// Filtreye göre tarih hesaplama
switch ($time_filter) {
    case 'week':
        $date_condition = "AND n.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        $filter_title = "Son 1 Hafta";
        break;
    case 'month':
        $date_condition = "AND n.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $filter_title = "Son 1 Ay";
        break;
    case 'year':
        $date_condition = "AND n.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $filter_title = "Son 1 Yıl";
        break;
    default: // all
        $date_condition = "";
        $filter_title = "Tüm Zamanlar";
        break;
}

// API fonksiyonları (ana sayfadan kopyalandı)
function getAPIData($url) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/xml, */*',
            'Accept-Language: tr-TR,tr;q=0.9,en;q=0.8'
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        return $response;
    }
    
    return null;
}

// Hava Durumu API fonksiyonları
function getWeatherData($city_id) {
    if (empty($city_id)) return null;
    
    $weather_json = getAPIData('https://api.tavcan.com/json/havadurumu/' . $city_id);
    if ($weather_json) {
        $weather_data = json_decode($weather_json, true);
        return $weather_data;
    }
    return null;
}

// Büyük şehirlerin hava durumları (navigasyon için)
$big_cities = ['diyarbakir', 'istanbul', 'ankara', 'antalya', 'izmir', 'trabzon', 'bursa'];
$quick_weather_data = [];

foreach ($big_cities as $city_id) {
    $weather_data = getWeatherData($city_id);
    if ($weather_data && isset($weather_data[0])) {
        $quick_weather_data[$city_id] = $weather_data[0];
    }
    usleep(100000);
}

// Piyasa Verileri Sınıfı
class PiyasaVerileriSystem {
    private $base_url = "https://query1.finance.yahoo.com/v8/finance/chart/";
    private $spread_oran = 0.0025;

    private function getAPIData($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($http_code === 200 && $response) ? $response : null;
    }

    public function getPiyasaVerisi($symbol) {
        $url = $this->base_url . $symbol;
        $data = $this->getAPIData($url);
        
        if(!$data) {
            return ['error' => 'Veri alınamadı'];
        }
        
        $result = json_decode($data, true);
        
        if(isset($result['chart']['result'][0])) {
            $chart = $result['chart']['result'][0];
            $meta = $chart['meta'];
            
            $price = $meta['regularMarketPrice'] ?? ($meta['previousClose'] ?? 0);
            $previousClose = $meta['previousClose'] ?? $price;
            
            $change = $price - $previousClose;
            $change_percent = $previousClose != 0 ? ($change / $previousClose * 100) : 0;
            
            $spread = $price * $this->spread_oran;
            
            return [
                'alis' => number_format($price - ($spread / 2), 2, '.', ''),
                'satis' => number_format($price + ($spread / 2), 2, '.', ''),
                'degisim' => number_format($change_percent, 2, '.', ''),
                'deger' => number_format($price, 2, '.', '')
            ];
        }
        
        return ['error' => 'Hatalı veri formatı'];
    }
}

// Döviz verilerini çek
try {
    $piyasa_sistemi = new PiyasaVerileriSystem();
    
    $doviz_data = [
        'piyasalar' => [
            'dolar' => $piyasa_sistemi->getPiyasaVerisi('TRY=X'),
            'euro' => $piyasa_sistemi->getPiyasaVerisi('EURTRY=X'),
            'sterlin' => $piyasa_sistemi->getPiyasaVerisi('GBPTRY=X'),
            'altin' => $piyasa_sistemi->getPiyasaVerisi('GC=F'),
            'gumus' => $piyasa_sistemi->getPiyasaVerisi('SI=F'),
            'bist' => $piyasa_sistemi->getPiyasaVerisi('XU100.IS'),
            'bitcoin' => $piyasa_sistemi->getPiyasaVerisi('BTC-USD')
        ]
    ];
} catch (Exception $e) {
    $doviz_data = null;
}

// En aktif kullanıcıları çek (zaman filtresine göre) - Basitleştirilmiş sorgu
try {
    // Önce tüm kullanıcıları ve temel verilerini al
    $query = "SELECT u.id, u.username, u.full_name, u.role, u.created_at FROM users u ORDER BY u.id LIMIT 100";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $basic_users = $stmt->fetchAll();
    
    $users = [];
    $rank = 1;
    
    foreach ($basic_users as $user) {
        // Her kullanıcı için detaylı istatistikleri hesapla
        $user_stats = [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
            'created_at' => $user['created_at'],
            'haber_sayisi' => 0,
            'toplam_goruntulenme' => 0,
            'yorum_sayisi' => 0,
            'activity_score' => 0
        ];
        
        // Haber sayısını çek
        $news_query = "SELECT COUNT(*) as count, COALESCE(SUM(views), 0) as views 
                      FROM news WHERE author_id = ? AND status = 'published'";
        if ($time_filter != 'all') {
            if ($time_filter == 'week') {
                $news_query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            } elseif ($time_filter == 'month') {
                $news_query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            } elseif ($time_filter == 'year') {
                $news_query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            }
        }
        
        $news_stmt = $db->prepare($news_query);
        $news_stmt->execute([$user['id']]);
        $news_data = $news_stmt->fetch();
        
        if ($news_data) {
            $user_stats['haber_sayisi'] = (int)$news_data['count'];
            $user_stats['toplam_goruntulenme'] = (int)$news_data['views'];
        }
        
        // Yorum sayısını çek
        $comments_query = "SELECT COUNT(*) as count FROM comments WHERE user_id = ? AND status = 'approved'";
        if ($time_filter != 'all') {
            if ($time_filter == 'week') {
                $comments_query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            } elseif ($time_filter == 'month') {
                $comments_query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            } elseif ($time_filter == 'year') {
                $comments_query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            }
        }
        
        $comments_stmt = $db->prepare($comments_query);
        $comments_stmt->execute([$user['id']]);
        $comments_data = $comments_stmt->fetch();
        
        if ($comments_data) {
            $user_stats['yorum_sayisi'] = (int)$comments_data['count'];
        }
        
        // Aktivite skorunu hesapla
        $user_stats['activity_score'] = ($user_stats['haber_sayisi'] * 10) + ($user_stats['yorum_sayisi'] * 2) + ($user_stats['toplam_goruntulenme'] / 100);
        
        $users[] = $user_stats;
    }
    
    // Skora göre sırala
    usort($users, function($a, $b) {
        return $b['activity_score'] <=> $a['activity_score'];
    });
    
    // Sadece ilk 100'ü al
    $users = array_slice($users, 0, 100);
    
} catch (Exception $e) {
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>En Aktif Kullanıcılar - ONVIBES</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ONVIBES Tema Değişkenleri - Premium */
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
            --purple: #9b59b6;
            --success: #28a745;
            --warning: #ffc107;
        }

        .dark-mode {
            --red: #ff6b6b;
            --dark: #000000;
            --light: #0a0a0a;
            --border: #1a1a1a;
            --surface: #0f0f0f;
            --text: #e0e0e0;
            --gray: #888888;
            --green: #2ecc71;
            --blue: #3498db;
            --orange: #e67e22;
            --purple: #9b59b6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        html {
            background: #f5f7fa;
        }
        
        html.dark-mode {
            background: #000000 !important;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            background-attachment: fixed;
        }

        body.dark-mode {
            background: #000000 !important;
            background-attachment: fixed;
            color: #e0e0e0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Header - Premium Upgrade */
        .main-menu {
            background: var(--light);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08), 0 1px 3px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
        }

        .dark-mode .main-menu {
            background: #0a0a0a;
            border-bottom: 1px solid #1a1a1a;
            box-shadow: 0 4px 20px rgba(0,0,0,0.8);
        }

        .top-bar {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 6px 0;
            position: relative;
            overflow: hidden;
        }

        .top-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .top-bar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .logo {
            font-size: 0;
            flex-shrink: 0;
        }

        .logo a {
            text-decoration: none;
        }

        .logo-text {
            color: white;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            background: linear-gradient(45deg, #ffffff, #ff6b6b, #ffffff);
            background-size: 200% 200%;
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer-text 3s ease-in-out infinite;
            display: inline-block;
            position: relative;
        }
        
        .logo-text::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent);
            transform: translateX(-100%);
            animation: shine 2s ease-in-out infinite;
        }
        
        @keyframes shimmer-text {
            0%, 100% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
        }
        
        @keyframes shine {
            0% {
                transform: translateX(-100%);
            }
            100% {
                transform: translateX(100%);
            }
        }

        /* Header Linkler - Enhanced */
        .header-links {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
            justify-content: center;
            flex-wrap: wrap;
        }

        .header-link {
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 25px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }
        
        .header-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            width: 0;
            height: 2px;
            background: white;
            transform: translateX(-50%);
            transition: width 0.3s;
        }
        
        .header-link:hover::after {
            width: 80%;
        }

        .header-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .header-link:hover::before {
            left: 100%;
        }

        .header-link:hover {
            background: rgba(255,255,255,0.15);
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }
        
        .header-link:active {
            transform: translateY(-1px) scale(0.98);
        }

        .header-link i {
            margin-right: 6px;
            font-size: 12px;
        }

        .right-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .top-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .theme-toggle-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
        }

        .theme-toggle-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .theme-toggle-btn:hover::before {
            width: 100%;
            height: 100%;
        }

        .theme-toggle-btn:hover {
            transform: translateY(-2px) rotate(20deg) scale(1.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .theme-toggle-btn:active {
            transform: translateY(0) rotate(0deg) scale(0.95);
        }

        /* Kullanıcı Profil Butonu */
        .user-profile-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
            text-decoration: none;
        }

        .user-profile-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .user-profile-btn:hover::before {
            width: 100%;
            height: 100%;
        }

        .user-profile-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .user-profile-btn:active {
            transform: translateY(0) scale(0.98);
        }

        .user-profile-btn i {
            font-size: 14px;
        }

        /* Navigation - Premium */
        #mainnav {
            background: linear-gradient(135deg, var(--dark) 0%, #34495e 100%);
            position: relative;
            overflow: hidden;
        }

        #mainnav::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 50%, rgba(255,255,255,0.05) 0%, transparent 70%);
        }

        #mainnav .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        
        .nav-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
            font-size: 12px;
            font-weight: 600;
            order: 2;
        }
        
        .current-time {
            background: rgba(255,255,255,0.15);
            padding: 8px 15px;
            border-radius: 20px;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.3);
            animation: pulse-glow 2s ease-in-out infinite;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 90px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .current-time i {
            font-size: 13px;
            opacity: 0.9;
            color: #ffd700;
        }
        
        #current-time {
            font-weight: 700 !important;
            color: #ffffff !important;
            text-shadow: 0 0 5px rgba(0,0,0,0.5) !important;
            font-size: 12px !important;
            letter-spacing: 0.5px;
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            background: transparent !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .weather-info {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            padding: 6px 12px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            color: white !important;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .weather-info:hover {
            background: rgba(255,255,255,0.15);
            transform: translateY(-1px);
        }
        
        .city-name {
            font-size: 11px;
            font-weight: 600;
            color: #ffffff !important;
            opacity: 0.9;
        }
        
        .weather-icon {
            font-size: 14px;
            color: #ffd700 !important;
        }
        
        .temperature {
            font-weight: 700;
            color: #ffd700 !important;
        }
        
        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 0 5px rgba(255,255,255,0.2);
            }
            50% {
                box-shadow: 0 0 15px rgba(255,255,255,0.4);
            }
        }

        .nav-links {
            display: flex;
            list-style: none;
            flex: 1;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
            order: 1;
        }

        .nav-links::-webkit-scrollbar {
            display: none;
        }

        .nav-links li {
            position: relative;
            flex-shrink: 0;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 12px 16px;
            display: block;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }
        
        .nav-links a::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .nav-links a:hover::after {
            width: 200%;
            height: 200%;
        }

        .nav-links a::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: var(--red);
            transition: all 0.3s;
            transform: translateX(-50%);
        }

        .nav-links a:hover::before,
        .nav-links a.active::before {
            width: 80%;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: var(--red);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(210, 35, 42, 0.5);
        }
        
        .nav-links a.active {
            animation: pulseScale 2s ease-in-out infinite;
        }

        /* Döviz Bar - Premium */
        .doviz-bar {
            background: var(--light);
            border-bottom: 1px solid var(--border);
            padding: 4px 0;
            overflow: hidden;
            position: relative;
        }

        .dark-mode .doviz-bar {
            background: #0a0a0a;
            border-bottom: 1px solid #1a1a1a;
        }

        .doviz-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
        }

        .currency-bar ul {
            display: flex;
            list-style: none;
            justify-content: space-between;
            align-items: center;
            flex-wrap: nowrap;
            overflow-x: auto;
            gap: 10px;
            padding: 0 5px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .currency-bar ul::-webkit-scrollbar {
            display: none;
        }

        .currency-bar li {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            font-weight: 800;
            padding: 5px 8px;
            white-space: nowrap;
            flex-shrink: 0;
            background: rgba(0,0,0,0.02);
            border-radius: 15px;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border: 1px solid transparent;
            min-width: 60px;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .currency-bar li::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(210, 35, 42, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .currency-bar li:hover::before {
            left: 100%;
        }

        .dark-mode .currency-bar li {
            background: #0f0f0f;
            color: var(--text);
            border: 1px solid #1a1a1a;
        }

        .currency-bar li:hover {
            background: rgba(210, 35, 42, 0.08);
            border-color: rgba(210, 35, 42, 0.2);
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 4px 12px rgba(210, 35, 42, 0.2);
        }

        .currency-bar .currency-symbol {
            font-size: 14px;
            font-weight: 800;
            color: var(--red);
            min-width: 20px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .currency-bar li:hover .currency-symbol {
            transform: scale(1.3) rotate(10deg);
            filter: drop-shadow(0 0 5px rgba(210, 35, 42, 0.5));
        }

        .currency-bar .amount {
            font-weight: 800;
            color: var(--dark);
            font-size: 10px;
            min-width: 40px;
            text-align: right;
            letter-spacing: 0.3px;
            transition: all 0.3s;
        }
        
        .currency-bar li:hover .amount {
            font-size: 11px;
            color: var(--red);
        }

        .dark-mode .currency-bar .amount {
            color: #e2e8f0;
            font-weight: 700;
        }

        .currency-bar .change {
            font-size: 8px;
            padding: 2px 5px;
            border-radius: 8px;
            min-width: 35px;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .currency-bar li:hover .change {
            transform: scale(1.1);
            font-weight: 700;
        }

        .currency-bar .up {
            color: var(--green);
            background: rgba(10, 140, 47, 0.1);
        }

        .currency-bar .down {
            color: var(--red);
            background: rgba(210, 35, 42, 0.1);
        }

        .currency-bar .up::before {
            content: '▲';
            font-size: 8px;
            margin-right: 2px;
        }

        .currency-bar .down::before {
            content: '▼';
            font-size: 8px;
            margin-right: 2px;
        }

        /* Ana İçerik Düzeni - Enhanced */
        .main-content {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 25px;
            margin: 25px 0;
        }

        /* Sol Sidebar - Premium */
        .sidebar-left {
            background: var(--light);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            animation: fadeInLeft 0.8s ease-out;
        }

        .dark-mode .sidebar-left {
            background: #0a0a0a;
            box-shadow: 0 8px 32px rgba(0,0,0,0.8);
            border: 1px solid #1a1a1a;
        }

        .sidebar-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--red), #ff6b6b, var(--red));
            background-size: 200% 200%;
            border-radius: 16px 16px 0 0;
            animation: gradientShift 3s ease infinite;
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--red);
            color: var(--dark);
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            animation: fadeInRight 0.6s ease-out;
        }
        
        .sidebar-title:hover {
            color: var(--red);
        }

        .dark-mode .sidebar-title {
            color: #ffffff;
        }

        .sidebar-title::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--orange);
            border-radius: 2px;
        }

        /* Son Aktiviteler */
        .activity-list {
            list-style: none;
            margin-bottom: 25px;
        }

        .activity-item {
            margin-bottom: 10px;
            padding: 12px;
            background: var(--surface);
            border-radius: 10px;
            border-left: 3px solid var(--red);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            overflow: hidden;
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(210, 35, 42, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .activity-item:hover::before {
            left: 100%;
        }

        .activity-item:hover {
            transform: translateX(10px) scale(1.02);
            box-shadow: 0 4px 15px rgba(210, 35, 42, 0.2);
            border-left-width: 5px;
        }

        .activity-user {
            font-weight: 600;
            color: var(--red);
            font-size: 13px;
            margin-bottom: 5px;
        }

        .activity-text {
            font-size: 12px;
            color: var(--text);
            line-height: 1.4;
        }

        .activity-time {
            font-size: 10px;
            color: var(--gray);
            margin-top: 5px;
        }

        /* Sidebar Sections */
        .sidebar-section {
            margin-bottom: 25px;
        }

        /* İstatistikler Kartları */
        .stats-grid {
            display: grid;
            gap: 15px;
        }

        .stat-card {
            background: var(--surface);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: radial-gradient(circle, rgba(210, 35, 42, 0.1) 0%, transparent 70%);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
            border-radius: 50%;
        }
        
        .stat-card:hover::before {
            width: 300px;
            height: 300px;
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 10px 30px rgba(210, 35, 42, 0.3);
            border-color: var(--red);
        }
        
        .stat-card:hover .stat-card-icon {
            animation: bounce 1s ease;
        }
        
        .stat-card:hover .stat-card-value {
            animation: heartbeat 1s ease;
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--red), var(--orange));
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: gradientShift 3s ease infinite;
            position: relative;
            z-index: 1;
        }

        .dark-mode .stat-card-value {
            background: linear-gradient(135deg, #ff6b6b, #ffa502);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card-label {
            font-size: 0.85rem;
            color: var(--gray);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.3;
            transition: all 0.3s;
            position: relative;
            z-index: 1;
        }

        /* Orta Bölüm - Kullanıcı Sıralamaları */
        .main-middle {
            flex: 1;
        }

        .users-header {
            background: var(--light);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out;
        }

        .dark-mode .users-header {
            background: #0a0a0a;
            box-shadow: 0 8px 32px rgba(0,0,0,0.8);
            border: 1px solid #1a1a1a;
        }

        .users-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--red), #ff6b6b, #ffd700, #ff6b6b, var(--red));
            background-size: 300% 100%;
            border-radius: 16px 16px 0 0;
            animation: gradientShift 4s ease infinite;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 15px;
            letter-spacing: -0.02em;
            animation: fadeInUp 1s ease-out;
            position: relative;
        }
        
        .page-title::after {
            content: '✨';
            position: absolute;
            margin-left: 10px;
            animation: float 2s ease-in-out infinite;
        }

        .page-subtitle {
            color: var(--gray);
            font-size: 1.1rem;
            max-width: 500px;
            margin: 0 auto;
        }

        /* Zaman Filtreleme Butonları */
        .time-filters {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 30px 0;
            flex-wrap: wrap;
        }

        .time-filter-btn {
            padding: 10px 20px;
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 25px;
            color: var(--text);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            overflow: hidden;
        }
        
        .time-filter-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(210, 35, 42, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .time-filter-btn:hover::after {
            width: 200%;
            height: 200%;
        }

        .time-filter-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .time-filter-btn:hover::before {
            left: 100%;
        }

        .time-filter-btn:hover {
            background: var(--red);
            color: white;
            border-color: var(--red);
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 6px 20px rgba(210, 35, 42, 0.4);
        }
        
        .time-filter-btn:active {
            transform: translateY(-1px) scale(0.98);
        }

        .time-filter-btn.active {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            background-size: 200% 200%;
            color: white;
            border-color: var(--red);
            box-shadow: 0 4px 15px rgba(210, 35, 42, 0.3);
            animation: gradientShift 3s ease infinite, pulseScale 2s ease-in-out infinite;
        }

        /* Kullanıcı Kartları */
        .users-grid {
            padding: 0 20px;
        }

        /* Top 3 Podium */
        .top-3-podium {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 30px;
            margin-bottom: 50px;
            padding: 40px 20px;
        }

        .podium-card {
            background: var(--light);
            border-radius: 20px;
            padding: 30px;
            position: relative;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            text-align: center;
            min-width: 280px;
            animation: fadeInUp 1s ease-out;
        }

        .podium-card:hover {
            transform: translateY(-15px) scale(1.05);
            filter: brightness(1.1);
        }
        
        .podium-card:active {
            animation: wobble 0.8s ease;
        }

        /* 1st Place - Gold */
        .podium-1 {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 50%, #ffd700 100%);
            border: 3px solid #ffaa00;
            order: 2;
            animation: gold-pulse 2s ease-in-out infinite;
        }

        .podium-1::before {
            content: '';
            position: absolute;
            top: -60px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 80px;
            animation: trophy-bounce 1s ease-in-out infinite;
        }

        .podium-1 .trophy-icon {
            font-size: 100px;
            color: #ffd700;
            text-shadow: 0 0 30px rgba(255, 215, 0, 0.8),
                         0 0 60px rgba(255, 215, 0, 0.5);
            animation: gold-trophy-spin 3s ease-in-out infinite;
            display: block;
            margin-bottom: 20px;
            filter: drop-shadow(0 10px 20px rgba(255, 215, 0, 0.5));
        }
        
        .podium-1:hover .trophy-icon {
            animation: gold-trophy-spin 1.5s ease-in-out infinite, bounce 1s ease-in-out infinite;
            filter: drop-shadow(0 15px 30px rgba(255, 215, 0, 0.8));
        }

        @keyframes gold-pulse {
            0%, 100% {
                box-shadow: 0 10px 40px rgba(255, 215, 0, 0.4), 0 0 60px rgba(255, 215, 0, 0.3);
            }
            50% {
                box-shadow: 0 15px 60px rgba(255, 215, 0, 0.6), 0 0 80px rgba(255, 215, 0, 0.5);
            }
        }

        @keyframes gold-trophy-spin {
            0%, 100% {
                transform: rotateY(0deg) scale(1);
            }
            50% {
                transform: rotateY(180deg) scale(1.1);
            }
        }

        /* 2nd Place - Silver */
        .podium-2 {
            background: linear-gradient(135deg, #c0c0c0 0%, #e8e8e8 50%, #c0c0c0 100%);
            border: 3px solid #a0a0a0;
            order: 1;
            animation: silver-pulse 2s ease-in-out infinite;
        }

        .podium-2 .trophy-icon {
            font-size: 80px;
            color: #c0c0c0;
            text-shadow: 0 0 25px rgba(192, 192, 192, 0.6),
                         0 0 50px rgba(192, 192, 192, 0.4);
            animation: silver-trophy-float 2.5s ease-in-out infinite;
            display: block;
            margin-bottom: 20px;
            filter: drop-shadow(0 8px 16px rgba(192, 192, 192, 0.5));
        }
        
        .podium-2:hover .trophy-icon {
            animation: silver-trophy-float 1.5s ease-in-out infinite, float 1s ease-in-out infinite;
            filter: drop-shadow(0 12px 24px rgba(192, 192, 192, 0.7));
        }

        @keyframes silver-pulse {
            0%, 100% {
                box-shadow: 0 8px 30px rgba(192, 192, 192, 0.4);
            }
            50% {
                box-shadow: 0 12px 45px rgba(192, 192, 192, 0.6);
            }
        }

        @keyframes silver-trophy-float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        /* 3rd Place - Bronze */
        .podium-3 {
            background: linear-gradient(135deg, #cd7f32 0%, #daa76a 50%, #cd7f32 100%);
            border: 3px solid #b5651d;
            order: 3;
            animation: bronze-pulse 2s ease-in-out infinite;
        }

        .podium-3 .trophy-icon {
            font-size: 70px;
            color: #cd7f32;
            text-shadow: 0 0 20px rgba(205, 127, 50, 0.5),
                         0 0 40px rgba(205, 127, 50, 0.3);
            animation: bronze-trophy-swing 2s ease-in-out infinite;
            display: block;
            margin-bottom: 20px;
            filter: drop-shadow(0 6px 12px rgba(205, 127, 50, 0.5));
        }
        
        .podium-3:hover .trophy-icon {
            animation: bronze-trophy-swing 1s ease-in-out infinite, wobble 1.5s ease-in-out infinite;
            filter: drop-shadow(0 10px 20px rgba(205, 127, 50, 0.7));
        }

        @keyframes bronze-pulse {
            0%, 100% {
                box-shadow: 0 6px 25px rgba(205, 127, 50, 0.4);
            }
            50% {
                box-shadow: 0 10px 35px rgba(205, 127, 50, 0.6);
            }
        }

        @keyframes bronze-trophy-swing {
            0%, 100% {
                transform: rotate(0deg);
            }
            25% {
                transform: rotate(-5deg);
            }
            75% {
                transform: rotate(5deg);
            }
        }

        .podium-rank {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 800;
            color: white;
            z-index: 10;
            animation: bounce 2s ease-in-out infinite;
        }

        .podium-1 .podium-rank {
            background: linear-gradient(135deg, #ffd700, #ffaa00);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.5);
        }

        .podium-2 .podium-rank {
            background: linear-gradient(135deg, #c0c0c0, #a0a0a0);
            box-shadow: 0 5px 15px rgba(192, 192, 192, 0.5);
            color: #333;
        }

        .podium-3 .podium-rank {
            background: linear-gradient(135deg, #cd7f32, #b5651d);
            box-shadow: 0 5px 15px rgba(205, 127, 50, 0.5);
        }

        .podium-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 20px;
            background: rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            border: 4px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
        }
        
        .podium-card:hover .podium-avatar {
            transform: scale(1.1);
            animation: pulseScale 1s ease infinite;
        }

        .podium-name {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #1a1a1a;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.5);
        }

        .podium-username {
            font-size: 1rem;
            color: #333;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .podium-stats {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid rgba(255, 255, 255, 0.3);
        }

        .podium-stat {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.95rem;
        }

        .podium-stat-label {
            color: #333;
            font-weight: 600;
        }

        .podium-stat-value {
            color: #1a1a1a;
            font-weight: 700;
        }

        /* Regular Users Grid */
        .regular-users {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            max-width: 800px;
            margin: 0 auto;
        }

        .regular-user-card {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            overflow: hidden;
        }
        
        .regular-user-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--red);
            transform: scaleY(0);
            transition: transform 0.3s;
        }
        
        .regular-user-card:hover::before {
            transform: scaleY(1);
        }

        .regular-user-card:hover {
            transform: translateX(15px) scale(1.02);
            box-shadow: 0 8px 30px rgba(210, 35, 42, 0.25);
            border-color: var(--red);
        }
        
        .regular-user-card:hover .regular-rank {
            animation: pulseScale 0.6s ease;
        }
        
        .regular-user-card:hover .regular-avatar {
            animation: rotate 0.6s ease;
        }

        .regular-rank {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--red), #ff6b6b);
            background-size: 200% 200%;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
            transition: all 0.3s;
            box-shadow: 0 4px 10px rgba(210, 35, 42, 0.3);
        }

        .regular-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--red);
            flex-shrink: 0;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .regular-info {
            flex: 1;
        }

        .regular-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .regular-username {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .regular-stats {
            display: flex;
            gap: 20px;
            flex-shrink: 0;
        }

        .regular-stat-item {
            text-align: center;
        }

        .regular-stat-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--red);
        }

        .regular-stat-label {
            font-size: 0.75rem;
            color: var(--gray);
            margin-top: 2px;
        }

        /* User Role Badge */
        .user-role {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            color: white;
        }

        .role-admin { background: var(--red); }
        .role-yazar { background: var(--success); }
        .role-muhabir { background: var(--warning); color: #000; }
        .role-user { background: var(--gray); }

        /* Footer */
        footer {
            background: linear-gradient(135deg, var(--dark) 0%, #1a1a1a 100%);
            color: white;
            padding: 40px 0 20px;
            margin-top: 50px;
            position: relative;
            overflow: hidden;
        }
        
        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--red), #ffd700, var(--red), transparent);
            background-size: 200% 100%;
            animation: gradientShift 3s ease infinite;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .footer-column h3 {
            color: white;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }
        
        .footer-column h3::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--red);
            border-radius: 2px;
            animation: pulseScale 2s ease-in-out infinite;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 8px;
        }

        .footer-links a {
            color: var(--gray);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            display: inline-block;
            position: relative;
        }
        
        .footer-links a::before {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--red);
            transition: width 0.3s;
        }
        
        .footer-links a:hover::before {
            width: 100%;
        }

        .footer-links a:hover {
            color: white;
            transform: translateX(5px);
        }

        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: var(--gray);
            font-size: 13px;
            animation: fadeInUp 1.2s ease-out;
        }
        
        .copyright:hover {
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .top-3-podium {
                flex-direction: column;
                align-items: center;
            }
            
            .podium-card {
                width: 100%;
                max-width: 350px;
            }
            
            .podium-1, .podium-2, .podium-3 {
                order: initial;
            }
            
            .regular-user-card {
                flex-direction: column;
                text-align: center;
            }
            
            .regular-stats {
                width: 100%;
                justify-content: center;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .time-filters {
                gap: 8px;
            }
            
            .time-filter-btn {
                padding: 8px 16px;
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 1.8rem;
            }
            
            .time-filters {
                flex-direction: column;
                align-items: center;
            }
            
            .podium-card {
                min-width: 100%;
            }
        }

        /* === ADVANCED ANIMATIONS & EFFECTS === */
        
        /* Fade In Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .animate-fade-in {
            animation: fadeInUp 0.6s ease forwards;
        }
        
        /* Floating Animation */
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        /* Rotate Animation */
        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        
        /* Bounce Animation */
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-15px);
            }
            60% {
                transform: translateY(-10px);
            }
        }
        
        /* Wobble Animation */
        @keyframes wobble {
            0%, 100% {
                transform: translateX(0);
            }
            15% {
                transform: translateX(-10px) rotate(-5deg);
            }
            30% {
                transform: translateX(10px) rotate(3deg);
            }
            45% {
                transform: translateX(-10px) rotate(-3deg);
            }
            60% {
                transform: translateX(10px) rotate(2deg);
            }
            75% {
                transform: translateX(-5px) rotate(-1deg);
            }
        }
        
        /* Pulse Scale Animation */
        @keyframes pulseScale {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        /* Rainbow Gradient Animation */
        @keyframes rainbow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Glow Pulse Animation */
        @keyframes glowPulse {
            0%, 100% {
                box-shadow: 0 0 5px rgba(210, 35, 42, 0.5),
                            0 0 10px rgba(210, 35, 42, 0.3);
            }
            50% {
                box-shadow: 0 0 20px rgba(210, 35, 42, 0.8),
                            0 0 30px rgba(210, 35, 42, 0.5),
                            0 0 40px rgba(210, 35, 42, 0.3);
            }
        }
        
        /* Shake Animation */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        /* Heartbeat Animation */
        @keyframes heartbeat {
            0%, 100% { transform: scale(1); }
            10%, 30% { transform: scale(1.1); }
            20%, 40% { transform: scale(1); }
        }
        
        /* Neon Glow Animation */
        @keyframes neonGlow {
            0%, 100% {
                text-shadow: 0 0 10px var(--red),
                            0 0 20px var(--red),
                            0 0 30px var(--red);
            }
            50% {
                text-shadow: 0 0 20px var(--red),
                            0 0 30px var(--red),
                            0 0 40px var(--red),
                            0 0 50px var(--red);
            }
        }
        
        /* Gradient Shift Animation */
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Scale Rotate Animation */
        @keyframes scaleRotate {
            0% {
                transform: scale(1) rotate(0deg);
            }
            50% {
                transform: scale(1.1) rotate(180deg);
            }
            100% {
                transform: scale(1) rotate(360deg);
            }
        }
        
        /* === EXTRA VISUAL EFFECTS === */
        
        /* Sparkle Effect */
        @keyframes sparkle {
            0%, 100% {
                opacity: 0;
                transform: scale(0);
            }
            50% {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* Slide In From Bottom */
        @keyframes slideInBottom {
            from {
                opacity: 0;
                transform: translateY(100px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Glow Expand */
        @keyframes glowExpand {
            0% {
                box-shadow: 0 0 5px rgba(210, 35, 42, 0.3);
            }
            50% {
                box-shadow: 0 0 20px rgba(210, 35, 42, 0.6),
                            0 0 30px rgba(210, 35, 42, 0.4),
                            0 0 40px rgba(210, 35, 42, 0.2);
            }
            100% {
                box-shadow: 0 0 5px rgba(210, 35, 42, 0.3);
            }
        }
        
        /* Flip Animation */
        @keyframes flip {
            0% {
                transform: perspective(400px) rotateY(0);
            }
            100% {
                transform: perspective(400px) rotateY(360deg);
            }
        }
        
        /* Color Shift */
        @keyframes colorShift {
            0%, 100% {
                filter: hue-rotate(0deg);
            }
            50% {
                filter: hue-rotate(20deg);
            }
        }
        
        /* Dark Mode Specific Enhancements */
        .dark-mode .podium-card {
            background: #0a0a0a;
            border: 1px solid #1a1a1a;
            box-shadow: 0 10px 40px rgba(255, 107, 107, 0.4);
        }
        
        .dark-mode .regular-user-card {
            background: #0a0a0a;
            border: 1px solid #1a1a1a;
        }
        
        .dark-mode .regular-user-card:hover {
            background: #0f0f0f;
            box-shadow: 0 8px 30px rgba(255, 107, 107, 0.5);
            border-color: #ff6b6b;
        }
        
        .dark-mode .regular-name {
            color: #ffffff;
        }
        
        .dark-mode .page-title {
            color: #ffffff;
        }
        
        .dark-mode footer {
            background: #000000;
            border-top: 1px solid #1a1a1a;
        }
        
        /* Dark mode: Main middle section */
        .dark-mode .main-middle {
            background: transparent !important;
        }
        
        /* Dark mode: Podium area background */
        .dark-mode .top-3-podium {
            background: transparent !important;
        }
        
        /* Dark mode: Users grid */
        .dark-mode .users-grid {
            background: transparent !important;
        }
        
        /* Dark mode: Activity items */
        .dark-mode .activity-item {
            background: #0f0f0f;
            border-left-color: #ff6b6b;
        }
        
        /* Dark mode: Stat cards */
        .dark-mode .stat-card {
            background: #0f0f0f;
            border-color: #1a1a1a;
        }
        
        /* Dark mode: Time filter buttons */
        .dark-mode .time-filter-btn {
            background: #0f0f0f;
            border-color: #1a1a1a;
            color: #e0e0e0;
        }
        
        .dark-mode .time-filter-btn.active {
            background: linear-gradient(135deg, #ff6b6b 0%, #d22328 100%);
            border-color: #ff6b6b;
            color: white;
        }
        
        /* Dark mode: Podium avatars stay light for contrast */
        .dark-mode .podium-avatar {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        /* Dark mode: Regular avatars */
        .dark-mode .regular-avatar {
            background: #0f0f0f;
            border-color: #1a1a1a;
        }
        
        /* Dark mode: Page subtitle */
        .dark-mode .page-subtitle {
            color: #888888;
        }
        
        /* Dark mode: Regular username and other gray text */
        .dark-mode .regular-username {
            color: #888888;
        }
        
        /* Dark mode: Activity text */
        .dark-mode .activity-text {
            color: #e0e0e0;
        }
        
        /* Dark mode: Stat card labels */
        .dark-mode .stat-card-label {
            color: #888888;
        }
        
        /* Dark mode: Regular stat labels */
        .dark-mode .regular-stat-label {
            color: #888888;
        }
        
        /* Dark mode: Podium stats border */
        .dark-mode .podium-stats {
            border-top-color: rgba(255, 255, 255, 0.2);
        }
        
        /* Dark mode: Ensure all backgrounds are dark */
        .dark-mode .main-content {
            background: transparent !important;
        }
        
        .dark-mode .container {
            background: transparent !important;
        }
        
        /* Force black background on html element too */
        .dark-mode {
            background: #000000 !important;
        }
        
        /* Dark mode: All wrapper elements must be transparent or black */
        body.dark-mode .main-content,
        body.dark-mode .main-middle,
        body.dark-mode .top-3-podium,
        body.dark-mode .users-grid,
        body.dark-mode .time-filters,
        body.dark-mode .regular-users,
        body.dark-mode .container {
            background: transparent !important;
        }
        
        /* Dark mode: Podium-1 (Gold) stays gold but text adjusted */
        .dark-mode .podium-1 {
            background: linear-gradient(135deg, #ffa500 0%, #ffd700 50%, #ffa500 100%);
            border: 3px solid #ff8c00;
        }
        
        .dark-mode .podium-1 .podium-name {
            color: #1a1a1a;
            text-shadow: 1px 1px 3px rgba(255, 255, 255, 0.6);
        }
        
        .dark-mode .podium-1 .podium-username,
        .dark-mode .podium-1 .podium-stat-label {
            color: #2d2d2d;
        }
        
        .dark-mode .podium-1 .podium-stat-value {
            color: #1a1a1a;
        }
        
        /* Dark mode: Podium-2 (Silver) */
        .dark-mode .podium-2 {
            background: linear-gradient(145deg, #a0a0a0 0%, #d0d0d0 50%, #a0a0a0 100%);
            border: 3px solid #808080;
        }
        
        .dark-mode .podium-2 .podium-name {
            color: #1a1a1a;
            text-shadow: 1px 1px 3px rgba(255, 255, 255, 0.6);
        }
        
        .dark-mode .podium-2 .podium-username,
        .dark-mode .podium-2 .podium-stat-label {
            color: #2d2d2d;
        }
        
        .dark-mode .podium-2 .podium-stat-value {
            color: #1a1a1a;
        }
        
        /* Dark mode: Podium-3 (Bronze) */
        .dark-mode .podium-3 {
            background: linear-gradient(145deg, #b8860b 0%, #daa520 50%, #b8860b 100%);
            border: 3px solid #8b6914;
        }
        
        .dark-mode .podium-3 .podium-name {
            color: #1a1a1a;
            text-shadow: 1px 1px 3px rgba(255, 255, 255, 0.6);
        }
        
        .dark-mode .podium-3 .podium-username,
        .dark-mode .podium-3 .podium-stat-label {
            color: #2d2d2d;
        }
        
        .dark-mode .podium-3 .podium-stat-value {
            color: #1a1a1a;
        }
        
        .dark-mode .stat-card:hover {
            background: #0f0f0f;
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.6);
            border-color: #ff6b6b;
        }
        
        /* Hover Glow Effects */
        .podium-1:hover {
            animation: gold-pulse 1s ease-in-out infinite, glowExpand 2s ease-in-out infinite;
        }
        
        .podium-2:hover {
            animation: silver-pulse 1s ease-in-out infinite, glowExpand 2s ease-in-out infinite;
        }
        
        .podium-3:hover {
            animation: bronze-pulse 1s ease-in-out infinite, glowExpand 2s ease-in-out infinite;
        }
        
        /* Page Load Animation */
        body {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Smooth transitions for all interactive elements */
        a, button, .card, .stat-card, .user-card {
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        /* Add subtle animation to icons */
        .fas, .fab {
            transition: transform 0.3s ease;
        }
        
        .footer-links a:hover .fas,
        .footer-links a:hover .fab {
            transform: scale(1.2) rotate(5deg);
        }
        
        /* Stat card icon hover animation */
        .stat-card:hover .stat-card-icon {
            opacity: 0.8;
            transform: scale(1.2);
        }
        
        /* Activity item icon animation */
        .activity-user::before {
            content: '⚡';
            margin-right: 5px;
            animation: sparkle 2s ease-in-out infinite;
        }
        
        /* Add pulsing effect to active filter */
        .time-filter-btn.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 25px;
            animation: glowPulse 2s ease-in-out infinite;
            z-index: -1;
        }
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <div class="logo">
                <a href="index.php">
                    <div class="logo-text">ONVIBES</div>
                </a>
            </div>
            
            <div class="header-links">
                <a href="Veri/ilan.php" class="header-link">
                    <i class="fas fa-bullhorn"></i>İlanlar
                </a>
                <a href="Veri/haberler.php" class="header-link">
                    <i class="fas fa-newspaper"></i>Haberler
                </a>
                <a href="kose-yazilari.php" class="header-link">
                    <i class="fas fa-chart-line"></i>Analizler
                </a>
                <a href="eek.php" class="header-link">
                    <i class="fas fa-trophy"></i>En Aktif Kullanıcılar
                </a>
            </div>

            <div class="right-nav">
                <div class="top-links">
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="toggle_theme" class="theme-toggle-btn">
                            <i class="fas fa-<?php echo $dark_mode ? 'sun' : 'moon'; ?>"></i>
                        </button>
                    </form>
                    
                    <?php if ($is_logged_in): ?>
                        <a href="profil.php" class="user-profile-btn">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($user_name); ?></span>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="user-profile-btn">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Giriş Yap</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav id="mainnav">
        <div class="container">
            <div class="nav-info">
                <div class="current-time">
                    <i class="fas fa-clock"></i>
                    <span id="current-time">--:--:--</span>
                </div>
                
                <div class="weather-info" id="nav-weather">
                    <span class="city-name">İstanbul</span>
                    <i class="fas fa-sun weather-icon"></i>
                    <span class="temperature">-°C</span>
                </div>
            </div>
            
            <ul class="nav-links">
                <li><a href="index.php">Ana Sayfa</a></li>
                <li><a href="eek.php" class="active">Kullanıcı Sıralaması</a></li>
                <?php foreach ($categories as $category): ?>
                <li><a href="index.php?category=<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>

    <!-- Döviz Bar -->
    <div class="doviz-bar">
        <div class="container">
            <div class="currency-bar">
                <ul>
                    <?php
                    if ($doviz_data && isset($doviz_data['piyasalar'])) {
                        $piyasalar = $doviz_data['piyasalar'];
                        $currency_symbols = [
                            'dolar' => '$',
                            'euro' => '€',
                            'sterlin' => '£',
                            'altin' => '🥇',
                            'gumus' => '🥈',
                            'bist' => '📈',
                            'bitcoin' => '₿'
                        ];
                        
                        foreach ($piyasalar as $piyasa_name => $piyasa_data) {
                            if (isset($piyasa_data['error'])) continue;
                            
                            $symbol = $currency_symbols[$piyasa_name] ?? '💱';
                            $display_name = '';
                            switch ($piyasa_name) {
                                case 'dolar': $display_name = 'USD'; break;
                                case 'euro': $display_name = 'EUR'; break;
                                case 'sterlin': $display_name = 'GBP'; break;
                                case 'altin': $display_name = 'ALTIN'; break;
                                case 'gumus': $display_name = 'GÜMÜŞ'; break;
                                case 'bist': $display_name = 'BIST'; break;
                                case 'bitcoin': $display_name = 'BTC'; break;
                                default: continue;
                            }
                            
                            $change_class = ($piyasa_data['degisim'] >= 0) ? 'up' : 'down';
                            
                            echo "
                            <li>
                                <span class='currency-symbol'>$symbol</span>
                                <span class='amount'>{$piyasa_data['deger']}</span>
                                <span class='change $change_class'>{$piyasa_data['degisim']}%</span>
                            </li>";
                        }
                    } else {
                        echo "<li>Veriler yükleniyor...</li>";
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <div class="main-content">
            <!-- Sol Sidebar -->
            <aside class="sidebar-left">
                <!-- Son Aktiviteler -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">Son Aktiviteler</h3>
                    <ul class="activity-list">
                        <?php
                        // Son 5 kullanıcı aktivitesini göster
                        if (!empty($users)) {
                            $recent_users = array_slice($users, 0, 5);
                            foreach ($recent_users as $user) {
                                $activity = "Yeni haberler ekledi";
                                if ($user['haber_sayisi'] > 0 && $user['yorum_sayisi'] > 0) {
                                    $activity = "Haber ve yorumlar ekledi";
                                } elseif ($user['yorum_sayisi'] > 0) {
                                    $activity = "Yorumlar yaptı";
                                }
                                
                                echo "
                                <li class='activity-item'>
                                    <div class='activity-user'>" . htmlspecialchars($user['username']) . "</div>
                                    <div class='activity-text'>$activity</div>
                                    <div class='activity-time'>" . date('d.m.Y') . "</div>
                                </li>";
                            }
                        } else {
                            echo "<li class='activity-item'>Henüz aktivite yok</li>";
                        }
                        ?>
                    </ul>
                </div>

                <!-- İstatistikler -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">
                        <i class="fas fa-chart-bar"></i> İstatistikler
                    </h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-card-icon">👥</div>
                            <div class="stat-card-value"><?php echo count($users); ?></div>
                            <div class="stat-card-label">Toplam Kullanıcı</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-card-icon">📰</div>
                            <div class="stat-card-value"><?php echo array_sum(array_column($users, 'haber_sayisi')); ?></div>
                            <div class="stat-card-label">Toplam Haber</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-card-icon">💬</div>
                            <div class="stat-card-value"><?php echo array_sum(array_column($users, 'yorum_sayisi')); ?></div>
                            <div class="stat-card-label">Toplam Yorum</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-card-icon">👁️</div>
                            <div class="stat-card-value"><?php echo number_format(array_sum(array_column($users, 'toplam_goruntulenme'))); ?></div>
                            <div class="stat-card-label">Görüntülenme</div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Orta Bölüm - Kullanıcı Sıralamaları -->
            <main class="main-middle">
                <!-- Kullanıcı Sıralama Header -->
                <div class="users-header">
                    <h1 class="page-title">En Aktif Kullanıcılar</h1>
                    <p class="page-subtitle">
                        <?php echo $filter_title; ?> içinde haber, yorum ve etkileşim sayısına göre sıralanmış en aktif kullanıcılarımız
                    </p>
                </div>

                <!-- Zaman Filtreleme -->
                <div class="time-filters">
                    <a href="eek.php?time_filter=all" class="time-filter-btn <?php echo $time_filter == 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-infinity"></i> Tüm Zamanlar
                    </a>
                    <a href="eek.php?time_filter=year" class="time-filter-btn <?php echo $time_filter == 'year' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-year"></i> Son 1 Yıl
                    </a>
                    <a href="eek.php?time_filter=month" class="time-filter-btn <?php echo $time_filter == 'month' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Son 1 Ay
                    </a>
                    <a href="eek.php?time_filter=week" class="time-filter-btn <?php echo $time_filter == 'week' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-week"></i> Son 1 Hafta
                    </a>
                </div>

                <!-- Top 3 Podium -->
                <div class="top-3-podium">
                    <?php 
                    $top_3 = array_slice($users, 0, 3);
                    foreach($top_3 as $index => $user): 
                        $rank = $index + 1;
                        $podium_class = 'podium-' . $rank;
                        
                        $role_class = 'role-' . strtolower($user['role']);
                        $role_text = [
                            'admin' => 'Yönetici',
                            'yazar' => 'Yazar',
                            'muhabir' => 'Muhabir',
                            'user' => 'Kullanıcı'
                        ][$user['role']] ?? 'Kullanıcı';
                    ?>
                    <div class="podium-card <?php echo $podium_class; ?>">
                        <div class="podium-rank"><?php echo $rank; ?></div>
                        
                        <div class="trophy-icon">
                            <?php 
                            if($rank == 1) echo '🏆';
                            elseif($rank == 2) echo '🥈';
                            else echo '🥉';
                            ?>
                        </div>
                        
                        <div class="podium-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        
                        <div class="podium-name">
                            <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                        </div>
                        
                        <div class="podium-username">
                            @<?php echo htmlspecialchars($user['username']); ?>
                        </div>
                        
                        <span class="user-role <?php echo $role_class; ?>">
                            <?php echo $role_text; ?>
                        </span>
                        
                        <div class="podium-stats">
                            <div class="podium-stat">
                                <span class="podium-stat-label">
                                    <i class="fas fa-newspaper"></i> Haberler
                                </span>
                                <span class="podium-stat-value"><?php echo $user['haber_sayisi']; ?></span>
                            </div>
                            
                            <div class="podium-stat">
                                <span class="podium-stat-label">
                                    <i class="fas fa-eye"></i> Görüntülenme
                                </span>
                                <span class="podium-stat-value"><?php echo number_format($user['toplam_goruntulenme']); ?></span>
                            </div>
                            
                            <div class="podium-stat">
                                <span class="podium-stat-label">
                                    <i class="fas fa-comments"></i> Yorumlar
                                </span>
                                <span class="podium-stat-value"><?php echo $user['yorum_sayisi']; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Regular Users (4+) -->
                <?php if(count($users) > 3): ?>
                <div style="text-align: center; margin: 40px 0 20px;">
                    <h2 style="font-size: 1.8rem; font-weight: 700; color: var(--dark);">Diğer Kullanıcılar</h2>
                </div>
                
                <div class="regular-users">
                    <?php 
                    $regular_users = array_slice($users, 3);
                    $rank = 4;
                    foreach($regular_users as $user): 
                        $role_class = 'role-' . strtolower($user['role']);
                        $role_text = [
                            'admin' => 'Yönetici',
                            'yazar' => 'Yazar',
                            'muhabir' => 'Muhabir',
                            'user' => 'Kullanıcı'
                        ][$user['role']] ?? 'Kullanıcı';
                    ?>
                    <div class="regular-user-card">
                        <div class="regular-rank"><?php echo $rank; ?></div>
                        
                        <div class="regular-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        
                        <div class="regular-info">
                            <div class="regular-name">
                                <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                            </div>
                            <div class="regular-username">
                                @<?php echo htmlspecialchars($user['username']); ?>
                            </div>
                            <span class="user-role <?php echo $role_class; ?>" style="display: inline-block; margin-top: 5px;">
                                <?php echo $role_text; ?>
                            </span>
                        </div>
                        
                        <div class="regular-stats">
                            <div class="regular-stat-item">
                                <div class="regular-stat-value"><?php echo $user['haber_sayisi']; ?></div>
                                <div class="regular-stat-label">Haber</div>
                            </div>
                            
                            <div class="regular-stat-item">
                                <div class="regular-stat-value"><?php echo number_format($user['toplam_goruntulenme']); ?></div>
                                <div class="regular-stat-label">Görüntülenme</div>
                            </div>
                            
                            <div class="regular-stat-item">
                                <div class="regular-stat-value"><?php echo $user['yorum_sayisi']; ?></div>
                                <div class="regular-stat-label">Yorum</div>
                            </div>
                        </div>
                    </div>
                    <?php 
                    $rank++;
                    endforeach; 
                    ?>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>ONVIBES</h3>
                    <ul class="footer-links">
                        <li><a href="hakkimizda.php">Hakkımızda</a></li>
                        <li><a href="iletisim.php">İletişim</a></li>
                        <li><a href="kariyer.php">Kariyer</a></li>
                        <li><a href="reklam.php">Reklam</a></li>
                        <li><a href="kunye.php">Künye</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Kategoriler</h3>
                    <ul class="footer-links">
                        <?php foreach ($categories as $category): ?>
                        <li><a href="index.php?category=<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Yardım</h3>
                    <ul class="footer-links">
                        <li><a href="sss.php">SSS</a></li>
                        <li><a href="kullanim.php">Kullanım Koşulları</a></li>
                        <li><a href="gizlilik.php">Gizlilik Politikası</a></li>
                        <li><a href="cerez.php">Çerez Politikası</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Bizi Takip Edin</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                        <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                        <li><a href="#"><i class="fab fa-youtube"></i> YouTube</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; 2024 ONVIBES - Tüm hakları saklıdır.
            </div>
        </div>
    </footer>

    <script>
        // Current time update - Güçlendirilmiş versiyon
        function updateTime() {
            const timeElement = document.getElementById('current-time');
            
            if (timeElement) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('tr-TR', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                timeElement.textContent = timeString;
            }
        }

        // Navigasyon Barı Hava Durumu Animasyonu
        const navWeather = document.getElementById('nav-weather');
        const cities = ['diyarbakir', 'istanbul', 'ankara', 'antalya', 'izmir', 'trabzon', 'bursa'];
        let currentCityIndex = 0;
        
        // PHP'den gelen hava durumu verilerini JavaScript'e aktar
        const quickWeatherData = <?php echo json_encode($quick_weather_data); ?>;
        
        function updateWeather() {
            if (navWeather && Object.keys(quickWeatherData).length > 0) {
                const cityKeys = Object.keys(quickWeatherData);
                const currentCity = cityKeys[currentCityIndex];
                const weatherData = quickWeatherData[currentCity];
                
                if (weatherData) {
                    // Fade out
                    navWeather.style.opacity = '0';
                    
                    setTimeout(() => {
                        // Update content
                        const weatherIcon = getWeatherIcon(weatherData.icon);
                        const cityName = currentCity.charAt(0).toUpperCase() + currentCity.slice(1);
                        
                        navWeather.innerHTML = `
                            <span class='city-name'>${cityName}</span>
                            <i class='${weatherIcon} weather-icon'></i>
                            <span class='temperature'>${weatherData.max}°C</span>
                        `;
                        
                        // Fade in
                        navWeather.style.opacity = '1';
                    }, 200);
                }
                
                // Next city
                currentCityIndex = (currentCityIndex + 1) % cityKeys.length;
            }
        }
        
        function getWeatherIcon(iconCode) {
            const icons = {
                '01d': 'fas fa-sun', '01n': 'fas fa-moon',
                '02d': 'fas fa-cloud-sun', '02n': 'fas fa-cloud-moon',
                '03d': 'fas fa-cloud', '03n': 'fas fa-cloud',
                '04d': 'fas fa-cloud', '04n': 'fas fa-cloud',
                '09d': 'fas fa-cloud-rain', '09n': 'fas fa-cloud-rain',
                '10d': 'fas fa-cloud-sun-rain', '10n': 'fas fa-cloud-moon-rain',
                '11d': 'fas fa-bolt', '11n': 'fas fa-bolt',
                '13d': 'fas fa-snowflake', '13n': 'fas fa-snowflake',
                '50d': 'fas fa-smog', '50n': 'fas fa-smog'
            };
            return icons[iconCode] || 'fas fa-cloud';
        }

        // Scroll Animation Observer
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const fadeInObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Parallax Scroll Effect
        function handleScroll() {
            const scrolled = window.pageYOffset;
            const parallaxElements = document.querySelectorAll('.users-header, .sidebar-left');
            
            parallaxElements.forEach((element, index) => {
                const speed = 0.5 + (index * 0.2);
                element.style.transform = `translateY(${scrolled * speed * 0.05}px)`;
            });
        }

        // Smooth Scroll for Links
        function smoothScroll(target) {
            const element = document.querySelector(target);
            if (element) {
                element.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }

        // Add ripple effect to clickable elements
        function createRipple(event) {
            const button = event.currentTarget;
            const ripple = document.createElement('span');
            const diameter = Math.max(button.clientWidth, button.clientHeight);
            const radius = diameter / 2;
            
            ripple.style.width = ripple.style.height = `${diameter}px`;
            ripple.style.left = `${event.clientX - button.offsetLeft - radius}px`;
            ripple.style.top = `${event.clientY - button.offsetTop - radius}px`;
            ripple.classList.add('ripple');
            
            const rippleEffect = button.getElementsByClassName('ripple')[0];
            if (rippleEffect) {
                rippleEffect.remove();
            }
            
            button.appendChild(ripple);
        }

        // Add CSS for ripple effect dynamically
        const style = document.createElement('style');
        style.textContent = `
            .ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                transform: scale(0);
                animation: rippleAnimation 0.6s ease-out;
                pointer-events: none;
            }
            
            @keyframes rippleAnimation {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            .scroll-fade {
                opacity: 0;
                transform: translateY(30px);
                transition: opacity 0.8s ease-out, transform 0.8s ease-out;
            }
            
            .scroll-fade.visible {
                opacity: 1;
                transform: translateY(0);
            }
        `;
        document.head.appendChild(style);

        // Event listener'lar
        document.addEventListener('DOMContentLoaded', function() {
            // Sync dark mode class to html element
            if (document.body.classList.contains('dark-mode')) {
                document.documentElement.classList.add('dark-mode');
            }
            
            updateTime();
            
            // Start weather rotation if data exists
            if (Object.keys(quickWeatherData).length > 0) {
                setInterval(updateWeather, 5000); // 5 seconds
            }

            // Update time every second
            setInterval(updateTime, 1000);

            // Initialize scroll animations
            const userCards = document.querySelectorAll('.regular-user-card, .podium-card, .stat-card, .activity-item');
            userCards.forEach((card, index) => {
                card.classList.add('scroll-fade');
                card.style.transitionDelay = `${index * 0.05}s`;
                fadeInObserver.observe(card);
            });

            // Add parallax effect on scroll
            let ticking = false;
            window.addEventListener('scroll', () => {
                if (!ticking) {
                    window.requestAnimationFrame(() => {
                        handleScroll();
                        ticking = false;
                    });
                    ticking = true;
                }
            });

            // Add ripple effect to buttons and cards
            const clickableElements = document.querySelectorAll('.time-filter-btn, .stat-card, .regular-user-card, .podium-card');
            clickableElements.forEach(element => {
                element.style.position = 'relative';
                element.style.overflow = 'hidden';
                element.addEventListener('click', createRipple);
            });

            // Add hover sound effect (optional visual feedback)
            const hoverElements = document.querySelectorAll('.nav-links a, .header-link, .footer-links a');
            hoverElements.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    this.style.transition = 'all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
                });
            });

            // Podium cards entrance animation
            const podiumCards = document.querySelectorAll('.podium-card');
            podiumCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'scale(0.8) translateY(50px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
                    card.style.opacity = '1';
                    card.style.transform = 'scale(1) translateY(0)';
                }, 300 + (index * 200));
            });

            // Add floating animation to trophy icons on hover
            document.querySelectorAll('.trophy-icon').forEach(trophy => {
                trophy.addEventListener('mouseenter', function() {
                    this.style.animation = 'float 1s ease-in-out infinite';
                });
                trophy.addEventListener('mouseleave', function() {
                    this.style.animation = '';
                });
            });

            // Cursor trail effect (subtle)
            let cursorTrail = [];
            const maxTrailLength = 10;

            document.addEventListener('mousemove', (e) => {
                cursorTrail.push({ x: e.clientX, y: e.clientY, time: Date.now() });
                
                if (cursorTrail.length > maxTrailLength) {
                    cursorTrail.shift();
                }
            });

            // Console welcome message with style
            console.log('%c✨ ONVIBES - En Aktif Kullanıcılar ✨', 
                'color: #d2232a; font-size: 20px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);');
            console.log('%cSayfa başarıyla yüklendi! 🚀', 
                'color: #0a8c2f; font-size: 14px; font-weight: bold;');
        });
    </script>
</body>
</html>
