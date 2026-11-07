-- ==============================================================
-- SUBH GAMING - CLEAN DATABASE DUMP
-- Rebranded & Completely Emptied for Production / Fresh Setup
-- All transaction data, user records, bids, and chats wiped clean.
-- Market lists, game rates, payout ratios, and settings configured.
-- Master Admin: admin_id: ADM0001, Email: subhgaming@gmail.com, Password: Subh@76265
-- ==============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+05:30";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Target Database: `subh_gaming`

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `admin_id` varchar(15) NOT NULL,
  `name` varchar(20) NOT NULL,
  `email` varchar(30) NOT NULL,
  `password` varchar(20) NOT NULL,
  `role` enum('master','admin') NOT NULL DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `admin_id`, `name`, `email`, `password`, `role`) VALUES
(17, 'ADM0001', 'SUBH GAMING', 'subhgaming@gmail.com', 'Subh@76265', 'master');

-- --------------------------------------------------------

--
-- Table structure for table `admin_chat_seen`
--

CREATE TABLE `admin_chat_seen` (
  `user_id` varchar(50) NOT NULL,
  `last_seen` bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_chat_seen`
--

-- Table `admin_chat_seen` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `admin_payment_details`
--

CREATE TABLE `admin_payment_details` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(150) NOT NULL DEFAULT '',
  `bank_account_name` varchar(150) NOT NULL DEFAULT '',
  `bank_account_number` varchar(50) NOT NULL DEFAULT '',
  `bank_ifsc` varchar(50) NOT NULL DEFAULT '',
  `bank_branch` varchar(150) NOT NULL DEFAULT '',
  `bank_note` varchar(255) NOT NULL DEFAULT '',
  `upi_id` varchar(150) NOT NULL DEFAULT '',
  `upi_name` varchar(150) NOT NULL DEFAULT '',
  `upi_note` varchar(255) NOT NULL DEFAULT '',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `upi_qr` varchar(255) NOT NULL DEFAULT '',
  `show_bank` tinyint(1) NOT NULL DEFAULT 1,
  `show_upi_id` tinyint(1) NOT NULL DEFAULT 1,
  `show_mobile_upi` tinyint(1) NOT NULL DEFAULT 0,
  `show_crypto` tinyint(1) NOT NULL DEFAULT 0,
  `crypto_name` varchar(50) NOT NULL DEFAULT 'USDT',
  `crypto_network` varchar(50) NOT NULL DEFAULT 'TRC20',
  `crypto_address` varchar(255) NOT NULL DEFAULT '',
  `inr_rate_per_unit` decimal(10,4) NOT NULL DEFAULT 90.0000,
  `crypto_qr` varchar(255) NOT NULL DEFAULT '',
  `upi_intent` varchar(150) NOT NULL DEFAULT '',
  `merchant_upi_name` varchar(150) NOT NULL DEFAULT '',
  `auto_accept_intent` tinyint(1) NOT NULL DEFAULT 1,
  `enable_upi_intent` tinyint(1) NOT NULL DEFAULT 0,
  `intent_webhook_url` varchar(255) NOT NULL DEFAULT '',
  `intent_success_redirect` varchar(255) NOT NULL DEFAULT '',
  `intent_failed_redirect` varchar(255) NOT NULL DEFAULT '',
  `whatsapp_no_1` varchar(20) NOT NULL DEFAULT '',
  `whatsapp_no_2` varchar(20) NOT NULL DEFAULT '',
  `min_deposit` int(11) NOT NULL DEFAULT 300,
  `min_withdraw` int(11) NOT NULL DEFAULT 1000,
  `min_bid` int(11) NOT NULL DEFAULT 10,
  `show_whatsapp_payment` tinyint(1) NOT NULL DEFAULT 0,
  `show_whatsapp_deposit` tinyint(1) NOT NULL DEFAULT 0,
  `whatsapp_deposit_no` varchar(20) NOT NULL DEFAULT '',
  `show_pay0` tinyint(1) NOT NULL DEFAULT 0,
  `pay0_token` varchar(255) NOT NULL DEFAULT '',
  `show_imb` tinyint(1) NOT NULL DEFAULT 0,
  `imb_token` varchar(255) NOT NULL DEFAULT '',
  `show_yuvipay` tinyint(1) NOT NULL DEFAULT 0,
  `yuvipay_token` varchar(255) NOT NULL DEFAULT '',
  `yuvipay_merch_id` varchar(255) NOT NULL DEFAULT '',
  `enable_phonepe` tinyint(1) NOT NULL DEFAULT 1,
  `enable_gpay` tinyint(1) NOT NULL DEFAULT 1,
  `enable_paytm` tinyint(1) NOT NULL DEFAULT 1,
  `enable_bhim` tinyint(1) NOT NULL DEFAULT 1,
  `enable_amazonpay` tinyint(1) NOT NULL DEFAULT 1,
  `enable_mobikwik` tinyint(1) NOT NULL DEFAULT 1,
    `telegram_id` varchar(255) NOT NULL DEFAULT '',
  `telegram_channel` varchar(255) NOT NULL DEFAULT '',
  `show_telegram` tinyint(1) NOT NULL DEFAULT 1,
  `show_telegram_channel` tinyint(1) NOT NULL DEFAULT 1,
  `pg_provider` varchar(50) NOT NULL DEFAULT 'manual_upi',
  `pg_merchant_id` varchar(255) NOT NULL DEFAULT '',
  `pg_api_key` varchar(255) NOT NULL DEFAULT '',
  `pg_secret_key` varchar(255) NOT NULL DEFAULT '',
  `pg_webhook_url` varchar(255) NOT NULL DEFAULT '',
  `pg_auto_accept` tinyint(1) NOT NULL DEFAULT 1,
  `phonepe_merchant_id` varchar(255) NOT NULL DEFAULT '',
  `phonepe_salt_key` varchar(255) NOT NULL DEFAULT '',
  `phonepe_salt_index` varchar(50) NOT NULL DEFAULT '1',
  `phonepe_webhook_url` varchar(255) NOT NULL DEFAULT '',
  `razorpay_key_id` varchar(255) NOT NULL DEFAULT '',
  `razorpay_key_secret` varchar(255) NOT NULL DEFAULT '',
  `razorpay_webhook_secret` varchar(255) NOT NULL DEFAULT '',
  `cashfree_app_id` varchar(255) NOT NULL DEFAULT '',
  `cashfree_secret_key` varchar(255) NOT NULL DEFAULT '',
  `cashfree_webhook_signature` varchar(255) NOT NULL DEFAULT '',
  `pay0_merchant_id` varchar(255) NOT NULL DEFAULT '',
  `telegram_bot_token` varchar(255) NOT NULL DEFAULT '',
  `telegram_channel_chat_id` varchar(255) NOT NULL DEFAULT '',
  `telegram_bot_auto_post` tinyint(1) NOT NULL DEFAULT 1,
  `enable_jiofinance` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `admin_payment_details`
--

INSERT INTO `admin_payment_details` (`id`, `bank_name`, `bank_account_name`, `bank_account_number`, `bank_ifsc`, `bank_branch`, `bank_note`, `upi_id`, `upi_name`, `upi_note`, `updated_at`, `upi_qr`, `show_bank`, `show_upi_id`, `show_mobile_upi`, `show_crypto`, `crypto_name`, `crypto_network`, `crypto_address`, `inr_rate_per_unit`, `crypto_qr`, `upi_intent`, `merchant_upi_name`, `auto_accept_intent`, `enable_upi_intent`, `intent_webhook_url`, `intent_success_redirect`, `intent_failed_redirect`, `whatsapp_no_1`, `whatsapp_no_2`, `min_deposit`, `min_withdraw`, `min_bid`, `show_whatsapp_payment`, `show_whatsapp_deposit`, `whatsapp_deposit_no`, `show_pay0`, `pay0_token`, `show_imb`, `imb_token`, `show_yuvipay`, `yuvipay_token`, `yuvipay_merch_id`, `enable_phonepe`, `enable_gpay`, `enable_paytm`, `enable_bhim`, `enable_amazonpay`, `enable_mobikwik`, `enable_jiofinance`) VALUES
(1, '', '', '', '', '', '', '', '', '', '2026-11-07 12:00:00', '', 0, 0, 0, 0, 'USDT', 'TRC20', '', 100.0000, '', '', '', 0, 0, '', '', '', '', '', 100, 500, 5, 0, 0, '', 0, '', 0, '', 0, '', '', 1, 0, 1, 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `admin_status`
--

CREATE TABLE `admin_status` (
  `id` tinyint(4) NOT NULL,
  `status` enum('online','offline') NOT NULL,
  `last_seen` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_status`
--

INSERT INTO `admin_status` (`id`, `status`, `last_seen`) VALUES
(1, 'online', '2026-10-08 14:32:28');

-- --------------------------------------------------------

--
-- Table structure for table `app_settings`
--

CREATE TABLE `app_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_settings`
--

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('global_user_deactive', '0', '2026-01-05 09:30:35'),
('new_user_default_deactive', '0', '2026-03-17 20:19:51');

-- --------------------------------------------------------

--
-- Table structure for table `app_settings_old`
--

CREATE TABLE `app_settings_old` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_settings_old`
--

INSERT INTO `app_settings_old` (`setting_key`, `setting_value`, `updated_at`) VALUES
('global_user_deactive', '0', '2026-01-05 09:30:35'),
('new_user_default_deactive', '1', '2026-01-05 15:01:06');

-- --------------------------------------------------------

--
-- Table structure for table `bank_kyc`
--

CREATE TABLE `bank_kyc` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `bank_name` varchar(50) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `ifsc` varchar(20) DEFAULT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `status` enum('Incomplete','Pending','Approved','Rejected') DEFAULT 'Incomplete',
  `date_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_kyc`
--

-- Table `bank_kyc` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `banner_text`
--

CREATE TABLE `banner_text` (
  `id` int(11) NOT NULL,
  `banner` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `banner_text`
--

INSERT INTO `banner_text` (`id`, `banner`) VALUES
(1, 'âœ¨ WELCOME TO SUBH GAMING âœ¨ FAST DEPOSIT â€¢ FAST WITHDRAWAL â€¢ 24Ã—7 SUPPORT â€¢ THANK YOU FOR JOINING ðŸ’Ž');

-- --------------------------------------------------------

--
-- Table structure for table `bid`
--

CREATE TABLE `bid` (
  `id` int(11) NOT NULL,
  `user_id` varchar(20) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `market` varchar(50) NOT NULL,
  `game_type` enum('SINGLE_DIGIT','JODI_DIGIT','JODI','SINGLE_PANA','DOUBLE_PANA','TRIPLE_PANA','HALF_SANGAM','FULL_SANGAM','SP_MOTOR','DP_MOTOR','LEFT_DIGIT','RIGHT_DIGIT') DEFAULT NULL,
  `single_digit` tinyint(3) UNSIGNED DEFAULT NULL,
  `open_digit` tinyint(3) UNSIGNED DEFAULT NULL,
  `close_digit` tinyint(3) UNSIGNED DEFAULT NULL,
  `jodi_digit` char(2) DEFAULT NULL,
  `single_pana` char(3) DEFAULT NULL,
  `double_pana` char(3) DEFAULT NULL,
  `triple_pana` char(3) DEFAULT NULL,
  `open_pana` char(3) DEFAULT NULL,
  `close_pana` char(3) DEFAULT NULL,
  `session_type` enum('Open','Close') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `bid_time` datetime NOT NULL,
  `is_cancelled` tinyint(1) NOT NULL DEFAULT 0,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_reason` varchar(255) DEFAULT NULL,
  `reverse_id` bigint(20) DEFAULT NULL,
  `bid_modified` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bid`
--

-- Table `bid` wiped clean for Subh Gaming initial launch
-- Table `bid` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `bid4`
--

CREATE TABLE `bid4` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` varchar(30) NOT NULL,
  `market` varchar(100) NOT NULL,
  `close_time` varchar(20) NOT NULL DEFAULT '',
  `game_type` varchar(30) NOT NULL DEFAULT 'SINGLE_DIGIT',
  `single_digit` varchar(5) DEFAULT NULL,
  `single_pana` varchar(5) DEFAULT NULL,
  `double_pana` varchar(5) DEFAULT NULL,
  `triple_pana` varchar(5) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bid_time` datetime NOT NULL,
  `win_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `result_declared` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `bid4`
--

-- Table `bid4` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `bid_refunds`
--

CREATE TABLE `bid_refunds` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `bid_id` bigint(20) NOT NULL,
  `source_table` varchar(20) NOT NULL DEFAULT 'bid',
  `batch_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `market` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `bid_time` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bid_refund_batches`
--

CREATE TABLE `bid_refund_batches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `market` varchar(50) NOT NULL,
  `from_dt` datetime DEFAULT NULL,
  `to_dt` datetime DEFAULT NULL,
  `filter_label` varchar(40) NOT NULL DEFAULT 'Custom',
  `note` varchar(255) DEFAULT NULL,
  `total_bids` int(11) NOT NULL DEFAULT 0,
  `total_users` int(11) NOT NULL DEFAULT 0,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `admin_id` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bonus_rate`
--

CREATE TABLE `bonus_rate` (
  `id` int(11) NOT NULL,
  `min_amount` decimal(12,2) NOT NULL,
  `bonus_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `bonus_rate`
--

INSERT INTO `bonus_rate` (`id`, `min_amount`, `bonus_amount`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1000.00, 150.00, 1, '2026-07-13 18:50:59', '2026-10-05 07:27:13'),
(2, 2000.00, 300.00, 1, '2026-07-13 18:50:59', '2026-10-05 07:27:40'),
(3, 5000.00, 600.00, 1, '2026-07-13 18:50:59', '2026-10-05 07:27:56'),
(4, 10000.00, 1200.00, 1, '2026-07-13 18:50:59', '2026-10-05 07:28:15'),
(5, 20000.00, 1800.00, 1, '2026-07-13 18:50:59', '2026-09-29 21:48:14'),
(6, 30000.00, 3000.00, 1, '2026-07-13 18:50:59', '2026-09-29 21:48:27'),
(7, 500.00, 50.00, 1, '2026-10-05 07:29:03', '2026-10-05 07:29:03'),
(8, 200.00, 20.00, 1, '2026-10-05 07:29:17', '2026-10-05 07:29:17'),
(9, 100.00, 10.00, 1, '2026-10-05 07:29:37', '2026-10-05 07:29:37');

-- --------------------------------------------------------

--
-- Table structure for table `bonus_settings`
--

CREATE TABLE `bonus_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `bonus_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `bonus_settings`
--

INSERT INTO `bonus_settings` (`id`, `bonus_enabled`, `updated_at`) VALUES
(1, 1, '2026-07-16 14:51:04');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `sender` enum('user','admin') NOT NULL,
  `message` text NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `ts` datetime NOT NULL,
  `is_edited` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_messages`
--

-- Table `chat_messages` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `chat_seen`
--

CREATE TABLE `chat_seen` (
  `user_id` varchar(50) NOT NULL,
  `last_seen` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_seen_user`
--

CREATE TABLE `chat_seen_user` (
  `user_id` varchar(50) NOT NULL,
  `last_seen` bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_seen_user`
--

-- Table `chat_seen_user` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `crypto_kyc`
--

CREATE TABLE `crypto_kyc` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `network` varchar(20) NOT NULL DEFAULT 'TRC20',
  `address` varchar(255) NOT NULL,
  `qr_image` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Incomplete') NOT NULL DEFAULT 'Pending',
  `date_time` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deposit`
--

CREATE TABLE `deposit` (
  `id` int(11) NOT NULL,
  `user_db_id` int(11) DEFAULT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `bonus_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `screenshot` varchar(255) NOT NULL,
  `method` varchar(50) DEFAULT 'UPI',
  `crypto_type` varchar(30) DEFAULT NULL,
  `crypto_network` varchar(30) DEFAULT NULL,
  `crypto_address` varchar(255) DEFAULT NULL,
  `crypto_tx_hash` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `date_time` datetime DEFAULT current_timestamp(),
  `admin_id` varchar(15) DEFAULT NULL,
  `order_id` varchar(100) DEFAULT NULL,
  `txn_id` varchar(255) DEFAULT NULL,
  `credited` tinyint(1) NOT NULL DEFAULT 0,
  `upi_txn_id` varchar(120) DEFAULT NULL,
  `upi_txn_ref` varchar(120) DEFAULT NULL,
  `upi_appr` varchar(120) DEFAULT NULL,
  `upi_resp_code` varchar(30) DEFAULT NULL,
  `upi_client_rc` varchar(30) DEFAULT NULL,
  `upi_raw` varchar(700) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deposit`
--

-- Table `deposit` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `deposit_history`
--

CREATE TABLE `deposit_history` (
  `id` int(11) NOT NULL,
  `order_token` varchar(100) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `pay_status` varchar(20) DEFAULT 'PENDING',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `downloads`
--

CREATE TABLE `downloads` (
  `id` int(10) UNSIGNED NOT NULL,
  `download_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `downloads`
--

-- Table `downloads` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `gali_bids`
--

CREATE TABLE `gali_bids` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `market_id` int(11) NOT NULL,
  `market_name` varchar(100) NOT NULL,
  `game_type` varchar(50) NOT NULL COMMENT 'Left Digit, Right Digit, Jodi Digit, etc.',
  `digit` varchar(10) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gali_bids`
--

-- Table `gali_bids` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `gali_markets`
--

CREATE TABLE `gali_markets` (
  `id` int(11) NOT NULL,
  `market_name` varchar(100) NOT NULL,
  `default_result` varchar(50) DEFAULT '**',
  `result_time` time DEFAULT NULL,
  `close_time_start` time DEFAULT '23:45:00',
  `close_time_end` time DEFAULT '00:00:00',
  `today_closed` tinyint(1) DEFAULT 0,
  `saturday_closed` tinyint(1) DEFAULT 0,
  `sunday_closed` tinyint(1) DEFAULT 0,
  `custom_close_text` varchar(255) DEFAULT NULL,
  `payout_left_digit` int(11) DEFAULT 100,
  `payout_right_digit` int(11) DEFAULT 100,
  `payout_jodi_digit` int(11) DEFAULT 1000,
  `is_published` tinyint(1) DEFAULT 1,
  `status` varchar(20) DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_next_day` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gali_markets`
--

INSERT INTO `gali_markets` (`id`, `market_name`, `default_result`, `result_time`, `close_time_start`, `close_time_end`, `today_closed`, `saturday_closed`, `sunday_closed`, `custom_close_text`, `payout_left_digit`, `payout_right_digit`, `payout_jodi_digit`, `is_published`, `status`, `sort_order`, `created_at`, `is_next_day`) VALUES
(1, 'DELHI BAZAR', '**', '15:15:00', '15:00:00', '00:00:00', 0, 0, 0, 'Saturday & Sunday Holidays', 100, 100, 1000, 1, 'active', 1, '2026-04-18 06:14:54', 0),
(2, 'SHREE GANESH', '**', '16:45:00', '16:30:00', '00:00:00', 0, 0, 0, 'Saturday & Sunday Holidays', 100, 100, 1000, 1, 'active', 2, '2026-04-18 06:17:17', 0),
(3, 'FARIDABAD', '**', '18:15:00', '18:00:00', '00:00:00', 0, 0, 0, 'Saturday & Sunday Holidays', 100, 100, 1000, 1, 'active', 3, '2026-04-18 06:17:53', 0),
(4, 'GAZIABAD', '**', '21:45:00', '21:30:00', '00:00:00', 0, 0, 0, 'Saturday & Sunday Holidays', 100, 100, 1000, 1, 'active', 4, '2026-04-18 06:18:25', 0),
(5, 'GALI', '**', '23:45:00', '23:30:00', '00:00:00', 0, 0, 0, 'Saturday & Sunday Holidays', 100, 100, 1000, 1, 'active', 5, '2026-04-18 06:18:56', 0),
(6, 'DESAWAR', '**', '05:15:00', '04:30:00', '07:00:00', 0, 0, 0, 'Saturday & Sunday Holidays', 100, 100, 1000, 1, 'active', 6, '2026-04-18 06:19:35', 1);

-- --------------------------------------------------------

--
-- Table structure for table `gali_rates`
--

CREATE TABLE `gali_rates` (
  `id` int(11) NOT NULL,
  `rate_key` varchar(100) NOT NULL,
  `rate_value` varchar(100) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gali_rates`
--

INSERT INTO `gali_rates` (`id`, `rate_key`, `rate_value`, `updated_at`) VALUES
(1, 'gali_left_rate', '10 Ka 95', '2026-04-18 07:36:43'),
(2, 'gali_right_rate', '10 Ka 95', '2026-04-18 07:36:43'),
(3, 'gali_jodi_rate', '10 Ka 950', '2026-04-18 07:36:43');

-- --------------------------------------------------------

--
-- Table structure for table `gali_result`
--

CREATE TABLE `gali_result` (
  `id` int(11) NOT NULL,
  `market` varchar(100) NOT NULL,
  `result_date` date NOT NULL,
  `left_digit` varchar(10) DEFAULT NULL,
  `right_digit` varchar(10) DEFAULT NULL,
  `jodi` varchar(10) DEFAULT NULL,
  `declared_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `result_str` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gali_result`
--

-- Table `gali_result` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `game_rates`
--

CREATE TABLE `game_rates` (
  `id` int(11) NOT NULL,
  `game_type` varchar(50) NOT NULL,
  `play_amount` int(11) NOT NULL DEFAULT 10,
  `win_amount` int(11) NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `game_rates`
--

INSERT INTO `game_rates` (`id`, `game_type`, `play_amount`, `win_amount`, `updated_at`) VALUES
(1, 'Single Digit', 10, 95, '2026-05-08 11:42:58'),
(2, 'Jodi Digit', 10, 950, '2026-05-08 11:42:58'),
(3, 'Single Pana', 10, 1500, '2026-05-08 11:42:58'),
(4, 'Double Pana', 10, 3000, '2026-05-08 11:42:58'),
(5, 'Triple Pana', 10, 6000, '2026-05-15 14:28:46'),
(6, 'Half Sangam', 10, 10000, '2026-04-29 09:52:34'),
(7, 'Full Sangam', 10, 100000, '2026-04-29 09:52:34');

-- --------------------------------------------------------

--
-- Table structure for table `how_to_play_buttons`
--

CREATE TABLE `how_to_play_buttons` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `link` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `how_to_play_buttons`
--

INSERT INTO `how_to_play_buttons` (`id`, `title`, `link`, `created_at`) VALUES
(2, 'Matka App à¤®à¥‡à¤‚ Deposit à¤•à¥ˆà¤¸à¥‡ à¤•à¤°à¥‡à¤‚?', 'Hy', '2026-04-27 10:08:16');

-- --------------------------------------------------------

--
-- Table structure for table `live`
--

CREATE TABLE `live` (
  `user_id` varchar(32) NOT NULL,
  `name` varchar(100) NOT NULL,
  `last_seen` datetime NOT NULL,
  `status` enum('online','offline') NOT NULL DEFAULT 'online'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `live`
--

-- Table `live` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `markets`
--

CREATE TABLE `markets` (
  `id` int(10) UNSIGNED NOT NULL,
  `market_name` varchar(100) NOT NULL,
  `default_result` varchar(11) NOT NULL DEFAULT '***-**-***',
  `open_time` time NOT NULL,
  `close_time` time NOT NULL,
  `today_closed` tinyint(1) NOT NULL DEFAULT 0,
  `saturday_closed` tinyint(1) NOT NULL DEFAULT 0,
  `sunday_closed` tinyint(1) NOT NULL DEFAULT 0,
  `custom_close_text` varchar(255) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `payout_single_digit` int(11) NOT NULL DEFAULT 9,
  `payout_jodi_digit` int(11) NOT NULL DEFAULT 90,
  `payout_single_pana` int(11) NOT NULL DEFAULT 140,
  `payout_double_pana` int(11) NOT NULL DEFAULT 280,
  `payout_triple_pana` int(11) NOT NULL DEFAULT 600,
  `payout_half_sangam` int(11) NOT NULL DEFAULT 10000,
  `payout_full_sangam` int(11) NOT NULL DEFAULT 100000,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `market_type` varchar(50) NOT NULL DEFAULT 'regular',
  `market_color` varchar(50) NOT NULL DEFAULT '#000000',
  `min_bid` int(11) NOT NULL DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `markets`
--

INSERT INTO `markets` (`id`, `market_name`, `default_result`, `open_time`, `close_time`, `today_closed`, `saturday_closed`, `sunday_closed`, `custom_close_text`, `is_published`, `status`, `payout_single_digit`, `payout_jodi_digit`, `payout_single_pana`, `payout_double_pana`, `payout_triple_pana`, `payout_half_sangam`, `payout_full_sangam`, `sort_order`, `market_type`, `market_color`, `min_bid`) VALUES
(220, 'KARNATAKA DAY', '***-**-***', '10:10:00', '11:10:00', 0, 0, 0, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 3, 'regular', '#000000', 10),
(221, 'MILAN DAY', '***-**-***', '14:00:00', '16:00:00', 0, 0, 1, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 10, 'regular', '#000000', 10),
(222, 'KALYAN MORNING', '***-**-***', '11:35:00', '12:35:00', 0, 0, 0, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 6, 'regular', '#000000', 10),
(223, 'MADHUR NIGHT', '***-**-***', '20:35:00', '22:35:00', 0, 0, 1, 'Sunday Holiday', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 17, 'regular', '#000000', 10),
(224, 'SRIDEVI', '***-**-***', '11:30:00', '12:30:00', 0, 0, 0, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 5, 'regular', '#000000', 10),
(225, 'TIME BAZAR', '***-**-***', '13:00:00', '14:00:00', 0, 0, 1, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 8, 'regular', '#000000', 10),
(226, 'MADHUR DAY', '***-**-***', '13:20:00', '14:20:00', 0, 0, 0, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 9, 'regular', '#000000', 10),
(227, 'MAIN BAZAR', '***-**-***', '21:45:00', '23:45:00', 0, 1, 1, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 22, 'regular', '#000000', 10),
(228, 'RAJDHANI NIGHT', '***-**-***', '21:30:00', '23:35:00', 0, 0, 1, 'Sunday Holiday', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 21, 'regular', '#000000', 10),
(229, 'KALYAN NIGHT', '***-**-***', '21:25:00', '23:25:00', 0, 1, 1, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 20, 'regular', '#000000', 10),
(230, 'MILAN NIGHT', '***-**-***', '21:00:00', '23:00:00', 0, 0, 1, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 19, 'regular', '#000000', 10),
(231, 'SUPREME NIGHT', '***-**-***', '20:35:00', '22:35:00', 0, 0, 0, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 18, 'regular', '#000000', 10),
(236, 'SRIDEVI NIGHT', '***-**-***', '19:05:00', '20:05:00', 0, 0, 0, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 15, 'regular', '#000000', 10),
(237, 'KARNATAKA NIGHT', '***-**-***', '18:40:00', '19:40:00', 0, 0, 0, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 14, 'regular', '#000000', 10),
(238, 'KALYAN', '***-**-***', '16:00:00', '18:00:00', 0, 0, 1, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 13, 'regular', '#000000', 10),
(239, 'SUPREME DAY', '***-**-***', '15:30:00', '17:30:00', 0, 0, 0, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 12, 'regular', '#000000', 10),
(240, 'RAJDHANI DAY', '***-**-***', '15:05:00', '17:10:00', 0, 0, 1, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 11, 'regular', '#000000', 10),
(242, 'MADHUR MORNING', '***-**-***', '11:25:00', '12:25:00', 0, 0, 0, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 4, 'regular', '#000000', 10),
(243, 'MILAN MORNING', '***-**-***', '10:10:00', '11:10:00', 0, 0, 0, 'Saturday & Sunday Holidays', 1, 'active', 95, 950, 1500, 3000, 7000, 10000, 100000, 2, 'regular', '#000000', 10);

-- --------------------------------------------------------

--
-- Table structure for table `markets4`
--

CREATE TABLE `markets4` (
  `id` int(11) NOT NULL,
  `market_name` varchar(100) NOT NULL,
  `default_result` varchar(20) NOT NULL DEFAULT '***-*',
  `open_time` time NOT NULL DEFAULT '00:00:00',
  `close_time` time NOT NULL DEFAULT '00:00:00',
  `today_closed` tinyint(1) NOT NULL DEFAULT 0,
  `saturday_closed` tinyint(1) NOT NULL DEFAULT 0,
  `sunday_closed` tinyint(1) NOT NULL DEFAULT 0,
  `custom_close_text` varchar(255) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `payout_single_digit` int(11) NOT NULL DEFAULT 100,
  `payout_single_pana` int(11) NOT NULL DEFAULT 1600,
  `payout_double_pana` int(11) NOT NULL DEFAULT 3200,
  `payout_triple_pana` int(11) NOT NULL DEFAULT 9000,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `markets4`
--

INSERT INTO `markets4` (`id`, `market_name`, `default_result`, `open_time`, `close_time`, `today_closed`, `saturday_closed`, `sunday_closed`, `custom_close_text`, `is_published`, `status`, `payout_single_digit`, `payout_single_pana`, `payout_double_pana`, `payout_triple_pana`, `sort_order`, `created_at`) VALUES
(1, 'STARLINE 08:00 AM', '***-*', '00:00:00', '08:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 1, '2026-05-11 00:41:10'),
(2, 'STARLINE 09:00 AM', '***-*', '00:00:00', '09:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 2, '2026-05-11 00:41:10'),
(3, 'STARLINE 10:00 AM', '***-*', '00:00:00', '10:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 3, '2026-05-11 00:41:10'),
(4, 'STARLINE 11:00 AM', '***-*', '00:00:00', '11:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 4, '2026-05-11 00:41:10'),
(5, 'STARLINE 12:00 PM', '***-*', '00:00:00', '12:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 5, '2026-05-11 00:41:10'),
(6, 'STARLINE 01:00 PM', '***-*', '00:00:00', '13:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 6, '2026-05-11 00:41:10'),
(7, 'STARLINE 02:00 PM', '***-*', '00:00:00', '14:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 7, '2026-05-11 00:41:10'),
(8, 'STARLINE 03:00 PM', '***-*', '00:00:00', '15:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 8, '2026-05-11 00:41:10'),
(9, 'STARLINE 04:00 PM', '***-*', '00:00:00', '16:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 9, '2026-05-11 00:41:10'),
(10, 'STARLINE 05:00 PM', '***-*', '00:00:00', '17:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 10, '2026-05-11 00:41:10'),
(11, 'STARLINE 06:00 PM', '***-*', '00:00:00', '18:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 11, '2026-05-11 00:41:10'),
(12, 'STARLINE 07:00 PM', '***-*', '00:00:00', '19:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 12, '2026-05-11 00:41:10'),
(13, 'STARLINE 08:00 PM', '***-*', '00:00:00', '20:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 13, '2026-05-11 00:41:10'),
(14, 'STARLINE 09:00 PM', '***-*', '00:00:00', '21:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 14, '2026-05-11 00:41:10'),
(15, 'STARLINE 10:00 PM', '***-*', '00:00:00', '22:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 15, '2026-05-11 00:41:10'),
(16, 'STARLINE 11:00 PM', '***-*', '00:00:00', '23:00:00', 0, 0, 0, NULL, 1, 'active', 100, 1600, 3200, 9000, 16, '2026-05-11 00:41:10');

-- --------------------------------------------------------

--
-- Table structure for table `market_result`
--

CREATE TABLE `market_result` (
  `id` int(11) NOT NULL,
  `market` varchar(20) NOT NULL,
  `result_date` date NOT NULL,
  `open_pana` char(3) DEFAULT NULL,
  `open_digit` char(1) DEFAULT NULL,
  `close_digit` char(1) DEFAULT NULL,
  `close_pana` char(3) DEFAULT NULL,
  `result_str` char(10) NOT NULL DEFAULT 'XXX-XX-XXX',
  `declared_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `market_result`
--

-- Table `market_result` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `market_result4`
--

CREATE TABLE `market_result4` (
  `id` int(11) NOT NULL,
  `market` varchar(100) NOT NULL,
  `result_date` date NOT NULL,
  `open_pana` varchar(10) DEFAULT NULL,
  `open_digit` varchar(10) DEFAULT NULL,
  `result_str` varchar(20) NOT NULL DEFAULT '***-*',
  `number` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `declared_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `market_result4`
--

-- Table `market_result4` wiped clean for Subh Gaming initial launch
-- Table `market_result4` wiped clean for Subh Gaming initial launch
-- Table `market_result4` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `market_reverse_log`
--

CREATE TABLE `market_reverse_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `action_ref` varchar(64) NOT NULL,
  `admin_id` varchar(50) NOT NULL,
  `market` varchar(80) NOT NULL,
  `result_date` date NOT NULL,
  `delete_scope` enum('OPEN','CLOSE','FULL') NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `bid_refund_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `win_reverse_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bids_deleted` int(11) NOT NULL DEFAULT 0,
  `wins_deleted` int(11) NOT NULL DEFAULT 0,
  `details_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notices`
--

CREATE TABLE `notices` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `type` varchar(32) DEFAULT 'text_image',
  `image` varchar(255) DEFAULT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `link_label` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `show_message` tinyint(1) DEFAULT 1,
  `show_link_url` tinyint(1) DEFAULT 1,
  `show_link_label` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `notices`
--

INSERT INTO `notices` (`id`, `title`, `message`, `type`, `image`, `link_url`, `link_label`, `created_at`, `show_message`, `show_link_url`, `show_link_label`) VALUES
(1, 'ðŸ† WINNER ALERT â€” SUBH GAMING ðŸ‘‘', 'ðŸ”¥ Aaj ki baazi, winner ke naam!\r\nAapka game, aapki timing aur aapki à¤œà¥€à¤¤ â€” SUBH GAMING par har moment khaas hai. ðŸŽ¯\r\n\r\nðŸ’« Winner Bano â€¢ Apni Jeet Ka Jashn Mano\r\nðŸ‘‘ SUBH GAMING â€” Game On, Champion!', 'text_image', 'uploads/notices/notice_1791447516_7267.png', '', '', '2026-10-08 13:48:36', 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `otp_records`
--

CREATE TABLE `otp_records` (
  `id` int(11) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `type` varchar(50) DEFAULT 'Registration',
  `status` enum('Sent','Verified','Expired') DEFAULT 'Sent',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otp_records`
--

-- Table `otp_records` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `otp_settings`
--

CREATE TABLE `otp_settings` (
  `id` int(11) NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'ON',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otp_settings`
--

INSERT INTO `otp_settings` (`id`, `status`, `updated_at`) VALUES
(1, 'ON', '2026-10-07 12:57:57');

-- --------------------------------------------------------

--
-- Table structure for table `page_views`
--

CREATE TABLE `page_views` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `page_name` varchar(80) NOT NULL DEFAULT 'index',
  `ip_address` varchar(64) NOT NULL,
  `country` varchar(120) DEFAULT NULL,
  `region` varchar(120) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `device` varchar(60) DEFAULT NULL,
  `os` varchar(60) DEFAULT NULL,
  `platform` varchar(60) DEFAULT NULL,
  `browser` varchar(60) DEFAULT NULL,
  `repeat_user` int(11) NOT NULL DEFAULT 1,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- --------------------------------------------------------


--
-- Table structure for table `recent_winners`
--

CREATE TABLE `recent_winners` (
  `id` int(11) NOT NULL,
  `name` varchar(20) DEFAULT NULL,
  `market_name` varchar(100) NOT NULL,
  `win_amount` decimal(10,2) NOT NULL,
  `game_type` varchar(50) NOT NULL,
  `session` varchar(50) DEFAULT NULL,
  `is_fake` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `recent_winners`
--

-- Table `recent_winners` wiped clean for Subh Gaming initial launch
-- Table `recent_winners` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(11) NOT NULL,
  `referrer_id` varchar(50) NOT NULL,
  `referee_id` varchar(50) NOT NULL,
  `referee_name` varchar(100) DEFAULT NULL,
  `referee_mobile` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `referrals`
--

-- Table `referrals` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `rk_config`
--

CREATE TABLE `rk_config` (
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `send_otp`
--

CREATE TABLE `send_otp` (
  `id` int(11) NOT NULL,
  `otp_mobile` varchar(15) NOT NULL,
  `otp_digit` varchar(10) NOT NULL,
  `otp_status` varchar(20) NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `starline4_conditions`
--

CREATE TABLE `starline4_conditions` (
  `id` int(10) UNSIGNED NOT NULL,
  `market_name` varchar(100) NOT NULL,
  `condition_type` enum('low_payout','profit_percentage','random','fixed') NOT NULL DEFAULT 'low_payout',
  `profit_percentage` int(11) NOT NULL DEFAULT 80,
  `fixed_value` varchar(20) NOT NULL DEFAULT '',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `starline4_conditions`
--

INSERT INTO `starline4_conditions` (`id`, `market_name`, `condition_type`, `profit_percentage`, `fixed_value`, `status`, `updated_at`) VALUES
(1, 'ALL_MARKETS', 'low_payout', 80, '', 'active', '2026-09-29 17:31:17');

-- --------------------------------------------------------

--
-- Table structure for table `upi_kyc`
--

CREATE TABLE `upi_kyc` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `upi_id` varchar(50) DEFAULT NULL,
  `qr_image` varchar(100) DEFAULT NULL,
  `status` enum('Incomplete','Pending','Approved','Rejected') DEFAULT 'Incomplete',
  `date_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `upi_kyc`
--

-- Table `upi_kyc` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `upi_numbers`
--

CREATE TABLE `upi_numbers` (
  `id` int(11) NOT NULL,
  `user_id` varchar(64) NOT NULL,
  `upi_number` varchar(20) NOT NULL,
  `upi_type` enum('phonepe','gpay','paytm','bhim') NOT NULL,
  `date_time` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `upi_numbers`
--

-- Table `upi_numbers` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `name` varchar(20) DEFAULT NULL,
  `mobile_no` varchar(10) DEFAULT NULL,
  `wallet_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `email` varchar(50) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `password` varchar(20) DEFAULT NULL,
  `referral` varchar(10) NOT NULL,
  `last_seen` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `language` varchar(10) DEFAULT 'en'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

-- Table `users` wiped clean for Subh Gaming initial launch
-- Table `users` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `user_fcm_tokens`
--

CREATE TABLE `user_fcm_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `fcm_token` text NOT NULL,
  `device_id` varchar(190) DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_fcm_tokens`
--

-- Table `user_fcm_tokens` wiped clean for Subh Gaming initial launch
-- Table `user_fcm_tokens` wiped clean for Subh Gaming initial launch
-- Table `user_fcm_tokens` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `user_status`
--

CREATE TABLE `user_status` (
  `user_id` varchar(50) NOT NULL,
  `last_seen` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_status`
--

-- Table `user_status` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `wining`
--

CREATE TABLE `wining` (
  `id` int(11) NOT NULL,
  `bid_id` int(11) DEFAULT NULL,
  `user_id` varchar(20) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `market` varchar(50) NOT NULL,
  `session_type` varchar(10) NOT NULL,
  `bet_type` enum('single_digit','jodi_digit','single_pana','double_pana','triple_pana','half_sangam','full_sangam','sp_motor','dp_motor') DEFAULT NULL,
  `bet_key` varchar(60) NOT NULL DEFAULT '',
  `single_digit` varchar(1) DEFAULT NULL,
  `jodi_digit` varchar(2) DEFAULT NULL,
  `single_pana` varchar(3) DEFAULT NULL,
  `double_pana` varchar(3) DEFAULT NULL,
  `triple_pana` varchar(3) DEFAULT NULL,
  `open_digit` tinyint(3) UNSIGNED DEFAULT NULL,
  `close_digit` tinyint(3) UNSIGNED DEFAULT NULL,
  `open_pana_x` char(3) DEFAULT NULL,
  `close_pana_x` char(3) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `wining_balance` decimal(10,2) NOT NULL,
  `win_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'Win',
  `win_time` datetime NOT NULL,
  `win_date` date DEFAULT NULL,
  `notified` tinyint(1) NOT NULL DEFAULT 0,
  `notified_at` datetime DEFAULT NULL,
  `is_reversed` tinyint(1) NOT NULL DEFAULT 0,
  `reversed_at` datetime DEFAULT NULL,
  `reversed_reason` varchar(255) DEFAULT NULL,
  `reverse_id` bigint(20) DEFAULT NULL,
  `jodi` varchar(2) DEFAULT NULL,
  `left_digit` varchar(1) DEFAULT NULL,
  `right_digit` varchar(1) DEFAULT NULL,
  `business_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wining`
--

-- Table `wining` wiped clean for Subh Gaming initial launch

--
-- Triggers `wining`
--
DELIMITER $$
CREATE TRIGGER `wining_block_lose` BEFORE INSERT ON `wining` FOR EACH ROW BEGIN
  IF NEW.status IS NOT NULL AND LOWER(NEW.status) <> 'win' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only WIN rows allowed in wining';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `wins_run_lock`
--

CREATE TABLE `wins_run_lock` (
  `id` int(11) NOT NULL,
  `lock_key` varchar(100) DEFAULT NULL,
  `last_run` datetime NOT NULL,
  `is_running` tinyint(1) NOT NULL DEFAULT 0,
  `heartbeat_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `last_msg` varchar(255) DEFAULT NULL,
  `last_result_str` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal`
--

CREATE TABLE `withdrawal` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `method` varchar(50) DEFAULT 'Bank',
  `status` varchar(50) DEFAULT 'Pending',
  `date_time` datetime DEFAULT current_timestamp(),
  `admin_id` varchar(20) NOT NULL DEFAULT 'ADM0001',
  `order_id` varchar(100) DEFAULT NULL,
  `txn_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `withdrawal`
--

-- Table `withdrawal` wiped clean for Subh Gaming initial launch

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_time`
--

CREATE TABLE `withdrawal_time` (
  `id` int(11) NOT NULL,
  `withdraw_start_time` varchar(50) NOT NULL DEFAULT '07:00 AM',
  `withdraw_end_time` varchar(50) NOT NULL DEFAULT '11:00 AM',
  `withdraw_note` varchar(255) NOT NULL DEFAULT 'Withdrawal allowed from 7:00 AM to 11:00 AM only.',
  `enable_withdraw_time` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `withdrawal_time`
--

INSERT INTO `withdrawal_time` (`id`, `withdraw_start_time`, `withdraw_end_time`, `withdraw_note`, `enable_withdraw_time`) VALUES
(1, '07:00 AM', '11:00 PM', 'Withdrawal allowed from 7:00 AM to 11:00 pm only.', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_id` (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_chat_seen`
--
ALTER TABLE `admin_chat_seen`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `admin_payment_details`
--
ALTER TABLE `admin_payment_details`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_status`
--
ALTER TABLE `admin_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `app_settings_old`
--
ALTER TABLE `app_settings_old`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `bank_kyc`
--
ALTER TABLE `bank_kyc`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `banner_text`
--
ALTER TABLE `banner_text`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bid`
--
ALTER TABLE `bid`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_market_time` (`market`,`bid_time`),
  ADD KEY `idx_game_type` (`game_type`),
  ADD KEY `market` (`market`),
  ADD KEY `bid_time` (`bid_time`),
  ADD KEY `is_cancelled` (`is_cancelled`),
  ADD KEY `reverse_id` (`reverse_id`);

--
-- Indexes for table `bid4`
--
ALTER TABLE `bid4`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bid4_user` (`user_id`),
  ADD KEY `idx_bid4_market` (`market`,`bid_time`);

--
-- Indexes for table `bid_refunds`
--
ALTER TABLE `bid_refunds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_bid_source` (`bid_id`,`source_table`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_market` (`market`);

--
-- Indexes for table `bid_refund_batches`
--
ALTER TABLE `bid_refund_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_market` (`market`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `bonus_rate`
--
ALTER TABLE `bonus_rate`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `min_amount` (`min_amount`);

--
-- Indexes for table `bonus_settings`
--
ALTER TABLE `bonus_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_ts` (`ts`),
  ADD KEY `idx_sender` (`sender`);

--
-- Indexes for table `chat_seen`
--
ALTER TABLE `chat_seen`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `chat_seen_user`
--
ALTER TABLE `chat_seen_user`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `crypto_kyc`
--
ALTER TABLE `crypto_kyc`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `deposit`
--
ALTER TABLE `deposit`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_transaction_id` (`order_id`),
  ADD UNIQUE KEY `uk_txn` (`order_id`),
  ADD UNIQUE KEY `uk_transaction` (`order_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `idx_user_db_id` (`user_db_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_method` (`method`),
  ADD KEY `idx_deposit_order_id` (`order_id`),
  ADD KEY `idx_deposit_transaction_id` (`txn_id`),
  ADD KEY `idx_deposit_credited` (`credited`);

--
-- Indexes for table `deposit_history`
--
ALTER TABLE `deposit_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `downloads`
--
ALTER TABLE `downloads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gali_bids`
--
ALTER TABLE `gali_bids`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gali_markets`
--
ALTER TABLE `gali_markets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gali_rates`
--
ALTER TABLE `gali_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gali_result`
--
ALTER TABLE `gali_result`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_market_date` (`market`,`result_date`);

--
-- Indexes for table `how_to_play_buttons`
--
ALTER TABLE `how_to_play_buttons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `live`
--
ALTER TABLE `live`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `markets`
--
ALTER TABLE `markets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `market_name` (`market_name`);

--
-- Indexes for table `markets4`
--
ALTER TABLE `markets4`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_market_name` (`market_name`);

--
-- Indexes for table `market_result`
--
ALTER TABLE `market_result`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_market_date` (`market`,`result_date`);

--
-- Indexes for table `market_result4`
--
ALTER TABLE `market_result4`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_market_date` (`market`,`result_date`);

--
-- Indexes for table `market_reverse_log`
--
ALTER TABLE `market_reverse_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_market_date` (`market`,`result_date`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action_ref` (`action_ref`);

--
-- Indexes for table `notices`
--
ALTER TABLE `notices`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `otp_records`
--
ALTER TABLE `otp_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `otp_settings`
--
ALTER TABLE `otp_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `page_views`
--
ALTER TABLE `page_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_region` (`region`),
  ADD KEY `idx_page_date` (`page_name`,`created_at`),
  ADD KEY `idx_ip_device` (`ip_address`,`device`),
  ADD KEY `idx_ip_ua` (`ip_address`,`user_agent`(40)),
  ADD KEY `idx_page_time` (`page_name`,`created_at`);

--
-- Indexes for table `recent_winners`
--
ALTER TABLE `recent_winners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_referee_id` (`referee_id`),
  ADD KEY `idx_ref` (`referrer_id`);

--
-- Indexes for table `rk_config`
--
ALTER TABLE `rk_config`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `send_otp`
--
ALTER TABLE `send_otp`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `starline4_conditions`
--
ALTER TABLE `starline4_conditions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `market_name` (`market_name`);

--
-- Indexes for table `upi_kyc`
--
ALTER TABLE `upi_kyc`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `upi_numbers`
--
ALTER TABLE `upi_numbers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `upi_type` (`upi_type`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_id` (`user_id`);

--
-- Indexes for table `user_fcm_tokens`
--
ALTER TABLE `user_fcm_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_device` (`user_id`,`device_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_device_id` (`device_id`);

--
-- Indexes for table `user_status`
--
ALTER TABLE `user_status`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `wining`
--
ALTER TABLE `wining`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wining_bid` (`bid_id`),
  ADD UNIQUE KEY `uniq_win` (`win_date`,`bet_key`,`bid_id`,`bet_type`),
  ADD KEY `idx_market_date` (`market`,`win_date`),
  ADD KEY `idx_user_date` (`user_id`,`win_date`),
  ADD KEY `idx_market_notified` (`market`,`win_date`,`notified`),
  ADD KEY `idx_user_notified` (`user_id`,`win_date`,`notified`),
  ADD KEY `idx_bet_key` (`bet_key`),
  ADD KEY `is_reversed` (`is_reversed`),
  ADD KEY `reverse_id` (`reverse_id`),
  ADD KEY `market` (`market`),
  ADD KEY `win_date` (`win_date`);

--
-- Indexes for table `wins_run_lock`
--
ALTER TABLE `wins_run_lock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lock_key` (`lock_key`),
  ADD UNIQUE KEY `uq_lock_key` (`lock_key`);

--
-- Indexes for table `withdrawal`
--
ALTER TABLE `withdrawal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_order_id` (`order_id`),
  ADD KEY `transaction_id` (`order_id`),
  ADD KEY `idx_txn_id` (`txn_id`),
  ADD KEY `idx_user_status` (`user_id`,`status`);

--
-- Indexes for table `withdrawal_time`
--
ALTER TABLE `withdrawal_time`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `bank_kyc`
--
ALTER TABLE `bank_kyc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `banner_text`
--
ALTER TABLE `banner_text`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `bid`
--
ALTER TABLE `bid`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `bid4`
--
ALTER TABLE `bid4`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `bid_refunds`
--
ALTER TABLE `bid_refunds`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bid_refund_batches`
--
ALTER TABLE `bid_refund_batches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bonus_rate`
--
ALTER TABLE `bonus_rate`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `crypto_kyc`
--
ALTER TABLE `crypto_kyc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deposit`
--
ALTER TABLE `deposit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `deposit_history`
--
ALTER TABLE `deposit_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `downloads`
--
ALTER TABLE `downloads`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `gali_bids`
--
ALTER TABLE `gali_bids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `gali_markets`
--
ALTER TABLE `gali_markets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `gali_rates`
--
ALTER TABLE `gali_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `gali_result`
--
ALTER TABLE `gali_result`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `how_to_play_buttons`
--
ALTER TABLE `how_to_play_buttons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `markets`
--
ALTER TABLE `markets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `markets4`
--
ALTER TABLE `markets4`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `market_result`
--
ALTER TABLE `market_result`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `market_result4`
--
ALTER TABLE `market_result4`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `market_reverse_log`
--
ALTER TABLE `market_reverse_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `notices`
--
ALTER TABLE `notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `otp_records`
--
ALTER TABLE `otp_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `page_views`
--
ALTER TABLE `page_views`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `recent_winners`
--
ALTER TABLE `recent_winners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `send_otp`
--
ALTER TABLE `send_otp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `starline4_conditions`
--
ALTER TABLE `starline4_conditions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `upi_kyc`
--
ALTER TABLE `upi_kyc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `upi_numbers`
--
ALTER TABLE `upi_numbers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `user_fcm_tokens`
--
ALTER TABLE `user_fcm_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `wining`
--
ALTER TABLE `wining`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `wins_run_lock`
--
ALTER TABLE `wins_run_lock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `withdrawal`
--
ALTER TABLE `withdrawal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `withdrawal_time`
--
ALTER TABLE `withdrawal_time`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bid_refunds`
--
ALTER TABLE `bid_refunds`
  ADD CONSTRAINT `fk_bid_refunds_batch` FOREIGN KEY (`batch_id`) REFERENCES `bid_refund_batches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deposit`
--
ALTER TABLE `deposit`
  ADD CONSTRAINT `fk_deposit_user` FOREIGN KEY (`user_db_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
