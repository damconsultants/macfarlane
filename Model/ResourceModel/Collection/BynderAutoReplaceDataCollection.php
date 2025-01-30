<?php

namespace DamConsultants\Macfarlane\Model\ResourceModel\Collection;

class BynderAutoReplaceDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Macfarlane\Model\BynderAutoReplaceData::class,
            \DamConsultants\Macfarlane\Model\ResourceModel\BynderAutoReplaceData::class
        );
    }
}
