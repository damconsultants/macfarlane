<?php

namespace DamConsultants\Macfarlane\Model\ResourceModel\Collection;

class BynderConfigSyncDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Macfarlane\Model\BynderConfigSyncData::class,
            \DamConsultants\Macfarlane\Model\ResourceModel\BynderConfigSyncData::class
        );
    }
}
