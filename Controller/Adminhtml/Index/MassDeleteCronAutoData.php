<?php
namespace DamConsultants\Macfarlane\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use DamConsultants\Macfarlane\Model\ResourceModel\Collection\BynderAutoReplaceDataCollectionFactory;

class MassDeleteCronAutoData extends Action
{
    /**
     * @var collectionFactory.
     *
     */
    public $collectionFactory;
    /**
     * @var filter.
     *
     */
    public $filter;
    /**
     * @var bynderFactory.
     *
     */
    protected $bynderFactory;
    /**
     * Get Sku.
     * @param Context $context
     * @param Filter $filter
     * @param BynderAutoReplaceDataCollectionFactory $collectionFactory
     * @param \DamConsultants\Macfarlane\Model\BynderAutoReplaceDataFactory $bynderFactory
     */
    public function __construct(
        Context $context,
        Filter $filter,
        BynderAutoReplaceDataCollectionFactory $collectionFactory,
        \DamConsultants\Macfarlane\Model\BynderAutoReplaceDataFactory $bynderFactory
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->bynderFactory = $bynderFactory;
        parent::__construct($context);
    }
    /**
     * Execute
     *
     * @return $this
     */
    public function execute()
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());

            $count = 0;
            foreach ($collection as $model) {
                $model = $this->bynderFactory->create()->load($model->getId());
                $model->delete();
                $count++;
            }
            $this->messageManager->addSuccess(__('A total of %1 data(s) have been deleted.', $count));
        } catch (\Exception $e) {
            $this->messageManager->addError(__($e->getMessage()));
        }
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('bynder/index/replacecrongrid');
    }
    /**
     * Execute
     *
     * @return $this
     */
    public function _isAllowed()
    {
        return $this->_authorization->isAllowed('DamConsultants_Macfarlane::delete');
    }
}
