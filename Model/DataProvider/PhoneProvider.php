<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Model\DataProvider;

use Byte8\FakerSuite\Api\DataProvider\DataProviderInterface;

/**
 * Phone Number Provider
 *
 * Provides valid phone numbers for different locales that pass Magento validation
 */
class PhoneProvider implements DataProviderInterface
{
    /**
     * Phone formats by locale
     */
    private const PHONE_FORMATS = [
        'en_US' => [
            '###-###-####',
            '(###) ###-####',
            '### ### ####',
            '+1 ### ### ####'
        ],
        'en_GB' => [
            '#### ######',
            '#####-######',
            '+44 #### ######',
            '0#### ######'
        ],
        'de_DE' => [
            '#### #######',
            '####-#######',
            '+49 #### #######',
            '0#### #######'
        ],
        'fr_FR' => [
            '## ## ## ## ##',
            '+33 # ## ## ## ##',
            '0# ## ## ## ##'
        ],
        'default' => [
            '+# ### ### ####',
            '### ### ####'
        ]
    ];

    /**
     * @inheritDoc
     */
    public function getRandom(?string $locale = null)
    {
        $locale = $locale ?: 'default';
        $formats = self::PHONE_FORMATS[$locale] ?? self::PHONE_FORMATS['default'];
        $format = $formats[array_rand($formats)];

        return $this->generateFromFormat($format);
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(int $count, ?string $locale = null): array
    {
        $phones = [];
        for ($i = 0; $i < $count; $i++) {
            $phones[] = $this->getRandom($locale);
        }
        return $phones;
    }

    /**
     * @inheritDoc
     */
    public function isLocaleSupported(string $locale): bool
    {
        return isset(self::PHONE_FORMATS[$locale]);
    }

    /**
     * @inheritDoc
     */
    public function getSupportedLocales(): array
    {
        return array_keys(self::PHONE_FORMATS);
    }

    /**
     * Generate phone number from format
     *
     * @param string $format
     * @return string
     */
    private function generateFromFormat(string $format): string
    {
        return preg_replace_callback('/#/', function() {
            return (string) rand(0, 9);
        }, $format);
    }

    /**
     * Get US phone number
     *
     * @return string
     */
    public function getUSPhone(): string
    {
        return sprintf(
            '%03d-%03d-%04d',
            rand(201, 999), // Area code (avoid 0xx and 1xx)
            rand(200, 999), // Exchange (avoid 0xx and 1xx)
            rand(1000, 9999)
        );
    }

    /**
     * Get UK phone number
     *
     * @return string
     */
    public function getUKPhone(): string
    {
        return sprintf(
            '0%d%d %d%d%d %d%d%d%d',
            rand(1, 9),
            rand(0, 9),
            rand(0, 9),
            rand(0, 9),
            rand(0, 9),
            rand(0, 9),
            rand(0, 9),
            rand(0, 9),
            rand(0, 9)
        );
    }

    /**
     * Get German phone number
     *
     * @return string
     */
    public function getGermanPhone(): string
    {
        return sprintf(
            '0%d%d %d%d%d%d%d%d%d',
            rand(1, 9),
            rand(0, 9),
            rand(0, 9),
            rand(0, 9),
            rand(0, 9),
            rand(0, 9),
            rand(0, 9),
            rand(0, 9),
            rand(0, 9)
        );
    }
}
