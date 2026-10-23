<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\LoginType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Demo for NowoManualKitBundle: landing page and in-memory sign-in; the manual itself is the bundle's controller.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DemoController extends AbstractController
{
    private const string LOCALE = 'en|es';

    #[Route('/', name: 'homepage_default', methods: ['GET'])]
    public function homeDefault(): Response
    {
        return $this->redirectToRoute('homepage', ['_locale' => 'en'], Response::HTTP_FOUND);
    }

    #[Route('/{_locale}', name: 'homepage', requirements: ['_locale' => self::LOCALE], methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('demo/home.html.twig');
    }

    #[Route('/{_locale}/login', name: 'demo_login', requirements: ['_locale' => self::LOCALE], methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $auth): Response
    {
        $last = $auth->getLastUsername();

        $form = $this->createForm(LoginType::class, null, [
            'action'        => $this->generateUrl('demo_login', ['_locale' => $request->getLocale()]),
            'method'        => 'POST',
            'last_username' => '' === $last ? 'demo' : $last,
            'target_path'   => $this->generateUrl('nowo_manual_index', ['_locale' => $request->getLocale()]),
        ]);

        return $this->render('demo/login.html.twig', [
            'login_form'  => $form,
            'login_error' => $auth->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'demo_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Handled by the security logout listener.');
    }
}
