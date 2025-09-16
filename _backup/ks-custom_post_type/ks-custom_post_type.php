<?php
/*
  Plugin Name: KS CPT
  Version: β1.0.0
  Description: カスタム投稿タイプ設定
  Author: KALEIDOSCOPE co.,Ltd.
  Author URI: https://kaleidoscope.co.jp/
  Text Domain: ks-custom_post_type
  Domain Path: /languages
  License: GPLv2
 */
require_once(dirname(__FILE__).'/class.php');

$ks-custom_post_type = new Ks-CustomPostType();
add_action('init', [$ks-custom_post_type, 'init']);
if(is_admin()){
  add_action('admin_init', [$ks-custom_post_type, 'ks_post']);    
  add_action('admin_menu', [$ks-custom_post_type, 'set_plugin_menu']);     // メニュー追加
  add_action('admin_menu', [$ks-custom_post_type, 'set_plugin_sub_menu']); // サブメニュー追加
  add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($actions){// プラグイン一覧：メニュー追加
    $menu_settings_url = '<a href="' . esc_url(admin_url('admin.php?page=ks-ai_nikki-config')) . '">設定</a>';
    array_unshift($actions, $menu_settings_url);
    return $actions;
  });
}

