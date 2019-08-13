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

class Asulpunto_Unicentaopos_Model_Unicentaoposproductapi extends Mage_Core_Model_Abstract
{
    private $_imageFolder='';

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

}
