<?php
/**
 * Magento - Unicenta Opos Integrator by Asulpunto
 *
 * NOTICE OF LICENSE
 *  This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, Version 3 of the License. You can view
 *   the license here http://opensource.org/licenses/GPL-3.0

 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 * @category    Asulpunto
 * @package     Asulpunto_Unicentaopos
 * @copyright   Copyright (c) 2013 Asulpunto (http://www.asulpunto.com)
 * @license     http://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 *
 */

class Asulpunto_Unicentaopos_Model_Unicentaoposapi extends Mage_Core_Model_Abstract
{
    const TABLE_PRODUCTS='products';
    const TABLE_LOCATIONS='locations';
    const TABLE_STOCKCURRENT='stockcurrent';
    const TABLE_APPLICATIONS='applications';

public function checkActivate(){
        $config = new Mage_Core_Model_Config();
        $rows=Mage::Helper('unicentaopos')->doQuery("show tables");
        $hasTables=false;
        if (!is_null($rows)){
            Mage::log(__METHOD__.'SHOW TABLES: '.print_r($rows,true),null,"asulpunto_unicentaopos.log");
            foreach  ($rows as $row){
                $hasTables=true;
                if (strtolower($row[0])==self::TABLE_PRODUCTS){
                    $config->saveConfig("asulpuntounicentaopos/tablenames/".self::TABLE_PRODUCTS, $row[0], 'default', 0);
                }
                if (strtolower($row[0])==self::TABLE_APPLICATIONS){
                    $config->saveConfig("asulpuntounicentaopos/tablenames/".self::TABLE_APPLICATIONS, $row[0], 'default', 0);
                }
                if (strtolower($row[0])==self::TABLE_LOCATIONS){
                    $config->saveConfig("asulpuntounicentaopos/tablenames/".self::TABLE_LOCATIONS, $row[0], 'default', 0);
                }
                if (strtolower($row[0])==self::TABLE_STOCKCURRENT){
                    $config->saveConfig("asulpuntounicentaopos/tablenames/".self::TABLE_STOCKCURRENT, $row[0], 'default', 0);
                }
            }
        }else{
            Mage::log(__METHOD__.'SHOW TABLES: IS EMPTY ',null,"asulpunto_unicentaopos.log");
        }
        Mage::app()->getConfig()->reinit();//reinit settings
        if ($hasTables){
            if ($this->_checkApplication()) return 'OK';
        }


        $error['url']=Mage::getStoreConfig('asulpuntounicentaopos/unicentaconfig/url');
        $error['login']=Mage::getStoreConfig('asulpuntounicentaopos/unicentaconfig/login');
        $error['password']=Mage::getStoreConfig('asulpuntounicentaopos/unicentaconfig/password');
        $error['name']=Mage::getStoreConfig('asulpuntounicentaopos/unicentaconfig/dbname');

        return json_encode($error);
    }

    private function _checkApplication(){
        $applicationsTable=Mage::Helper('unicentaopos')->getTableName(self::TABLE_APPLICATIONS);
        $rows=Mage::Helper('unicentaopos')->doQuery("select * from `$applicationsTable`");
        if (!is_null($rows)){
            foreach  ($rows as $row){
                if ($row['ID']=='unicentaopos')
                return true;
            }
        }
        Mage::log(__METHOD__.'Test Connection Failed',null,"asulpunto_unicentaopos.log");
        return false;
    }


    public function cronProducts($mode){

            $ps=Mage::getModel('unicentaopos/productstock');
            $ps->loadProductHash();//load a hash of history
            $ps->noImageProducts();//load products without images so that if change is detected we load them.

            if (is_a($mode,'Mage_Cron_Model_Schedule')){
                $ps->configSim();
            }else{
                $ps->configSim($mode);
            }
            $db=Mage::Helper('unicentaopos')->getUnicentaOposConnection();
            $productsTable=Mage::Helper('unicentaopos')->getTableName(self::TABLE_PRODUCTS);
            if (is_null($db))return false;
            $sql="select * from `$productsTable`";
            $rows=$db->query($sql);
            if (is_null($rows)) return false;

            foreach  ($rows as $row){
                $ps->getUnicentaProducts($row);
                $ps->doImage($row);
            }
            $ps->updateMagentoProducts();
        return true;
    }

    public function cronStock(){
        try{
            $ps=Mage::getModel('unicentaopos/productstock');
            $ps->loadStock();
            $loc=Mage::getStoreConfig('asulpuntounicentaopos/tools/location');
            $db=Mage::Helper('unicentaopos')->getUnicentaOposConnection();
            if (is_null($db)) return false;
            $stockcurrentTable=Mage::Helper('unicentaopos')->getTableName(self::TABLE_STOCKCURRENT);
            $productsTable=Mage::Helper('unicentaopos')->getTableName(self::TABLE_PRODUCTS);

            $sql="select b.REFERENCE as REFERENCE,a.UNITS as UNITS from `$stockcurrentTable` a , `$productsTable` b where a.PRODUCT=b.ID and location='$loc'";

            $rows=$db->query($sql);
            foreach  ($rows as $row){
                $ps->getUnicentaStock($row);
            }
            $ps->updateMagentoStock();
        }catch(Exception $e){
            Mage::log(__METHOD__.$e->getMessage(),null,"asulpunto_unicentaopos.log");
            return false;
        }
        return true;
    }


    private function _updateUnicentaStock(){
        $loc=Mage::getStoreConfig('asulpuntounicentaopos/tools/location');
        $col=Mage::getModel('unicentaopos/unicentaoposorderitem')->getCollection()->addFilter('stockupdated',1);
        $stockcurrentTable=Mage::Helper('unicentaopos')->getTableName(self::TABLE_STOCKCURRENT);
        $productsTable=Mage::Helper('unicentaopos')->getTableName(self::TABLE_PRODUCTS);

        try{
            $db=Mage::Helper('unicentaopos')->getUnicentaOposConnection();
            foreach($col as $item){
                $sql="UPDATE `$stockcurrentTable` set UNITS=UNITS-{$item->getQuantity()} where PRODUCT=(SELECT ID FROM $productsTable WHERE REFERENCE='{$item->getSku()}') and location='$loc'";
                $res=$db->query($sql);
                $item->setStockupdated(0);
                $item->save();
            }
        }catch(Exception $e){
            Mage::log(__METHOD__.$e->getMessage(),null,"asulpunto_unicentaopos.log");

        }
    }




    public function cronOrders(){

        $maxorder=$this->getMaxOrderId();
        $status=Mage::getStoreConfig('asulpuntounicentaopos/tools/orderstatus');
        $resource = Mage::getSingleton('core/resource');
        $readCon=$resource->getConnection('core_read');

        $salesTable =   $resource->getTableName('sales/order');
        $aoiTable=      $resource->getTableName('unicentaopos/unicentaoposorderitem');

        $sql="select entity_id from $salesTable a where
              status = '$status'
              and a.entity_id > $maxorder
              and   a.entity_id not in
                  (select magento_order_id from $aoiTable b where b.magento_order_id=a.entity_id)";

        $res=$readCon->query($sql);
        $so= Mage::getModel('unicentaopos/orders');
        foreach($res as $row){
            $order=Mage::getModel('sales/order')->load($row['entity_id']);
            $so->writeStockOrder($order);
            Mage::dispatchEvent('asulpunto_unicentaoposapi_post_order',array('order'=>$order));
        }
        $this->_updateUnicentaStock();
    }

    public function getMaxOrderId(){
        $orderNo=Mage::getStoreConfig('asulpuntounicentaopos/advanced/orderno');
        if (empty($orderNo)){
            $orderNo=0;
        }else{
            $col=Mage::getModel('sales/order')->getCollection()->addFilter('increment_id',$orderNo);
            $row=$col->getFirstItem();
            $orderNo=$row->getId();
        }
        return $orderNo;
    }

    public function getLocations(){
        try{
            $ARR=array();
            $locationsTable=Mage::Helper('unicentaopos')->getTableName(self::TABLE_LOCATIONS);
            $rows=Mage::Helper('unicentaopos')->doQuery("select * from `$locationsTable`");
            if (!is_null($rows)){
                foreach  ($rows as $row){
                    $ARR[$row['ID']]=$row['NAME'];
                }
            }
        }catch(Exception $e){
            Mage::log(__METHOD__.$e->getMessage(),null,"asulpunto_unicentaopos.log");
        }
        return $ARR;
    }



}
