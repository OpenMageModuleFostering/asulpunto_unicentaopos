<?php
class Asulpunto_Unicentaopos_Model_Source_Producttypelist
{
    protected $_options;

    public function toOptionArray()
    {
        $prodType = Mage::getModel('eav/entity_type')->loadByCode('catalog_product');
        $prodAttributeSet = Mage::getModel('eav/entity_attribute_set')->getCollection()->addFilter('entity_type_id',$prodType->getId());

        $list= array(
            array('value' => '', 'label' => ''),
        );
        foreach ( $prodAttributeSet as $set)
        $list[]=array('value' => $set->getId(), 'label' =>$set->getAttributeSetName() );

        return $list;
    }
}