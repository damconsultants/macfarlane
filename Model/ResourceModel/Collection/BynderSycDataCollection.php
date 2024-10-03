<?php

namespace DamConsultants\Macfarlane\Model\ResourceModel\Collection;

class BynderSycDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderSycDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Macfarlane\Model\BynderSycData::class,
            \DamConsultants\Macfarlane\Model\ResourceModel\BynderSycData::class
        );
    }
}
