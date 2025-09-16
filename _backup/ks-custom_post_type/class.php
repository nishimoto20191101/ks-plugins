<?php
class Ks-CustomPostType {
  const PLUGIN_ID = 'ks-custom_post_type';
  const NAME = "KS CPT";
  private $config_file;
  public  $config = [];
  private $error;
  /*################################################################################
    初期処理
  ################################################################################*/
  static function init(){
    return new self();
  }
  function __construct(){
    //言語ファイル
    #load_plugin_textdomain(self::PLUGIN_ID, false, basename( dirname( __FILE__ ) ).'/languages' );
    //config取得
    $this->config_file = dirname(__FILE__).'/data/config.json';
    $this->config = $this->get_config(); //[ "start_date", "period" ]
		$this->error = new WP_Error();
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
    $modes = [
      [ "key" => "config",  "name" => "自動投稿スケジュール"],
      [ "key" => "import", "name" => "インポート"],
      [ "key" => "export", "name" => "エクスポート"]
    ];
    add_submenu_page(self::PLUGIN_ID, '概要 < '.self::NAME, "概要", 'manage_options', self::PLUGIN_ID, [$this, "print_defalut_page"]);
    foreach( $modes as $key => $val){
      if( $val["key"] == "config" || $val["key"] == "import" || $this->check_AI() ){
        add_submenu_page(self::PLUGIN_ID, $val["name"].' < '.self::NAME, $val["name"], 'manage_options', self::PLUGIN_ID."-{$val["key"]}", [$this, "print_{$val["key"]}_page"]);
      }
    }
  }
  /*################################################################################
    読み込み処理
  ################################################################################*/
  //#### config読み込み ####//
  private function get_config(){
    !file_exists($this->config_file) && file_put_contents($this->config_file, '');
    return json_decode( file_get_contents($this->config_file) );
  }
  /*################################################################################
    書き込み処理
  ################################################################################*/
  //#### config書き込み ####//
  private function set_config($config){
    return file_put_contents($this->config_file , json_encode($config));
  }

	//#### 管理画面からの投稿処理 ####//
  public function ks_post($data){
    $mode = !empty($_POST['mode']) ? $_POST['mode'] : "";
    $redirect_qs = "";
		if ( !empty($mode) && check_admin_referer(self::PLUGIN_ID.'-nonce-action', self::PLUGIN_ID.'-nonce-key') ) {
      try{
        switch($mode){
          case 'config': //投稿スケジュール
            $config_data = is_array($this->config) ? $this->config : [];
            $action = !empty($_POST["action"]) ? $_POST["action"] : "";
            if($action == "save"){
              $character = sanitize_textarea_field($_POST["character"]);
              $this_character = !empty($this->config[$_POST["config_no"]]) ? $this->config[$_POST["config_no"]]->character : "";
              $editor_prompt = !empty($this->config[$_POST["config_no"]]) ? $this->config[$_POST["config_no"]]->editor_prompt : "";
              if( !empty($_POST["editor_prompt"]) && $_POST["editor_prompt"] != $editor_prompt ){
                $editor_prompt = $_POST["editor_prompt"];
              }else{
                $editor_prompt =!empty($character) && $this_character != $character ? $this->mk_editor_prompt($character) : $editor_prompt;
              }
              $config_edit_data = [
                "nikki_title" => !empty($_POST["nikki_title"]) ? $_POST["nikki_title"] : "",
                "category"     => !empty($_POST["category"]) ? $_POST["category"] : "",
                "post_type"   => !empty($_POST["post_type"]) ? $_POST["post_type"] : "post",
                "start_date"  => $_POST["start_date"],
                "period"      => $_POST["period"],
                "post_author" => !empty($_POST["post_author"]) ? $_POST["post_author"] : 1,
                "character"   => $character,
                "editor_prompt" => !empty($character) && $this_character != $character ? $this->mk_editor_prompt($character) : $editor_prompt,
                "text_form"   => !empty($_POST["text_form"]) ? $_POST["text_form"] : self::TYPES[0]
              ];
              if( !empty($config_edit_data)){
                if( isset($_POST["config_no"]) && !empty($config_data) ){
                  $config_data[$_POST["config_no"]] = $config_edit_data;
                }else{
                  array_push($config_data, $config_edit_data);
                }
                $messsage = "自動投稿スケジュールを更新しました。";
              }
            }else if( $action == "del" && isset($_POST["config_no"]) ){
              unset($config_data[$_POST["config_no"]]); //array_splice($config_data, $_POST["config_no"], 1);
              $messsage = "自動投稿スケジュール削除を完了しました。";
            }
            $result = $this->set_config($config_data);
            set_transient(self::PLUGIN_ID.'-updated', $messsage, 10);
            $redirect_qs = isset($_POST["config_no"]) && $action != "del" ? "&no={$_POST["config_no"]}" : "";
            break;
          case'import'://設定ファイルインポート
            $this->uploade_zip_data_fld();
            break;
          default: 

        }
      }catch(Exception $ex) {
        set_transient(self::PLUGIN_ID.'-errors', $ex->getMessage(), 10);
      }
      // 設定画面にリダイレクト
      !empty($_POST['mode']) && wp_safe_redirect(menu_page_url(self::PLUGIN_ID."-{$_POST['mode']}").$redirect_qs, 302);
      return $result;
		}
    add_action('admin_notices', [$this, 'print_message']);
  }
  /*################################################################################
    設定ファイル処理
  ################################################################################*/
  //#### 設定ファイル格納フォルダを圧縮DL ####//
  public function dl_zip_data_fld(){
    $folder = "data";
    $fileName = "ks-custom_post_type".wp_date("YmdHis").".zip";// Zip ファイル名
    $dir = plugin_dir_path( __FILE__ );// ファイルディレクトリ
    $zipPath = "{$dir}/{$fileName}";// Zip ファイルパス
    exec("cd {$dir};"."zip -r '{$fileName}' ./{$folder}/");
    mb_http_output( "pass" ) ;
    header("Content-Type: application/zip");
    header("Content-Transfer-Encoding: Binary");
    header("Content-Length: ".filesize($zipPath));
    header('Content-Disposition: attachment; filename*=UTF-8\'\'' . $fileName);
    ob_end_clean();
    readfile($zipPath);
    unlink($zipPath);// Zipファイル削除
    exit;
  }
  //#### 設定ファイルアップロード ####//
  private function uploade_zip_data_fld(){
    $error_flg = false;
    //エラーチェック
    if(!empty($_FILES['import_file']['size'])){
      $ext = substr($_FILES['import_file']['name'], strrpos($_FILES['import_file']['name'], '.') + 1);
      if($_FILES['import_file']['size'] > 128000000){
        set_transient(self::PLUGIN_ID.'-errors', "ファイルサイズが大きすぎます。", 10);
        $error_flg = true;
      }else if($ext != 'zip'){
        set_transient(self::PLUGIN_ID.'-errors', "ファイル形式が不適切です。", 10);
        $error_flg = true;
      }
    }
    if( ! $error_flg ){
       //ディレクトリの指定
      $directory_path =  plugin_dir_path( __FILE__ );
      if($_FILES['import_file']['size'] > 0 ){
        $filename = time().'.zip';
        $filepath = $directory_path.'/'.$filename;
        move_uploaded_file($_FILES['import_file']['tmp_name'], $filepath);
        if (file_exists($filepath)) {
          $zip = new ZipArchive;
          if ($zip->open($filepath) === TRUE) {
            $zip->extractTo($directory_path);
            $zip->close();
            unlink($filepath);//zipファイルの削除
          }
        }
        set_transient(self::PLUGIN_ID.'-updated', "設定のインポートが完了しました。", 10);
      }else{
        set_transient(self::PLUGIN_ID.'-errors', "設定のインポートに失敗しました。", 10);
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
		if(!empty($message))	{
			echo <<<HTML
			<div id="message" class="{$status} notice is-dismissible">
				<p>{$message}</p>
			</div>
HTML;
    }
	}
  //#### デフォルトページ ####//
  public function print_defalut_page(){
    $selfTitle = esc_html(self::NAME);
    if($this->check_set_config()){
      $link = self::PLUGIN_ID.'-config';
      $message =<<<HTML
      <a href="admin.php?page={$link}">初期設定</a>
HTML;
    }else{
      $message =<<<HTML
      <h3>メニュー</h3>
      <ul class="ks-custom_post_type_menu">
        <li>・<a href="./admin.php?page=ks-custom_post_type-config">カスタム投稿タイプ一覧</a>：カスタム投稿タイプを設定します。</li>
        <li>・<a href="./admin.php?page=ks-custom_post_type-import">インポート</a>：エクスポートメニューでDLした情報を反映します。</li>
        <li>・<a href="./admin.php?page=ks-custom_post_type-export">エクスポート</a>：設定、自動投稿スケジュールの情報を保存します。</li>
      </ul>
<style>
  .ks-custom_post_type_menu a{display:inline-block;width:10em}
</style>
HTML;
    }
    echo <<<HTML
    <div class="wrap">
			<h2>{$selfTitle}</h2>
      <p>カスタム投稿タイプを設定します。</p>
      {$message}
      <hr>
    </div>
HTML;
  }
  //#### 自動投稿スケジュールページ ####//
  public function print_config_page(){
    $selfTitle = esc_html(self::NAME)." 自動投稿スケジュール";
    $config_no = !empty($_GET["no"]) ? $_GET["no"] : 0;
    echo <<<HTML
    <div class="wrap">
			<h2>{$selfTitle}</h2>
      <p>日記タイトルを元に生成AIが投稿タイトルと記事を自動生成し投稿先に定期投稿します。</p>
HTML;
    if( !isset($_GET["no"]) && !empty($this->config) ){
      $this->print_config_list();
    }else{
      $this->print_config_edit($config_no);
    }
  }
  //### リスト表示 - 自動投稿スケジュールページ ####//
  private function print_config_list(){
    $result = "";
    $periods = wp_get_configules();
    $new_no = 0;
    if(!empty($this->config) ){
      $result .=<<<HTML
      <table class="wp-list-table widefat fixed striped table-view-list posts" style="margin-top:1rem">
        <thead>
          <tr>
            <th>スケジュール名</th>
            <th>投稿先</th>
            <th>投稿頻度</th>
            <th>文章形態</th>
            <th style="width:3em"></th>
          </tr>
        </thead>
HTML;
      foreach( $this->config as $key => $val ){
        $post_type = esc_html(get_post_type_object($val->post_type)->label);
        $period = !empty($periods[$val->period]["display"]) ? $periods[$val->period]["display"] : "実行しない";
        $result .=<<<HTML
        <tr>
          <td>{$val->nikki_title}</td>
          <td>{$post_type}</td>
          <td>{$period}</td>
          <td>{$val->text_form}</td>
          <td><a href="./admin.php?page=ks-custom_post_type-config&no={$key}">編集</a></td>
        </tr>
HTML;
        $new_no = $key >= $new_no ? $key+1 : $new_no;
      }
      $result .=<<<HTML
    </table>
HTML;
    }
    $result = '<a href="./admin.php?page=ks-custom_post_type-config&no='.$new_no.'" class="page-title-action">新規スケジュールを追加</a>'.$result;
    echo $result;
  }
  //### 編集表示 - 自動投稿スケジュールページ ####//
  private function print_config_edit($config_no){
    if(is_array($this->config) && !empty($this->config[$config_no]) ){
      $nikki_title = $this->config[$config_no]->nikki_title;
      $category = $this->config[$config_no]->category;
      $post_type = $this->config[$config_no]->post_type;
      $text_form = $this->config[$config_no]->text_form;
      $post_author = $this->config[$config_no]->post_author;
      $character = $this->config[$config_no]->character;
      $start_date = $this->config[$config_no]->start_date;
      $period = $this->config[$config_no]->period;
      $editor_prompt = $this->config[$config_no]->editor_prompt;
      $api_url = plugins_url()."/ks-custom_post_type/api/?key={$this->config->key}&mode=post&no={$config_no}";
      $api_url__html =<<<HTML
      <dt></dt><dd>※安定した自動投稿を希望される場合は、【実行しない】に設定し下にある自動投稿URLをcronなどに設定してください。</dd>
      <dt>自動投稿URL</dt><dd style="padding-top:5px"><span class="copyText">{$api_url}</span> <a href="#" class="exec" style="margin-left:1rem">今すぐ投稿する</a></dd>
HTML;
    }else{
      $nikki_title = "";
      $post_type = "post";
      $category = "";
      $text_form = self::TYPES[0];
      $post_author = 1;
      $character = $this->config->character;
      $start_date = wp_date("Y-m-d H:i:s");
      $period = "";
      $editor_prompt = "";
      $api_url = "";
      $api_url__html = "";
    }
    $post_type__options = $this->get_post_type__options($post_type);//投稿タイプ一覧
    $category__options = $this->get_category__options($category);
    $text_form__options = $this->get_text_form__options($text_form);
    $post_author__options = $this->get_post_author__options($post_author);
    $period__options = $this->get_period__options($period);
    $print_prompt = $this->get_print_prompt($editor_prompt);
    echo <<<HTML
    <div class="wrap">
      <form action="./admin.php?page=ks-custom_post_type" method="post" style="padding-top:1em">
HTML;
    wp_nonce_field(self::PLUGIN_ID.'-nonce-action', self::PLUGIN_ID.'-nonce-key');
    echo <<<HTML
        <input type="hidden" name="mode" value="config">
        <input type="hidden" name="config_no" value="{$config_no}">
        <span style="color:#f00">※</span>必須項目
        <dl>
          <dt>スケジュール名<span style="color:#f00">※</span></dt><dd><input type="text" name="nikki_title" value="{$nikki_title}" style="width:100em;max-width:100%" required></dd>
          <dt>カテゴリ</dt><dd><select name="category">{$category__options}</select></dd>
          <dt>投稿先</dt><dd><select name="post_type">{$post_type__options}</select></dd>
          <dt>投稿頻度</dt><dd><select name="period"><option value="">実行しない</option>{$period__options}</select></dd>
          {$api_url__html}
          <dt>開始日時</dt><dd><input type="datetime-local" name="start_date" value="{$start_date}"></dd>
          <dt></dt><dd>※開始日時を起点に定期投稿します。</dd>
          <dt>投稿者</dt><dd><select name="post_author">{$post_author__options}</select></dd>
          <dt>投稿人格設定<span style="color:#f00">※</span></dt><dd><textarea name="character" rows="8" style="width:100%" required>{$character}</textarea></dd>
          <dt>文章形態</dt><dd><select name="text_form">{$text_form__options}</select></dd>
          {$print_prompt}
        </dl>
        <p class="submit">
          <button type="submit" name="action" class="button-primary" value="save">保存</button>
           <button type="submit" name="action" class="edit-slug button button-small del" value="del">削除</button>
        </p>
			</form>
      <h3>注意点</h3>
        <ul style="margin-bottom:2remx">
          <li>・「開始日時」が保存タイミングより過去の場合、保存処理と同時に投稿されます。</li>
          <li>・サイトにアクセスされる際に投稿処理が行われる仕様となっております。そのためアクセスのない日は投稿されません。</li>
          <li>・Basic認証を設定していると正常に動かない事がございます。</li>
          <li>・安定した自動投稿を希望される場合は、投稿頻度を【実行しない】に設定しcronなどで自動投稿URLを定期的に実行するよう設定してください。</li>
        </ul>
		</div>
<style>
  form dl{display:flex;flex-wrap:wrap;max-width:100%;width:80em}
  form dt{width:9.5em;padding-top:10px}
  form dd{max-width:100%;min-width:200px;width:calc(100% - 9.5em);margin:5px 0}
  input[type="text"]{padding:5px 10px}
  .copyText{display:inline-block}
  .copyText:hover{cursor:copy}
  .copyText::after{content:"コピー";border:1px #888 solid;margin-left:.8em;padding:3px;font-size:.8em}
</style>
<script>
  jQuery(".copyText").on('click',function(){
     var text = jQuery(this).text();
     // クリップボードにコピー
     navigator.clipboard.writeText(text);
     alert("コピー完了\\n" + text);
  });
  jQuery("a.exec").on('click',function(){
    if(window.confirm('今すぐ実行しますか？')){
      jQuery("#wpwrap").css({"cursor":"wait"});
      jQuery.ajax( {
        url:'{$api_url}',
        dataType:'text',
        success:function( data ) {
          alert( data );
          jQuery("#wpwrap").css({"cursor":"auto"});
        },error:function( data ) {
          alert( '実行に失敗しました。' );
        }
      });
    }else{
      window.alert('キャンセルされました');
    }
    return false;
  });  
  jQuery("button.del").on('click',function(){
    if(window.confirm('削除してよろしいですか？')){
      return true;
    }else{
      window.alert('キャンセルされました');
      return false; // 送信を中止
    }
  });
</script>
HTML;
  }
  //#### エクスポートページ ####//
	public function print_export_page(){
    $selfTitle = esc_html(self::NAME)." エクスポート";
    $this_plugins_url = plugins_url()."/".self::PLUGIN_ID;
    $api_url = plugins_url()."/ks-custom_post_type/api/?key={$this->config->key}&mode=zip_data";
     echo <<<HTML
      <div class="wrap">
			<h2>{$selfTitle}</h2>
      <p>「エクスポート」ボタンをクリックすると設定、自動投稿スケジュールの情報をエクスポートします。</p>
			<form action="./admin.php?page=ks-custom_post_type" method="post" enctype="multipart/form-data" class="export">
HTML;
    wp_nonce_field(self::PLUGIN_ID.'-nonce-action', self::PLUGIN_ID.'-nonce-key');
    echo <<<HTML
				<input type="hidden" name="mode" value="export">
        <input type="hidden" name="error" value="1">
        <p class="submit"><input type="submit" id="downloadZip" class="button-primary" value="エクスポート" /></p>
        </form>
		</div>
<script>
jQuery("#downloadZip").on('click', function() {
  location.href="{$api_url}";
  return false;
});
</script>
HTML;
  }
  //#### インポートページ ####//
	public function print_import_page(){
    $selfTitle = esc_html(self::NAME)." インポート";
    $this_plugins_url = plugins_url()."/".self::PLUGIN_ID;
    $admin_url = admin_url();
    echo <<<HTML
      <div class="wrap">
			<h2>{$selfTitle}</h2>
      <p>エクスポートメニューでDLしたファイルをアップロードし「インポートボタン」をクリックしてください。</p>
			<form action="./admin.php?page=ks-custom_post_type" method="post" enctype="multipart/form-data" >
HTML;
    wp_nonce_field(self::PLUGIN_ID.'-nonce-action', self::PLUGIN_ID.'-nonce-key');
    echo <<<HTML
				<input type="hidden" name="mode" value="import">
        <dl>
        <dt>ファイル[zip形式]</dt>
        <dd><input type="file" name="import_file" required></dd>
        </dl>
        <p class="submit"><input type="submit" id="downloadZip" class="button-primary" value="インポート" /></p>
        </form>
		</div>
<style>
  form dl{display:flex;flex-wrap:wrap;max-width:100%;width:100em}
  form dt{width:9.5em;padding-top:10px}
  form dd{max-width:100%;min-width:200px;width:calc(100% - 9.5em);margin:5px 0}
</style>
HTML;
  }
  /*################################################################################
    その他
  ################################################################################*/
  //#### ショートコード ####//
  public function shortcode(){
    add_shortcode('ks_ai_nikki', function($atts){
      extract(shortcode_atts(['key' => '', 'no'  => ''], $atts));
      switch($key){
        case "trends":
          $result = "<ul>";
          $trends = $this->get_trends4google();
          if(is_array($trends)){
              foreach( $trends as $key => $val){
                  $result .= "<li>{$val["title"]}</li>";
              }
          }
          $result .= "</ul>";
          break;
        default:
          $result = "";
      }
      return $result;
    });
  }
}
