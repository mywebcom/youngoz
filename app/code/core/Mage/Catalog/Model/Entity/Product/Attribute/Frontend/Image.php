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
 * @category   Mage
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2008 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Product image attribute frontend
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author      Magento Core Team <core@magentocommerce.com>

 */
class Mage_Catalog_Model_Entity_Product_Attribute_Frontend_Image extends Mage_Eav_Model_Entity_Attribute_Frontend_Abstract
{
    public function getUrl($object, $size=null)
    {
        $url = false;
        $image = $object->getData($this->getAttribute()->getAttributeCode());

        if( !is_null($size) && file_exists(Mage::getBaseDir('media').'/catalog/product/'. $size . '/' . $image) ) {
            # image is cached
            $url = Mage::getBaseUrl('media').'catalog/product/' . $size . '/' . $image;
        } elseif( !is_null($size) ) {
            # image is not cached
            $url = Mage::getBaseUrl().'catalog/product/image/size/' . $size . '/' . $image;
        } else {
            # image is not cached
            $url = Mage::getBaseUrl().'catalog/product/image' . $image;
        }/* elseif ($image) {
            # using original image
            $url = Mage::getBaseUrl('media').'catalog/product/'.$image;
        }*/
        return $url;
    }
}