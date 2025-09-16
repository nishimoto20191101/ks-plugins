<?php
##################################################################################################
# MYSQL操作(クラス)
##################################################################################################
class EditMySql {
    //プロパティ設定
	var $cnInfo;	        //DB接続情報(　ホストURL,ユーザーID,パスワード　)
	var $dbName;			//DB名
	var $conn;		        //DB接続
	var $tblObjArray;		//DB操作クラス(テーブル単位の処理を行うための情報)
    // コンストラクタ
	function  __construct( $cnInfo, $dbName ) {
	 	$this->cnInfo = explode(",", $cnInfo);	//DB接続情報データベース接続情報をカンマ区切りにする
		$this->dbName = $dbName; //DB名設定
	 }
   	// データベースに接続
	public function doConnect() {
		$this->conn = mysqli_init();
		mysqli_options( $this->conn, MYSQLI_OPT_LOCAL_INFILE, 1 );	//LOAD LOCAL INFILE の使用可
		//データベースへ接続
		mysqli_real_connect( $this->conn, $this->cnInfo[0], $this->cnInfo[1], $this->cnInfo[2], $this->dbName ) or trigger_error("DB選択エラー",E_USER_ERROR);
		//文字コード指定
		mysqli_set_charset ( $this->conn , 'utf8' );

		//データベースへ接続
		//$this->conn = mysqli_connect( $this->cnInfo[0], $this->cnInfo[1], $this->cnInfo[2] ) or trigger_error("DB接続エラー",E_USER_ERROR);
		//データベースの選択
		//mysqli_select_db( $this->conn, $this->dbName ) or trigger_error("DB選択エラー",E_USER_ERROR);
		
	}
  	// データベース切断
	public function doDisConnect() {
		$res = mysqli_close($this->conn) or trigger_error("DB切断エラー",E_USER_ERROR);
	}
 	// トランザクション開始
	public function doBeginTrans() {
		$this->queryExe("begin");
	}
	// トランザクションロールバック
	private function doRollbackTrans() {
		$this->queryExe("rollback");
  	}
	// トランザクションコミット
	public function doCommitTrans() {
		$sql = "commit";
		$this->queryExe($sql);
  	}
	// データ取得
	public function getData($sql) {
		$result = array();
		$data = $this->queryExe($sql); //クエリー実行
		while ( $row = mysqli_fetch_assoc($data) ) {
			$result[] = $row;
		}	
		return $result;
	}
	// データ取得
	public function getSelectData($sql) {
		$result = array();
		$data = $this->queryExe($sql); //クエリー実行
	    $i = 1;
	    while ( $row = mysqli_fetch_assoc($data) ) {
	        $result[$i][id]    = $row[0];
	        $result[$i][value] = $row[1];
	        $i++;
	    }
		return $result;
	}
	// SQLクエリー実行
	private	function queryExe( $sql ) {
	    $result = mysqli_query( $this->conn, $sql ) or trigger_error( mysqli_errno($this->conn).":\n".mysqli_error($this->conn).":\n{$sql}\n", E_USER_ERROR );
		return $result;
	}
	// 追加、更新、削除を伴う処理
	public function queryExeEdit( $sql ) {
	    $result = mysqli_query( $this->conn, $sql );
		if( $result === false ){
			$errorNo = mysqli_errno($this->conn);
			$errorText = mysqli_error($this->conn);
			$this->doRollbackTrans() || trigger_error($errorNo.":\n".$errorText.":\n{$sql}\n", E_USER_ERROR);
		}
		return $result;
	}
}
?>