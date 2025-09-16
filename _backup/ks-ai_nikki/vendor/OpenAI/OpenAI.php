<?php

 class Ks_OpenAI {
  public  $AiClient;
  private $OpenAiKey;
  private $anser_cnt = 0;
  /*################################################################################
    初期処理
  ################################################################################*/
  static function init(){
    return new self();
  }
  function __construct($key){
    $this->OpenAiKey = $key;
  }
  //#### 生成AIサービスからの返答 ####//
  public function get_anser($prompt){
    $result = "";
    $data = [
      'model' => 'gpt-3.5-turbo',
      'messages' => [
        [ "role" => "system", "content" => "日本語で応答してください" ],
        [ "role" => "user",   "content" => $prompt ]
      ]
    ];
    $response = $this->exec('https://api.openai.com/v1/chat/completions', $data);
    $response = json_decode($response, true);
    $result = json_encode($response['choices'][0]['message']['content'], JSON_PRETTY_PRINT);

    return $result;
  }
  //#### 生成AIサービスからの返答（画像入力） ####//
  /*public function get_anser_image($image_url, $mimeType='image/jpeg'){
    $result = $this->AiClient->geminiPro()->generateContent( new KS_OpenAI_ImagePart($mimeType, base64_encode(file_get_contents($image_url))) )->text();
    if( empty($result) && $this->anser_err < 5 ){
      $this->anser_err++;
      $result = $this->get_anser_image($image_url, $mimeType);
    }
    $this->anser_cnt = 0;
    return $result;
  }*/
  //#### 生成AIサービスからの返答（画像） ####//
  public function get_image($prompt){
    return $this->mk_image(['prompt' => $prompt])[0];
  }
  public function mk_image($data){
    $result = "";
    if( empty($data['prompt']) ){ return 'エラー: promptが指定されていません。'; }
    $data =[
        'model'   => !empty($data['model']) ? $data['model'] : 'dall-e-3',
        'prompt'  => !empty($data['prompt']) ? $data['prompt'] : "",
        'n'       => !empty($data['n']) ? $data['n'] : 1, //枚数
        'size'    => !empty($data['size'])   ? $data['size'] : '1024x1024', //256x256 | 512x512 | 1024x1024 | 1792x1024 | 1024x1792
        'quality' => !empty($data['quality']) ? $data['quality'] : 'standard',  //standard | hd
        'style'   => !empty($data['style']) ? $data['style'] : 'natural',   //vivid | natural
    ];
    $response = $this->exec('https://api.openai.com/v1/images/generations', $headers, $data);
    $json_result = json_decode($response, true);
    if ($json_result === null){
        $result = 'JSONデコードエラー: ' . json_last_error_msg();
    }else{
        $result = [];
        foreach($json_result['data'] as $key => $val){
            $result[] = $val["url"];
        }
    }
    return $result;
  }
  private function exec($url, $data){
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $this->ApiKey,
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($ch);
    if ( !emppty($result) ){
        $result = false; //'cURLエラー: ' . curl_error($ch);
    }
    curl_close($ch);
    return $result;
  }
  //#### 投稿内容生成 ####//
  //人格プロンプトに関する論文  https://arxiv.org/abs/2410.05603
  public function mk_prompt( $type, $data = [] ){
    $result = "";
    switch($type){
      ################################################################################################3
      case 'editor':
        $result =<<<TEXT
## 役割・目標
あなたは、ChatCPT モデル用の効果的なプロンプトを自動生成するAIアシスタントです。目標は、ユーザーが指定したタスクに対して最適化された、明確で構造化されたプロンプトを作成することです。

## 視点・対象
- 主な対象：Gemini AIモデルを使用するユーザー
- 二次的な対象：Gemini AIモデル自体（プロンプトの受け手として）

## 制約条件
1. 生成されるプロンプトは、ChatCPTモデルの特性と制限を考慮に入れたものであること
2. プロンプトは明確で簡潔であること、ただし必要な詳細は省略しないこと
3. 特定の構造（役割・目標、制約条件など）を含めること
4. 倫理的で法的に問題のない内容であること
5. Geminiの機能と制限を正確に反映すること

## 処理手順 (Chain of Thought)
1. ユーザーの入力を分析し、要求されているタスクを特定する
2. タスクに適した役割と目標を定義する
3. 対象となる視点や読者を決定する
4. タスクに関連する制約条件をリストアップする
5. タスクを完了するための具体的な手順を考案する
6. 必要な入力情報を特定する
7. 期待される出力形式を決定する
8. 上記の要素を組み合わせて、構造化されたプロンプトを作成する
9. プロンプトを見直し、明確さと簡潔さを確認する
10. 必要に応じて微調整を行う

## 入力文
以下の形式で入力を受け付けます：

[{$data["exp"]}、コンテンツ記事を投稿する]のGemini用プロンプトを役割・目標、視点・対象、制約条件、処理手順(CoT)、入力文、出力文を考慮して作成して

## 出力文
以下の構造に従ってプロンプトを生成します：

# [タスク名] Gemini Prompt

## ペルソナ
[ペルソナの説明]

## 役割・目標
[役割と目標の説明]

## 視点・対象
[視点と対象の列挙]

## 制約条件
1. [制約条件1]
2. [制約条件2]
...

## 処理手順 (Chain of Thought)
1. [手順1]
2. [手順2]
...

## 入力文
[必要な入力情報の説明]

## 出力文
[期待される出力形式の説明]

このフォーマットに従って、要求されたタスクに最適化されたGemini用プロンプトを生成します。
TEXT;
        break;
      ################################################################################################3
      case 'title':
        if(in_array($data["text_form"], ["ニュース"])){
          $date = wp_date( 'Y-m-d H:i', strtotime("-7 day") );
          $constraints = "6.{$date}以降の話題を元に作成すること\n7.最も大事なニュースに絞っていること\n";
        }else{
          $constraints = "";
        }
        $result =<<<TEXT
{$data["editor_prompt"]}

## 追加の制約条件
1.Do not hallucinate
2.最大30文字程度であること
3.検索ボリュームの多いキーワードを1つ以上含めること
4.何の話題かを明示していること
5.*【】は使用しないこと
{$constraints}

## 作成日時
{$data["post_date"]}

役割・目標、視点・対象、制約条件、処理手順(CoT)、入力文、追加の制約条件、作成日時、参考サイトを考慮して作成する
コンテンツ{$data["nikki_title"]}の{$data["text_form"]}記事の[タイトル]を作成して

以下の構造に従ってタイトルを生成します：
[タイトル]

このフォーマットに従って、要求されたタスクに最適化されたタイトルを生成します。
TEXT;
        break;
      ################################################################################################3
      case 'post':
        $post_date = !empty($data["post_date"]) ? $data["post_date"] : wp_date("Y-m-d H:i:s");
        $categories = !empty($data["category"]) && is_array($data["category"]) ? implode(",", $data["category"]) : "";
        $print_format = "";
        $constraints = "";
        $reference = "";
        $print_rule = "";
        if($data["text_form"] == "12星座占い"){
          $print_rule =<<<TEXT
1.[星座名]ごとに、12つの記事ブロックに分けて[占い結果]を作成する。
2.[占い結果]を、SEOと読みやすさの観点から、修正して[占い結果]として出力する。
3.同様にして、第2-12ブロックの[星座名]と[占い結果]を出力する。
TEXT;
          $print_format =<<<TEXT
          <div class="ai_make_content OpenAI">

            <div class="Aries">
              <h2><span class="zodiac">&#x2648;︎ [星座名]</span><span class="birth">[mm/dd～mm/dd]生まれの人</span></h2>
              [占い結果]
            </div>

          </div>
TEXT;
        }else if(in_array($data["text_form"], ["ニュース", "レポート"]) ){
          $print_rule =<<<TEXT
1.[記事タイトル]をもとに、5つの記事ブロックに分けて事実をもとにした情報で[アウトライン]を作成する。
2.[アウトライン]を元に、第1ブロックの[見出し]と[見出しに対応した文章]を作成する。
3.[見出し]と[見出しに対応した文章]を、SEOと読みやすさの観点から、修正して[見出し]と[見出しに対応した文章]として出力する。
4.同様にして、第2ブロック、第3ブロック、第4ブロック、第5ブロックまでの[見出し]と[見出しに対応した文章]を出力する。
5.記事の最後に[参考サイト]を出力する
TEXT;
        $date = wp_date( 'Y-m-d H:i', strtotime("-7 day") );
        $constraints = "3.{$date}以降の話題を元に記事を作成すること\n";
        if( is_array($data["reference"]) ){
          $constraints .= "4.自己紹介を記載しないこと\n5.Do not hallucinate reference sites\n6.参考サイトのタイトルが取得できない場合は内容をもとに作成すること\n";//制約条件 Do not hallucinate
          $reference = "\n## 参考サイト\n";
          foreach( $data["reference"] as $key => $val){
            $reference .= "・{$val["title"]}：{$val["url"]}\n";
          }
          $reference .= "\n";
          $print_reference =<<<TEXT

  <h2 class="reference">参考サイト</h2>
  <ul>

    <li><a href="[URL]" target="_blank" rel="noopener">[ページタイトル]</a></li>

  </ul>

TEXT;
        }
        $print_format =<<<TEXT
<div class="ai_make_content OpenAI">

  <h2>[見出し]</h2>
  <h3>[小見出し]</h3>
  [見出しに対応した文章]

{$print_reference}

</div>
TEXT;
      }else{
        $print_rule =<<<TEXT
1.[記事タイトル]をもとに、5つの記事ブロックに分けて[アウトライン]を作成する
2.[アウトライン]を元に、第1ブロックの[文章]を作成する。
3.[文章]を、SEOと読みやすさの観点から、修正して[最終稿]として出力する。
4.同様にして、第2ブロック、第3ブロック、第4ブロック、第5ブロックまでの[最終稿]を出力する。
TEXT;
        $print_format =<<<TEXT
<div class="ai_make_content OpenAI">

<div>[最終稿]</div>

</div>
TEXT;
      }
        $result =<<<TEXT
{$data["editor_prompt"]}

## 追加の制約条件
1.記事内にはタイトルを記載しないこと
2.記事内には作成日時を記載しないこと
{$constraints}
## 記事タイトル
{$data["title"]}

## 記事カテゴリ
{$categories}

## 作成日時
{$post_date}

## コンテンツ形態
{$data["text_form"]}
{$reference}
## 記事作成手順
{$print_rule}

## 出力形式
HTML形式で構造化された記事

役割・目標、視点・対象、制約条件、処理手順(CoT)、入力文、出力文、追加の制約条件、記事タイトル、記事カテゴリ、作成日時、コンテンツ形態、参考サイト、記事作成手順、出力形式を考慮して記事を作成して

以下の構造に従って記事を生成します：

{$print_format}

このフォーマットに従って、要求されたタスクに最適化された記事を生成します。
TEXT;
        break;
    }

    return $result;
  }
 }
