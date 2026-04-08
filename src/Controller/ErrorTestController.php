<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ErrorTestController extends AbstractController
{
    #[Route('/test/trigger-403', name: 'test_trigger_403')]
    public function trigger403(): Response
    {
        throw $this->createAccessDeniedException('Accès de test refusé.');
    }
}
