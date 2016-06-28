<?php
/*
Plugin Name: WP-Kogatana
Plugin URI: https://github.com/ym405nm/wp-kogatana
Description: WP-Kogatana
Author: yoshinori matsumoto
Version: 0.1
Author URI: https://twitter.com/ym405nm
*/

class WpKogatana {

	function get_portal_icon() {
		return plugins_url() . "/wp-kogatana/img/portal.png";
	}

	function get_loading_icon() {
		return plugins_url() . "/wp-kogatana/img/loading.gif";
	}

	function __construct() {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
		add_action( 'wp_head', 'add_my_ajaxurl', 1 ); // インライン JavaScript 読み込み
		add_action( 'wp_ajax_kogatana_update_info', array( __CLASS__, 'kogatana_update_info' ) );
		add_action( 'wp_ajax_nopriv_kogatana_update_info', array( __CLASS__, 'kogatana_update_info' ) );
	}

	function add_pages() {
		add_menu_page( 'wp-kogatana', 'wp-kogatana', 'level_8', "wp-kogatana", array( $this, 'wp_kogatana_settings' ),
			$this->get_portal_icon() );
	}

	function wp_kogatana_settings() {
		wp_enqueue_style( "kogatana-default", plugins_url() . "/wp-kogatana/css/kogatana-default.css" );
		wp_enqueue_style( "kogatana-table", plugins_url() . "/wp-kogatana/css/kogatana-table.css" );
		$all_plugins = get_plugins();
		$all_themes  = wp_get_themes();
		echo "<h1>WP PORTAL 攻撃ログチェッカー</h1>";
		echo "<div id='link-portal'><span class='dashicons dashicons-share-alt2'></span><a href='https://wp-portal.net' target='_blank'> WP PORTAL にアクセスする</a></div>";
		echo "<h2>テーマ一覧</h2>";
		echo "<div class=\"updated fade update-notice themes-update-notice\" ><p>テーマがあるプラグインがあります <a href='/wp-admin/update-core.php'>詳しく</a></p></div>";
		echo "<table><tr><th>テーマ名</th><th>インストールされているバージョン</th><th>最新バージョン</th><th><img src=\"{$this->get_portal_icon()}\">WP PORTAL</th></tr>";
		foreach ( $all_themes as $theme => $theme_param ) {
			$theme_slug     = $theme;
			$theme_param_id = str_replace( " ", "-", $theme_param["Name"] );
			echo <<< EOF
<tr>
<td>
<div class="kogatana_updates" data-type="themes" data-name="{$theme_slug}" id="kogatana_updates_{$theme_param_id}">{$theme_param["Name"]}</div>
</td>
<td>
<div id="kogatana_updates_{$theme_param_id}_current" data-version="{$theme_param["Version"]}">{$theme_param["Version"]}</div>
</td>
<td>
<div id="kogatana_updates_{$theme_param_id}_result"><img src="{$this->get_loading_icon()}"></div>
</td>
<td>
<div id="kogatana_updates_{$theme_param_id}_portal">ログなし</div>
</td>
</tr>
EOF;
		}
		echo "</table>";
		echo "<h2>プラグイン一覧</h2>";
		echo "<div class=\"updated fade update-notice plugins-update-notice\" ><p>アップデートがあるプラグインがあります <a href='/wp-admin/update-core.php'>詳しく</a></p></div>";
		echo "<table><tr><th>プラグイン名</th><th>インストールされているバージョン</th><th>最新バージョン</th><th><img src=\"{$this->get_portal_icon()}\">WP PORTAL</th></tr>";
		foreach ( $all_plugins as $plugin => $plugin_param ) {
			if ( "hello.php" === $plugin ) {
				// Hello Dolly は省略
				continue;
			}
			$plugin_slug     = explode( "/", $plugin )[0];
			$plugin_param_id = str_replace( " ", "-", $plugin_param["Name"] );
			echo <<< EOF
<tr>
<td>
<div class="kogatana_updates" data-type="plugins" data-name="{$plugin_slug}" id="kogatana_updates_{$plugin_param_id}">{$plugin_param["Name"]}</div>
</td>
<td>
<div id="kogatana_updates_{$plugin_param_id}_current" data-version="{$plugin_param["Version"]}">{$plugin_param["Version"]}</div>
</td>
<td>
<div id="kogatana_updates_{$plugin_param_id}_result"><img src="{$this->get_loading_icon()}"></div>
</td>
<td>
<div id="kogatana_updates_{$theme_param_id}_portal">ログなし</div>
</td>
</tr>
EOF;
		}
		echo "</table>";
		wp_enqueue_script( 'wp-kogatana-default', plugins_url() . '/wp-kogatana/js/default.js', array( 'jquery' ),
			'1.0' );
	}

	function add_my_ajaxurl() {
		echo "<script>var ajaxurl = '" . admin_url( 'admin-ajax.php' ) . "';</script>";
	}

	function kogatana_update_info() {
		$result    = array();
		$slug      = $_POST["target_name"];
		$data_type = $_POST["data_type"];
		if ( "themes" === $data_type ) {
			$base_url   = "https://api.wordpress.org/themes/info/1.1/?action=theme_information&request[slug]=";
			$plugin_url = $base_url . $slug;
		} else {
			$base_url   = "https://api.wordpress.org/plugins/info/1.0/";
			$plugin_url = $base_url . $slug . ".json";
		}
		$response         = wp_remote_get( $plugin_url );
		$result["url"]    = $plugin_url;
		$response_json    = json_decode( $response["body"] );
		$response_version = $response_json->version;
		if ( is_null( $response_version ) ) {
			$result["result"] = "ng";
		} else {
			$result["result"]  = "ok";
			$response_json     = json_decode( $response["body"] );
			$result["version"] = $response_json->version;
		}
		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode( $result );
		die();
	}

}

$wpKogatana = new WpKogatana;
