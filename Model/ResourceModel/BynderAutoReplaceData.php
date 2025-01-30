<?php

namespace DamConsultants\Macfarlane\Model\ResourceModel;

class BynderAutoReplaceData extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Bynder Syc Data
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init('bynder_cron_replace_data', 'id');
    }
}
