<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Api\Generator;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Customer\Api\Data\CustomerInterface;

/**
 * Order Generator Interface
 *
 * Generates order entities with realistic test data
 */
interface OrderGeneratorInterface extends GeneratorInterface
{
    public const TYPE = 'order';

    /**
     * Generate order for existing customer
     *
     * @param CustomerInterface $customer
     * @param array $productSkus Product SKUs to include
     * @param array $overrides Optional order overrides
     * @return OrderInterface
     * @throws \Exception
     */
    public function generateOrderForCustomer(
        CustomerInterface $customer,
        array $productSkus = [],
        array $overrides = []
    ): OrderInterface;

    /**
     * Generate guest order
     *
     * @param int $storeId
     * @param array $productSkus Product SKUs to include
     * @param array $overrides Optional order overrides
     * @return OrderInterface
     * @throws \Exception
     */
    public function generateGuestOrder(
        int $storeId,
        array $productSkus = [],
        array $overrides = []
    ): OrderInterface;

    /**
     * Generate order with new customer
     *
     * @param int $storeId
     * @param array $productSkus Product SKUs to include
     * @param array $customerOverrides Customer attribute overrides
     * @param array $orderOverrides Order attribute overrides
     * @return OrderInterface
     * @throws \Exception
     */
    public function generateOrderWithNewCustomer(
        int $storeId,
        array $productSkus = [],
        array $customerOverrides = [],
        array $orderOverrides = []
    ): OrderInterface;
}
