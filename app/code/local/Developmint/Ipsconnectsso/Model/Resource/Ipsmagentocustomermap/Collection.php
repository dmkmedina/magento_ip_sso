<?php
class Developmint_Ipsconnectsso_Model_Resource_Ipsmagentocustomermap_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract {
    protected function _construct()
    {
        $this->_init('ipsconnectsso/ipsmagentocustomermap');
    }

    public function filterByConnectId($connect_id) {
        $items = $this->addFilter('connect_id', $connect_id);

        foreach ($items as $item) {
            return $item;
        }

        return false;
    }

    public function getCustomerIdFromConnectId($connect_id) {
        $items = $this->addFilter('connect_id', $connect_id);

        foreach ($items as $item) {
            return $item->getCustomer_id();
        }

        return -1;
    }

    public function getConnectIdFromCustomerId($customer_id) {
        $items = $this->addFilter('customer_id', $customer_id);

        foreach ($items as $item) {
            return $item->getConnect_id();
        }

        return -1;
    }
}