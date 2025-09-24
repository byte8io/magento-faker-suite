<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Byte8\FakerSuite\Api\Data\GeneratorConfigInterfaceFactory;
use Byte8\FakerSuite\Api\Generator\CustomerGeneratorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;

/**
 * Generate Customers Command
 *
 * Console command to generate fake customers
 */
class GenerateCustomersCommand extends Command
{
    private const COMMAND_NAME = 'faker:customer';
    private const COUNT_OPTION = 'count';
    private const WEBSITE_OPTION = 'website';
    private const STORE_OPTION = 'store';
    private const WITH_ADDRESSES_OPTION = 'with-addresses';
    private const ADDRESS_COUNT_OPTION = 'address-count';
    private const GROUP_ID_OPTION = 'group';
    private const LOCALE_OPTION = 'locale';

    /**
     * Constructor
     *
     * @param State $appState
     * @param CustomerGeneratorInterface $customerGenerator
     * @param GeneratorConfigInterfaceFactory $configFactory
     * @param string|null $name
     */
    public function __construct(
        private State $appState,
        private CustomerGeneratorInterface $customerGenerator,
        private GeneratorConfigInterfaceFactory $configFactory,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Generate fake customers for testing')
            ->setDefinition([
                new InputOption(
                    self::COUNT_OPTION,
                    '-c',
                    InputOption::VALUE_REQUIRED,
                    'Number of customers to generate',
                    '10'
                ),
                new InputOption(
                    self::WEBSITE_OPTION,
                    '-w',
                    InputOption::VALUE_REQUIRED,
                    'Website ID'
                ),
                new InputOption(
                    self::STORE_OPTION,
                    '-s',
                    InputOption::VALUE_REQUIRED,
                    'Store ID'
                ),
                new InputOption(
                    self::WITH_ADDRESSES_OPTION,
                    '-a',
                    InputOption::VALUE_NONE,
                    'Generate with addresses'
                ),
                new InputOption(
                    self::ADDRESS_COUNT_OPTION,
                    null,
                    InputOption::VALUE_REQUIRED,
                    'Number of addresses per customer',
                    '1'
                ),
                new InputOption(
                    self::GROUP_ID_OPTION,
                    '-g',
                    InputOption::VALUE_REQUIRED,
                    'Customer group ID'
                ),
                new InputOption(
                    self::LOCALE_OPTION,
                    '-l',
                    InputOption::VALUE_REQUIRED,
                    'Locale for data generation (e.g., en_US, de_DE)'
                )
            ]);

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
            // Area already set
        }

        $count = (int) $input->getOption(self::COUNT_OPTION);
        $withAddresses = $input->getOption(self::WITH_ADDRESSES_OPTION);
        $addressCount = (int) $input->getOption(self::ADDRESS_COUNT_OPTION);

        $output->writeln('<info>Starting customer generation...</info>');
        $output->writeln(sprintf(
            'Generating <comment>%d</comment> customers%s',
            $count,
            $withAddresses ? sprintf(' with <comment>%d</comment> addresses each', $addressCount) : ''
        ));
        $output->writeln('');

        // Create configuration
        $config = $this->configFactory->create();

        if ($websiteId = $input->getOption(self::WEBSITE_OPTION)) {
            $config->setWebsiteId((int) $websiteId);
        }

        if ($storeId = $input->getOption(self::STORE_OPTION)) {
            $config->setStoreId((int) $storeId);
        }

        if ($locale = $input->getOption(self::LOCALE_OPTION)) {
            $config->setLocale($locale);
        }

        if ($groupId = $input->getOption(self::GROUP_ID_OPTION)) {
            $config->setAttributes(['group_id' => (int) $groupId]);
        }

        $config->setOption('with_addresses', $withAddresses);
        $config->setOption('address_count', $addressCount);

        // Validate configuration
        $errors = $this->customerGenerator->validate($config);
        if (!empty($errors)) {
            $output->writeln('<error>Configuration validation failed:</error>');
            foreach ($errors as $error) {
                $output->writeln(sprintf('  - %s', $error));
            }
            return Cli::RETURN_FAILURE;
        }

        // Create progress bar
        $progressBar = new ProgressBar($output, $count);
        $progressBar->setFormat('verbose');
        $progressBar->start();

        $successful = [];
        $failed = [];

        // Generate customers
        for ($i = 0; $i < $count; $i++) {
            try {
                $result = $this->customerGenerator->generate($config);

                if ($result->isSuccess()) {
                    $customer = $result->getEntity();
                    $successful[] = [
                        'id' => $customer->getId(),
                        'email' => $customer->getEmail(),
                        'name' => $customer->getFirstname() . ' ' . $customer->getLastname()
                    ];
                } else {
                    $failed[] = [
                        'index' => $i + 1,
                        'error' => implode('; ', $result->getErrors())
                    ];
                }
            } catch (\Exception $e) {
                $failed[] = [
                    'index' => $i + 1,
                    'error' => $e->getMessage()
                ];
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln("\n");

        // Display summary
        $this->displaySummary($output, $successful, $failed);

        return empty($failed) ? Cli::RETURN_SUCCESS : Cli::RETURN_FAILURE;
    }

    /**
     * Display generation summary
     *
     * @param OutputInterface $output
     * @param array $successful
     * @param array $failed
     * @return void
     */
    private function displaySummary(OutputInterface $output, array $successful, array $failed): void
    {
        $output->writeln('<info>Generation Complete!</info>');
        $output->writeln(sprintf(
            'Successfully created: <info>%d</info> customers',
            count($successful)
        ));

        if (!empty($failed)) {
            $output->writeln(sprintf(
                'Failed: <error>%d</error> customers',
                count($failed)
            ));
        }

        // Show sample of created customers
        if (!empty($successful)) {
            $output->writeln("\n<comment>Sample of created customers:</comment>");

            $table = new Table($output);
            $table->setHeaders(['ID', 'Email', 'Name']);

            // Show first 10 customers
            $sample = array_slice($successful, 0, 10);
            foreach ($sample as $customer) {
                $table->addRow([
                    $customer['id'],
                    $customer['email'],
                    $customer['name']
                ]);
            }

            $table->render();

            if (count($successful) > 10) {
                $output->writeln(sprintf(
                    '<comment>... and %d more</comment>',
                    count($successful) - 10
                ));
            }
        }

        // Show errors if any
        if (!empty($failed)) {
            $output->writeln("\n<error>Failed generations:</error>");
            foreach ($failed as $failure) {
                $output->writeln(sprintf(
                    '  - Customer #%d: %s',
                    $failure['index'],
                    $failure['error']
                ));
            }
        }
    }
}
