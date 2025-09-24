# Faker Suite Module for Magento 2

Generate realistic test data for Magento 2 development and testing.

## Features

- **Customer Generation**: Create fake customers with realistic names, addresses, and contact information
- **Order Generation**: Generate test orders with configurable products, shipping, and payment methods
- **Locale Support**: Generate data appropriate for different countries/languages
- **Validation**: Built-in validation ensures generated data passes Magento's validation rules
- **Batch Generation**: Create multiple entities at once
- **Console Commands**: Easy-to-use CLI commands for generating data

## Installation

```bash
composer require byte8/module-faker-suite
bin/magento module:enable Byte8_FakerSuite
bin/magento setup:upgrade
```

## Usage

### Generate Customers

```bash
# Generate 10 customers (default)
bin/magento faker:customer

# Generate 50 customers with addresses
bin/magento faker:customer -c 50 --with-addresses

# Generate customers for specific store
bin/magento faker:customer -c 20 -s 1

# Generate customers with 3 addresses each
bin/magento faker:customer -c 10 --with-addresses --address-count=3

# Generate customers for specific locale
bin/magento faker:customer -c 10 -l de_DE
```

### Generate Orders

```bash
# Generate 10 orders
bin/magento faker:order -c 10

# Generate orders for specific store
bin/magento faker:order -c 5 -s 1

# Generate orders with specific products
bin/magento faker:order -c 10 --sku="SKU1,SKU2,SKU3"

# Generate orders and create invoices
bin/magento faker:order -c 5 --with-invoice
```

## Architecture

The module follows a clean, extensible architecture:

```
Api/
├── Generator/           # Generator interfaces
├── DataProvider/        # Data provider interfaces
└── Data/               # Data transfer objects

Model/
├── Generator/          # Generator implementations
├── DataProvider/       # Data providers (phone, address, etc.)
└── Validator/          # Data validators
```

## Key Components

### Generators
- `CustomerGenerator`: Creates customer accounts with optional addresses
- `OrderGenerator`: Creates orders with products, shipping, and payment

### Data Providers
- `PhoneProvider`: Generates valid phone numbers for different countries
- `AddressProvider`: Creates realistic addresses with proper formatting

### Validators
- `CustomerValidator`: Ensures customer data meets Magento requirements
- `OrderValidator`: Validates order data before creation

## Extending

You can create custom generators by:

1. Implementing `GeneratorInterface`
2. Extending `AbstractGenerator` for common functionality
3. Registering your generator in `di.xml`

Example:
```php
class ProductGenerator extends AbstractGenerator implements ProductGeneratorInterface
{
    public function generate(GeneratorConfigInterface $config): GeneratorResultInterface
    {
        // Your generation logic
    }
}
```

## Configuration

The module uses smart defaults but can be configured:

- Default locale detection from store configuration
- Automatic website/store assignment
- Configurable data patterns

## Best Practices

1. **Test Environment Only**: This module should only be used in development/test environments
2. **Batch Sizes**: Keep batch sizes reasonable (< 100) to avoid memory issues
3. **Cleanup**: Remember to clean up test data when done

## Support

For issues or questions, contact support@Byte8.io
