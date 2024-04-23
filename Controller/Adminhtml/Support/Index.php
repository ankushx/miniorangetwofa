<?php

namespace MiniOrange\TwoFA\Controller\Adminhtml\Support;

use Magento\Framework\App\Action\HttpGetActionInterface;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use Magento\Framework\Controller\ResultFactory;
use MiniOrange\TwoFA\Helper\TwoFAMessages;
use MiniOrange\TwoFA\Helper\Curl;
use MiniOrange\TwoFA\Controller\Actions\BaseAdminAction;
use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * This class handles the action for endpoint: moTwoFA/support/Index
 * Extends the \Magento\Backend\App\Action for Admin Actions which
 * inturn extends the \Magento\Framework\App\Action\Action class necessary
 * for each Controller class
 *
 * This class handles processing and sending or support request
 */
class Index extends BaseAdminAction implements HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * The first function to be called when a Controller class is invoked.
     * Usually, has all our controller logic. Returns a view/page/template
     * to be shown to the users.
     *
     * This function gets and prepares all our SP config data from the
     * database. It's called when you visis the moasaml/metadata/Index
     * URL. It prepares all the values required on the SP setting
     * page in the backend and returns the block to be displayed.
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {   
        try {
            $params = $this->getRequest()->getParams(); //get params
            if ($this->isFormOptionBeingSaved($params)) {
                $this->checkIfSupportQueryFieldsEmpty(['email'=>$params,'query'=>$params]);
                $email = $params['email'];
                $phone = $params['phone'];
                $query = $params['query'];
                Curl::submit_contact_us($email, $phone, $query);
                $this->messageManager->addSuccessMessage(TwoFAMessages::QUERY_SENT);
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
                  $this->logger->debug($e->getMessage());
        }
        // $resultPage = $this->resultPageFactory->create();
        // $resultPage->getConfig()->getTitle()->prepend(__(TwoFAConstants::MODULE_TITLE));
         $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        return $resultRedirect;
    }


    /**
     * Is the user allowed to view the Support settings.
     * This is based on the ACL set by the admin in the backend.
     * Works in conjugation with acl.xml
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(TwoFAConstants::MODULE_DIR.TwoFAConstants::MODULE_SUPPORT);
    }
}
