<?php

namespace Claroline\CoreBundle\Controller\Badge;

use Claroline\CoreBundle\Entity\Badge\Badge;
use Claroline\CoreBundle\Entity\Badge\BadgeClaim;
use Claroline\CoreBundle\Entity\Badge\BadgeRule;
use Claroline\CoreBundle\Entity\Badge\BadgeTranslation;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Form\Badge\BadgeAwardType;
use Claroline\CoreBundle\Form\Badge\BadgeType;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use JMS\SecurityExtraBundle\Annotation as SEC;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * @DI\Tag("security.secure_service")
 * @SEC\PreAuthorize("hasRole('ADMIN')")
 *
 * Controller of the badges.
 *
 * @Route("/admin/badges")
 */
class AdminController extends Controller
{
    /**
     * @Route("/{badgePage}/{claimPage}", name="claro_admin_badges", requirements={"badgePage" = "\d+", "claimPage" = "\d+"}, defaults={"badgePage" = 1, "claimPage" = 1})
     *
     * @Template
     */
    public function listAction($badgePage, $claimPage)
    {
        /** @var \Claroline\CoreBundle\Library\Configuration\PlatformConfigurationHandler $platformConfigHandler */
        $platformConfigHandler = $this->get('claroline.config.platform_config_handler');

        $parameters = array(
            'badgePage'    => $badgePage,
            'claimPage'    => $claimPage,
            'add_link'         => 'claro_admin_badges_add',
            'edit_link'        => array(
                'url'    => 'claro_admin_badges_edit',
                'suffix' => '#!edit'
            ),
            'delete_link'      => 'claro_admin_badges_delete',
            'view_link'        => 'claro_admin_badges_edit',
            'current_link'     => 'claro_admin_badges',
            'claim_link'       => 'claro_admin_manage_claim',
            'claim_link'       => 'claro_admin_manage_claim',
            'route_parameters' => array()
        );
        return array(
            'parameters'  => $parameters,
            'language'    => $platformConfigHandler->getParameter('locale_language')
        );
    }

    /**
     * @Route("/add", name="claro_admin_badges_add")
     *
     * @Template()
     */
    public function addAction(Request $request)
    {
        $badge = new Badge();

        //@TODO Get locales from locale source (database etc...)
        $locales = array('fr', 'en');
        foreach ($locales as $locale) {
            $translation = new BadgeTranslation();
            $translation->setLocale($locale);
            $badge->addTranslation($translation);
        }

        $form = $this->createForm($this->get('claroline.form.badge'), $badge);

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                /** @var \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator */
                $translator = $this->get('translator');
                try {
                    /** @var \Doctrine\Common\Persistence\ObjectManager $entityManager */
                    $entityManager = $this->getDoctrine()->getManager();

                    $entityManager->persist($badge);
                    $entityManager->flush();

                    $this->get('session')->getFlashBag()->add('success', $translator->trans('badge_add_success_message', array(), 'badge'));
                } catch (\Exception $exception) {
                    $this->get('session')->getFlashBag()->add('error', $translator->trans('badge_add_error_message', array(), 'badge'));
                }

                return $this->redirect($this->generateUrl('claro_admin_badges'));
            }
        }

        return array(
            'form' => $form->createView()
        );
    }

    /**
     * @Route("/edit/{id}/{page}", name="claro_admin_badges_edit")
     *
     * @Template()
     */
    public function editAction(Request $request, Badge $badge, $page = 1)
    {
        /** @var \Claroline\CoreBundle\Library\Configuration\PlatformConfigurationHandler $platformConfigHandler */
        $platformConfigHandler = $this->get('claroline.config.platform_config_handler');
        $badge->setLocale($platformConfigHandler->getParameter('locale_language'));

        $doctrine = $this->getDoctrine();

        $query   = $doctrine->getRepository('ClarolineCoreBundle:Badge\Badge')->findUsers($badge, false);
        $adapter = new DoctrineORMAdapter($query);
        $pager   = new Pagerfanta($adapter);

        try {
            $pager->setCurrentPage($page);
        } catch (NotValidCurrentPageException $exception) {
            throw new NotFoundHttpException();
        }

        /** @var BadgeRule[] $originalRules */
        $originalRules = array();
        foreach ($badge->getBadgeRules() as $rule) {
            $originalRules[] = $rule;
        }

        $form = $this->createForm($this->get('claroline.form.badge'), $badge);

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                /** @var \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator */
                $translator = $this->get('translator');
                try {
                    /** @var \Doctrine\Common\Persistence\ObjectManager $entityManager */
                    $entityManager = $doctrine->getManager();

                    // Compute which rules was deleted
                    foreach ($badge->getBadgeRules() as $rule) {
                        foreach ($originalRules as $key => $originalRule) {
                            if ($originalRule->getId() === $rule->getId()) {
                                unset($originalRules[$key]);
                            }
                        }
                    }

                    // Delete rules
                    foreach ($originalRules as $rule) {
                        $entityManager->remove($rule);
                    }

                    $entityManager->persist($badge);
                    $entityManager->flush();

                    $this->get('session')->getFlashBag()->add('success', $translator->trans('badge_edit_success_message', array(), 'badge'));
                } catch (\Exception $exception) {
                    $this->get('session')->getFlashBag()->add('error', $translator->trans('badge_edit_error_message', array(), 'badge'));
                }

                return $this->redirect($this->generateUrl('claro_admin_badges'));
            }
        }

        return array(
            'form'  => $form->createView(),
            'badge' => $badge,
            'pager' => $pager
        );
    }

    /**
     * @Route("/delete/{id}", name="claro_admin_badges_delete")
     *
     * @Template()
     */
    public function deleteAction(Badge $badge)
    {
        /** @var \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator */
        $translator = $this->get('translator');
        try {
            /** @var \Doctrine\Common\Persistence\ObjectManager $entityManager */
            $entityManager = $this->getDoctrine()->getManager();

            $entityManager->remove($badge);
            $entityManager->flush();

            $this->get('session')->getFlashBag()->add('success', $translator->trans('badge_delete_success_message', array(), 'badge'));
        } catch (\Exception $exception) {
            $this->get('session')->getFlashBag()->add('error', $translator->trans('badge_delete_error_message', array(), 'badge'));
        }

        return $this->redirect($this->generateUrl('claro_admin_badges'));
    }

    /**
     * @Route("/award/{id}", name="claro_admin_badges_award")
     *
     * @Template()
     */
    public function awardAction(Request $request, Badge $badge)
    {
        $form = $this->createForm(new BadgeAwardType());

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                /** @var \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator */
                $translator = $this->get('translator');
                try {
                    $doctrine = $this->getDoctrine();

                    /** @var \Doctrine\ORM\EntityManager $entityManager */
                    $entityManager = $doctrine->getManager();

                    $groupName    = $form->get('group')->getData();
                    $userName     = $form->get('user')->getData();
                    $awardedBadge = 0;

                    /** @var \Claroline\CoreBundle\Entity\User[] $users */
                    $users = array();

                    if (null !== $groupName) {
                        $group = $doctrine->getRepository('ClarolineCoreBundle:Group')->findOneByName($groupName);

                        if (null !== $group) {
                            $users = $doctrine->getRepository('ClarolineCoreBundle:User')->findByGroup($group);
                        }
                    } elseif (null !== $userName) {
                        list($firstName, $lastName) = explode(' ', $userName);
                        $user = $doctrine->getRepository('ClarolineCoreBundle:User')->findOneBy(array('firstName' => $firstName, 'lastName' => $lastName));

                        if (null !== $user) {
                            $users[] = $user;
                        }
                    }

                    /** @var \Claroline\CoreBundle\Manager\BadgeManager $badgeManager */
                    $badgeManager = $this->get('claroline.manager.badge');
                    $awardedBadge = $badgeManager->addBadgeToUsers($badge, $users);

                    $flashMessageType = 'error';

                    if (0 < $awardedBadge) {
                        $flashMessageType = 'success';
                    }

                    $this->get('session')->getFlashBag()->add($flashMessageType, $translator->transChoice('badge_awarded_count_message', $awardedBadge, array('%awaredBadge%' => $awardedBadge), 'badge'));
                } catch (\Exception $exception) {
                    if (!$request->isXmlHttpRequest()) {
                        $this->get('session')->getFlashBag()->add('error', $translator->trans('badge_award_error_message', array(), 'badge'));
                    } else {
                        return new Response($exception->getMessage(), 500);
                    }
                }

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(array('error' => false));
                }

                return $this->redirect($this->generateUrl('claro_admin_badges_edit', array('id' => $badge->getId())));
            }
        }

        return array(
            'badge'  => $badge,
            'form'   => $form->createView()
        );
    }

    /**
     * @Route("/unaward/{id}/{username}", name="claro_admin_badges_unaward")
     * @ParamConverter("user", options={"mapping": {"username": "username"}})
     *
     * @Template()
     */
    public function unawardAction(Request $request, Badge $badge, User $user)
    {
        /** @var \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator */
        $translator = $this->get('translator');
        try {
            $doctrine = $this->getDoctrine();
            /** @var \Doctrine\ORM\EntityManager $entityManager */
            $entityManager = $doctrine->getManager();

            $userBadge = $doctrine->getRepository('ClarolineCoreBundle:Badge\UserBadge')->findOneByBadgeAndUser($badge, $user);

            $entityManager->remove($userBadge);
            $entityManager->flush();

            $this->get('session')->getFlashBag()->add('success', $translator->trans('badge_unaward_success_message', array(), 'badge'));
        } catch (\Exception $exception) {
            if (!$request->isXmlHttpRequest()) {
                $this->get('session')->getFlashBag()->add('error', $translator->trans('badge_unaward_error_message', array(), 'badge'));
            } else {
                return new Response($exception->getMessage(), 500);
            }
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(array('error' => false));
        }

        return $this->redirect($this->generateUrl('claro_admin_badges_edit', array('id' => $badge->getId())));
    }

    /**
     * @Route("/claim/manage/{id}/{validate}", name="claro_admin_manage_claim")
     * @ParamConverter("user", options={"mapping": {"username": "username"}})
     *
     * @Template()
     */
    public function manageClaimAction(Request $request, BadgeClaim $badgeClaim, $validate = false)
    {
        /** @var \Symfony\Bundle\FrameworkBundle\Translation\Translator $translator */
        $translator = $this->get('translator');
        try {
            $successMessage = $translator->trans('badge_reject_award_success_message', array(), 'badge');
            $errorMessage   = $translator->trans('badge_reject_award_error_message', array(), 'badge');

            if ($validate) {
                $successMessage = $translator->trans('badge_validate_award_success_message', array(), 'badge');
                $errorMessage   = $translator->trans('badge_validate_award_error_message', array(), 'badge');

                /** @var \Claroline\CoreBundle\Manager\BadgeManager $badgeManager */
                $badgeManager = $this->get('claroline.manager.badge');
                $awardedBadge = $badgeManager->addBadgeToUsers($badgeClaim->getBadge(), array($badgeClaim->getUser()));
                if (0 === $awardedBadge) {
                    throw new \Exception('No badge were awarded.');
                }
            }

            $entityManager = $this->getDoctrine()->getManager();

            $entityManager->remove($badgeClaim);
            $entityManager->flush();

            $this->get('session')->getFlashBag()->add('success', $successMessage);
        } catch (\Exception $exception) {
            $this->get('session')->getFlashBag()->add('error', $errorMessage);
        }

        return $this->redirect($this->generateUrl('claro_admin_badges'));
    }
}
