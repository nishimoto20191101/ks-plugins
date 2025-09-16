<?php
$exec_key = !empty($_GET["key"]) ? $_GET["key"] : "";
$mode = !empty($_GET["mode"]) ? $_GET["mode"] : "";

if(!empty($mode)){
    include_once( dirname(__FILE__).'/../../../../wp-load.php');
    require_once( dirname(__FILE__).'/../class.php');
    $ks_ai_nikki = new Ks_AI_nikki();
    $ks_ai_nikki->set_AI_Client();
    add_action('init', [$ks_ai_nikki, 'init']);
    if($ks_ai_nikki->get_config_key() == $exec_key){
        if( $mode == "post"){
            $no = !empty($_GET["no"]) ? $_GET["no"] : 0;
            $result = !empty($ks_ai_nikki->sched[$no]) ? $ks_ai_nikki->ks_post_sched_exec($no) : "";
            echo $result ? "{$ks_ai_nikki->sched[$no]->nikki_title} 投稿完了しました。" : "投稿失敗しました。";
            return true;
        }else if($mode == "title"){
            $no = !empty($_GET["no"]) ? $_GET["no"] : 0;
            echo "タイトル<br>";
            echo $ks_ai_nikki->mk_title();
        }else if($mode == "anser"){
            if(!empty($_GET["message"])){
                $text_form = !empty($_GET["text_form"]) ? $_GET["text_form"] : "日記";
                echo $ks_ai_nikki->post_generation( ["title"=>$_GET["message"], "text_form"=>$text_form] )["content"];
                return true;
            }
        }else if($mode == "prompt"){
            if(!empty($_GET["exp"])){
                echo "結果".$ks_ai_nikki->mk_prompt( $_GET["exp"] );
                return true;
            }
        }else if($mode == "zip_data"){
            $result = $ks_ai_nikki->dl_zip_data_fld();
        }else if($mode == "search"){
            if(!empty($_GET["word"])){
                echo "<h1>{$_GET["word"]}</h1>\n";
                $result = "";
                $news = $ks_ai_nikki->get_news4google($_GET["word"], 5);
                if(is_array($news)){
                    foreach( $news as $key => $val){
                        $result .= "<h3>{$val["title"]}</h3>{$val["url"]}<br>\n";
                    }
                }
                echo $result;
            }else{
                echo "...";
            }
        }else if($mode == "trends"){
            echo "<h1>Google Trends</h1>\n";
            $result = "<ul>";
            $trends = $ks_ai_nikki->get_trends4google();
            if(is_array($trends)){
                foreach( $trends as $key => $val){
                    $result .= "<li>{$val["title"]}</li>";
                }
            }
            $result .= "</ul>";
            echo $result."<hr>";
        }
    }
    return false;
} 
