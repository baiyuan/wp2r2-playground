<?php
/**
 * Plugin Name: WP2R2 Playground
 * Plugin URI:  https://github.com/your-username/wp2r2-playground
 * Description: A WASM-compatible WordPress backup solution for Cloudflare R2, designed specifically for WordPress Playground.
 * Version:     1.0.0
 * Author:      Google Senior PHP Engineer
 * Text Domain: wp2r2-playground
 * Requires PHP: 7.4
 */

// 嚴格禁止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 定義常數 (使用 WP2R2P 前綴避免衝突)
define( 'WP2R2P_VERSION', '1.0.0' );
define( 'WP2R2P_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP2R2P_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP2R2P_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WP2R2P_OPTION_GROUP', 'wp2r2p_settings_group' );
define( 'WP2R2P_OPTION_NAME', 'wp2r2p_options' );

/**
 * 外掛啟動鉤子：設定預設選項
 */
register_activation_hook( __FILE__, 'wp2r2p_activate_plugin' );

function wp2r2p_activate_plugin() {
	// 檢查 PHP 版本
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		wp_die( esc_html__( 'WP2R2 Playground requires PHP version 7.4 or higher.', 'wp2r2-playground' ) );
	}
	
	// 初始化預設選項 (如果不存在)
	if ( false === get_option( WP2R2P_OPTION_NAME ) ) {
		$default_options = array(
			'r2_account_id' => '',
			'r2_access_key' => '',
			'r2_secret_key' => '',
			'r2_bucket'     => '',
		);
		add_option( WP2R2P_OPTION_NAME, $default_options );
	}
}

/**
 * 載入並初始化類別
 */
function wp2r2p_run_plugin() {
	// 載入設定頁面類別
	require_once WP2R2P_PLUGIN_DIR . 'includes/class-wp2r2p-settings.php';
	new WP2R2P_Settings();
	
	// 載入備份與上傳類別
	require_once WP2R2P_PLUGIN_DIR . 'includes/class-wp2r2p-backup.php';
	require_once WP2R2P_PLUGIN_DIR . 'includes/class-wp2r2p-uploader.php';
}

add_action( 'plugins_loaded', 'wp2r2p_run_plugin' );

/**
 * 處理手動備份邏輯 (Admin Action)
 * 當使用者點擊後台的 "Backup Now" 按鈕時觸發
 */
add_action( 'admin_init', 'wp2r2p_handle_manual_backup' );

function wp2r2p_handle_manual_backup() {
	// 檢查是否有觸發備份的 POST 請求與 Nonce 驗證
	if ( isset( $_POST['wp2r2p_manual_backup'] ) && check_admin_referer( 'wp2r2p_backup_action', 'wp2r2p_nonce' ) ) {
		
		// 權限檢查
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$options = get_option( WP2R2P_OPTION_NAME );
		
		// 1. 執行壓縮 (使用 Playground 優化版 Backup 類別)
		$backup = new WP2R2P_Backup();
		$zip_path = $backup->create_backup();

		if ( is_wp_error( $zip_path ) ) {
			add_settings_error( 'wp2r2p_messages', 'wp2r2p_error', 'Backup failed: ' . $zip_path->get_error_message(), 'error' );
			return;
		}

		// 2. 執行上傳 (使用 Playground 優化版 Uploader 類別 - wp_remote_request)
		$uploader = new WP2R2P_Uploader( $options );
		$filename = basename( $zip_path );
		$result = $uploader->upload_file( $zip_path, $filename );

		// 3. 清理暫存檔
		$backup->cleanup_backup( $zip_path );

		// 4. 顯示結果通知
		if ( is_wp_error( $result ) ) {
			add_settings_error( 'wp2r2p_messages', 'wp2r2p_error', 'R2 Upload failed: ' . $result->get_error_message(), 'error' );
		} else {
			add_settings_error( 'wp2r2p_messages', 'wp2r2p_success', 'Playground Backup successfully uploaded to R2!', 'updated' );
		}
	}
}

/**
 * 顯示 Admin 通知訊息
 */
add_action( 'admin_notices', 'wp2r2p_display_admin_notices' );

function wp2r2p_display_admin_notices() {
    settings_errors( 'wp2r2p_messages' );
}
