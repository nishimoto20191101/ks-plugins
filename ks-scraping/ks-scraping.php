<?php
/*
  Plugin Name: スクレイピング
  Version: 1.0.0
  Description: KS scraping
  Author: KALEIDOSCOPE co.,Ltd.
  Author URI: https://kaleidoscope.co.jp/
  Text Domain: ks-scraping
  Domain Path: /ks-scraping
 */
require_once(dirname(__FILE__).'/GeminiAPI/vendor/autoload.php');
use GeminiAPI\Client;
use GeminiAPI\Resources\Parts\TextPart;

$sources = [ 
  //["title" => "【怨霊･封印】投稿系ホラー7【XXX･ﾏｼﾞｶﾙ】", "url" => "https://lavender.5ch.net/test/read.cgi/movie/1727497852/"],
  ["title" => "年瀬のあいさつ", "url" => ""],
];

$ks_scraping = new Ks_scraping('post', $sources);
class Ks_scraping {
  const NAME = "KSスクレイピング";
  private $error;
  private $post_type;
  private $sources;
  private $GeminiApiKey = "AIzaSyBEvqsElmSBHw_koQAW6lqlhfUrCRs50FA";
  function __construct($post_type = 'post', $sources = []){
		$this->error = new WP_Error();
    if (is_admin()) {
      add_action('admin_menu', [$this, 'add_submemu_page']);
      add_action('init',       [$this, 'ks_scraping']);
    }
    $this->post_type = $post_type;
    $this->sources = $sources;
  }
	public function add_submemu_page(){
		add_submenu_page('tools.php', self::NAME, self::NAME, 'manage_options', 'ks-scraping', [$this, 'print_options_page']);
	}
	public function print_options_page(){
    $selfTitle = esc_html(self::NAME);
    echo <<<HTML
      <div class="wrap">
			<h2>{$selfTitle}</h2>
			<form action="" method="post" >
HTML;
    wp_nonce_field('data_scraping');
    echo <<<HTML
				<input type="hidden" name="mode" value="exec">
        <textarea name="sources" height="5"></textarea>
				<p class="submit"><input type="submit" class="button-primary" value="実行" /></p>			
			</form>
		</div>
HTML;
  }
	// エラーが発生したらメッセージを通知
	public function print_message(){
		$check['error'] = esc_html( get_transient('ks-scraping-errors') );
		$check['updated']  = esc_html( get_transient('ks-scraping-updated') );
		$status = $check['error'] ? 'error' : 'updated';
		$message = $check[$status];
		if ($message)	{
			echo <<<HTML
			<div id="message" class="{$status} notice is-dismissible">
				<p>{$message}</p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text">この通知を非表示にする</span>
				</button>
			</div>
HTML;
		}
	}
	// スクレイピングし投稿
  public function ks_scraping(){
    $mode = !empty($_POST['mode']) ? $_POST['mode'] : "";
		if ( !empty($mode)) {
			try {
				check_admin_referer('data_scraping');
        $sources = $this->sources;
        if(!empty($sources) && $mode == 'exec'){
          foreach($sources as $key => $val){
            if(!empty($val["title"])){
              $post_date = $val["date"] ? $val["date"] : date_i18n("Y-m-d h:m:s");
              $post_value = [
                  'post_type' => $this->post_type, //カスタム投稿タイプの名称を入れる
                  'post_author' => 1,
                  'post_title' => $val["title"],
                  'post_content' => $this->post_generation($val),
                  'post_category' => [],
                  'tags_input' => ['', ''],
                  'post_status' => 'publish',  //公開済(publish)　予約済(future)　下書き(draft)　承認待ち(pending)　非公開(private)
                  'post_date' => $post_date
              ];
              wp_insert_post($post_value);
            }
          } 
        }
        set_transient('ks-scraping-updated', "投稿を完了しました。", 10);
        add_action('admin_notices', [$this, 'print_message']);
			} catch (Exception $ex) {
				set_transient('ks-scraping-errors', $ex->getMessage(), 10);
				add_action('admin_notices', [$this, 'print_message']);
			}
		}
	}
	// 投稿内容生成
  public function post_generation( $source = [] ){
    $result = "";
    $url = $source['url'];
    if(empty($url)){
      //API KEY
      $apiKey = $this->GeminiApiKey;
      //質問
      $user_message = "タイトル「{$source['title']}」の記事をHTMLで作成して";
      $client = new Client($apiKey);
      //回答取得
      $result = $client->geminiPro()->generateContent(
        new TextPart($user_message)
      );
      //出力
      $title = $user_message;
      $result = $result->text();
      $result = str_replace('```html', '', $result);
    }else{
      $site = preg_match("/^https:\/\//", $url) ? file_get_contents($url) : "";
      //FQDN判定で処理を切り分け
      if(empty($site)){
        $result = "<div>URLの値が間違えています</div>";
      }else{
        require_once(dirname(__FILE__)."/phpQuery-onefile.php");
        $content = phpQuery::newDocument($site);
        if(strpos($url, "https://stocks.finance.yahoo.co.jp/") !== false){
          $corp  = $content->find(".name__xcPE:eq(0)")->text();
          $price = $content->find(".number__3wVT:eq(0)")->text();//株価
          $result = "{$corp}：{$price}<br>";
        }else if(strpos($url, "https://finance.yahoo.co.jp/") !== false){
          $corp  = $content->find(".PriceBoardMain__name__6uDh:eq(0)")->text();
          $price = $content->find(".StyledNumber__value__3rXW:eq(0)")->text();//株価
          $result = "{$corp}：{$price}<br>";
        }else if(strpos($url, "https://lavender.5ch.net/") !== false){//5ch
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
          $result = "<h3>{$title}</h3>".implode("", $html);
        }else{
          $result = phpQuery::newDocument($site)->find("body")->html();
        }      
      }
    }
    return $result;
  }
}
