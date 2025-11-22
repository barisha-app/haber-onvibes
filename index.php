<?php
// index.php - API Entegrasyonlu ONVIBES (Animasyonlar Güncellendi)
session_start();
include 'config.php';

$database = new Database();
$db = $database->getConnection();

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Kategori filtresi
$current_category = isset($_GET['category']) ? $_GET['category'] : 'all';
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Kategorileri veritabanından çek
try {
    $categories_query = "SELECT * FROM categories ORDER BY name ASC";
    $categories_stmt = $db->prepare($categories_query);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Gelişmiş API veri çekme fonksiyonu
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

function getWeatherIconClass($icon_code) {
    $icons = [
        '01d' => 'fas fa-sun', '01n' => 'fas fa-moon',
        '02d' => 'fas fa-cloud-sun', '02n' => 'fas fa-cloud-moon',
        '03d' => 'fas fa-cloud', '03n' => 'fas fa-cloud',
        '04d' => 'fas fa-cloud', '04n' => 'fas fa-cloud',
        '09d' => 'fas fa-cloud-rain', '09n' => 'fas fa-cloud-rain',
        '10d' => 'fas fa-cloud-sun-rain', '10n' => 'fas fa-cloud-moon-rain',
        '11d' => 'fas fa-bolt', '11n' => 'fas fa-bolt',
        '13d' => 'fas fa-snowflake', '13n' => 'fas fa-snowflake',
        '50d' => 'fas fa-smog', '50n' => 'fas fa-smog'
    ];
    return $icons[$icon_code] ?? 'fas fa-cloud';
}

function formatWeatherText($flaticon) {
    $translations = [
        'cloudy' => 'Bulutlu',
        'sunny' => 'Güneşli', 
        'day-hail' => 'Sağanak',
        'night-hail' => 'Sağanak',
        'day-rain' => 'Yağmurlu',
        'night-rain' => 'Yağmurlu',
        'day-thunderstorm' => 'Fırtınalı',
        'night-thunderstorm' => 'Fırtınalı',
        'day-snow' => 'Karlı',
        'night-snow' => 'Karlı',
        'day-fog' => 'Sisli',
        'night-fog' => 'Sisli'
    ];
    return $translations[$flaticon] ?? 'Bilinmiyor';
}

// API'lerden veri çekme
$doviz_data = null;
$puan_durumu = null;
$fikstur_data = null;

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

// Süper Lig puan durumu - TAVCAN API
try {
    $xml_data = getAPIData('https://api.tavcan.com/xml/superlig.xml');
    if ($xml_data) {
        $xml = simplexml_load_string($xml_data);
        if ($xml) {
            $puan_durumu = new stdClass();
            $puan_durumu->takim = [];
            
            foreach ($xml->takim as $takim) {
                $formatted_takim = new stdClass();
                $formatted_takim->isim = (string)$takim->adi;
                $formatted_takim->puan = (string)$takim->p;
                $formatted_takim->oynanan = (string)$takim->o;
                $formatted_takim->galibiyet = (string)$takim->g;
                $formatted_takim->beraberlik = (string)$takim->b;
                $formatted_takim->maglubiyet = (string)$takim->m;
                $formatted_takim->atilan = (string)$takim->a;
                $formatted_takim->yenilen = (string)$takim->y;
                $formatted_takim->averaj = (string)$takim->av;
                
                $puan_durumu->takim[] = $formatted_takim;
            }
        } else {
            $puan_durumu = null;
        }
    } else {
        $puan_durumu = null;
    }
} catch (Exception $e) {
    $puan_durumu = null;
}

// Fikstür verisi - TAVCAN XML API
function parseFixtureXML($xml_string) {
    if (!$xml_string) return null;
    
    $xml = simplexml_load_string($xml_string);
    if (!$xml) return null;
    
    $matches = [];
    
    foreach ($xml->takim as $takim) {
        $tarih_text = (string)$takim->tarih;
        $clean_text = preg_replace('/\s+/', ' ', trim($tarih_text));
        
        if (preg_match('/-\s*(\d{1,2})\s+Kasım\s+(\d{4}),?\s+(\d{2}\.\d{2})/', $clean_text, $date_parts)) {
            $day = $date_parts[1];
            $year = $date_parts[2];
            $time = $date_parts[3];
            
            $after_date = substr($clean_text, strpos($clean_text, $time) + strlen($time));
            $after_date = trim($after_date);
            
            if (preg_match('/^([A-Za-zıİĞğÜüŞşÖöçÇ\s\.\-]+?)\s+(\d+\-\d+)/', $after_date, $team_score_parts)) {
                $team1 = trim($team_score_parts[1]);
                $score = trim($team_score_parts[2]);
                
                $after_half_time = $after_date;
                if (($pos = strpos($after_half_time, 'İlk Yarı')) !== false) {
                    $after_half_time = trim(substr($after_half_time, $pos + strlen('İlk Yarı:')));
                    $after_half_time = preg_replace('/^\s*\d+\-\d+\s*/', '', $after_half_time);
                    
                    if (preg_match('/^([A-Za-zıİĞğÜüŞşÖöçÇ\s\.\-]+?)(?:\s|$)/', $after_half_time, $team2_parts)) {
                        $team2 = trim($team2_parts[1]);
                        
                        if (strlen($team1) > 3 && strlen($team2) > 3 && 
                            !preg_match('/^\d+$/', $team1) && !preg_match('/^\d+$/', $team2)) {
                            
                            $match_date = "{$year}-11-{$day}";
                            $formatted_date = $day . " Kasım " . $year . " " . str_replace('.', ':', $time);
                            
                            $match_info = [
                                'tarih' => $formatted_date,
                                'match_date' => $match_date,
                                'evSahibi' => $team1,
                                'deplasman' => $team2
                            ];
                            
                            if (preg_match('/\d+\-\d+/', $score)) {
                                $match_info['skor'] = $score;
                                $match_info['completed'] = true;
                            } else {
                                $match_info['completed'] = false;
                            }
                            
                            $matches[] = $match_info;
                        }
                    }
                }
            }
        }
    }
    
    usort($matches, function($a, $b) {
        return strcmp($b['match_date'], $a['match_date']);
    });
    
    return ['maclar' => $matches];
}

try {
    $fikstur_xml = getAPIData('https://api.tavcan.com/xml/fikstur.xml');
    if ($fikstur_xml) {
        $fikstur_data = parseFixtureXML($fikstur_xml);
    } else {
        $fikstur_data = null;
    }
} catch (Exception $e) {
    $fikstur_data = null;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HABER | ONVIBES - Son Dakika Haberler, Güncel Haberler</title>
    <meta name="description" content="Haberler ve güncel gelişmeler, gündemden ekonomiye son dakika haberler Türkiye'nin en çok takip edilen flaş haber sitesi ONVIBES'te.">
    
    <!-- AdSense -->
    <meta name="google-adsense-account" content="ca-pub-2853730635148966">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2853730635148966" crossorigin="anonymous"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            background-attachment: fixed;
        }

        .dark-mode body {
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

        /* Mobil Arama - Enhanced */
        .mobile-search-container {
            display: none;
            padding: 12px 15px;
            background: var(--light);
            border-bottom: 1px solid var(--border);
        }

        .mobile-search-box {
            display: flex;
            background: var(--surface);
            border: 2px solid transparent;
            border-radius: 25px;
            overflow: hidden;
            transition: border-color 0.3s;
        }

        .mobile-search-box:focus-within {
            border-color: var(--red);
        }

        .mobile-search-input {
            flex: 1;
            border: none;
            padding: 12px 18px;
            background: transparent;
            color: var(--text);
            outline: none;
            font-size: 14px;
        }

        .mobile-search-button {
            background: var(--red);
            border: none;
            color: white;
            padding: 0 18px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .mobile-search-button:hover {
            background: #b81d24;
            transform: scale(1.05);
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
            grid-template-columns: 250px 1fr 300px;
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

        /* Köşe Yazıları Slider - Premium */
        .kose-yazilari-slider {
            position: relative;
            height: 400px;
            overflow: hidden;
            margin-bottom: 25px;
            border-radius: 12px;
            background: var(--surface);
            animation: fadeInUp 0.8s ease-out;
        }

        .dark-mode .kose-yazilari-slider {
            background: #0f0f0f;
        }

        .kose-yazisi-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: all 0.6s cubic-bezier(0.25, 0.8, 0.25, 1);
            display: flex;
            flex-direction: column;
            transform: translateX(30px);
        }

        .kose-yazisi-slide.active {
            opacity: 1;
            transform: translateX(0);
        }

        .kose-yazisi-content {
            flex: 1;
            background: linear-gradient(135deg, var(--surface) 0%, rgba(255,255,255,0.5) 100%);
            padding: 18px;
            border-radius: 12px;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .kose-yazisi-content:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 8px 25px rgba(210, 35, 42, 0.2);
        }

        .dark-mode .kose-yazisi-content {
            background: #0f0f0f;
        }

        .kose-yazisi-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--red), var(--orange));
        }

        .kose-yazisi-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--dark);
            line-height: 1.4;
            letter-spacing: 0.3px;
        }

        .dark-mode .kose-yazisi-title {
            color: var(--text);
        }

        .kose-yazisi-excerpt {
            font-size: 13px;
            color: var(--gray);
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .kose-yazisi-author {
            font-size: 12px;
            color: var(--red);
            font-weight: 700;
        }

        .kose-yazisi-date {
            font-size: 11px;
            color: var(--gray);
            font-weight: 500;
        }

        .kose-yazisi-nav {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
            gap: 8px;
        }

        .kose-yazisi-nav button {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            overflow: hidden;
        }

        .kose-yazisi-nav button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .kose-yazisi-nav button:hover::before {
            left: 100%;
        }

        .kose-yazisi-nav button:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 20px rgba(210, 35, 42, 0.4);
        }
        
        .kose-yazisi-nav button:active {
            transform: translateY(-1px) scale(0.98);
        }

        /* Reklam Panosu - Premium */
        .sidebar-ad {
            background: linear-gradient(135deg, var(--light) 0%, rgba(255,255,255,0.8) 100%);
            border: 2px solid var(--red);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        .dark-mode .sidebar-ad {
            background: #0a0a0a;
        }

        .sidebar-ad::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(210, 35, 42, 0.03), transparent);
            animation: rotate 6s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .ad-label {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 10px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .ad-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            position: relative;
            z-index: 1;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .ad-content:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        /* Hava Durumu - Premium */
        .hava-durumu {
            background: var(--light);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }

        .dark-mode .hava-durumu {
            background: #0a0a0a;
        }

        .hava-durumu::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--blue), #74b9ff);
            border-radius: 16px 16px 0 0;
        }

        /* Süper Lig Tablosu - Premium */
        .lig-tablosu {
            background: var(--light);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }

        .dark-mode .lig-tablosu {
            background: #0a0a0a;
        }

        .lig-tablosu::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--green), #00b894);
            border-radius: 16px 16px 0 0;
        }

        .takim-siralamasi {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        .takim-siralamasi th {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 6px 2px;
            text-align: center;
            font-weight: 700;
            border-radius: 6px 6px 0 0;
            font-size: 8px;
        }

        .takim-siralamasi td {
            padding: 4px 2px;
            text-align: center;
            border-bottom: 1px solid var(--border);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            font-size: 8px;
            line-height: 1.2;
        }

        .dark-mode .takim-siralamasi td {
            color: var(--text);
        }

        .takim-siralamasi tr:hover {
            background: var(--surface);
            transform: scale(1.02);
        }

        .takim-adi {
            text-align: left !important;
            font-weight: 700;
            font-size: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 60px;
        }

        .siralama-1 { 
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            box-shadow: inset 3px 0 0 #28a745;
        }
        .siralama-2 { 
            background: linear-gradient(135deg, #f0f8ff 0%, #d1ecf1 100%);
            box-shadow: inset 3px 0 0 #17a2b8;
        }
        .siralama-3 { 
            background: linear-gradient(135deg, #fff8e1 0%, #ffeaa7 100%);
            box-shadow: inset 3px 0 0 #ffc107;
        }
        .siralama-4 { 
            background: linear-gradient(135deg, #fff0f0 0%, #f8d7da 100%);
            box-shadow: inset 3px 0 0 #dc3545;
        }

        .dark-mode .siralama-1 { 
            background: linear-gradient(135deg, #1a331a 0%, #2d5a2d 100%);
            box-shadow: inset 3px 0 0 #28a745;
        }
        .dark-mode .siralama-2 { 
            background: linear-gradient(135deg, #1a1f33 0%, #2d4059 100%);
            box-shadow: inset 3px 0 0 #17a2b8;
        }
        .dark-mode .siralama-3 { 
            background: linear-gradient(135deg, #332b1a 0%, #5a4d3a 100%);
            box-shadow: inset 3px 0 0 #ffc107;
        }
        .dark-mode .siralama-4 { 
            background: linear-gradient(135deg, #331a1a 0%, #5a2d2d 100%);
            box-shadow: inset 3px 0 0 #dc3545;
        }

        /* Fikstür - Premium */
        .fikstur {
            background: var(--light);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out 0.8s both;
        }

        .dark-mode .fikstur {
            background: #0a0a0a;
        }

        .fikstur::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--green) 0%, #00b894);
            border-radius: 16px 16px 0 0;
        }

        /* Yeni Fikstür Kartları - Modern Format */
        .fikstur-kartlari {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-top: 15px;
        }

        .fikstur-kart {
            background: linear-gradient(135deg, var(--surface) 0%, rgba(255,255,255,0.1) 100%);
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .dark-mode .fikstur-kart {
            background: #0f0f0f;
        }

        .fikstur-kart::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s ease;
        }

        .fikstur-kart:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: var(--orange);
        }

        .fikstur-kart:hover::before {
            left: 100%;
        }

        .fikstur-tarih {
            font-size: 10px;
            color: var(--gray);
            font-weight: 600;
            margin-bottom: 6px;
            text-align: center;
            padding-bottom: 3px;
            border-bottom: 1px solid var(--border);
        }

        .mac-satiri {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 2px 0;
        }

        .ev-sahibi-satir, .deplasman-satir {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            font-weight: 600;
            padding: 1px 0;
        }

        .ev-sahibi-satir {
            color: var(--text);
        }

        .deplasman-satir {
            color: var(--text);
        }

        .mac-skoru {
            color: var(--red);
            font-weight: 700;
            font-size: 13px;
            min-width: 25px;
            text-align: right;
        }

        .vs-skoru {
            color: var(--gray);
            font-weight: 700;
            font-size: 11px;
            min-width: 25px;
            text-align: right;
        }

        .ayirici {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0.5px 0;
        }

        .ayirici-cizgi {
            width: 20px;
            height: 1px;
            background: var(--gray);
            opacity: 0.4;
        }

        /* Orta Bölüm - Ana Slider - Premium */
        .main-middle {
            flex: 1;
        }

        .slider-container {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0,0,0,0.15), 0 6px 20px rgba(0,0,0,0.1);
            animation: fadeInUp 0.8s ease-out;
        }

        .slider-wrapper {
            position: relative;
            height: 380px;
        }

        .slider-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: all 0.8s cubic-bezier(0.25, 0.8, 0.25, 1);
            background-size: cover;
            background-position: center;
            background-color: #e0e0e0;
            transform: scale(1.1);
        }

        .slider-slide.active {
            opacity: 1;
            transform: scale(1);
        }

        .slider-link {
            text-decoration: none;
            color: white;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: flex-end;
        }

        .slider-overlay {
            background: linear-gradient(transparent, rgba(0,0,0,0.9));
            padding: 25px;
            width: 100%;
            position: relative;
        }

        .slider-overlay::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(210, 35, 42, 0.1) 0%, transparent 50%, rgba(0,0,0,0.2) 100%);
            pointer-events: none;
        }

        .slider-category {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 1;
            box-shadow: 0 2px 10px rgba(210, 35, 42, 0.3);
        }

        .slider-title {
            font-size: 20px;
            font-weight: 800;
            line-height: 1.4;
            text-shadow: 0 2px 4px rgba(0,0,0,0.8);
            position: relative;
            z-index: 1;
            letter-spacing: 0.5px;
        }

        /* Slider Kontrolleri - Premium */
        .slider-controls {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            transform: translateY(-50%);
            display: flex;
            justify-content: space-between;
            padding: 0 15px;
            pointer-events: none;
        }

        .slider-controls button {
            background: rgba(0,0,0,0.6);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            pointer-events: all;
            backdrop-filter: blur(10px);
        }

        .slider-controls button:hover {
            background: rgba(0,0,0,0.9);
            transform: scale(1.1) rotate(10deg);
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .slider-controls button:active {
            transform: scale(0.95) rotate(0deg);
        }

        .slider-indicators {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
        }

        .slider-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0;
            position: relative;
        }

        .slider-indicator::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: white;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.3s, height 0.3s;
        }

        .slider-indicator.active::before,
        .slider-indicator:hover::before {
            width: 100%;
            height: 100%;
        }

        .slider-counter {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            backdrop-filter: blur(10px);
            letter-spacing: 0.5px;
        }

        /* Sağ Sidebar - Premium */
        .sidebar-right {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .sidebar-section {
            background: var(--light);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            animation: fadeInRight 0.8s ease-out;
        }

        .dark-mode .sidebar-section {
            background: #0a0a0a;
        }

        .sidebar-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--orange) 0%, #fdcb6e);
            border-radius: 16px 16px 0 0;
        }

        .user-news-item,
        .popular-news-item {
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            overflow: hidden;
        }

        .user-news-item::before,
        .popular-news-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .user-news-item:hover::before,
        .popular-news-item:hover::before {
            left: 100%;
        }

        .user-news-item:hover,
        .popular-news-item:hover {
            background: var(--surface);
            transform: translateX(8px) scale(1.02);
            padding-left: 10px;
        }

        .user-news-item:last-child,
        .popular-news-item:last-child {
            border-bottom: none;
        }

        .user-news-item a,
        .popular-news-item a {
            text-decoration: none;
            color: var(--text);
            display: block;
        }

        .user-news-title,
        .popular-news-title {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.4;
            margin-bottom: 6px;
            color: var(--dark);
        }

        .dark-mode .user-news-title,
        .dark-mode .popular-news-title {
            color: var(--text);
        }

        .user-news-meta,
        .popular-news-meta {
            font-size: 11px;
            color: var(--gray);
            display: flex;
            justify-content: space-between;
            font-weight: 500;
        }

        /* Arama Çubuğu - Premium */
        .search-container {
            position: relative;
            margin: 8px 0;
            display: block;
        }

        .search-box {
            display: flex;
            background: var(--light);
            border: 2px solid transparent;
            border-radius: 25px;
            overflow: hidden;
            min-width: 250px;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .search-box:focus-within {
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(210, 35, 42, 0.1);
            transform: scale(1.05);
        }

        .search-input {
            flex: 1;
            border: none;
            padding: 10px 18px;
            background: transparent;
            color: var(--text);
            outline: none;
            font-size: 14px;
        }

        .search-input::placeholder {
            color: var(--gray);
            font-weight: 500;
        }

        .search-button {
            background: var(--red);
            border: none;
            color: white;
            padding: 0 18px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .search-button:hover {
            background: #b81d24;
            transform: scale(1.1) rotate(5deg);
        }

        /* Grid News - Premium */
        .grid-news {
            margin: 30px 0;
        }

        .section-title {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--red);
            color: var(--dark);
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            animation: fadeInUp 0.8s ease-out;
        }

        .dark-mode .section-title {
            color: var(--text);
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--orange);
            border-radius: 2px;
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .news-card {
            background: var(--light);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            cursor: pointer;
            position: relative;
            border: 1px solid transparent;
            animation: fadeInUp 0.8s ease-out;
        }

        .dark-mode .news-card {
            background: #0a0a0a;
        }

        .news-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--red), var(--orange), var(--red));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .news-card:hover::before {
            opacity: 1;
        }

        .news-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 10px 30px rgba(0,0,0,0.1);
            border-color: rgba(210, 35, 42, 0.2);
        }

        .news-image {
            height: 180px;
            background-size: cover;
            background-position: center;
            background-color: #e0e0e0;
            position: relative;
            overflow: hidden;
        }

        .news-image::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }

        .news-card:hover .news-image::after {
            transform: translateX(100%);
        }

        .news-content {
            padding: 18px;
        }

        .news-category {
            display: inline-block;
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(210, 35, 42, 0.3);
            transition: all 0.3s;
        }
        
        .news-card:hover .news-category {
            transform: scale(1.1);
        }

        .news-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 10px;
            line-height: 1.4;
            color: var(--dark);
            letter-spacing: 0.3px;
        }

        .dark-mode .news-title {
            color: var(--text);
        }

        .news-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--gray);
            font-weight: 500;
        }

        /* Footer - Premium */
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
            grid-template-columns: repeat(4, 1fr);
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

        /* Responsive - Enhanced */
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .sidebar-left,
            .sidebar-right {
                display: none;
            }

            .header-links {
                gap: 10px;
            }

            .header-link {
                font-size: 12px;
                padding: 4px 8px;
            }
        }

        @media (max-width: 768px) {
            .top-bar .container {
                flex-direction: column;
                gap: 10px;
            }

            .header-links {
                order: 3;
                width: 100%;
                justify-content: center;
                margin-top: 8px;
            }

            .search-container {
                display: none;
            }

            .mobile-search-container {
                display: block;
            }

            .nav-links {
                padding: 0 10px;
            }

            .nav-links a {
                padding: 12px 15px;
                font-size: 12px;
            }

            .nav-info {
                display: none;
            }

            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .currency-bar ul {
                justify-content: flex-start;
            }

            .slider-wrapper {
                height: 280px;
            }
            
            .slider-title {
                font-size: 18px;
            }

            .slider-overlay {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .footer-content {
                grid-template-columns: 1fr;
            }
            
            .news-grid {
                grid-template-columns: 1fr;
            }
            
            .slider-wrapper {
                height: 220px;
            }
            
            .slider-title {
                font-size: 16px;
            }

            .header-links {
                gap: 5px;
            }

            .header-link {
                font-size: 11px;
                padding: 3px 6px;
            }

            .header-link span {
                display: none;
            }

            .header-link i {
                margin-right: 0;
                font-size: 14px;
            }

            .top-links button span,
            .top-links a span {
                display: none;
            }

            .logo-text {
                font-size: 20px;
            }

            .nav-links a {
                padding: 10px 12px;
                font-size: 11px;
            }
        }

        /* Loading Animation */
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .loading {
            animation: pulse 1.5s ease-in-out infinite;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--surface);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--red), #b81d24);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #b81d24, #9e1a20);
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
        
        /* Ripple Effect */
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

        /* Smooth transitions for all interactive elements */
        a, button, .card, .news-card, .user-news-item, .popular-news-item {
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
        
        /* Page Load Animation */
        body {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <!-- Header -->
    <header id="header">
        <div class="main-menu">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="container">
                    <h1 class="logo">
                        <a href="index.php" title="ONVIBES">
                            <span class="logo-text">HABER|ONVIBES</span>
                        </a>
                    </h1>

                    <!-- Header Linkler - Enhanced -->
                    <div class="header-links">
                        <a href="Veri/ilan.php" class="header-link">
                            <i class="fas fa-bullhorn"></i>
                            <span>İlanlar</span>
                        </a>
                        <a href="Veri/haberler.php" class="header-link">
                            <i class="fas fa-newspaper"></i>
                            <span>Haberler</span>
                        </a>
                        <a href="kose-yazilari.php" class="header-link">
                            <i class="fas fa-pen-fancy"></i>
                            <span>Analizler</span>
                        </a>
                        <a href="eek.php" class="header-link">
                            <i class="fas fa-users"></i>
                            <span>En Aktif Kullanıcılar</span>
                        </a>
                    </div>

                    <!-- Right Nav -->
                    <div class="right-nav">
                        <!-- Masaüstü Arama Çubuğu -->
                        <div class="search-container">
                            <div class="search-box">
                                <input type="text" class="search-input" placeholder="Haber ara...">
                                <button class="search-button">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Top Links -->
                        <div class="top-links">
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="toggle_theme" class="theme-toggle-btn">
                                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                                </button>
                            </form>
                            <?php if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                                <a href="profil.php" class="user-profile-btn">
                                    <i class="fas fa-user"></i>
                                    <span>Profil</span>
                                </a>
                            <?php else: ?>
                                <a href="Giris/login.php" class="user-profile-btn">
                                    <i class="fas fa-sign-in-alt"></i>
                                    <span>Üyelik</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobil Arama Çubuğu -->
            <div class="mobile-search-container">
                <div class="mobile-search-box">
                    <input type="text" class="mobile-search-input" placeholder="Haber ara...">
                    <button class="mobile-search-button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>

            <!-- Navigation -->
            <nav id="mainnav">
                <div class="container">
                    <ul class="nav-links">
                        <li><a href="index.php" class="<?php echo $current_category == 'all' ? 'active' : ''; ?>">Ana Sayfa</a></li>
                        <?php foreach($categories as $cat): ?>
                            <li>
                                <a href="index.php?category=<?php echo $cat['id']; ?>" 
                                   class="<?php echo $current_category == $cat['id'] ? 'active' : ''; ?>">
                                    #<?php echo htmlspecialchars($cat['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <!-- Nav Info -->
                    <div class="nav-info">
                        <div class="current-time">
                            <i class="fas fa-clock"></i>
                            <span id="current-time">--:--:--</span>
                        </div>
                        <!-- Hava Durumu (Rotasyonlu) -->
                        <div class="weather-info" id="nav-weather">
                            <?php
                            if (!empty($quick_weather_data)) {
                                $first_city = array_keys($quick_weather_data)[0];
                                $current_weather = $quick_weather_data[$first_city];
                                $current_city_name = ucfirst($first_city);
                                $weather_icon = getWeatherIconClass($current_weather['icon']);
                                echo "<span class='city-name'>{$current_city_name}</span>";
                                echo "<i class='{$weather_icon} weather-icon'></i>";
                                echo "<span class='temperature'>{$current_weather['max']}°C</span>";
                            } else {
                                echo "<span class='city-name'>Diyarbakır</span>";
                                echo "<i class='fas fa-cloud weather-icon'></i>";
                                echo "<span class='temperature'>--°C</span>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Döviz Bar -->
    <div class="doviz-bar">
        <div class="container">
            <div class="currency-bar">
                <ul>
                    <?php
                    // Dolar
                    if ($doviz_data && isset($doviz_data['piyasalar']['dolar'])) {
                        $dolar = $doviz_data['piyasalar']['dolar'];
                        $dolar_yon = $dolar['degisim'] >= 0 ? 'up' : 'down';
                        $dolar_degisim = $dolar['degisim'] >= 0 ? '+'.$dolar['degisim'] : $dolar['degisim'];
                        echo "<li><span class='currency-symbol'>$</span> <span class='{$dolar_yon}'></span><span class='amount'>{$dolar['alis']}</span><span class='change'>%{$dolar_degisim}</span></li>";
                    } else {
                        echo "<li><span class='currency-symbol'>$</span> <span class='down'></span><span class='amount'>0.00</span><span class='change'>%0.00</span></li>";
                    }
                    
                    // Euro
                    if ($doviz_data && isset($doviz_data['piyasalar']['euro'])) {
                        $euro = $doviz_data['piyasalar']['euro'];
                        $euro_yon = $euro['degisim'] >= 0 ? 'up' : 'down';
                        $euro_degisim = $euro['degisim'] >= 0 ? '+'.$euro['degisim'] : $euro['degisim'];
                        echo "<li><span class='currency-symbol'>€</span> <span class='{$euro_yon}'></span><span class='amount'>{$euro['alis']}</span><span class='change'>%{$euro_degisim}</span></li>";
                    } else {
                        echo "<li><span class='currency-symbol'>€</span> <span class='down'></span><span class='amount'>0.00</span><span class='change'>%0.00</span></li>";
                    }
                    
                    // Sterlin
                    if ($doviz_data && isset($doviz_data['piyasalar']['sterlin'])) {
                        $sterlin = $doviz_data['piyasalar']['sterlin'];
                        $sterlin_yon = $sterlin['degisim'] >= 0 ? 'up' : 'down';
                        $sterlin_degisim = $sterlin['degisim'] >= 0 ? '+'.$sterlin['degisim'] : $sterlin['degisim'];
                        echo "<li><span class='currency-symbol'>£</span> <span class='{$sterlin_yon}'></span><span class='amount'>{$sterlin['alis']}</span><span class='change'>%{$sterlin_degisim}</span></li>";
                    } else {
                        echo "<li><span class='currency-symbol'>£</span> <span class='down'></span><span class='amount'>0.00</span><span class='change'>%0.00</span></li>";
                    }
                    
                    // Altın
                    if ($doviz_data && isset($doviz_data['piyasalar']['altin'])) {
                        $altin = $doviz_data['piyasalar']['altin'];
                        $altin_yon = $altin['degisim'] >= 0 ? 'up' : 'down';
                        $altin_degisim = $altin['degisim'] >= 0 ? '+'.$altin['degisim'] : $altin['degisim'];
                        echo "<li><span class='currency-symbol'>🪙</span> <span class='{$altin_yon}'></span><span class='amount'>{$altin['alis']}</span><span class='change'>%{$altin_degisim}</span></li>";
                    } else {
                        echo "<li><span class='currency-symbol'>🪙</span> <span class='down'></span><span class='amount'>0.00</span><span class='change'>%0.00</span></li>";
                    }
                    
                    // Gümüş
                    if ($doviz_data && isset($doviz_data['piyasalar']['gumus'])) {
                        $gumus = $doviz_data['piyasalar']['gumus'];
                        $gumus_yon = $gumus['degisim'] >= 0 ? 'up' : 'down';
                        $gumus_degisim = $gumus['degisim'] >= 0 ? '+'.$gumus['degisim'] : $gumus['degisim'];
                        echo "<li><span class='currency-symbol'>⚪</span> <span class='{$gumus_yon}'></span><span class='amount'>{$gumus['alis']}</span><span class='change'>%{$gumus_degisim}</span></li>";
                    } else {
                        echo "<li><span class='currency-symbol'>⚪</span> <span class='down'></span><span class='amount'>0.00</span><span class='change'>%0.00</span></li>";
                    }
                    
                    // BIST 100
                    if ($doviz_data && isset($doviz_data['piyasalar']['bist'])) {
                        $bist = $doviz_data['piyasalar']['bist'];
                        $bist_yon = $bist['degisim'] >= 0 ? 'up' : 'down';
                        $bist_degisim = $bist['degisim'] >= 0 ? '+'.$bist['degisim'] : $bist['degisim'];
                        echo "<li><span class='currency-symbol'>📈</span> <span class='{$bist_yon}'></span><span class='amount'>{$bist['deger']}</span><span class='change'>%{$bist_degisim}</span></li>";
                    } else {
                        echo "<li><span class='currency-symbol'>📈</span> <span class='down'></span><span class='amount'>0.00</span><span class='change'>%0.00</span></li>";
                    }
                    
                    // Bitcoin
                    if ($doviz_data && isset($doviz_data['piyasalar']['bitcoin'])) {
                        $bitcoin = $doviz_data['piyasalar']['bitcoin'];
                        $bitcoin_yon = $bitcoin['degisim'] >= 0 ? 'up' : 'down';
                        $bitcoin_degisim = $bitcoin['degisim'] >= 0 ? '+'.$bitcoin['degisim'] : $bitcoin['degisim'];
                        $bitcoin_fiyat = number_format($bitcoin['deger'], 0, ',', '.');
                        echo "<li><span class='currency-symbol'>₿</span> <span class='{$bitcoin_yon}'></span><span class='amount'>{$bitcoin_fiyat}</span><span class='change'>%{$bitcoin_degisim}</span></li>";
                    } else {
                        echo "<li><span class='currency-symbol'>₿</span> <span class='down'></span><span class='amount'>0</span><span class='change'>%0.00</span></li>";
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Ana İçerik -->
    <div class="container">
        <div class="main-content">
            <!-- Sol Sidebar - Köşe Yazıları ve Ekstralar -->
            <aside class="sidebar-left">
                <!-- Köşe Yazıları -->
                <h3 class="sidebar-title">Köşe Yazıları</h3>
                <div class="kose-yazilari-slider">
                    <?php
                    if($db) {
                        try {
                            $query = "SELECT ky.id, ky.title, ky.content, ky.created_at, 
                                     ky.author_name, ky.author_avatar, ky.summary
                                     FROM kose_yazisi ky 
                                     WHERE ky.status='approved' 
                                     ORDER BY ky.created_at DESC LIMIT 10";
                            $stmt = $db->prepare($query);
                            $stmt->execute();
                            
                            $slide_index = 0;
                            if($stmt->rowCount() > 0) {
                                while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $excerpt = strlen($row['content']) > 120 ? substr($row['content'], 0, 120) . '...' : $row['content'];
                                    $date = date('d.m.Y', strtotime($row['created_at']));
                                    $active_class = $slide_index === 0 ? 'active' : '';
                                    
                                    echo "
                                    <div class='kose-yazisi-slide {$active_class}' data-index='{$slide_index}'>
                                        <div class='kose-yazisi-content'>
                                            <h4 class='kose-yazisi-title'>" . htmlspecialchars($row['title']) . "</h4>
                                            <p class='kose-yazisi-excerpt'>" . htmlspecialchars($excerpt) . "</p>
                                            <div class='kose-yazisi-author'>" . htmlspecialchars($row['author_name']) . "</div>
                                            <div class='kose-yazisi-date'>{$date}</div>
                                        </div>
                                        <div class='kose-yazisi-nav'>
                                            <button class='prev-yazi' onclick='prevKoseYazisi()'><i class='fas fa-chevron-left'></i> Önceki</button>
                                            <button class='next-yazi' onclick='nextKoseYazisi()'>Sonraki <i class='fas fa-chevron-right'></i></button>
                                        </div>
                                    </div>";
                                    $slide_index++;
                                }
                            } else {
                                echo "
                                <div class='kose-yazisi-slide active'>
                                    <div class='kose-yazisi-content'>
                                        <h4 class='kose-yazisi-title'>Henüz Köşe Yazısı Yok</h4>
                                        <p class='kose-yazisi-excerpt'>Köşe yazıları sistemi aktif değil.</p>
                                        <div class='kose-yazisi-author'>Sistem</div>
                                        <div class='kose-yazisi-date'>" . date('d.m.Y') . "</div>
                                    </div>
                                </div>";
                            }
                        } catch (PDOException $e) {
                            echo "
                            <div class='kose-yazisi-slide active'>
                                <div class='kose-yazisi-content'>
                                    <h4 class='kose-yazisi-title'>Veritabanı Hatası</h4>
                                    <p class='kose-yazisi-excerpt'>Köşe yazıları yüklenemedi.</p>
                                    <div class='kose-yazisi-author'>Hata</div>
                                    <div class='kose-yazisi-date'>" . date('d.m.Y') . "</div>
                                </div>
                            </div>";
                        }
                    }
                    ?>
                </div>

                <!-- Reklam Panosu -->
                <div class="sidebar-ad">
                    <div class="ad-label">Reklam</div>
                    <div class="ad-content">
                        <i class="fas fa-ad"></i><br>
                        REKLAM ALANI<br>
                        <small>300x250</small>
                    </div>
                </div>

                <!-- Hava Durumu -->
                <div class="hava-durumu">
                    <h3 class="sidebar-title">Hava Durumu</h3>
                    
                    <!-- Şehir Seçimi -->
                    <div style="margin-bottom: 15px;">
                        <label for="sidebar-city-select" style="font-size: 12px; color: var(--gray); display: block; margin-bottom: 5px;">Şehir Seçin</label>
                        <select id="sidebar-city-select" onchange="updateSidebarWeather()" style="width: 100%; padding: 8px; border-radius: 8px; border: 1px solid var(--border); background: var(--light); color: var(--text); font-size: 12px;">
                            <option value="">-- Şehir Seçin --</option>
                            <option value="adana">Adana</option>
                            <option value="adiyaman">Adıyaman</option>
                            <option value="afyon">Afyon</option>
                            <option value="agri">Ağrı</option>
                            <option value="aksaray">Aksaray</option>
                            <option value="amasya">Amasya</option>
                            <option value="ankara">Ankara</option>
                            <option value="antalya">Antalya</option>
                            <option value="ardahan">Ardahan</option>
                            <option value="artvin">Artvin</option>
                            <option value="aydin">Aydın</option>
                            <option value="balikesir">Balıkesir</option>
                            <option value="bartin">Bartın</option>
                            <option value="batman">Batman</option>
                            <option value="bayburt">Bayburt</option>
                            <option value="bilecik">Bilecik</option>
                            <option value="bingol">Bingöl</option>
                            <option value="bitlis">Bitlis</option>
                            <option value="bolu">Bolu</option>
                            <option value="burdur">Burdur</option>
                            <option value="bursa">Bursa</option>
                            <option value="canakkale">Çanakkale</option>
                            <option value="cankiri">Çankırı</option>
                            <option value="corum">Çorum</option>
                            <option value="denizli">Denizli</option>
                            <option value="diyarbakir">Diyarbakır</option>
                            <option value="duzce">Düzce</option>
                            <option value="edirne">Edirne</option>
                            <option value="elazig">Elazığ</option>
                            <option value="erzincan">Erzincan</option>
                            <option value="erzurum">Erzurum</option>
                            <option value="eskisehir">Eskişehir</option>
                            <option value="gaziantep">Gaziantep</option>
                            <option value="giresun">Giresun</option>
                            <option value="gumushane">Gümüşhane</option>
                            <option value="hakkari">Hakkari</option>
                            <option value="hatay">Hatay</option>
                            <option value="igdir">Iğdır</option>
                            <option value="isparta">Isparta</option>
                            <option value="istanbul">İstanbul</option>
                            <option value="izmir">İzmir</option>
                            <option value="kahramanmaras">Kahramanmaraş</option>
                            <option value="karabuk">Karabük</option>
                            <option value="karaman">Karaman</option>
                            <option value="kars">Kars</option>
                            <option value="kastamonu">Kastamonu</option>
                            <option value="kayseri">Kayseri</option>
                            <option value="kirikkale">Kırıkkale</option>
                            <option value="kirklareli">Kırklareli</option>
                            <option value="kirsehir">Kırşehir</option>
                            <option value="kilis">Kilis</option>
                            <option value="kocaeli">Kocaeli</option>
                            <option value="konya">Konya</option>
                            <option value="kutahya">Kütahya</option>
                            <option value="malatya">Malatya</option>
                            <option value="manisa">Manisa</option>
                            <option value="mardin">Mardin</option>
                            <option value="mersin">Mersin</option>
                            <option value="mugla">Muğla</option>
                            <option value="mus">Muş</option>
                            <option value="nevsehir">Nevşehir</option>
                            <option value="nigde">Niğde</option>
                            <option value="ordu">Ordu</option>
                            <option value="osmaniye">Osmaniye</option>
                            <option value="rize">Rize</option>
                            <option value="sakarya">Sakarya</option>
                            <option value="samsun">Samsun</option>
                            <option value="siirt">Siirt</option>
                            <option value="sinop">Sinop</option>
                            <option value="sivas">Sivas</option>
                            <option value="sanliurfa">Şanlıurfa</option>
                            <option value="sirnak">Şırnak</option>
                            <option value="tekirdag">Tekirdağ</option>
                            <option value="tokat">Tokat</option>
                            <option value="trabzon">Trabzon</option>
                            <option value="tunceli">Tunceli</option>
                            <option value="usak">Uşak</option>
                            <option value="van">Van</option>
                            <option value="yozgat">Yozgat</option>
                            <option value="zonguldak">Zonguldak</option>
                        </select>
                    </div>
                    
                    <!-- Hava Durumu Gösterimi -->
                    <div id="sidebar-weather-display">
                        <div style="text-align: center; padding: 20px; color: var(--gray);">
                            <i class="fas fa-cloud-sun" style="font-size: 48px; margin-bottom: 10px; color: var(--blue);"></i>
                            <p style="font-size: 12px;">Yukarıdan bir şehir seçin</p>
                        </div>
                    </div>
                </div>

                <!-- Süper Lig Puan Durumu -->
                <div class="lig-tablosu">
                    <h3 class="sidebar-title">Süper Lig Puan Durumu</h3>
                    <div style="overflow-x: auto; margin-bottom: 10px;">
                    <table class="takim-siralamasi">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Takım</th>
                                <th>P</th>
                                <th>O</th>
                                <th>G</th>
                                <th>B</th>
                                <th>M</th>
                                <th>A</th>
                                <th>Y</th>
                                <th>Av</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($puan_durumu && isset($puan_durumu->takim)) {
                                $counter = 0;
                                foreach ($puan_durumu->takim as $takim) {
                                    $counter++;
                                    $siralama_class = '';
                                    if ($counter <= 4) {
                                        if ($counter == 1) $siralama_class = 'siralama-1';
                                        elseif ($counter == 2) $siralama_class = 'siralama-2';
                                        elseif ($counter == 3) $siralama_class = 'siralama-3';
                                        elseif ($counter == 4) $siralama_class = 'siralama-4';
                                    }
                                    
                                    echo "
                                    <tr class='{$siralama_class}'>
                                        <td style='font-weight: bold;'>".(str_pad($counter, 2, '0', STR_PAD_LEFT))."</td>
                                        <td class='takim-adi'>".substr($takim->isim, 0, 12)."</td>
                                        <td><strong>{$takim->puan}</strong></td>
                                        <td>{$takim->oynanan}</td>
                                        <td>{$takim->galibiyet}</td>
                                        <td>{$takim->beraberlik}</td>
                                        <td>{$takim->maglubiyet}</td>
                                        <td>{$takim->atilan}</td>
                                        <td>{$takim->yenilen}</td>
                                        <td style='color: ".($takim->averaj >= 0 ? 'var(--green)' : 'var(--red)')."'>".$takim->averaj."</td>
                                    </tr>";
                                }
                            } else {
                                echo "
                                <tr>
                                    <td colspan='10' style='text-align: center; padding: 20px; color: var(--gray);'>
                                        <i class='fas fa-wifi'></i><br>
                                        <strong>Puan Durumu API'si:</strong><br>
                                        TAVCAN API'den güncel veriler alınıyor<br>
                                        <small>2025-2026 Sezonu</small>
                                    </td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Fikstür - Yeni Modern Format -->
                <div class="fikstur">
                    <h3 class="sidebar-title">Süper Lig Fikstür ve Sonuçları</h3>
                    <?php
                    if ($fikstur_data && isset($fikstur_data['maclar'])) {
                        $match_count = count($fikstur_data['maclar']);
                        echo "<div style='font-size: 11px; color: var(--gray); margin-bottom: 8px; text-align: right;'>";
                        echo "<i class='fas fa-calendar'></i> Toplam $match_count maç";
                        echo "</div>";
                        
                        echo "<div class='fikstur-kartlari'>";
                        
                        // Sıralamayı tersine çevir: eski maçlar üstte, yeni maçlar altta
                        $maclar = array_reverse($fikstur_data['maclar']);
                        
                        foreach ($maclar as $mac) {
                            echo "<div class='fikstur-kart'>";
                            
                            // Tarih kısa format
                            $tarih_str = $mac['tarih'];
                            if (preg_match('/(\d{1,2})\s+([A-Za-zıİĞğÜüŞşÖöçÇ]+)\s+(\d{4})\s+(\d{1,2}):(\d{2})/', $tarih_str, $matches)) {
                                $gun = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                                $ay_adi = $matches[2];
                                $yil = $matches[3];
                                $saat = $matches[4];
                                $dakika = $matches[5];
                                
                                // Ay isimlerini sayılara çevir
                                $aylar = [
                                    'Ocak' => '01', 'Şubat' => '02', 'Mart' => '03', 'Nisan' => '04',
                                    'Mayıs' => '05', 'Haziran' => '06', 'Temmuz' => '07', 'Ağustos' => '08',
                                    'Eylül' => '09', 'Ekim' => '10', 'Kasım' => '11', 'Aralık' => '12'
                                ];
                                $ay = $aylar[$ay_adi] ?? '01';
                                $kisa_tarih = "$gun.$ay.$yil $saat:$dakika";
                            } else {
                                $kisa_tarih = $tarih_str;
                            }
                            
                            echo "<div class='fikstur-tarih'>$kisa_tarih</div>";
                            
                            if (isset($mac['skor'])) {
                                // Oynanmış maç
                                list($ev_skor, $dep_skor) = explode('-', $mac['skor']);
                                
                                echo "<div class='mac-satiri'>";
                                echo "<div class='ev-sahibi-satir'>";
                                echo "<span>" . htmlspecialchars($mac['evSahibi']) . "</span>";
                                echo "<span class='mac-skoru'>" . trim($ev_skor) . "</span>";
                                echo "</div>";
                                
                                echo "<div class='ayirici'>";
                                echo "<div class='ayirici-cizgi'></div>";
                                echo "</div>";
                                
                                echo "<div class='deplasman-satir'>";
                                echo "<span>" . htmlspecialchars($mac['deplasman']) . "</span>";
                                echo "<span class='mac-skoru'>" . trim($dep_skor) . "</span>";
                                echo "</div>";
                                echo "</div>";
                            } else {
                                // Gelecek maç
                                echo "<div class='mac-satiri'>";
                                echo "<div class='ev-sahibi-satir'>";
                                echo "<span>" . htmlspecialchars($mac['evSahibi']) . "</span>";
                                echo "<span class='vs-skoru'>-</span>";
                                echo "</div>";
                                
                                echo "<div class='ayirici'>";
                                echo "<div class='ayirici-cizgi'></div>";
                                echo "</div>";
                                
                                echo "<div class='deplasman-satir'>";
                                echo "<span>" . htmlspecialchars($mac['deplasman']) . "</span>";
                                echo "<span class='vs-skoru'>-</span>";
                                echo "</div>";
                                echo "</div>";
                            }
                            
                            echo "</div>";
                        }
                        
                        echo "</div>";
                    } else {
                        echo "
                        <div style='text-align: center; padding: 30px; background: var(--surface); border-radius: 8px; color: var(--gray);'>
                            <i class='fas fa-calendar-times' style='font-size: 24px; margin-bottom: 10px; color: var(--red);'></i><br>
                            <strong>Fikstür Verisi Yüklenemedi</strong><br>
                            <small>TAVCAN API'den veri alınamadı veya parse edilemedi</small><br>
                            <small style='color: var(--text); margin-top: 10px; display: block;'>Lütfen sayfayı yenileyin</small>
                        </div>";
                    }
                    ?>
                </div>
            </aside>

            <!-- Orta Bölüm - Ana İçerik -->
            <main class="main-middle">
                <!-- Ana Slider -->
                <section class="slider-container">
                    <div class="slider-wrapper" id="main-slider">
                        <?php
                        if($db) {
                            try {
                                $one_week_ago = date('Y-m-d H:i:s', strtotime('-1 week'));
                                $query = "SELECT n.id, n.title, n.image, c.name as category_name 
                                         FROM news n 
                                         LEFT JOIN categories c ON n.category_id = c.id 
                                         WHERE n.status='approved' AND n.created_at >= :one_week_ago
                                         ORDER BY n.created_at DESC LIMIT 10";
                                $stmt = $db->prepare($query);
                                $stmt->bindParam(':one_week_ago', $one_week_ago);
                                $stmt->execute();
                                
                                $slide_index = 0;
                                if($stmt->rowCount() > 0) {
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $active_class = $slide_index === 0 ? 'active' : '';
                                        $image_url = $row['image'] ?: 'https://picsum.photos/800/400?random=' . $row['id'];
                                        echo "
                                        <div class='slider-slide {$active_class}' data-index='{$slide_index}' style='background-image: url(\"{$image_url}\")'>
                                            <a href='Veri/haber-detay.php?id={$row['id']}' class='slider-link'>
                                                <div class='slider-overlay'>
                                                    <span class='slider-category'>" . htmlspecialchars($row['category_name']) . "</span>
                                                    <h2 class='slider-title'>" . htmlspecialchars($row['title']) . "</h2>
                                                </div>
                                            </a>
                                        </div>";
                                        $slide_index++;
                                    }
                                } else {
                                    echo "
                                    <div class='slider-slide active' style='background-image: url(https://picsum.photos/800/400?random=1)'>
                                        <div class='slider-overlay'>
                                            <span class='slider-category'>Bilgi</span>
                                            <h2 class='slider-title'>Henüz haber bulunmamaktadır</h2>
                                        </div>
                                    </div>";
                                }
                            } catch (PDOException $e) {
                                echo "
                                <div class='slider-slide active' style='background-image: url(https://picsum.photos/800/400?random=1)'>
                                    <div class='slider-overlay'>
                                        <span class='slider-category'>Hata</span>
                                        <h2 class='slider-title'>Haberler yüklenemedi</h2>
                                    </div>
                                </div>";
                            }
                        }
                        ?>
                    </div>
                    
                    <!-- Slider Kontrolleri -->
                    <div class="slider-controls">
                        <button class="slider-prev" onclick="prevSlide()">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="slider-next" onclick="nextSlide()">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    
                    <div class="slider-indicators" id="slider-indicators">
                        <!-- Slider göstergeleri JavaScript ile eklenecek -->
                    </div>
                    
                    <div class="slider-counter" id="slider-counter">
                        <!-- Slider sayacı JavaScript ile eklenecek -->
                    </div>
                </section>

                <!-- Kategori Haberleri -->
                <section class="grid-news" id="category-news">
                    <h2 class="section-title" id="category-title">
                        <?php
                        $category_titles = [
                            'all' => 'Öne Çıkan Haberler',
                            '1' => 'Gündem Haberleri',
                            '2' => 'Spor Haberleri', 
                            '3' => 'Magazin Haberleri',
                            '4' => 'Teknoloji Haberleri',
                            '5' => 'Ekonomi Haberleri',
                            '6' => 'Sağlık Haberleri',
                            '7' => 'Dünya Haberleri'
                        ];
                        echo $category_titles[$current_category] ?? 'Haberler';
                        ?>
                    </h2>
                    
                    <!-- Advertisement -->
                    <div class="ad-container">
                        <div class="ad-slot">
                            <ins class="adsbygoogle"
                                 style="display:block"
                                 data-ad-client="ca-pub-2853730635148966"
                                 data-ad-slot="1234567890"
                                 data-ad-format="auto"
                                 data-full-width-responsive="true"></ins>
                            <script>
                                 (adsbygoogle = window.adsbygoogle || []).push({});
                            </script>
                        </div>
                    </div>

                    <div class="news-grid" id="news-container">
                        <?php
                        if($db) {
                            try {
                                $query = "SELECT n.id, n.title, n.summary, n.image, c.name as category_name, 
                                         n.view_count, n.created_at 
                                         FROM news n 
                                         LEFT JOIN categories c ON n.category_id = c.id 
                                         WHERE n.status='approved'";
                                
                                if ($current_category != 'all') {
                                    $query .= " AND n.category_id = :category_id";
                                }
                                
                                $query .= " ORDER BY n.created_at DESC LIMIT 12";
                                
                                $stmt = $db->prepare($query);
                                
                                if ($current_category != 'all') {
                                    $stmt->bindParam(':category_id', $current_category);
                                }
                                
                                $stmt->execute();
                                
                                if($stmt->rowCount() > 0) {
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $time_ago = time_elapsed_string($row['created_at']);
                                        $image_url = $row['image'] ?: 'https://picsum.photos/400/200?random=' . $row['id'];
                                        echo "
                                        <div class='news-card' onclick=\"window.location.href='Veri/haber-detay.php?id={$row['id']}'\">
                                            <div class='news-image' style='background-image: url(\"{$image_url}\")'></div>
                                            <div class='news-content'>
                                                <span class='news-category'>" . htmlspecialchars($row['category_name']) . "</span>
                                                <h3 class='news-title'>" . htmlspecialchars($row['title']) . "</h3>
                                                <div class='news-meta'>
                                                    <span><i class='far fa-clock'></i> {$time_ago}</span>
                                                    <span><i class='far fa-eye'></i> " . number_format($row['view_count']) . "</span>
                                                </div>
                                            </div>
                                        </div>";
                                    }
                                } else {
                                    echo "<p style='text-align: center; padding: 40px; color: var(--gray);'>Bu kategoride henüz haber bulunmamaktadır.</p>";
                                }
                            } catch (PDOException $e) {
                                echo "<p style='text-align: center; padding: 40px; color: var(--red);'>Haberler yüklenirken bir hata oluştu.</p>";
                            }
                        }
                        ?>
                    </div>
                </section>
            </main>

            <!-- Sağ Sidebar -->
            <aside class="sidebar-right">
                <!-- Kullanıcı Haberleri -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">Kullanıcı Haberleri</h3>
                    <div class="user-news-list">
                        <?php
                        if($db) {
                            try {
                                $query = "SELECT n.id, n.title, n.created_at, u.username, n.author_name 
                                         FROM news n 
                                         LEFT JOIN users u ON n.author_id = u.id 
                                         WHERE n.status='approved' AND n.author_id IS NOT NULL
                                         ORDER BY n.created_at DESC LIMIT 10";
                                $stmt = $db->prepare($query);
                                $stmt->execute();
                                
                                if($stmt->rowCount() > 0) {
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $time_ago = time_elapsed_string($row['created_at']);
                                        $author = $row['username'] ?: ($row['author_name'] ?: 'Anonim');
                                        echo "
                                        <div class='user-news-item'>
                                            <a href='Veri/haber-detay.php?id={$row['id']}'>
                                                <div class='user-news-title'>" . htmlspecialchars($row['title']) . "</div>
                                                <div class='user-news-meta'>
                                                    <span>@" . htmlspecialchars($author) . "</span>
                                                    <span>{$time_ago}</span>
                                                </div>
                                            </a>
                                        </div>";
                                    }
                                } else {
                                    echo "<p style='text-align: center; color: var(--gray);'>Henüz kullanıcı haberi yok</p>";
                                }
                            } catch (PDOException $e) {
                                echo "<p style='text-align: center; color: var(--red);'>Kullanıcı haberleri yüklenemedi</p>";
                            }
                        }
                        ?>
                    </div>
                </div>

                <!-- Önerilen Haberler -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">Çok Okunanlar</h3>
                    <div class="popular-news-list">
                        <?php
                        if($db) {
                            try {
                                $one_week_ago = date('Y-m-d H:i:s', strtotime('-1 week'));
                                $query = "SELECT n.id, n.title, n.view_count, n.created_at 
                                         FROM news n 
                                         WHERE n.status='approved' AND n.created_at >= :one_week_ago
                                         ORDER BY n.view_count DESC LIMIT 5";
                                $stmt = $db->prepare($query);
                                $stmt->bindParam(':one_week_ago', $one_week_ago);
                                $stmt->execute();
                                
                                if($stmt->rowCount() > 0) {
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $time_ago = time_elapsed_string($row['created_at']);
                                        echo "
                                        <div class='popular-news-item'>
                                            <a href='Veri/haber-detay.php?id={$row['id']}'>
                                                <div class='popular-news-title'>" . htmlspecialchars($row['title']) . "</div>
                                                <div class='popular-news-meta'>
                                                    <span>" . number_format($row['view_count']) . " okunma</span>
                                                    <span>{$time_ago}</span>
                                                </div>
                                            </a>
                                        </div>";
                                    }
                                } else {
                                    echo "<p style='text-align: center; color: var(--gray);'>Henüz çok okunan haber yok</p>";
                                }
                            } catch (PDOException $e) {
                                echo "<p style='text-align: center; color: var(--red);'>Çok okunanlar yüklenemedi</p>";
                            }
                        }
                        ?>
                    </div>
                </div>
            </aside>
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
                        <li><a href="index.php?category=1">Gündem</a></li>
                        <li><a href="index.php?category=2">Spor</a></li>
                        <li><a href="index.php?category=3">Magazin</a></li>
                        <li><a href="index.php?category=4">Teknoloji</a></li>
                        <li><a href="index.php?category=5">Ekonomi</a></li>
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
        // Enhanced JavaScript kodları
        let currentSlide = 0;
        let slideInterval;
        const slides = document.querySelectorAll('.slider-slide');
        const indicatorsContainer = document.getElementById('slider-indicators');
        const counterContainer = document.getElementById('slider-counter');

        function createSliderUI() {
            if (!indicatorsContainer) return;
            
            indicatorsContainer.innerHTML = '';
            slides.forEach((_, index) => {
                const indicator = document.createElement('div');
                indicator.className = `slider-indicator ${index === 0 ? 'active' : ''}`;
                indicator.setAttribute('data-index', index);
                indicator.addEventListener('click', () => goToSlide(index));
                indicator.addEventListener('mouseenter', () => pauseSlider());
                indicator.addEventListener('mouseleave', () => startSlider());
                indicatorsContainer.appendChild(indicator);
            });
            
            updateCounter();
        }

        function updateCounter() {
            if (counterContainer) {
                counterContainer.textContent = `${currentSlide + 1} / ${slides.length}`;
            }
        }

        function goToSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            document.querySelectorAll('.slider-indicator').forEach(ind => ind.classList.remove('active'));
            
            if (slides[index]) {
                slides[index].classList.add('active');
            }
            const indicator = document.querySelector(`.slider-indicator[data-index="${index}"]`);
            if (indicator) {
                indicator.classList.add('active');
            }
            currentSlide = index;
            updateCounter();
        }

        function prevSlide() {
            currentSlide = (currentSlide - 1 + slides.length) % slides.length;
            goToSlide(currentSlide);
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            goToSlide(currentSlide);
        }

        function startSlider() {
            if (slides.length > 1) {
                slideInterval = setInterval(nextSlide, 5000);
            }
        }

        function pauseSlider() {
            clearInterval(slideInterval);
        }

        let currentKoseYazisi = 0;
        const koseYazilari = document.querySelectorAll('.kose-yazisi-slide');

        function goToKoseYazisi(index) {
            koseYazilari.forEach(slide => slide.classList.remove('active'));
            if (koseYazilari[index]) {
                koseYazilari[index].classList.add('active');
            }
            currentKoseYazisi = index;
        }

        function nextKoseYazisi() {
            if (koseYazilari.length > 0) {
                currentKoseYazisi = (currentKoseYazisi + 1) % koseYazilari.length;
                goToKoseYazisi(currentKoseYazisi);
            }
        }

        function prevKoseYazisi() {
            if (koseYazilari.length > 0) {
                currentKoseYazisi = (currentKoseYazisi - 1 + koseYazilari.length) % koseYazilari.length;
                goToKoseYazisi(currentKoseYazisi);
            }
        }

        // Arama fonksiyonları
        function performSearch() {
            const searchTerm = document.querySelector('.search-input')?.value.trim() || 
                             document.querySelector('.mobile-search-input')?.value.trim();
            if (searchTerm) {
                window.location.href = `Veri/arama.php?q=${encodeURIComponent(searchTerm)}`;
            }
        }

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

        // Sidebar için hava durumu güncelleme
        function updateSidebarWeather() {
            const select = document.getElementById('sidebar-city-select');
            const weatherContainer = document.getElementById('sidebar-weather-display');
            
            if (!select || !weatherContainer) return;
            
            const selectedCity = select.value;
            
            if (!selectedCity) {
                weatherContainer.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: var(--gray);">
                        <i class="fas fa-cloud-sun" style="font-size: 48px; margin-bottom: 10px; color: var(--blue);"></i>
                        <p style="font-size: 12px;">Yukarıdan bir şehir seçin</p>
                    </div>
                `;
                return;
            }
            
            // Loading state
            weatherContainer.innerHTML = `
                <div style="text-align: center; padding: 15px; color: var(--gray);">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 8px; color: var(--blue);"></i>
                    <p style="font-size: 11px;">Hava durumu yükleniyor...</p>
                </div>
            `;
            
            // Doğrudan TAVCAN API'ye istek at
            const apiUrl = `https://api.tavcan.com/json/havadurumu/${selectedCity}`;
            
            fetch(apiUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('API yanıt vermedi');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Hava durumu verisi:', data);
                    
                    if (data && Array.isArray(data) && data.length > 0) {
                        const cityName = select.options[select.selectedIndex].text;
                        displaySidebarWeatherData(data, cityName);
                    } else {
                        throw new Error('Geçerli hava durumu verisi bulunamadı');
                    }
                })
                .catch(error => {
                    console.error('Hava durumu hatası:', error);
                    weatherContainer.innerHTML = `
                        <div style="text-align: center; padding: 15px; color: var(--gray);">
                            <i class="fas fa-wifi" style="font-size: 24px; margin-bottom: 8px; color: var(--red);"></i>
                            <p style="font-size: 11px;">Hava durumu verisi alınamadı</p>
                            <small style="font-size: 9px; color: var(--gray);">Lütfen daha sonra tekrar deneyin</small>
                        </div>
                    `;
                });
        }

        function displaySidebarWeatherData(weatherDataArray, cityName) {
            const weatherContainer = document.getElementById('sidebar-weather-display');
            
            if (!weatherDataArray || !Array.isArray(weatherDataArray) || weatherDataArray.length === 0) {
                weatherContainer.innerHTML = `
                    <div style="text-align: center; padding: 15px; color: var(--gray);">
                        <i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 8px; color: var(--red);"></i>
                        <p style="font-size: 11px;">Hava durumu verisi bulunamadı</p>
                    </div>
                `;
                return;
            }
            
            // İlk günün verisini al (bugün)
            const todayData = weatherDataArray[0];
            
            if (!todayData) {
                weatherContainer.innerHTML = `
                    <div style="text-align: center; padding: 15px; color: var(--gray);">
                        <i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 8px; color: var(--red);"></i>
                        <p style="font-size: 11px;">Bugünkü hava durumu verisi yok</p>
                    </div>
                `;
                return;
            }
            
            const weatherIcon = getWeatherIcon(todayData.icon || '01d');
            const weatherText = formatWeatherText(todayData.flaticon || 'sunny');
            
            // Sıcaklık değerlerini kontrol et
            const maxTemp = todayData.max || todayData.sicaklik || '--';
            const minTemp = todayData.min || '--';
            const date = todayData.tarih || 'Bugün';
            
            weatherContainer.innerHTML = `
                <div style="text-align: center; padding: 10px;">
                    <i class="${weatherIcon}" style="font-size: 48px; margin-bottom: 10px; color: var(--blue);"></i>
                    <div style="font-size: 11px; color: var(--gray); margin-bottom: 5px; font-weight: 600;">${cityName}</div>
                    <div style="font-size: 28px; font-weight: 800; color: var(--blue); margin: 10px 0;">${maxTemp}°C</div>
                    <div style="font-size: 12px; color: var(--dark); margin-bottom: 5px; font-weight: 600;">Min: ${minTemp}°C</div>
                    <div style="font-size: 14px; color: var(--dark); margin-bottom: 10px; font-weight: 600;">${weatherText}</div>
                    <div style="font-size: 10px; color: var(--gray);">${date}</div>
                </div>
            `;
        }

        function formatWeatherText(flaticon) {
            if (!flaticon) return 'Açık';
            
            const translations = {
                'cloudy': 'Bulutlu',
                'sunny': 'Güneşli', 
                'day-hail': 'Sağanak',
                'night-hail': 'Sağanak',
                'day-rain': 'Yağmurlu',
                'night-rain': 'Yağmurlu',
                'day-thunderstorm': 'Fırtınalı',
                'night-thunderstorm': 'Fırtınalı',
                'day-snow': 'Karlı',
                'night-snow': 'Karlı',
                'day-fog': 'Sisli',
                'night-fog': 'Sisli',
                'clear': 'Açık',
                'partly-cloudy': 'Parçalı Bulutlu'
            };
            return translations[flaticon] || 'Açık';
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
            const parallaxElements = document.querySelectorAll('.sidebar-left, .slider-container');
            
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

        // Event listener'lar
        document.addEventListener('DOMContentLoaded', function() {
            // Sync dark mode class to html element
            if (document.body.classList.contains('dark-mode')) {
                document.documentElement.classList.add('dark-mode');
            }
            
            createSliderUI();
            startSlider();
            updateTime();
            
            const slider = document.querySelector('.slider-wrapper');
            if (slider) {
                slider.addEventListener('mouseenter', pauseSlider);
                slider.addEventListener('mouseleave', startSlider);
            }

            // Masaüstü arama
            const desktopSearchBtn = document.querySelector('.search-button');
            const desktopSearchInput = document.querySelector('.search-input');
            
            if (desktopSearchBtn) {
                desktopSearchBtn.addEventListener('click', performSearch);
            }
            
            if (desktopSearchInput) {
                desktopSearchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        performSearch();
                    }
                });
            }

            // Mobil arama
            const mobileSearchBtn = document.querySelector('.mobile-search-button');
            const mobileSearchInput = document.querySelector('.mobile-search-input');
            
            if (mobileSearchBtn) {
                mobileSearchBtn.addEventListener('click', performSearch);
            }
            
            if (mobileSearchInput) {
                mobileSearchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        performSearch();
                    }
                });
            }

            // Start weather rotation if data exists
            if (Object.keys(quickWeatherData).length > 0) {
                setInterval(updateWeather, 5000); // 5 seconds
            }

            // Update time every second
            setInterval(updateTime, 1000);

            // İstanbul'u varsayılan olarak seç ve hava durumunu yükle
            const select = document.getElementById('sidebar-city-select');
            if (select) {
                const istanbulOption = Array.from(select.options).find(option => option.value === 'istanbul');
                if (istanbulOption) {
                    select.value = 'istanbul';
                    // Hemen hava durumunu yükle
                    setTimeout(() => updateSidebarWeather(), 1000);
                }
            }

            // Initialize scroll animations
            const newsCards = document.querySelectorAll('.news-card, .user-news-item, .popular-news-item, .kose-yazisi-content, .fikstur-kart');
            newsCards.forEach((card, index) => {
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
            const clickableElements = document.querySelectorAll('.news-card, .kose-yazisi-nav button, .slider-controls button, .user-news-item, .popular-news-item');
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

            // Köşe yazıları slider entrance animation
            const koseSlides = document.querySelectorAll('.kose-yazisi-slide');
            koseSlides.forEach((slide, index) => {
                slide.style.opacity = '0';
                slide.style.transform = 'translateX(30px)';
                
                setTimeout(() => {
                    slide.style.transition = 'all 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
                    if (slide.classList.contains('active')) {
                        slide.style.opacity = '1';
                        slide.style.transform = 'translateX(0)';
                    }
                }, 300 + (index * 200));
            });

            // Add floating animation to weather icons on hover
            document.querySelectorAll('.weather-icon').forEach(icon => {
                icon.addEventListener('mouseenter', function() {
                    this.style.animation = 'float 1s ease-in-out infinite';
                });
                icon.addEventListener('mouseleave', function() {
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
            console.log('%c✨ ONVIBES - Premium Haber Sitesi ✨', 
                'color: #d2232a; font-size: 20px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);');
            console.log('%cSayfa başarıyla yüklendi! Animasyonlar aktif. 🚀', 
                'color: #0a8c2f; font-size: 14px; font-weight: bold;');
        });
    </script>
</body>
</html>

<?php
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'yıl',
        'm' => 'ay',
        'w' => 'hafta',
        'd' => 'gün',
        'h' => 'saat',
        'i' => 'dakika',
        's' => 'saniye',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' önce' : 'şimdi';
}
