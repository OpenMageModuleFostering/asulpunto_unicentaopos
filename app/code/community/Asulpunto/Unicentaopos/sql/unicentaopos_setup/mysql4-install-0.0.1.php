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

$installer->run("
CREATE TABLE
    {$installer->getTable('unicentaopos/unicentaoposproduct')} (
    entity_id int(11) unsigned NOT NULL AUTO_INCREMENT,
    sku varchar(64) DEFAULT NULL,
    magento_product_id int(11) DEFAULT NULL,
    barcode varchar(128) DEFAULT NULL,
    name varchar(255) DEFAULT NULL,
    longdesc varchar(2000) DEFAULT NULL,
    cost double DEFAULT NULL,
    price double DEFAULT NULL,
    stock double DEFAULT NULL,
    data varchar(2000) DEFAULT NULL,
    infoupdated int(11) DEFAULT NULL,
    stockupdated int(11) DEFAULT NULL,
    lastupdate timestamp NULL DEFAULT NULL,
  PRIMARY KEY (entity_id)
) ");

$installer->run("

    CREATE  TABLE {$installer->getTable('unicentaopos/unicentaoposorderitem')} (
      entity_id INT(11) UNSIGNED NOT NULL  AUTO_INCREMENT ,
      magento_order_id INT(11) NULL ,
      magento_increment_id VARCHAR(50) NULL ,
      sku VARCHAR(64) NULL ,
      action VARCHAR(1) NULL ,
      quantity DOUBLE NULL ,
      stockupdated INT(11) NULL ,
      lastupdate TIMESTAMP NULL ,
      PRIMARY KEY (entity_id) )");


$installer->endSetup();
