<?php
defined('_JEXEC') or die('Restricted access');

/**
 *
 * a special type of 'paypal ':
 * @author Max Milbers
 * @author Valérie Isaksen
 * @version $Id: paypal.php 5177 2011-12-28 18:44:10Z alatak $
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2004-2008 soeren - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.org
 */
if (!class_exists('vmPSPlugin'))
    require (JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentIPAY extends vmPSPlugin
{

    // instance of class
    public static $_this = false;

    function __construct(&$subject, $config)
    {
        //if (self::$_this)
        //   return self::$_this;
        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id'; //virtuemart_dineromail_id';
        $this->_tableId = 'id'; //'virtuemart_dineromail_id';
        $varsToPush = array(
            'payment_logos' => array('', 'char'), 
            'merchant' => array('', 'char'), 
            'country_id' => array(1, 'int'), 
            'dm_currency_select' => array('', 'char'), 
            'payment_method_1' => array('', 'char'), 
            'payment_method_available' => array('', 'char'), 
            'dm_store_logo_url' => array('', 'char'), 
            'dm_message' => array(0, 'int'),
            'dm_delivery_address' => array(0, 'int'), 
            'payment_button' => array('', 'char'), 
            'dm_payment_button_tooltip' => array('', 'char'),
            'dm_send_form' => array(0, 'int'), 
            'debug' => array(0, 'int'), 
            'status_pending' => array('', 'char'), 
            'status_success' => array('', 'char'),
            'status_canceled' => array('', 'char'), 
            'min_amount' => array('', 'int'), 
            'max_amount' => array('', 'int'), 
            'cost_per_transaction' => array('',
            'int'), 'cost_percent_total' => array('', 'int'), 
            'tax_id' => array(0, 'int'),
            
            
            'ipay_merchant_key' => array('', 'char'),
            'ipay_notification_url' => array('', 'char'),
    	    'ipay_cancelled_url' => array('', 'char'),
    	    'ipay_success_url' => array('', 'char'),
    	    'ipay_gateway_url' => array('', 'char'),
    	    'ipay_gateway_mode' => array('', 'char'),
    	    'ipay_gateway_version' => array('', 'char')
        );

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

        //self::$_this = $this;
    }

    public function getVmPluginCreateTableSQL()
    {

        return $this->createTableSQL('Payment iPay Table');
    }

    function getTableSQLFields()
    {

        $SQLfields = array('id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT', 
            'virtuemart_order_id' => 'int(1) UNSIGNED', 
            'order_number' => ' char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED', 
            'payment_name' => 'varchar(5000)', 
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'', 
            'payment_currency' => 'char(3) ', 
            'cost_per_transaction' => 'decimal(10,2)', 
            'cost_percent_total' =>
            'decimal(10,2)', 
            'tax_id' => ' smallint(1)'//, 
            //'dineromail_response_mc_gross' => 'decimal(10,2)',
            //'dineromail_response_mc_currency' => 'char(10)', 
            //'dineromail_response_invoice' => 'char(32)', 
            //'dineromail_response_protection_eligibility' => 'char(128)', 
            //'dineromail_response_payer_id' => 'char(13)', 
            //'dineromail_response_tax' => 'decimal(10,2)', 
            //'dineromail_response_payment_date' => 'char(28)', 
            //'dineromail_response_payment_status' => 'char(50)', 
            //'dineromail_response_pending_reason' => 'char(50)', 
            //'dineromail_response_mc_fee' => 'decimal(10,2) ', 
            //'dineromail_response_payer_email' => 'char(128)', 
            //'dineromail_response_last_name' => 'char(64)', 
            //'dineromail_response_first_name' => 'char(64)', 
            //'dineromail_response_business' => 'char(128)', 
            //'dineromail_response_receiver_email' => 'char(128)',
            //'dineromail_response_transaction_subject' => 'char(128)', 
            //'dineromail_response_residence_country' => 'char(2)', 
            //'dineromailresponse_raw' => 'varchar(512)'
        );
        return $SQLfields;
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices)
    {
        if (preg_match('/%$/', $method->cost_percent_total))
        {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        }
        else
        {
            $cost_percent_total = $method->cost_percent_total;
        }

        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }

    /**
     * Reimplementation of vmPaymentPlugin::checkPaymentConditions()
     * @param array $cart_prices all cart prices
     * @param object $payment payment parameters object
     * @return bool true if conditions verified
     */
    function checkConditions($cart, $method, $cart_prices)
    {
        $this->convert($method);
        $amount = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount && $amount <= $method->max_amount || ($amount >= $method->min_amount && empty($method->max_amount)));

        return $amount_cond;
    }

    function convert($method)
    {
        $method->min_amount = (float)$method->min_amount;
        $method->max_amount = (float)$method->max_amount;
    }

    /**
     * Prepare data and redirect to PayZen payment platform
     * @param string $order_number
     * @param object $orderData
     * @param string $return_context the session id
     * @param string $html the form to display
     * @param bool $new_status false if it should not be changed, otherwise new staus
     * @return NULL
     */
    function plgVmConfirmedOrder($cart, $order)
    {

        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id)))
        {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element))
        {
            return false;
        }

        $this->_debug = $method->debug; // enable debug
        $session = JFactory::getSession();
        $return_context = $session->getId();
        $this->logInfo('plgVmOnConfirmedOrderGetPaymentForm -- order number: ' . $order['details']['BT']->order_number, 'message');
        
        //$method->url = "https://checkout.dineromail.com/CheckOut";
       
        
        // set config parameters
        $paramNames = array(
            'tool' => "button",
            'merchant_key' => $method->ipay_merchant_key, 
            'seller_name' => $order['details']['BT']->company, 
            'language' => "en",
            #"notification_url" => $method->ipay_notification_url,
            #"success_url" =>        JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&status_code=ok&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id) . '&transaction_id=' . $order['details']['BT']->virtuemart_order_id,
	        #"cancelled_url" =>        JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&status_code=cancelled&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id) . '&transaction_id=' . $order['details']['BT']->virtuemart_order_id,
	        
            'success_url' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id."&o_id={$order['details']['BT']->order_number}"  . '&invoice_id=' . $order['details']['BT']->virtuemart_order_id),
            'cancelled_url' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id  . '&invoice_id=' . $order['details']['BT']->virtuemart_order_id),
            'notification_url' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&on=' . $order['details']['BT']->order_number .'&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id."&XDEBUG_SESSION_START=session_name"."&o_id={$order['details']['BT']->order_number}"),
            
            'payment_method_available' => $method->payment_method_available, 
            'payment_method_1' => $method->payment_method_1,

            'buyer_email' => $order['details']['BT']->email, 
            
        );
    	//print_r($order);
    	$length = count($order['items']);
	    for ($i = 0; $i < $length; $i++) {
	      $item_num = $i+1;
	      $paramNames['item_quantity_'.$item_num] = $order['items'][$i]->product_quantity;
	      $paramNames['item_description_'.$item_num] = $order['items'][$i]->order_item_sku . ' - ' . $order['items'][$i]->order_item_name;
	      $paramNames['item_price_'.$item_num] = $order['items'][$i]->product_quantity * $order['items'][$i]->product_final_price;
	      //$paramNames['item_price_'.$item_num] = $order['items'][$i]->product_final_price;
	    }
	
	$paramNames['total'] = $order['details']['BT']->order_total;
	$paramNames['invoice_id'] = $order['details']['BT']->virtuemart_order_id;
	$paramNames['ver'] = $method->ipay_gateway_version;
	
        
	if($method->ipay_gateway_mode == "1" ){
		$paramNames['live_order'] = True;
	}
        
	
    	
        // Set the language code
        $lang = JFactory::getLanguage();
        $lang->load('plg_vmpayment_' . $this->_name, JPATH_ADMINISTRATOR);

        $tag = substr($lang->get('tag'), 0, 2);
        //$language = in_array($tag, $api->getSupportedLanguages()) ? $tag : ($method->language ? $method->language : 'fr');

        // Prepare data that should be stored in the database
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['payment_name'] = $this->renderPluginName($method, $order);
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues[$this->_name . '_custom'] = $return_context;
        $this->storePSPluginInternalData($dbValues);

        $this->logInfo('plgVmOnConfirmedOrderGetPaymentForm -- payment data saved to table ' . $this->_tablename, 'message');

        // echo the redirect form
        $form = '<html><head><title>Redirection</title></head><body><div style="margin: auto; text-align: center;">';
        
//        if ($method->dm_send_form) {
//            $form .= '<p>' . JText::_('VMPAYMENT_' . $this->_name . '_PLEASE_WAIT') . '</p>'."\n";
//            $form .= '<p>' . JText::_('VMPAYMENT_DINEROMAIL_CLICK_ON_BUTTON') . '</p>'."\n";      
//        } else {
//            $form .= '<p>' . JText::_('VMPAYMENT_DINEROMAIL_CLICK_ON_BUTTON_NOW') . '</p>'."\n";
//        }
        
        
        
        $form .= '<form action="' . $method->ipay_gateway_url . '" method="POST" name="vm_' . $this->_name . '_form" >'."\n";
        $form .= '<input type="image" name="submit" src="' . JURI::base(true) . '/images/stories/virtuemart/payment/dineromail/buttons/' . $method->payment_button . '" 
            alt="' . $method->dm_payment_button_tooltip . '" 
            title="' . $method->dm_payment_button_tooltip . '"/>'."\n";
        
        foreach ($paramNames as $k => $v) {
            $form .= '<input type="hidden" name="'.$k.'" value="'.$v.'" />'."\n";
        }
        
        $form .= '</form></div>'."\n";
        
//        if ($method->dm_send_form) {
            $form .= '<script type="text/javascript">document.forms[0].submit();</script>';  
//        }
        
        $form .= '</body></html>'."\n";

        $this->logInfo('plgVmOnConfirmedOrderGetPaymentForm -- user redirected to ' . $this->_name, 'message');

        echo $form;

        $cart->_confirmDone = false;
        $cart->_dataValidated = false;
        $cart->setCartIntoSession();
        die(); // not save order, not send mail, do redirect
    }

    /**
     * Check PayZen response, save order if not done by server call and redirect to response page
     *  when client comes back from payment platform.
     * @param int $virtuemart_order_id virtuemart order primary key concerned by payment
     * @param string $html message to show as result
     * @return
     */
    function plgVmOnPaymentResponseReceived(&$html)
    {
        // the payment itself should send the parameter needed.
    	$virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
    
    	$vendorId = 0;
    	if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) 
        {
    	    return null; // Another method was selected, do nothing
    	}
    	
        if (!$this->selectedThisElement($method->payment_element)) 
        {
    	    return false;
    	}
    
    	$payment_data = JRequest::get('get');
    	vmdebug('plgVmOnPaymentResponseReceived', $payment_data);
    	$order_number = $payment_data['o_id'];

    	if (!class_exists('VirtueMartModelOrders'))
    	    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
    
    	$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
    	$payment_name = $this->renderPluginName($method);
    	$html = $this->_getPaymentResponseHtml($payment_data, $payment_name);

	    if ($virtuemart_order_id) 
        {
    		if (!class_exists('VirtueMartCart'))
    		    require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
    		
            // get the correct cart / session
    		$cart = VirtueMartCart::getCart();
    
    		// send the email ONLY if payment has been accepted
    		if (!class_exists('VirtueMartModelOrders'))
    		    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
    		
            $order = new VirtueMartModelOrders();
    		$orderitems = $order->getOrder($virtuemart_order_id);
    		//$cart->sentOrderConfirmedEmail($orderitems);
    		$cart->emptyCart();

	    }
        
        $this->plgVmOnPaymentNotification();
        return true;
    }

    /**
     * Process a PayZen payment cancellation.
     * @param int $virtuemart_order_id virtuemart order primary key concerned by payment
     * @return
     */
    function plgVmOnUserPaymentCancel()
    {
        if (!class_exists('VirtueMartModelOrders'))
            require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');

        $order_number = JRequest::getString('on');
        if (!$order_number)
        {
            return false;
        }
        if (!$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))
        {
            return null;
        }
        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id)))
        {
            return null;
        }


        $session = JFactory::getSession();
        $return_context = $session->getId();
        $field = $this->_name . '_custom';
        if (strcmp($paymentTable->$field, $return_context) === 0)
        {
            $this->handlePaymentUserCancel($virtuemart_order_id);
        }
        //JRequest::setVar('paymentResponse', $returnValue);
        
        $this->plgVmOnPaymentNotification();
        return true;
    }

    /**
     * Check PayZen response, save order and empty cart (if payment success) when server notification is received from payment platform.
     * @param string $return_context session id
     * @param int $virtuemart_order_id virtuemart order primary key concerned by payment
     * @param string $new_status new order status
     * @return
     */
    function plgVmOnPaymentNotification()
    {
        if (!class_exists('VirtueMartModelOrders'))
    	    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

        $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
        //echo $virtuemart_paymentmethod_id;
        

    	if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) 
        {
    	    return null; // Another method was selected, do nothing
    	}
    	
        if (!$this->selectedThisElement($method->payment_element)) 
        {
    	    return false;
    	}




        //echo "Allo allo";
        //print_r($_GET);
        //echo isset($_GET['invoice_id']);
        
        if (isset($_GET['invoice_id'])) {
        
            $url = $method->ipay_gateway_url;
            $url = rtrim($url, '/');
            $url = substr($url, 0, strrpos($url, '/', -1)) . '/status_chk';
            
            $myvars = 'merchant_key=' . $method->ipay_merchant_key . '&invoice_ids=' . $_GET['invoice_id'];

            //$ch = curl_init( $url );
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,$url);
            curl_setopt( $ch, CURLOPT_FAILONERROR,1);
            curl_setopt( $ch, CURLOPT_VERBOSE, 1);
            curl_setopt( $ch, CURLOPT_POST, 1);
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
            //curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt( $ch, CURLOPT_HEADER, 0);
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            
            $response = curl_exec( $ch );
            
            curl_close($ch);
            
            
            $response_params = explode('::', $response);
            $my_order_id = $response_params[0];
            $my_order_status = $response_params[1];
            $new_status = $method->status_pending;
            
            if ( $my_order_status == 'paid' ){
                $new_status = $method->status_success;
            }elseif ( $my_order_status == 'cancelled' ){
                $new_status = $method->status_canceled;
            }
            
            
            $virtuemart_order_id = $_GET['invoice_id'];

        	if (!$virtuemart_order_id) 
            {
        	    $this->_debug = true; // force debug here
        	    $this->logInfo('plgVmOnPaymentNotification: virtuemart_order_id not found ', 'ERROR');
        	    // send an email to admin, and ofc not update the order status: exit  is fine
        	    //$this->sendEmailToVendorAndAdmins(JText::_('VMPAYMENT_PAYFAST_ERROR_EMAIL_SUBJECT'), JText::_('VMPAYMENT_PAYFAST_UNKNOWN_ORDER_ID'));
        	    exit;
        	}
        

        	$payment = $this->getDataByOrderId($virtuemart_order_id);
        	$method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);

    	
            if (!$this->selectedThisElement($method->payment_element)) 
            {
        	    return false;
        	}
    
        	$this->_debug = $method->debug;
        	if (!$payment) 
            {
        	    $this->logInfo('getDataByOrderId payment not found: exit ', 'ERROR');
        	    return null;
        	}
        	


            	
        	// get all know columns of the table
        	$response_fields['virtuemart_order_id'] = $virtuemart_order_id;
        	$response_fields['order_number'] = $order_number;
            $response_fields['virtuemart_payment_method_id'] = $payment->virtuemart_paymentmethod_id;
            $response_fields['payment_name'] = $this->renderPluginName($method);
        	$response_fields['cost_per_transaction'] = $payment->cost_per_transaction;
        	$response_fields['cost_percent_total'] = $payment->cost_percent_total;
        	$response_fields['payment_currency'] = $payment->payment_currency;
        	$response_fields['payment_order_total'] = $totalInPaymentCurrency;
        	$response_fields['tax_id'] = $method->tax_id;
            //$response_fields['payfast_response'] = $pfData['payment_status'];
            //$response_fields['payfast_response_payment_date'] = date('Y-m-d H:i:s');

        	$this->storePSPluginInternalData($response_fields);
        
        	$this->logInfo('plgVmOnPaymentNotification return new_status:' . $new_status, 'message');
    
        	if ($virtuemart_order_id) 
            {
        	    // send the email only if payment has been accepted
        	    if (!class_exists('VirtueMartModelOrders'))
                    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
        	    
                $modelOrder = new VirtueMartModelOrders();
        	    $order['order_status'] = $new_status;
        	    $order['virtuemart_order_id'] = $virtuemart_order_id;
        	    $order['customer_notified'] = 1;
        	    $order['comments'] = JTExt::sprintf('VMPAYMENT_PAYFAST_PAYMENT_CONFIRMED', $order_number);
        	    $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
        	}
            
        	$this->emptyCart($return_context);
        
        	return true;
        }
        
        return parent::plgVmOnPaymentNotification();
    }

    function _getHtmlPaymentResponse($msg, $is_success = true, $order_id = null, $amount = null)
    {
        if (!$is_success)
        {
            return '<p style="text-align: center;">' . JText::_($msg) . '</p>';
        }
        else
        {
            $html = '<table>' . "\n";
            $html .= '<thead><tr><td colspan="2" style="text-align: center;">' . JText::_($msg) . '</td></tr></thead>';
            $html .= $this->getHtmlRow($this->_name . '_ORDER_NUMBER', $order_id, 'style="width: 90px;" class="key"');
            //$html .= $this->getHtmlRow($this->_name . '_AMOUNT', $amount, 'style="width: 90px;" class="key"');
            $html .= '</table>' . "\n";

            return $html;
        }
    }

    function savePaymentData($virtuemart_order_id, $resp)
    {
        
        vmdebug($this->_name . 'response_raw', json_encode($resp));
        $response[$this->_tablepkey] = $this->_getTablepkeyValue($virtuemart_order_id);
        $response['virtuemart_order_id'] = $virtuemart_order_id;
        $response[$this->_name . '_response_payment_date'] = gmdate('Y-m-d H:i:s', time());
        $response[$this->_name . '_response_payment_status'] = $resp['status_code'];
        $response[$this->_name . '_response_trans_id'] = $resp['transaction_id'];;
        $this->storePSPluginInternalData($response, $this->_tablepkey, true);
    }

    function _getTablepkeyValue($virtuemart_order_id)
    {
        $db = JFactory::getDBO();
        $q = 'SELECT ' . $this->_tablepkey . ' FROM `' . $this->_tablename . '` ' . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
        $db->setQuery($q);

        if (!($pkey = $db->loadResult()))
        {
            JError::raiseWarning(500, $db->getErrorMsg());
            return '';
        }
        return $pkey;
    }

    function emptyCart($session_id)
    {
        if ($session_id != null)
        {
            $session = JFactory::getSession();
            $session->close();

            // Recover session in wich the payment is done
            session_id($session_id);
            session_start();
        }

        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();
        return true;
    }

    function managePaymentResponse($virtuemart_order_id, $resp, $new_status, $return_context = null)
    {
        // Save platform response data
        $this->savePaymentData($virtuemart_order_id, $resp);

        if (!class_exists('VirtueMartModelOrders'))
        {
            require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        // save order data
        $modelOrder = new VirtueMartModelOrders();
        $order['order_status'] = $new_status;
        $order['virtuemart_order_id'] = $virtuemart_order_id;
        $order['customer_notified'] = 1;
        $date = JFactory::getDate();
        $order['comments'] = JText::sprintf('VMPAYMENT_' . $this->_name . '_RESPONSE_NOTIFICATION', $date->toFormat('%Y-%m-%d %H:%M:%S'));
        vmdebug($this->_name . ' - managePaymentResponse', $order);

        // la fonction updateStatusForOneOrder fait l'envoie de l'email à partir de VM2.0.2
        //$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

        if (!class_exists('VirtueMartCart'))
        {
            require (JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        }

        if ($resp['status_code'] == 'ok')
        {
            // Empty cart in session
            $this->emptyCart($return_context);
        }
    }

    /**
     * We must reimplement this triggers for joomla 1.7
     */

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     * @author Valérie Isaksen
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {

        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author Valérie isaksen
     *
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart)
    {
        return $this->OnSelectCheck($cart);
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Valerie Isaksen
     * @author Max Milbers
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /*
    * plgVmonSelectedCalculatePricePayment
    * Calculate the price (value, tax_id) of the selected method
    * It is called by the calculator
    * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
    * @author Valerie Isaksen
    * @cart: VirtueMartCart the current cart
    * @cart_prices: array the new cart prices
    * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
    *
    *
    */

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array & $cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     * @author Valerie Isaksen
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     * @author Max Milbers
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This event is fired during the checkout process. It can be used to validate the
     * method data as entered by the user.
     *
     * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
     * @author Max Milbers

     * public function plgVmOnCheckoutCheckDataPayment($psType, VirtueMartCart $cart) {
     * return null;
     * }
     */

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
     * Save updated order data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.

     * public function plgVmOnUpdateOrderPayment(  $_formData) {
     * return null;
     * }
     */
    /**
     * Save updated orderline data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.

     * public function plgVmOnUpdateOrderLine(  $_formData) {
     * return null;
     * }
     */
    /**
     * plgVmOnEditOrderLineBE
     * This method is fired when editing the order line details in the backend.
     * It can be used to add line specific package codes
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise

     * public function plgVmOnEditOrderLineBE(  $_orderId, $_lineId) {
     * return null;
     * }
     */

    /**
     * This method is fired when showing the order details in the frontend, for every orderline.
     * It can be used to display line specific package codes, e.g. with a link to external tracking and
     * tracing systems
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise

     * public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
     * return null;
     * }
     */
    function plgVmDeclarePluginParamsPayment($name, $id, &$data)
    {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

}

// No closing tag
