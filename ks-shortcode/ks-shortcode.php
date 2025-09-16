<?php
/*
  Plugin Name: KS ショートコード
  Version: 1.0.0
  Description: オリジナルの便利なショートコードです
  Author: KALEIDOSCOPE co.,Ltd.
  Author URI: https://kaleidoscope.co.jp/
  Text Domain: ks-shortcode
  Domain Path: /languages
  License: GPLv2
 */
$ks_shortcode = new Ks_Shortcode;
if(is_admin()){
  add_action('admin_menu', [$ks_shortcode, 'set_plugin_menu']);     // メニュー追加
  add_action('admin_menu', [$ks_shortcode, 'set_plugin_sub_menu']); // サブメニュー追加
  add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($actions){// プラグイン一覧：メニュー追加
    $menu_settings_url = '<a href="' . esc_url(admin_url('admin.php?page=ks-shortcode')) . '">概要</a>';
    array_unshift($actions, $menu_settings_url);
    return $actions;
  });
}
$ks_shortcode->shortcode();

class Ks_Shortcode{
  const PLUGIN_ID = 'ks-shortcode';
  const NAME = "KS ショートコード";
  /*################################################################################
    初期処理
  ################################################################################*/
  static function init(){
    return new self();
  }
  function __construct(){
    //言語ファイル
    #load_plugin_textdomain(self::PLUGIN_ID, false, basename( dirname( __FILE__ ) ).'/languages' );
  }
  /*################################################################################
    管理画面メニュ
  ################################################################################*/
  //メニュー
  public function set_plugin_menu(){ /* アイコン： https://developer.wordpress.org/resource/dashicons/#awards*/
    add_menu_page(self::NAME, self::NAME, 'manage_options', self::PLUGIN_ID, [$this, "print_defalut_page"], 'dashicons-format-gallery'/* アイコン */, 99);
  }
  //サブメニュー
  function set_plugin_sub_menu(){
    $mode = !empty($_POST['mode']) ? $_POST['mode'] : 'post';
    $modes = [/*
      [ "key" => "config", "name" => "設定"],
    */];
    add_submenu_page(self::PLUGIN_ID, '概要 < '.self::NAME, "概要", 'manage_options', self::PLUGIN_ID, [$this, "print_defalut_page"]);
	if(!empty($modes)){
		foreach( $modes as $key => $val){
			if( $val["key"] == "config" || $val["key"] == "import" || $this->check_AI() ){
				add_submenu_page(self::PLUGIN_ID, $val["name"].' < '.self::NAME, $val["name"], 'manage_options', self::PLUGIN_ID."-{$val["key"]}", [$this, "print_{$val["key"]}_page"]);
			}
		}
	}
  }
  /*################################################################################
    表示
  ################################################################################*/
	//#### 通知メッセージ ####//
	public function print_message(){
		$check['error']    = esc_html( get_transient(self::PLUGIN_ID.'-errors') );
		$check['updated']  = esc_html( get_transient(self::PLUGIN_ID.'-updated') );
		$status = !empty($check['error']) ? 'error' : 'updated';
		$message = $check[$status];
		if(!empty($message)){
			wp_admin_notice(
				__( $message, 'my-text-domain' ),
				array(
				'type'               => $status,
				'dismissible'        => true,
				'additional_classes' => array( 'inline', "notice-alt {$status}" ),
				'attributes'         => array( 'data-slug' => 'post_types-slug' )
				)
			);
		}
	}
  //#### デフォルトページ ####//
  public function print_defalut_page(){
    $selfTitle = esc_html(self::NAME);
	echo <<<HTML
    <div class="wrap">
	  <h2>{$selfTitle}</h2>
      <p>弊社作成時によく使うオリジナルのショートコードです。</p>
	  <h3>&lbrack;ks_func name="●●●●" arg="●●●●" sub="●●●●" buffer="●●●●"&rbrack;</h3>
	  <p>関数の処理結果を出力します。</p>
	  <h4>属性</h4>
	  <ul>
		<li><span class="name">name</span><span>関数名</span></li>
		<li><span class="name">arg</span><span>引数 ,区切りで複数 タグを使用したい場合は &lt;を&amp;lt; &gt;を&amp;gt; に置き換えてください。</span></li>
		<li><span class="name">sub</span><span>返り値の添字</span></li>
		<li><span class="name">buffer</span><span>出力のバッファリングを有効にする場合は1</span></li>
	  </ul>
	  <h4>使用例</h4>
	  <ul>
		<li><span class="name">サイトURL</span><span>&lbrack;ks_func name="site_url"&rbrack;</span></li>
		<li><span class="name">閲覧者IPアドレス</span><span>&lbrack;ks_func name="getenv" arg="REMOTE_ADDR"&rbrack;</span></li>
		<li><span class="name">リファラ</span><span>&lbrack;ks_func name="getenv" arg="HTTP_REFERER"&rbrack;</span></li>
		<!--<li><span class="name">共通表示情報</span><span>&lbrack;ks_func name="get_option" arg="common_disp"&rbrack;</span></li>-->
		<li><span class="name">投稿タイトル</span><span>&lbrack;ks_func name="get_the_title"&rbrack; もしくは &lbrack;ks_func name="the_title" arg="&amp;lt;h1&amp;gt;, &amp;lt;/h1&amp;gt;" buffer=1&rbrack;</span></li>
		<li><span class="name">アーカイブタイトル</span><span>&lbrack;ks_func name="get_the_archive_title"&rbrack; もしくは &lbrack;ks_func name="the_archive_title" arg="&amp;lt;h1&amp;gt;, &amp;lt;/h1&amp;gt;" buffer=1&rbrack;</span></li>
		<li><span class="name">ウィジェット</span><span>&lbrack;ks_func name="dynamic_sidebar" arg="sidebar-1" buffer=1&rbrack;</span></li>
		<li><span class="name">yoastパンくず</span><span>&lbrack;ks_func name="yoast_breadcrumb" arg='&amp;lt;div id="breadcrumbs" class="inner"&amp;gt;,&amp;lt;/div&amp;gt;' buffer=1&rbrack;</span></li>
		<li><span class="name">テンプレート表示</span><span>&lbrack;ks_func name="get_template_part" arg="template-parts/,list" buffer=1&rbrack;</span></li>
	  </ul>
	  <h3>&lbrack;ks_field name="●●●●" func="●●●●" arg="●●●●" buffer="●●●●"&rbrack;</h3>
	  <p>フィールドの値を出力します。</p>
	  <h4>属性</h4>
	  <ul>
		<li><span class="name">name</span><span>フィールド名</span></li>
		<li><span class="name">func</span><span>返り値を処理する関数 ,区切りで複数指定 返り値を指定する場合は *result* と記載してください。</span></li>
		<li><span class="name">arg</span><span>返り値を処理する関数の引数</span></li>
		<li><span class="name">buffer</span><span>出力のバッファリングを有効にする場合は1</span></li>
	  </ul>
	  <h4>使用例</h4>
	  <ul>
		<li><span class="name">フィールド名:photo<br>画像URL</span><span>&lbrack;ks_field name="photo" func="wp_get_attachment_url" arg="*result*,full"&rbrack;</span></li>
	  </ul>
	  <h3>&lbrack;ks_arc post_type="●●●●" posts_per_page="●●●●" paged="●●●●" new_days="●●●●" category__in="●●●●" category__not_in="●●●●" term__in="●●●●" tag__not_in="●●●●" orderby="●●●●" order="●●●●" template="●●●●" tag="●●●●" add_class="●●●●" navi="●●●●"&rbrack;</h3>
	  <p>投稿データの一覧を出力します。</p>
	  <h4>属性</h4>
	  <ul>
		<li><span class="name">post_type</span><span>投稿タイプ /*this*/でアクセスしているページの投稿タイプを指定</span></li>
		<li><span class="name">posts_per_page</span><span>表示件数。-1なら全件表示 デフォルトはwordpressで設定されている表示件数</span></li>
		<li><span class="name">paged</span><span>取得ページ</span></li>
		<li><span class="name">new_days</span><span>指定した整数値日以内に投稿されたpostデータにnewクラスを追加</span></li>
		<li><span class="name">category__in</span><span>絞込カテゴリ</span></li>
		<li><span class="name">category__not_in</span><span>除外カテゴリ</span></li>
		<li><span class="name">term__in</span><span>絞込ターム</span></li>
		<li><span class="name">tag__not_in</span><span>除外ターム</span></li>
		<li><span class="name">orderby</span><span>ソートする要素名</span></li>
		<li><span class="name">order</span><span>昇降順の指定 昇順:ASC 降順:DESC デフォルトはDESC</span></li>
		<li><span class="name">template</span><span>テーマの「template-parts」フォルダ内にあるlist-●●●●.phpを投稿データのテンプレートとして表示</span></li>
		<li><span class="name">tag</span><span>一覧を囲むタグ デフォルトはdiv</span></li>
		<li><span class="name">add_class</span><span>一覧を囲むタグに追加するclass</span></li>
		<li><span class="name">navi</span><span>1より大きい整数値を指定するとページネーションを一覧下に追加</span></li>
	  </ul>
	  <h4>使用例</h4>
	  <ul>
		<li><span class="name">POST一覧</span>&lbrack;ks_arc post_type="post"&rbrack;</span></li>
	  </ul>
	  <h3>&lbrack;ks_terms taxonomy="●●●●" hide_empty="●●●●" parent="●●●●" child_of="●●●●" tag="●●●●" add_class="●●●●"&rbrack;</h3>
	  <p>タクソノミの一覧を出力します。</p>
	  <h4>属性</h4>
	  <ul>
		<li><span class="name">taxonomy</span><span>タクソノミを指定 ,区切りで複数</span></li>
		<li><span class="name">hide_empty</span><span>投稿記事がないタクソノミを非表示 非表示の場合1</span></li>
		<li><span class="name">parent</span><span>親タクソノミで絞り込み term_idを指定</span></li>
		<li><span class="name">child_of</span><span>先祖タクソノミで絞り込み  term_idを指定</span></li>
		<li><span class="name">post_arc</span><span>カスタム投稿タイプ一覧記事ページへのリンクを表示 表示の場合1</span></li>
		<li><span class="name">tag</span><span>一覧を囲むタグ デフォルトは ul</span></li>
		<li><span class="name">add_class</span><span>一覧を囲むタグに追加するclass</span></li>
	  </ul>
	  <h4>使用例</h4>
	  <ul>
		<li><span class="name">カテゴリ、タグ一覧</span>&lbrack;ks_terms taxonomy="category,post_tag"&rbrack;</span></li>
	  </ul>
	  <h3>&lbrack;ks_disp post_type="●●●●" post_type__not_in="●●●●" term_id="●●●●" term_id__not_in="●●●●" post_id="●●●●" post_id__not_in="●●●●" disp_type="●●●●" disp_type__not_in="●●●●"&rbrack; content &lbrack;/ks_disp&rbrack;</h3>
	  <p>記載された条件時に囲まれた内容を出力します。各項目,区切りで複数設定することができます。</p>
	  <h4>属性</h4>
	  <ul>
		<li><span class="name">post_type</span><span>表示投稿タイプ</span></li>
		<li><span class="name">post_type__not_in</span><span>除外投稿タイプ</span></li>
		<li><span class="name">term_id</span><span>表示term_id</span></li>
		<li><span class="name">term_id__not_in</span><span>除外term_id</span></li>
		<li><span class="name">post_id</span><span>表示投稿ID</span></li>
		<li><span class="name">post_id__not_in</span><span>除外投稿ID</span></li>
		<li><span class="name">disp_type</span><span>is_[disp_type]で判定 sigle, archive, search, home, front_page</span></li>
		<li><span class="name">disp_type__not_in</span><span>is_[disp_type]で除外判定 sigle, archive, search, home, front_page</span></li>
	  </ul>
	  <h4>使用例</h4>
	  <ul>
		<li><span class="name">表示投稿タイプ info</span>&lbrack;ks_disp post_type="info"&rbrack; content &lbrack;/ks_disp&rbrack;</span></li>
	  </ul>
	</div>
<style>
	.wrap h3{padding:.5rem;margin-top:2rem;background-color:rgba(0, 0, 0, 0.07)}
	.wrap h4{margin-bottom:0}
	.wrap li>*{vertical-align:top}
	.wrap li .name{display:inline-block;width:10em}
</style>
HTML;
  }
  //#### ショートコード ####//
  public function shortcode(){
	//**********************************************************//
	//関数
	add_shortcode('ks_func', function($atts){
		extract(shortcode_atts([
			'name' => '',	//変数名 環境変数を取得したい場合はgetenv
			'arg' => '',	//引数
			'sub' => '',	//関数の返り値が配列の場合に指定する添字
			'sc'  => '',	//引数の値に使うショートコードargで/*sc*/を指定
			'buffer' => ''	//出力のバッファリングを有効にする
		], $atts));
		$result = "";
		$arg = str_replace('&lt;', "<", $arg);
		$arg = str_replace('&gt;', ">", $arg);
		$args = explode(',', $arg);
		if( !empty($args) ){
			foreach( $args as $key => $val ){
				$val == "/*sc*/" && !empty($sc) && ( $args[$key] = do_shortcode("[{$sc}]") );
			}
		}
		if( in_array($name,['_GET','_POST'])){
			$data = $name == '_GET' ? $_GET : $_POST;
			if(!empty($data)){
				$temp = [];
				foreach($data as $key => $val){
					if( in_array($key,$args) ){
						$temp[] = $val;
					}
				}
				$result = implode(',', $temp);
			}

		}else if( !empty($name) && function_exists($name) ){
			if(!empty($buffer)){
				if( $name == "dynamic_sidebar" && ! is_active_sidebar($arg)){
					return "ウィジェット「{$arg}」は存在しません。";
				}
				ob_start();
				$name(...$args);
				$result = ob_get_contents();
				ob_end_clean();
			}else{
				$result = $name(...$args);
			}
			if( is_array($result) ){
				$result = $sub === "" ? print_r($result, true) : $result[$sub];
			}
		}else{
			$result = "name:正しい関数名を指定してください。";	
		}
		return $result;
	});
	//フィールド
	add_shortcode('ks_field', function($atts){
		extract(shortcode_atts([
			'name' => '', //変数名
			'func' => '',//返り値を処理する関数
			'arg' => '',//返り値を処理する関数の引数 ,区切りで複数引き渡し *result*と記載すると返り値に変換
			'buffer' => ''//出力のバッファリングを有効にする
		], $atts));
		if( !empty($name) ){
			$result = get_post_meta(get_the_ID(), $name, true);
			if( !empty($result) && !empty($func) ){
				if(function_exists($func)){
					$arg = str_replace('*result*', $result, $arg);
					$args = explode(',', $arg);
					if(!empty($buffer)){
						ob_start();
						$func(...$args);
						$result = ob_get_contents();
						ob_end_clean();
					}else{
						$result = $func(...$args);
					}
				}else{
					$result = "func:正しい関数名を指定してください。";	
				}
			}
		}else{
			$result = "name:フィールド名を指定してください。";	
		}
		return $result;
	});
	//閲覧者環境
	add_shortcode('ks_disp', function($atts, $content = null){
		extract(shortcode_atts([
			'post_type' => "",	//,区切りで複数
			'post_type__not_in' => '',
			'term_id' => '',
			'term_id__not_in' => '',
			'post_id' => '',
			'post_id__not_in' => '',
			'disp_type' => '',	//sigle, archive, search, home, front_page ※,区切りで複数
			'disp_type__not_in' => ''
		], $atts));

		$result = "";
		$flag = true;

		if( !empty($post_type) ){
			$post_types = explode(",", $post_type);
			$flag &= in_array(get_post_type(), $post_types);
		}
		if( !empty($post_type__not_in) ){
			$post_types = explode(",", $post_type__not_in);
			$flag &= ! in_array(get_post_type(), $post_types);
		}
		if( !empty($term_id) ){
			$term_ids = explode(',', $term_id);
			if( is_archive() || is_search() || is_home() ){
				$this_term_id_obj = get_queried_object();
				$flag &= in_array($this_term_id_obj->term_id_id, $term_ids);
			}else{
				$this_term_ids = wp_get_post_term_ids();
				if(!empty($term_ids) && !is_wp_error($term_ids)){
					$flag_term = false;
					foreach ($this_term_ids as $val) {
						if(in_array($val->term_id_id, $this_term_ids)){
							$flag_term = true;
							break;
						}
					}
					$flag &= $flag_term;	
				}
			}
		}
		if( !empty($term_id__not_in) ){
			$term_ids = explode(',', $term_id__not_in);
			if( is_archive() || is_search() || is_home() ){
				$this_term_id_obj = get_queried_object();
				$flag &= ! in_array($this_term_id_obj->term_id_id, $term_ids);
			}else{
				$this_term_ids = wp_get_post_term_ids();
				if(!empty($term_ids) && !is_wp_error($term_ids)){
					$flag_term = false;
					foreach ($this_term_ids as $val) {
						if(! in_array($val->term_id_id, $this_term_ids)){
							$flag_term = true;
							break;
						}
					}				
					$flag &= $flag_term;	
				}
			}
		}
		if( !empty($post_id) ){
			$post_ids = explode(',', $post_id);
			$flag &= ( is_single() || is_page() ) && in_array(get_the_ID(), $post_ids);
		}
		if( !empty($post_id__not_in) && ( is_single() || is_page() ) ){
			$post_ids = explode(',', $post_id__not_in);
			$flag &= ! in_array(get_the_ID(), $post_ids);
		}
		if( !empty($disp_type) ){
			$disp_types = explode(',', $disp_type);
			foreach( $disp_types as $val){
				$func = "is_{$val}"; 
				if( function_exists($func) ){$flag &= $func();}
			}
		}
		if( !empty($disp_type__not_in) ){
			$disp_types = explode(',', $disp_type__not_in);
			foreach( $disp_types as $val){
				$func = "is_{$val}"; 
				if( function_exists($func) ){$flag &= ! $func();}
			}
		}
		if( $flag ){
			preg_match_all('/\[([^\]]+)\]/is', $content, $sc);
			$result = !empty($sc[0]) ? do_shortcode( $content ) : $content;
		}
		return $result;
	});
	//閲覧者環境
	add_shortcode('ks_userinfo', function(){
		return @gethostbyaddr($_SERVER['REMOTE_ADDR'])."\n".$_SERVER['HTTP_USER_AGENT']."\n";
	});
	//term一覧取得
	add_shortcode('ks_terms', function($atts){
		extract(shortcode_atts([
			'taxonomy' => 'category',//,区切りで複数
			'hide_empty' => false,	//投稿のないターム非表示の有無
			'parent' => "", 		//親カテゴリで絞り込み term_idを指定 トップtermの場合は0
			'child_of' => "",		//先祖カテゴリで絞り込み term_idを指定
			'post_arc' => false,	//全記事表示用のリンク表示の有無
			'tag' => "ul",
			'tier' => 1,
			'add_class' => ""
		], $atts));
		if( empty($taxonomy)){ return false; }
		$result = "";
		$tags = explode(",", $tag);
		$tags[1] = empty( $tags[1] ) && in_array( $tags[0], ["ul", "ol"] ) ? "li" : "";
		$taxonomies = explode(',', $taxonomy);
		if( !empty($post_arc) ){ //各投稿タイプへのリンク
			$result .= "<{$tags[0]} class=\"post_type {$add_class}\">";
			foreach($taxonomies as $key => $val){
				$post_types = get_taxonomy( $val )->object_type;
				foreach($post_types as $post_type){
					$url = get_post_type_archive_link($post_type);
					$label = get_post_type_object($post_type)->label;
					$result .= !empty($tags[1]) ? "<{$tags[1]}>" : "";
					$result .= "<a href=\"{$url}\" class=\"post_type\"><span>{$label}</span></a>";
					$result .= !empty($tags[1]) ? "</{$tags[1]}>" : "";	
				}
			}
			$result .= "</{$tags[0]}>";
		}
		$args = ['hide_empty' => $hide_empty];
		isset($parent) && ( $args['parent'] = $parent );
		isset($child_of) && ( $args['child_of'] = $child_of );
		$terms = get_terms($taxonomies, $args);
		$responce = [];
		foreach($terms as $val){
			if( empty($val->slug) || $val->slug == 'header' ) continue;
			! isset( $responce[$val->parent] ) && ( $responce[$val->parent] = [] );
			$responce[$val->parent][] = $val;
		}
		$root_term_id = !empty($parent) ? $parent : ( !empty($child_of) ? $child_of : 0 ); 
		$result .= "<{$tags[0]} class=\"parent_term parent_term_{$root_term_id} tier-{$tier} {$add_class}\">";
		$result .= $this->get_term_link__html( $responce, $root_term_id, $tags, $tier);
		$result .= "</{$tags[0]}>";
		return $result;
	});
	//カスタム投稿タイプ一覧
	add_shortcode('ks_post_types', function($atts){
		extract(shortcode_atts(['public' => true, '_builtin' => false, 'tag' => "div", 'add_class' => ""], $atts));
		$post_types = get_post_types(['public' => $public, '_builtin' => $_builtin ]);
		$class = !empty($add_class) ? " class='{$add_class}'" : "";
		$tags = explode(",", $tag);
		$tags[1] = empty( $tags[1] ) && in_array( $tags[0], ["ul", "ol"] ) ? "li" : "";
		$result = !empty($tags[0]) ? "<{$tags[0]}{$class}>" : "";
		if( is_array($post_types)){
			foreach( $post_types as $key => $val ){
				$label = esc_html(get_post_type_object($val)->label);
				$url = esc_html(get_post_type_archive_link($val));
				$result .= !empty($tags[1]) ? "<{$tags[1]} class=\"{$val}\">" : "";
				$result .= "<a href=\"{$url}\" class=\"{$val}\">{$label}</a>";
				$result .= !empty($tags[1]) ? "</{$tag[1]}>" : "";
			}
		}
		$result .= !empty($tags[0]) ? "</{$tags[0]}>" : "";
		return $result;
	});
	//投稿一覧取得
	add_shortcode('ks_arc', function($atts){
		extract(shortcode_atts([
			'post_type' => 'post',//カスタム投稿タイプの名称を入れる
			'posts_per_page' => get_option('posts_per_page'),//表示件数。-1なら全件表示
			'paged' => get_query_var( 'page' ) ?  get_query_var( 'page' ) : ( get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1),
			'new_days' => 7,
			'post__in' => '',
			'post__not_in' => '',
			'category__in' => '',
			'category__not_in' => '',
			'term__in' => '',
			'tag__not_in' => '',
			'orderby' => '',
			'order' => '',
			'template' => '',
			'tag' => 'div',
			'add_class' => '',
			'navi' => 0
		], $atts));
		$attr = [
			'post_type' => $post_type,
			'posts_per_page' => $posts_per_page,
			'paged' => $paged,
			'new_days' => $new_days,
			'post__in' 			=> !empty($post__in) ? explode(',', $post__in) : [],
			'post__not_in' 		=> !empty($post__not_in) ? explode(',', $post__not_in) : [],
			'category__in' 		=> !empty($category__in) ? explode(',', $category__in) : [],
			'category__not_in' 	=> !empty($category__not__in) ? explode(',', $category__not_in) : [],
			'term__in' 			=> !empty($term__in) ? explode(',', $term__in) : [],
			'tag__not_in' 		=> !empty($tag__not__in) ? explode(',', $tag__not_in) : [],
			'orderby' => $orderby,
			'order' => $order,
			'template' => $template,
			'tag' => $tag,
			'add_class' => $add_class,
			'navi' => $navi
		];
		return $this->get_archive( $attr );
	});
	//検索フォーム
	add_shortcode('ks_search_form', function($atts){
		extract(shortcode_atts([
			'post_type' => '', //カスタム投稿タイプの名称を入れる
			'action' => "",
			'placeholder' => ""
		], $atts));
		$s = !empty($_GET['s']) ? $_GET['s'] : "";
		$url = !empty($action) ? ( $action == "/*this*/" ? get_post_type_archive_link($post_type) : $action ) : home_url();
		$post_type = $post_type != "/*this*/" ? $post_type : get_post_type();
		$post_types = explode(',', $post_type);
		$post_type__html = "";
		if( !empty($post_types) ){
			foreach($post_types as $val){
				$post_type__html .= !empty($val) ? "<input type=\"hidden\" name=\"post_type[]\" value=\"{$val}\">" : "";
			}
		}
		return <<<HTML
		<form role="search" method="get" action="{$url}" class="wp-block-search__button-outside wp-block-search__text-button wp-block-search">
			<label class="wp-block-search__label" for="wp-block-search__input-1">検索</label>
			<div class="wp-block-search__inside-wrapper ">
				<input class="wp-block-search__input" id="ks-block-search__input" placeholder="{$placeholder}" value="{$s}" type="search" name="s" required="">
				{$post_type__html}
				<button aria-label="検索" class="wp-block-search__button wp-element-button" type="submit">検索</button></div>
			</form>
	HTML;
	});
  }
  public function get_term_link__html( $terms, $parent, $tags, $tier){
	$result = "";
	if(!empty($terms[$parent])){
		$next_tier = $tier + 1;
		foreach( $terms[$parent] as $val ){
			$url = get_term_link($val);
			$result .= !empty($tags[1]) ? "<{$tags[1]}>" : "";
			$result .= "<a href=\"{$url}\" class=\"term term_{$val->term_id} tier-{$tier}\"><span>{$val->name}</span></a>";
			$result .= !empty($terms[$val->term_id]) ? "<{$tags[0]} class=\"parent_term term_{$val->term_id} tier-{$tier}\">" : "";
			$result .= $this->get_term_link__html( $terms, $val->term_id, $tags, $next_tier);
			$result .= !empty($terms[$val->term_id]) ? "</{$tags[0]}>" : "";
			$result .= !empty($tags[1]) ? "</{$tags[1]}>" : "";
		}
	}
	return $result;
  }
  public function get_archive( $attr = ['post_type' => 'post', 'posts_per_page' => 10, 'paged' => 1, 'new_days' => 7, 'post__in' => [], 'post__not_in' => [], 'category__in' => [], 'category__not_in' => [], 'tag' => 'div' ] ){
	$result = "";
	$new_days = $attr['new_days'];	//NEWアイコン表示条件（日数）
	$today = date_i18n('U');//NEWアイコン比較用
	$paged = $attr['paged'];
	$wp_query = new WP_Query();
	if($attr['post_type'] == "/*this*/"){
		$post_type = get_post_type();
		if( $post_type == "page" ){
			$post_types = get_post_types(['public' => true, '_builtin' => false ]);
			$post_type = array_keys($post_types);
		}
	}else{
		$post_type = $attr['post_type'];
	}
	$args = [
		'post_type' => $post_type,
		'posts_per_page' => $attr['posts_per_page'],
		'paged' => $attr['paged'],
		'post__in' => $attr['post__in'],
		'post__not_in' => $attr['post__not_in'],
		'category__in' => $attr['category__in'],
		'category__not_in' => $attr['category__not_in'],
		'tag__not_in' => $attr['tag__not_in'],
		'orderby' => $attr['orderby'],
		'order' => $attr['order']
	];
	if( !empty($attr['term__in'])){
		$args['tax_query'] = [
			[
				'taxonomy' => "{$post_type}_cat",
				'terms' => $attr['term__in']
			]
		];
	}
	$add_class = !empty($attr['add_class']) ? ' class="'.str_replace(',', ' ', $attr['add_class']).'"' : "";
	$wp_query->query($args);
	if ( $wp_query->have_posts() ){
		if( !empty($attr['template']) ){
			$template = explode(",", $attr['template']);
			$template[1] = $template[1] ? : "";
		}else if( file_exists(get_template_directory().'/template-parts/list.php') ){
			$template[0] = 'template-parts/list';
			$template[1] =  $args['post_type'];
		}
		$tags_list = !empty( $attr['tag'] ) ?  [ "<li>", "</li>" ] : ["", ""];

		ob_start();
		echo !empty( $attr['tag'] ) ? "<{$attr['tag']}{$add_class}>" : "";
		while( $wp_query->have_posts() ){
			$wp_query->the_post();
			if( !empty($template) ){
				get_template_part( $template[0], $template[1], $args);
			}else{
				$post_type = get_post_type();
				$post_id = get_the_ID();
				//タイトル
				$title = get_the_title();
				//投稿日
				$date = get_the_date(get_option('date_format'));
				//タクソノミ
				if(get_categories("taxonomy={$post_type}_cat")){
					$term__html = '<span class="terms">';
					$terms = get_the_terms($post->ID, "{$post_type}_cat");
					foreach( $terms as $key => $val){
						$slug = $val->slug;
						$term_url = get_term_link($val);
						$term__html .= !empty($slug) ? "<span class=\"term {$slug}\">{$slug->name}</span>" : "";
					}
					$term__html .= '</span>';
				}else{
					$term__html = "";
				}
				//タグ
				$tags = get_the_tags();
				if( $tags ){
					$tags__html = '<span class="tags">';
					foreach( $tags as $tag ){
						$tag_name = esc_html( $tag->name );
						$tag_url = get_tag_link( $tag );
						$tags__html .= "<span>{$tag_name}</span>";
					}
					$tags__html .= '</span>';
				}else{
					$tags__html = "";
				}
				//サムネイル
				if( has_post_thumbnail($post_id) ){
					$thumb = wp_get_attachment_image_src( get_post_thumbnail_id($post->ID), 'thumbnail' );
					$thumb__html = "<img src=\"{$thumb[0]}\" width=\"{$thumb[1]}\" height=\"{$thumb[2]}\" alt=\"{$title}\">";
					$image_class = "";
				}else{
					$thumb__html = "";
					$image_class = ' noimage';
				}
				//詳細ページURL
				$url = get_the_permalink($post_id);
				//表示
				echo <<<HTML
    {$tags_list[0]}<a href="{$url}" id="post-{$post_id}" class="flex between{$new}">
      <div class="image{$image_class}">{$thumb__html}</div>
      <div class="text">
        {$term__html}{$tags__html}
        <h3>{$title}</h3>
        <span class="date">{$date}</span>
      </div>
    </a>{$tags_list[1]}
HTML;				
				}
			}
			echo "</{$attr['tag']}>";
			$result = ob_get_contents();
			ob_end_clean();
			if( !empty($attr['navi']) && $attr['navi'] > 1 ){
				$url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
				$url_root = explode("/page/", explode("?", $url)[0])[0];
				$max_disp = $attr['navi'];
				$max_page = $wp_query->max_num_pages;
				$max_start = ( $max_page - $max_disp ) > 0 ?  ( $max_page - $max_disp + 1 ) : 1;
				$start =  $max_start == 1 || ($max_page <= $max_disp ) ? 1 : ( $paged - floor($max_disp / 2) );
				$start = $start > 0 ? ( $start <= $max_start ? $start : $max_start ) : 1;
				$end = $start + $max_disp -1;
				$end = $max_page > $end ? $end : $max_page;
				$query_string = !empty($_SERVER['QUERY_STRING']) ? "?{$_SERVER['QUERY_STRING']}" : "";
				$pages_html = "";
				for( $i = $start; $i <= $end; $i++ ){
					$this_class = $i == $attr['paged'] ? ' class="this"' : '';
					$pages_html .= "<li{$this_class}><a href=\"{$url_root}/page/{$i}/{$query_string}\"><span>{$i}</span></a></li>\n";
				}
				$prev_page = $start > 1 ? '<li class="prev">...</li>' : "";
				$next_page = $end < $max_page ? '<li class="next">...</li>' : "";
				if( $max_page > 1 ){
					$result .=<<<HTML
			<div class="listNavi">
				<ul class="flex">
			{$prev_page}
			{$pages_html}
			{$next_page}
				</ul>
			</div>
HTML;
			}
		}
	} else {
		$result =<<<HTML
		<!-- nodata -->
		<span class="nodata"></span>
HTML;
	}
	wp_reset_postdata();
	return $result;
  }
}
//ショートコード周りの自動整形を解除
add_filter('widget_text', function($content) {
	$content = strtr($content, ['<p></p>' => '', '<p>[' => '[', ']</p>' => ']', ']<br />' => ']']);
	return $content;
});
add_action( 'widgets_init', function() {
  remove_filter( 'widget_text_content', 'wpautop' );	
}, 1 );