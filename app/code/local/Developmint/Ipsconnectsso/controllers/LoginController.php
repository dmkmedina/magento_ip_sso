<?php
class Developmint_Ipsconnectsso_LoginController extends Mage_Core_Controller_Front_Action {

    public function processAction() {
        if ($this->_getSession()->isLoggedIn()) {
            $this->_redirect('*/account/');
            return;
        }

        $params = $this->getRequest()->getParams();
        $helper = Mage::helper('ipsconnectsso');

        if (isset($params['login']['username']) && $params['login']['username'] != ''
            && isset($params['login']['password']) && $params['login']['password'] != '') {

            $id = -1;
            $idType = '';
            //this now checks for username and e-mail addresses
            $connect_id = $helper->getConnectIdFromEmail($params['login']['username']);

            $referer = isset($params['login']['referer']) ? $params['login']['referer'] : '';
            if ($referer) {
                // Rebuild referer URL to handle the case when SID was changed
                $referer = Mage::getModel('core/url')
                    ->getRebuiltUrl(Mage::helper('core')->urlDecode($referer));
                if (!$this->_isUrlInternal($referer)) {
                    $referer = '';
                }
            }

            if ($referer == '') {
                $referer = Mage::getUrl('customer/account/');
            }


            if ($connect_id > 0) {
                $id = $connect_id;
                $idType = Developmint_Ipsconnectsso_Helper_Data::ID_TYPE_CONNECT_ID;
            }else if(filter_var($params['login']['username'], FILTER_VALIDATE_EMAIL)) {
                $id = $params['login']['username'];
                $idType = Developmint_Ipsconnectsso_Helper_Data::ID_TYPE_EMAIL;
            }else {
                //if its not an e-mail address, assume its a username
                $id = $params['login']['username'];
                $idType = Developmint_Ipsconnectsso_Helper_Data::ID_TYPE_USERNAME;
            }

            $result = $helper->verifyLoginWithNetwork($id, $idType, $params['login']['password']);

            if ($result->connect_status == Developmint_Ipsconnectsso_Helper_Data::CONNECT_STATUS_SUCCESS) {
                $customer_id = $helper->getCustomerId($result->connect_id, $result->connect_email);

                //customer does not exist locally, create the customer
                if ($customer_id < 0) {
                    $customer_id = $helper->createCustomer($result->connect_id, $result->connect_email, $params['login']['password']);

                    //failed to create the customer, throw an error here, what else can we do?
                    if ($customer_id < 0) {
                        //$this->_getSession()->addError($this->__('There was an error with your E-Mail/Password combination. Please try again.'));
                        $this->_redirect('ipsconnectsso/login/incorrect');
                    }
                }else {
                    //we've verified the provided password is the current network password, update locally
                    $helper->updateCustomerPassword($customer_id, $params['login']['password']);
                }

                $helper->logCustomerIn($customer_id);
                $url = $helper->getNetworkLoginRequestUrl($id, $idType, $params['login']['password'], $referer);
                $this->_redirectUrl($url);
            }else if ($result->connect_status == Developmint_Ipsconnectsso_Helper_Data::CONNECT_STATUS_NO_USER) {
                //first check if the account exists locally
                $customer_id = $helper->getCustomerIdFromEmail($params['login']['username']);

                //need to verify the password
                if ($customer_id > 0) {
                    $val = $helper->authenticateCustomer($customer_id, $params['login']['password']);
                    if ($val == Developmint_Ipsconnectsso_Helper_Data::CONNECT_STATUS_VALIDATING) {
                        //need to provide a different error message for accounts pending validation
                        $this->_getSession()->addError($this->__('The provided e-mail address has not yet been validated.'));
                        $this->_redirect('*/account/login');
                    }else if ($val != Developmint_Ipsconnectsso_Helper_Data::CONNECT_STATUS_SUCCESS) {
                        $customer_id = -1;
                    }
                }

                //if the account exists locally, we have to create the account on the messageboard
                if ($customer_id > 0 && $helper->emailIsAvailableWithNetwork($params['login']['username'])) {
                    $connect_id = $helper->registerCustomerWithNetwork($params['login']['username'], $params['login']['password'], $customer_id);

                    if ($connect_id == false) {
                        //failed to create the account on the messageboard, show an error message
                        //$this->_getSession()->addError($this->__('There was an error with your E-Mail/Password combination. Please try again.'));
                        $this->_redirect('ipsconnectsso/login/incorrect');
                    }else {
                        //log the customer into the network
                        $helper->logCustomerIn($customer_id);
                        $url = $helper->getNetworkLoginRequestUrl($connect_id, Developmint_Ipsconnectsso_Helper_Data::ID_TYPE_CONNECT_ID, $params['login']['password'], $referer);

                        $this->_redirectUrl($url);
                    }
                }else {
                    //the user does not exist locally or on the network, show an error message
                    //$this->_getSession()->addError($this->__('There was an error with your E-Mail/Password combination. Please try again.'));
                    $this->_redirect('ipsconnectsso/login/incorrect');
                }
            }else if ($result->connect_status == Developmint_Ipsconnectsso_Helper_Data::CONNECT_STATUS_VALIDATING) {
                $revalidate_url = Mage::helper('customer')->getEmailConfirmationUrl($params['login']['username']);
                $this->_getSession()->addError($this->__('This account is not confirmed. <a href="%s">Click here</a> to resend confirmation email.', $revalidate_url));
                $this->_redirect('*/account/login');
            }else {
                //handle all other types of errors here
                //$this->_getSession()->addError($this->__('There was an error with your E-Mail/Password combination. Please try again.'));
                $this->_redirect('ipsconnectsso/login/incorrect');
            }
        }else {
            //missing username or password, show an error message
            //$this->_getSession()->addError($this->__('There was an error with your E-Mail/Password combination. Please try again.'));
            $this->_redirect('ipsconnectsso/login/incorrect');
        }

        //$this->_redirect('*/account/');
    }

    /**
     * Gets customer session
     * @return Mage_Core_Model_Abstract
     */
    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }


    public function incorrectAction() {
        $this->loadLayout();
        $this->_initLayoutMessages('catalog/session');

        $this->getLayout()->getBlock('head')->setTitle($this->__('Incorrect Login'));


        $this->renderLayout();
    }


    public function testAction() {
        /*$payment = new Developmint_Autoship_Model_Autoshippayments();
        $payment->setAmount(100.23);
        $payment->setLast4('1234');
        $payment->setPay_method('VI');
        $payment->setTransarmor_token('7608528436881111');
        $payment->setFull_name('Daniel Medina');
        $payment->setExpire_date('0115');

        $obj = new Developmint_Autoship_Model_Autoshipfirstdatagge4();
        $obj->autoship_capture($payment);*/

        /*$address = Mage::getModel('autoship/autoshipaddresses')->load(1);
        $payment_info = array();

        $payment_info['billing_address'] = $address;
        $payment_info['exp_month'] = '01';
        $payment_info['exp_year'] = '15';
        $payment_info['cc_cid'] = '123';
        $payment_info['card_num'] = '4111111111111111';

        $obj = new Developmint_Autoship_Model_Autoshipfirstdatagge4();
        $obj->autoship_authorize($payment_info, 100.23);*/

        /*$obj = new Developmint_Autoship_Model_Autoshipfirstdatagge4();
        $obj->autoship_void('ET187872|5005898', 100.23);*/

        //$obj = new Developmint_Autoship_Model_Autoshipfirstdatagge4();
        //$obj->autoship_refund('ET148287|5185331', 100.23);


        /*$autoship_payment = Mage::getModel('autoship/autoshippayments');
        $autoship_payment->setAutoship_id(1234);
        $autoship_payment->setDate(time());
        $autoship_payment->setAmount(12.30);
        $autoship_payment->setTransaction_id('ET162960|5012360');
        $autoship_payment->setFull_name('Daniel Medina');

        $autoship_payment->setExpire_month('01');
        Mage::log('Month: ' . $autoship_payment->getExp_month());
        $autoship_payment->setExpire_year('2015');
        $autoship_payment->setGateway('F');

        $autoship_payment->setResult(1);
        $autoship_payment->setRespmsg('Initial charge: Approved');

        $autoship_payment->setLast4('1111');
        $autoship_payment->setPay_method('VI');
        //this is the first shipment
        $autoship_payment->setShipment_num(1);
        $autoship_payment->save();*/

        //echo 'testing<br/>';
        //$obj->autoship_capture();

        /*$observer = new Developmint_Autoship_Model_Observer();
        $map = $observer->dailyAutoShip();
        echo '<pre>';
        print_r($map);
        echo '</pre>';*/

        /*$customer = Mage::getModel('customer/customer')->load(136403);
        Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);

        echo $customer->getEmail();*/

        /*$item = Mage::getModel('savedcc/savedcclegacy');
        $item->setCustomer_id(136403);
        $item->setHas_saved('y');

        $item->save();

        print_r($item);*/

        /*$hasSaved = Mage::helper('savedcc')->hasLegacySaved(136403);
        if ($hasSaved) {
            echo 'HAS LEGACY SAVED';
        }else {
            echo 'DOES NOT HAVE LEGACY SAVED';
        }*/

        /*$helper = Mage::helper('eodprocessing');

        $helper->isEodFileReady();*/

        //Mage::helper('eodprocessing')->isEodFileReady();

        /*get_class(Mage::helper('eodprocessing'));
        echo 'testing';*/

        //$file_path = Mage::getBaseDir() . '/var/fulfillment_files/ignore-store-test.csv';

        /*$file = fopen($file_path, 'r');
        print_r(fgetcsv($file));
        fclose($file);*/

        //echo $file_path . '<br/>';
        //Mage::helper('eodprocessing')->uploadFileToFTP($file_path, 'ignore-store-test.csv');





        /*$fulfillment_map = array();

        Mage::log('Processing daily autoship orders');
        //Filter the orders to only list those that are active and have a payment due
        $orders = Mage::getModel('autoship/autoshiporders')
            ->getCollection()
            ->addFilter('active', 'y');


        $orders->getSelect()->where('next_payment <= "'.date('Y-m-d 23:59:59').'"');

        //Each of these orders needs to be charged
        foreach ($orders as $order) {
            $order_id = $order->getId();
            $payment = Mage::helper('autoship')->getLastSuccessfulPayment($order_id);
            $shipment_num = Mage::helper('autoship')->getLastShipmentNum($order_id);

            echo 'attempting to charge order ' . $order_id . '<br/>';

            $products = Mage::helper('autoship')->getProducts($order_id);
            //handle errors with the shipping price
            $ship = Mage::helper('autoship')->getShipping($order, $products);
            if ($ship['price'] < 0) {
                //Unable to retrieve a shipping price for this order
                //keep a record of this failure
                $new_payment = Mage::getModel('autoship/autoshippayments');
                $new_payment->setAutoship_id($order_id);
                $new_payment->setDate(time());
                $new_payment->setResult(Mage_Paypal_Model_Payflowpro::RESPONSE_CODE_DECLINED_BY_MERCHANT);
                $new_payment->setRespmsg('Invalid shipping address');
                $new_payment->setManual('n');
                $new_payment->save();

                Mage::helper('autoship')->processDeclinedPayment($order);
                Mage::log('Failed to charge for autoship #' . $order_id . ", unable to get shipping prices");

                continue;
            }else {
                $amount = Mage::helper('autoship')->getProductsTotal($products) + $ship['price'];
            }

            echo get_class($payment);

            if ($payment->getGateway() == 'P') {
                $result = $this->processPayflowProPayment($payment, $amount, $order_id, $shipment_num);

                if ($result == Developmint_Autoship_Model_Autoshippayflowpro::AUTOSHIP_CAPTURE_SUCCESS) {
                    $order->setNext_payment(strtotime('+'.$order->getHow_often_weeks().' weeks', time()));
                    $order->save();

                    Mage::helper('autoshipemails')->sendSuccessEmail($order);
                    Mage::log('Successfully charged autoship order #' . $order_id);

                    $fulfillment_map[$order->getId()] = Mage::helper('autoship')->getFulfillmentOrderMap($order, $ship['name']);
                }else { //The capture failed
                    Mage::helper('autoship')->processDeclinedPayment($order);
                    Mage::log('Failed to charge for autoship #' . $order_id);
                }
            }else {
                $result = $this->processFirstDataPayment($payment, $amount, $order_id, $shipment_num);

                if ($result == Developmint_Autoship_Model_Autoshipfirstdatagge4::GGE4_AUTOSHIP_CAPTURE_SUCCESS) {
                    $order->setNext_payment(strtotime('+'.$order->getHow_often_weeks().' weeks', time()));
                    $order->save();

                    Mage::helper('autoshipemails')->sendSuccessEmail($order);
                    Mage::log('Successfully charged autoship order #' . $order_id);

                    $fulfillment_map[$order->getId()] = Mage::helper('autoship')->getFulfillmentOrderMap($order, $ship['name']);
                }else { //The capture failed
                    Mage::helper('autoship')->processDeclinedPayment($order);
                    Mage::log('Failed to charge for autoship #' . $order_id);
                }
            }
        }

        Mage::log($fulfillment_map);*/

        //$observer = new Developmint_Autoship_Model_Observer();
        //$observer->dailyAutoShip();

        //echo date('Y-m-d 23:59:59');

        //$autoship = Mage::getModel('autoship/autoshiporders')->load(73);
        //Mage::helper('autoship')->createMagentoOrder($autoship);

        //Mage::helper('eodprocessing')->processEodFile();


        //Then we handle normal orders
        /*$orders = Mage::getModel('sales/order')->getCollection()
            ->addFieldToFilter('status', Mage_Sales_Model_Order::STATE_PROCESSING);

        echo 'There are ' . $orders->getSize() . ' orders to be processed today<br/>';

        foreach ($orders as $order) {
            echo $order->getIncrementId() . ': ' . $observer->determineShippingMethod($order->getShippingDescription(), floatval($order->getWeight())) . '<br/>';
        }*/

        /*$order_ids = array(
            19402,
            21765,
            22363,
            24124,
            29604
        );*/

        $customer = Mage::getSingleton('customer/session')->getCustomer();

        echo 'TESTING ' . $customer->getId() . '<br/>';
        if ($customer->getId() && $customer->getId() == 136463) {

            echo 'Welcome Daniel<br/>';


            /*foreach ($order_ids as $order_id) {
                $order = Mage::getModel('autoship/autoshiporders')->load($order_id);
                echo $order->getId() . '<br/>';

                echo 'Attempting to charge autoship order #'.$order->getId(). '<br/>';

                //the create function handles all error checking and sends the necessary e-mails
                Mage::helper('autoship')->createMagentoOrder($order);
            }*/

            /*
            //30365
            $order = Mage::getModel('autoship/autoshiporders')->load(30492);
            echo $order->getId() . '<br/>';

            echo 'Attempting to charge autoship order #'.$order->getId(). '<br/>';

            //the create function handles all error checking and sends the necessary e-mails
            Mage::helper('autoship')->createMagentoOrder($order);

            //return $fulfillment_map;
            */


            /*
             30417
             30018
             */

            /*echo 'AUTOSHIP!!!!<br/>';

            $order = Mage::getModel('autoship/autoshiporders')->load(30417);
            echo $order->getId();
            Mage::helper('autoship')->createMagentoOrder($order);*/


            //$observer = new Developmint_Autoship_Model_Observer();
            //$observer->incrementalOrderProcessing();
            //$observer->fulfillDailyOrders();
            //$observer->fulfillDailyOrders();
            //$observer->cronTest();
            //$observer->processEodFile();
            //$observer->dailyAutoShip();
            //$observer->shipDailyOrders();
            //$observer->notifyUpcomingAutoships();
            //$observer->processEodFile();

            //$order = Mage::getModel('autoship/autoshiporders')->load(31043);
            //Mage::helper('autoshipemails')->sendUpcomingEmail($order);

            //Mage::helper('autoship')->createMagentoOrder($order);

            //Mage::helper('eodprocessing')->processEodFile();


            //$file_path = Mage::getBaseDir() . '/var/fulfillment_files/parsed-DK-' .date('Ymd').'.csv';

            /*echo $file_path . '<br/>';
            $file = fopen($file_path, 'r');
            if (!$file) {
                Mage::log('Failed to open the fulfillment file when preparing to set orders as shipped' . $file_path, null, 'dailyorders.log');
                Mage::helper('eodprocessing')->reportGenericWarning('Developmint.org Store Could Not Open Order File For Shipping', 'Failed to open the fulfillment file ' . $file_path);
                return;
            }

            $keys = fgetcsv($file);

            while ($row = fgetcsv($file)) {
                $order = Mage::getModel('sales/order')->loadByIncrementId($row[1]);

                //echo $order->getId() . ' : ' . $order->getStatus() . '<br/>';

                if ($order->getStatus() == 'processing') {
                    echo 'Processing order '.$order->getIncrementId().'<br/>';

                    $itemQty = $order->getItemsCollection()->count();
                    $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($itemQty);
                    $shipment = new Mage_Sales_Model_Order_Shipment_Api();
                    $shipmentId = $shipment->create($order->getIncrementId());
                    //$shipment->sendEmail();
                    $shipment->sendInfo($shipmentId);
                }
            }*/

            //$order = Mage::getModel('sales/order')->loadByIncrementId(100403254);

            /*if ($order->getId()) {
                $collection = $order->getShipmentsCollection();
                if ($collection->count() == 0 && $order->canShip()) {
                    $itemQty = $order->getItemsCollection()->count();
                    $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($itemQty);
                    $shipment = new Mage_Sales_Model_Order_Shipment_Api();
                    $shipmentId = $shipment->create($order->getIncrementId());
                    $shipment->addTrack($shipmentId, strtolower('ups'), 'Next Day Air', '1Z3027Y00162742051');
                    $shipment->sendEmail();
                }else {
                    foreach($collection as $shipment) {
                        //create the tracking number and add it to the shipment
                        $track = Mage::getModel('sales/order_shipment_track')
                            ->setShipment($shipment)
                            ->setData('title', 'Next Day Air')
                            ->setData('number', '1Z3027Y00162742051')
                            ->setData('carrier_code', strtolower('ups'))
                            ->setData('order_id', $shipment->getData('order_id'))
                            ->save();

                        $shipment->sendEmail();

                        break;
                    }
                }
            }*/

            //Create the shipment for this order, the tracking number will be added later
            /*$itemQty = $order->getItemsCollection()->count();
            $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($itemQty);
            $shipment = new Mage_Sales_Model_Order_Shipment_Api();
            $shipmentId = $shipment->create($order->getIncrementId());*/


            /*for ($i = 100402994; $i < 100403338; $i++) {
                $order = Mage::getModel('sales/order')->loadByIncrementId($i);

                if ($order->getId()) {

                }
            }*/
        }
    }

}