<?php

namespace OroB2B\Bundle\AccountBundle\Controller\Frontend;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\SecurityBundle\Annotation\Acl;

use OroB2B\Bundle\AccountBundle\Entity\AccountUser;
use OroB2B\Bundle\AccountBundle\Entity\AccountUserManager;
use OroB2B\Bundle\AccountBundle\Form\Handler\FrontendAccountUserHandler;
use OroB2B\Bundle\AccountBundle\Form\Type\FrontendAccountUserRegistrationType;
use OroB2B\Bundle\WebsiteBundle\Manager\WebsiteManager;
use OroB2B\Bundle\AccountBundle\Form\Type\FrontendAccountUserProfileType;
use OroB2B\Bundle\AccountBundle\Form\Type\FrontendAccountUserType;

class AccountUserProfileController extends Controller
{
    /**
     * Create account user form
     *
     * @Route("/register", name="orob2b_account_frontend_account_user_register")
     * @Template("OroB2BAccountBundle:AccountUser/Frontend:register.html.twig")
     *
     * @param Request $request
     * @return array|RedirectResponse
     */
    public function registerAction(Request $request)
    {
        $checkPermissions = $this->checkPermissions();
        if ($checkPermissions instanceof RedirectResponse) {
            return $checkPermissions;
        }
        $accountUser = new AccountUser();
        $this->setUserData($accountUser);
        return $this->handleForm($accountUser, $request);
    }

    /**
     * @return bool|RedirectResponse
     */
    private function checkPermissions()
    {
        if ($this->getUser()) {
            return $this->redirect($this->generateUrl('orob2b_account_frontend_account_user_profile'));
        }
        $isRegistrationAllowed = $this->get('oro_config.manager')->get('oro_b2b_account.registration_allowed');
        if (!$isRegistrationAllowed) {
            throw new AccessDeniedException();
        }
        return true;
    }

    /**
     * @param AccountUser $accountUser
     */
    private function setUserData(AccountUser $accountUser)
    {
        /** @var WebsiteManager $websiteManager */
        $websiteManager = $this->get('orob2b_website.manager');
        $website = $websiteManager->getCurrentWebsite();
        $websiteOrganization = $website->getOrganization();
        if (!$websiteOrganization) {
            throw new \RuntimeException('Website organization is empty');
        }
        $defaultRole = $this->getDoctrine()
            ->getManagerForClass('OroB2BAccountBundle:AccountUserRole')
            ->getRepository('OroB2BAccountBundle:AccountUserRole')
            ->getDefaultAccountUserRoleByWebsite($website);
        if (!$defaultRole) {
            throw new \RuntimeException(sprintf('Role "%s" was not found', AccountUser::ROLE_DEFAULT));
        }
        $accountUser
            ->addOrganization($websiteOrganization)
            ->setOrganization($websiteOrganization)
            ->addRole($defaultRole);
    }

    /**
     * @param AccountUser $accountUser
     * @param Request $request
     * @return array|RedirectResponse
     */
    private function handleForm(AccountUser $accountUser, Request $request)
    {
        /** @var $userManager AccountUserManager */
        $userManager = $this->get('orob2b_account_user.manager');
        $form = $this->createForm(FrontendAccountUserRegistrationType::NAME, $accountUser);
        $handler = new FrontendAccountUserHandler($form, $request, $userManager);
        if ($userManager->isConfirmationRequired()) {
            $registrationMessage = 'orob2b.account.controller.accountuser.registered_with_confirmation.message';
        } else {
            $registrationMessage = 'orob2b.account.controller.accountuser.registered.message';
        }
        return $this->get('oro_form.model.update_handler')->handleUpdate(
            $accountUser,
            $form,
            ['route' => 'orob2b_account_account_user_security_login'],
            ['route' => 'orob2b_account_account_user_security_login'],
            $this->get('translator')->trans($registrationMessage),
            $handler
        );
    }

    /**
     * @Route("/confirm-email", name="orob2b_account_frontend_account_user_confirmation")
     * @param Request $request
     * @return RedirectResponse
     */
    public function confirmEmailAction(Request $request)
    {
        $userManager = $this->get('orob2b_account_user.manager');
        /** @var AccountUser $accountUser */
        $accountUser = $userManager->findUserByUsernameOrEmail($request->get('username'));
        $token = $request->get('token');
        if ($accountUser === null || empty($token) || $accountUser->getConfirmationToken() !== $token) {
            throw $this->createNotFoundException(
                $this->get('translator')->trans('orob2b.account.controller.accountuser.confirmation_error.message')
            );
        }
        if (!$accountUser->isConfirmed()) {
            $userManager->confirmRegistration($accountUser);
            $userManager->updateUser($accountUser);
            $messageType = 'success';
            $message = 'orob2b.account.controller.accountuser.confirmed.message';
        } else {
            $messageType = 'warn';
            $message = 'orob2b.account.controller.accountuser.already_confirmed.message';
        }
        $this->get('session')->getFlashBag()->add($messageType, $message);
        return $this->redirect($this->generateUrl('orob2b_account_account_user_security_login'));
    }

    /**
     * @Route("/profile", name="orob2b_account_frontend_account_user_profile")
     * @Template("OroB2BAccountBundle:AccountUser/Frontend:viewProfile.html.twig")
     *
     * @return array
     */
    public function profileAction()
    {
        return [
            'entity' => $this->getUser(),
            'editRoute' => 'orob2b_account_frontend_account_user_profile_update'
        ];
    }

    /**
     * Edit account user form
     *
     * @Route("/profile/update", name="orob2b_account_frontend_account_user_profile_update")
     * @Template("OroB2BAccountBundle:AccountUser/Frontend:updateProfile.html.twig")
     * @AclAncestor("orob2b_account_frontend_account_user_update")
     *
     * @param Request $request
     * @return array|RedirectResponse
     */
    public function updateAction(Request $request)
    {
        $accountUser = $this->getUser();
        $form = $this->createForm(FrontendAccountUserProfileType::NAME, $accountUser);
        $handler = new FrontendAccountUserHandler(
            $form,
            $request,
            $this->get('orob2b_account_user.manager')
        );
        return $this->get('oro_form.model.update_handler')->handleUpdate(
            $accountUser,
            $form,
            ['route' => 'orob2b_account_frontend_account_user_profile_update'],
            ['route' => 'orob2b_account_frontend_account_user_profile'],
            $this->get('translator')->trans('orob2b.account.controller.accountuser.profile_updated.message'),
            $handler
        );
    }
}
