<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontController extends AbstractController
{
   #[Route('/', name: 'app_home')]
public function home(): Response
{
    return $this->render('front/index.html.twig');
}


#[Route('/boutique', name: 'app_shop')]
public function shop(): Response
{
    $products = [
        ['id' => 1, 'name' => 'Analyse financière', 'price' => 299],
        ['id' => 2, 'name' => 'Plan de comptes', 'price' => 100],
       ['id' => 3, 'name' => 'Tableau de bord', 'price' => 199],
       ['id' => 4, 'name' => 'Formation en ligne', 'price' => 499],
       ['id' => 5, 'name' => 'Consultation personnalisée', 'price' => 999],
    ];
    return $this->render('front/shop.html.twig', ['products' => $products]);
}
}
