<?php
class Asulpunto_Unicentaopos_Model_Source_Transfer
{
    const TRANSFER_ALWAYS='always';
    const TRANSFER_CREATE='create';
    const TRANSFER_NEVER='never';

    protected $_options;

    public function toOptionArray()
    {

        $list= array();
        $list[]=array('value' => self::TRANSFER_ALWAYS, 'label' =>'Create & Update' );
        $list[]=array('value' => self::TRANSFER_CREATE, 'label' =>'Create Only' );
        $list[]=array('value' => self::TRANSFER_NEVER, 'label'  =>'Never transfer' );
        return $list;
    }
}