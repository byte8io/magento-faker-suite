<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Api\DataProvider;

/**
 * Data Provider Interface
 *
 * Base interface for all data providers
 */
interface DataProviderInterface
{
    /**
     * Get random data based on locale
     *
     * @param string|null $locale
     * @return mixed
     */
    public function getRandom(?string $locale = null);

    /**
     * Get multiple random data entries
     *
     * @param int $count
     * @param string|null $locale
     * @return array
     */
    public function getMultiple(int $count, ?string $locale = null): array;

    /**
     * Check if locale is supported
     *
     * @param string $locale
     * @return bool
     */
    public function isLocaleSupported(string $locale): bool;

    /**
     * Get supported locales
     *
     * @return array
     */
    public function getSupportedLocales(): array;
}
