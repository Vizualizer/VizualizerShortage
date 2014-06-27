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
 * 短縮URL設定のデータモデルです。
 *
 * @package VizualizerShortage
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerShortage_Model_Url extends Vizualizer_Plugin_Model
{
    private static $counts;

    /**
     * コンストラクタ
     */
    function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("shortage");
        parent::__construct($loader->loadTable("Urls"), $values);
    }

    /**
     * 主キーでデータを検索する。
     */
    function findByPrimaryKey($url_id)
    {
        $this->findBy(array("url_id" => $url_id));
    }

    /**
     * URLコードでデータを検索する。
     */
    function findByUrlCode($url_code)
    {
        if(strpos($url_code, "-") > 0){
            list($code, $suffix) = explode("-", $url_code, 2);
        }else{
            $code = $url_code;
        }
        $this->findBy(array("url_code" => $code));
    }

    protected function getClickCounts(){
        if(!is_array(self::$counts)){
            $attr = Vizualizer::attr();
            $loader = new Vizualizer_Plugin("shortage");
            $clickLog = $loader->loadModel("ClickLog");
            $log = $loader->loadTable("ClickLogs");
            $url = $loader->loadTable("Urls");
            $select = new Vizualizer_Query_Select($log);
            $select->addColumn($url->url_code, "url_code");
            $select->addGroupBy($url->url_code)->addOrder($url->url_code);
            $select->addColumn("COUNT(".$log->click_log_id.")", "total");
            $select->addColumn("SUM(CASE WHEN ".$log->click_type." = 1 THEN 1 ELSE 0 END)", "pc");
            $select->addColumn("SUM(CASE WHEN ".$log->click_type." = 2 THEN 1 ELSE 0 END)", "sp");
            $select->addColumn("SUM(CASE WHEN ".$log->click_type." = 3 THEN 1 ELSE 0 END)", "mb");
            $select->join($url, array($log->url_id." = ".$url->url_id));
            $select->addWhere($url->operator_id." = ?", array($attr[VizualizerAdmin::KEY]->operator_id));
            $clickLogs = $clickLog->queryAllBy($select);
            self::$counts = array();
            foreach($clickLogs as $log){
                self::$counts[$log->url_code] = array("total" => $log->total, "pc" => $log->pc, "sp" => $log->sp, "mb" => $log->mb);
            }
        }
        return self::$counts;
    }

    public function calcClickCounts() {
        $counts = $this->getClickCounts();
        $this->total_count = $counts[$this->url_code]["total"];
        $this->pc_count = $counts[$this->url_code]["pc"];
        $this->sp_count = $counts[$this->url_code]["sp"];
        $this->mb_count = $counts[$this->url_code]["mb"];
    }
}
