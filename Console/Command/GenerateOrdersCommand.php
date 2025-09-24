<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Console\Command;

use Byte8\FakerSuite\Api\Generator\OrderGeneratorInterface;
use Byte8\FakerSuite\Model\Config;
use Byte8\FakerSuite\Model\Data\GeneratorConfigFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class GenerateOrdersCommand extends Command
{
    private const COMMAND_NAME = 'faker:order';
    private const OPTION_COUNT = 'count';
    private const OPTION_STORE = 'store';
    private const OPTION_SKUS = 'sku';
    private const OPTION_CUSTOMER_TYPE = 'customer-type';
    private const OPTION_WITH_INVOICE = 'with-invoice';
    private const OPTION_WITH_SHIPMENT = 'with-shipment';
    private const OPTION_LOCALE = 'locale';
    private const OPTION_TAG = 'tag';
    private const OPTION_PAYMENT_METHOD = 'payment-method';
    private const OPTION_SHIPPING_METHOD = 'shipping-method';
    private const OPTION_PRODUCT_TYPE = 'product-type';
    private const OPTION_ITEM_COUNT = 'item-count';
    private const OPTION_CURRENCY = 'currency';
    private const OPTION_WITH_DISCOUNT = 'with-discount';
    private const OPTION_TAX_EXEMPT = 'with-tax-exempt';
    private const OPTION_PARTIAL_INVOICE = 'partial-invoice';
    private const OPTION_MULTI_ADDRESS = 'multi-address';
    private const OPTION_ORDER_STATUS = 'order-status';

    public function __construct(
        private readonly State $appState,
        private readonly OrderGeneratorInterface $orderGenerator,
        private readonly GeneratorConfigFactory $generatorConfigFactory,
        private readonly Config $config
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Generate fake orders for testing')
            ->setHelp('This command generates fake orders with realistic data for testing purposes')
            ->addOption(
                self::OPTION_COUNT,
                'c',
                InputOption::VALUE_REQUIRED,
                'Number of orders to generate',
                '10'
            )
            ->addOption(
                self::OPTION_STORE,
                's',
                InputOption::VALUE_REQUIRED,
                'Store ID for orders',
                '1'
            )
            ->addOption(
                self::OPTION_SKUS,
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated list of product SKUs to use'
            )
            ->addOption(
                self::OPTION_CUSTOMER_TYPE,
                't',
                InputOption::VALUE_REQUIRED,
                'Customer type: random, existing, new, guest',
                'random'
            )
            ->addOption(
                self::OPTION_WITH_INVOICE,
                null,
                InputOption::VALUE_NONE,
                'Always create invoices for orders'
            )
            ->addOption(
                self::OPTION_WITH_SHIPMENT,
                null,
                InputOption::VALUE_NONE,
                'Always create shipments for orders'
            )
            ->addOption(
                self::OPTION_LOCALE,
                'l',
                InputOption::VALUE_REQUIRED,
                'Locale for generating order data'
            )
            ->addOption(
                self::OPTION_TAG,
                null,
                InputOption::VALUE_REQUIRED,
                'Tag to identify test orders (added to order comments)'
            )
            ->addOption(
                self::OPTION_PAYMENT_METHOD,
                null,
                InputOption::VALUE_REQUIRED,
                'Specific payment method to use'
            )
            ->addOption(
                self::OPTION_SHIPPING_METHOD,
                null,
                InputOption::VALUE_REQUIRED,
                'Specific shipping method to use'
            )
            ->addOption(
                self::OPTION_PRODUCT_TYPE,
                null,
                InputOption::VALUE_REQUIRED,
                'Product type filter: simple, configurable, bundle, virtual'
            )
            ->addOption(
                self::OPTION_ITEM_COUNT,
                null,
                InputOption::VALUE_REQUIRED,
                'Number of items per order',
                '0'
            )
            ->addOption(
                self::OPTION_CURRENCY,
                null,
                InputOption::VALUE_REQUIRED,
                'Currency code for orders'
            )
            ->addOption(
                self::OPTION_WITH_DISCOUNT,
                null,
                InputOption::VALUE_NONE,
                'Add discount/coupon to orders'
            )
            ->addOption(
                self::OPTION_TAX_EXEMPT,
                null,
                InputOption::VALUE_NONE,
                'Create tax-exempt orders'
            )
            ->addOption(
                self::OPTION_PARTIAL_INVOICE,
                null,
                InputOption::VALUE_NONE,
                'Create partial invoices'
            )
            ->addOption(
                self::OPTION_MULTI_ADDRESS,
                null,
                InputOption::VALUE_NONE,
                'Use different billing/shipping addresses'
            )
            ->addOption(
                self::OPTION_ORDER_STATUS,
                null,
                InputOption::VALUE_REQUIRED,
                'Target order status after creation'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
            // Area already set
        }

        if (!$this->config->isEnabled()) {
            $output->writeln('<error>Faker Suite is disabled. Enable it in configuration.</error>');
            return Cli::RETURN_FAILURE;
        }

        $count = (int) $input->getOption(self::OPTION_COUNT);
        $storeId = (int) $input->getOption(self::OPTION_STORE);
        $skus = $input->getOption(self::OPTION_SKUS);
        $customerType = $input->getOption(self::OPTION_CUSTOMER_TYPE);
        $locale = $input->getOption(self::OPTION_LOCALE);
        $tag = $input->getOption(self::OPTION_TAG);

        // Check locale is allowed
        if ($locale) {
            $allowedLocales = $this->config->getAllowedLocales($storeId);
            if (!empty($allowedLocales) && !in_array($locale, $allowedLocales)) {
                $output->writeln('<error>Locale not allowed: ' . $locale . '</error>');
                $output->writeln('Allowed locales: ' . implode(', ', $allowedLocales));
                return Cli::RETURN_FAILURE;
            }
        }

        // Validate customer type
        $validTypes = ['random', 'existing', 'new', 'guest'];
        if (!in_array($customerType, $validTypes)) {
            $output->writeln('<error>Invalid customer type: ' . $customerType . '</error>');
            $output->writeln('Valid types: ' . implode(', ', $validTypes));
            return Cli::RETURN_FAILURE;
        }

        $output->writeln('<info>Generating ' . $count . ' orders...</info>');
        
        // Display test configuration
        if ($tag) {
            $output->writeln('<comment>Tag: ' . $tag . '</comment>');
        }

        // Create generator config
        $config = $this->generatorConfigFactory->create();
        $config->setOption('count', $count);
        $config->setStoreId($storeId);
        $config->setOption('customer_type', $customerType);

        // Add tag to order comments if provided
        if ($tag) {
            $config->setOption('order_comment', 'Test Order - Tag: ' . $tag);
            $config->setOption('tag', $tag);
        }

        if ($skus) {
            $config->setOption('product_skus', array_map('trim', explode(',', $skus)));
        }

        if ($locale) {
            $config->setLocale($locale);
        }

        // Payment method
        if ($paymentMethod = $input->getOption(self::OPTION_PAYMENT_METHOD)) {
            $config->setOption('payment_method', $paymentMethod);
        }

        // Shipping method
        if ($shippingMethod = $input->getOption(self::OPTION_SHIPPING_METHOD)) {
            $config->setOption('shipping_method', $shippingMethod);
        }

        // Product type filter
        if ($productType = $input->getOption(self::OPTION_PRODUCT_TYPE)) {
            $config->setOption('product_type', $productType);
        }

        // Item count
        if ($itemCount = (int) $input->getOption(self::OPTION_ITEM_COUNT)) {
            $config->setOption('item_count', $itemCount);
        }

        // Currency
        if ($currency = $input->getOption(self::OPTION_CURRENCY)) {
            $config->setOption('currency', $currency);
        }

        // Boolean flags
        if ($input->getOption(self::OPTION_WITH_DISCOUNT)) {
            $config->setOption('with_discount', true);
        }

        if ($input->getOption(self::OPTION_TAX_EXEMPT)) {
            $config->setOption('tax_exempt', true);
        }

        if ($input->getOption(self::OPTION_PARTIAL_INVOICE)) {
            $config->setOption('partial_invoice', true);
        }

        if ($input->getOption(self::OPTION_MULTI_ADDRESS)) {
            $config->setOption('multi_address', true);
        }

        // Order status
        if ($orderStatus = $input->getOption(self::OPTION_ORDER_STATUS)) {
            $config->setOption('order_status', $orderStatus);
        }

        // Override invoice/shipment creation if specified
        if ($input->getOption(self::OPTION_WITH_INVOICE)) {
            $config->setOption('force_invoice', true);
        }
        if ($input->getOption(self::OPTION_WITH_SHIPMENT)) {
            $config->setOption('force_shipment', true);
        }

        // Progress bar
        $progressBar = new ProgressBar($output, $count);
        $progressBar->start();

        try {
            $result = $this->orderGenerator->generate($config);
            $progressBar->finish();
            $output->writeln('');

            $output->writeln('<info>Order generation completed!</info>');

            $metadata = $result->getMetadata();
            $successCount = $metadata['total_generated'] ?? 0;
            $failureCount = $metadata['total_failed'] ?? 0;

            $output->writeln(sprintf(
                '<comment>Success: %d, Failed: %d</comment>',
                $successCount,
                $failureCount
            ));

            // Show created order IDs
            if ($successCount > 0 && !empty($metadata['orders'])) {
                $output->writeln('<comment>Created orders:</comment>');
                foreach ($metadata['orders'] as $order) {
                    $orderLine = sprintf('  - %s (ID: %d)', $order['increment_id'], $order['id']);
                    if ($tag) {
                        $orderLine .= ' [' . $tag . ']';
                    }
                    $output->writeln($orderLine);
                }
            }

            // Show failures
            $errors = $result->getErrors();
            if (!empty($errors)) {
                $output->writeln('<error>Failures:</error>');
                foreach ($errors as $error) {
                    $output->writeln('  - ' . $error);
                }
            }

            return $failureCount > 0 ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;

        } catch (\Exception $e) {
            $progressBar->finish();
            $output->writeln('');
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }
}