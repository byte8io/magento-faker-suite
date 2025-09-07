<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Model\Data;

use Magento\Framework\DataObject;
use Byte8\FakerSuite\Api\Data\GeneratorResultInterface;

/**
 * Generator Result
 */
class GeneratorResult extends DataObject implements GeneratorResultInterface
{
    /**
     * @inheritDoc
     */
    public function getEntity()
    {
        return $this->getData(self::ENTITY);
    }

    /**
     * @inheritDoc
     */
    public function setEntity($entity): GeneratorResultInterface
    {
        return $this->setData(self::ENTITY, $entity);
    }

    /**
     * @inheritDoc
     */
    public function getEntityId()
    {
        return $this->getData(self::ENTITY_ID);
    }

    /**
     * @inheritDoc
     */
    public function setEntityId($entityId): GeneratorResultInterface
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->getData(self::TYPE) ?: '';
    }

    /**
     * @inheritDoc
     */
    public function setType(string $type): GeneratorResultInterface
    {
        return $this->setData(self::TYPE, $type);
    }

    /**
     * @inheritDoc
     */
    public function isSuccess(): bool
    {
        return (bool) $this->getData(self::SUCCESS);
    }

    /**
     * @inheritDoc
     */
    public function setSuccess(bool $success): GeneratorResultInterface
    {
        return $this->setData(self::SUCCESS, $success);
    }

    /**
     * @inheritDoc
     */
    public function getErrors(): array
    {
        return $this->getData(self::ERRORS) ?: [];
    }

    /**
     * @inheritDoc
     */
    public function addError(string $error): GeneratorResultInterface
    {
        $errors = $this->getErrors();
        $errors[] = $error;
        return $this->setData(self::ERRORS, $errors);
    }

    /**
     * @inheritDoc
     */
    public function getWarnings(): array
    {
        return $this->getData(self::WARNINGS) ?: [];
    }

    /**
     * @inheritDoc
     */
    public function addWarning(string $warning): GeneratorResultInterface
    {
        $warnings = $this->getWarnings();
        $warnings[] = $warning;
        return $this->setData(self::WARNINGS, $warnings);
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(): array
    {
        return $this->getData(self::METADATA) ?: [];
    }

    /**
     * @inheritDoc
     */
    public function setMetadata(array $metadata): GeneratorResultInterface
    {
        return $this->setData(self::METADATA, $metadata);
    }
}
