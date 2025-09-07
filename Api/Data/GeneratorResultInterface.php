<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Api\Data;

/**
 * Generator Result Interface
 *
 * Result object returned by generators
 */
interface GeneratorResultInterface
{
    public const ENTITY = 'entity';
    public const ENTITY_ID = 'entity_id';
    public const TYPE = 'type';
    public const SUCCESS = 'success';
    public const ERRORS = 'errors';
    public const WARNINGS = 'warnings';
    public const METADATA = 'metadata';

    /**
     * Get generated entity
     *
     * @return mixed
     */
    public function getEntity();

    /**
     * Set generated entity
     *
     * @param mixed $entity
     * @return $this
     */
    public function setEntity($entity): self;

    /**
     * Get entity ID
     *
     * @return string|int|null
     */
    public function getEntityId();

    /**
     * Set entity ID
     *
     * @param string|int|null $entityId
     * @return $this
     */
    public function setEntityId($entityId): self;

    /**
     * Get entity type
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Set entity type
     *
     * @param string $type
     * @return $this
     */
    public function setType(string $type): self;

    /**
     * Check if generation was successful
     *
     * @return bool
     */
    public function isSuccess(): bool;

    /**
     * Set success status
     *
     * @param bool $success
     * @return $this
     */
    public function setSuccess(bool $success): self;

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors(): array;

    /**
     * Add error
     *
     * @param string $error
     * @return $this
     */
    public function addError(string $error): self;

    /**
     * Get warnings
     *
     * @return array
     */
    public function getWarnings(): array;

    /**
     * Add warning
     *
     * @param string $warning
     * @return $this
     */
    public function addWarning(string $warning): self;

    /**
     * Get metadata
     *
     * @return array
     */
    public function getMetadata(): array;

    /**
     * Set metadata
     *
     * @param array $metadata
     * @return $this
     */
    public function setMetadata(array $metadata): self;
}
