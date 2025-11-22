<?php
// haber-detay.php - Premium Tema Entegrasyonlu Haber Detay Sayfası (API Entegreli)
session_start();

include '../config.php';
$database = new Database();
$db = $database->getConnection();

// Debug: URL parametrelerini kontrol et
if (isset($_GET['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    echo "<h3>Debug Bilgileri:</h3>";
    echo "<pre>";
    echo "GET Parametreleri: " . print_r($_GET, true) . "\n\n";
    echo "Session Durumu: " . print_r($_SESSION, true) . "\n\n";
    echo "Database Bağlantısı: " . ($db ? "Başarılı" : "Başarısız") . "\n\n";
    echo "</pre>";
    exit();
}

// Haber ID kontrolü
if(!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ../index.php?error=no_id');
    exit();
}

$news_id = (int)$_GET['id'];

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $news_id);
    exit();
}
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Kategori filtresi
$current_category = isset($_GET['category']) ? $_GET['category'] : 'all';

// Kategorileri veritabanından çek
try {
    $categories_query = "SELECT * FROM categories ORDER BY name ASC";
    $categories_stmt = $db->prepare($categories_query);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// API veri çekme fonksiyonları
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

// Debug için veritabanı kontrolü
if (!isset($db)) {
    die('Veritabanı bağlantısı kurulamadı!');
}

// Görüntülenme sayısını artır
try {
    $view_query = "UPDATE news SET views = views + 1 WHERE id = :id";
    $view_stmt = $db->prepare($view_query);
    $view_stmt->bindParam(':id', $news_id);
    $view_stmt->execute();
} catch(PDOException $e) {
    // Sessizce devam et
}

// Haber detaylarını çek
try {
    $query = "SELECT n.*, u.username, u.full_name, c.name as category_name, c.id as category_id
              FROM news n
              LEFT JOIN users u ON n.author_id = u.id
              LEFT JOIN categories c ON n.category_id = c.id
              WHERE n.id = :id AND n.status IN ('approved', 'published')";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $news_id);
    $stmt->execute();
    
    $news = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$news) {
        // Haber bulunamadı, önce ID'nin gerçekten var olup olmadığını kontrol et
        $check_query = "SELECT id, title, status FROM news WHERE id = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $news_id);
        $check_stmt->execute();
        $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($check_result) {
            // Haber var ama status'u published değil
            header('Location: ../index.php?error=news_not_published');
        } else {
            // Haber hiç yok
            header('Location: ../index.php?error=news_not_found');
        }
        exit();
    }
} catch(PDOException $e) {
    // Hata detaylarını logla ama kullanıcıya gösterme
    error_log("Haber detay sorgu hatası: " . $e->getMessage());
    die('Haber yüklenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.');
}

// İlgili haberler
try {
    $related_query = "SELECT id, title, image, created_at, views 
                      FROM news 
                      WHERE category_id = :category_id 
                      AND id != :news_id 
                      AND status IN ('approved', 'published')
                      ORDER BY created_at DESC 
                      LIMIT 5";
    
    $related_stmt = $db->prepare($related_query);
    $related_stmt->bindParam(':category_id', $news['category_id']);
    $related_stmt->bindParam(':news_id', $news_id);
    $related_stmt->execute();
    
    $related_news = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $related_news = [];
}

// Yorumları çek
try {
    $comments_query = "SELECT c.*, u.username, u.full_name, u.avatar 
                       FROM comments c
                       LEFT JOIN users u ON c.user_id = u.id
                       WHERE c.news_id = :news_id AND c.status = 'approved'
                       ORDER BY c.created_at DESC";
    
    $comments_stmt = $db->prepare($comments_query);
    $comments_stmt->bindParam(':news_id', $news_id);
    $comments_stmt->execute();
    
    $comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $comments = [];
}

// Yorum ekleme
$comment_message = '';
$comment_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_comment'])) {
    if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        $comment_error = 'Yorum yapabilmek için giriş yapmalısınız!';
    } else {
        $comment_text = trim($_POST['comment']);
        $user_id = $_SESSION['user_id'];
        
        if(empty($comment_text)) {
            $comment_error = 'Yorum alanı boş olamaz!';
        } elseif(strlen($comment_text) < 10) {
            $comment_error = 'Yorum en az 10 karakter olmalıdır!';
        } else {
            try {
                $insert_query = "INSERT INTO comments (news_id, user_id, comment, status, created_at) 
                                VALUES (:news_id, :user_id, :comment, 'pending', NOW())";
                
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':news_id', $news_id);
                $insert_stmt->bindParam(':user_id', $user_id);
                $insert_stmt->bindParam(':comment', $comment_text);
                
                if($insert_stmt->execute()) {
                    $comment_message = 'Yorumunuz onay için gönderildi!';
                }
            } catch(PDOException $e) {
                $comment_error = 'Yorum eklenirken hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

// Meta etiketler için
$page_title = htmlspecialchars($news['title'] ?? '') . ' - ONVIBES';
$page_description = htmlspecialchars(Helper::excerpt($news['content'] ?? '', 160));
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="<?php echo $page_description; ?>">
    
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
            grid-template-columns: 1fr 300px;
            gap: 25px;
            margin: 25px 0;
        }

        /* HABER DETAY STİLLERİ - PREMIUM UPDATE */
        .news-detail-container {
            background: var(--light);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.1), 0 6px 20px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out;
        }

        .dark-mode .news-detail-container {
            background: #0a0a0a;
            box-shadow: 0 12px 40px rgba(0,0,0,0.8);
        }

        .news-detail-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--red), var(--orange), var(--red));
            background-size: 200% 200%;
            border-radius: 20px 20px 0 0;
            animation: gradientShift 3s ease infinite;
        }

        .news-header {
            margin-bottom: 25px;
            position: relative;
        }

        .news-title {
            color: var(--dark);
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            line-height: 1.2;
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
            padding-left: 1.5rem;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        .news-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background: linear-gradient(45deg, var(--red), var(--orange));
            border-radius: 3px;
        }

        .dark-mode .news-title {
            color: var(--text);
        }

        .news-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2rem 0;
            padding: 2rem;
            background: linear-gradient(135deg, var(--surface), rgba(52, 152, 219, 0.1));
            border-radius: 15px;
            border-left: 5px solid var(--red);
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            position: relative;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }

        .news-meta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--red), var(--orange), var(--red));
            border-radius: 15px 15px 0 0;
        }

        .meta-left, .meta-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .news-source-info {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            border-left: 3px solid var(--red);
            backdrop-filter: blur(10px);
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }

        .dark-mode .news-source-info {
            background: rgba(255, 255, 255, 0.1);
        }

        .source-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 0.95rem;
        }

        .source-item i {
            color: var(--red);
            font-size: 1rem;
        }

        .source-label {
            color: var(--text);
            font-weight: 600;
        }

        .source-value {
            color: var(--blue);
            font-weight: 500;
        }

        .news-meta .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text);
            font-weight: 500;
        }

        .category-badge {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            box-shadow: 0 4px 15px rgba(210, 35, 42, 0.3);
        }

        .category-badge:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(210, 35, 42, 0.5);
        }

        /* GÖRSEL STİLLERİ - PREMIUM */
        .featured-image-container {
            position: relative;
            margin: 2.5rem 0;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 50px rgba(0,0,0,0.2);
            background: linear-gradient(135deg, var(--dark), var(--blue));
            animation: fadeInUp 0.8s ease-out 0.8s both;
        }

        .featured-news-image {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
            transition: all 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
            display: block;
        }

        .featured-image-container:hover .featured-news-image {
            transform: scale(1.05);
        }

        .image-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.9));
            padding: 2rem 1.5rem 1.5rem;
            transform: translateY(100%);
            transition: transform 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .featured-image-container:hover .image-overlay {
            transform: translateY(0);
        }

        .image-source {
            color: white;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .news-image-placeholder {
            width: 100%;
            height: 300px;
            border-radius: 12px;
            margin: 25px 0;
            background: linear-gradient(135deg, var(--dark), #95a5a6);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            animation: fadeInUp 0.8s ease-out 0.8s both;
        }

        .placeholder-icon {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.8;
        }

        /* HABER İÇERİĞİ - PREMIUM */
        .news-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--text);
            margin: 2rem 0;
            animation: fadeInUp 0.8s ease-out 1s both;
        }

        .news-content p {
            margin-bottom: 1.5rem;
            text-align: justify;
        }

        .news-content h2 {
            color: var(--red);
            font-size: 1.6rem;
            margin: 2.5rem 0 1rem 0;
            border-bottom: 2px solid var(--red);
            padding-bottom: 0.5rem;
        }

        .news-content h3 {
            color: var(--blue);
            font-size: 1.4rem;
            margin: 2rem 0 1rem 0;
        }

        .news-content strong {
            color: var(--red);
            font-weight: 600;
        }

        .news-content em {
            color: var(--text);
            opacity: 0.8;
            font-style: italic;
        }

        .news-content blockquote {
            border-left: 4px solid var(--red);
            padding-left: 1.5rem;
            margin: 2rem 0;
            background: var(--surface);
            padding: 1.5rem;
            border-radius: 0 12px 12px 0;
            font-style: italic;
            position: relative;
        }

        .news-content blockquote::before {
            content: '"';
            position: absolute;
            top: -10px;
            left: 10px;
            font-size: 4rem;
            color: var(--red);
            opacity: 0.2;
            font-family: Georgia, serif;
        }

        /* HABER FOOTER - PREMIUM */
        .news-footer {
            padding-top: 2rem;
            border-top: 2px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-top: 2rem;
            animation: fadeInUp 0.8s ease-out 1.2s both;
        }

        .author-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .author-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(210, 35, 42, 0.3);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .author-avatar:hover {
            transform: scale(1.1) rotate(10deg);
        }

        .author-details h4 {
            color: var(--text);
            font-size: 1.1rem;
            margin-bottom: 0.3rem;
            font-weight: 700;
        }

        .author-details p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .share-buttons {
            display: flex;
            gap: 0.8rem;
        }

        .share-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            font-size: 1.1rem;
            position: relative;
            overflow: hidden;
        }

        .share-btn::before {
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

        .share-btn:hover::before {
            width: 100%;
            height: 100%;
        }

        .share-btn.facebook {
            background: #3b5998;
        }

        .share-btn.twitter {
            background: #1da1f2;
        }

        .share-btn.whatsapp {
            background: #25d366;
        }

        .share-btn:hover {
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        /* YORUMLAR BÖLÜMÜ - PREMIUM */
        .comments-section {
            background: var(--light);
            border-radius: 20px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out 1.4s both;
        }

        .dark-mode .comments-section {
            background: #0a0a0a;
        }

        .comments-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--blue), #74b9ff);
            border-radius: 20px 20px 0 0;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .dark-mode .section-title {
            color: var(--text);
        }

        .section-title i {
            color: var(--blue);
        }

        /* YORUM FORMU - PREMIUM */
        .comment-form {
            background: var(--surface);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
            animation: fadeInUp 0.8s ease-out 1.6s both;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.8rem;
            font-weight: 600;
            color: var(--text);
            font-size: 1rem;
        }

        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: var(--light);
            color: var(--text);
            font-size: 1rem;
            font-family: inherit;
            resize: vertical;
            min-height: 120px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .dark-mode .form-group textarea {
            background: #0f0f0f;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(210, 35, 42, 0.1);
            transform: scale(1.02);
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            border: none;
            padding: 12px 35px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            overflow: hidden;
        }

        .submit-btn::before {
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

        .submit-btn:hover::before {
            width: 100%;
            height: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(210, 35, 42, 0.4);
        }

        /* MESAJLAR - PREMIUM */
        .alert {
            padding: 1.2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            animation: fadeInUp 0.8s ease-out;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .dark-mode .alert-success {
            background: #155724;
            color: #d4edda;
            border-color: #0c3514;
        }

        .dark-mode .alert-error {
            background: #721c24;
            color: #f8d7da;
            border-color: #491217;
        }

        /* YORUMLAR LİSTESİ - PREMIUM */
        .comments-list {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .comment-item {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: 15px;
            border-left: 4px solid var(--blue);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            animation: fadeInUp 0.8s ease-out;
        }

        .comment-item:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .comment-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .comment-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue) 0%, #2980b9 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .comment-avatar:hover {
            transform: scale(1.1) rotate(10deg);
        }

        .comment-info h4 {
            color: var(--text);
            font-size: 1.1rem;
            margin-bottom: 0.3rem;
            font-weight: 700;
        }

        .comment-info span {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .comment-text {
            color: var(--text);
            font-size: 1rem;
            line-height: 1.6;
        }

        .no-comments {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
            animation: fadeInUp 0.8s ease-out;
        }

        .no-comments i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* SIDEBAR - PREMIUM */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .sidebar-card {
            background: var(--light);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1), 0 4px 16px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            animation: fadeInRight 0.8s ease-out;
        }

        .dark-mode .sidebar-card {
            background: #0a0a0a;
        }

        .sidebar-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--green), #00b894);
            border-radius: 20px 20px 0 0;
        }

        .sidebar-title {
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            color: var(--dark);
            padding-bottom: 0.8rem;
            border-bottom: 3px solid transparent;
            border-image: linear-gradient(90deg, var(--green), #00b894) 1;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .dark-mode .sidebar-title {
            color: var(--text);
        }

        .sidebar-title i {
            color: var(--green);
            font-size: 1.3rem;
        }

        /* İLGİLİ HABERLER - PREMIUM */
        .related-news-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            cursor: pointer;
            animation: fadeInRight 0.8s ease-out;
        }

        .related-news-item:hover {
            transform: translateX(8px);
            border-bottom-color: var(--green);
        }

        .related-news-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .related-news-img {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--dark), var(--green));
            border: 3px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .related-news-item:hover .related-news-img {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        .related-news-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .related-news-info h4 {
            font-size: 0.95rem;
            color: var(--text);
            margin-bottom: 0.5rem;
            line-height: 1.4;
            font-weight: 600;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .related-news-info h4 a {
            color: var(--text);
            text-decoration: none;
            transition: color 0.3s;
        }

        .related-news-info h4 a:hover {
            color: var(--green);
        }

        .related-news-meta {
            display: flex;
            gap: 0.8rem;
            font-size: 0.8rem;
            color: var(--gray);
        }

        .related-news-meta span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .related-news-meta i {
            color: var(--green);
            font-size: 0.8rem;
        }

        /* API VERİ KARTLARI - YENİ */
        .api-data-card {
            background: var(--light);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid var(--blue);
            animation: fadeInRight 0.8s ease-out;
        }

        .dark-mode .api-data-card {
            background: #0a0a0a;
        }

        .api-data-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dark-mode .api-data-title {
            color: var(--text);
        }

        .api-data-content {
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .weather-display {
            text-align: center;
            padding: 15px;
        }

        .weather-icon-large {
            font-size: 3rem;
            margin-bottom: 10px;
            color: var(--blue);
        }

        .weather-temp {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .dark-mode .weather-temp {
            color: var(--text);
        }

        .weather-desc {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .takim-siralamasi {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        .takim-siralamasi th {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            padding: 8px 4px;
            text-align: center;
            font-weight: 700;
            border-radius: 6px 6px 0 0;
            font-size: 0.7rem;
        }

        .takim-siralamasi td {
            padding: 6px 4px;
            text-align: center;
            border-bottom: 1px solid var(--border);
            transition: all 0.3s;
            font-size: 0.75rem;
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
            font-size: 0.75rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 80px;
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

        .fikstur-kartlari {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .fikstur-kart {
            background: var(--surface);
            border-radius: 10px;
            padding: 12px;
            border-left: 3px solid var(--green);
            transition: all 0.3s;
        }

        .fikstur-kart:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .fikstur-tarih {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 8px;
            font-weight: 600;
        }

        .mac-satiri {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
        }

        .mac-skoru {
            font-weight: 700;
            color: var(--red);
        }

        /* DEBUG ALERT */
        .debug-alert {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            margin: 15px 0;
            border-radius: 12px;
            animation: fadeInUp 0.8s ease-out;
        }

        .dark-mode .debug-alert {
            background: #332b00;
            border-color: #665800;
            color: #ffd700;
        }

        .debug-alert a {
            color: #856404;
            font-weight: 600;
            text-decoration: none;
        }

        .dark-mode .debug-alert a {
            color: #ffd700;
        }

        /* BREADCRUMB - PREMIUM */
        .breadcrumb {
            display: flex;
            gap: 0.8rem;
            align-items: center;
            color: var(--gray);
            font-size: 0.9rem;
            margin: 1.5rem 0;
            padding: 1rem 0;
            animation: fadeInUp 0.8s ease-out;
        }

        .breadcrumb a {
            color: var(--red);
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
        }

        .breadcrumb a:hover {
            color: var(--blue);
            transform: translateY(-1px);
        }

        .breadcrumb span {
            color: var(--gray);
            opacity: 0.7;
        }

        /* ANIMATIONS */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
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

        @keyframes pulseScale {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* RESPONSIVE - PREMIUM */
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                margin-top: 2rem;
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

            .news-title {
                font-size: 1.8rem;
            }

            .news-meta {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
                padding: 1.5rem;
            }

            .meta-left, .meta-right {
                gap: 1rem;
                width: 100%;
                justify-content: flex-start;
            }

            .news-source-info {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .news-footer {
                flex-direction: column;
                gap: 1.5rem;
            }

            .featured-image-container {
                margin: 1.5rem 0;
            }

            .featured-news-image {
                max-height: 300px;
            }

            .api-data-card {
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            .news-detail-container,
            .comments-section,
            .sidebar-card {
                padding: 1.5rem;
            }

            .news-title {
                font-size: 1.5rem;
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

            .takim-siralamasi {
                font-size: 0.7rem;
            }

            .takim-siralamasi th,
            .takim-siralamasi td {
                padding: 4px 2px;
            }
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
                        <a href="../index.php" title="ONVIBES">
                            <span class="logo-text">HABER|ONVIBES</span>
                        </a>
                    </h1>

                    <!-- Header Linkler - Enhanced -->
                    <div class="header-links">
                        <a href="../Veri/ilan.php" class="header-link">
                            <i class="fas fa-bullhorn"></i>
                            <span>İlanlar</span>
                        </a>
                        <a href="../Veri/haberler.php" class="header-link">
                            <i class="fas fa-newspaper"></i>
                            <span>Haberler</span>
                        </a>
                        <a href="../kose-yazilari.php" class="header-link">
                            <i class="fas fa-pen-fancy"></i>
                            <span>Analizler</span>
                        </a>
                        <a href="../eek.php" class="header-link">
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
                                <a href="../profil.php" class="user-profile-btn">
                                    <i class="fas fa-user"></i>
                                    <span>Profil</span>
                                </a>
                            <?php else: ?>
                                <a href="../Giris/login.php" class="user-profile-btn">
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
                        <li><a href="../index.php" class="<?php echo $current_category == 'all' ? 'active' : ''; ?>">Ana Sayfa</a></li>
                        <?php foreach($categories as $cat): ?>
                            <li>
                                <a href="../index.php?category=<?php echo $cat['id']; ?>" 
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
        <!-- Debug Info (Eğer URL'de debug parametresi varsa) -->
        <?php if(isset($_GET['debug'])): ?>
            <div class="debug-alert">
                <i class="fas fa-info-circle"></i>
                <strong>Debug Modu Aktif!</strong> - Sorununuzu bu bilgilerle tespit edebilirsiniz.
                <a href="<?php echo str_replace('&debug=1', '', $_SERVER['REQUEST_URI']); ?>">Debug modunu kapat</a>
            </div>
        <?php endif; ?>

        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="../index.php">
                <i class="fas fa-home"></i> Ana Sayfa
            </a>
            <span>/</span>
            <a href="../Veri/haberler.php?category=<?php echo $news['category_id']; ?>">
                <?php echo htmlspecialchars($news['category_name'] ?? ''); ?>
            </a>
            <span>/</span>
            <span><?php echo htmlspecialchars($news['title'] ?? ''); ?></span>
        </div>

        <div class="main-content">
            <!-- Main Content -->
            <div>
                <!-- Haber Detay -->
                <article class="news-detail-container">
                    <div class="news-header">
                        <h1 class="news-title"><?php echo htmlspecialchars($news['title'] ?? ''); ?></h1>
                        
                        <div class="news-meta">
                            <div class="meta-left">
                                <div class="meta-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('d.m.Y H:i', strtotime($news['created_at'] ?? '')); ?>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-eye"></i>
                                    <?php echo Helper::formatNumber($news['views'] ?? 0); ?> Görüntülenme
                                </div>
                            </div>
                            <div class="meta-right">
                                <a href="../Veri/haberler.php?category=<?php echo $news['category_id'] ?? ''; ?>" class="category-badge">
                                    <?php echo htmlspecialchars($news['category_name'] ?? ''); ?>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Haber Kaynağı ve Yazar Bilgisi -->
                        <div class="news-source-info">
                            <div class="source-item">
                                <i class="fas fa-user-edit"></i>
                                <span class="source-label">Yazar:</span>
                                <span class="source-value"><?php echo htmlspecialchars($news['full_name'] ?? $news['username']); ?></span>
                            </div>
                            <?php if(!empty($news['source'])): ?>
                                <div class="source-item">
                                    <i class="fas fa-link"></i>
                                    <span class="source-label">Kaynak:</span>
                                    <span class="source-value"><?php echo htmlspecialchars($news['source']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if(!empty($news['updated_at']) && $news['updated_at'] != $news['created_at']): ?>
                                <div class="source-item">
                                    <i class="fas fa-clock"></i>
                                    <span class="source-label">Güncelleme:</span>
                                    <span class="source-value"><?php echo date('d.m.Y H:i', strtotime($news['updated_at'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if(!empty($news['image'])): ?>
                        <?php 
                        $imagePath = '../' . ltrim($news['image'], '/');
                        $imageExists = file_exists($imagePath);
                        if($imageExists): 
                        ?>
                            <div class="featured-image-container">
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                     alt="<?php echo htmlspecialchars($news['title'] ?? ''); ?>" 
                                     class="featured-news-image"
                                     onerror="this.style.display='none'; document.querySelector('.fallback-image').style.display='flex';">
                                <div class="fallback-image news-image-placeholder" style="display: none;">
                                    <div class="placeholder-icon">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    <p>Görsel Yüklenemedi</p>
                                </div>
                                <div class="image-overlay">
                                    <span class="image-source">
                                        <i class="fas fa-camera"></i>
                                        <?php echo !empty($news['image_source']) ? htmlspecialchars($news['image_source']) : 'Arşiv Fotoğrafı'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="news-image-placeholder featured-image-container">
                                <div class="placeholder-icon">
                                    <i class="fas fa-image"></i>
                                </div>
                                <p>Görsel Bulunamadı</p>
                                <small style="opacity: 0.7; margin-top: 0.5rem;">Bu haber henüz görsel ile desteklenmemiştir</small>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="news-image-placeholder featured-image-container">
                            <div class="placeholder-icon">
                                <i class="fas fa-image"></i>
                            </div>
                            <p>Görsel Eklenmedi</p>
                            <small style="opacity: 0.7; margin-top: 0.5rem;">Bu haber henüz görsel ile desteklenmemiştir</small>
                        </div>
                    <?php endif; ?>

                    <div class="news-content news-preview">
                        <?php 
                        // Markdown'u HTML'e çevir
                        $content = $news['content'] ?? '';
                        if(!empty($content)) {
                            $content = preg_replace('/^### (.*$)/im', '<h3>$1</h3>', $content);
                            $content = preg_replace('/^## (.*$)/im', '<h2>$1</h2>', $content);
                            $content = preg_replace('/^# (.*$)/im', '<h1>$1</h1>', $content);
                            $content = preg_replace('/\\*\\*(.*?)\\*\\*/im', '<strong>$1</strong>', $content);
                            $content = preg_replace('/\\*(.*?)\\*/im', '<em>$1</em>', $content);
                            $content = preg_replace('/!\\[(.*?)\\]\\((.*?)\\)/im', '<img src="$2" alt="$1" class="featured-news-image" style="margin: 2rem auto; border-radius: 12px; max-width: 100%;" onerror="this.style.display=\'none\'"><div class="image-caption" style="text-align: center; font-style: italic; color: var(--gray); margin-top: 0.5rem;">$1</div>', $content);
                            $content = preg_replace('/\\[(.*?)\\]\\((.*?)\\)/im', '<a href="$2" target="_blank" style="color: var(--red); text-decoration: none;">$1</a>', $content);
                            $content = preg_replace('/^> (.*$)/im', '<blockquote>$1</blockquote>', $content);
                            $content = preg_replace('/\\n\\n/im', '</p><p>', $content);
                            $content = preg_replace('/\\n/im', '<br>', $content);
                            $content = '<p>' . $content . '</p>';
                            $content = preg_replace('/<p><\\/p>/im', '', $content);
                            
                            echo $content;
                        } else {
                            echo '<p>İçerik bulunamadı.</p>';
                        }
                        ?>
                    </div>

                    <div class="news-footer">
                        <div class="author-info">
                            <div class="author-avatar">
                                <?php echo strtoupper(substr($news['full_name'] ?? $news['username'], 0, 1)); ?>
                            </div>
                            <div class="author-details">
                                <h4><?php echo htmlspecialchars($news['full_name'] ?? $news['username']); ?></h4>
                                <p>Yazar</p>
                            </div>
                        </div>

                        <div class="share-buttons">
                            <button class="share-btn facebook" 
                                    onclick="window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(window.location.href), '_blank')">
                                <i class="fab fa-facebook-f"></i>
                            </button>
                            <button class="share-btn twitter" 
                                    onclick="window.open('https://twitter.com/intent/tweet?url=' + encodeURIComponent(window.location.href) + '&text=' + encodeURIComponent('<?php echo addslashes($news['title']); ?>'), '_blank')">
                                <i class="fab fa-twitter"></i>
                            </button>
                            <button class="share-btn whatsapp" 
                                    onclick="window.open('https://wa.me/?text=' + encodeURIComponent('<?php echo addslashes($news['title']); ?> ' + window.location.href), '_blank')">
                                <i class="fab fa-whatsapp"></i>
                            </button>
                        </div>
                    </div>
                </article>

                <!-- Yorumlar -->
                <section class="comments-section">
                    <h2 class="section-title">
                        <i class="fas fa-comments"></i>
                        Yorumlar (<?php echo count($comments); ?>)
                    </h2>

                    <?php if($comment_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $comment_message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if($comment_error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $comment_error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                        <!-- Yorum Formu -->
                        <form method="post" class="comment-form">
                            <div class="form-group">
                                <label for="comment">Yorumunuz</label>
                                <textarea name="comment" id="comment" required 
                                          placeholder="Yorumunuzu buraya yazın... (En az 10 karakter)"></textarea>
                            </div>
                            <button type="submit" name="add_comment" class="submit-btn">
                                <i class="fas fa-paper-plane"></i> Yorum Gönder
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-error">
                            <i class="fas fa-info-circle"></i>
                            Yorum yapabilmek için <a href="../Giris/login.php" style="color: var(--red); font-weight: 700;">giriş yapmalısınız</a>.
                        </div>
                    <?php endif; ?>

                    <!-- Yorumlar Listesi -->
                    <div class="comments-list">
                        <?php if(count($comments) > 0): ?>
                            <?php foreach($comments as $comment): ?>
                                <div class="comment-item">
                                    <div class="comment-header">
                                        <div class="comment-avatar">
                                            <?php echo strtoupper(substr($comment['full_name'] ?? $comment['username'], 0, 1)); ?>
                                        </div>
                                        <div class="comment-info">
                                            <h4><?php echo htmlspecialchars($comment['full_name'] ?? $comment['username']); ?></h4>
                                            <span><?php echo Helper::timeAgo($comment['created_at']); ?></span>
                                        </div>
                                    </div>
                                    <div class="comment-text">
                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-comments">
                                <i class="fas fa-comment-slash"></i>
                                <p>Henüz yorum yapılmamış. İlk yorumu siz yapın!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <!-- Sidebar -->
            <aside class="sidebar">
                <!-- İlgili Haberler -->
                <?php if(count($related_news) > 0): ?>
                    <div class="sidebar-card">
                        <h3 class="sidebar-title">
                            <i class="fas fa-newspaper"></i>
                            İlgili Haberler
                        </h3>
                        <?php foreach($related_news as $related): ?>
                            <div class="related-news-item" onclick="window.location.href='haber-detay.php?id=<?php echo $related['id']; ?>'">
                                <?php if(!empty($related['image'])): ?>
                                    <img src="../<?php echo htmlspecialchars($related['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($related['title']); ?>" 
                                         class="related-news-img">
                                <?php else: ?>
                                    <div class="related-news-img" style="background: linear-gradient(135deg, var(--dark), var(--green));"></div>
                                <?php endif; ?>
                                <div class="related-news-info">
                                    <h4>
                                        <a href="haber-detay.php?id=<?php echo $related['id']; ?>">
                                            <?php echo htmlspecialchars(Helper::excerpt($related['title'], 60)); ?>
                                        </a>
                                    </h4>
                                    <div class="related-news-meta">
                                        <span><i class="fas fa-calendar-alt"></i> 
                                            <?php echo date('d.m.Y', strtotime($related['created_at'])); ?>
                                        </span>
                                        <span><i class="fas fa-eye"></i> 
                                            <?php echo Helper::formatNumber($related['views']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Hava Durumu -->
                <div class="sidebar-card">
                    <h3 class="sidebar-title">
                        <i class="fas fa-cloud-sun"></i>
                        Hava Durumu
                    </h3>
                    <div class="api-data-card">
                        <div class="weather-display" id="sidebar-weather">
                            <div style="text-align: center; padding: 20px; color: var(--gray);">
                                <i class="fas fa-cloud-sun weather-icon-large"></i>
                                <p style="font-size: 12px;">Hava durumu yükleniyor...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Süper Lig Puan Durumu -->
                <?php if ($puan_durumu && isset($puan_durumu->takim)): ?>
                    <div class="sidebar-card">
                        <h3 class="sidebar-title">
                            <i class="fas fa-trophy"></i>
                            Süper Lig
                        </h3>
                        <div class="api-data-card">
                            <div style="overflow-x: auto;">
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
                                        $counter = 0;
                                        foreach ($puan_durumu->takim as $takim) {
                                            $counter++;
                                            $siralama_class = '';
                                            if ($counter <= 3) {
                                                if ($counter == 1) $siralama_class = 'siralama-1';
                                                elseif ($counter == 2) $siralama_class = 'siralama-2';
                                                elseif ($counter == 3) $siralama_class = 'siralama-3';
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
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Fikstür -->
                <?php if ($fikstur_data && isset($fikstur_data['maclar'])): ?>
                    <div class="sidebar-card">
                        <h3 class="sidebar-title">
                            <i class="fas fa-futbol"></i>
                            Son Maçlar
                        </h3>
                        <div class="api-data-card">
                            <div class="fikstur-kartlari">
                                <?php
                                $maclar = array_slice(array_reverse($fikstur_data['maclar']), 0, 5);
                                foreach ($maclar as $mac) {
                                    echo "<div class='fikstur-kart'>";
                                    echo "<div class='fikstur-tarih'>{$mac['tarih']}</div>";
                                    
                                    if (isset($mac['skor'])) {
                                        list($ev_skor, $dep_skor) = explode('-', $mac['skor']);
                                        echo "<div class='mac-satiri'>";
                                        echo "<span>{$mac['evSahibi']}</span>";
                                        echo "<span class='mac-skoru'>{$mac['skor']}</span>";
                                        echo "<span>{$mac['deplasman']}</span>";
                                        echo "</div>";
                                    } else {
                                        echo "<div class='mac-satiri'>";
                                        echo "<span>{$mac['evSahibi']}</span>";
                                        echo "<span class='mac-skoru'>VS</span>";
                                        echo "<span>{$mac['deplasman']}</span>";
                                        echo "</div>";
                                    }
                                    
                                    echo "</div>";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Reklam Alanı -->
                <div class="sidebar-card">
                    <h3 class="sidebar-title">
                        <i class="fas fa-ad"></i>
                        Reklam
                    </h3>
                    <div style="text-align: center; padding: 20px; background: var(--surface); border-radius: 12px;">
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
                        <li><a href="../hakkimizda.php">Hakkımızda</a></li>
                        <li><a href="../iletisim.php">İletişim</a></li>
                        <li><a href="../kariyer.php">Kariyer</a></li>
                        <li><a href="../reklam.php">Reklam</a></li>
                        <li><a href="../kunye.php">Künye</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Kategoriler</h3>
                    <ul class="footer-links">
                        <li><a href="../index.php?category=1">Gündem</a></li>
                        <li><a href="../index.php?category=2">Spor</a></li>
                        <li><a href="../index.php?category=3">Magazin</a></li>
                        <li><a href="../index.php?category=4">Teknoloji</a></li>
                        <li><a href="../index.php?category=5">Ekonomi</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Yardım</h3>
                    <ul class="footer-links">
                        <li><a href="../sss.php">SSS</a></li>
                        <li><a href="../kullanim.php">Kullanım Koşulları</a></li>
                        <li><a href="../gizlilik.php">Gizlilik Politikası</a></li>
                        <li><a href="../cerez.php">Çerez Politikası</a></li>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Sync dark mode class to html element
            if (document.body.classList.contains('dark-mode')) {
                document.documentElement.classList.add('dark-mode');
            }
            
            // Current time update
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
            
            // Update time every second
            updateTime();
            setInterval(updateTime, 1000);

            // Arama fonksiyonları
            function performSearch() {
                const searchTerm = document.querySelector('.search-input')?.value.trim() || 
                                 document.querySelector('.mobile-search-input')?.value.trim();
                if (searchTerm) {
                    window.location.href = `../Veri/arama.php?q=${encodeURIComponent(searchTerm)}`;
                }
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

            // Hava Durumu Güncelleme
            function updateSidebarWeather() {
                const weatherContainer = document.getElementById('sidebar-weather');
                if (!weatherContainer) return;

                // İstanbul için hava durumu çek
                fetch('../api/hava-durumu.php?city=istanbul')
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.success) {
                            const weather = data.data[0];
                            const weatherIcon = getWeatherIcon(weather.icon);
                            const weatherText = formatWeatherText(weather.flaticon);
                            
                            weatherContainer.innerHTML = `
                                <div class="weather-display">
                                    <i class="${weatherIcon} weather-icon-large"></i>
                                    <div class="weather-temp">${weather.max}°C</div>
                                    <div class="weather-desc">${weatherText}</div>
                                    <div style="font-size: 0.8rem; color: var(--gray); margin-top: 5px;">İstanbul</div>
                                </div>
                            `;
                        } else {
                            weatherContainer.innerHTML = `
                                <div style="text-align: center; padding: 20px; color: var(--gray);">
                                    <i class="fas fa-wifi weather-icon-large"></i>
                                    <p style="font-size: 12px;">Hava durumu alınamadı</p>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Hava durumu hatası:', error);
                        weatherContainer.innerHTML = `
                            <div style="text-align: center; padding: 20px; color: var(--gray);">
                                <i class="fas fa-exclamation-triangle weather-icon-large"></i>
                                <p style="font-size: 12px;">Hava durumu yüklenemedi</p>
                            </div>
                        `;
                    });
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

            function formatWeatherText(flaticon) {
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
                    'night-fog': 'Sisli'
                };
                return translations[flaticon] || 'Açık';
            }

            // Navigasyon Barı Hava Durumu Animasyonu
            const navWeather = document.getElementById('nav-weather');
            const cities = ['diyarbakir', 'istanbul', 'ankara', 'antalya', 'izmir', 'trabzon', 'bursa'];
            let currentCityIndex = 0;
            
            function updateNavWeather() {
                if (navWeather && cities.length > 0) {
                    const currentCity = cities[currentCityIndex];
                    
                    // Fade out
                    navWeather.style.opacity = '0';
                    
                    setTimeout(() => {
                        fetch(`../api/hava-durumu.php?city=${currentCity}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data && data.success) {
                                    const weather = data.data[0];
                                    const weatherIcon = getWeatherIcon(weather.icon);
                                    const cityName = currentCity.charAt(0).toUpperCase() + currentCity.slice(1);
                                    
                                    navWeather.innerHTML = `
                                        <span class='city-name'>${cityName}</span>
                                        <i class='${weatherIcon} weather-icon'></i>
                                        <span class='temperature'>${weather.max}°C</span>
                                    `;
                                }
                            })
                            .catch(error => {
                                console.error('Nav weather error:', error);
                            });
                        
                        // Fade in
                        navWeather.style.opacity = '1';
                    }, 200);
                    
                    // Next city
                    currentCityIndex = (currentCityIndex + 1) % cities.length;
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

            // Add ripple effect to buttons and cards
            const clickableElements = document.querySelectorAll('.share-btn, .submit-btn, .category-badge, .related-news-item, .fikstur-kart');
            clickableElements.forEach(element => {
                element.style.position = 'relative';
                element.style.overflow = 'hidden';
                element.addEventListener('click', createRipple);
            });

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

            // Initialize scroll animations
            const animatedElements = document.querySelectorAll('.news-detail-container, .comments-section, .sidebar-card, .news-title, .news-meta, .news-source-info, .featured-image-container, .news-content, .news-footer, .comment-form, .comment-item, .api-data-card');
            animatedElements.forEach((element, index) => {
                element.classList.add('scroll-fade');
                element.style.transitionDelay = `${index * 0.1}s`;
                fadeInObserver.observe(element);
            });

            // Add hover sound effect (optional visual feedback)
            const hoverElements = document.querySelectorAll('.nav-links a, .header-link, .footer-links a, .related-news-item');
            hoverElements.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    this.style.transition = 'all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
                });
            });

            // API verilerini yükle
            updateSidebarWeather();
            
            // Navigasyon hava durumu rotasyonu
            if (cities.length > 0) {
                setInterval(updateNavWeather, 10000); // 10 saniyede bir
            }

            // Console welcome message with style
            console.log('%c✨ ONVIBES - Premium Haber Detay Sayfası ✨', 
                'color: #d2232a; font-size: 20px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);');
            console.log('%cSayfa başarıyla yüklendi! API entegrasyonu aktif. 🚀', 
                'color: #0a8c2f; font-size: 14px; font-weight: bold;');
        });

        // Add CSS for ripple effect
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
    </script>
</body>
</html>
