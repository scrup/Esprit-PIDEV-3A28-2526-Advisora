<?php
namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class LocaleListener implements EventSubscriberInterface
{
    private const SUPPORTED_LOCALES = ['fr', 'en'];

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $requestedLocale = trim((string) $request->query->get('_locale', ''));
        if ($requestedLocale !== '') {
            $locale = $this->normalizeLocale($requestedLocale);
            $request->getSession()->set('_locale', $locale);
            $request->setLocale($locale);
            return;
        }

        $sessionLocale = trim((string) $request->getSession()->get('_locale', ''));
        if ($sessionLocale !== '') {
            $locale = $this->normalizeLocale($sessionLocale);
            $request->setLocale($locale);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    private function normalizeLocale(string $locale): string
    {
        $normalized = mb_strtolower($locale);

        return in_array($normalized, self::SUPPORTED_LOCALES, true) ? $normalized : 'fr';
    }
}
