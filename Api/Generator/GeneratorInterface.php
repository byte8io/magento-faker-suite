<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Api\Generator;

use Byte8\FakerSuite\Api\Data\GeneratorResultInterface;
use Byte8\FakerSuite\Api\Data\GeneratorConfigInterface;

/**
 * Generator Interface
 *
 * Base interface for all data generators
 */
interface GeneratorInterface
{
    /**
     * Generate entity with given configuration
     *
     * @param GeneratorConfigInterface $config
     * @return GeneratorResultInterface
     * @throws \Exception
     */
    public function generate(GeneratorConfigInterface $config): GeneratorResultInterface;

    /**
     * Generate multiple entities
     *
     * @param GeneratorConfigInterface $config
     * @param int $count
     * @return GeneratorResultInterface[]
     * @throws \Exception
     */
    public function generateBatch(GeneratorConfigInterface $config, int $count): array;

    /**
     * Validate configuration before generation
     *
     * @param GeneratorConfigInterface $config
     * @return array List of validation errors (empty if valid)
     */
    public function validate(GeneratorConfigInterface $config): array;

    /**
     * Get generator type identifier
     *
     * @return string
     */
    public function getType(): string;
}
