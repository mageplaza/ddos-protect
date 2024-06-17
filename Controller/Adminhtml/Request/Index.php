<?php

namespace Mageplaza\DDoSProtect\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * Constructor
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Index action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Mageplaza_DDoSProtect::requests');
        $resultPage->addBreadcrumb(__('DDoS Protect'), __('DDoS Protect'));
        $resultPage->addBreadcrumb(__('Requests'), __('Requests'));
        $resultPage->getConfig()->getTitle()->prepend(__('Requests'));

        return $resultPage;
    }
}
