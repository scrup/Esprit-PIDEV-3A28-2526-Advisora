<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ErrorPageController extends AbstractController
{
    #[Route('/403', name: 'error_403')]
    public function accessDenied(): Response
    {
        return $this->render('bundles/TwigBundle/Exception/error403.html.twig', [], new Response(null, 403));
    }
}
