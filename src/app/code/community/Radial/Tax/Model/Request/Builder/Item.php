<?php
/**
 * Copyright (c) 2013-2016 Radial Commerce Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright   Copyright (c) 2013-2016 Radial Commerce Inc. (http://www.radial.com/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use eBayEnterprise\RetailOrderManagement\Payload\IPayload;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IOrderItemRequest;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IOrderItemRequestIterable;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IDiscountContainer;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedDiscountIterable;
use eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\ITaxedDiscountContainer;

class Radial_Tax_Model_Request_Builder_Item
{
    /** @var IOrderItemRequestIterable */
    protected $_orderItemIterable;
    /** @var IOrderItemRequest */
    protected $_orderItem;
    /** @var Mage_Customer_Model_Address_Abstract */
    protected $_address;
    /** @var Mage_Core_Model_Abstract */
    protected $_item;
    /** @var Mage_Catalog_Model_Product */
    protected $_itemProduct;
    /** @var Radial_Tax_Helper_Data */
    protected $_taxHelper;
    /** @var Radial_Tax_Helper_Payload */
    protected $_payloadHelper;
    /** @var Radial_Core_Model_Config_Registry */
    protected $_taxConfig;
    /** @var Radial_Core_Helper_Discount */
    protected $_discountHelper;
    /** @var EbayEnterprise_MageLog_Helper_Data */
    protected $_logger;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $_logContext;
    /** @var Mage_Sales_Model_Order_Invoice */
    protected $_invoice;

    /**
     * @param array $args Must contain key/value for:
     *                         - order_item_iterable => eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IOrderItemRequestIterable | eBayEnterprise\RetailOrderManagement\Payload\TaxDutyFee\IITaxedOrderItemIterable
     *                         - address => Mage_Customer_Model_Address_Abstract
     *                         - item => Mage_Core_Model_Abstract
     *                         May contain key/value for:
     *                         - tax_helper => Radial_Tax_Helper_Data
     *                         - payload_helper => Radial_Tax_Helper_Payload
     *                         - tax_config => Radial_Core_Model_Config_Registry
     *                         - discount_helper => Radial_Core_Helper_Discount
     *                         - logger => EbayEnterprise_MageLog_Helper_Data
     *                         - log_context => EbayEnterprise_MageLog_Helper_Context
     *			       - invoice => Mage_Sales_Model_Order_Invoice
     */
    public function __construct(array $args)
    {
        list(
            $this->_orderItemIterable,
            $this->_address,
            $this->_item,
            $this->_taxHelper,
            $this->_payloadHelper,
            $this->_taxConfig,
            $this->_discountHelper,
            $this->_logger,
            $this->_logContext,
	    $this->_invoice
        ) = $this->_checkTypes(
            $args['order_item_iterable'],
            $args['address'],
            $args['item'],
            $this->_nullCoalesce($args, 'tax_helper', Mage::helper('radial_tax')),
            $this->_nullCoalesce($args, 'payload_helper', Mage::helper('radial_tax/payload')),
            $this->_nullCoalesce($args, 'tax_config', Mage::helper('radial_tax')->getConfigModel()),
            $this->_nullCoalesce($args, 'discount_helper', Mage::helper('radial_core/discount')),
            $this->_nullCoalesce($args, 'logger', Mage::helper('ebayenterprise_magelog')),
            $this->_nullCoalesce($args, 'log_context', Mage::helper('ebayenterprise_magelog/context')),
	    $args['invoice']
        );
        $this->_itemProduct = $this->getItemProduct($this->_item);
        $this->_orderItem = $this->_orderItemIterable->getEmptyOrderItem();
        $this->_populateRequest();
    }

    /**
     * Enforce type checks on constructor args array.
     *
     * @param IOrderItemRequestIterable | ITaxedOrderItemIterable
     * @param Mage_Customer_Model_Address_Abstract
     * @param Mage_Core_Model_Abstract
     * @param Radial_Tax_Helper_Data
     * @param Radial_Tax_Helper_Payload
     * @param Radial_Core_Model_Config_Registry
     * @param Radial_Core_Helper_Discount
     * @param EbayEnterprise_MageLog_Helper_Data
     * @param EbayEnterprise_MageLog_Helper_Context
     * @param Mage_Sales_Model_Order_Invoice
     * @return array
     */
    protected function _checkTypes(
        IPayload $orderItemIterable,
        Mage_Customer_Model_Address_Abstract $address,
        Mage_Core_Model_Abstract $item,
        Radial_Tax_Helper_Data $taxHelper,
        Radial_Tax_Helper_Payload $payloadHelper,
        Radial_Core_Model_Config_Registry $taxConfig,
        Radial_Core_Helper_Discount $discountHelper,
        EbayEnterprise_MageLog_Helper_Data $logger,
        EbayEnterprise_MageLog_Helper_Context $logContext,
	Mage_Sales_Model_Order_Invoice $invoice
    ) {
        return [
            $orderItemIterable,
            $address,
            $item,
            $taxHelper,
            $payloadHelper,
            $taxConfig,
            $discountHelper,
            $logger,
            $logContext,
	    $invoice
        ];
    }

    /**
     * Fill in default values.
     *
     * @param string
     * @param array
     * @param mixed
     * @return mixed
     */
    protected function _nullCoalesce(array $arr, $key, $default)
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    /**
     * Get the order item payload for the item.
     *
     * @return IOrderItemRequest|null
     */
    public function getOrderItemPayload()
    {
        return $this->_orderItem;
    }

    /**
     * Create an order item payload and inject it with data from the item.
     *
     * @return self
     */
    protected function _populateRequest()
    {
        return $this->_injectItemData()
            ->_injectOriginData()
            ->_injectPricingData()
            ->_injectGiftingData();
    }

    /**
     * Inject general item data into the order item payload.
     *
     * @return self
     */
    protected function _injectItemData()
    {
        $this->_orderItem
            ->setLineNumber($this->_item->getId())
            ->setItemId($this->_item->getSku())
            ->setQuantity((int) $this->_item->getTotalQty())
            ->setDescription($this->_item->getName())
            ->setHtsCode($this->_taxHelper->getProductHtsCodeByCountry($this->_itemProduct, $this->_address->getCountryId()))
            ->setManufacturingCountryCode($this->_itemProduct->getCountryOfManufacture())
            ->setScreenSize($this->_itemProduct->getScreenSize());

	if((int)$this->_item->getTotalQty() === 0 )
	{
		$this->_orderItem->setQuantity((int) $this->_item->getQty());
	}

	return $this;
    }

    /**
     * Add admin and shipping origin data to the item payload.
     *
     * @return self
     */
    protected function _injectOriginData()
    {
        // Admin origin set in configuration.
        $adminOrigin = Mage::getModel('customer/address', [
            'street' => rtrim(
                implode([
                    $this->_taxConfig->adminOriginLine1,
                    $this->_taxConfig->adminOriginLine2,
                    $this->_taxConfig->adminOriginLine3,
                    $this->_taxConfig->adminOriginLine4
                ]),
                "\n"
            ),
            'city' => $this->_taxConfig->adminOriginCity,
            'region_id' => $this->_taxConfig->adminOriginMainDivision,
            'country_id' => $this->_taxConfig->adminOriginCountryCode,
            'postcode' => $this->_taxConfig->adminOriginPostalCode,
        ]);

        // Shipping origin may be set on order items if this information has been
        // retrieved from ROM services. When not available, default to the same
        // address set as the admin origin.
        $shippingOrigin = $this->delegateShippingOrigin() ?: $adminOrigin;

        $this->_orderItem
            ->setAdminOrigin($this->_payloadHelper->customerAddressToPhysicalAddressPayload(
                $adminOrigin,
                $this->_orderItem->getEmptyPhysicalAddress()->setOriginAddressNodeName('AdminOrigin')
            ))
            ->setShippingOrigin($this->_payloadHelper->customerAddressToPhysicalAddressPayload(
                $shippingOrigin,
                $this->_orderItem->getEmptyPhysicalAddress()->setOriginAddressNodeName('ShippingOrigin')
            ));
        return $this;
    }

    /**
     * Inject gifting data for the item.
     *
     * @return self
     */
    protected function _injectGiftingData()
    {
        if ($this->_itemHasGifting()) {
            // Given payload will be updated to include gifting data from the
            // item, so no need to handle the return value as the side-effects
            // of the method will accomplish all that is needed to add gifting
            // data to the payload.
            $this->_payloadHelper->giftingItemToGiftingPayload($this->_item, $this->_orderItem);
        }
        return $this;
    }

    /**
     * Add pricing data for the item to the item payload.
     *
     * @return self
     */
    protected function _injectPricingData()
    {
        $canIncludeAmounts = $this->_canIncludeAmounts($this->_item);

	if($this->_invoice->getId())
	{
		$merchandiseInvoicePricing = $this->_orderItem->getEmptyMerchandisePriceGroup()
		    ->setUnitPrice($canIncludeAmounts ? $this->_item->getPrice() : 0)
                    ->setAmount($canIncludeAmounts ? $this->_item->getRowTotal() : 0)
		    ->setTaxClass("76511");
		if ($canIncludeAmounts) {
                    $this->_discountHelper->transferInvoiceTaxDiscounts($this->_item, $merchandiseInvoicePricing);
                }

		$this->_orderItem->setMerchandisePricing($merchandiseInvoicePricing);
	} else {
        	$merchandisePricing = $this->_orderItem->getEmptyMerchandisePriceGroup()
        	    ->setUnitPrice($canIncludeAmounts ? $this->_item->getPrice() : 0)
        	    ->setAmount($canIncludeAmounts ? $this->_item->getRowTotal() : 0)
        	    ->setTaxClass($this->_itemProduct->getTaxCode());
        	if ($canIncludeAmounts) {
        	    $this->_discountHelper->transferTaxDiscounts($this->_item, $merchandisePricing);
        	}

        	$this->_orderItem->setMerchandisePricing($merchandisePricing);
	}

        // This will be set by the parent address when initially creating the
        // item request builder. Each ship group should include shipping on
        // only one item in the ship group for address level shipping totals.
        if ($this->_item->getIncludeShippingTotals()) {
	    if($this->_invoice->getId())
	    {
		$order = $this->_invoice->getOrder();
		$shipping = $order->getShippingInclTax();
		
		if($shipping)
		{
			//Magento does not break this down by item level, so sending flatrate / qty in invoice
			$shipAmountF = ($this->_invoice->getOrder()->getShippingAmount() / $this->_invoice->getOrder()->getData('total_qty_ordered')) * $this->_item->getQty();
			$shipAmount = round($shipAmountF, 2);
		} else {
			$shipAmount = 0;
		}

		$invoicePricing = $this->_orderItem->getEmptyInvoicePriceGroup()
                	->setAmount($shipAmount)
                	->setTaxClass($this->_taxConfig->shippingTaxClass);
            	$this->_addInvoiceShippingDiscount($invoicePricing);

		$this->_orderItem->setInvoicePricing($invoicePricing);
	    } else {
		$shippingPricing = $this->_orderItem->getEmptyShippingPriceGroup()
                	->setAmount($this->_address->getShippingAmount())
                	->setTaxClass($this->_taxConfig->shippingTaxClass);
                $this->_addShippingDiscount($shippingPricing);

            	$this->_orderItem->setShippingPricing($shippingPricing);
	    }
        }
        return $this;
    }

    /**
     * determine if the item's amounts should be put into the request.
     *
     * @param Mage_Core_Model_Abstract
     * @return bool
     */
    protected function _canIncludeAmounts(Mage_Core_Model_Abstract $item)
    {
	$product = Mage::getModel('catalog/product')->loadByAttribute('sku', $item->getSku());

        return !(
            // only the parent item will have the bundle product type
            $product->getProductType() === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE
            && $item->isChildrenCalculated()
        );
    }

    /**
     * Add discounts for shipping discount amount.
     *
     * Does not use the radial_core/discount helper as shipping discount
     * data may not have been collected to be used by the helper - both
     * use the same event so order between the two cannot be guarantted
     * without introducing a hard dependency. In this case, however,
     * discount data is simple enough to collect independently.
     *
     * @param ITaxDiscountContainer
     * @return ITaxDiscountContainer
     */
    protected function _addShippingDiscount(IDiscountContainer $discountContainer)
    {
        $shippingDiscountAmount = $this->_address->getShippingDiscountAmount();
        if ($shippingDiscountAmount) {
            $discounts = $discountContainer->getDiscounts();
            $shippingDiscount = $discounts->getEmptyDiscount()
                ->setAmount($shippingDiscountAmount);
            $discounts[$shippingDiscount] = $shippingDiscount;
            $discountContainer->setDiscounts($discounts);
        }
        return $discountContainer;
    }

    /**
     * Add discounts for shipping discount amount.
     *
     * Does not use the radial_core/discount helper as shipping discount
     * data may not have been collected to be used by the helper - both
     * use the same event so order between the two cannot be guarantted
     * without introducing a hard dependency. In this case, however,
     * discount data is simple enough to collect independently.
     *
     * @param ITaxedDiscountContainer
     * @return ITaxedDiscountContainer
     */
    protected function _addInvoiceShippingDiscount(ITaxedDiscountContainer $discountContainer)
    {
        $shippingDiscountAmount = $this->_address->getShippingDiscountAmount();
        if ($shippingDiscountAmount) {
            $discounts = $discountContainer->getDiscounts();
            $shippingDiscount = $discounts->getEmptyDiscount()
                ->setAmount($shippingDiscountAmount);
            $discounts[$shippingDiscount] = $shippingDiscount;
            $discountContainer->setDiscounts($discounts);
        }
        return $discountContainer;
    }

    protected function delegateShippingOrigin()
    {
        $address = Mage::getModel('customer/address');
        Mage::dispatchEvent('radial_tax_item_ship_origin', ['item' => $this->_item, 'address' => $address]);
        if ($this->isValidPhysicalAddress($address)) {
            return $address;
        }
        return null;
    }

    /**
     * Check for the item to have shipping origin data set.
     *
     * @return bool
     */
    protected function isValidPhysicalAddress($address)
    {
        return $address->getStreet1()
            && $address->getCity()
            && $address->getCountryId();
    }

    /**
     * Get the product the item represents.
     *
     * @param Mage_Core_Model_Abstract
     * @return Mage_Catalog_Model_Product
     */
    protected function getItemProduct(Mage_Core_Model_Abstract $item)
    {
        // When dealing with configurable items, need to get tax data from
        // the child product and not the parent.
        if ($item->getProductType() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $sku = $item->getSku();
            $children = $item->getChildren();
            if ($children) {
                foreach ($children as $childItem) {
                    $childProduct = $childItem->getProduct();
                    // If the SKU of the child product matches the SKU of the
                    // item, the simple product being ordered was found and should
                    // be used.
                    if ($childProduct->getSku() === $sku) {
                        return $childProduct;
                    }
                }
            }
        }
        return Mage::getModel('catalog/product')->loadByAttribute('sku', $item->getSku());
    }

    /**
     * Check for the item to have gifting data.
     *
     * @param bool
     */
    protected function _itemHasGifting()
    {
        return $this->_item->getGwId() && $this->_item->getGwPrice();
    }
}
