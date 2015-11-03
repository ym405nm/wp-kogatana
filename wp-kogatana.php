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
	var $table_name;
	function __construct() {
		// テーブル設定
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'kogatana';
		register_activation_hook (__FILE__, array($this, 'initial_create_table'));
		
		add_action('wp_authenticate', array($this, 'wp_authenticate_log'), 10, 2);
	}

	function initial_create_table(){
		global $wpdb;
		$sql = "CREATE TABLE IF NOT EXISTS `" . $this->table_name . "` (
					`id` BIGINT NOT NULL ,
					`src_ip` VARCHAR(64) NOT NULL ,
					`url` VARCHAR(1024) NOT NULL ,
					`content` LONGTEXT NOT NULL ,
					`status` INT NOT NULL DEFAULT '0' ,
					`created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ) 
					CHARACTER SET 'utf8';";
		$wpdb->query($sql);
		$sql = "ALTER TABLE `" . $this->table_name . "` ADD PRIMARY KEY (`id`);";
		$wpdb->query($sql);
		$sql = "ALTER TABLE `" . $this->table_name . "` MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;";
		$wpdb->query($sql);
	}

	function wp_authenticate_log($user_login, $user_password){
		global $wpdb;
		if(empty($user_login))
			return;
		$user = wp_authenticate($user_login, $user_password);
		if(is_wp_error($user)){
			// テーブルに入れる
			$wpdb->insert($this->table_name, array(
					"src_ip" => $_SERVER['REMOTE_ADDR'],
					"url" => $_SERVER['REQUEST_URI'],
					"content" => sprintf("%s,%s", $user_login, $user_password)
			));
		}
	}
}
$wpKogatana = new WpKogatana;
