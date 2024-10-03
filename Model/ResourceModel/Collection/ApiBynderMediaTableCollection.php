<?php

namespace DamConsultants\Macfarlane\Model\ResourceModel\Collection;

class ApiBynderMediaTableCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Macfarlane\Model\ApiBynderMediaTable::class,
            \DamConsultants\Macfarlane\Model\ResourceModel\ApiBynderMediaTable::class
        );
    }
}
