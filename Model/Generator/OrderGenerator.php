<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Model\Generator;

use Byte8\FakerSuite\Api\Data\GeneratorConfigInterface;
use Byte8\FakerSuite\Api\Data\GeneratorResultInterface;
use Byte8\FakerSuite\Api\DataProvider\DataProviderInterface;
use Byte8\FakerSuite\Api\Generator\CustomerGeneratorInterface;
use Byte8\FakerSuite\Api\Generator\OrderGeneratorInterface;
use Byte8\FakerSuite\Model\Config;
use Byte8\FakerSuite\Model\Data\GeneratorResultFactory;
use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceManagementInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class OrderGenerator extends AbstractGenerator implements OrderGeneratorInterface
{
    private FakerGenerator $faker;
    private ?GeneratorConfigInterface $currentConfig = null;
    
    public function __construct(
        StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Byte8\FakerSuite\Api\Data\GeneratorResultInterfaceFactory $resultFactory,
        LoggerInterface $logger,
        private readonly Config $config,
        private readonly CustomerGeneratorInterface $customerGenerator,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly QuoteFactory $quoteFactory,
        private readonly CartManagementInterface $cartManagement,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderManagementInterface $orderManagement,
        private readonly InvoiceManagementInterface $invoiceManagement,
        private readonly InvoiceService $invoiceService,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly DataProviderInterface $addressProvider
    ) {
        parent::__construct($storeManager, $scopeConfig, $resultFactory, $logger);
        $this->faker = FakerFactory::create();
    }

    public function generate(GeneratorConfigInterface $config): GeneratorResultInterface
    {
        // Get count from options or data since it's not a direct method
        $count = $config->getOption('count', 10);
        if (!$count && method_exists($config, 'getData')) {
            $count = $config->getData('count') ?: 10;
        }
        
        $storeId = $config->getStoreId() ?: (int) $this->storeManager->getStore()->getId();
        $productSkus = $config->getOption('product_skus', []);
        $customerType = $config->getOption('customer_type', 'random'); // random, existing, new, guest
        
        // Store the config for use in other methods
        $this->currentConfig = $config;
        
        $this->logger->info(sprintf('Starting order generation: count=%d, store=%d', $count, $storeId));
        
        $successCount = 0;
        $errors = [];
        $generatedOrders = [];
        
        for ($i = 0; $i < $count; $i++) {
            try {
                $order = $this->generateSingleOrder($storeId, $productSkus, $customerType);
                
                // Process order based on configuration
                $this->processOrderPostCreation($order, $config);
                
                $successCount++;
                $generatedOrders[] = $order;
                
            } catch (\Exception $e) {
                $this->logger->error('Failed to generate order: ' . $e->getMessage());
                $errors[] = sprintf('Order %d: %s', $i + 1, $e->getMessage());
            }
        }
        
        // Create result for the batch
        $result = $this->resultFactory->create();
        $result->setSuccess($successCount > 0);
        $result->setType($this->getType());
        
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $result->addError($error);
            }
        }
        
        // Set metadata about the generation
        $result->setMetadata([
            'total_requested' => $count,
            'total_generated' => $successCount,
            'total_failed' => count($errors),
            'orders' => array_map(function ($order) {
                return [
                    'id' => $order->getId(),
                    'increment_id' => $order->getIncrementId()
                ];
            }, $generatedOrders)
        ]);
        
        return $result;
    }

    public function generateOrderForCustomer(
        CustomerInterface $customer,
        array $productSkus = [],
        array $overrides = []
    ): OrderInterface {
        $storeId = (int) $customer->getStoreId();
        $quote = $this->createQuote($storeId, $customer);
        
        $this->addProductsToQuote($quote, $productSkus);
        $this->setQuoteAddresses($quote, $customer);
        $this->setQuotePaymentAndShipping($quote);
        
        return $this->placeOrder($quote);
    }

    public function generateGuestOrder(
        int $storeId,
        array $productSkus = [],
        array $overrides = []
    ): OrderInterface {
        $quote = $this->createQuote($storeId);
        
        $this->addProductsToQuote($quote, $productSkus);
        $this->setGuestQuoteAddresses($quote);
        $this->setQuotePaymentAndShipping($quote);
        
        return $this->placeOrder($quote);
    }

    public function generateOrderWithNewCustomer(
        int $storeId,
        array $productSkus = [],
        array $customerOverrides = [],
        array $orderOverrides = []
    ): OrderInterface {
        // Generate new customer using proper config
        $customerConfigFactory = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Byte8\FakerSuite\Model\Data\GeneratorConfigFactory::class);
        
        $customerConfig = $customerConfigFactory->create();
        $customerConfig->setStoreId($storeId);
        $customerConfig->setOption('count', 1);
        
        if (!empty($customerOverrides)) {
            $customerConfig->setAttributes($customerOverrides);
        }
        
        $customerResult = $this->customerGenerator->generate($customerConfig);
        if (!$customerResult->isSuccess()) {
            throw new LocalizedException(__('Failed to create customer for order'));
        }
        
        // Get the generated customer entity
        $customer = $customerResult->getEntity();
        if (!$customer) {
            // Try to get from metadata
            $metadata = $customerResult->getMetadata();
            $customerId = $metadata['customer_id'] ?? null;
            
            if ($customerId) {
                $customer = $this->customerRepository->getById($customerId);
            } else {
                throw new LocalizedException(__('Customer not found after generation'));
            }
        }
        
        return $this->generateOrderForCustomer($customer, $productSkus, $orderOverrides);
    }

    private function generateSingleOrder(int $storeId, array $productSkus, string $customerType): OrderInterface
    {
        // Check for specific customer by ID or email first
        if ($this->currentConfig) {
            $customerId = $this->currentConfig->getOption('customer_id');
            $customerEmail = $this->currentConfig->getOption('customer_email');

            if ($customerId) {
                $customer = $this->getCustomerById((int) $customerId);
                if (!$customer) {
                    throw new LocalizedException(__('Customer with ID %1 not found', $customerId));
                }
                return $this->generateOrderForCustomer($customer, $productSkus);
            }

            if ($customerEmail) {
                $customer = $this->getCustomerByEmail($customerEmail, $storeId);
                if (!$customer) {
                    throw new LocalizedException(__('Customer with email %1 not found', $customerEmail));
                }
                return $this->generateOrderForCustomer($customer, $productSkus);
            }
        }

        switch ($customerType) {
            case 'guest':
                return $this->generateGuestOrder($storeId, $productSkus);

            case 'new':
                return $this->generateOrderWithNewCustomer($storeId, $productSkus);

            case 'existing':
                $customer = $this->getRandomExistingCustomer($storeId);
                if (!$customer) {
                    throw new LocalizedException(__('No existing customers found'));
                }
                return $this->generateOrderForCustomer($customer, $productSkus);

            default: // random
                $random = rand(1, 3);
                if ($random === 1) {
                    return $this->generateGuestOrder($storeId, $productSkus);
                } elseif ($random === 2) {
                    $customer = $this->getRandomExistingCustomer($storeId);
                    if ($customer) {
                        return $this->generateOrderForCustomer($customer, $productSkus);
                    }
                }
                return $this->generateOrderWithNewCustomer($storeId, $productSkus);
        }
    }

    private function createQuote(int $storeId, ?CustomerInterface $customer = null): Quote
    {
        $store = $this->storeManager->getStore($storeId);
        
        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $quote->setStore($store);
        $quote->setCurrency();
        
        if ($customer) {
            $quote->assignCustomer($customer);
        } else {
            $quote->setCustomerIsGuest(true);
            $quote->setCheckoutMethod('guest');
            
            // Set guest email
            $emailDomain = $this->config->getDefaultEmailDomain($storeId);
            $emailPrefix = $this->config->getEmailPrefix($storeId);
            $username = $emailPrefix . $this->faker->userName;
            $quote->setCustomerEmail($username . '@' . $emailDomain);
        }
        
        return $quote;
    }

    private function addProductsToQuote(Quote $quote, array $productSkus): void
    {
        if (empty($productSkus)) {
            $productSkus = $this->getRandomProductSkus($quote->getStoreId());
        }
        
        if (empty($productSkus)) {
            throw new LocalizedException(__('No products available for order generation'));
        }
        
        foreach ($productSkus as $sku) {
            try {
                $product = $this->productRepository->get($sku, false, $quote->getStoreId());
                
                if (!$product->isSaleable()) {
                    continue;
                }
                
                $qty = rand(1, 3);
                $quote->addProduct($product, $qty);
                
            } catch (\Exception $e) {
                $this->logger->warning(sprintf('Could not add product %s to quote: %s', $sku, $e->getMessage()));
            }
        }
        
        if (!$quote->getAllVisibleItems()) {
            throw new LocalizedException(__('No products could be added to the quote'));
        }
    }

    private function setQuoteAddresses(Quote $quote, CustomerInterface $customer): void
    {
        $addresses = $customer->getAddresses();
        
        if (!empty($addresses)) {
            $billingAddress = $addresses[0];
            $shippingAddress = count($addresses) > 1 ? $addresses[1] : $addresses[0];
            
            $quote->getBillingAddress()->importCustomerAddressData($billingAddress);
            $quote->getShippingAddress()->importCustomerAddressData($shippingAddress);
        } else {
            $this->setGuestQuoteAddresses($quote);
        }
    }

    private function setGuestQuoteAddresses(Quote $quote): void
    {
        $addressData = $this->addressProvider->getData([
            'country_id' => $quote->getStore()->getConfig('general/country/default'),
            'locale' => $this->config->getAllowedLocales($quote->getStoreId())[0] ?? null
        ]);
        
        $namePrefix = $this->config->getNamePrefix($quote->getStoreId());
        $surnamePrefix = $this->config->getSurnamePrefix($quote->getStoreId());
        $addressPrefix = $this->config->getAddressPrefix($quote->getStoreId());
        
        $addressData['firstname'] = $namePrefix . $this->faker->firstName;
        $addressData['lastname'] = $surnamePrefix . $this->faker->lastName;
        $addressData['street'] = [$addressPrefix . $addressData['street'][0] ?? ''];
        
        $quote->getBillingAddress()->addData($addressData);
        $quote->getShippingAddress()->addData($addressData);
        
        if (!$quote->getCustomerEmail()) {
            $emailDomain = $this->config->getDefaultEmailDomain($quote->getStoreId());
            $emailPrefix = $this->config->getEmailPrefix($quote->getStoreId());
            $username = $emailPrefix . $this->faker->userName;
            $quote->setCustomerEmail($username . '@' . $emailDomain);
            $quote->setCustomerFirstname($addressData['firstname']);
            $quote->setCustomerLastname($addressData['lastname']);
        }
    }

    private function setQuotePaymentAndShipping(Quote $quote): void
    {
        // Ensure quote has required data
        if (!$quote->getStoreId()) {
            $quote->setStoreId($this->storeManager->getStore()->getId());
        }

        // Save quote first to ensure it has an ID
        $this->cartRepository->save($quote);

        // Set shipping method
        $shippingAddress = $quote->getShippingAddress();

        // Ensure address has required data for shipping calculation
        if (!$shippingAddress->getCountryId()) {
            $shippingAddress->setCountryId($quote->getStore()->getConfig('general/country/default'));
        }

        // Collect shipping rates
        $shippingAddress->setCollectShippingRates(true);

        try {
            $shippingAddress->collectShippingRates();
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('Failed to collect shipping rates: %s', $e->getMessage()));
        }

        // Get available shipping methods with robust fallback
        $shippingMethod = $this->getAvailableShippingMethod($quote);

        if (!$shippingMethod) {
            throw new LocalizedException(__('No shipping methods available. Please enable at least one shipping method (e.g., Flat Rate).'));
        }

        // Set the validated shipping method
        $shippingAddress->setShippingMethod($shippingMethod);

        // Save quote after setting shipping to persist the rates
        $this->cartRepository->save($quote);

        // Collect totals after shipping is set
        $quote->collectTotals();

        // Get available payment method with robust fallback
        $paymentMethod = $this->getAvailablePaymentMethod($quote);

        if (!$paymentMethod) {
            throw new LocalizedException(__('No payment methods available. Please enable at least one payment method (e.g., Check/Money Order).'));
        }

        // Set the validated payment method
        $quote->getPayment()->setMethod($paymentMethod);

        // Save quote again after payment
        $this->cartRepository->save($quote);
    }

    private function placeOrder(Quote $quote): OrderInterface
    {
        // Final validation before placing order
        $shippingAddress = $quote->getShippingAddress();
        if (!$quote->isVirtual() && !$shippingAddress->getShippingMethod()) {
            // Force set shipping method as last resort
            $this->logger->warning('No shipping method set before order placement, forcing flatrate_flatrate');
            $shippingAddress->setShippingMethod('flatrate_flatrate');
            $shippingAddress->setShippingRateByCode('flatrate_flatrate');
        }
        
        // Recollect totals to ensure everything is calculated properly
        $quote->collectTotals();
        
        // Save quote
        $this->cartRepository->save($quote);
        
        // Submit order
        $orderId = $this->cartManagement->placeOrder($quote->getId());
        
        return $this->orderRepository->get($orderId);
    }

    private function processOrderPostCreation(OrderInterface $order, GeneratorConfigInterface $config): void
    {
        // Create invoice based on config option or chance
        $shouldCreateInvoice = $config->getOption('force_invoice', false) || $this->shouldCreateInvoice();
        if ($shouldCreateInvoice) {
            try {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->register();
                $invoice->pay();
                $invoice->save();
                
                $order->addRelatedObject($invoice);
                $this->orderRepository->save($order);
                
                $this->logger->info(sprintf('Created invoice for order %s', $order->getIncrementId()));
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Failed to create invoice for order %s: %s',
                    $order->getIncrementId(),
                    $e->getMessage()
                ));
            }
        }
        
        // Create shipment based on config option or chance
        $shouldCreateShipment = $config->getOption('force_shipment', false) || $this->shouldCreateShipment();
        if ($shouldCreateShipment && $order->canShip()) {
            try {
                $shipment = $order->prepareShipment();
                $shipment->register();
                $shipment->save();
                
                $order->setIsInProcess(true);
                $this->orderRepository->save($order);
                
                $this->logger->info(sprintf('Created shipment for order %s', $order->getIncrementId()));
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Failed to create shipment for order %s: %s',
                    $order->getIncrementId(),
                    $e->getMessage()
                ));
            }
        }
        
        // Create credit memo based on chance
        if ($this->shouldCreateCreditmemo() && $order->canCreditmemo()) {
            try {
                // This would need more implementation for partial refunds
                $this->logger->info(sprintf('Credit memo creation for order %s skipped (not fully implemented)', $order->getIncrementId()));
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Failed to create credit memo for order %s: %s',
                    $order->getIncrementId(),
                    $e->getMessage()
                ));
            }
        }
    }

    private function getRandomExistingCustomer(int $storeId): ?CustomerInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('store_id', $storeId)
            ->setPageSize(100)
            ->create();

        $customers = $this->customerRepository->getList($searchCriteria)->getItems();

        if (empty($customers)) {
            return null;
        }

        return $customers[array_rand($customers)];
    }

    private function getCustomerById(int $customerId): ?CustomerInterface
    {
        try {
            return $this->customerRepository->getById($customerId);
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('Customer with ID %d not found: %s', $customerId, $e->getMessage()));
            return null;
        }
    }

    private function getCustomerByEmail(string $email, int $storeId): ?CustomerInterface
    {
        try {
            $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
            return $this->customerRepository->get($email, $websiteId);
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('Customer with email %s not found: %s', $email, $e->getMessage()));
            return null;
        }
    }

    private function getRandomProductSkus(int $storeId): array
    {
        $saleableProducts = [];
        $attempts = 0;
        $maxAttempts = 3;
        
        // Try multiple strategies to find saleable products
        while (empty($saleableProducts) && $attempts < $maxAttempts) {
            $products = [];
            
            switch ($attempts) {
                case 0:
                    // First attempt: Simple products, sorted by entity_id
                    $searchCriteria = $this->searchCriteriaBuilder
                        ->addFilter('type_id', 'simple')
                        ->addFilter('status', 1)
                        ->setPageSize(100)
                        ->create();
                    break;
                    
                case 1:
                    // Second attempt: Any type, sorted by created_at DESC (newer products)
                    $searchCriteria = $this->searchCriteriaBuilder
                        ->addFilter('status', 1)
                        ->addFilter('visibility', ['neq' => 1]) // Not "Not Visible Individually"
                        ->setPageSize(100)
                        ->create();
                    break;
                    
                case 2:
                    // Third attempt: Random page of products
                    $randomPage = rand(1, 5);
                    $searchCriteria = $this->searchCriteriaBuilder
                        ->addFilter('status', 1)
                        ->addFilter('type_id', ['in' => ['simple', 'virtual', 'downloadable']])
                        ->setPageSize(50)
                        ->setCurrentPage($randomPage)
                        ->create();
                    break;
            }
            
            try {
                $products = $this->productRepository->getList($searchCriteria)->getItems();
            } catch (\Exception $e) {
                $this->logger->warning(sprintf('Failed to load products on attempt %d: %s', $attempts + 1, $e->getMessage()));
            }
            
            // Check for saleable products
            foreach ($products as $product) {
                try {
                    // Check if product is saleable (in stock)
                    if ($product->isSaleable()) {
                        $saleableProducts[] = $product;
                        
                        // If we have enough products, stop checking
                        if (count($saleableProducts) >= 20) {
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    // Skip products that throw errors
                    $this->logger->debug(sprintf('Product %s skipped: %s', $product->getSku(), $e->getMessage()));
                }
            }
            
            $attempts++;
        }
        
        if (empty($saleableProducts)) {
            // Last resort: try to get any enabled product
            try {
                $searchCriteria = $this->searchCriteriaBuilder
                    ->addFilter('status', 1)
                    ->setPageSize(10)
                    ->create();
                    
                $products = $this->productRepository->getList($searchCriteria)->getItems();
                
                foreach ($products as $product) {
                    try {
                        // Add even if not saleable, but log warning
                        $saleableProducts[] = $product;
                        $this->logger->warning(sprintf(
                            'Using potentially non-saleable product %s for order generation', 
                            $product->getSku()
                        ));
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to find any products for order generation: ' . $e->getMessage());
            }
        }
        
        if (empty($saleableProducts)) {
            return [];
        }
        
        // Select random products from saleable products
        $skus = [];
        $numProducts = rand(1, min(5, count($saleableProducts)));
        $selectedIndexes = [];
        
        // Ensure we don't select the same product twice
        while (count($selectedIndexes) < $numProducts) {
            $index = array_rand($saleableProducts);
            if (!in_array($index, $selectedIndexes)) {
                $selectedIndexes[] = $index;
                $skus[] = $saleableProducts[$index]->getSku();
            }
        }
        
        $this->logger->info(sprintf(
            'Found %d saleable products, selected %d for order', 
            count($saleableProducts), 
            count($skus)
        ));
        
        return $skus;
    }

    private function getRandomPaymentMethod(Quote $quote): string
    {
        $allowedMethods = $this->config->getAllowedPaymentMethods($quote->getStoreId());
        
        if (!empty($allowedMethods)) {
            return $allowedMethods[array_rand($allowedMethods)];
        }
        
        // Default to checkmo (Check/Money Order) if no methods configured
        return 'checkmo';
    }

    private function getRandomShippingMethod(Quote $quote): string
    {
        $allowedMethods = $this->config->getAllowedShippingMethods($quote->getStoreId());
        
        if (!empty($allowedMethods)) {
            return $allowedMethods[array_rand($allowedMethods)];
        }
        
        // Get first available shipping method
        $shippingAddress = $quote->getShippingAddress();
        
        // Ensure we have collected rates before trying to get them
        if (!$shippingAddress->getShippingRatesCollection()) {
            try {
                $shippingAddress->setCollectShippingRates(true)->collectShippingRates();
            } catch (\Exception $e) {
                $this->logger->debug('Failed to collect shipping rates in getRandomShippingMethod: ' . $e->getMessage());
            }
        }
        
        $rates = $shippingAddress->getAllShippingRates();
        
        if (!empty($rates)) {
            $rate = reset($rates);
            $method = $rate->getCarrier() . '_' . $rate->getMethod();
            $this->logger->debug(sprintf('Found available shipping method: %s', $method));
            return $method;
        }
        
        // Try to get any active shipping method from configuration
        $activeMethods = [];
        $carriers = $this->scopeConfig->getValue('carriers', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $quote->getStoreId());
        
        if (is_array($carriers)) {
            foreach ($carriers as $carrierCode => $carrierConfig) {
                if (isset($carrierConfig['active']) && $carrierConfig['active'] == '1') {
                    // Common shipping method codes
                    if ($carrierCode === 'flatrate') {
                        $activeMethods[] = 'flatrate_flatrate';
                    } elseif ($carrierCode === 'freeshipping') {
                        $activeMethods[] = 'freeshipping_freeshipping';
                    } elseif ($carrierCode === 'tablerate') {
                        $activeMethods[] = 'tablerate_bestway';
                    }
                }
            }
        }
        
        if (!empty($activeMethods)) {
            $method = $activeMethods[array_rand($activeMethods)];
            $this->logger->debug(sprintf('Using active shipping method from config: %s', $method));
            return $method;
        }
        
        // Default to flat rate
        $this->logger->warning('No shipping methods found, defaulting to flatrate_flatrate');
        return 'flatrate_flatrate';
    }

    private function shouldCreateInvoice(): bool
    {
        return rand(1, 100) <= $this->config->getInvoiceChance();
    }

    private function shouldCreateShipment(): bool
    {
        return rand(1, 100) <= $this->config->getShipmentChance();
    }

    private function shouldCreateCreditmemo(): bool
    {
        return rand(1, 100) <= $this->config->getCreditmemoChance();
    }

    /**
     * Get an available and validated shipping method for the quote
     * Implements robust fallback logic to ensure orders always succeed
     *
     * @param Quote $quote
     * @return string|null
     */
    private function getAvailableShippingMethod(Quote $quote): ?string
    {
        $shippingAddress = $quote->getShippingAddress();

        // 1. Try configured shipping method first (if specified by user)
        if ($this->currentConfig && ($configuredMethod = $this->currentConfig->getOption('shipping_method'))) {
            if ($this->isShippingMethodAvailable($shippingAddress, $configuredMethod)) {
                $this->logger->debug(sprintf('Using configured shipping method: %s', $configuredMethod));
                return $configuredMethod;
            }
            $this->logger->warning(sprintf(
                'Configured shipping method %s not available, trying fallback',
                $configuredMethod
            ));
        }

        // 2. Try random method from allowed methods (config-based)
        $allowedMethods = $this->config->getAllowedShippingMethods($quote->getStoreId());
        if (!empty($allowedMethods)) {
            shuffle($allowedMethods);
            foreach ($allowedMethods as $method) {
                if ($this->isShippingMethodAvailable($shippingAddress, $method)) {
                    $this->logger->debug(sprintf('Using allowed shipping method: %s', $method));
                    return $method;
                }
            }
        }

        // 3. Try any available shipping rate from collected rates
        $rates = $shippingAddress->getAllShippingRates();
        if (!empty($rates)) {
            $rate = reset($rates);
            $method = $rate->getCarrier() . '_' . $rate->getMethod();
            $this->logger->debug(sprintf('Using available shipping rate: %s', $method));
            return $method;
        }

        // 4. Try common active carriers from store config
        $carriers = $this->scopeConfig->getValue(
            'carriers',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $quote->getStoreId()
        );

        if (is_array($carriers)) {
            // Prioritize common methods
            $commonMethods = [
                'flatrate' => 'flatrate_flatrate',
                'freeshipping' => 'freeshipping_freeshipping',
                'tablerate' => 'tablerate_bestway'
            ];

            foreach ($commonMethods as $carrierCode => $methodCode) {
                if (isset($carriers[$carrierCode]['active']) && $carriers[$carrierCode]['active'] == '1') {
                    $this->logger->debug(sprintf('Using active carrier from config: %s', $methodCode));
                    return $methodCode;
                }
            }
        }

        // 5. Last resort: null (caller will throw exception)
        $this->logger->error('No shipping methods available for quote');
        return null;
    }

    /**
     * Check if a shipping method is available in the collected rates
     *
     * @param \Magento\Quote\Model\Quote\Address $shippingAddress
     * @param string $method
     * @return bool
     */
    private function isShippingMethodAvailable($shippingAddress, string $method): bool
    {
        $rates = $shippingAddress->getAllShippingRates();
        foreach ($rates as $rate) {
            $rateMethod = $rate->getCarrier() . '_' . $rate->getMethod();
            if ($rateMethod === $method) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get an available and validated payment method for the quote
     * Implements robust fallback logic to ensure orders always succeed
     *
     * @param Quote $quote
     * @return string|null
     */
    private function getAvailablePaymentMethod(Quote $quote): ?string
    {
        // 1. Try configured payment method first (if specified by user)
        if ($this->currentConfig && ($configuredMethod = $this->currentConfig->getOption('payment_method'))) {
            if ($this->isPaymentMethodAvailable($quote, $configuredMethod)) {
                $this->logger->debug(sprintf('Using configured payment method: %s', $configuredMethod));
                return $configuredMethod;
            }
            $this->logger->warning(sprintf(
                'Configured payment method %s not available, trying fallback',
                $configuredMethod
            ));
        }

        // 2. Try random method from allowed methods (config-based)
        $allowedMethods = $this->config->getAllowedPaymentMethods($quote->getStoreId());
        if (!empty($allowedMethods)) {
            shuffle($allowedMethods);
            foreach ($allowedMethods as $method) {
                if ($this->isPaymentMethodAvailable($quote, $method)) {
                    $this->logger->debug(sprintf('Using allowed payment method: %s', $method));
                    return $method;
                }
            }
        }

        // 3. Try common payment methods
        $commonMethods = ['checkmo', 'cashondelivery', 'banktransfer', 'free', 'purchaseorder'];
        foreach ($commonMethods as $method) {
            if ($this->isPaymentMethodAvailable($quote, $method)) {
                $this->logger->debug(sprintf('Using common payment method: %s', $method));
                return $method;
            }
        }

        // 4. Last resort: null (caller will throw exception)
        $this->logger->error('No payment methods available for quote');
        return null;
    }

    /**
     * Check if a payment method is active and available for the quote
     *
     * @param Quote $quote
     * @param string $method
     * @return bool
     */
    private function isPaymentMethodAvailable(Quote $quote, string $method): bool
    {
        // Check if method is active in store config
        $isActive = $this->scopeConfig->getValue(
            'payment/' . $method . '/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $quote->getStoreId()
        );

        if (!$isActive) {
            return false;
        }

        // Additional validation: try to get payment method instance
        try {
            $quote->getPayment()->setMethod($method);
            // If no exception thrown, method is available
            return true;
        } catch (\Exception $e) {
            $this->logger->debug(sprintf('Payment method %s validation failed: %s', $method, $e->getMessage()));
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return self::TYPE;
    }
}