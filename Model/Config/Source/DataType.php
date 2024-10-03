<?php
/**
 * DamConsultants
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the ecomteck.com license that is
 * available through the world-wide-web at this URL:
 *
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    DamConsultants
 * @package     DamConsultants_Macfarlane
 */
namespace DamConsultants\Macfarlane\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class DataType implements ArrayInterface
{
    /**
     * To Array
     *
     * @return $this
     */
    public function toOptionArray()
    {
        
        return [
            [
                'value' => 1,
                'label' => __('Image'),
            ],
            [
                'value' => 2,
                'label' => __('Document'),
            ],
            [
                'value' => 3,
                'label' => __('Video'),
            ]
        ];
    }
}
