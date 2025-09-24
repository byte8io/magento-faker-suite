<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Controller\Adminhtml\Generate;

use Byte8\FakerSuite\Api\Generator\OrderGeneratorInterface;
use Byte8\FakerSuite\Model\Config;
use Byte8\FakerSuite\Model\Data\GeneratorConfigFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

class GenerateOrders extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Byte8_FakerSuite::generate';

    public function __construct(
        Context $context,
        private readonly OrderGeneratorInterface $orderGenerator,
        private readonly GeneratorConfigFactory $generatorConfigFactory,
        private readonly Config $config
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->config->isEnabled()) {
            $this->messageManager->addErrorMessage(__('Faker Suite is disabled. Enable it in configuration.'));
            return $this->_redirect('*/*/index');
        }

        $postData = $this->getRequest()->getParams();
        
        try {
            $count = (int) ($postData['order_count'] ?? 10);
            $storeId = (int) ($postData['store_id'] ?? 1);
            $customerType = $postData['customer_type'] ?? 'random';
            $productSkus = !empty($postData['product_skus']) 
                ? array_map('trim', explode(',', $postData['product_skus'])) 
                : [];
            $locale = $postData['locale'] ?? null;
            
            $config = $this->generatorConfigFactory->create();
            $config->setCount($count);
            $config->setStoreId($storeId);
            $config->setData('customer_type', $customerType);
            $config->setData('product_skus', $productSkus);
            if ($locale) {
                $config->setData('locale', $locale);
            }
            
            $result = $this->orderGenerator->generate($config);
            
            if ($result->getSuccessCount() > 0) {
                $this->messageManager->addSuccessMessage(
                    __('Successfully generated %1 orders.', $result->getSuccessCount())
                );
            }
            
            if ($result->getFailureCount() > 0) {
                $this->messageManager->addWarningMessage(
                    __('%1 orders failed to generate.', $result->getFailureCount())
                );
            }
            
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error generating orders: %1', $e->getMessage()));
        }
        
        return $this->_redirect('*/*/index');
    }
}