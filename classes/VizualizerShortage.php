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
        $info = pathinfo($attr["templateName"]);

        // URLをパース
        $baseUrl = substr($info["dirname"], 1);
        if(strpos($baseUrl, "/") > 0 && $info["basename"] == "index.html"){
            list($baseUrl, $codeUrl) = explode("/", $baseUrl, 2);
        }else{
            $codeUrl = $info["filename"];
        }

        // 短縮URLの対象ユーザーを取得
        $loader = new Vizualizer_Plugin("Admin");
        $operator = $loader->loadModel("CompanyOperator");
        $operator->findBy(array("url" => $baseUrl));

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
                if(Vizualizer_Configure::get("device")->isFuturePhone()){
                    $clickLog->click_type = 3;
                    header("Location: ".$url->mb_url);
                }elseif(Vizualizer_Configure::get("device")->isSmartPhone()){
                    $clickLog->click_type = 2;
                    header("Location: ".$url->sp_url);
                }else{
                    $clickLog->click_type = 1;
                    header("Location: ".$url->pc_url);
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
