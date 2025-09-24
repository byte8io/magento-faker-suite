<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\FakerSuite\Model\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;

class GenerateFormDataProvider extends AbstractDataProvider
{
    protected $loadedData;
    
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        // Return default data
        $this->loadedData = [
            '' => [
                'customer_count' => 10,
                'customer_with_addresses' => 1,
                'order_count' => 10,
                'customer_type' => 'random',
                'store_id' => 1
            ]
        ];

        return $this->loadedData;
    }
}