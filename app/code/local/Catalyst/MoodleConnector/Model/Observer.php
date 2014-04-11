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
* @category  Catalyst
* @package   Catalyst_MoodleConnector
* @author    Edwin Phillips <edwin.phillips@catalyst-eu.net>
* @copyright Copyright (c) 2014 Catalyst IT (http://catalyst-eu.net)
* @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
*/
class Catalyst_MoodleConnector_Model_Observer
{
    public function __construct()
    {
    }

    /**
    * Calls Moodle webservice if order contains any Moodle courses
    *
    * @param Varien_Event_Observer $observer
    */
    public function updateMoodle(Varien_Event_Observer $observer)
    {

        $moodle_courses = array();
        $order_id = $observer->getEvent()->getOrder()->getId();
        $order = Mage::getModel('sales/order')->load($order_id);
        $ordered_items = $order->getAllItems();

        foreach ($ordered_items as $item) {
            $product_id = $item->getProduct()->getId();
            $products = Mage::getModel('catalog/product')
                    ->getCollection()
                    ->addAttributeToSelect('moodle_id')
                    ->addIdFilter($product_id);
            foreach($products as $product) {
                if ($product->moodle_id) {
                    $moodle_courses[] = array('course_id' => $product->moodle_id);
                }
            }
        }

        if ($moodle_courses && Mage::getStoreConfig('moodleconnector/settings/enabled')) {

            $order_number = $order->getIncrementId();

            $customer = array();
            $customer['firstname'] = $order->getCustomerFirstname();
            $customer['lastname']  = $order->getCustomerLastname();
            $customer['email']     = $order->getCustomerEmail();
            $customer['city']      = $order->getShippingAddress()->getCity();
            $customer['country']   = $order->getShippingAddress()->getCountry();

            $baseurl = Mage::getStoreConfig('moodleconnector/settings/baseurl');
            $token   = Mage::getStoreConfig('moodleconnector/settings/token');
            $url     = "{$baseurl}/webservice/xmlrpc/server.php?wstoken={$token}";
            $data    = xmlrpc_encode_request('local_magentoconnector_process_request',
                    array($order_number, $customer, $moodle_courses));

            $curl = new Varien_Http_Adapter_Curl();
            $curl->setConfig(array('timeout' => 30, 'header' => false));
            $curl->write(Zend_Http_Client::POST, $url, CURL_HTTP_VERSION_1_1, array(), $data);
            $response = xmlrpc_decode($curl->read());
            $status = $curl->getInfo(CURLINFO_HTTP_CODE);
            $curl->close();

            if (($status != 200) || ($response != 1)) {
                $result = array();
                $result['success'] = false;
                $result['error'] = true;
                $result['error_messages'] = __('The Moodle server did not successfully process your request.');
                echo Mage::helper('core')->jsonEncode($result);
                die;
            }
        }
    }
}
