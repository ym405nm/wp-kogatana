<?php
/*
Plugin Name: WP-PORTAL プラグイン
Plugin URI: https://github.com/ym405nm/wp-kogatana
Description: WP-PORTAL と連携するプラグインです
Author: WP-PORTL PROJECT
Version: 0.1
Author URI: https://wp-portal.net
*/

class WpKogatana {

    public $table_name = 'kogatana';

	function get_portal_icon() {
		return plugins_url( 'img/portal.png', __FILE__ );
	}

	function get_loading_icon() {
		return plugins_url( 'img/loading.gif', __FILE__ );
	}

	function __construct() {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
		add_action( 'wp_ajax_kogatana_update_info', array( __CLASS__, 'kogatana_update_info' ) );
		add_action( 'wp_ajax_nopriv_kogatana_update_info', array( __CLASS__, 'kogatana_update_info' ) );
		add_action( 'wp_ajax_kogatana_get_portal', array( __CLASS__, 'kogatana_get_portal' ) );
		add_action( 'wp_ajax_nopriv_kogatana_get_portal', array( __CLASS__, 'kogatana_get_portal' ) );
		add_action( 'parse_request', array( $this, 'handle_request' ) );
        add_action('wp_authenticate', array($this, 'handle_login'), 30, 2);
        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    function activate()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        if ($wpdb->get_var("show tables like '%" . $table_name . "%'") != $table_name) {
            $wpdb->query("
                CREATE TABLE `" . $table_name . "` (
                 `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                 `ipaddr` VARCHAR(25) NOT NULL,
                 `created_at` DATETIME NOT NULL,
                 `status` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                 `type` VARCHAR(255) NOT NULL,
                 `result` VARCHAR(255) NOT NULL,
                 `path` VARCHAR(255) NOT NULL,
                 `log` VARCHAR(255) NULL,
                 `password` VARCHAR(255) NULL,
                 `postdata` TEXT NULL,
                 PRIMARY KEY (`id`)
            )");
        }
        $default_options = array(
	        "log_send"     => false,
	        "log_password" => false,
	        "slack"        => false,
	        "slack_url"    => "",
	        "slack_token"  => "",
	        "api_token"    => ""
        );
        update_option("kogatana_settings", $default_options);
    }

    function handle_request()
    {
        $request_url     = $_SERVER['REQUEST_URI'];
        $content_dir     = "";
        $data_type       = "";
        $plugin_dir_path = WP_CONTENT_DIR;
        $plugin_split    = explode("/plugins/", $request_url);
        if (count($plugin_split) > 1) {
            $data_type     = "plugins";
            $content_right = $plugin_split[1];
            $content_dir   = explode("/", $content_right)[0];
        }
        $theme_split = explode("/themes/", $request_url);
        if (count($theme_split) > 1) {
            $data_type     = "themes";
            $content_right = $theme_split[1];
            $content_dir   = explode("/", $content_right)[0];
        }
        $target_file_path = $plugin_dir_path . "/" . $data_type . "/" . $content_dir;
        if (file_exists($target_file_path)) {
            // 存在する場合
            $result = "exists";
        } else {
            // 存在しない場合
            $result = "no exists";
        }
        if ( ! empty($content_dir)) {
            WpKogatana::insert_third_party_data($data_type, $content_dir, $result, null);
            $mes = "$data_type: $content_dir";
            WpKogatana::post_slack($mes);
        }

    }

    function handle_login($username, $password)
    {
        if (is_null($username) && is_null($password)) {
            // 通常アクセス
            return;
        }
        $user = wp_authenticate($username, $password);
        if ( ! is_wp_error($user)) {
            // ログイン成功した場合
            return;
        }
        $errors = $user->errors;
        $options = get_option("kogatana_settings");
        if(!$options["log_password"]){
            $password = "******";
        }
        if (count($errors) > 0) {
            WpKogatana::insert_login_data($username, $password);
            $mes = "login: $username,$password";
            WpKogatana::post_slack($mes);
        }
    }

    function post_slack($mes)
    {
        $options = get_option("kogatana_settings");
        if ( ! $options["slack"]) {
            return false;
        }
        $url     = $options["slack_url"];
        $payload = array("text" => $mes);
        $ch      = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'payload=' . json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);

        return true;
    }

    function insert_login_data($username, $password)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $sql        = $wpdb->prepare(
            "INSERT INTO `" . $table_name . "`
            (`created_at`, `ipaddr`, `type`, `result`, `path`, `log`, `password`)
            VALUES (NOW(), %s, %s, %s, %s, %s, %s)",
            $_SERVER["REMOTE_ADDR"],
            'login',
            'fail',
            'wp-login.php',
            $username,
            $password
        );
        $wpdb->query($sql);
    }

    function insert_third_party_data($data_type, $path, $result, $post_data = null)
    {
        global $wpdb;
        $table_name  = $wpdb->prefix . $this->table_name;
        $remote_addr = $_SERVER["REMOTE_ADDR"];
        if (is_null($post_data)) {
            $sql = $wpdb->prepare(
                "INSERT INTO `" . $table_name . "`
            (`created_at`, `ipaddr`, `type`, `result`, `path`)
            VALUES (NOW(), %s, %s, %s, %s)",
                $remote_addr,
                $data_type,
                $result,
                $path,
                $post_data
            );
        } else {
            $sql = $wpdb->prepare(
                "INSERT INTO `" . $table_name . "`
            (`created_at`, `ipaddr`, `type`, `result`, `path`, `postdata`)
            VALUES (NOW(), %s, %s, %s, %s, %s)",
                $remote_addr,
                $data_type,
                $result,
                $path,
                $post_data
            );
        }

        $wpdb->query($sql);

    }

    function create_output_text($content_dir, $request_url)
    {
        $remote_host      = $_SERVER['HTTP_HOST'];
        $current_date     = new DateTime();
        $current_datetime = $current_date->format('Y-m-d H:i:s');
        $output_text      = sprintf(
            "%s,%s,%s,%s",
            $current_datetime,
            $remote_host,
            $content_dir,
            $request_url
        );

        return $output_text;
    }


	function add_pages() {
        add_menu_page('WP PORTAL', 'WP PORTAL', 'level_8', "WP PORTAL", array($this, 'wp_kogatana_settings'),
			$this->get_portal_icon() );
	}

	function reset_all_data(){
		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;
		$wpdb->query(
			"truncate table `$table_name`"
		);
		return true;
	}

	function wp_kogatana_settings() {
		$post_error_mes = "";
		$reset_data = false;
		$post_data  = false;
		$nonce_error_mes = "ページ遷移が正しくありません";
		// リセット処理
		if (array_key_exists("reset", $_POST)){
			// nonce 検証
			if(wp_verify_nonce($_POST['reset-nonce'], 'reset-nonce')){
				$this->reset_all_data();
				$reset_data = true;
			}else{
				$post_error_mes = $nonce_error_mes;
			}
		}
		// 設定変更処理
        if (array_key_exists("kogatana", $_POST)) {
			if(wp_verify_nonce($_POST['settings-nonce'], 'settings-nonce')){
				$post_data = WpKogatana::post_data($_POST["kogatana"]);

			}else{
				$post_error_mes = $nonce_error_mes;
			}
        }
		wp_enqueue_style( "kogatana-default", plugins_url() . "/wp-kogatana/css/kogatana-default.css" );
		wp_enqueue_style( "kogatana-table", plugins_url() . "/wp-kogatana/css/kogatana-table.css" );
		$all_plugins = get_plugins();
		$all_themes  = wp_get_themes();
        $login_data = $this->get_login_data();
        $third_data = $this->get_third_party_data();
		echo "<h1>WP PORTAL 攻撃ログチェッカー</h1>";
		echo "<div id='link-portal'><span class='dashicons dashicons-share-alt2'></span><a href='https://wp-portal.net' target='_blank'> WP PORTAL にアクセスする</a></div>";
        if($post_data){
            echo "<div class=\"notice notice-success\"><p><strong>設定を変更しました。</strong></p></div>";
        }
		if($reset_data){
			echo "<div class=\"notice notice-success\"><p><strong>リセットしました。</strong></p></div>";
		}
		if(!empty($post_error_mes)){
			$post_error_mes = esc_html($post_error_mes);
			echo "<div class=\"notice notice-error\"><p><strong>$post_error_mes</strong></p></div>";
		}
		echo "<h2><span class=\"dashicons dashicons-testimonial\"></span> テーマ一覧</h2>";
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
<div id="kogatana_updates_{$theme_param_id}_portal"><img src="{$this->get_loading_icon()}"></div>
</td>
</tr>
EOF;
		}
		echo "</table>";
		echo "<h2><span class=\"dashicons dashicons-testimonial\"></span> プラグイン一覧</h2>";
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
<div id="kogatana_updates_{$plugin_param_id}_portal"><img src="{$this->get_loading_icon()}"></div>
</td>
</tr>
EOF;
		}
		echo "</table>";
        echo "<h2><span class=\"dashicons dashicons-visibility\"></span> 過去の要注意アクセス</h2>";
        echo "<h3>ログイン</h3>";
        echo "<div class=\"updated fade plugins-update-notice\" >
            <p>過去にログインが失敗したアドレスです。特定のIPアドレスからの情報がないかどうか確認してください。</p>
        </div>";
        echo "<table><tr><th>時刻</th><th>IPアドレス</th><th>ユーザ</th><th>パスワード</th></tr>";
        foreach ($login_data as $login_request) {
            $output_login_request = sprintf(
                "<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>",
                esc_html($login_request->created_at),
                esc_html($login_request->ipaddr),
                esc_html($login_request->log),
                esc_html($login_request->password)
            );
            echo $output_login_request;
        }
        echo "</table>";
        echo "<h3>プラグイン/テーマ</h3>";
        echo "<div class=\"updated fade plugins-update-notice\" >
            <p>存在しないリソースへのアクセスです。特定のIPアドレスからの情報がないかどうか確認してください。</p>
        </div>";
        echo "<table><tr><th>時刻</th><th>IPアドレス</th><th>アクセス先</th><th>製品名</th></tr>";
        foreach ($third_data as $third_request) {
            $output_third_request = sprintf(
                "<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>",
                esc_html($third_request->created_at),
                esc_html($third_request->ipaddr),
                esc_html($third_request->type),
                esc_html($third_request->path)
            );
            echo $output_third_request;
        }
        echo "</table>";
        echo $this->output_form();
        wp_enqueue_script('wp-kogatana-default', plugins_url() . '/wp-kogatana/js/default.js', array('jquery'),
			'1.0' );
	}

    function post_data($request_data)
    {
        $update_option = array(
	        "log_send"     => false,
	        "log_password" => false,
	        "slack"        => false,
	        "slack_url"    => "",
	        "slack_token"  => "",
	        "api_token"    => ""
        );
        if (array_key_exists("log_send", $request_data)) {
            $update_option["log_send"] = true;
        }
        if (array_key_exists("log_password", $request_data)) {
            $update_option["log_password"] = true;
        }
        if (array_key_exists("slack", $request_data)) {
            $update_option["slack"] = true;
        }
        if (array_key_exists("slack_url", $request_data)) {
            $update_option["slack_url"] = $request_data["slack_url"];
        }
        if (array_key_exists("slack_token", $request_data)) {
            $update_option["slack_token"] = $request_data["slack_token"];
        }
	    if ( array_key_exists( "api_token", $request_data ) ) {
		    $update_option["api_token"] = $request_data["api_token"];
	    }
        update_option('kogatana_settings', $update_option);

	    // デバッグモードの場合 host パラメータがついてくる可能性がある
	    if ( array_key_exists( "host", $request_data ) ) {
		    update_option( "kogatana_host", $request_data["host"] );
	    }

        return true;
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

	public function kogatana_get_portal() {
		$data_type   = $_POST["data-type"];
		$api_token   = $_POST["api_token"];
		$protocol    = "https://";
		$path        = "/api/v1/rankings/";
		$option_host = get_option( 'kogatana_host' );

		// TOKEN 指定
		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_token
			)
		);

		// デバッグ環境の場合 verify バイパス
		if ( WP_DEBUG ) {
			$args['sslverify'] = false;
		}

		if ( WP_DEBUG && $option_host ) {
			$host = $option_host;
		} else {
			$host = "wp-portal.net";
		}
		$url      = $protocol . $host . $path . $data_type;
		$response = wp_remote_request( $url, $args );
		header( 'Content-Type: application/json; charset=utf-8' );
		$response_body = $response['body'];
		$response_body = json_decode( $response_body );
		if(property_exists($response_body, "error")){
			$result = array(
				"error" => $response_body->error
			);
			echo json_encode($result);
			die();
		}
		$result        = array();
		foreach ( $response_body as $response_item ) {
			$result[ $response_item->key ] = $response_item->doc_count;
		}
		echo json_encode( $result );
		die();
	}


	public function output_form()
    {
        $options = get_option('kogatana_settings');
        if ($options["log_send"]) {
            $log_send = " checked";
        } else {
            $log_send = "";
        }
        if ($options["log_password"]) {
            $log_password = " checked";
        } else {
            $log_password = "";
        }
        if ($options["slack"]) {
            $slack = " checked";
        } else {
            $slack = "";
        }
        if ($options["slack_url"] && ! empty($options["slack_url"])) {
            $slack_url = esc_html($options["slack_url"]);
        } else {
            $slack_url = "";
        }
	    if ( $options["api_token"] && ! empty( $options["api_token"] ) ) {
		    $api_token = esc_html( $options["api_token"] );
	    } else {
		    $api_token = "";
	    }
	    $reset_form_html = file_get_contents( plugin_dir_path( __FILE__ ) . '/inc/reset_form.html' );
	    $reset_form_html = str_replace("{{nonce}}", wp_create_nonce( 'reset-nonce' ), $reset_form_html);
        $settings_nonce = wp_create_nonce('settings-nonce');
	    if ( WP_DEBUG ) {
		    $portal_host = get_option( "kogatana_host", "" );
		    $debug_form  = "WP PORTAL ホスト名 : <input type=\"text\" name=\"kogatana[host]\" value=\"$portal_host\"><br>";
	    } else {
		    $debug_form = "";
	    }
	    $output_form = <<< EOF
<h2>設定</h2> 
<form action="" method="post">
<input type="checkbox" name="kogatana[log_send]" id="log_send"$log_send><label for="log_send">ログを WP PORTAL へ送信する（データは匿名化され分析後、公開される可能性があります）</label><br><br>
<input type="checkbox" name="kogatana[log_password]" id="log_password"$log_password><label for="log_password">ログイン試行で使用されたパスワードを保存する（研究・調査目的のみ推奨）</label><br><br>
<input type="checkbox" name="kogatana[slack]" id="slack"$slack><label for="slack">不信なリクエスト受信時にSlackに投稿する</label> ※ パフォーマンスが低下する恐れがあります<br>
（
<label for="slack_url">投稿先 Slack Incoming Hook の URL</label><input type="text" name="kogatana[slack_url]" id="slack_url" value="$slack_url" style="width:600px;">
）<br>
<input type="hidden" name="settings-nonce" value="$settings_nonce">
$debug_form
<label for="slack_url">WP PORTAL の API トークン : </label><input type="text" name="kogatana[api_token]" id="api_token" value="$api_token" style="width:600px;">
<p>API トークンを取得するには、WP PORTALにログインして、右上のアイコンをクリックし、「アプリ連携」メニューから取得してください。 <a href="https://wp-portal.net" target="_blank">ログイン・新規登録はこちら</a></p>
<br><br><br>
<input type="submit" class="button-primary" value="設定を更新">

</form>
$reset_form_html
EOF;

        return $output_form;
    }

    public function get_login_data($limit = 100)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $sql        =
            "SELECT * FROM `" . $table_name . "`
            WHERE `type` = 'login'
            ORDER BY id DESC
            LIMIT " . $limit;
        $result     = $wpdb->get_results($sql);

        return $result;
    }

    public function get_third_party_data($limit = 100)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $sql        =
            "SELECT * FROM `" . $table_name . "`
            WHERE `type` = 'themes' OR `type` = 'plugins'
            ORDER BY id DESC
            LIMIT " . $limit;
        $result     = $wpdb->get_results($sql);

        return $result;
    }

}

$wpKogatana = new WpKogatana;
