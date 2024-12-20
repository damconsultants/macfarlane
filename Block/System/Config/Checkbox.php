<?php

namespace DamConsultants\Macfarlane\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;

class Checkbox extends Field
{
    /**
     * Block template.
     *
     * @var string
     */
	protected $_template = 'DamConsultants_Macfarlane::system/config/checkbox.phtml';
    /**
     * @var $_storeManager
     */
    protected $_storeManager;
    /**
     * @var $HelperBackend
     */
    protected $HelperBackend;
    /**
     * @var $_toHtml
     */
    protected $_toHtml;
    /**
     * @var $getUrl
     */
    protected $getUrl;
    /**
     * Retrieve element HTML markup.
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->setNamePrefix($element->getName())
            ->setHtmlId($element->getHtmlId());
        return $this->_toHtml();
    }
    
    /**
     * Getvalue.
     *
     * @return $this
     */
    public function getValues()
    {
        $values = [];
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $val = $objectManager->create(\DamConsultants\Macfarlane\Model\Config\Source\Checkbox::class);
        $valuess = $val->toOptionArray();
        foreach ($valuess as $value) {
            $values[$value['value']] = $value['label'];
        }
        return $values;
    }

    /**
     * GetNPrefix.
     *
     * @return $this
     */
    public function getNPrefix()
    {
        return $this->getNamePrefix();
    }

    /**
     * GetId.
     *
     * @return $this
     */
    public function getId()
    {
        return $this->getHtmlId();
    }

    /**
     * GetCheck.
     *
     * @return $this
     * @param string $name
     */
    public function getCheck($name)
    {
        return $this->getIsChecked($name);
    }
}
