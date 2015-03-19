<?php

class Developmint_Ipsconnectsso_Block_Login extends Mage_Core_Block_Template
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('developmint/ipsconnectsso/login.phtml');

        /*$customerMap = Mage::getModel('ipsconnectsso/ipsmagentocustomermap');
        echo get_class($customerMap) . '<br/>';
        $customerMap->setConnect_id(1);
        $customerMap->setCustomer_id(2);
        $customerMap->save();*/


        /*$result_set = Mage::helper('ipsconnectsso')->getIPSLoggedInUser();
        print_r($result_set);

        $user_id = Mage::helper('ipsconnectsso')->getCustomerIdFromEmail($result_set->connect_email);
        //echo '<br/>USERID: '.$user_id;

        Mage::helper('ipsconnectsso')->logCustomerIn($user_id);*/

        Mage::app()->getFrontController()->getAction()->getLayout()->getBlock('root')->setHeaderTitle(Mage::helper('sales')->__('Customer SSO Login'));
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();


        return $this;
    }

    //public function getViewUrl($order)
    //{
        //return $this->getUrl('*/*/view', array('order_id' => $order->getId()));
    //}

    //public function getRemoveUrl($cc)
    //{
    //    return $this->getUrl('*/*/remove', array('cc_id' => $cc->getId()));
    //}

    //public function getBackUrl()
    //{
    //    return $this->getUrl('customer/account/');
    //}

    public function getPostActionUrl() {
        return $this->getUrl('*/login/process');
    }

    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }

    public function getMessages() {
        $output = '';
        $session = $this->_getSession();
        $items = $session->getMessages(true)->getItems();

        $output .= '<ul class="messages">';

        foreach($items as $item) {
            //print_r($item);
            $output .= '<li class="'.$item->getType().'-msg"><ul><li><span>';
            $output .= $item->getText();
            $output .= '</span></li></ul></li></ul>';
        }

        $output .= '</ul>';
        return $output;
    }

    /**
     * Retrieve create new account url
     *
     * @return string
     */
    public function getCreateAccountUrl()
    {
        $url = $this->getData('create_account_url');
        if (is_null($url)) {
            $url = $this->helper('customer')->getRegisterUrl();
        }
        return $url;
    }

    /**
     * Retrieve password forgotten url
     *
     * @return string
     */
    public function getForgotPasswordUrl()
    {
        return $this->helper('customer')->getForgotPasswordUrl();
    }

}