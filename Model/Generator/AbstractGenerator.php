<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Model\Generator;

use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Byte8\FakerSuite\Api\Data\GeneratorConfigInterface;
use Byte8\FakerSuite\Api\Data\GeneratorResultInterface;
use Byte8\FakerSuite\Api\Data\GeneratorResultInterfaceFactory;
use Byte8\FakerSuite\Api\Generator\GeneratorInterface;

/**
 * Abstract Generator
 *
 * Base class for all data generators
 */
abstract class AbstractGenerator implements GeneratorInterface
{
    /**
     * @var FakerGenerator[]
     */
    private array $fakerInstances = [];

    /**
     * Constructor
     *
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param GeneratorResultInterfaceFactory $resultFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected ScopeConfigInterface $scopeConfig,
        protected GeneratorResultInterfaceFactory $resultFactory,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function generateBatch(GeneratorConfigInterface $config, int $count): array
    {
        $results = [];

        for ($i = 0; $i < $count; $i++) {
            try {
                $results[] = $this->generate($config);
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf('Error generating entity %d of %d: %s', $i + 1, $count, $e->getMessage()),
                    ['exception' => $e]
                );

                $result = $this->resultFactory->create();
                $result->setSuccess(false)
                    ->setType($this->getType())
                    ->addError($e->getMessage());
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @inheritDoc
     */
    public function validate(GeneratorConfigInterface $config): array
    {
        $errors = [];

        // Validate store/website configuration
        if ($config->getStoreId() !== null) {
            try {
                $this->storeManager->getStore($config->getStoreId());
            } catch (\Exception $e) {
                $errors[] = sprintf('Invalid store ID: %s', $config->getStoreId());
            }
        }

        if ($config->getWebsiteId() !== null) {
            try {
                $this->storeManager->getWebsite($config->getWebsiteId());
            } catch (\Exception $e) {
                $errors[] = sprintf('Invalid website ID: %s', $config->getWebsiteId());
            }
        }

        // Child classes can add their own validation
        return array_merge($errors, $this->validateSpecific($config));
    }

    /**
     * Get Faker instance for locale
     *
     * @param string|null $locale
     * @return FakerGenerator
     */
    protected function getFaker(?string $locale = null): FakerGenerator
    {
        $locale = $locale ?: 'en_US';

        if (!isset($this->fakerInstances[$locale])) {
            $this->fakerInstances[$locale] = FakerFactory::create($locale);
        }

        return $this->fakerInstances[$locale];
    }

    /**
     * Create result object
     *
     * @param bool $success
     * @param mixed $entity
     * @param array $errors
     * @param array $warnings
     * @return GeneratorResultInterface
     */
    protected function createResult(
        bool $success,
        $entity = null,
        array $errors = [],
        array $warnings = []
    ): GeneratorResultInterface {
        $result = $this->resultFactory->create();
        $result->setSuccess($success)
            ->setType($this->getType());

        if ($entity !== null) {
            $result->setEntity($entity);
            if (method_exists($entity, 'getId')) {
                $result->setEntityId($entity->getId());
            }
        }

        foreach ($errors as $error) {
            $result->addError($error);
        }

        foreach ($warnings as $warning) {
            $result->addWarning($warning);
        }

        return $result;
    }

    /**
     * Perform specific validation for child classes
     *
     * @param GeneratorConfigInterface $config
     * @return array Validation errors
     */
    protected function validateSpecific(GeneratorConfigInterface $config): array
    {
        return [];
    }

    /**
     * Log generation event
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function log(string $message, array $context = []): void
    {
        $context['generator'] = $this->getType();
        $this->logger->info($message, $context);
    }
}
