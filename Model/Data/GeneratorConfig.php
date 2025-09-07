<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Model\Data;

use Magento\Framework\DataObject;
use Byte8\FakerSuite\Api\Data\GeneratorConfigInterface;

/**
 * Generator Configuration
 */
class GeneratorConfig extends DataObject implements GeneratorConfigInterface
{
    /**
     * @inheritDoc
     */
    public function getStoreId(): ?int
    {
        $storeId = $this->getData(self::STORE_ID);
        return $storeId !== null ? (int) $storeId : null;
    }

    /**
     * @inheritDoc
     */
    public function setStoreId(?int $storeId): GeneratorConfigInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getWebsiteId(): ?int
    {
        $websiteId = $this->getData(self::WEBSITE_ID);
        return $websiteId !== null ? (int) $websiteId : null;
    }

    /**
     * @inheritDoc
     */
    public function setWebsiteId(?int $websiteId): GeneratorConfigInterface
    {
        return $this->setData(self::WEBSITE_ID, $websiteId);
    }

    /**
     * @inheritDoc
     */
    public function getLocale(): ?string
    {
        return $this->getData(self::LOCALE);
    }

    /**
     * @inheritDoc
     */
    public function setLocale(?string $locale): GeneratorConfigInterface
    {
        return $this->setData(self::LOCALE, $locale);
    }

    /**
     * @inheritDoc
     */
    public function getAttributes(): array
    {
        return $this->getData(self::ATTRIBUTES) ?: [];
    }

    /**
     * @inheritDoc
     */
    public function setAttributes(array $attributes): GeneratorConfigInterface
    {
        return $this->setData(self::ATTRIBUTES, $attributes);
    }

    /**
     * @inheritDoc
     */
    public function getOptions(): array
    {
        return $this->getData(self::OPTIONS) ?: [];
    }

    /**
     * @inheritDoc
     */
    public function setOptions(array $options): GeneratorConfigInterface
    {
        return $this->setData(self::OPTIONS, $options);
    }

    /**
     * @inheritDoc
     */
    public function getOption(string $key, $default = null)
    {
        $options = $this->getOptions();
        return $options[$key] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setOption(string $key, $value): GeneratorConfigInterface
    {
        $options = $this->getOptions();
        $options[$key] = $value;
        return $this->setOptions($options);
    }
}
