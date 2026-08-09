<?php

namespace DamConsultants\Macfarlane\Model\ResourceModel\Collection;

class MagentoSkuCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * MagentoSkuCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Macfarlane\Model\MagentoSku::class,
            \DamConsultants\Macfarlane\Model\ResourceModel\MagentoSku::class
        );
    }
}
