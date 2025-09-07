<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Api\Generator;

use Magento\Customer\Api\Data\CustomerInterface;

/**
 * Customer Generator Interface
 *
 * Generates customer entities with realistic test data
 */
interface CustomerGeneratorInterface extends GeneratorInterface
{
    public const TYPE = 'customer';

    /**
     * Generate a single customer
     *
     * @param int|null $websiteId
     * @param int|null $storeId
     * @param array $overrides Optional attribute overrides
     * @return CustomerInterface
     * @throws \Exception
     */
    public function generateCustomer(
        ?int $websiteId = null,
        ?int $storeId = null,
        array $overrides = []
    ): CustomerInterface;

    /**
     * Generate customer with addresses
     *
     * @param int|null $websiteId
     * @param int|null $storeId
     * @param int $addressCount Number of addresses to generate
     * @param array $overrides Optional attribute overrides
     * @return CustomerInterface
     * @throws \Exception
     */
    public function generateCustomerWithAddresses(
        ?int $websiteId = null,
        ?int $storeId = null,
        int $addressCount = 1,
        array $overrides = []
    ): CustomerInterface;
}
