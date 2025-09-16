<?php
class Ks_OPENAI_DALL-E3{
  public  $AiClient;
  private $ApiKey;
  /*################################################################################
    初期処理
  ################################################################################*/
  static function init(){
    return new self();
  }
  function __construct($key){
    $this->ApiKey = $key;
  }
  public function set_data($data){
    $result =[
        'model'   => !empty($data['model']) ? $data['model'] : 'dall-e-3',
        'prompt'  => !empty($data['prompt']) ? $data['prompt'] : "",
        //'n'     => !empty($data['n']) ? $data['n'] : 1, //枚数
        'size'    => !empty($data['size'])   ? $data['size'] : '1024x1024', //256x256 | 512x512 | 1024x1024 | 1792x1024 | 1024x1792
        'quality' => !empty($data['quality']) ? $data['quality'] : 'standard',  //standard | hd
        'style'   => !empty($data['style']) ? $data['style'] : 'natural',   //vivid | natural
    ];
    return $result;
  }
  public function get_image($data = ""){
    if( empty($data['prompt']) ){ return 'エラー: promptが指定されていません。'; }
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $this->ApiKey,
    ];
    $data = $this->set_data($data);
    $ch = curl_init('https://api.openai.com/v1/images/generations');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    if ($response === false){
        $result = 'cURLエラー: ' . curl_error($ch);
    } else{
        $json_result = json_decode($response, true);
        if ($json_result === null){
            $result = 'JSONデコードエラー: ' . json_last_error_msg();
        } else{
            $result = [];
            foreach($json_result['data'] as $key => $val){
                $result[] = $val["url"];
            }
        }
    }
    curl_close($ch);
    return $result;
  }
}