<?php
/*
  Plugin Name: AI NIKKI
  Version: β1.0.0
  Description: AIサービスのAPIを使い日記を作成・投稿します。 ※対応AIサービス：Google AI Gemini
  Author: KALEIDOSCOPE co.,Ltd.
  Author URI: https://kaleidoscope.co.jp/
  Text Domain: ks-ai_nikki
  Domain Path: /languages
  License: GPLv2
 */
require_once(dirname(__FILE__).'/class.php');

$ks_ai_nikki = new Ks_AI_nikki();
add_action('init', [$ks_ai_nikki, 'init']);
if(is_admin()){
  add_action('admin_init', [$ks_ai_nikki, 'ks_post']);    
  add_action('admin_menu', [$ks_ai_nikki, 'set_plugin_menu']);     // メニュー追加
  add_action('admin_menu', [$ks_ai_nikki, 'set_plugin_sub_menu']); // サブメニュー追加
  add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($actions){// プラグイン一覧：メニュー追加
    $menu_settings_url = '<a href="' . esc_url(admin_url('admin.php?page=ks-ai_nikki-config')) . '">設定</a>';
    array_unshift($actions, $menu_settings_url);
    return $actions;
  });
}
if( $ks_ai_nikki->check_AI() ){
  $ks_ai_nikki->ks_post_sched();
  $ks_ai_nikki->shortcode();
  $post_types = get_post_types(['public' => true,'_builtin' => false]);
  $post_types = array_merge(['post', 'page'], array_values($post_types));
  if( is_array($post_types)){
    foreach( $post_types as $key => $val ){
      add_action( "admin_footer-{$val}-new.php", [$ks_ai_nikki, 'print_managementScreen_post']);
      add_action( "admin_footer-{$val}.php",     [$ks_ai_nikki, 'print_managementScreen_post']);
    }
  }
}
