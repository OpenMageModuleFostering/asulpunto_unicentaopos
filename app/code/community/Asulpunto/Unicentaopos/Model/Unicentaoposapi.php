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
    private $_UPPHash=array();
    private $_UPS=array();
    private $_NoImageProducts=array();

    public function checkActivate(){
        try{
            $rows=Mage::Helper('unicentaopos')->doQuery("select * from `APPLICATIONS`");
            if (!is_null($rows)){
               foreach  ($rows as $row){
                    if ($row['ID']=='unicentaopos')
                        return 'OK';
                    }
            }
        }catch(Exception $e){
            Mage::log(__METHOD__.$e->getMessage(),null,"asulpunto_unicentaopos.log");
        }
        $error['url']=Mage::getStoreConfig('asulpuntounicentaopos/unicentaconfig/url');
        $error['login']=Mage::getStoreConfig('asulpuntounicentaopos/unicentaconfig/login');
        $error['password']=Mage::getStoreConfig('asulpuntounicentaopos/unicentaconfig/password');
        $error['name']=Mage::getStoreConfig('asulpuntounicentaopos/unicentaconfig/dbname');
        return json_encode($error);
    }

    public function cronProducts(){
        try{
            $this->_loadProductHash();//load a hash of history
            $this->_noImageProducts();//load products without images so that if change is detected we load them.

            $db=Mage::Helper('unicentaopos')->getUnicentaOposConnection();
            $sql="select * from `PRODUCTS`";
            $rows=$db->query($sql);

            foreach  ($rows as $row){
                $this->_getUnicentaProducts($row);
                $this->_doImage($row);
            }
            $this->updateMagentoProducts();
        }catch(Exception $e){
            Mage::log(__METHOD__.$e->getMessage(),null,"asulpunto_unicentaopos.log");
        }
    }

    public function cronStock(){
        try{
            $this->_loadStock();
            $loc=Mage::getStoreConfig('asulpuntounicentaopos/tools/location');
            $db=Mage::Helper('unicentaopos')->getUnicentaOposConnection();
            $sql="select b.REFERENCE as REFERENCE,a.UNITS as UNITS from `STOCKCURRENT` a , `PRODUCTS` b where a.PRODUCT=b.ID and location='$loc'";
            $rows=$db->query($sql);
            foreach  ($rows as $row){
                $this->_getUnicentaStock($row);
            }
            $this->updateMagentoStock();
        }catch(Exception $e){
            Mage::log(__METHOD__.$e->getMessage(),null,"asulpunto_unicentaopos.log");
        }
    }

    private function _noImageProducts(){
        $collection=Mage::getModel('unicentaopos/unicentaoposproduct')->getCollection();
        $collection->addFieldToFilter('image', array('null' => true));
        foreach ($collection as $item){
            $this->_NoImageProducts[$item->getSku()]=1;
        }
    }

    private function _doImage($row){
        if (empty($row['IMAGE'])) return true;
        if (array_key_exists($row['REFERENCE'],$this->_NoImageProducts)){
            $mageRow=$this->_getRowByCode($row['REFERENCE']);
            $mageRow->setImage($row['IMAGE']);
            $mageRow->setInfoupdated(2);
            $mageRow->save();
        }
    }



    private function _getUnicentaProducts($row){
            $updateNeeded=false;
            $state=0;

        $md5=md5(
                    $row['CODE'].'|'.
                    $row['NAME'].'|'.
                    $row['ATTRIBUTES'].'|'.
                    $row['PRICEBUY'].'|'.
                    $row['PRICESELL']
                );

        if (array_key_exists($row['REFERENCE'],$this->_UPPHash)){
        //First Check Hashkey
            if ($this->_UPPHash[$row['REFERENCE']]!=$md5){
                $updateNeeded=true;
                $state=1; //if 1 do not do image
                $mageRow=$this->_getRowByCode($row['REFERENCE']);
            }
        }else{
            $updateNeeded=true;
            $state=2;    //if 2 also create image
            $mageRow=Mage::getModel('unicentaopos/unicentaoposproduct');
        }

        if ($updateNeeded){
            $mageRow->setName($row['NAME']);
            $mageRow->setSku($row['REFERENCE']);
            $mageRow->setBarcode($row['CODE']);
            $mageRow->setLongdesc($row['ATTRIBUTES']);
            $mageRow->setCost($row['PRICEBUY']);
            $mageRow->setPrice($row['PRICESELL']);
            $mageRow->setStock($row['STOCKVOLUME']);
            $mageRow->setImage($row['IMAGE']);
            $mageRow->setInfoupdated($state);
            $mageRow->save();
        }
    }

    private function _getUnicentaStock($row){
        if (array_key_exists($row['REFERENCE'],$this->_UPS)){
            if ($this->_UPS[$row['REFERENCE']]!=$row['UNITS']){
                $mageRow=$this->_getRowByCode($row['REFERENCE']);
                if ($mageRow){
                    $mageRow->setStock($row['UNITS']);
                    $mageRow->setStockupdated(1);
                    $mageRow->save();
                }
            }
        }
    }

    public function cronOrders(){

        $maxorder=$this->getMaxOrderId();
        $status=Mage::getStoreConfig('asulpuntounicentaopos/tools/orderstatus');
        $readCon=Mage::getSingleton('core/resource')->getConnection('core_read');

        $sql="select entity_id from sales_flat_order a where
              status = '$status'
              and a.entity_id > $maxorder
              and   a.entity_id not in
                  (select magento_order_id from asulpunto_unicentaopos_order_item b where b.magento_order_id=a.entity_id)";

        $res=$readCon->query($sql);

        foreach($res as $row){
            $order=Mage::getModel('sales/order')->load($row['entity_id']);
            $this->_writeStockOrder($order);
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

    private function _writeStockOrder($order){
        $items=$order->getAllItems();
        foreach ($items as $item){
            if (!$this->_getRowByCode($item->getSku()))continue;
            $oitem=Mage::getModel('unicentaopos/unicentaoposorderitem');
            $oitem->setMagentoOrderId($order->getId());
            $oitem->setMagentoIncrementId($order->getIncrementId());
            $oitem->setSku($item->getSku());
            $oitem->setQuantity($item->getQtyOrdered());
            $oitem->setAction('-');
            $oitem->setStockupdated(1);
            $oitem->save();
        }
    }

    private function _updateUnicentaStock(){
        $loc=Mage::getStoreConfig('asulpuntounicentaopos/tools/location');
        $col=Mage::getModel('unicentaopos/unicentaoposorderitem')->getCollection()->addFilter('stockupdated',1);

        try{
            $db=Mage::Helper('unicentaopos')->getUnicentaOposConnection();
            foreach($col as $item){
                $sql="UPDATE `STOCKCURRENT` set UNITS=UNITS-{$item->getQuantity()} where PRODUCT=(SELECT ID FROM PRODUCTS WHERE REFERENCE='{$item->getSku()}') and location='$loc'";
                $res=$db->query($sql);
                $item->setStockupdated(0);
                $item->save();
            }
        }catch(Exception $e){
            Mage::log(__METHOD__.$e->getMessage(),null,"asulpunto_unicentaopos.log");
        }
    }

    private function _getRowByCode($skucode){

        $col=Mage::getModel('unicentaopos/unicentaoposproduct')->getCollection()->addFilter('sku',$skucode);
        if ($col->count()>0){
            $item= $col->getFirstItem();
             return $item;
        }

        return false; //not found
    }

    public  function updateMagentoProducts(){
        $col=Mage::getModel('unicentaopos/unicentaoposproduct')->getCollection()->addFieldToFilter('infoupdated',array('in' => array('1', '2')));
        foreach ($col as $row){
            $this->_saveMagentoProduct($row);
        }
    }

    public  function updateMagentoStock(){
        $col=Mage::getModel('unicentaopos/unicentaoposproduct')->getCollection()->addFilter('stockupdated',1);
        foreach ($col as $row){
            if ($row->getMagentoProductId()){
                $product = Mage::getModel('catalog/product')->load($row->getMagentoProductId());
                if ($product->getId()){
                    $sData=$product->getStockData();
                    $sData['qty']=$row->getStock();
                    if ($row->getStock()>0) {
                        $sData['is_in_stock']=1;
                    } else {
                        $sData['is_in_stock']=0;
                    }
                    $product->setStockData($sData);
                    $product->save();
                }
            }
            $row->setStockupdated(0);
            $row->save();
        }
    }


    private function _saveMagentoProduct($row){
        try
        {
            $ptype=$this->getProductType();
            $newProduct=false;
            $product = Mage::getModel('catalog/product');
            if ($row->getMagentoProductId()){
                $product = $product->load($row->getMagentoProductId());
            }

            if (!$product->getId()){
                $product->setSku($row->getSku());
                $product->setTypeId('simple');
                $product->setAttributeSetId($ptype);
                $product->setTaxClassId(1);
                $product->setWeight(0.0);
                $sData['qty']=$row->getStock();
                if ($row->getStock()>0) $sData['is_in_stock']=1;
                $product->setStockData($sData);
                $newProduct=true;
            }

            //$product->setWebsiteIds(array(1));

            $product->setName($row->getName());
            $product->setDescription($row->getLongdesc());
            $product->setShortDescription($row->getName());
            $sData=$product->getStockData($sData);
            $product->setCostPrice($row->getCost());
            $product->setPrice($row->getPrice());
            if ($row->getInfoupdated()==2) $product=Mage::getModel('unicentaopos/unicentaoposproductapi')->setProductImage($row->getImage(),$product);
            $product->save();

            if ($newProduct) Mage::getModel('catalog/product_status')->updateProductStatus($product->getId(), 0, Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
            if ($row->getInfoupdated()==2) Mage::getModel('unicentaopos/unicentaoposproductapi')->setProductImage($row->getImage(),$product);
            $row->setInfoupdated(0);
            $row->setMagentoProductId($product->getId());
            $row->save();
            return $product->getId();
        }
        catch (exception $e)
        {
            Mage::log(__METHOD__.$e->getMessage(),null,"asulpunto_unicentaopos.log");
        }
        unset($row);
        return 0;
    }

    private function _loadProductHash(){
        $cols=Mage::getModel('unicentaopos/unicentaoposproduct')->getCollection();
        foreach ($cols as $item){
            $this->_UPPHash[$item->getSku()]=md5(
                $item->getBarcode().'|'.
                    $item->getName().'|'.
                    $item->getLongdesc().'|'.
                    $item->getCost().'|'.
                    $item->getPrice()
            );
        }
        unset($cols);
    }

    private function _loadStock(){
        $cols=Mage::getModel('unicentaopos/unicentaoposproduct')->getCollection();
        foreach ($cols as $item){
            $this->_UPS[$item->getSku()]=$item->getStock();
        }
        unset($cols);
    }

    private function getProductType(){
            return Mage::getStoreConfig('asulpuntounicentaopos/tools/product_type');
    }

    public function getLocations(){
        try{
            $ARR=array();
            $rows=Mage::Helper('unicentaopos')->doQuery("select * from `LOCATIONS`");
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
