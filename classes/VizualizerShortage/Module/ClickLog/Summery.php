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

/**
 * クリックログ集計データを取得する。
 *
 * @package VizualizerShortage
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerShortage_Module_ClickLog_Summery extends Vizualizer_Plugin_Module
{

    function execute($params)
    {
        $post = Vizualizer::request();
        $attr = Vizualizer::attr();
        $loader = new Vizualizer_Plugin("shortage");
        $clickLog = $loader->loadModel("ClickLog");
        $log = $loader->loadTable("ClickLogs");
        $url = $loader->loadTable("Urls");
        $select = new Vizualizer_Query_Select($log);

        // デフォルト値を設定
        if(preg_match("/^([0-9]{4})-?([0-9]{2})$/", $post["ym"], $p) == 0){
            $post["ym"] = date("Ym");
        }
        if(preg_match("/^[0-9]{1,2}$/", $post["d"]) == 0 && empty($post["k"])){
            $post["d"] = date("d");
        }

        // 集計処理を実行
        if(preg_match("/^([0-9]{4})-?([0-9]{2})$/", $post["ym"], $p) > 0){
            $year = $p[1];
            $month = $p[2];
            $attr["currentYm"] = date("Ym", strtotime($year."-".$month."-01"));
            $attr["currentDay"] = $post["d"];
            $attr["currentKeyword"] = $post["k"];
            $attr["prevYm"] = date("Ym", strtotime("-1 month", strtotime($year."-".$month."-01")));
            $attr["nextYm"] = date("Ym", strtotime("+1 month", strtotime($year."-".$month."-01")));
            $dates = array();
            for($i = 1; $i <= date("t", strtotime("-1 month", strtotime($year."-".$month."-01"))); $i ++){
                $dates[] = $i;
            }
            $attr["dates"] = $dates;
            if(preg_match("/^[0-9]{1,2}$/", $post["d"]) > 0){
                $day = $post["d"];
                if(!empty($post["k"])){
                    // 日別生ログ
                    $keyword = $post["k"];
                    if($post["t"] > 0){
                        $clickLogs = $clickLog->findAllBy(array("click_type" => $post["t"], "back:create_time" => $year."-".$month."-".$day." "));
                    }else{
                        $clickLogs = $clickLog->findAllBy(array("back:create_time" => $year."-".$month."-".$day." "));
                    }
                }else{
                    // 日別集計
                    if($post["ext"] == "1"){
                        $select->addColumn($log->url_code, "url_code");
                        $select->addGroupBy($log->url_code)->addOrder($log->url_code);
                    }else{
                        $select->addColumn($url->url_code, "url_code");
                        $select->addGroupBy($url->url_code)->addOrder($url->url_code);
                    }
                    $select->addColumn("COUNT(".$log->click_log_id.")", "total");
                    $select->addColumn("SUM(CASE WHEN ".$log->click_type." = 1 THEN 1 ELSE 0 END)", "pc");
                    $select->addColumn("SUM(CASE WHEN ".$log->click_type." = 2 THEN 1 ELSE 0 END)", "sp");
                    $select->addColumn("SUM(CASE WHEN ".$log->click_type." = 3 THEN 1 ELSE 0 END)", "mb");
                    $select->join($url, array($log->url_id." = ".$url->url_id));
                    $select->addWhere($url->operator_id." = ?", array($attr[VizualizerAdmin::KEY]->operator_id));
                    $select->addWhere($log->create_time." LIKE ?", array($year."-".$month."-".$day." %"));
                    $clickLogs = $clickLog->queryAllBy($select);
                }
            }elseif(!empty($post["k"])){
                // 月別集計
                $keyword = $post["k"];
                list($kw) = explode("-", $keyword, 2);
                $select->addColumn("DATE(".$log->create_time.")", "log_date");
                $select->addGroupBy("DATE(".$log->create_time.")")->addOrder("DATE(".$log->create_time.")");
                $select->addColumn("COUNT(".$log->click_log_id.")", "total");
                $select->addColumn("SUM(CASE WHEN ".$log->click_type." = 1 THEN 1 ELSE 0 END)", "pc");
                $select->addColumn("SUM(CASE WHEN ".$log->click_type." = 2 THEN 1 ELSE 0 END)", "sp");
                $select->addColumn("SUM(CASE WHEN ".$log->click_type." = 3 THEN 1 ELSE 0 END)", "mb");
                $select->join($url, array($log->url_id." = ".$url->url_id));
                $select->addWhere($url->operator_id." = ?", array($attr[VizualizerAdmin::KEY]->operator_id));
                $select->addWhere($log->create_time." LIKE ?", array($year."-".$month."-%"));
                $select->addWhere($url->url_code." = ?", array($kw));
                $clickLogs = $clickLog->queryAllBy($select);
            }else{
                throw new Vizualizer_Exception_Invalid("op", "追加の条件が指定されていません");
            }
        }else{
            throw new Vizualizer_Exception_Invalid("ym", "年月の指定が正しくありません");
        }
        $attr["click_logs"] = $clickLogs;
    }
}
