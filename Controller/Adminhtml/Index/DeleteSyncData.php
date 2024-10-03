<?php
namespace DamConsultants\Macfarlane\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class DeleteSyncData extends Action
{
    /**
     * @var $BynderConfigSyncDataFactory
     */
    public $BynderConfigSyncDataFactory;
    /**
     * Closed constructor.
     *
     * @param Context $context
     * @param DamConsultants\Macfarlane\Model\BynderConfigSyncDataFactory $BynderConfigSyncDataFactory
     */
    public function __construct(
        Context $context,
        \DamConsultants\Macfarlane\Model\BynderConfigSyncDataFactory $BynderConfigSyncDataFactory
    ) {
        $this->bynderConfigSyncDataFactory = $BynderConfigSyncDataFactory;
        parent::__construct($context);
    }
    /**
     * Execute
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('id');
        try {
            $syncModel = $this->bynderConfigSyncDataFactory->create();
            $syncModel->load($id);
            $syncModel->delete();
            $this->messageManager->addSuccessMessage(__('You deleted the sync data.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $resultRedirect->setPath('bynder/index/sync');
    }
    /**
     * Is Allowed
     */
    public function _isAllowed()
    {
        return $this->_authorization->isAllowed('DamConsultants_Macfarlane::delete');
    }
}
