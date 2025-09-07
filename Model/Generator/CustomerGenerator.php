<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Model\Generator;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Byte8\FakerSuite\Api\Data\GeneratorConfigInterface;
use Byte8\FakerSuite\Api\Data\GeneratorResultInterface;
use Byte8\FakerSuite\Api\Data\GeneratorResultInterfaceFactory;
use Byte8\FakerSuite\Api\Generator\CustomerGeneratorInterface;
use Byte8\FakerSuite\Model\DataProvider\AddressProvider;
use Byte8\FakerSuite\Model\Validator\CustomerValidator;

/**
 * Customer Generator
 *
 * Generates customer entities with realistic test data
 */
class CustomerGenerator extends AbstractGenerator implements CustomerGeneratorInterface
{
    /**
     * Constructor
     *
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param GeneratorResultInterfaceFactory $resultFactory
     * @param LoggerInterface $logger
     * @param CustomerInterfaceFactory $customerFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param AccountManagementInterface $accountManagement
     * @param AddressInterfaceFactory $addressFactory
     * @param AddressRepositoryInterface $addressRepository
     * @param GroupRepositoryInterface $groupRepository
     * @param AddressProvider $addressProvider
     * @param CustomerValidator $customerValidator
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        GeneratorResultInterfaceFactory $resultFactory,
        LoggerInterface $logger,
        private CustomerInterfaceFactory $customerFactory,
        private CustomerRepositoryInterface $customerRepository,
        private AccountManagementInterface $accountManagement,
        private AddressInterfaceFactory $addressFactory,
        private AddressRepositoryInterface $addressRepository,
        private GroupRepositoryInterface $groupRepository,
        private AddressProvider $addressProvider,
        private CustomerValidator $customerValidator
    ) {
        parent::__construct($storeManager, $scopeConfig, $resultFactory, $logger);
    }

    /**
     * @inheritDoc
     */
    public function generate(GeneratorConfigInterface $config): GeneratorResultInterface
    {
        try {
            $customer = $this->generateCustomer(
                $config->getWebsiteId(),
                $config->getStoreId(),
                $config->getAttributes()
            );

            return $this->createResult(true, $customer);
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate customer: ' . $e->getMessage(), [
                'exception' => $e,
                'config' => $config->getData()
            ]);

            return $this->createResult(false, null, [$e->getMessage()]);
        }
    }

    /**
     * @inheritDoc
     */
    public function generateCustomer(
        ?int $websiteId = null,
        ?int $storeId = null,
        array $overrides = []
    ): CustomerInterface {
        // Get store and website
        $store = $storeId ? $this->storeManager->getStore($storeId) : $this->storeManager->getDefaultStoreView();
        $websiteId = $websiteId ?: $store->getWebsiteId();

        // Get locale from store
        $locale = $this->scopeConfig->getValue(
            'general/locale/code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store->getId()
        );

        $faker = $this->getFaker($locale);

        // Generate customer data
        $email = $overrides['email'] ?? $faker->unique()->safeEmail;
        $firstName = $overrides['firstname'] ?? $faker->firstName;
        $lastName = $overrides['lastname'] ?? $faker->lastName;

        // Create customer
        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId)
            ->setStoreId($store->getId())
            ->setEmail($email)
            ->setFirstname($firstName)
            ->setLastname($lastName);

        // Set optional fields
        if ($faker->boolean(30)) {
            $customer->setMiddlename($faker->firstName);
        }

        if ($faker->boolean(20)) {
            $customer->setPrefix($faker->title);
        }

        if ($faker->boolean(10)) {
            $customer->setSuffix($faker->suffix);
        }

        // Set date of birth for some customers
        if ($faker->boolean(40)) {
            $dob = $faker->dateTimeBetween('-65 years', '-18 years')->format('Y-m-d');
            $customer->setDob($dob);
        }

        // Set gender
        if ($faker->boolean(60)) {
            $customer->setGender($faker->numberBetween(1, 2)); // 1 = Male, 2 = Female
        }

        // Set tax/VAT number for some customers
        if ($faker->boolean(15)) {
            $customer->setTaxvat($this->generateTaxVat($locale));
        }

        // Apply any overrides
        foreach ($overrides as $key => $value) {
            $setter = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($customer, $setter)) {
                $customer->$setter($value);
            }
        }

        // Validate before saving
        $validationErrors = $this->customerValidator->validate($customer);
        if (!empty($validationErrors)) {
            throw new LocalizedException(
                __('Customer validation failed: %1', implode(', ', $validationErrors))
            );
        }

        // Generate password
        $password = $overrides['password'] ?? $this->generatePassword();

        // Create account
        $customer = $this->accountManagement->createAccount($customer, $password);

        $this->log('Generated customer', [
            'customer_id' => $customer->getId(),
            'email' => $customer->getEmail(),
            'website_id' => $customer->getWebsiteId()
        ]);

        return $customer;
    }

    /**
     * @inheritDoc
     */
    public function generateCustomerWithAddresses(
        ?int $websiteId = null,
        ?int $storeId = null,
        int $addressCount = 1,
        array $overrides = []
    ): CustomerInterface {
        // First create the customer
        $customer = $this->generateCustomer($websiteId, $storeId, $overrides);

        // Get locale from customer's store
        $locale = $this->scopeConfig->getValue(
            'general/locale/code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $customer->getStoreId()
        );

        // Generate addresses
        $addresses = [];
        for ($i = 0; $i < $addressCount; $i++) {
            try {
                $addressData = $this->addressProvider->getRandom($locale);

                $address = $this->addressFactory->create();
                $address->setCustomerId($customer->getId())
                    ->setFirstname($addressData['firstname'])
                    ->setLastname($addressData['lastname'])
                    ->setStreet(array_filter((array) $addressData['street']))
                    ->setCity($addressData['city'])
                    ->setCountryId($addressData['country_id'])
                    ->setPostcode($addressData['postcode'])
                    ->setTelephone($addressData['telephone']);

                if (!empty($addressData['company'])) {
                    $address->setCompany($addressData['company']);
                }

                if (!empty($addressData['region_id'])) {
                    $address->setRegionId($addressData['region_id']);
                }

                if (!empty($addressData['region'])) {
                    $address->setRegion($addressData['region']);
                }

                // Set first address as default
                if ($i === 0) {
                    $address->setIsDefaultBilling(true)
                        ->setIsDefaultShipping(true);
                }

                $addresses[] = $this->addressRepository->save($address);

            } catch (\Exception $e) {
                $this->logger->warning('Failed to create address for customer: ' . $e->getMessage());
            }
        }

        $this->log('Generated customer with addresses', [
            'customer_id' => $customer->getId(),
            'address_count' => count($addresses)
        ]);

        return $customer;
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * @inheritDoc
     */
    protected function validateSpecific(GeneratorConfigInterface $config): array
    {
        $errors = [];

        // Validate email if provided
        if ($email = $config->getAttributes()['email'] ?? null) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }
        }

        // Validate group_id if provided
        if ($groupId = $config->getAttributes()['group_id'] ?? null) {
            try {
                $this->groupRepository->getById($groupId);
            } catch (\Exception $e) {
                $errors[] = sprintf('Invalid customer group ID: %s', $groupId);
            }
        }

        return $errors;
    }

    /**
     * Generate secure password
     *
     * @return string
     */
    private function generatePassword(): string
    {
        $faker = $this->getFaker();

        // Generate a secure password with mixed case, numbers, and special chars
        $password = $faker->lexify('??????????'); // 10 letters
        $password = ucfirst($password); // Capitalize first letter
        $password .= $faker->numerify('###'); // Add 3 numbers
        $password .= $faker->randomElement(['!', '@', '#', '$', '%', '&', '*']); // Add special char

        return $password;
    }

    /**
     * Generate tax/VAT number
     *
     * @param string|null $locale
     * @return string
     */
    private function generateTaxVat(?string $locale): string
    {
        $faker = $this->getFaker($locale);

        // Generate based on locale
        $formats = [
            'de_DE' => 'DE#########', // German VAT
            'fr_FR' => 'FR##########', // French VAT
            'it_IT' => 'IT###########', // Italian VAT
            'es_ES' => 'ES#########', // Spanish VAT
            'nl_NL' => 'NL#########B##', // Dutch VAT
            'default' => '##-#######' // US EIN format
        ];

        $format = $formats[$locale] ?? $formats['default'];

        return preg_replace_callback('/#/', function() {
            return (string) rand(0, 9);
        }, $format);
    }
}
