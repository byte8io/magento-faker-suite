<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Model\Validator;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Validator\EmailAddress;

/**
 * Customer Validator
 *
 * Validates customer data before saving
 */
class CustomerValidator
{
    /**
     * Constructor
     *
     * @param EmailAddress $emailValidator
     */
    public function __construct(
        private EmailAddress $emailValidator
    ) {
    }

    /**
     * Validate customer data
     *
     * @param CustomerInterface $customer
     * @return array List of validation errors
     */
    public function validate(CustomerInterface $customer): array
    {
        $errors = [];

        // Validate required fields
        if (empty($customer->getEmail())) {
            $errors[] = 'Email is required';
        } elseif (!$this->emailValidator->isValid($customer->getEmail())) {
            $errors[] = 'Invalid email format';
        }

        if (empty($customer->getFirstname())) {
            $errors[] = 'First name is required';
        }

        if (empty($customer->getLastname())) {
            $errors[] = 'Last name is required';
        }

        if (!$customer->getWebsiteId()) {
            $errors[] = 'Website ID is required';
        }

        if ($customer->getStoreId() === null) {
            $errors[] = 'Store ID is required';
        }

        // Validate date of birth format if provided
        if ($dob = $customer->getDob()) {
            $date = \DateTime::createFromFormat('Y-m-d', $dob);
            if (!$date || $date->format('Y-m-d') !== $dob) {
                $errors[] = 'Invalid date of birth format. Expected: YYYY-MM-DD';
            } else {
                // Check age (must be at least 18)
                $now = new \DateTime();
                $age = $now->diff($date)->y;
                if ($age < 18) {
                    $errors[] = 'Customer must be at least 18 years old';
                }
                if ($age > 120) {
                    $errors[] = 'Invalid date of birth';
                }
            }
        }

        // Validate gender if provided
        if ($gender = $customer->getGender()) {
            if (!in_array($gender, [1, 2, 3])) { // 1=Male, 2=Female, 3=Not Specified
                $errors[] = 'Invalid gender value';
            }
        }

        return $errors;
    }

    /**
     * Validate address data
     *
     * @param array $addressData
     * @return array List of validation errors
     */
    public function validateAddress(array $addressData): array
    {
        $errors = [];

        $requiredFields = [
            'firstname' => 'First name',
            'lastname' => 'Last name',
            'street' => 'Street address',
            'city' => 'City',
            'country_id' => 'Country',
            'telephone' => 'Phone number'
        ];

        foreach ($requiredFields as $field => $label) {
            if (empty($addressData[$field])) {
                $errors[] = $label . ' is required';
            }
        }

        // Validate phone format
        if (!empty($addressData['telephone'])) {
            if (!$this->isValidPhone($addressData['telephone'])) {
                $errors[] = 'Invalid phone number format';
            }
        }

        return $errors;
    }

    /**
     * Validate phone number
     *
     * @param string $phone
     * @return bool
     */
    private function isValidPhone(string $phone): bool
    {
        // Allow numbers, spaces, dashes, parentheses, and plus sign
        $pattern = '/^[0-9\s\-\(\)\+]+$/';

        // Must have at least 10 digits
        $digitsOnly = preg_replace('/[^0-9]/', '', $phone);

        return preg_match($pattern, $phone) && strlen($digitsOnly) >= 10;
    }
}
