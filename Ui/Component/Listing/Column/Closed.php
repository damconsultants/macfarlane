<?php
/**
 * Macfarlane
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the ecomteck.com license that is
 * available through the world-wide-web at this URL:
 * https://Macfarlane.com/
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    DamConsultants
 * @package     DamConsultants_Macfarlane
 */
namespace DamConsultants\Macfarlane\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Store\Model\StoreManagerInterface;

class Closed extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Closed constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param StoreManagerInterface $storeManager
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * PrepareDataSource
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                
                if ($item) {
                    $color = ($item['lable'] == 1 ? 'green' : 'red');
                    $true_css = '#d0e5a9 none repeat scroll 0 0';
                    $false_css = '#feeee1 none repeat scroll 0 0';
                    $b_ground = ($color == 'green' ? $true_css : $false_css);
                    $border_color = ($color == 'green' ? ' #5b8116 1px solid' : '#ed4f2e 1px solid');
                    $button_style = '<span style="color:'.$color.';
                        font-weight:bold; background:'.$b_ground.';
                        border:'.$border_color.';
                        display: block;line-height: 19px;
                        padding: 0 5px; text-align: center;
                        text-transform: uppercase;">';
                    if ($item['lable'] == 1) {
                        $button_style .= __('SUCCESS');
                    } elseif ($item['lable'] == 2) {
                        $button_style .= __('RE-SYNC');
                    } else {
                        $button_style .= __('ERROR');
                    }
                    $button_style .= '</span>';
                    $item['lable'] = $button_style;
                }
            }
        }
        return $dataSource;
    }
}
