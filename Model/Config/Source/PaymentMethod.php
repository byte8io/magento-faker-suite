<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Payment\Model\Config as PaymentConfig;

class PaymentMethod implements OptionSourceInterface
{
    public function __construct(
        private readonly PaymentConfig $paymentConfig
    ) {}

    public function toOptionArray(): array
    {
        $options = [];
        $payments = $this->paymentConfig->getActiveMethods();
        
        foreach ($payments as $code => $payment) {
            if ($payment->getTitle()) {
                $options[] = [
                    'value' => $code,
                    'label' => $payment->getTitle()
                ];
            }
        }
        
        return $options;
    }
}