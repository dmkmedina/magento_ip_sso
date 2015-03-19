<?php

class Developmint_Ipsconnectsso_Block_Incorrect extends Mage_Core_Block_Template
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('developmint/ipsconnectsso/incorrect.phtml');

        Mage::app()->getFrontController()->getAction()->getLayout()->getBlock('root')->setHeaderTitle(Mage::helper('sales')->__('Incorrect Login'));
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();


        return $this;
    }


    public function getPostActionUrl() {
        return $this->getUrl('customer/login/process');
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
    /*public function getCreateAccountUrl()
    {
        $url = $this->getData('create_account_url');
        if (is_null($url)) {
            $url = $this->helper('customer')->getRegisterUrl();
        }
        return $url;
    }*/

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