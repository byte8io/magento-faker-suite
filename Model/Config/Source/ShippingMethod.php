<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Model\Config\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Shipping\Model\Config as ShippingConfig;

class ShippingMethod implements OptionSourceInterface
{
    public function __construct(
        private readonly ShippingConfig $shippingConfig,
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    public function toOptionArray(): array
    {
        $options = [];
        $carriers = $this->shippingConfig->getActiveCarriers();
        
        foreach ($carriers as $carrierCode => $carrier) {
            $carrierTitle = $this->scopeConfig->getValue(
                'carriers/' . $carrierCode . '/title',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
            
            if ($carrierTitle) {
                $carrierMethods = $carrier->getAllowedMethods();
                if ($carrierMethods) {
                    foreach ($carrierMethods as $methodCode => $methodTitle) {
                        $options[] = [
                            'value' => $carrierCode . '_' . $methodCode,
                            'label' => $carrierTitle . ' - ' . $methodTitle
                        ];
                    }
                }
            }
        }
        
        return $options;
    }
}