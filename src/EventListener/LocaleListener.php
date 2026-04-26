<?php
namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LocaleListener implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();
        if ($locale = $request->query->get('_locale')) {
            $request->getSession()->set('_locale', $locale);
            $request->setLocale($locale);
        } elseif ($locale = $request->getSession()->get('_locale')) {
            $request->setLocale($locale);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
