-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Anamakine: localhost:3306
-- Üretim Zamanı: 14 Kas 2025, 17:03:13
-- Sunucu sürümü: 5.5.68-MariaDB-cll-lve
-- PHP Sürümü: 8.4.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `onvibes_online_barisha`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `description` text,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Tablo döküm verisi `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `created_at`) VALUES
(1, 'Gündem', 'gundem', 'Güncel haberler ve gelişmeler', '2025-11-03 02:52:18'),
(2, 'Spor', 'spor', 'Spor haberleri ve maç sonuçları', '2025-11-03 02:52:18'),
(3, 'Magazin', 'magazin', 'Magazin ve eğlence dünyası', '2025-11-03 02:52:18'),
(4, 'Teknoloji', 'teknoloji', 'Teknoloji haberleri ve incelemeler', '2025-11-03 02:52:18'),
(5, 'Ekonomi', 'ekonomi', 'Ekonomi ve finans haberleri', '2025-11-03 02:52:18'),
(6, 'Sağlık', 'saglik', 'Sağlık ve yaşam haberleri', '2025-11-03 02:52:18'),
(7, 'Dünya', 'dunya', NULL, NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `chat_media`
--

CREATE TABLE `chat_media` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Tablo döküm verisi `chat_media`
--

INSERT INTO `chat_media` (`id`, `room_id`, `user_id`, `file_name`, `file_path`, `file_type`, `file_size`, `created_at`) VALUES
(2, 9, 9, '690d1c2762c21_yesil-768x512.jpg', '../uploads/chat_media/690d1c2762c21_yesil-768x512.jpg', 'image/jpeg', 25952, '2025-11-06 22:07:35'),
(3, 10, 1, '690df9e32e998_IMG-20251107-WA0001.jpeg', '../uploads/chat_media/690df9e32e998_IMG-20251107-WA0001.jpeg', 'image/jpeg', 1354720, '2025-11-07 13:53:39'),
(4, 10, 1, '690df9e670422_IMG-20251107-WA0001.jpeg', '../uploads/chat_media/690df9e670422_IMG-20251107-WA0001.jpeg', 'image/jpeg', 1354720, '2025-11-07 13:53:42'),
(5, 10, 1, '690df9e695093_IMG-20251107-WA0001.jpeg', '../uploads/chat_media/690df9e695093_IMG-20251107-WA0001.jpeg', 'image/jpeg', 1354720, '2025-11-07 13:53:42'),
(6, 10, 1, '690df9fd62bbb_IMG-20251107-WA0001.jpeg', '../uploads/chat_media/690df9fd62bbb_IMG-20251107-WA0001.jpeg', 'image/jpeg', 1354720, '2025-11-07 13:54:05'),
(7, 10, 1, '690df9fdea8b2_IMG-20251107-WA0001.jpeg', '../uploads/chat_media/690df9fdea8b2_IMG-20251107-WA0001.jpeg', 'image/jpeg', 1354720, '2025-11-07 13:54:05'),
(8, 10, 1, '690df9fe290a3_IMG-20251107-WA0001.jpeg', '../uploads/chat_media/690df9fe290a3_IMG-20251107-WA0001.jpeg', 'image/jpeg', 1354720, '2025-11-07 13:54:06');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `room_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `media_id` int(11) DEFAULT NULL,
  `message_type` enum('text','image','video','file') DEFAULT 'text'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `chat_rooms`
--

CREATE TABLE `chat_rooms` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `max_users` int(11) DEFAULT '50',
  `is_private` tinyint(1) DEFAULT '0',
  `room_password` varchar(255) DEFAULT NULL,
  `allow_media` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `chat_room_users`
--

CREATE TABLE `chat_room_users` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `news_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `status` enum('active','pending','deleted') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `discussions`
--

CREATE TABLE `discussions` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `discussion_comments`
--

CREATE TABLE `discussion_comments` (
  `id` int(11) NOT NULL,
  `discussion_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `discussion_likes`
--

CREATE TABLE `discussion_likes` (
  `id` int(11) NOT NULL,
  `discussion_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `ilanlar`
--

CREATE TABLE `ilanlar` (
  `id` int(11) NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('duyuru','etkinlik','bildirim') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'duyuru',
  `priority` enum('düşük','orta','yüksek') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'orta',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `author_id` int(11) NOT NULL,
  `views` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `ilanlar`
--

INSERT INTO `ilanlar` (`id`, `title`, `content`, `type`, `priority`, `status`, `author_id`, `views`, `created_at`, `updated_at`) VALUES
(1, 'Sistem Yenilendi!', 'Web sitemiz yeni tasarımı ile sizlerle! Daha hızlı ve kullanışlı bir deneyim için sistemimizi güncelledik.', 'duyuru', 'yüksek', 'active', 1, 0, '2025-11-14 02:16:47', NULL),
(2, 'Editör Toplantısı', 'Tüm editörler ve yazarlar için toplantı 15 Kasım 2025 tarihinde saat 14:00\'da yapılacaktır.', 'etkinlik', 'orta', 'active', 1, 0, '2025-11-14 02:16:47', NULL),
(3, 'Yeni Kategori Eklendi', 'Teknoloji ve Sağlık kategorileri sitemize eklendi. Bu kategorilerde yazı yazabilirsiniz.', 'bildirim', 'düşük', 'active', 1, 0, '2025-11-14 02:16:47', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kose_yazisi`
--

CREATE TABLE `kose_yazisi` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `author_id` int(11) DEFAULT NULL,
  `author_name` varchar(100) NOT NULL,
  `author_avatar` varchar(255) DEFAULT NULL,
  `summary` text,
  `featured_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `is_featured` tinyint(1) DEFAULT '0',
  `view_count` int(11) DEFAULT '0',
  `like_count` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Tablo döküm verisi `kose_yazisi`
--

INSERT INTO `kose_yazisi` (`id`, `title`, `content`, `author_id`, `author_name`, `author_avatar`, `summary`, `featured_image`, `status`, `is_featured`, `view_count`, `like_count`, `created_at`, `updated_at`) VALUES
(1, 'Türkiye Ekonomisinde Yeni Dönem', 'Türkiye ekonomisi son dönemde önemli gelişmeler kaydediyor. Dijital dönüşüm ve teknolojik yenilikler ülke ekonomisine yeni bir soluk getiriyor. Bu yazıda ekonomik gelişmeleri ve gelecek projeksiyonlarını değerlendireceğiz.', NULL, 'Ekonomi Uzmanı', NULL, 'Ekonomik analiz ve değerlendirmeler', NULL, 'approved', 0, 0, 0, '2025-11-09 02:12:10', NULL),
(2, 'Spor Dünyasında Yenilikçi Yaklaşımlar', 'Modern spor anlayışı artık sadece fiziksel performansla sınırlı değil. Mental dayanıklılık, teknoloji entegrasyonu ve veri analizi sporcuların performansını yeni seviyelere taşıyor.', NULL, 'Spor Analisti', NULL, 'Spor teknolojileri ve modern antrenman teknikleri', NULL, 'approved', 0, 0, 0, '2025-11-09 02:12:10', NULL),
(3, 'Teknoloji ve Toplum İlişkisi', 'Yapay zeka, blockchain ve IoT gibi teknolojiler toplumsal yaşamı kökten değiştiriyor. Bu değişim sürecinde etik değerleri korumanın önemi her geçen gün artıyor.', NULL, 'Teknoloji Yazarı', NULL, 'Teknolojinin toplumsal etkileri ve gelecek senaryoları', NULL, 'approved', 0, 0, 0, '2025-11-09 02:12:10', NULL),
(4, 'Türkiye Ekonomisinde Yeni Dönem', 'Türkiye ekonomisi son dönemde önemli gelişmeler kaydediyor. Dijital dönüşüm ve teknolojik yenilikler ülke ekonomisine yeni bir soluk getiriyor. Bu yazıda ekonomik gelişmeleri ve gelecek projeksiyonlarını değerlendireceğiz.', NULL, 'Ekonomi Uzmanı', NULL, 'Ekonomik analiz ve değerlendirmeler', NULL, 'approved', 0, 0, 0, '2025-11-09 02:12:31', NULL),
(5, 'Spor Dünyasında Yenilikçi Yaklaşımlar', 'Modern spor anlayışı artık sadece fiziksel performansla sınırlı değil. Mental dayanıklılık, teknoloji entegrasyonu ve veri analizi sporcuların performansını yeni seviyelere taşıyor.', NULL, 'Spor Analisti', NULL, 'Spor teknolojileri ve modern antrenman teknikleri', NULL, 'approved', 0, 0, 0, '2025-11-09 02:12:31', NULL),
(6, 'Teknoloji ve Toplum İlişkisi', 'Yapay zeka, blockchain ve IoT gibi teknolojiler toplumsal yaşamı kökten değiştiriyor. Bu değişim sürecinde etik değerleri korumanın önemi her geçen gün artıyor.', NULL, 'Teknoloji Yazarı', NULL, 'Teknolojinin toplumsal etkileri ve gelecek senaryoları', NULL, 'approved', 0, 0, 0, '2025-11-09 02:12:31', NULL),
(7, 'Türkiye Ekonomisinde Yeni Dönem', 'Türkiye ekonomisi son dönemde önemli gelişmeler kaydediyor. Dijital dönüşüm ve teknolojik yenilikler ülke ekonomisine yeni bir soluk getiriyor. Bu yazıda ekonomik gelişmeleri ve gelecek projeksiyonlarını değerlendireceğiz.', NULL, 'Ekonomi Uzmanı', NULL, 'Ekonomik analiz ve değerlendirmeler', NULL, 'approved', 0, 0, 0, '2025-11-09 02:33:15', NULL),
(8, 'Spor Dünyasında Yenilikçi Yaklaşımlar', 'Modern spor anlayışı artık sadece fiziksel performansla sınırlı değil. Mental dayanıklılık, teknoloji entegrasyonu ve veri analizi sporcuların performansını yeni seviyelere taşıyor.', NULL, 'Spor Analisti', NULL, 'Spor teknolojileri ve modern antrenman teknikleri', NULL, 'approved', 0, 0, 0, '2025-11-09 02:33:15', NULL),
(9, 'Teknoloji ve Toplum İlişkisi', 'Yapay zeka, blockchain ve IoT gibi teknolojiler toplumsal yaşamı kökten değiştiriyor. Bu değişim sürecinde etik değerleri korumanın önemi her geçen gün artıyor.', NULL, 'Teknoloji Yazarı', NULL, 'Teknolojinin toplumsal etkileri ve gelecek senaryoları', NULL, 'approved', 0, 0, 0, '2025-11-09 02:33:15', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `likes`
--

CREATE TABLE `likes` (
  `id` int(11) NOT NULL,
  `news_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `summary` text,
  `image` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `view_count` int(11) DEFAULT '0',
  `like_count` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `source` varchar(255) DEFAULT NULL,
  `author_name` varchar(255) DEFAULT NULL,
  `tags` text,
  `image_size` varchar(50) DEFAULT 'medium',
  `text_color` varchar(7) DEFAULT NULL,
  `bg_color` varchar(7) DEFAULT NULL,
  `likes` int(11) DEFAULT '0',
  `views` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Tablo döküm verisi `news`
--

INSERT INTO `news` (`id`, `title`, `content`, `summary`, `image`, `category_id`, `author_id`, `status`, `view_count`, `like_count`, `created_at`, `updated_at`, `source`, `author_name`, `tags`, `image_size`, `text_color`, `bg_color`, `likes`, `views`) VALUES
(12, 'Kurtalan’da polis olası faciayı önledi', '**Siirt’in Kurtalan** ilçesi *15 Temmuz Demokrasi Parkı*’nda elindeki bıçakla çevredekilere yönelen 25 yaşındaki A.E., polis ekiplerinin hızlı müdahalesiyle etkisiz hale getirildi.\r\n\r\n*15 Temmuz Demokrasi Parkı*’nda bir gencin elindeki bıçakla vatandaşlara yönelmesi üzerine panik yaşandı. Çevrede bulunanların ihbarıyla olay yerine polis ve sağlık ekipleri sevk edildi.\r\n\r\n Gencin elindeki bıçakla vatandaşlara yaklaşması üzerine harekete geçen polis ekipleri, şahsı kontrol altına alarak gözaltına aldı. Olayda yaralanan olmadığı öğrenildi.\r\n\r\nOlay yerinde güvenlik önlemleri alınırken, parkta bulunan vatandaşlar polisin zamanında müdahalesiyle olası bir olayın yaşanmadan sorunun çözüldüğünü ifade etti.', 'Siirt’in Kurtalan ilçesi 15 Temmuz Demokrasi Parkı’nda elindeki bıçakla çevredekilere yönelen 25 yaşındaki A.E., polis ekiplerinin hızlı müdahalesiyle etkisiz hale getirildi.', 'https://github.com/barisha-app/onvibes.online/blob/main/haber/453397.png?raw=true', 1, 1, 'approved', 74, 0, '2025-11-06 02:17:20', '2025-11-09 09:59:33', NULL, NULL, NULL, 'medium', NULL, NULL, 0, 32),
(13, 'Kurtalan’da polis olası faciayı önledi', '**Siirt**’in **Kurtalan** ilçesi *15 Temmuz Demokrasi Parkı*’nda elindeki bıçakla çevredekilere yönelen 25 yaşındaki A.E., polis ekiplerinin hızlı müdahalesiyle etkisiz hale getirildi. \r\n\r\n*15 Temmuz Demokrasi Parkı*’nda bir gencin elindeki bıçakla vatandaşlara yönelmesi üzerine panik yaşandı. Çevrede bulunanların ihbarıyla olay yerine polis ve sağlık ekipleri sevk edildi.\r\n\r\nGencin elindeki bıçakla vatandaşlara yaklaşması üzerine harekete geçen polis ekipleri, şahsı kontrol altına alarak gözaltına aldı. Olayda yaralanan olmadığı öğrenildi. \r\n\r\nOlay yerinde güvenlik önlemleri alınırken, parkta bulunan vatandaşlar polisin zamanında müdahalesiyle olası bir olayın yaşanmadan sorunun çözüldüğünü ifade etti.', 'Siirt’in Kurtalan ilçesi 15 Temmuz Demokrasi Parkı’nda elindeki bıçakla çevredekilere yönelen 25 yaşındaki A.E., polis ekiplerinin hızlı müdahalesiyle etkisiz hale getirildi.', 'https://github.com/barisha-app/onvibes.online/blob/main/haber/453397.png?raw=true', 1, 1, 'rejected', 4, 0, '2025-11-06 02:36:41', '2025-11-09 09:59:33', 'Onvibes.online', 'barisha', '#kurtalan #siirt', 'medium', '#2c3e50', '#ffffff', 0, 13),
(14, 'Kurtalan’da polis olası faciayı önledi', '**Siirt’in Kurtalan** ilçesi *15 Temmuz Demokrasi Parkı*’nda elindeki bıçakla çevredekilere yönelen 25 yaşındaki A.E., polis ekiplerinin hızlı müdahalesiyle etkisiz hale getirildi. \r\n\r\n*15 Temmuz Demokrasi Parkı*’nda bir gencin elindeki bıçakla vatandaşlara yönelmesi üzerine panik yaşandı. Çevrede bulunanların ihbarıyla olay yerine polis ve sağlık ekipleri sevk edildi.\r\n\r\nGencin elindeki bıçakla vatandaşlara yaklaşması üzerine harekete geçen polis ekipleri, şahsı kontrol altına alarak gözaltına aldı. \r\nOlayda yaralanan olmadığı öğrenildi. \r\n\r\nOlay yerinde güvenlik önlemleri alınırken, parkta bulunan vatandaşlar polisin zamanında müdahalesiyle olası bir olayın yaşanmadan sorunun çözüldüğünü ifade etti.', 'Siirt’in Kurtalan ilçesi 15 Temmuz Demokrasi Parkı’nda elindeki bıçakla çevredekilere yönelen 25 yaşındaki A.E., polis ekiplerinin hızlı müdahalesiyle etkisiz hale getirildi.', 'https://github.com/barisha-app/onvibes.online/blob/main/haber/453397.png?raw=true', 1, 1, 'rejected', 7, 0, '2025-11-06 02:46:26', '2025-11-09 09:59:33', 'Onvibes.online', 'barisha', '#kurtalan #siirt', 'medium', '#2c3e50', '#ffffff', 0, 6),
(15, 'Test Haberi', 'Test haber içeriği', 'Bu bir test haberidir', NULL, 1, 1, 'approved', 2, 0, '2025-11-09 09:59:33', '2025-11-09 09:59:33', NULL, NULL, NULL, 'medium', NULL, NULL, 0, 11),
(16, 'Test Haberi Başlığı', 'Bu haberin detaylı içeriği. İçerik buraya yazılacak.\r\n\r\n## Alt Başlık\r\nDetaylı açıklamalar...\r\n\r\n**Önemli noktalar:**\r\n- Madde 1\r\n- Madde 2\r\n- Madde 3', 'Bu haberin kısa özeti', NULL, 1, 1, 'approved', 1, 0, '2025-11-09 09:59:33', '2025-11-09 09:59:33', NULL, NULL, NULL, 'medium', NULL, NULL, 0, 3),
(17, 'Test Haberi', 'Test içerik', 'Test özet', NULL, 1, 1, 'rejected', 0, 0, '2025-11-09 09:59:33', '2025-11-09 09:59:33', NULL, NULL, NULL, 'medium', NULL, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(4) DEFAULT '0',
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `piyasa_verileri`
--

CREATE TABLE `piyasa_verileri` (
  `id` int(11) NOT NULL,
  `sembol` varchar(20) NOT NULL,
  `fiyat` decimal(15,6) NOT NULL,
  `onceki_kapanis` decimal(15,6) NOT NULL,
  `degisim` decimal(15,6) NOT NULL,
  `degisim_yuzde` decimal(10,4) NOT NULL,
  `para_birimi` varchar(10) NOT NULL,
  `durum` varchar(20) NOT NULL,
  `durum_metni` varchar(50) NOT NULL,
  `guncel_fiyat` tinyint(1) DEFAULT '1',
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Tablo döküm verisi `piyasa_verileri`
--

INSERT INTO `piyasa_verileri` (`id`, `sembol`, `fiyat`, `onceki_kapanis`, `degisim`, `degisim_yuzde`, `para_birimi`, `durum`, `durum_metni`, `guncel_fiyat`, `guncelleme_tarihi`) VALUES
(1, 'TRY=X', 42.223700, 42.189600, 0.034100, 0.0808, 'TRY', 'success', 'Aktif', 1, '2025-11-10 18:57:56'),
(2, 'EURTRY=X', 48.802400, 48.832900, -0.030500, -0.0625, 'TRY', 'success', 'Aktif', 1, '2025-11-10 18:57:57'),
(3, 'GBPTRY=X', 55.660700, 55.555300, 0.105400, 0.1897, 'TRY', 'success', 'Aktif', 1, '2025-11-10 18:57:57'),
(4, 'GC=F', 4114.800000, 4009.800000, 105.000000, 2.6186, 'USD', 'success', 'Aktif', 1, '2025-11-10 18:57:57'),
(5, 'SI=F', 50.330000, 48.143000, 2.187000, 4.5427, 'USD', 'success', 'Aktif', 1, '2025-11-10 18:57:58'),
(6, 'XU100.IS', 10789.030000, 10924.530000, -135.500000, -1.2403, 'TRY', 'after_hours', 'Mesai dışı', 0, '2025-11-10 18:57:58'),
(7, 'BTC-USD', 105786.470000, 104725.830000, 1060.640000, 1.0128, 'USD', 'success', 'Aktif', 1, '2025-11-10 18:57:58');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `saved_news`
--

CREATE TABLE `saved_news` (
  `id` int(11) NOT NULL,
  `news_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `superlig_fikstur`
--

CREATE TABLE `superlig_fikstur` (
  `id` int(11) NOT NULL,
  `hafta` int(11) NOT NULL,
  `ev_takim_adi` varchar(100) DEFAULT NULL,
  `deplasman_takim_adi` varchar(100) DEFAULT NULL,
  `mac_tarihi` varchar(100) DEFAULT NULL,
  `skor` varchar(20) DEFAULT NULL,
  `mac_durumu` varchar(50) DEFAULT NULL,
  `sezon` varchar(20) DEFAULT '2024-2025',
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `superlig_oyuncular`
--

CREATE TABLE `superlig_oyuncular` (
  `id` int(11) NOT NULL,
  `oyuncu_adi` varchar(100) DEFAULT NULL,
  `takim_adi` varchar(100) DEFAULT NULL,
  `pozisyon` varchar(50) DEFAULT NULL,
  `oynanan_mac` int(11) DEFAULT '0',
  `gol` int(11) DEFAULT '0',
  `asist` int(11) DEFAULT '0',
  `sar_kart` int(11) DEFAULT '0',
  `kirmizi_kart` int(11) DEFAULT '0',
  `sezon` varchar(20) DEFAULT '2024-2025',
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `superlig_puan_durumu`
--

CREATE TABLE `superlig_puan_durumu` (
  `id` int(11) NOT NULL,
  `takim_adi` varchar(100) NOT NULL,
  `sira` int(11) DEFAULT NULL,
  `oynanan` int(11) DEFAULT '0',
  `galibiyet` int(11) DEFAULT '0',
  `beraberlik` int(11) DEFAULT '0',
  `maglubiyet` int(11) DEFAULT '0',
  `atilan_gol` int(11) DEFAULT '0',
  `yenilen_gol` int(11) DEFAULT '0',
  `averaj` int(11) DEFAULT '0',
  `puan` int(11) DEFAULT '0',
  `form` varchar(50) DEFAULT NULL,
  `sezon` varchar(20) DEFAULT '2024-2025',
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `superlig_takimlar`
--

CREATE TABLE `superlig_takimlar` (
  `id` int(11) NOT NULL,
  `takim_adi` varchar(100) NOT NULL,
  `takim_kisa_adi` varchar(50) DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `api_takim_id` int(11) DEFAULT NULL,
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `bio` text,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('user','admin','super_admin') DEFAULT 'user',
  `created_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `verified` tinyint(1) DEFAULT '0',
  `is_admin` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `bio`, `password`, `full_name`, `avatar`, `role`, `created_at`, `last_login`, `verified`, `is_admin`) VALUES
(1, 'barisha', 'barisha@onvibes.online', '', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Barış A.', 'uploads/avatars/avatar_1_1762296058.jpg', '', '2025-11-03 02:52:18', '2025-11-14 15:48:02', 1, 1),
(9, 'Test', 'test@test.com', NULL, '$2y$10$3NktvAqNJtgfe1VvJJFsTOQT2uoy0Pqx.AIVMGQBFtPRW/usTQ8ea', '', NULL, 'admin', '2025-11-07 00:14:31', '2025-11-14 04:04:51', 0, 0),
(10, 'denemee', 'denemee@denemee.com', NULL, '$2y$10$3S9DbdwfzwxMXCKeLyP9aOjpAaPhCKt/NHlzcMI0PctmF1wgyaj4K', NULL, NULL, 'user', '2025-11-09 03:11:18', '2025-11-09 03:24:16', 0, 0);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_follows`
--

CREATE TABLE `user_follows` (
  `id` int(11) NOT NULL,
  `follower_id` int(11) NOT NULL,
  `following_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_news`
--

CREATE TABLE `user_news` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `chat_media`
--
ALTER TABLE `chat_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `media_id` (`media_id`);

--
-- Tablo için indeksler `chat_rooms`
--
ALTER TABLE `chat_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Tablo için indeksler `chat_room_users`
--
ALTER TABLE `chat_room_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_room_user` (`room_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `news_id` (`news_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `discussions`
--
ALTER TABLE `discussions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `author_id` (`author_id`);

--
-- Tablo için indeksler `discussion_comments`
--
ALTER TABLE `discussion_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `discussion_id` (`discussion_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `discussion_likes`
--
ALTER TABLE `discussion_likes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `discussion_id` (`discussion_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `ilanlar`
--
ALTER TABLE `ilanlar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `type` (`type`),
  ADD KEY `priority` (`priority`),
  ADD KEY `status` (`status`);

--
-- Tablo için indeksler `kose_yazisi`
--
ALTER TABLE `kose_yazisi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `author_id` (`author_id`);

--
-- Tablo için indeksler `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_like` (`news_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `author_id` (`author_id`);

--
-- Tablo için indeksler `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

--
-- Tablo için indeksler `piyasa_verileri`
--
ALTER TABLE `piyasa_verileri`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sembol` (`sembol`),
  ADD KEY `idx_sembol` (`sembol`),
  ADD KEY `idx_guncelleme` (`guncelleme_tarihi`);

--
-- Tablo için indeksler `saved_news`
--
ALTER TABLE `saved_news`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_save` (`news_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `superlig_fikstur`
--
ALTER TABLE `superlig_fikstur`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `superlig_oyuncular`
--
ALTER TABLE `superlig_oyuncular`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `superlig_puan_durumu`
--
ALTER TABLE `superlig_puan_durumu`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `superlig_takimlar`
--
ALTER TABLE `superlig_takimlar`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Tablo için indeksler `user_follows`
--
ALTER TABLE `user_follows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `follower_id` (`follower_id`),
  ADD KEY `following_id` (`following_id`);

--
-- Tablo için indeksler `user_news`
--
ALTER TABLE `user_news`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Tablo için AUTO_INCREMENT değeri `chat_media`
--
ALTER TABLE `chat_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Tablo için AUTO_INCREMENT değeri `chat_rooms`
--
ALTER TABLE `chat_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Tablo için AUTO_INCREMENT değeri `chat_room_users`
--
ALTER TABLE `chat_room_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `discussions`
--
ALTER TABLE `discussions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `discussion_comments`
--
ALTER TABLE `discussion_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `discussion_likes`
--
ALTER TABLE `discussion_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `ilanlar`
--
ALTER TABLE `ilanlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `kose_yazisi`
--
ALTER TABLE `kose_yazisi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `likes`
--
ALTER TABLE `likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Tablo için AUTO_INCREMENT değeri `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `piyasa_verileri`
--
ALTER TABLE `piyasa_verileri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Tablo için AUTO_INCREMENT değeri `saved_news`
--
ALTER TABLE `saved_news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `superlig_fikstur`
--
ALTER TABLE `superlig_fikstur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `superlig_oyuncular`
--
ALTER TABLE `superlig_oyuncular`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `superlig_puan_durumu`
--
ALTER TABLE `superlig_puan_durumu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `superlig_takimlar`
--
ALTER TABLE `superlig_takimlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Tablo için AUTO_INCREMENT değeri `user_follows`
--
ALTER TABLE `user_follows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `user_news`
--
ALTER TABLE `user_news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `chat_media`
--
ALTER TABLE `chat_media`
  ADD CONSTRAINT `chat_media_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`),
  ADD CONSTRAINT `chat_media_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`),
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `chat_messages_ibfk_3` FOREIGN KEY (`media_id`) REFERENCES `chat_media` (`id`);

--
-- Tablo kısıtlamaları `chat_rooms`
--
ALTER TABLE `chat_rooms`
  ADD CONSTRAINT `chat_rooms_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `chat_room_users`
--
ALTER TABLE `chat_room_users`
  ADD CONSTRAINT `chat_room_users_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`),
  ADD CONSTRAINT `chat_room_users_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `discussions`
--
ALTER TABLE `discussions`
  ADD CONSTRAINT `discussions_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `discussion_comments`
--
ALTER TABLE `discussion_comments`
  ADD CONSTRAINT `discussion_comments_ibfk_1` FOREIGN KEY (`discussion_id`) REFERENCES `discussions` (`id`),
  ADD CONSTRAINT `discussion_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `discussion_likes`
--
ALTER TABLE `discussion_likes`
  ADD CONSTRAINT `discussion_likes_ibfk_1` FOREIGN KEY (`discussion_id`) REFERENCES `discussions` (`id`),
  ADD CONSTRAINT `discussion_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `kose_yazisi`
--
ALTER TABLE `kose_yazisi`
  ADD CONSTRAINT `kose_yazisi_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `likes`
--
ALTER TABLE `likes`
  ADD CONSTRAINT `likes_ibfk_1` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `news`
--
ALTER TABLE `news`
  ADD CONSTRAINT `news_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `news_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `saved_news`
--
ALTER TABLE `saved_news`
  ADD CONSTRAINT `saved_news_ibfk_1` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `saved_news_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `user_follows`
--
ALTER TABLE `user_follows`
  ADD CONSTRAINT `user_follows_ibfk_1` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_follows_ibfk_2` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `user_news`
--
ALTER TABLE `user_news`
  ADD CONSTRAINT `user_news_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
