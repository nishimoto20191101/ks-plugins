<?php
/*
  Plugin Name: 山形トヨタ様プレミアムクラブ 顧客データアップロード
  Version: 2
  Description: KS data uploader
  Author: KALEIDOSCOPE co.,Ltd.
  Author URI: https://kaleidoscope.co.jp/
  Text Domain: wp-KS-csv-exporter
  Domain Path: /languages
 */
$ks_data_uploader = new Ks_Data_Uploader;
$ks_data_uploader->register();
class Ks_Data_Uploader {
	const NAME     = "顧客データアップロード";
	public function register(){
		$this->error = new WP_Error();
		add_action('plugins_loaded', [$this, 'plugins_loaded']);
	}
	public function plugins_loaded(){
		add_action('admin_menu', [$this, 'add_submemu_page']);
		add_action('init',       [$this, 'ks_upload']);
	}
	public function add_submemu_page(){
		add_submenu_page('tools.php', self::NAME, self::NAME, 'manage_options', 'ks-data-upload', [$this, 'print_options_page']);
	}
	public function print_options_page(){
		$selfTitle = esc_html(self::NAME);
		$selfUrl = plugins_url()."/ks-data-upload";
echo <<<HTML
		<script>
			(function ($) {
				$(document).on('change', '#post-type .required', function () {
					if( $('select[name=tbl]').val() != "" && $('input[name=csvfile]').val() != "" ){
						$('.submit input[type="submit"]').css( 'display' , 'block');
					}else{
						$('.submit input[type="submit"]').css( 'display' , 'none');
					}
				});
			})(window.jQuery);
		</script>

		<div class="wrap">
			<h2>{$selfTitle}</h2>
			<form action="" method="post" enctype="multipart/form-data" >
HTML;
wp_nonce_field('data_uploader');
echo <<<HTML
				<p>「i-corp-j」から抜き出した顧客データをアップロードすることができます。<br>
				「更新データ種類」と「アップロードファイル」を選択すると【アップロード】ボタンが表示されますので、更新される場合はクリックしてください。</p>
				<p>※アップロードしたファイルの内容と差し代わります。追加ではございませんのでご注意ください</p>

				<table class="form-table" id="post-type">
					<tr valign="top">
						<th scope="row"><label for="inputtext">更新データ種類</label></th>
						<td>
							<select name="tbl" required="required" class="required">
								<option value="">▼以下より選択してください</option>
								<option value="tbl_cust">顧客基本情報</option>
								<option value="tbl_car">基本車両情報</option>
								<option value="tbl_storing">入庫歴情報</option>
								<option value="tbl_trans">車両取引履歴情報</option>
								<option value="tbl_tire">タイヤ預かり情報</option>
							</select>
							<p style="font-size:.8em;margin-bottom:1em">※更新するのファイルの種類を選択してください。</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="inputtext">アップロードファイル</label></th>
						<td>
							<input type="file" name="csvfile" accept=".csv" required="required" class="required">
							<p style="font-size:.8em;margin-bottom:1em">※各種データ用に整形したi-crop-jのcsvファイルを選択してください。</p>
							<ul>
								<li style="font-weight:bold">サンプル</li>
								<li><a href="{$selfUrl}/sample/cust.csv" target="_blank">顧客基本情報</a>　
								<a href="{$selfUrl}/sample/car.csv" target="_blank">基本車両情報</a>　
								<a href="{$selfUrl}/sample/storing.csv" target="_blank">入庫歴情報</a>　
								<a href="{$selfUrl}/sample/trans.csv" target="_blank">車両取引履歴情報</a>　
								<a href="{$selfUrl}/sample/tire.csv" target="_blank">タイヤ預かり情報</a>
								</li>
							</ul>
						</td>
					</tr>
				</table>
				<table class="form-table" id="meta-keys"></table>
				<p class="submit"><input type="submit" class="button-primary" value="アップロード" style="display:none;" /></p>			
			</form>
		</div>
HTML;
	}
	/**
	 * エラーが発生したらメッセージを通知
	 */
	public function print_message(){
		$check['error'] = esc_html( get_transient('ks-data-upload-errors') );
		$check['updated']  = esc_html( get_transient('ks-data-upload-updated') );
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

	public function ks_upload(){
		if ( !empty($_POST['tbl']) && !empty($_FILES["csvfile"]) && is_uploaded_file($_FILES["csvfile"]["tmp_name"] ) ) {
			try {
				check_admin_referer('data_uploader');

				$tbl = $_POST['tbl'];
				$file_tmp_name = $_FILES["csvfile"]["tmp_name"];
				$file_name = $_FILES["csvfile"]["name"];
				
				if( pathinfo($file_name, PATHINFO_EXTENSION) != 'csv' ) {
					throw new Exception("CSVファイルのみ対応しています。");
					//set_transient('ks-data-upload-errors', "CSVファイルのみ対応しています。", 10);
					//add_action('admin_notices', [$this, 'print_message']);

				}else {
					$filePath = dirname(__FILE__)."/{$tbl}.csv"; //$file_name;
					if ( move_uploaded_file( $file_tmp_name, $filePath ) ) { //ファイルをdataディレクトリに移動
				
						$this->loadDataInFile( $tbl, $filePath );

					} else {
						throw new Exception("ファイルをアップロードできませんでした。");
					}
				}
							  
			} catch (Exception $ex) {
				throw new Exception($ex->getMessage());
			}
		}else if( !empty($_POST['tbl']) ){
			$errorData = print_r( $_FILES, true );
			throw new Exception("データが正しくありません。{$errorData}");
		}
	}

	/**
	 * アップロード実行
	 * @throws Exception
	 */
	function loadDataInFile( $tbl, $filePath, $option = [] ){
		$result = "";
	  
		if( empty($filePath) || ! file_exists($filePath) ){
			throw new Exception("ファイル「{$filePath}」がありません");
			return false;
		}
	  
		system('nkf --overwrite -w ' . $filePath);// ファイルをUTF-8に変換
		chmod( $filePath, 0644 ); //パーミッション変更

		$title     =  isset($option['title']) ? $option['title'] : true;
		$fieldChar =  !empty($option['fieldChar']) ? $option['fieldChar'] : ",";
		$enclosed  =  !empty($option['enclosed']) ? $option['enclosed'] : '"';
		$lineChar  =  !empty($option['lineChar']) ? $option['lineChar'] : '\r\n';
		
		//TRUNCATE `haw1005x4et6_club`.`tbl_cust`
		//require_once( dirname(__FILE__).'/class.mySql.php');  themes/ks/inc/custom-member.phpで読み込み済
		//php.iniのmysqli.allow_local_infileをOnにする
		$sql  = "LOAD DATA LOCAL INFILE '{$filePath}' ";
		$sql .= " REPLACE ";
		$sql .= " INTO TABLE {$tbl} ";
		$sql .= " FIELDS TERMINATED BY '{$fieldChar}' ENCLOSED BY '{$enclosed}' ";
		$sql .= " LINES  TERMINATED BY '{$lineChar}'";
		$sql .= $title ? " IGNORE 1 LINES " : "";
		$sql .= ";";
	  
		$dbObj = new EditMySql( DB_HOST.','.DB_USER.','.DB_PASSWORD, DB_NAME );
		$dbObj->doConnect();
		$dbObj->doBeginTrans();//トランザクション開始
		mysqli_query( $dbObj->conn, "TRUNCATE {$tbl}" ) or trigger_error( mysqli_errno($dbObj->conn).":\n".mysqli_error($dbObj->conn).":\n{TRUNCATE {$tbl}}\n", E_USER_ERROR );	//全データ削除
		//mysqli_query( $dbObj->conn, $sql ) or $this->dbError( $dbObj->conn, $sql ); //or trigger_error( mysqli_errno($dbObj->conn).":\n".mysqli_error($dbObj->conn).":\n{$sql}\n", E_USER_ERROR );
		$dbObj->queryExeEdit( $sql ) or $this->dbError( $dbObj->conn, $sql ); 
		$dbObj->doCommitTrans();//トランザクションコミット
		$dbObj->doDisConnect();//DB切断*/

		unlink( $filePath );

		set_transient('ks-data-upload-updated', "アップロードを完了しました。", 10);
		add_action('admin_notices', [$this, 'print_message']);
		
		return $result;
	  }

	  function dbError( $conn, $sql ){
		set_transient('ks-data-upload-errors', mysqli_errno($conn).":\n".mysqli_error($conn).":\n{$sql}\n", 10);
		add_action('admin_notices', [$this, 'print_message']);
	  }
}