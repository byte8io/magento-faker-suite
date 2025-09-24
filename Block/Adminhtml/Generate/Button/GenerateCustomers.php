<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Block\Adminhtml\Generate\Button;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class GenerateCustomers implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        return [
            'label' => __('Generate Customers'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => [
                    'button' => ['event' => 'save', 'target' => '#byte8-faker-suite-generate-form']
                ],
                'form-role' => 'save',
            ],
            'on_click' => sprintf("location.href = '%s';", $this->getGenerateUrl()),
            'sort_order' => 90,
        ];
    }

    private function getGenerateUrl(): string
    {
        return '*/*/generateCustomers';
    }
}