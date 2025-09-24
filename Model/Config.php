<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'byte8_faker_suite/general/enabled';
    private const XML_PATH_ALLOWED_LOCALES = 'byte8_faker_suite/general/allowed_locales';
    private const XML_PATH_DEFAULT_EMAIL_DOMAIN = 'byte8_faker_suite/general/default_email_domain';
    
    private const XML_PATH_NAME_PREFIX = 'byte8_faker_suite/customer/name_prefix';
    private const XML_PATH_SURNAME_PREFIX = 'byte8_faker_suite/customer/surname_prefix';
    private const XML_PATH_ADDRESS_PREFIX = 'byte8_faker_suite/customer/address_prefix';
    private const XML_PATH_EMAIL_PREFIX = 'byte8_faker_suite/customer/email_prefix';
    
    private const XML_PATH_ALLOWED_PAYMENT_METHODS = 'byte8_faker_suite/order/allowed_payment_methods';
    private const XML_PATH_ALLOWED_SHIPPING_METHODS = 'byte8_faker_suite/order/allowed_shipping_methods';
    private const XML_PATH_INVOICE_CHANCE = 'byte8_faker_suite/order/invoice_chance';
    private const XML_PATH_SHIPMENT_CHANCE = 'byte8_faker_suite/order/shipment_chance';
    private const XML_PATH_CREDITMEMO_CHANCE = 'byte8_faker_suite/order/creditmemo_chance';
    
    private const XML_PATH_CRON_ENABLED = 'byte8_faker_suite/cron/enabled';
    private const XML_PATH_CRON_EXPRESSION = 'byte8_faker_suite/cron/expression';
    private const XML_PATH_CRON_CUSTOMER_COUNT = 'byte8_faker_suite/cron/customer_count';
    private const XML_PATH_CRON_ORDER_COUNT = 'byte8_faker_suite/cron/order_count';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getAllowedLocales(?int $storeId = null): array
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_ALLOWED_LOCALES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        return $value ? explode(',', $value) : [];
    }

    public function getDefaultEmailDomain(?int $storeId = null): string
    {
        $domain = (string) $this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_EMAIL_DOMAIN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        // Fallback to example.com if not configured
        return $domain ?: 'example.com';
    }

    public function getNamePrefix(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_NAME_PREFIX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSurnamePrefix(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_SURNAME_PREFIX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getAddressPrefix(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_ADDRESS_PREFIX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getEmailPrefix(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_PREFIX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getAllowedPaymentMethods(?int $storeId = null): array
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_ALLOWED_PAYMENT_METHODS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        return $value ? explode(',', $value) : [];
    }

    public function getAllowedShippingMethods(?int $storeId = null): array
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_ALLOWED_SHIPPING_METHODS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        return $value ? explode(',', $value) : [];
    }

    public function getInvoiceChance(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_INVOICE_CHANCE,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    public function getShipmentChance(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_SHIPMENT_CHANCE,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    public function getCreditmemoChance(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_CREDITMEMO_CHANCE,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    public function isCronEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_CRON_ENABLED,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    public function getCronExpression(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_CRON_EXPRESSION,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    public function getCronCustomerCount(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_CRON_CUSTOMER_COUNT,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    public function getCronOrderCount(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_CRON_ORDER_COUNT,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }
}