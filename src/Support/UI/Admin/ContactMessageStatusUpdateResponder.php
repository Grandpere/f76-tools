<?php

declare(strict_types=1);

/*
 * This file is part of a F76 project.
 *
 * (c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Support\UI\Admin;

use App\Support\Application\Contact\ContactMessageStatusUpdateResult;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ContactMessageStatusUpdateResponder
{
    public function __construct(
        private readonly ContactMessageStatusUpdateFeedbackMapper $feedbackMapper,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function invalidCsrf(Request $request): RedirectResponse
    {
        $this->addFlash($request, 'warning', 'admin_contact.flash.invalid_csrf');

        return $this->redirectToList($request);
    }

    public function fromResult(Request $request, ContactMessageStatusUpdateResult $result): RedirectResponse
    {
        $feedback = $this->feedbackMapper->map($result);
        $this->addFlash($request, $feedback['flashType'], $feedback['flashMessage']);

        return $this->redirectToList($request);
    }

    private function redirectToList(Request $request): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('app_admin_contact_messages', [
            '_locale' => $request->getLocale(),
        ]));
    }

    private function addFlash(Request $request, string $flashType, string $flashMessage): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $flashBag = $request->getSession()->getBag('flashes');
        if (!$flashBag instanceof FlashBagInterface) {
            return;
        }

        $flashBag->add($flashType, $flashMessage);
    }
}
