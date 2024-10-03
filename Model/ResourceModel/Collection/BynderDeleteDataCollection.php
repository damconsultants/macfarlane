<?php

namespace DamConsultants\Macfarlane\Model\ResourceModel\Collection;

class BynderDeleteDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Macfarlane\Model\BynderDeleteData::class,
            \DamConsultants\Macfarlane\Model\ResourceModel\BynderDeleteData::class
        );
    }
}
