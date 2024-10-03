<?php

namespace DamConsultants\Macfarlane\Model\ResourceModel\Collection;

class DefaultMetaPropertyCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * MetaPropertyCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Macfarlane\Model\DefaultMetaProperty::class,
            \DamConsultants\Macfarlane\Model\ResourceModel\DefaultMetaProperty::class
        );
    }
}
