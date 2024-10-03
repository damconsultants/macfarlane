<?php

namespace DamConsultants\Macfarlane\Model\ResourceModel\Collection;

class BynderTempDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Macfarlane\Model\BynderTempData::class,
            \DamConsultants\Macfarlane\Model\ResourceModel\BynderTempData::class
        );
    }
}
