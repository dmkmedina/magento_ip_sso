<?php

class Developmint_Ipsconnectsso_Model_Resource_Ipsmagentocustomermap extends Mage_Core_Model_Resource_Db_Abstract{
    protected function _construct()
    {
        $this->_init('ipsconnectsso/ipsmagentocustomermap', 'map_id');
    }
}