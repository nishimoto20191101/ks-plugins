<?php
//GeminiAPI用処理用Class
require_once(dirname(__FILE__).'/vendor/GeminiAPI/GeminiAPI.php');

class Ks_AI_nikki {
  const PLUGIN_ID = 'ks-ai_nikki';
  const NAME = "AI NIKKI";
  const TYPES = ["ニュース", "レポート", "コラム", "日記", "説明", "紹介", "感想", "小説", "12星座占い"];
  //CATEGORYはGoogle Trendsを参考
  const CATEGORY = ["エンターテイメント", "ゲーム", "占い", "ショッピング", "スポーツ", "テクノロジー", "ビジネス、金融", "フード、ドリンク", "ペット、動物", "科学", "気候", "健康", "自動車、乗り物", "趣味、レジャー", "政治", "人材募集、職業訓練", "美容、ファッション", "法律、行政", "旅行、交通機関", "Google Trends"];
  const CHAR = "名前: AI-nikki\n生年月日:不明\n外見:スタイリッシュなデザインのロボット。クールな表情が特徴。\n性格: 冷静沈着で、世の中の動向を的確に分析する。\nキャッチフレーズ:「最新トレンドをいち早くお届けします。」";
  const CRON_HOOK_SCHED = "ks-nikki_sched";
  const SCHED_POST_TITLE = ["自動生成", "スケジュール名"];
  private $config_file;
  private $config = [];
  private $sched_file;
  public  $sched = [];
  public  $AiClient;
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
    $this->config = $this->get_config(); //[ "key", "GeminiApiKey", "post_type", "character" ]
    !empty($this->config) && $this->config->character = !empty($this->config->character) ? $this->config->character : self::CHAR;
    $this->sched_file = dirname(__FILE__).'/data/sched.json';
    $this->sched = $this->get_sched(); //[ "start_date", "period" ]
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
      [ "key" => "post",   "name" => "投稿"],
      [ "key" => "sched",  "name" => "自動投稿スケジュール"],
      [ "key" => "config", "name" => "設定"],
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
    !file_exists($this->config_file) && file_put_contents($this->config_file, ["key"=>"","GeminiApiKey"=>"","character"=>"","editor_prompt"=>""]);
    return json_decode( file_get_contents($this->config_file) );
  }
  public function get_config_key(){
    return !empty($this->config->key) ? $this->config->key : "";
  }
  //#### sched読み込み ####//
  private function get_sched(){
    !file_exists($this->sched_file) && file_put_contents($this->sched_file, '');
    return json_decode( file_get_contents($this->sched_file) );
  }
  /*################################################################################
    書き込み処理
  ################################################################################*/
  //#### config書き込み ####//
  private function set_config($config){
   return file_put_contents($this->config_file , json_encode($config));
  }
  //#### sched書き込み ####//
  private function set_sched($sched){
    return file_put_contents($this->sched_file , json_encode($sched));
  }
	//#### 投稿データインサート ####//
  public function ks_post_insert( $data = [] ){
    $post_data = $this->post_generation($data);
    $result = "";
    if(!empty($post_data["title"])){
      $post_value = [
          "post_type"     => !empty($data["post_type"]) ? $data["post_type"] : "post",
          "post_author"   => !empty($data["post_author"]) ? $data["post_author"] : 1,
          "post_title"    => $post_data["title"],
          "post_content"  => $post_data["content"],
          "post_category" => [],
          "tags_input"    => !empty($data["tags_input"]) ? $data["tags_input"] : [],
          "post_status"   => !empty($data["post_date"]) && empty($data["draft"]) ? "publish" : "draft", //公開済(publish)　予約済(future)　下書き(draft)　承認待ち(pending)　非公開(private)
          "post_date"     => !empty($data["post_date"]) ? $data["post_date"] : wp_date("Y-m-d H:i:s")
      ];
      $result = wp_insert_post($post_value);
    }
    return $result;
  }
	//#### 管理画面からの投稿処理 ####//
  public function ks_post($data){
    $mode = !empty($_POST['mode']) ? $_POST['mode'] : "";
    $redirect_qs = "";
		if ( !empty($mode) && check_admin_referer(self::PLUGIN_ID.'-nonce-action', self::PLUGIN_ID.'-nonce-key') ) {
      try{
        switch($mode){
          case 'config': //設定情報
            $before_GeminiApiKey = $this->check_AI() ? $this->config->GeminiApiKey : "";  
            $this->config->GeminiApiKey = $_POST["GeminiApiKey"];
            $this->set_AI_Client();
            try{
              $error_chk = $before_GeminiApiKey != $this->config->GeminiApiKey ? $this->AiClient->chk_key() : "";
              //$error_chk = $before_GeminiApiKey != $this->config->GeminiApiKey ? $this->AiClient->get_anser("こんにちは") : "";
              $character = sanitize_textarea_field($_POST["character"]);
              $this_character = !empty($this->config) ? $this->config->character : "";
              if( !empty($character) && $this_character != $character ){
                $editor_prompt = $this->mk_editor_prompt($character);
              }else if( !empty($_POST["editor_prompt"]) ){
                $editor_prompt = $_POST["editor_prompt"];
              }else{
                $editor_prompt = !empty($this->config) ? $this->config->editor_prompt : "";
              }
              $result = $this->set_config([
                "key" => $_POST["key"],
                "GeminiApiKey" => $_POST["GeminiApiKey"],
                "character" => $character,
                "editor_prompt" => $editor_prompt
              ]);
              set_transient(self::PLUGIN_ID.'-updated', "設定を更新しました。", 10);
            }catch(Exception $e){
              set_transient(self::PLUGIN_ID.'-errors', "Gemini API API キーが無効です。"/*"予期しないエラーが発生しました: {$code} ".$e->getMessage()*/, 10);
            }
            break;
          case 'sched': //投稿スケジュール
            $sched_data = is_array($this->sched) ? $this->sched : [];
            $action = !empty($_POST["action"]) ? $_POST["action"] : "";
            if($action == "save"){
              $character = sanitize_textarea_field($_POST["character"]);
              $this_character = !empty($this->sched[$_POST["sched_no"]]) ? $this->sched[$_POST["sched_no"]]->character : "";
              if( !empty($character) && $this_character != $character ){
                $editor_prompt = $this->mk_editor_prompt($character);
              }else if( !empty($_POST["editor_prompt"]) ){
                $editor_prompt = $_POST["editor_prompt"];
              }else{
                $editor_prompt  = !empty($this->sched[$_POST["sched_no"]]) ? $this->sched[$_POST["sched_no"]]->editor_prompt : "";
              }
              $sched_edit_data = [
                "nikki_title" => !empty($_POST["nikki_title"]) ? $_POST["nikki_title"] : "",
                "category"    => !empty($_POST["category"]) ? $_POST["category"] : "",
                "post_type"   => !empty($_POST["post_type"]) ? $_POST["post_type"] : "post",
                "post_title"  => !empty($_POST["post_title"]) ? $_POST["post_title"] : 0,
                "draft"       => !empty($_POST["draft"]) ? $_POST["draft"] : "",
                "start_date"  => $_POST["start_date"],
                "period"      => $_POST["period"],
                "post_author" => !empty($_POST["post_author"]) ? $_POST["post_author"] : 1,
                "character"   => $character,
                "editor_prompt" => $editor_prompt,
                "text_form"   => !empty($_POST["text_form"]) ? $_POST["text_form"] : self::TYPES[0]
              ];
              if( !empty($sched_edit_data)){
                if( isset($_POST["sched_no"]) && !empty($sched_data) ){
                  $sched_data[$_POST["sched_no"]] = $sched_edit_data;
                }else{
                  array_push($sched_data, $sched_edit_data);
                }
                $messsage = "自動投稿スケジュールを更新しました。";
              }
            }else if( $action == "del" && isset($_POST["sched_no"]) ){
              unset($sched_data[$_POST["sched_no"]]); //array_splice($sched_data, $_POST["sched_no"], 1);
              $messsage = "自動投稿スケジュール削除を完了しました。";
            }
            //既存の編集前条件のcron削除
            if(isset($_POST["sched_no"])){
              $corns = _get_cron_array();
              foreach( $corns as $cron_key => $cron_val ){
                foreach( $cron_val as $sche_name => $sche_val ){
                    if( $sche_name == self::CRON_HOOK_SCHED ){
                      $sche_key = key( $sche_val );
                      if( $sche_val[$sche_key]['args'][0] == $_POST["sched_no"] ){
                        wp_unschedule_event( $cron_key, $sche_name, $sche_val[$sche_key]['args']);
                      }
                    }
                }
              }
            }
            $result = $this->set_sched($sched_data);
            set_transient(self::PLUGIN_ID.'-updated', $messsage, 10);
            $redirect_qs = isset($_POST["sched_no"]) && $action != "del" ? "&no={$_POST["sched_no"]}" : "";
            break;
          case'import'://設定ファイルインポート
            $this->uploade_zip_data_fld();
            break;
          default: //投稿
            if( $mode == 'post' && !empty($_POST["title"])){
              $text_form = !empty($_POST["text_form"]) ? $_POST["text_form"] : self::TYPES[0];
              $character = sanitize_textarea_field($_POST["character"]);
              $result = $this->ks_post_insert([
                "post_type" => !empty($_POST['post_type']) ? $_POST['post_type'] : "post",
                "title" => $_POST["title"],
                "post_date" => $_POST["post_date"],
                "post_author" => $_POST["post_author"],
                "character" => $character,
                "editor_prompt" => !empty($character) && $character != $this->config->character ? $this->mk_editor_prompt($character) : $this->config->editor_prompt,
                "tags_input" => [],
                "text_form" => $text_form
              ]);
              if( $result ){
                set_transient(self::PLUGIN_ID.'-updated', "『{$_POST["title"]}』の記事を作成し投稿しました。", 10);
              }else{
                set_transient(self::PLUGIN_ID.'-errors', "記事の生成に失敗しましたタイトルを調整してください。", 10);
              }
            }else if( is_array($data) && !empty($data) ){//複数登録する場合
              /*$data =[
                ["post_type"=>"post", "title"=>"", "post_author" => 1, "post_date" => wp_date("Y-m-d H:i:s"), "text_form"=>"日記"],
                ["post_type"=>"post", "title"=>"", "post_author" => 1, "post_date" => wp_date("Y-m-d H:i:s"), "text_form"=>"日記"]
              ];*/
              $result = array_map( fn($val) => $this->ks_post_insert($val), $data);
            }
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
    書き込みスケジュール
  ################################################################################*/
  //#### wp_cron登録 ####//
  public function ks_post_sched(){
    if( $this->check_set_config() || !is_array($this->sched)){
      return false;
    }
    if(is_array($this->sched)){
      add_action( self::CRON_HOOK_SCHED, [$this, 'ks_post_sched_exec']);
      foreach($this->sched as $key => $val){
        if(!empty($val->period) && !empty( $val->start_date )){
          // cron登録処理
          if ( !wp_next_scheduled( self::CRON_HOOK_SCHED, [$key]  ) ) {  // 何度も同じcronが登録されないように
            date_default_timezone_set('Asia/Tokyo');  // タイムゾーンの設定
            wp_schedule_event( strtotime( $val->start_date ), $val->period, self::CRON_HOOK_SCHED, [$key] );
          }
        }
       }
     }
  }
  //#### スケジュールデータ生成し投稿 ####//
  public function ks_post_sched_exec($i=0){
    $result = "";
    $sched = $this->sched[$i];
    if(!empty($sched)){
      $this->set_AI_Client();
      $data = [
        "nikki_title" => $sched->nikki_title,
        "category" => $sched->category,
        "post_category" => [],
        "post_type" => $sched->post_type,
        "draft" => !empty($sched->draft) ? $sched->draft : "",
        "post_author" => $sched->post_author, 
        "character" => $sched->character,
        "editor_prompt" => $sched->editor_prompt,
        "tags_input"=>[$sched->category], 
        "text_form"=>$sched->text_form, 
        "post_date" => wp_date("Y-m-d H:i:s")
      ];
      $title = $sched->post_title == 1 ? $sched->nikki_title : $this->mk_title($data);
      $title = preg_replace('/\*\*/u', '', $title);
      $title = preg_replace('/\[今日\]/u', wp_date(get_option('date_format')), $title);
      //$title = preg_replace('/(\*\*)|.+タイトル：||[.+タイトル]|： 記事/u', '', $title);

      $data["title"] = $title;
      $result = $this->ks_post_insert( $data );
    }
    return $result;
  }
  /*################################################################################
    設定ファイル処理
  ################################################################################*/
  //#### 設定ファイルが設定されているかチェック ####//
  public function check_set_config(){
    return empty($this->config->GeminiApiKey);
  }
  //#### 設定ファイル格納フォルダを圧縮DL ####//
  public function dl_zip_data_fld(){
    $folder = "data";
    $fileName = "ks-ai_nikki".wp_date("YmdHis").".zip";// Zip ファイル名
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
    生成AIサービス処理
    ※現状Geminiのみ、対応サービスが増えたときに追記
  ################################################################################*/
  //#### 生成AIサービスが利用できるかチェック ####//
  public function check_AI(){
    return !empty($this->config->GeminiApiKey);
  }
  //#### AI用オブジェクト生成 ####//
  public function set_AI_Client(){
    empty($this->AiClient) && ( $this->AiClient = new ks_GeminiAPI($this->config->GeminiApiKey) );
  }
  //#### タイトル生成 ####//
  public function mk_title($data){
    if(!empty($data["title"])){
      $result = $data["title"];
    }else if( !empty($data["category"]) && $data["category"] == "Google Trends" ){
      $result = $this->get_trends4google()[0]["title"];
    }else{
      //$character = !empty($data["character"]) ? $data["character"] : $this->config->character;
      $data["nikki_title"] = !empty($data["nikki_title"]) ? $data["nikki_title"] : get_bloginfo( 'name' );
      $data["editor_prompt"] = !empty($data["editor_prompt"]) ? $data["editor_prompt"] : $this->config->editor_prompt;
      $data["text_form"] = !empty($data["text_form"]) ? $data["text_form"] : self::TYPES[0];
      $prompt = $this->AiClient->mk_prompt('title', $data);
      $result = $this->AiClient->get_anser($prompt);
    }
     return $result;
  }
  //#### 画像 ####//
  public function mk_image($text){
    //return !empty($text) ? $this->AiClient->get_image($text) : 'error';
  }
	//#### 投稿内容生成 ####//
  //人格プロンプトに関する論文  https://arxiv.org/abs/2410.05603
  //Gemini プロンプト設計戦略  https://ai.google.dev/gemini-api/docs/prompting-strategies?hl=ja
  public function post_generation( $data = [] ){
    require_once(dirname(__FILE__)."/vendor/phpQuery-onefile.php");
    $url  = !empty($data['url']) ? $data['url'] : "";
    $AI_flag = false;
    if( empty($url) ){
      $AI_flag = true;
    }else{
       if( $data["text_form"] == "まとめ" ){
        $site = preg_match("/^https:\/\//", $data["url"]) ? file_get_contents($data["url"]) : "";
        if(empty($site)){
          throw new Exception("URLの値が正しくありません。{$data["url"]}");
        }else{
          //FQDN判定で処理を切り分け
          if(strpos($url, "https://lavender.5ch.net/") !== false){//5ch
            $result = post_generation_5ch($data["url"]);
            $AI_flag = false;
          }else{
            $data["text_form"] == "要約";
            $AI_flag = true;
          }
        }
      }else{
        $AI_flag = true;
      }
    }
    if($AI_flag && $this->check_AI()){
      $data["text_form"] = !empty($data["text_form"]) ? $data["text_form"] : self::TYPES[0];
      $data["editor_prompt"] = !empty($data["editor_prompt"]) ? $data["editor_prompt"] : $this->config->editor_prompt;
      $result = ["title" => $this->mk_title($data)];
      if(in_array($data["text_form"], ["ニュース", "レポート"]) ){
        $data["reference"] = $this->get_news4google($result["title"], 5);
      }
      $this->set_AI_Client();
      $prompt = $this->AiClient->mk_prompt('post', $data);
      $result["content"] = $this->AiClient->get_anser($prompt);
    }
    return $result;
  }
  //#### 運営AIプロンプト（人格プロトコル）作成 ####//
  public function mk_editor_prompt($exp = ""){
    if(empty($exp)){
      return false;
    }else{
      $this->set_AI_Client();
      $prompt = $this->AiClient->mk_prompt('editor', ["exp" => $exp ]);
      $result = $this->AiClient->get_anser($prompt);
    }
     return $result;
  }
  /*################################################################################
    外部サイトの内容を整形
  ################################################################################*/
	//#### 投稿内容生成（5ch） ####//
  private function post_generation_5ch($site){
    $result = [];
    $content = phpQuery::newDocument($site);
    $title = $content->find("#threadtitle:eq(0)")->text();
    $posts = $content->find(".post-content");
    $html = [];
    for($i=0; $i<count($posts); $i++){
      $post_cmt  = mb_convert_encoding($content->find(".post-content:eq($i)")->html(), "utf-8", "utf-8, sjis-win");
      $len = mb_strlen($post_cmt, "UTF-8");
      $str_w = mb_strwidth($post_cmt, "UTF-8");
      $multibyte_rate = ($str_w - $len) / $len;//全角文字の割合
      // 削除対象該当するキーワード（例: 暴言）
      $trollKeywords = [
        '前スレ', 'バカ', 'クズ', 'ゴミ', 'キチガイ', '消えろ', '乞食', '死ぬ', '死ね','しね',  '殺す', '屑', 'まんこ', 'マンコ', '売春', '買春',
        'ハゲ', 'ちんこ', 'チンコ', '糞尿', 'クソ', 'ウンチ', 'うんち', 'スカトロ', 'オナニ', 'ゴキブリ', 'インフォガ', 'いんふぉが', 'こんな所'
      ];
      if( strlen($post_cmt) > 20 && $multibyte_rate >= 0.8 && !preg_match('/('.implode('|',$trollKeywords).')/', $post_cmt) ){
        $post_id   = $content->find(".post-header:eq($i) .postid")->text();
        $post_name = $content->find(".post-header:eq($i) .postusername")->text();
        $post_date = $content->find(".post-header:eq($i) .date")->text();
        $html[] = "<h4>{$post_id} {$post_name} <span class=\"date\">{$post_date}</span></h4><div>{$post_cmt}</div>";
      }
    }
    $result["title"]   = $title;
    $result["content"] = implode("", $html);
    return $result;
  }
  //#### googleの検索結果を取得 ####//
  public function get_news4google($keywords, $max_num){
    set_time_limit(90);
    $api_url = "https://news.google.com/atom/search?hl=ja&ie=UTF-8&oe=UTF-8&q=".urlencode(mb_convert_encoding($keywords,"UTF-8", "auto"))."&gl=JP&ceid=JP:ja";//&tbs=qdr:w
    $contents = file_get_contents($api_url);
    $xml = simplexml_load_string($contents);
    $data = $xml->entry; //記事エントリを取り出す
    $result = [];
    //記事のタイトルとURLを取り出して配列に格納
    for ($i = 0; $i < count($data); $i++) {
      if( $i >= $max_num){ break; }
      $url_split =  explode("=", (string)$data[$i]->link->attributes()->href);
      $result[] = [
        'title' => mb_convert_encoding($data[$i]->title ,"UTF-8", "auto"),
        'url'   => $url_split[0]
      ];
    }
    return $result;
  }
  //#### Google Trendsを取得 https://trends.google.co.jp/trends/?geo=JP&hl=ja ####//
  public function get_trends4google(){
    set_time_limit(90);
    $contents = file_get_contents("https://trends.google.co.jp/trending/rss?geo=JP");
    $xml = simplexml_load_string($contents);
    $data = $xml->channel->item;
    //記事のタイトルとURLを取り出して配列に格納
    for( $i=0; $i<count($data); $i++ ) {
      $result[] = [
        'title' => mb_convert_encoding($data[$i]->title ,"UTF-8", "auto"),
      ];
    }  
    return $result;
  }
  /*################################################################################
    HTML生成
  ################################################################################*/
	//#### post_typeの一覧をselectタグのoptionに整形 ####//
  public function get_post_type__options($select_val=""){
    $post_types = get_post_types(['public' => true,'_builtin' => false]);
    $post_types = array_merge(['post', 'page'], array_values($post_types));
    $result = "";
    if( is_array($post_types)){
      foreach( $post_types as $key => $val ){
        $selected = $val == $select_val ? " selected" : "";
        $label = esc_html(get_post_type_object($val)->label);
        $result .= "<option value=\"{$val}\"{$selected}>{$label}</option>";
      }
    }
    return $result;
  }
  //#### post_typeの一覧をselectタグのoptionに整形 ####//
  public function get_category__options($select_val=""){
    $result = "<option value=\"\">----</option>";
    if( is_array(self::CATEGORY)){
      foreach(self::CATEGORY as $key => $val){
        $selected = $val == $select_val ? " selected" : "";
        $result .= "<option{$selected}>{$val}</option>";
      }
    }
    return $result;
  }
	//#### 文章形態の一覧をselectタグのoptionに整形 ####//
  public function get_text_form__options($select_val=""){
    $result = "";
    if( is_array(self::TYPES)){
      foreach(self::TYPES as $key => $val){
        $selected = $val == $select_val ? " selected" : "";
        $result .= "<option{$selected}>{$val}</option>";
      }
    }
    return $result;
  }
	//#### 投稿者の一覧をselectタグのoptionに整形 ####//
  public function get_post_author__options($select_val=""){
    $result = "";
    $post_authors = get_users( [ "role__in" => ["administrator", "editor"], "orderby"=>"ID", "order"=>"ASC"] );
    if( is_array($post_authors)){
      foreach($post_authors as $post_author){
        $selected = $post_author->ID == $select_val ? " selected" : "";
        $result .= "<option{$selected} value=\"{$post_author->ID}\">{$post_author->display_name}</option>";
      }
    }
    return $result;
  }
	//#### wp_cronの周期一覧をselectタグのoptionに整形 ####//
  public function get_period__options($select_val=""){
    $result = "";
    $periods = wp_get_schedules();
    if( is_array($periods) ){
      foreach( $periods as $key => $val ){
        if( ! str_contains($key, "wp") ){
          $selected = $key == $select_val ? " selected" : "";
          $result .= "<option value=\"{$key}\"$selected>{$val["display"]}</option>";
        }
      }
    }
    return $result;
  }
  /*################################################################################
    表示
  ################################################################################*/
  //#### 投稿、固定ページAIで本文を生成する機能追加 ####//
  public function print_managementScreen_post(){
    $api_url = plugins_url()."/ks-ai_nikki/api/?key={$this->config->key}&mode=anser";
    $text_form__options = $this->get_text_form__options();
    echo <<<HTML
      <script>
        jQuery("#postdivrich").append('<div style="display:flex;align-items:center;margin-top:5px"><span>文章形態：</span><select name="text_form" id="text_form">{$text_form__options}</select><input type="button" value="AIで生成する" id="mk_ai_wp-editor-area" style="padding:3px 1em"></div><span>※タイトルが生成元ワードになります。</span>');
        jQuery("#mk_ai_wp-editor-area").on("click", function(){
          var qs = '&message='+jQuery("input[name='post_title']#title").val()+'&text_form='+jQuery("select#text_form option:selected").val();
          var pressed = jQuery("#content-tmce").attr('aria-pressed');
          jQuery("#postdivrich *").css({"cursor":"wait"});
          jQuery("#content_ifr html").css({"cursor":"wait"});
          if(title){
            jQuery.ajax( {
              url:'{$api_url}'+qs,
              dataType:'text',
              success:function( data ) {
                 jQuery('#content-html').click();
                jQuery("textarea.wp-editor-area").val(data);
                (pressed == "true") && jQuery('#content-tmce').click();
                jQuery("#postdivrich *").css({"pointer-events":"auto", "cursor":"auto"});
                jQuery("#content_ifr html").css({"cursor":"auto"});
                //alert( '読み込み成功' );
              },error:function( data ) {
                 //alert( '読み込み失敗' );
              }
            });
          }else{
            alert("タイトルを入力してください。");
          }
        });
    </script>
HTML;
  }
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
    if($this->check_set_config()){
      $link = self::PLUGIN_ID.'-config';
      $message =<<<HTML
      <a href="admin.php?page={$link}">初期設定</a>
HTML;
    }else{
      $message =<<<HTML
      <h3>メニュー</h3>
      <ul class="ks-ai_nikki_menu">
        <li>・<a href="./admin.php?page=ks-ai_nikki-post">投稿</a>：生成AIが文章を自動生成し任意の投稿先に投稿します。</li>
        <li>・<a href="./admin.php?page=ks-ai_nikki-sched">自動投稿スケジュール</a>：投稿タイトルと記事を自動生成し定期投稿します。</li>
        <li>・<a href="./admin.php?page=ks-ai_nikki-config">設定</a>：初期設定です。</li>
        <li>・<a href="./admin.php?page=ks-ai_nikki-import">インポート</a>：エクスポートメニューでDLした情報を反映します。</li>
        <li>・<a href="./admin.php?page=ks-ai_nikki-export">エクスポート</a>：設定、自動投稿スケジュールの情報を保存します。</li>
      </ul>
<style>
  .ks-ai_nikki_menu a{display:inline-block;width:10em}
</style>
HTML;
    }
    echo <<<HTML
    <div class="wrap">
			<h2>{$selfTitle}</h2>
      <p>生成AIサービスのAPIを使い日記や記事などを自動作成、投稿します。</p>
      {$message}
      <hr>
      <h3>対応言語モデル</h3>
      <ul style="margin-top:-5px">
        <li>・Google AI Gemini (<a href="https://gemini.google.com/?hl=ja" target="_blank" rel="noopener">https://gemini.google.com/?hl=ja</a>)</li>
      </ul>
    </div>
HTML;
  }
  //#### 設定ページ ####//
	public function print_config_page(){
    $selfTitle = esc_html(self::NAME)." 設定";
    $print_prompt = !empty($this->config->editor_prompt) ? $this->get_print_prompt($this->config->editor_prompt) : "";
    echo <<<HTML
      <div class="wrap">
			<h2>{$selfTitle}</h2>
			<form action="./admin.php?page=ks-ai_nikki" method="post" >
HTML;
    wp_nonce_field(self::PLUGIN_ID.'-nonce-action', self::PLUGIN_ID.'-nonce-key');
    $plugin_key = !empty($this->config->key) ? $this->config->key : uniqid();
    $GeminiApiKey = $this->check_AI() ? $this->config->GeminiApiKey : "";
    $character = !empty($this->config->character) ? $this->config->character : self::CHAR;
    echo <<<HTML
				<input type="hidden" name="mode" value="config">
        <input type="hidden" name="key" value="{$plugin_key}">
        <dl>
          <dt>Gemini API key</dt><dd><input type="text" name="GeminiApiKey" placeholder="半角文字でご入力ください" value="{$GeminiApiKey}" style="max-width:100%;width:23em" maxlength="40" pattern="^[a-zA-Z0-9_-]+$"></dd>
          <dt></dt><dd>※参考サイト：<a href="https://ai.google.dev/gemini-api/docs/api-key?hl=ja" target="_blank" ref="noopener" style="display:inline-block">https://ai.google.dev/gemini-api/docs/api-key?hl=ja</a></dd>
          <dt>投稿人格<span>設定</dt><dd><textarea name="character" rows="8" style="width:100%">{$character}</textarea></dd>
          <dt></dt><dd><div style="text-indent:-1em;padding-left:1em">※投稿ページにある「AIで生成する」ボタンをクリックした際に記事を生成するAIの人格設定です。</div><div style="text-indent:-1em;padding-left:1em">※以下の内容を入力してください。<br>・名前、生年月日、職業などの基本情報<br>・性格、価値観などの内面要素<br>・文体や語彙選びなどの言語的特徴</div>※実在する人物のプライバシー情報を入力しないようにしてください。</dd>
          {$print_prompt}
        </dl>
        <p class="submit"><input type="submit" class="button-primary" value="保存" /></p>
			</form>
		</div>
<style>
  form dl{display:flex;flex-wrap:wrap;max-width:100%;width:80em}
  form dt{width:9.5em;padding-top:10px;margin:7px 0}
  form dd{max-width:100%;min-width:200px;width:calc(100% - 9.5em);margin:12px 0}
  input[type="text"]{padding:5px 10px}
</style>
HTML;
  }
  private function get_print_prompt($val){
    if(empty($val)){ return ""; }
    return <<<HTML
  <input type="checkbox" name="disp_prompt" value="" id="disp_prompt" style="display:none">
  <dt><label for="disp_prompt"><span>高度な設定</span></label></dt>
  <dd>
    <h3 style="margin:.2rem 0 .5rem">Gemini AI投稿用プロンプト</h3>
    <span>投稿人格設定を更新すると優先的に自動生成された内容に更新されます。</span>
    <textarea name="editor_prompt" rows="20" style="width:100%">{$val}</textarea>
  </dd>
<style>
  #disp_prompt+dt label{display:flex;align-items:center;width:fit-content;cursor:pointer}
  #disp_prompt+dt label::after{content:"▼";margin-left:2rem;-webkit-transition:all .3s;transition:all .3s}
  #disp_prompt+dt+dd *{display:none}
  #disp_prompt+dt+dd textarea{white-space: pre-wrap;width:90%;padding:1rem;background-color:#000;color:#fff}
  #disp_prompt:checked+dt label::after{transform:rotate(180deg)}
  #disp_prompt:checked+dt+dd *{display:block}
</style>
HTML;
  }
  //#### 投稿ページ ####//
	public function print_post_page(){
    if($this->check_set_config()){
      return $this->print_not_set();
    }
    $selfTitle = esc_html(self::NAME)." 投稿";
    $post_type__options = $this->get_post_type__options("post");//投稿タイプ一覧
    $text_form__options = $this->get_text_form__options();
    $post_author__options = $this->get_post_author__options();
    $character = !empty($this->config->character) ? $this->config->character : "";
    $now = wp_date("Y-m-d H:i:s");
    echo <<<HTML
      <div class="wrap">
			<h2>{$selfTitle}</h2>
      <p>タイトルを元に生成AIが文章を自動生成し投稿先に投稿します。</p>
			<form action="./admin.php?page=ks-ai_nikki" method="post" style="padding-top:1em">
HTML;
    wp_nonce_field(self::PLUGIN_ID.'-nonce-action', self::PLUGIN_ID.'-nonce-key');
    echo <<<HTML
				<input type="hidden" name="mode" value="post">
        <span style="color:#f00">※</span>必須項目
        <dl>
          <dt>投稿先</dt><dd><select name="post_type">{$post_type__options}</select></dd>
          <dt>投稿タイトル<span style="color:#f00">※</span></dt><dd><input type="text" name="title" value="" style="width:100%" required></dd>
          <dt>公開日時</dt><dd><input type="datetime-local" name="post_date" value="{$now}"></dd>
          <dt style="margin-top:-5px"></dt><dd style="margin-top:-5px">※公開日を空白にすると「下書き」で投稿されます。</dd>
          <dt>投稿者</dt><dd><select name="post_author">{$post_author__options}</select></dd>
          <dt>投稿人格設定<span style="color:#f00">※</span></dt><dd><textarea name="character" rows="8" style="width:100%" required>{$character}</textarea></dd>
          <dt style="margin-top:-5px"></dt><dd style="margin-top:-5px">※設定で登録されている「投稿人格設定」が初期値として入力されています。</dd>
          <dt>文章形態</dt><dd><select name="text_form">{$text_form__options}</select></dd>
        </dl>
				<p class="submit"><input type="submit" class="button-primary" value="投稿" /></p>
			</form>
		</div>
<style>
  form dl{display:flex;flex-wrap:wrap;max-width:100%;width:100em}
  form dt{width:9.5em;padding-top:10px;margin:7px 0}
  form dd{max-width:100%;min-width:200px;width:calc(100% - 9.5em);margin:12px 0}
  input[type="text"]{padding:5px 10px}
</style>
<script>
jQuery("#wpbody-content .button-primary").on("click", function(){
  jQuery("body").css({"cursor":"wait"})
});
</script>
HTML;
  }
  //#### 自動投稿スケジュールページ ####//
  public function print_sched_page(){
    if($this->check_set_config()){
      return $this->print_not_set();
    }
    $selfTitle = esc_html(self::NAME)." 自動投稿スケジュール";
    $sched_no = !empty($_GET["no"]) ? $_GET["no"] : 0;
    echo <<<HTML
    <div class="wrap">
			<h2>{$selfTitle}</h2>
      <p>日記タイトルを元に生成AIが投稿タイトルと記事を自動生成し投稿先に定期投稿します。</p>
HTML;
    if( !isset($_GET["no"]) && !empty($this->sched) ){
      $this->print_sched_list();
    }else{
      $this->print_sched_edit($sched_no);
    }
  }
  //### リスト表示 - 自動投稿スケジュールページ ####//
  private function print_sched_list(){
    $result = "";
    $periods = wp_get_schedules();
    $new_no = 0;
    if(!empty($this->sched) ){
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
      foreach( $this->sched as $key => $val ){
        $post_type = esc_html(get_post_type_object($val->post_type)->label);
        $period = !empty($periods[$val->period]["display"]) ? $periods[$val->period]["display"] : "実行しない";
        $result .=<<<HTML
        <tr>
          <td>{$val->nikki_title}</td>
          <td>{$post_type}</td>
          <td>{$period}</td>
          <td>{$val->text_form}</td>
          <td><a href="./admin.php?page=ks-ai_nikki-sched&no={$key}">編集</a></td>
        </tr>
HTML;
        $new_no = $key >= $new_no ? $key+1 : $new_no;
      }
      $result .=<<<HTML
    </table>
HTML;
    }
    $result = '<a href="./admin.php?page=ks-ai_nikki-sched&no='.$new_no.'" class="page-title-action">新規スケジュールを追加</a>'.$result;
    echo $result;
  }
  //### 編集表示 - 自動投稿スケジュールページ ####//
  private function print_sched_edit($sched_no){
    if(is_array($this->sched) && !empty($this->sched[$sched_no]) ){
      $nikki_title = $this->sched[$sched_no]->nikki_title;
      $category = $this->sched[$sched_no]->category;
      $post_title =  $this->sched[$sched_no]->post_title;
      $post_type = $this->sched[$sched_no]->post_type;
      $draft = !empty($this->sched[$sched_no]->draft) ? $this->sched[$sched_no]->draft : "";
      $text_form = $this->sched[$sched_no]->text_form;
      $post_author = $this->sched[$sched_no]->post_author;
      $character = $this->sched[$sched_no]->character;
      $start_date = $this->sched[$sched_no]->start_date;
      $period = $this->sched[$sched_no]->period;
      $editor_prompt = $this->sched[$sched_no]->editor_prompt;
      $api_url = plugins_url()."/ks-ai_nikki/api/?key={$this->config->key}&mode=post&no={$sched_no}";
      $api_url__html =<<<HTML
      <dt style="margin-top:-5px"></dt><dd style="margin-top:-5px">※安定した自動投稿を希望される場合は、【実行しない】に設定し下にある自動投稿URLをcronなどに設定してください。</dd>
      <dt>自動投稿URL</dt><dd style="padding-top:5px"><span class="copyText">{$api_url}</span> <a href="#" class="exec" style="margin-left:1rem">今すぐ投稿する</a></dd>
HTML;
    }else{
      $nikki_title = "";
      $post_type = "post";
      $category = "";
      $post_title = 0;
      $text_form = self::TYPES[0];
      $post_author = 1;
      $character = $this->config->character;
      $start_date = wp_date("Y-m-d H:i:s");
      $period = "";
      $editor_prompt = "";
      $api_url = "";
      $api_url__html = "";
    }
    $post_title_radio = "";
    foreach(self::SCHED_POST_TITLE as $key => $val){
      $checked = $post_title == $key ? " checked" : "";
      $post_title_radio .=<<<HTML
        <label><input type="radio" name="post_title" value="{$key}"{$checked}> {$val}</label>
HTML;
    }
    $draft_chk = !empty($draft) ? " checked" : "";
    $post_type__options = $this->get_post_type__options($post_type);//投稿タイプ一覧
    $category__options = $this->get_category__options($category);
    $text_form__options = $this->get_text_form__options($text_form);
    $post_author__options = $this->get_post_author__options($post_author);
    $period__options = $this->get_period__options($period);
    $print_prompt = $this->get_print_prompt($editor_prompt);
    echo <<<HTML
    <div class="wrap">
      <form action="./admin.php?page=ks-ai_nikki" method="post" style="padding-top:1em">
HTML;
    wp_nonce_field(self::PLUGIN_ID.'-nonce-action', self::PLUGIN_ID.'-nonce-key');
    echo <<<HTML
        <input type="hidden" name="mode" value="sched">
        <input type="hidden" name="sched_no" value="{$sched_no}">
        <span style="color:#f00">※</span>必須項目
        <dl>
          <dt>スケジュール名<span style="color:#f00">※</span></dt><dd><input type="text" name="nikki_title" value="{$nikki_title}" style="width:100em;max-width:100%" required></dd>
          <dt>カテゴリ</dt><dd><select name="category">{$category__options}</select></dd>
          <dt>投稿先</dt><dd><select name="post_type">{$post_type__options}</select></dd>
          <dt>投稿タイトル</dt><dd>{$post_title_radio}</dd>
          <dt>投稿ステータス</dt><dd><label><input type="checkbox" name="draft" value="1"{$draft_chk}> 「下書き」で投稿する</label></dd>
          <dt>投稿頻度</dt><dd><select name="period"><option value="">実行しない</option>{$period__options}</select></dd>
          {$api_url__html}
          <dt>開始日時</dt><dd><input type="datetime-local" name="start_date" value="{$start_date}"></dd>
          <dt style="margin-top:-5px"></dt><dd style="margin-top:-5px">※開始日時を起点に定期投稿します。</dd>
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
  form dt{width:9.5em;padding-top:10px;margin:7px 0}
  form dd{max-width:100%;min-width:200px;width:calc(100% - 9.5em);margin:12px 0}
  form dd label+label{margin-left:1rem}
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
    if(window.confirm('今すぐ投稿しますか？')){
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
  //初期設定が済んでいない場合
  public function print_not_set(){
    $selfTitle = esc_html(self::NAME);
    $link = self::PLUGIN_ID.'-config';
    echo <<<HTML
			<h2>{$selfTitle}</h2>
      <p>以下より設定を行ってください。</p>
      <a href="admin.php?page={$link}">設定する</a>
HTML;
    return false;
  }
  //#### エクスポートページ ####//
	public function print_export_page(){
    $selfTitle = esc_html(self::NAME)." エクスポート";
    $this_plugins_url = plugins_url()."/".self::PLUGIN_ID;
    $api_url = plugins_url()."/ks-ai_nikki/api/?key={$this->config->key}&mode=zip_data";
     echo <<<HTML
      <div class="wrap">
			<h2>{$selfTitle}</h2>
      <p>「エクスポート」ボタンをクリックすると設定、自動投稿スケジュールの情報をエクスポートします。</p>
			<form action="./admin.php?page=ks-ai_nikki" method="post" enctype="multipart/form-data" class="export">
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
			<form action="./admin.php?page=ks-ai_nikki" method="post" enctype="multipart/form-data" >
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
  form dt{width:9.5em;padding-top:10px;;margin:7px 0}
  form dd{max-width:100%;min-width:200px;width:calc(100% - 9.5em);margin:12px 0}
</style>
HTML;
  }
  /*################################################################################
    その他
  ################################################################################*/
  //#### ショートコード ####//
  public function shortcode(){
    add_shortcode('ks_ai_nikki', function($atts){
      extract(shortcode_atts(['key' => '', 'attrs'  => ''], $atts));
      switch($key){
        case "trends":
          $trends = $this->get_trends4google();
          $url = 'https://trends.google.co.jp/trends/explore?date=now%201-d&geo=JP&hl=ja&q=';
          $result = "<ul>";
          if(is_array($trends)){
              foreach( $trends as $key => $val){
                  $result .= "<li><a href=\"{$url}{$val["title"]}\" target=\"_blank\" rel=\"noopener\">{$val["title"]}</a></li>";
              }
          }
          $result .= "</ul>";
          break;
        case "get_post_types":
          $post_types = get_post_types(['public' => true, '_builtin' => false]);
          $result = "<ul>";
          if( is_array($post_types)){
            foreach( $post_types as $key => $val ){
              $label = esc_html(get_post_type_object($val)->label);
              $url = esc_html(get_post_type_archive_link($val));
              $result .= "<li class=\"{$val}\"><a href=\"{$url}\">{$label}</a></li>";
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
