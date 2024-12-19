<?php

namespace DamConsultants\Macfarlane\Block\Adminhtml\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\View\Element\Template;

class Version extends Template implements RendererInterface
{
    /**
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        // Replace '1.0.0' with your actual module version
        $version = '1.0.4';
        return '<div><strong>' . __('Version: %1', $version) . '</strong></div>';
    }
}
