<?php
/*
  Plugin Name: 山形トヨタ様プレミアムクラブ メ―ル配信用会員データダウンロード
  Version: 0.1
  Description: KS data down
  Author: KALEIDOSCOPE co.,Ltd.
  Author URI: https://kaleidoscope.co.jp/
  Text Domain: ks-data-dl
  Domain Path: /languages
 */
$ks_data_downloader = new Ks_Data_Downloader;
$ks_data_downloader->register();
class Ks_Data_Downloader {
	const NAME     = "メ―ル配信用会員データダウンロード";
	public function register(){
		$this->error = new WP_Error();
		add_action('plugins_loaded', [$this, 'plugins_loaded']);
	}
	public function plugins_loaded(){
		add_action('admin_menu', [$this, 'add_submemu_page']);
		add_action('init',  [$this, 'ks_download']);
	}
	public function add_submemu_page(){
		add_submenu_page('tools.php', self::NAME, self::NAME, 'manage_options', 'ks-data-dl', [$this, 'print_options_page']);
	}
	public function print_options_page(){
		$selfTitle = esc_html(self::NAME);
		$selfUrl = plugins_url()."/ks-data-dl";
echo <<<HTML
		<script>
			/*(function ($) {
				$(document).on('change', '#post-type .required', function () {
					if( $('select[name=rank]').val() != "" ){
						$('.submit input[type="submit"]').css( 'display' , 'block');
					}else{
						$('.submit input[type="submit"]').css( 'display' , 'none');
					}
				});
			})(window.jQuery);*/
		</script>

		<div class="wrap">
			<h2>{$selfTitle}</h2>
			<form action="" method="post" enctype="multipart/form-data" >
HTML;
wp_nonce_field('data_downloader');
echo <<<HTML
				<input type="hidden" name="rank" value="all">
				<p>GMO Mail Marketing配信用の会員データをCSV形式でダウンロードすることができます。<br>
				【CSVダウンロード】ボタンをクリックしてください。
				<!--「会員ランク」を選択すると【ダウンロード】ボタンが表示されますので、クリックしてください。 --></p>
				<!-- <table class="form-table" id="post-type">
					<tr valign="top">
						<th scope="row"><label for="inputtext">会員ステータス</label></th>
						<td>
							<select name="rank" required="required" class="required">
								<option value="">▼以下より選択してください</option>
								<option value="all">全会員</option>
								<option value="premium">プレミアム会員</option>
								<option value="normal">一般会員</option>
							</select>
							<p style="font-size:.8em;margin-bottom:1em">※ダウンロードする会員のステータスを選択してください。</p>
						</td>
					</tr>
				</table> -->
				<table class="form-table" id="meta-keys"></table>
				<p class="submit"><input type="submit" class="button-primary" value="CSVダウンロード" style="/*display:none;*/" /></p>			
			</form>
		</div>
HTML;
	}
	/**
	 * エラーが発生したらメッセージを通知
	 */
	public function print_message(){
		$check['error'] = esc_html( get_transient('ks-data-dl-errors') );
		$check['updated']  = esc_html( get_transient('ks-data-dl-updated'));
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

	public function ks_download(){
		if ( !empty($_POST['rank']) ) {
			try {
				check_admin_referer('data_downloader');

				$rank = $_POST['rank'];			
				$this->downloadData( $rank );
							  
			} catch (Exception $ex) {
				set_transient('ks-data-dl-errors', $ex->getMessage(), 10);
				add_action('admin_notices', [$this, 'print_message']);
			}
		}
	}

	/**
	 * ダウンロード実行
	 * @throws Exception
	 */
	function downloadData( $rank ){
		$result = "";

		require_once( ABSPATH."/../sys/class.mySql.php");
		require_once( ABSPATH."/../sys/functions.php");	//関数集
		require_once( ABSPATH."/../sys/cust/class.cust.php");
	  
		$dbObj = new EditMySql( DB_HOST.','.DB_USER.','.DB_PASSWORD, DB_NAME );
		$dbObj->doConnect();
		$dbObj->doBeginTrans();//トランザクション開始
		$cust = new cust($dbObj);
		$result = $cust->getCustCsv( $rank );
		$dbObj->doCommitTrans();//トランザクションコミット
		$dbObj->doDisConnect();//DB切断*/

		if ( empty($result) ){
			throw new Exception("該当会員が見つかりませんでした。");
		}

		//ファイル名
		$filename = "member-{$_POST['rank']}-".date_i18n('YmdHms').".csv";//wordpress仕様でUTC設定共用のためWPの独自関数を使って日時取得 "member-{$_POST['rank']}-".date('YmdHms').".csv";
		// 項目名を取得
		//$head[] = ['顧客コード','メールアドレス','名前','会員ランク'];
		$head[] = ['#配信状況（配信管理者様設定）(0:配信 1:停止)','配信状況（読者様設定）(0:配信 1:停止)','配信状況（システム設定）(0:配信 1:停止)','テストユーザー(0:本ユーザー 1:テストユーザー)','メールアドレス','氏名','性別(男性 女性)','都道府県','生年月日(yyyy/MM/dd)','顧客コード','会員ランク','登録日時'];
		// 先頭に項目名を追加
		$list = array_merge($head, $result);
		// 1時データを保存するためストリームを準備
		$fp = fopen('php://memory', 'r+b');
		// 配列をカンマ区切りにしてストリームに書き込み
		$i = 0;
		foreach ($list as $fields){
			$fields['custName'] = preg_replace('/( |　)(?= |　)/', '', $fields['custName']);
			//$fields['custName'] = preg_replace('/( |　)$/', '', $fields['custName']);
			if( $i > 0 ){
				$fields['joinDate'] = date('Y-m-d H:i:s', strtotime( get_user_by( 'login', $fields['custNo'] )->user_registered.'+9hour' ));
			}
			fputcsv($fp, $fields);
			$i++;
		}
		// ポインタを先頭に戻す
		rewind($fp);
		// CSVフォーマットされた文字列をストリームから読みだして変数に格納
		$tmp = str_replace(PHP_EOL, "\r\n", stream_get_contents($fp));
		fclose($fp);
		$tmp = mb_convert_encoding($tmp, 'SJIS-win', 'UTF-8');
		header('Content-Type:application/octet-stream');
		header('Content-Disposition:filename=' . $filename);
		header('Content-Length:' . strlen($tmp));
		echo $tmp;  //ダウンロード
		exit;
	  }

	  function dbError( $conn, $sql ){
		set_transient('ks-data-dl-errors', mysqli_errno($conn).":\n".mysqli_error($conn).":\n{$sql}\n", 10);
		add_action('admin_notices', [$this, 'print_message']);
	  }
}