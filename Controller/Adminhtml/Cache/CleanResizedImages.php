<?php

namespace Staempfli\ImageResizer\Controller\Adminhtml\Cache;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Controller\Adminhtml\Cache;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Staempfli\ImageResizer\Model\Cache as ResizerCache;

class CleanResizedImages extends Cache
{
    protected ManagerInterface $eventManager;

    protected $messageManager;

    public function __construct(
        protected ResizerCache $resizerCache,
        Context $context,
        TypeListInterface $cacheTypeList,
        StateInterface $cacheState,
        Pool $cacheFrontendPool,
        PageFactory $resultPageFactory
    ) {
        $this->eventManager = $context->getEventManager();
        $this->messageManager = $context->getMessageManager();

        parent::__construct(
            $context,
            $cacheTypeList,
            $cacheState,
            $cacheFrontendPool,
            $resultPageFactory
        );
    }

    /**
     * Clean JS/css files cache
     */
    public function execute(): Redirect
    {
        try {
            $this->resizerCache->clearResizedImagesCache();
            $this->eventManager->dispatch('staempfli_imageresizer_clean_images_cache_after');
            $this->messageManager->addSuccessMessage(__('The resized images cache was cleaned.'));
        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('An error occurred while clearing the resized images cache.'));
        }

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('adminhtml/cache');
    }
}
