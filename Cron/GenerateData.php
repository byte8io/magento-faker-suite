<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Cron;

use Byte8\FakerSuite\Api\Data\GeneratorConfigInterface;
use Byte8\FakerSuite\Api\Generator\CustomerGeneratorInterface;
use Byte8\FakerSuite\Api\Generator\OrderGeneratorInterface;
use Byte8\FakerSuite\Model\Config;
use Byte8\FakerSuite\Model\Data\GeneratorConfigFactory;
use Psr\Log\LoggerInterface;

class GenerateData
{
    public function __construct(
        private readonly Config $config,
        private readonly CustomerGeneratorInterface $customerGenerator,
        private readonly OrderGeneratorInterface $orderGenerator,
        private readonly GeneratorConfigFactory $generatorConfigFactory,
        private readonly LoggerInterface $logger
    ) {}

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isCronEnabled()) {
            return;
        }

        try {
            // Generate customers
            $customerCount = $this->config->getCronCustomerCount();
            if ($customerCount > 0) {
                $this->logger->info(sprintf('Faker Suite: Generating %d customers via cron', $customerCount));
                
                $config = $this->generatorConfigFactory->create();
                $config->setOption('count', $customerCount);
                $config->setOption('with_addresses', true);
                
                $result = $this->customerGenerator->generate($config);
                
                $metadata = $result->getMetadata();
                $successCount = $metadata['total_generated'] ?? 0;
                $failureCount = $metadata['total_failed'] ?? 0;
                
                $this->logger->info(sprintf(
                    'Faker Suite: Generated %d customers, %d failed',
                    $successCount,
                    $failureCount
                ));
            }

            // Generate orders
            $orderCount = $this->config->getCronOrderCount();
            if ($orderCount > 0) {
                $this->logger->info(sprintf('Faker Suite: Generating %d orders via cron', $orderCount));
                
                $config = $this->generatorConfigFactory->create();
                $config->setOption('count', $orderCount);
                
                $result = $this->orderGenerator->generate($config);
                
                $metadata = $result->getMetadata();
                $successCount = $metadata['total_generated'] ?? 0;
                $failureCount = $metadata['total_failed'] ?? 0;
                
                $this->logger->info(sprintf(
                    'Faker Suite: Generated %d orders, %d failed',
                    $successCount,
                    $failureCount
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error('Faker Suite cron error: ' . $e->getMessage());
        }
    }
}