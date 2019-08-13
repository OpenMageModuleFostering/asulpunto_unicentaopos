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



class Asulpunto_Unicentaopos_Helper_Data extends Mage_Core_Helper_Abstract
{

    public function getUnicentaOposConnection(){
        $url=Mage::getStoreConfig('asulpuntounicentaopos/unicentaconfig/url');
        $login=Mage::getStoreConfig('asulpuntounicentaopos/unicentaconfig/login');
        $password=Mage::getStoreConfig('asulpuntounicentaopos/unicentaconfig/password');
        $name=Mage::getStoreConfig('asulpuntounicentaopos/unicentaconfig/dbname');
        try {
            $db = new PDO("mysql:host={$url};dbname={$name}",
                $login,
                $password
            );
        } catch (Exception $e){
            Mage::log(__METHOD__." ERROR Getting Connetion URL:$url DBNAME:$name Login:$login Password:$password",null,"asulpunto_unicentaopos.log");
            Mage::log(__METHOD__.$e->getMessage(),null,"asulpunto_unicentaopos.log");
            return null;
        }
         return $db;
    }

    public function doQuery($sql){
        $db=$this->getUnicentaOposConnection();
        if (is_null($db)) return null;
        $rows=$db->query($sql);
        return $rows;
    }

    public function doExecute($sql){
        $db=$this->getUnicentaOposConnection();
        if (is_null($db)) return null;
        $res=$db->exec($sql);
        return $res;
    }



}