<?php
/**
 * Class to Handle FORM Operations
 *
 * @category Core, Helpers
 * @package  MoOauthClient
 * @author   miniOrange <info@miniorange.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @link     https://miniorange.com
 */
namespace MiniOrange\TwoFA\Plugin\Adminhtml\Block\System\Account\Edit;

use Closure;
use Google\Authenticator\GoogleAuthenticator;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Model\Config\Source\Enabledisable;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutInterface;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;
use MiniOrange\TwoFA\Block\Adminhtml\User\Edit\Tab\Renderer\DisableButton;
use MiniOrange\TwoFA\Block\Adminhtml\User\Edit\Tab\Renderer\QrCode;
use MiniOrange\TwoFA\Block\Adminhtml\User\Edit\Tab\Renderer\TrustedDevices;

/**
 * Class Form
 * @package MiniOrange\TwoFA\Plugin\Block\Adminhtml\System\Account\Edit
 */
class Form
{
    /**
     * @var Enabledisable
     */
    protected $_enableDisable;

    /**
     * @var Registry
     */
    protected $_coreRegistry;

    /**
     * @var LayoutInterface
     */
    protected $_layout;

    /**
     * @var Session
     */
    protected $_authSession;

    /**
     * @var UserFactory
     */
    protected $_userFactory;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * Form constructor.
     *
     * @param Enabledisable $enableDisable
     * @param Registry $coreRegistry
     * @param LayoutInterface $layout
     * @param Session $authSession
     * @param UserFactory $userFactory
     * @param HelperData $helperData
     */
    public function __construct(
        Enabledisable $enableDisable,
        Registry $coreRegistry,
        LayoutInterface $layout,
        Session $authSession,
        UserFactory $userFactory
    ) {
        $this->_enableDisable = $enableDisable;
        $this->_coreRegistry  = $coreRegistry;
        $this->_layout        = $layout;
        $this->_authSession   = $authSession;
        $this->_userFactory   = $userFactory;
    }

    /**
     * @param \Magento\Backend\Block\System\Account\Edit\Form $subject
     * @param Closure $proceed
     *
     * @return mixed
     */
    public function aroundGetFormHtml(
        \Magento\Backend\Block\System\Account\Edit\Form $subject,
        Closure $proceed
    ) {
        $form = $subject->getForm();
        /** @var $model User */
        $userId = $this->_authSession->getUser()->getId();
        $user   = $this->_userFactory->create()->load($userId);
        $user->unsetData('password');
        $this->_coreRegistry->register('mp_permissions_user', $user);
        //$secretFactory = new GoogleAuthenticator();
       // $secret        = ($user->getMpTfaSecret()) ?: $secretFactory->generateSecret();
       $secret ='WEGABHMOAAWMWFKX';
        if (0) {
            $mpTfaFieldset = $form->addFieldset('mp_tfa_security', ['legend' => __('Security')]);
            $mpTfaFieldset->addField('mp_tfa_enable', 'select', [
                'name'       => 'mp_tfa_enable',
                'label'      => __('Two-Factor Authentication'),
                'title'      => __('Two-Factor Authentication'),
                'values'     => $this->_enableDisable->toOptionArray(),
                'after_html' => 'Brian tran'
            ]);

            $mpTfaFieldset->addField('mp_tfa_secret_temp', QrCode::class, [
                'name' => 'mp_tfa_secret_temp'
            ]);
            $mpTfaFieldset->addField('mp_tfa_secret_temp_hidden', 'hidden', ['name' => 'mp_tfa_secret_temp_hidden']);
            $mpTfaFieldset->addField('mp_tfa_secret', 'hidden', ['name' => 'mp_tfa_secret']);
            $mpTfaFieldset->addField('mp_tfa_status', 'hidden', ['name' => 'mp_tfa_status']);

            $mpTfaFieldset->addField('mp_tfa_disable', DisableButton::class, [
                'name' => 'mp_tfa_disable'
            ]);

            if ($user->getData('mp_tfa_enable')) {
                $mpTfaChildFieldset = $mpTfaFieldset->addFieldset('mp_tfa_trust_device', [
                    'legend' => __('Trusted Devices')
                ]);
                $mpTfaChildFieldset->addField('mp_tfa_trusted_device', 'label', [
                    'name' => 'mp_tfa_trusted_device',
                ])->setAfterElementHtml($this->getTrustedDeviceHtml($user));
            }

            $data                       = $user->getData();
            $data['mp_tfa_secret_temp'] = $data['mp_tfa_secret_temp_hidden'] = $secret;
            $data['mp_tfa_status']      = $user->getMpTfaStatus();

            $form->setValues($data);
            $subject->setForm($form);
        }

        return $proceed();
    }

    /**
     * @param $model
     *
     * @return mixed
     */
    public function getTrustedDeviceHtml($model)
    {
        return $this->_layout
            ->createBlock(TrustedDevices::class)
            ->setUserObject($model)
            ->toHtml();
    }
}
