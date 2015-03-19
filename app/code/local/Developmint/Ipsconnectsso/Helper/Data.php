<?php
    /**
     * Magento
     *
     * NOTICE OF LICENSE
     *
     * This source file is subject to the Open Software License (OSL 3.0)
     * that is bundled with this package in the file LICENSE.txt.
     * It is also available through the world-wide-web at this URL:
     * http://opensource.org/licenses/osl-3.0.php
     * If you did not receive a copy of the license and are unable to
     * obtain it through the world-wide-web, please send an email
     * to license@magentocommerce.com so we can send you a copy immediately.
     *
     * DISCLAIMER
     *
     * Do not edit or add to this file if you wish to upgrade Magento to newer
     * versions in the future. If you wish to customize Magento for your
     * needs please refer to http://www.magentocommerce.com for more information.
     *
     * @category    Mage
     * @package     Mage_AdminNotification
     * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
     * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
     */


    /**
     * Ipsconnectsso Data helper
     *
     * @category   Mage
     * @package    Mage_AdminNotification
     * @author      Magento Core Team <core@magentocommerce.com>
     */
class Developmint_Ipsconnectsso_Helper_Data extends Mage_Core_Helper_Abstract {
    const MASTER_URL = 'http://www.developmint.org/messageboard/interface/ipsconnect/ipsconnect.php';
    const CONNECT_KEY = 'd24d30f72f3feea0b07e9b9bb9a4ac3b';
    const COOKIE_PREFIX = 'ipsconnect_';

    //connect status consts
    //the login/check was successful
    const CONNECT_STATUS_SUCCESS = 'SUCCESS';
    //The login/check was successful, but the account hasn't been validated yet. Do not process login.
    const CONNECT_STATUS_VALIDATING = 'VALIDATING';
    //login/check was unsuccessful
    const CONNECT_STATUS_FAIL = 'FAIL';
    //the password was incorrect
    const CONNECT_STATUS_WRONG_AUTH = 'WRONG_AUTH';
    //could not locate a user based on the ID given.
    const CONNECT_STATUS_NO_USER = 'NO_USER';
    //you did not provide all the required data
    const CONNECT_STATUS_MISSING_DATA = 'MISSING_DATA';
    //Account has been locked by brute-force prevention
    const CONNECT_STATUS_ACCOUNT_LOCKED = 'ACCOUNT_LOCKED';
    const CONNECT_STATUS_EMAIL_IN_USE = 'EMAIL_IN_USE';
    const CONNECT_STATUS_BAD_KEY = 'BAD_KEY';

    const ID_TYPE_USERNAME = 'username';
    const ID_TYPE_EMAIL = 'email';
    const ID_TYPE_CONNECT_ID = 'id';

    public function getCookieName() {
        return self::COOKIE_PREFIX . md5(self::MASTER_URL);
    }

    public function getCookieValue() {
        return Mage::getModel('core/cookie')->get(self::COOKIE_PREFIX . md5(self::MASTER_URL));
    }

    public function setCookieValue() {
        //setcookie(self::COOKIE_PREFIX . md5(self::MASTER_URL),'1',time() + (86400 * 7));
        Mage::getModel('core/cookie')->set(self::COOKIE_PREFIX . md5(self::MASTER_URL), '1', null, null, '.developmint.org');
        //Mage::getModel('core/cookie')->delete(self::COOKIE_PREFIX . md5(self::MASTER_URL));
    }

    public function isUserLoggedInLocally() {
        return Mage::helper('customer')->isLoggedIn();
    }

    public function getIPSLoggedInUser() {
        $data = json_encode( $_COOKIE );
        $result = file_get_contents(self::MASTER_URL.'?'.http_build_query(array('act' => 'cookies', 'data' => $data)));

        if ($result === FALSE) {
            return FALSE;
        }else {
            return json_decode($result);
        }
    }

    public function checkForExistingNetworkLogin() {
        $cookie_val = $this->getCookieValue();
        $session = $this->_getSession();

        if ($cookie_val == 0 && $session->isLoggedIn()) {
            //user has explicitly logged out of the master application
            $session->logout();
        }else if ($cookie_val == 1 && !$session->isLoggedIn()) {
            $result_set = $this->getIPSLoggedInUser();

            if ($result_set && $result_set->connect_status == self::CONNECT_STATUS_SUCCESS) {
                //this happens when the user registers on envision, ipb does not correctly set the cookies
                if ($cookie_val == 0) {
                    /*echo 'SETTING COOKIE VALUE<br/>';
                    $this->setCookieValue();
                    echo 'COOKIE VALUE: ' . $this->getCookieValue() . '<br/>';*/
                }

                $customer_id = Mage::getModel('ipsconnectsso/ipsmagentocustomermap')
                    ->getCollection()
                    ->getCustomerIdFromConnectId($result_set->connect_id);

                $customer = Mage::getModel('customer/customer')->load($customer_id);

                if ($customer_id > 0 && $customer->getId()) {
                    $session->setCustomerAsLoggedIn($customer);
                    //$customer = $this->logCustomerIn($customer_id);
                    if ($customer->getEmail() != $result_set->connect_email) {
                        $customer->setEmail($result_set->connect_email);
                        $customer->save();
                    }
                }else {
                    //check by e-mail
                    $customer_id = $this->getCustomerIdFromEmail($result_set->connect_email);
                    if ($customer_id > 0) {
                        $this->addMapEntry($customer_id, $result_set->connect_id);
                        $this->logCustomerIn($customer_id);
                    }else {
                        //need to create the customer
                        $customer_id = $this->createCustomer($result_set->connect_id, $result_set->connect_email, '');
                        $this->logCustomerIn($customer_id);
                    }
                }
            }
        }
    }

    public function getNetworkLogoutUrl() {
        $customer = $this->_getSession();

        $connect_id = Mage::getModel('ipsconnectsso/ipsmagentocustomermap')
            ->getCollection()
            ->getConnectIdFromCustomerId($customer->getId());

        $customer->logout();

        //it should always find a match, but just incase
        if ($connect_id > 0) {
            $encoded_redirect_url = base64_encode(Mage::helper('core/url')->getHomeUrl() . 'customer/account/logoutSuccess/');
            //$encoded_redirect_url = base64_encode($_SERVER['HTTP_ORIGIN'] . $_SERVER['PHP_SELF']);

            return self::MASTER_URL . '?' .
                http_build_query( array( 'act' => 'logout', 'id' => $connect_id,
                    'key' => md5( self::CONNECT_KEY . $connect_id ), 'redirect' => $encoded_redirect_url,
                    'redirectHash' => md5( self::CONNECT_KEY . $encoded_redirect_url), 'noparams' => '1'));
        }else {
            return '';
        }
    }


    public function getCustomerId($connect_id, $connect_email) {
        //first we look at the ips magento map
        $map_entry = Mage::getModel('ipsconnectsso/ipsmagentocustomermap')
            ->getCollection()
            ->filterByConnectId($connect_id);

        $customer_id = $map_entry ? $map_entry->getCustomer_id() : -1;
        $customer = Mage::getModel('customer/customer')->load($customer_id);

        if ($customer_id > 0 && !$customer->getId()) { //customer no longer exists
            $customer_id = -1;
            $map_entry->delete();
        }

        if ($customer_id > 0) { //map entry exists
            $customer = Mage::getModel('customer/customer')->load($customer_id);

            if ($customer->getEmail() != $connect_email) {
                //update the e-mail address if it has changed.
                $customer->setEmail($connect_email);
                $customer->save();
            }
        }else if (($customer_id = $this->getCustomerIdFromEmail($connect_email)) > 0) {
            //add a map entry for this customer
            $this->addMapEntry($customer_id, $connect_id);
        }else {
            //customer does not exist locally, return an error value
            $customer_id = -1;
        }

        return $customer_id;
    }

    public function getCustomerIdFromEmail($email) {
        $customer = Mage::getModel("customer/customer");
        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        $customer->loadByEmail($email); //load customer by email

        if ($customer->getId()) {
            return $customer->getId();
        }else {
            return -1;
        }
    }

    public function getConnectIdFromEmail($email) {
        $connect_id = -1;
        $customer_id = $this->getCustomerIdFromEmail($email);

        if ($customer_id > 0) {
            $connect_id = Mage::getModel('ipsconnectsso/ipsmagentocustomermap')
                ->getCollection()
                ->getConnectIdFromCustomerId($customer_id);
        }

        return $connect_id;
    }

    public function authenticateCustomer($customer_id, $password) {
        $password = $this->ipsPasswordClean($password);
        $customer = Mage::getModel('customer/customer');

        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        $customer->load($customer_id);

        if ($customer->getConfirmation() && $customer->isConfirmationRequired()) {
            //customer has not validated account
            return self::CONNECT_STATUS_VALIDATING;
        }else if (!$customer->validatePassword($password)) {
            return self::CONNECT_STATUS_FAIL;
        }

        return self::CONNECT_STATUS_SUCCESS;
    }

    public function logCustomerInWithEmail($email) {
        $customer_id = $this->getCustomerIdFromEmail($email);
        if ($customer_id > 0) {
            $this->logCustomerIn($customer_id);
            return true;
        }else {
            return false;
        }
    }

    public function logCustomerIn($customerid) {
        $customer = Mage::getModel('customer/customer')->load($customerid);
        $this->_getSession()->setCustomerAsLoggedIn($customer);

        return $customer;
    }

    public function verifyLoginWithNetwork($id, $idType, $password) {
        $password = $this->ipsPasswordClean($password);
        $result = file_get_contents(self::MASTER_URL.'?'.
            http_build_query(array('act' => 'login', 'idType' => $idType, 'id' => $id, 'password' => md5($password))));

        if ($result === FALSE) {
            return FALSE;
        }else {
            return json_decode($result);
        }
    }

    public function getNetworkLoginRequestUrl($id, $idType, $password, $redirect = '') {
        $password = $this->ipsPasswordClean($password);

        if ($redirect == '') {
            $encoded_redirect_url = base64_encode($_SERVER['HTTP_ORIGIN'] . $_SERVER['PHP_SELF']);
        }else {
            $encoded_redirect_url = base64_encode($redirect);
        }


        return self::MASTER_URL . '?' .
            http_build_query( array( 'act' => 'login', 'idType' => $idType, 'id' => $id, 'password' => md5($password),
                'key' => md5( self::CONNECT_KEY . $id ), 'redirect' => $encoded_redirect_url,
                'redirectHash' => md5( self::CONNECT_KEY . $encoded_redirect_url), 'noparams' => '1' ) );

    }

    public function handleNetworkLoginFail($result) {

    }

    public function createCustomer($connect_id, $email, $password) {
        $customer = Mage::getModel('customer/customer');

        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        $customer->setEmail($email);
        //default the first and last name to the e-mail address since we don't
        //have any better option
        $customer->setFirstname('First Name');
        $customer->setLastname('Last Name');
        $customer->setPassword($password == '' ? $customer->generatePassword(10) : $password);

        try {
            $customer->save();
            $customer->setConfirmation(null);
            $customer->save();
        }catch (Exception $ex) {
            return false;
        }

        $this->addMapEntry($customer->getId(), $connect_id);

        return $customer->getId();
    }

    public function updateCustomerPassword($customer_id, $password) {
        $customer = Mage::getModel('customer/customer');

        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        $customer->load($customer_id);
        $customer->setPassword($password);
        $customer->save();
    }

    public function emailIsAvailableWithNetwork($email) {
        $result = file_get_contents(self::MASTER_URL.'?'.
            http_build_query(array('act' => 'check', 'key' => self::CONNECT_KEY, 'email' => $email)));

        if ($result === FALSE) {
            return FALSE;
        }else {
            $result_set = json_decode($result);
            //print_r($result_set);

            return ($result_set->status == self::CONNECT_STATUS_SUCCESS && $result_set->email);
        }
    }

    public function registerCustomerWithNetwork($email, $password, $customer_id, $revalidate_url = '') {
        $password = $this->ipsPasswordClean($password);

        if ($revalidate_url != '') {
            $query = http_build_query(array('act' => 'register', 'key' => self::CONNECT_KEY,
                'email' => $email, 'password' => md5($password), 'revalidateurl' => $revalidate_url));
        }else {
            $query = http_build_query(array('act' => 'register', 'key' => self::CONNECT_KEY,
                'email' => $email, 'password' => md5($password)));
        }

        $result = file_get_contents(self::MASTER_URL.'?'. $query);

        if ($result === FALSE) {
            return false;
        }else {
            $result_set = json_decode($result);

            if ($result_set->status == self::CONNECT_STATUS_SUCCESS) {
                //create a map entry
                $this->addMapEntry($customer_id, $result_set->id);

                return $result_set->id;
            }else {
                return false;
            }
        }
    }

    public function validateCustomerWithNetwork($customer_id) {
        $connect_id = Mage::getModel('ipsconnectsso/ipsmagentocustomermap')
            ->getCollection()
            ->getConnectIdFromCustomerId($customer_id);

        if ($connect_id > 0) {
            $result = file_get_contents(self::MASTER_URL.'?'.
                http_build_query(array('act' => 'validate', 'key' => md5(self::CONNECT_KEY . $connect_id), 'id' => $connect_id)));
        }else {
            //error
        }
    }

    public function updateCustomerWithNetwork($params) {
        $password = isset($params['password']) ? $this->ipsPasswordClean($params['password']) : '';
        $email = isset($params['email']) ? $params['email'] : '';
        $customer_id = isset($params['customer_id']) ? $params['customer_id'] : $this->_getSession()->getId();

        //there isn't anything to update with the network
        if ($password == '' && $email == '') {
            return self::CONNECT_STATUS_SUCCESS;
        }

        $connect_id = Mage::getModel('ipsconnectsso/ipsmagentocustomermap')
            ->getCollection()
            ->getConnectIdFromCustomerId($customer_id);


        if ($connect_id == -1) {
            $connect_id = $this->registerCustomerWithNetwork($email, $password, $customer_id);

            if (!$connect_id) {
                //now we're in trouble
                return 'Cannot save the customer.';
            }else {
                return self::CONNECT_STATUS_SUCCESS;
            }
        }

        $customer = Mage::getModel('customer/customer')->load($customer_id);
        if ($customer->getEmail() == $email) {
            //email is not being changed
            $email = '';

            //something other then the email or password is being changed
            if ($password == '') {
                return self::CONNECT_STATUS_SUCCESS;
            }
        }

        $result = file_get_contents(self::MASTER_URL.'?'.
            http_build_query(array('act' => 'change', 'id' => $connect_id,
                'key' => md5(self::CONNECT_KEY . $connect_id), 'email' => $email,
                'password' => $password == '' ? '' : md5($password))));


        if ($result === FALSE) {
            return 'Cannot save the customer.';
        }else {
            $result_set = json_decode($result);
            //print_r($result_set);

            if ($result_set->status == self::CONNECT_STATUS_SUCCESS) {
                return self::CONNECT_STATUS_SUCCESS;
            }else if ($result_set->status == self::CONNECT_STATUS_EMAIL_IN_USE) {
                return 'The provided e-mail address is already in use.';
            }else {
                return 'Cannot save the customer.';
            }
        }
    }

    public function deleteCustomerFromNetwork($customer_id) {
        $connect_id = Mage::getModel('ipsconnectsso/ipsmagentocustomermap')
            ->getCollection()
            ->getConnectIdFromCustomerId($customer_id);

        $encoded_id = json_encode(array($connect_id));

        Mage::log(self::MASTER_URL . '?' .
            http_build_query(array('act' => 'delete', 'id' => array($connect_id),
                'key' => md5(self::CONNECT_KEY . $encoded_id))));
        if ($connect_id > 0) {
            $result = file_get_contents(self::MASTER_URL . '?' .
                http_build_query(array('act' => 'delete', 'id' => array($connect_id),
                    'key' => md5(self::CONNECT_KEY . $encoded_id))));

            $result_set = json_decode($result);

            return $result_set->status;
        }else {
            return self::CONNECT_STATUS_NO_USER;
        }
    }

    public function addMapEntry($customer_id, $connect_id) {
        //delete any existing map entries
        $items = Mage::getModel('ipsconnectsso/ipsmagentocustomermap')
            ->getCollection()
            ->addFilter('connect_id', $connect_id);
        foreach ($items as $item) {
            $item->delete();
        }

        $items = Mage::getModel('ipsconnectsso/ipsmagentocustomermap')
            ->getCollection()
            ->addFilter('customer_id', $customer_id);
        foreach ($items as $item) {
            $item->delete();
        }

        $customer_map = Mage::getModel('ipsconnectsso/ipsmagentocustomermap');
        $customer_map->setCustomer_id($customer_id);
        $customer_map->setConnect_id($connect_id);
        $customer_map->save();

        return $customer_map->getId();
    }

    public function ipsPasswordClean($password) {
        $password = str_replace('&', '&amp;', $password);
        $password = str_replace('\\', '&#092;', $password);
        $password = str_replace('!', '&#33;', $password);
        $password = str_replace('$', '&#036;', $password);
        $password = str_replace('"', '&quot;', $password);
        $password = str_replace('<', '&lt;', $password);
        $password = str_replace('>', '&gt;', $password);
        $password = str_replace("'", '&#39;', $password);

        return $password;
    }

    /**
     * Gets customer session
     * @return Mage_Core_Model_Abstract
     */
    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }
}