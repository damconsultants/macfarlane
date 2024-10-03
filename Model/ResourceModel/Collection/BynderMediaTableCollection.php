<?php

namespace DamConsultants\Macfarlane\Model\ResourceModel\Collection;

class BynderMediaTableCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Macfarlane\Model\BynderMediaTable::class,
            \DamConsultants\Macfarlane\Model\ResourceModel\BynderMediaTable::class
        );
    }
}
