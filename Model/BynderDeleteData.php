<?php

namespace DamConsultants\Macfarlane\Model;

class BynderDeleteData extends \Magento\Framework\Model\AbstractModel
{
    protected const CACHE_TAG = 'DamConsultants_Macfarlane';

    /**
     * @var $_cacheTag
     */
    protected $_cacheTag = 'DamConsultants_Macfarlane';

    /**
     * @var $_eventPrefix
     */
    protected $_eventPrefix = 'DamConsultants_Macfarlane';

    /**
     * BynderDAM Syc Data
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(\DamConsultants\Macfarlane\Model\ResourceModel\BynderDeleteData::class);
    }
}
