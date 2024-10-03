<?php

namespace DamConsultants\Macfarlane\Model\ResourceModel\Collection;

class BynderTempDocDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Macfarlane\Model\BynderTempDocData::class,
            \DamConsultants\Macfarlane\Model\ResourceModel\BynderTempDocData::class
        );
    }
}
