<?php

    
        /*** access Joomla's configuration file ***/
// Set flag that this is a parent file.

$my_path = dirname(__FILE__) . "/../../../index.php";


// Get Joomla! framework
define( '_JEXEC', 1 );
define( '_VALID_MOS', 1 );
define( 'JPATH_BASE', realpath(dirname($my_path)));
define( 'DS', DIRECTORY_SEPARATOR );
require_once ( JPATH_BASE .DS.'includes'.DS.'defines.php' );
require_once ( JPATH_BASE .DS.'includes'.DS.'framework.php' );

$mainframe =& JFactory::getApplication('site');
$mainframe->initialise();
$user =& JFactory::getUser();
$session =& JFactory::getSession();


jimport( 'joomla.application.module.helper' );
$module = JModuleHelper::getModule('mymodulename');
$moduleParams = new JRegistry();
$moduleParams->loadString($module->params);



    /*** END of Joomla config ***/
    
    
    


    
    
    
    
    
defined('_JEXEC') or die('Restricted access');

/**
 *
 * We need this extra paths to have always the correct path undependent by loaded application, module or plugin
 * Plugin, module developers must always include this config at start of their application
 *   $vmConfig = VmConfig::loadConfig(); // load the config and create an instance
 *  $vmConfig -> jQuery(); // for use of jQuery
 *  Then always use the defined paths below to ensure future stability
 */
define( 'JPATH_VM_SITE', JPATH_ROOT.DS.'components'.DS.'com_virtuemart' );
defined('JPATH_VM_ADMINISTRATOR') or define('JPATH_VM_ADMINISTRATOR', JPATH_ROOT.DS.'administrator'.DS.'components'.DS.'com_virtuemart');
// define( 'JPATH_VM_ADMINISTRATOR', JPATH_ROOT.DS.'administrator'.DS.'components'.DS.'com_virtuemart' );
define( 'JPATH_VM_PLUGINS', JPATH_VM_ADMINISTRATOR.DS.'plugins' );

if(version_compare(JVERSION,'1.7.0','ge')) {
	defined('JPATH_VM_LIBRARIES') or define ('JPATH_VM_LIBRARIES', JPATH_PLATFORM);
	defined('JVM_VERSION') or define ('JVM_VERSION', 2);
}
else {
	if (version_compare (JVERSION, '1.6.0', 'ge')) {
		defined ('JPATH_VM_LIBRARIES') or define ('JPATH_VM_LIBRARIES', JPATH_LIBRARIES);
		defined ('JVM_VERSION') or define ('JVM_VERSION', 2);
	}
	else {
		defined ('JPATH_VM_LIBRARIES') or define ('JPATH_VM_LIBRARIES', JPATH_LIBRARIES);
		defined ('JVM_VERSION') or define ('JVM_VERSION', 1);
	}
}

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

//This number is for obstruction, similar to the prefix jos_ of joomla it should be avoided
//to use the standard 7, choose something else between 1 and 99, it is added to the ordernumber as counter
// and must not be lowered.
define('VM_ORDER_OFFSET',3);

require(JPATH_VM_ADMINISTRATOR.DS.'version.php');


if (!class_exists('JTable')
		)require(JPATH_VM_LIBRARIES . DS . 'joomla' . DS . 'database' . DS . 'table.php');

JTable::addIncludePath(JPATH_VM_ADMINISTRATOR.DS.'tables');

if (!class_exists ('VmModel')) {
	require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmmodel.php');
}
    
    
if (!class_exists( 'VmConfig' )) require(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_virtuemart'.DS.'helpers'.DS.'config.php');
$config = VmConfig::loadConfig();
    
    
    
    
    
    
    
    
    
        if (!class_exists('VirtueMartModelOrders'))
        {
            require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

if (isset($_GET['invoice_id'])) {
    //require_once("views/default/tmpl/ps_ipay.cfg.php"); 
    
    $url = IPX_GW;
    
    $url = rtrim($url, '/');
    $url = substr($url, 0, strrpos($url, '/', -1)) . '/status_chk';
    
    $myvars = 'merchant_key=' . IPX_MERCHANT_KEY . '&invoice_ids=' . $_GET['invoice_id'];

    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_VERBOSE, 1);
    curl_setopt( $ch, CURLOPT_POST, 1);
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
    //curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt( $ch, CURLOPT_HEADER, 0);
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    
    $response = curl_exec( $ch );
    
    $response_params = explode('::', $response);
    $my_order_id = $response_params[0];
    $my_order_status = $response_params[1];
    $new_status = 'X';
    
    if ( $my_order_status == 'paid' ){
        $new_status = 'C';
    }elseif ( $my_order_status == 'cancelled' ){
        $new_status = 'X';
    }
    
    //echo IPX_MERCHANT_KEY;
    //echo "<br />" . $my_order_id;
    //echo "<br />" . $response_params[0];
    //echo "<br />" . $response;
    
    // Get the Order Details from the database      
    $qv = "SELECT `order_id`, `order_number`, `user_id`, `order_subtotal`,
                    `order_total`, `order_currency`, `order_tax`, 
                    `order_shipping_tax`, `coupon_discount`, `order_discount`
					FROM `#__{vm}_orders` 
					WHERE `order_id`='".$my_order_id."'";
        
        if (!class_exists('VirtueMartModelOrders'))
        {
            require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        
        
        
        // save order data
        //$modelOrder = new VirtueMartModelOrders();
        $modelOrder = VmModel::getModel('orders');
        $order['order_status'] = $new_status;
        $order['virtuemart_order_id'] = $my_order_id;
        $order['customer_notified'] = 0;
        $date = JFactory::getDate();
        $order['comments'] = JText::sprintf('VMPAYMENT_' . 'IPAY_RESPONSE_NOTIFICATION', $date->toFormat('%Y-%m-%d %H:%M:%S'));
        //vmdebug('IPAY'. ' - managePaymentResponse', $order);
        
        $modelOrder->updateStatusForOneOrder($my_order_id, $order, TRUE);
    
    
    //$db = new ps_DB;
    //$db = JFactory::getDBO();
    //$db->query($qv);
    //$db->next_record();
    //$order_id = $db->f("order_id");
    
    
    //$d['order_id'] = $order_id;
    //$d['notify_customer'] = "Y";
    
    //if ( $my_order_status == 'paid' ){
    //    $d['order_status'] = 'C';
    //}elseif ( $my_order_status == 'cancelled' ){
    //    $d['order_status'] = 'X';
    //}
    
    //require_once ( CLASSPATH . 'ps_order.php' );
    //$ps_order= new ps_order;
    //$ps_order->order_status_update($d);
    
}


?>
