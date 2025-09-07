<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Model\DataProvider;

use Faker\Factory as FakerFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Byte8\FakerSuite\Api\DataProvider\DataProviderInterface;

/**
 * Address Provider
 *
 * Provides valid addresses for different countries
 */
class AddressProvider implements DataProviderInterface
{
    /**
     * Constructor
     *
     * @param RegionFactory $regionFactory
     * @param RegionCollectionFactory $regionCollectionFactory
     * @param PhoneProvider $phoneProvider
     */
    public function __construct(
        private RegionFactory $regionFactory,
        private RegionCollectionFactory $regionCollectionFactory,
        private PhoneProvider $phoneProvider
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getRandom(?string $locale = null)
    {
        $faker = FakerFactory::create($locale ?: 'en_US');
        $countryCode = $this->getCountryCodeFromLocale($locale);

        // Get region data
        $regionData = $this->getRandomRegion($countryCode);

        return [
            'firstname' => $faker->firstName,
            'lastname' => $faker->lastName,
            'company' => $faker->boolean(30) ? $faker->company : null,
            'street' => [
                $faker->streetAddress,
                $faker->boolean(20) ? $faker->secondaryAddress : null
            ],
            'city' => $faker->city,
            'country_id' => $countryCode,
            'region' => $regionData['name'] ?? null,
            'region_id' => $regionData['id'] ?? null,
            'postcode' => $this->getPostcode($countryCode, $faker),
            'telephone' => $this->phoneProvider->getRandom($locale)
        ];
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(int $count, ?string $locale = null): array
    {
        $addresses = [];
        for ($i = 0; $i < $count; $i++) {
            $addresses[] = $this->getRandom($locale);
        }
        return $addresses;
    }

    /**
     * @inheritDoc
     */
    public function isLocaleSupported(string $locale): bool
    {
        // We support all locales through Faker
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getSupportedLocales(): array
    {
        return [
            'en_US', 'en_GB', 'de_DE', 'fr_FR', 'es_ES',
            'it_IT', 'nl_NL', 'pt_BR', 'ja_JP', 'zh_CN'
        ];
    }

    /**
     * Get country code from locale
     *
     * @param string|null $locale
     * @return string
     */
    private function getCountryCodeFromLocale(?string $locale): string
    {
        if (!$locale) {
            return 'US';
        }

        $parts = explode('_', $locale);
        return end($parts);
    }

    /**
     * Get random region for country
     *
     * @param string $countryCode
     * @return array
     */
    private function getRandomRegion(string $countryCode): array
    {
        $collection = $this->regionCollectionFactory->create();
        $collection->addFieldToFilter('country_id', $countryCode);

        if ($collection->getSize() === 0) {
            return [];
        }

        $regions = $collection->toArray()['items'];
        $region = $regions[array_rand($regions)];

        return [
            'id' => $region['region_id'] ?? null,
            'name' => $region['default_name'] ?? null,
            'code' => $region['code'] ?? null
        ];
    }

    /**
     * Get valid postcode for country
     *
     * @param string $countryCode
     * @param \Faker\Generator $faker
     * @return string
     */
    private function getPostcode(string $countryCode, $faker): string
    {
        // Country-specific postcode formats
        $formats = [
            'US' => '#####',
            'GB' => function() {
                $areas = ['SW', 'SE', 'NW', 'NE', 'W', 'E', 'N', 'EC', 'WC'];
                $area = $areas[array_rand($areas)];
                return $area . rand(1, 20) . ' ' . rand(1, 9) . chr(rand(65, 90)) . chr(rand(65, 90));
            },
            'DE' => '#####',
            'FR' => '#####',
            'CA' => function() {
                $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                return $letters[rand(0, 25)] . rand(0, 9) . $letters[rand(0, 25)] . ' ' .
                       rand(0, 9) . $letters[rand(0, 25)] . rand(0, 9);
            },
            'AU' => '####',
            'JP' => '###-####'
        ];

        if (isset($formats[$countryCode])) {
            if (is_callable($formats[$countryCode])) {
                return $formats[$countryCode]();
            }
            return preg_replace_callback('/#/', function() {
                return (string) rand(0, 9);
            }, $formats[$countryCode]);
        }

        // Default to faker's postcode
        return $faker->postcode;
    }
}
