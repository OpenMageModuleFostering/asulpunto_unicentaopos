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

$installer = $this;
$installer->startSetup();

$col=Mage::getModel('sales/order')  ->getCollection()
                                    ->setOrder('entity_id')
                                    ->setPage(1, 1);


$item=$col->getFirstItem();
$docNo='';
if ($item->getId()){
    $docNo=$item->getIncrementId();
}

$groups['advanced']['fields']['orderno']['value'] = $docNo;

/* Save config values */
$data = Mage::getModel('adminhtml/config_data')
    ->setSection('asulpuntounicentaopos')
    ->setWebsite(0)
    ->setGroups($groups)
    ->save();
$installer->endSetup();
