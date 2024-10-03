<?php

namespace DamConsultants\Macfarlane\Model\ResourceModel\Collection;

class MetaPropertyCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * MetaPropertyCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Macfarlane\Model\MetaProperty::class,
            \DamConsultants\Macfarlane\Model\ResourceModel\MetaProperty::class
        );
    }
}
