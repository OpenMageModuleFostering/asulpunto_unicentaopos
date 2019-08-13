<?php
class Asulpunto_Unicentaopos_Model_Source_Attributes
{
    protected $_options;

    public function toOptionArray()
    {
        $attributes = Mage::getModel('catalog/product')->getAttributes();
        $attributeArray = array();

        $attributeArray[] = array(
            'label' => '-- Do Not Transfer --',
            'value' => ''
        );

        foreach($attributes as $a){
            foreach ($a->getEntityType()->getAttributeCodes() as $attributeName) {

                //$attributeArray[$attributeName] = $attributeName;
                $attributeArray[] = array(
                    'label' => $attributeName,
                    'value' => $attributeName
                );
            }
            break;
        }
        return $attributeArray;
    }
}