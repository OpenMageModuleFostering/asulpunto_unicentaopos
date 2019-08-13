<?php
class Asulpunto_Unicentaopos_Model_Source_Location
{
    protected $_options;

    public function toOptionArray()
    {
        $locations=Mage::getModel('unicentaopos/unicentaoposapi')->getLocations();
        if (count($locations)==0){
            return array(
                array('value' => '0', 'label' => 'Connection down.'),
            );
        }else{
            $list= array();
            foreach ( $locations as $key => $value)
                $list[]=array('value' => $key, 'label' =>$value );
        }
        return $list;
    }
}