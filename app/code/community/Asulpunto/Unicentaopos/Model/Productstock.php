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

class Asulpunto_Unicentaopos_Model_Productstock extends Mage_Core_Model_Abstract
{
    private $_imageFolder='';
    private $_UPPHash=array();
    private $_UPS=array();
    private $_NoImageProducts=array();

    private $_cnf_longdescription='';
    private $_cnf_shortdescription='';
    private $_cnf_price='';
    private $_cnf_name='';
    private $_cnf_barcode_attribute='';

    public function __construct() {
        $this->_cnf_longdescription=  Mage::getStoreConfig('asulpuntounicentaopos/attributes/longdescription');
        $this->_cnf_shortdescription= Mage::getStoreConfig('asulpuntounicentaopos/attributes/shortdescription');
        $this->_cnf_price=            Mage::getStoreConfig('asulpuntounicentaopos/attributes/price');
        $this->_cnf_name=             Mage::getStoreConfig('asulpuntounicentaopos/attributes/name');
        $this->_cnf_barcode_attribute=Mage::getStoreConfig('asulpuntounicentaopos/attributes/barcode');
        parent::_construct();
    }


    public function setProductImage($image,$product){
        if (empty($image)) return $product;

        $col=$product->getMediaGalleryImages();
        if (!is_null($col) && $col->getSize()>0) return $product; //We do not overwrite images. Never.
        $name=trim($product->getSku());
        $imageFolder=$this->getImageFolder();
        $path=$imageFolder.DIRECTORY_SEPARATOR.$name.'.png';
        $res=file_put_contents($path,$image);
        if ($res===false){
            Mage::log(__METHOD__."Cannot create image for $name in folder: $imageFolder ",null,"asulpunto_unicentaopos.log");
        }else{
            $product->addImageToMediaGallery($path, array('thumbnail','small_image','image'),true,false);
        }
        unset($image);
        return $product;
    }

    private function getImageFolder(){
        if (empty($this->_imageFolder)){
            $media=Mage::getBaseDir('media');
            $unicenta=$media.DIRECTORY_SEPARATOR.'unicenta';
             if (!file_exists($unicenta)){
                $res=mkdir($unicenta,0777);
                if (!$res) $this->_imageFolder=Mage::getBaseDir('media');
            }
            $this->_imageFolder=$unicenta;
        }
        return $this->_imageFolder;
    }

    public function saveMagentoProduct($row){
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
            $product=$this->_transferField($product,'name',$row->getName(),$this->_cnf_name,$newProduct);
            $product=$this->_transferField($product,'description',$row->getLongdesc(),$this->_cnf_longdescription,$newProduct);
            $product=$this->_transferField($product,'short_description',$row->getName(),$this->_cnf_shortdescription,$newProduct);
            $product=$this->_transferField($product,'price',$row->getPrice(),$this->_cnf_price,$newProduct);
            $product=$this->_transferField($product,$this->_cnf_barcode_attribute,$row->getBarcode(),Asulpunto_Unicentaopos_Model_Source_Transfer::TRANSFER_ALWAYS,$newProduct);

            //$product->setName($row->getName());
            //$product->setDescription();
            //$product->setShortDescription($row->getName());
            //$product->setPrice($row->getPrice());
            if ($row->getInfoupdated()==2) $product=$this->setProductImage($row->getImage(),$product);
            $product->save();

            if ($newProduct) Mage::getModel('catalog/product_status')->updateProductStatus($product->getId(), 0, Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
            if ($row->getInfoupdated()==2) $this->setProductImage($row->getImage(),$product);
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

    public function loadProductHash(){
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

    public  function updateMagentoProducts(){
        $col=Mage::getModel('unicentaopos/unicentaoposproduct')->getCollection()->addFieldToFilter('infoupdated',array('in' => array('1', '2')));
        foreach ($col as $row){
            $this->saveMagentoProduct($row);
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

    public function loadStock(){
        $cols=Mage::getModel('unicentaopos/unicentaoposproduct')->getCollection();
        foreach ($cols as $item){
            $this->_UPS[$item->getSku()]=$item->getStock();
        }
        unset($cols);
    }



    private function getProductType(){
        return Mage::getStoreConfig('asulpuntounicentaopos/tools/product_type');
    }

    public function getUnicentaProducts($row){
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
                $mageRow=Mage::Helper('unicentaopos')->getRowByCode($row['REFERENCE']);
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

    public function getUnicentaStock($row){
        if (array_key_exists($row['REFERENCE'],$this->_UPS)){
            if ($this->_UPS[$row['REFERENCE']]!=$row['UNITS']){
                $mageRow=Mage::Helper('unicentaopos')->getRowByCode($row['REFERENCE']);
                if ($mageRow){
                    $mageRow->setStock($row['UNITS']);
                    $mageRow->setStockupdated(1);
                    $mageRow->save();
                }
            }
        }
    }

    public  function noImageProducts(){
        $collection=Mage::getModel('unicentaopos/unicentaoposproduct')->getCollection();
        $collection->addFieldToFilter('image', array('null' => true));
        foreach ($collection as $item){
            $this->_NoImageProducts[$item->getSku()]=1;
        }
    }

    public function doImage($row){
        if (empty($row['IMAGE'])) return true;
        if (array_key_exists($row['REFERENCE'],$this->_NoImageProducts)){
            $mageRow=Mage::Helper('unicentaopos')->getRowByCode($row['REFERENCE']);
            $mageRow->setImage($row['IMAGE']);
            $mageRow->setInfoupdated(2);
            $mageRow->save();
        }
    }



    private function _transferField($object,$name,$value,$config,$newMode){
        if ($newMode){
            if ($config==Asulpunto_Unicentaopos_Model_Source_Transfer::TRANSFER_ALWAYS || $config==Asulpunto_Unicentaopos_Model_Source_Transfer::TRANSFER_CREATE){
                $arr=explode('_',$name);
                $name='';
                foreach($arr as $s){
                    $name=$name.ucfirst($name);
                }
                $f="set$name";
                $object->$f($value);
            }
        }else{ //This is update mode
            if ($config==Asulpunto_Unicentaopos_Model_Source_Transfer::TRANSFER_ALWAYS ){
                $name=ucfirst($name);
                $f="set$name";
                $object->$f($value);
            }
        }
        return $object;
    }


    public function configSim($cfg){
        $this->_cnf_longdescription=$cfg;
        $this->_cnf_shortdescription=$cfg;
        $this->_cnf_price=$cfg;
        $this->_cnf_name=$cfg;
    }

}
