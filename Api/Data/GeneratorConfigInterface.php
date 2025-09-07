<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Api\Data;

/**
 * Generator Configuration Interface
 *
 * Configuration object for data generators
 */
interface GeneratorConfigInterface
{
    public const STORE_ID = 'store_id';
    public const WEBSITE_ID = 'website_id';
    public const LOCALE = 'locale';
    public const ATTRIBUTES = 'attributes';
    public const OPTIONS = 'options';

    /**
     * Get store ID
     *
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * Set store ID
     *
     * @param int|null $storeId
     * @return $this
     */
    public function setStoreId(?int $storeId): self;

    /**
     * Get website ID
     *
     * @return int|null
     */
    public function getWebsiteId(): ?int;

    /**
     * Set website ID
     *
     * @param int|null $websiteId
     * @return $this
     */
    public function setWebsiteId(?int $websiteId): self;

    /**
     * Get locale code
     *
     * @return string|null
     */
    public function getLocale(): ?string;

    /**
     * Set locale code
     *
     * @param string|null $locale
     * @return $this
     */
    public function setLocale(?string $locale): self;

    /**
     * Get attribute overrides
     *
     * @return array
     */
    public function getAttributes(): array;

    /**
     * Set attribute overrides
     *
     * @param array $attributes
     * @return $this
     */
    public function setAttributes(array $attributes): self;

    /**
     * Get additional options
     *
     * @return array
     */
    public function getOptions(): array;

    /**
     * Set additional options
     *
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options): self;

    /**
     * Get specific option
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getOption(string $key, $default = null);

    /**
     * Set specific option
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setOption(string $key, $value): self;
}
