<?php

/**
 * Copyright (C) 2012 Vizualizer All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author    Naohisa Minagawa <info@vizualizer.jp>
 * @copyright Copyright (c) 2010, Vizualizer
 * @license http://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 * @since PHP 5.3
 * @version   1.0.0
 */

// プラグインの初期化
VizualizerShortage::initialize();

/**
 * プラグインの設定用クラス
 *
 * @package VizualizerAddress
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerShortage
{

    /**
     * プラグインの初期化処理を行うメソッドです。
     */
    final public static function initialize()
    {
    }

    /**
     * 短縮URL用受け入れコールバック関数
     */
    final public static function prefilter(){
        // 呼び出されたURLを取得
        $attr = Vizualizer::attr();
        $info = pathinfo(str_replace(VIZUALIZER_SUBDIR."/", "", str_replace("?".$_SERVER["QUERY_STRING"], "", $_SERVER["REQUEST_URI"])));

        // URLをパース
        $baseUrl = "";
        if(array_key_exists("dirname", $info)){
            if($info["dirname"] == "."){
                $baseUrl = "";
            }elseif(substr($info["dirname"], 0, 2) == "./"){
                $baseUrl = substr($info["dirname"], 2);
            }elseif(substr($info["dirname"], 0, 1) == "/"){
                $baseUrl = substr($info["dirname"], 1);
            }else{
                $baseUrl = $info["dirname"];
            }
        }
        if(strpos($baseUrl, "/") > 0 && $info["basename"] == "index.html"){
            list($baseUrl, $codeUrl) = explode("/", $baseUrl, 2);
        }else{
            $codeUrl = $info["filename"];
        }
        if(Vizualizer_Configure::get("shorturl_ignore_subdir")){
            $codeUrl = $baseUrl . ((!empty($baseUrl) && !empty($codeUrl))?"/":"") . $codeUrl;
            $baseUrl = "";
        }

        // 短縮URLの対象ユーザーを取得
        $loader = new Vizualizer_Plugin("Admin");
        $operator = $loader->loadModel("CompanyOperator");
        if(Vizualizer_Configure::get("shorturl_ignore_subdir")){
            $operator->findBy(array());
        }else{
            $operator->findBy(array("url" => $baseUrl));
        }
        if(!($operator->operator_id > 0)){
            $operator->findBy(array("url" => ""));
            $codeUrl = $baseUrl . ((!empty($baseUrl) && !empty($codeUrl))?"/":"") . $codeUrl;
            $baseUrl = "";
        }
        // 短縮URLを取得
        if($operator->operator_id > 0){
            $loader = new Vizualizer_Plugin("Shortage");
            $url = $loader->loadModel("Url");
            if(strpos($codeUrl, "-") > 0){
                list($baseCode, $extCode) = explode("-", $codeUrl);
            }else{
                $baseCode = $codeUrl;
                $extCode = "";
            }
            $url->findBy(array("operator_id" => $operator->operator_id, "url_code" => $baseCode));
            if($url->url_id > 0){
                $clickLog = $loader->loadModel("ClickLog");
                $clickLog->url_id = $url->url_id;
                $clickLog->url_code = $codeUrl;
                $clickLog->referer = $_SERVER["HTTP_REFERER"];
                $clickLog->user_agent = $_SERVER["HTTP_USER_AGENT"];
                $clickLog->ip_address = $_SERVER["REMOTE_ADDR"];
                $redirectUrl = $url->pc_url;
                if(Vizualizer_Configure::get("device")->isFuturePhone()){
                    $clickLog->click_type = 3;
                    if($url->mb_url){
                        $redirectUrl = $url->mb_url;
                    }
                }elseif(Vizualizer_Configure::get("device")->isSmartPhone()){
                    $clickLog->click_type = 2;
                    if($url->sp_url){
                        $redirectUrl = $url->sp_url;
                    }
                }else{
                    $clickLog->click_type = 1;
                }
                // トランザクションの開始
                $connection = Vizualizer_Database_Factory::begin("shortage");
                try {
                    $clickLog->save();
                    // エラーが無かった場合、処理をコミットする。
                    Vizualizer_Database_Factory::commit($connection);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    Vizualizer_Logger::writeInfo("Skipped save Click Log");
                    Vizualizer_Logger::writeInfo(print_r($clickLog->toArray()));
                }
                ob_end_clean();
                echo "<html>";
                echo "<head>";
                echo "<meta http-equiv=\"refresh\" content = \"0; url=".$redirectUrl."\" >";
                echo "<meta name=\"robots\" content=\"noindex,noarchive\" />";
                echo "</head>";
                echo "<body>";
                echo "<!--以下にSSのCVタグ、リタゲタグ、アクセス解析タグ、ASPのクリエイティブ名など-->";
                echo $url->custom_tag;
                echo "</body>";
                echo "</html>";
                exit;
            }
        }
    }

    /**
     * データベースインストールの処理を行うメソッド
     */
    final public static function install()
    {
    }
}
