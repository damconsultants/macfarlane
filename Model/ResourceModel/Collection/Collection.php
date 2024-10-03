<?php

namespace DamConsultants\Macfarlane\Model\ResourceModel\Collection;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Collection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Macfarlane\Model\Bynder::class,
            \DamConsultants\Macfarlane\Model\ResourceModel\Bynder::class
        );
    }
}
