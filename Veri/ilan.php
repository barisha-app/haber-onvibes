<?php
// ilan.php - İlan/Duyuru Listesi Sayfası (Premium Animasyonlu)
session_start();

include '../config.php';
$database = new Database();
$db = $database->getConnection();

// Karanlık mod kontrolü
if (isset($_POST['toggle_theme'])) {
    $_SESSION['dark_mode'] = !isset($_SESSION['dark_mode']) || !$_SESSION['dark_mode'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
$dark_mode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'];

// Filtreleme
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Sayfalama
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// İlanları çek
try {
    // Toplam sayı için sorgu
    $count_query = "SELECT COUNT(*) as total FROM ilanlar WHERE status = 'active'";
    
    if(!empty($filter_type)) {
        $count_query .= " AND type = :type";
    }
    
    if(!empty($filter_priority)) {
        $count_query .= " AND priority = :priority";
    }
    
    if(!empty($filter_date)) {
        $count_query .= " AND DATE(created_at) = :filter_date";
    }
    
    if(!empty($search)) {
        $count_query .= " AND (title LIKE :search OR content LIKE :search)";
    }
    
    $count_stmt = $db->prepare($count_query);
    
    if(!empty($filter_type)) {
        $count_stmt->bindParam(':type', $filter_type);
    }
    
    if(!empty($filter_priority)) {
        $count_stmt->bindParam(':priority', $filter_priority);
    }
    
    if(!empty($filter_date)) {
        $count_stmt->bindParam(':filter_date', $filter_date);
    }
    
    if(!empty($search)) {
        $search_term = '%' . $search . '%';
        $count_stmt->bindParam(':search', $search_term);
    }
    
    $count_stmt->execute();
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_count / $per_page);
    
    // İlanlar için sorgu
    $query = "SELECT a.*, u.username, u.full_name 
              FROM ilanlar a
              LEFT JOIN users u ON a.created_by = u.id
              WHERE a.status = 'active'";
    
    if(!empty($filter_type)) {
        $query .= " AND a.type = :type";
    }
    
    if(!empty($filter_priority)) {
        $query .= " AND a.priority = :priority";
    }
    
    if(!empty($filter_date)) {
        $query .= " AND DATE(a.created_at) = :filter_date";
    }
    
    if(!empty($search)) {
        $query .= " AND (a.title LIKE :search OR a.content LIKE :search)";
    }
    
    $query .= " ORDER BY a.priority DESC, a.created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    
    if(!empty($filter_type)) {
        $stmt->bindParam(':type', $filter_type);
    }
    
    if(!empty($filter_priority)) {
        $stmt->bindParam(':priority', $filter_priority);
    }
    
    if(!empty($filter_date)) {
        $stmt->bindParam(':filter_date', $filter_date);
    }
    
    if(!empty($search)) {
        $search_term = '%' . $search . '%';
        $stmt->bindParam(':search', $search_term);
    }
    
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $ilanlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $ilanlar = [];
    $total_count = 0;
    $total_pages = 0;
    $error = 'İlanlar yüklenirken hata oluştu: ' . $e->getMessage();
}

// İstatistikler
try {
    $stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN type = 'announcement' THEN 1 ELSE 0 END) as announcement_count,
                    SUM(CASE WHEN type = 'event' THEN 1 ELSE 0 END) as event_count,
                    SUM(CASE WHEN type = 'notice' THEN 1 ELSE 0 END) as notice_count,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority
                    FROM ilanlar
                    WHERE status = 'active'";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $stats = ['total' => 0, 'announcement_count' => 0, 'event_count' => 0, 'notice_count' => 0, 'high_priority' => 0];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İlanlar ve Duyurular - ONVIBES</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --red: #e50914;
            --dark: #141414;
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
            animation: fadeInUp 0.6s ease-out;
        }

        .dark-mode body {
            background: #000000 !important;
            background-attachment: fixed;
            color: #e0e0e0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Header */
        .main-menu {
            background: var(--light);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
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
            padding: 10px 0;
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
            gap: 15px;
        }

        .logo-text {
            color: white;
            font-size: 24px;
            font-weight: 800;
            text-transform: uppercase;
            text-decoration: none;
            background: linear-gradient(45deg, #ffffff, #ff6b6b, #ffffff);
            background-size: 200% 200%;
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer-text 3s ease-in-out infinite;
        }

        @keyframes shimmer-text {
            0%, 100% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
        }

        .top-links {
            display: flex;
            gap: 10px;
            align-items: center;
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
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
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
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
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

        /* Main Content */
        .main-content {
            padding: 30px 0;
        }

        .page-header {
            margin-bottom: 30px;
            animation: fadeInUp 0.8s ease-out;
        }

        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }

        .dark-mode .page-title {
            color: #ffffff;
        }

        .page-title i {
            color: var(--red);
            animation: bounce 2s ease-in-out infinite;
        }

        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            color: var(--gray);
            font-size: 14px;
        }

        .breadcrumb a {
            color: var(--red);
            text-decoration: none;
            transition: all 0.3s;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
            transform: translateX(2px);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--light);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out;
        }

        .dark-mode .stat-card {
            background: #0a0a0a;
            border: 1px solid #1a1a1a;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--red), #ff6b6b, var(--red));
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 28px;
            color: white;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.2) rotate(10deg);
        }

        .stat-icon.red {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #3b5998 0%, #2d4373 100%);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #ff9800 0%, #e68900 100%);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 5px;
            background: linear-gradient(135deg, var(--red), var(--orange));
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: gradientShift 3s ease infinite;
        }

        .dark-mode .stat-number {
            color: #ffffff;
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray);
            font-weight: 600;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
        }

        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .sidebar-card {
            background: var(--light);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            animation: fadeInLeft 0.8s ease-out;
        }

        .dark-mode .sidebar-card {
            background: #0a0a0a;
            border: 1px solid #1a1a1a;
        }

        .sidebar-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--red), var(--orange));
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--red);
            position: relative;
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

        /* Filters */
        .filters {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--dark);
        }

        .dark-mode .filter-group label {
            color: #ffffff;
        }

        .filter-group input,
        .filter-group select {
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text);
            font-size: 13px;
            transition: all 0.3s;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.1);
            transform: scale(1.02);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-filter {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            overflow: hidden;
        }

        .btn-apply {
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
        }

        .btn-apply:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 20px rgba(229, 9, 20, 0.4);
        }

        .btn-reset {
            background: var(--surface);
            color: var(--text);
            border: 2px solid var(--border);
        }

        .btn-reset:hover {
            background: var(--border);
            transform: translateY(-2px);
        }

        /* Announcements Grid */
        .ilanlar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .announcement-card {
            background: var(--light);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out;
            border: 1px solid transparent;
        }

        .dark-mode .announcement-card {
            background: #0a0a0a;
            border: 1px solid #1a1a1a;
        }

        .announcement-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--red);
            transition: all 0.3s;
        }

        .announcement-card.high-priority::before {
            background: linear-gradient(180deg, #ff0000 0%, #ff6b6b 100%);
            width: 8px;
            animation: glowPulse 2s ease-in-out infinite;
        }

        .announcement-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            border-color: rgba(229, 9, 20, 0.2);
        }

        .announcement-card:hover::before {
            width: 8px;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            gap: 10px;
        }

        .announcement-type {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            transition: all 0.3s;
        }

        .announcement-card:hover .announcement-type {
            transform: scale(1.1);
        }

        .type-announcement {
            background: #d1ecf1;
            color: #0c5460;
        }

        .type-event {
            background: #fff3cd;
            color: #856404;
        }

        .type-notice {
            background: #d4edda;
            color: #155724;
        }

        .priority-badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            transition: all 0.3s;
        }

        .announcement-card:hover .priority-badge {
            transform: scale(1.1);
        }

        .priority-high {
            background: #f8d7da;
            color: #721c24;
            animation: pulseScale 2s ease-in-out infinite;
        }

        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }

        .priority-low {
            background: #d4edda;
            color: #155724;
        }

        .announcement-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 12px;
            line-height: 1.4;
            transition: color 0.3s;
        }

        .dark-mode .announcement-title {
            color: #ffffff;
        }

        .announcement-card:hover .announcement-title {
            color: var(--red);
        }

        .announcement-content {
            color: var(--text);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .announcement-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 2px solid var(--border);
            font-size: 12px;
            color: var(--gray);
        }

        .announcement-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .announcement-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .announcement-card:hover .announcement-meta span {
            transform: translateX(3px);
        }

        .announcement-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--red) 0%, #b81d24 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .announcement-card:hover .announcement-icon {
            transform: scale(1.2) rotate(10deg);
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
        }

        .pagination a,
        .pagination span {
            padding: 12px 18px;
            border-radius: 10px;
            background: var(--light);
            color: var(--text);
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            overflow: hidden;
        }

        .pagination a:hover {
            background: var(--red);
            color: white;
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 6px 20px rgba(229, 9, 20, 0.4);
        }

        .pagination span.active {
            background: var(--red);
            color: white;
            animation: pulseScale 2s ease-in-out infinite;
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination .disabled:hover {
            background: var(--light);
            color: var(--text);
            transform: none;
        }

        /* No Results */
        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 30px;
            background: var(--light);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            animation: fadeInUp 0.8s ease-out;
        }

        .no-results i {
            font-size: 80px;
            color: var(--gray);
            opacity: 0.5;
            margin-bottom: 20px;
            animation: bounce 2s ease-in-out infinite;
        }

        .no-results h3 {
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .dark-mode .no-results h3 {
            color: #ffffff;
        }

        .no-results p {
            color: var(--gray);
            font-size: 14px;
        }

        /* === ADVANCED ANIMATIONS === */
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

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
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

        @keyframes glowPulse {
            0%, 100% {
                box-shadow: 0 0 5px rgba(255, 0, 0, 0.5);
            }
            50% {
                box-shadow: 0 0 20px rgba(255, 0, 0, 0.8);
            }
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
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

        /* Responsive */
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .sidebar {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .sidebar-card {
                flex: 1;
                min-width: 300px;
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .ilanlar-grid {
                grid-template-columns: 1fr;
            }

            .sidebar {
                flex-direction: column;
            }

            .sidebar-card {
                min-width: auto;
            }
        }

        @media (max-width: 480px) {
            .top-bar .container {
                flex-direction: column;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .announcement-header {
                flex-direction: column;
                align-items: start;
            }

            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <!-- Header -->
    <header class="main-menu">
        <div class="top-bar">
            <div class="container">
                <a href="../index.php" class="logo-text">ONVIBES</a>
                <div class="top-links">
                    <form method="post" style="display: inline;">
                        <button type="submit" name="toggle_theme" class="theme-toggle-btn">
                            <i class="fas fa-<?php echo $dark_mode ? 'sun' : 'moon'; ?>"></i>
                        </button>
                    </form>
                    <?php if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                        <a href="../profil.php" class="user-profile-btn">
                            <i class="fas fa-user"></i> Profil
                        </a>
                        <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'writer', 'reporter'])): ?>
                            <a href="ilan-edit.php" class="user-profile-btn">
                                <i class="fas fa-plus-circle"></i> Yeni İlan
                            </a>
                        <?php endif; ?>
                        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                            <a href="../admin.php" class="user-profile-btn">
                                <i class="fas fa-cog"></i> Admin
                            </a>
                        <?php endif; ?>
                        <a href="../Giris/logout.php" class="user-profile-btn">
                            <i class="fas fa-sign-out-alt"></i> Çıkış
                        </a>
                    <?php else: ?>
                        <a href="../Giris/login.php" class="user-profile-btn">
                            <i class="fas fa-sign-in-alt"></i> Giriş
                        </a>
                        <a href="../Giris/register.php" class="user-profile-btn">
                            <i class="fas fa-user-plus"></i> Kayıt
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-bullhorn"></i>
                    İlanlar ve Duyurular
                </h1>
                <div class="breadcrumb">
                    <a href="../index.php"><i class="fas fa-home"></i> Ana Sayfa</a>
                    <span>/</span>
                    <span>İlanlar</span>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Toplam İlan</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-megaphone"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['announcement_count']; ?></div>
                    <div class="stat-label">Duyuru</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['event_count']; ?></div>
                    <div class="stat-label">Etkinlik</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['high_priority']; ?></div>
                    <div class="stat-label">Yüksek Öncelik</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Sidebar -->
                <aside class="sidebar">
                    <!-- Filters -->
                    <div class="sidebar-card">
                        <h3 class="sidebar-title">
                            <i class="fas fa-filter"></i> Filtreler
                        </h3>
                        <form method="get" class="filters">
                            <div class="filter-group">
                                <label for="search">Ara</label>
                                <input type="text" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Başlık veya içerik...">
                            </div>
                            
                            <div class="filter-group">
                                <label for="type">Tür</label>
                                <select id="type" name="type">
                                    <option value="">Tümü</option>
                                    <option value="announcement" <?php echo $filter_type == 'announcement' ? 'selected' : ''; ?>>
                                        Duyuru
                                    </option>
                                    <option value="event" <?php echo $filter_type == 'event' ? 'selected' : ''; ?>>
                                        Etkinlik
                                    </option>
                                    <option value="notice" <?php echo $filter_type == 'notice' ? 'selected' : ''; ?>>
                                        Bildirim
                                    </option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="priority">Öncelik</label>
                                <select id="priority" name="priority">
                                    <option value="">Tümü</option>
                                    <option value="high" <?php echo $filter_priority == 'high' ? 'selected' : ''; ?>>
                                        Yüksek
                                    </option>
                                    <option value="medium" <?php echo $filter_priority == 'medium' ? 'selected' : ''; ?>>
                                        Orta
                                    </option>
                                    <option value="low" <?php echo $filter_priority == 'low' ? 'selected' : ''; ?>>
                                        Düşük
                                    </option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="date">Tarih</label>
                                <input type="date" id="date" name="date" 
                                       value="<?php echo htmlspecialchars($filter_date); ?>">
                            </div>
                            
                            <div class="filter-actions">
                                <button type="submit" class="btn-filter btn-apply">
                                    <i class="fas fa-search"></i> Filtrele
                                </button>
                                <a href="ilan.php" class="btn-filter btn-reset">
                                    <i class="fas fa-redo"></i> Sıfırla
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Info -->
                    <div class="sidebar-card">
                        <h3 class="sidebar-title">
                            <i class="fas fa-info-circle"></i> Bilgi
                        </h3>
                        <p style="font-size: 13px; color: var(--gray); line-height: 1.6;">
                            Bu sayfada tüm aktif ilanlar, duyurular ve etkinlikler listelenmektedir. 
                            Filtreleme seçeneklerini kullanarak aradığınız ilana kolayca ulaşabilirsiniz.
                        </p>
                    </div>
                </aside>

                <!-- Announcements -->
                <div>
                    <div class="ilanlar-grid">
                        <?php if(count($ilanlar) > 0): ?>
                            <?php foreach($ilanlar as $announcement): ?>
                                <div class="announcement-card <?php echo $announcement['priority'] == 'high' ? 'high-priority' : ''; ?>">
                                    <div class="announcement-header">
                                        <span class="announcement-type type-<?php echo $announcement['type']; ?>">
                                            <?php 
                                            $types = [
                                                'announcement' => 'Duyuru',
                                                'event' => 'Etkinlik',
                                                'notice' => 'Bildirim'
                                            ];
                                            echo $types[$announcement['type']] ?? $announcement['type'];
                                            ?>
                                        </span>
                                        <span class="priority-badge priority-<?php echo $announcement['priority']; ?>">
                                            <?php 
                                            $priorities = [
                                                'high' => 'Yüksek',
                                                'medium' => 'Orta',
                                                'low' => 'Düşük'
                                            ];
                                            echo $priorities[$announcement['priority']] ?? $announcement['priority'];
                                            ?>
                                        </span>
                                    </div>

                                    <h3 class="announcement-title">
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                    </h3>

                                    <div class="announcement-content">
                                        <?php 
                                        $content = strip_tags($announcement['content']);
                                        echo nl2br(htmlspecialchars(substr($content, 0, 150))) . (strlen($content) > 150 ? '...' : ''); 
                                        ?>
                                    </div>

                                    <div class="announcement-footer">
                                        <div class="announcement-meta">
                                            <span>
                                                <i class="fas fa-calendar-alt"></i>
                                                <?php echo date('d.m.Y H:i', strtotime($announcement['created_at'])); ?>
                                            </span>
                                            <span>
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($announcement['full_name'] ?? $announcement['username']); ?>
                                            </span>
                                        </div>
                                        <div class="announcement-icon">
                                            <i class="fas fa-<?php 
                                                echo $announcement['type'] == 'event' ? 'calendar-alt' : 
                                                     ($announcement['type'] == 'notice' ? 'bell' : 'bullhorn'); 
                                            ?>"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-results">
                                <i class="fas fa-inbox"></i>
                                <h3>İlan Bulunamadı</h3>
                                <p>Henüz aktif ilan bulunmuyor veya arama kriterleriyle eşleşen sonuç yok.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if($page > 1): ?>
                                <a href="?page=<?php echo ($page - 1); ?><?php echo !empty($filter_type) ? '&type=' . $filter_type : ''; ?><?php echo !empty($filter_priority) ? '&priority=' . $filter_priority : ''; ?><?php echo !empty($filter_date) ? '&date=' . $filter_date : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                    <i class="fas fa-chevron-left"></i> Önceki
                                </a>
                            <?php else: ?>
                                <span class="disabled"><i class="fas fa-chevron-left"></i> Önceki</span>
                            <?php endif; ?>

                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if($i == $page): ?>
                                    <span class="active"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?><?php echo !empty($filter_type) ? '&type=' . $filter_type : ''; ?><?php echo !empty($filter_priority) ? '&priority=' . $filter_priority : ''; ?><?php echo !empty($filter_date) ? '&date=' . $filter_date : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if($page < $total_pages): ?>
                                <a href="?page=<?php echo ($page + 1); ?><?php echo !empty($filter_type) ? '&type=' . $filter_type : ''; ?><?php echo !empty($filter_priority) ? '&priority=' . $filter_priority : ''; ?><?php echo !empty($filter_date) ? '&date=' . $filter_date : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                    Sonraki <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <span class="disabled">Sonraki <i class="fas fa-chevron-right"></i></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
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

        // Ripple Effect
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

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            // Sync dark mode class to html element
            if (document.body.classList.contains('dark-mode')) {
                document.documentElement.classList.add('dark-mode');
            }
            
            // Initialize scroll animations
            const announcementCards = document.querySelectorAll('.announcement-card, .stat-card, .sidebar-card');
            announcementCards.forEach((card, index) => {
                card.classList.add('scroll-fade');
                card.style.transitionDelay = `${index * 0.05}s`;
                fadeInObserver.observe(card);
            });

            // Add ripple effect to buttons
            const clickableElements = document.querySelectorAll('.btn-filter, .pagination a, .announcement-card');
            clickableElements.forEach(element => {
                element.style.position = 'relative';
                element.style.overflow = 'hidden';
                element.addEventListener('click', createRipple);
            });

            // Add hover effects to filter inputs
            const filterInputs = document.querySelectorAll('.filter-group input, .filter-group select');
            filterInputs.forEach(input => {
                input.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                input.addEventListener('mouseleave', function() {
                    if (document.activeElement !== this) {
                        this.style.transform = 'scale(1)';
                    }
                });
            });

            // Console welcome message
            console.log('%c📢 ONVIBES - İlanlar ve Duyurular 📢', 
                'color: #e50914; font-size: 20px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);');
            console.log('%cPremium animasyonlar aktif! 🚀', 
                'color: #0a8c2f; font-size: 14px; font-weight: bold;');
        });
    </script>
</body>
</html>
