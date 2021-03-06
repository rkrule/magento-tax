<?php

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$entities = array(
        'quote',
        'order'
);

$options = array(
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'visible' => false,
        'required' => false
);

$attrList = array(
	'radial_tax_taxrecords', 'radial_tax_duties', 'radial_tax_fees'
);

foreach ($entities as $entity) {
	$model = 'sales/' . $entity;

	foreach( $attrList as $attrN )
	{
		$attr = Mage::getResourceModel($model)->loadByCode($entity, $attrN)
		if( !$attr->getId())
		{
			$installer->addAttribute($entity, $attrN, $options);
		}
	}
}

$installerA = Mage::getResourceModel('catalog/setup', 'catalog_setup');

$entity = 'catalog_product';
$code = 'hts_codes';
$attr = Mage::getResourceModel('catalog/eav_attribute')
    ->loadByCode($entity,$code);

if( !$attr->getId())
{
	$installerA->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'hts_codes', array(
	    'type' => 'text',
	    'group' => 'PTF',
	    'label' => 'HTS Codes',
	    'input' => 'textarea',
	    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
	    'visible' => true,
	    'required' => false,
	    'user_defined' => false,
	    'default' => '',
	    'apply_to' => 'simple,configurable,virtual,bundle,downloadable,giftcard',
	    'visible_on_front' => false,
	    'used_in_product_listing' => true
	));
}

$entity = 'catalog_product';
$code = 'tax_code';
$attr = Mage::getResourceModel('catalog/eav_attribute')
    ->loadByCode($entity,$code);

if( !$attr->getId())
{
	$installerA->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'tax_code', array(
	    'type' => 'varchar',
	    'group' => 'Prices',
	    'label' => 'Tax Code',
	    'input' => 'text',
	    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
	    'visible' => true,
	    'required' => true,
	    'user_defined' => false,
	    'default' => '',
	    'apply_to' => 'simple,virtual,bundle,downloadable,giftcard',
	    'visible_on_front' => false,
	    'used_in_product_listing' => true
	));
}

$entity = 'catalog_product';
$code = 'screen_size';
$attr = Mage::getResourceModel('catalog/eav_attribute')
    ->loadByCode($entity,$code);

if( !$attr->getId())
{
	$installerA->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'screen_size', array(
	    'type' => 'text',
	    'group' => 'PTF',
	    'label' => 'Screen Size',
	    'input' => 'textarea',
	    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
	    'visible' => true,
	    'required' => false,
	    'user_defined' => false,
	    'default' => '',
	    'apply_to' => 'simple,configurable,virtual,bundle,downloadable,giftcard',
	    'visible_on_front' => false,
	    'used_in_product_listing' => true
	));
}

$installer->endSetup();
