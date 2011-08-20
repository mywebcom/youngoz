<?php
/**
 * ET Web Solutions
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade to newer
 * versions in the future.
 *
 * @category   ET
 * @package    ET_IpSecurity
 * @copyright  Copyright (c) 2011 ET Web Solutions (http://etwebsolutions.com)
 * @contacts   support@etwebsolutions.com
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */  

$installer = $this;
/* $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

//try
//{
	$installer->run("
		DROP TABLE IF EXISTS {$this->getTable('ipsecurity_log')};
		CREATE TABLE {$this->getTable('ipsecurity_log')} 
		(
			`logid` int(11) NOT NULL AUTO_INCREMENT,
			`blocked_from` varchar(50) NOT NULL,
			`blocked_ip` varchar(23) NOT NULL,
			`qty` int(11) unsigned NOT NULL DEFAULT '0',
			`create_time` datetime NOT NULL,
			`update_time` datetime NOT NULL,
			PRIMARY KEY (`logid`),
			KEY `blocked_from` (`blocked_from`),
			KEY `blocked_ip` (`blocked_ip`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='ip security log - count of block qty' AUTO_INCREMENT=1 ;
");
//}catch(Exception $e){}

$installer->endSetup();