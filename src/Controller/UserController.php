<?php

namespace App\Controller\Back;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/back/user')]
class UserController extends AbstractController
{
  
 #[Route('/', name: 'back_user_index', methods: ['GET'])]
public function index(UserRepository $userRepository, Request $request): Response
{
    $allowedSortFields = ['idUser', 'roleUser', 'dateNUser'];

    $sortBy = $request->query->get('sort', 'idUser');
    $direction = strtoupper($request->query->get('direction', 'ASC'));

    if (!in_array($sortBy, $allowedSortFields, true)) {
        $sortBy = 'idUser';
    }

    if (!in_array($direction, ['ASC', 'DESC'], true)) {
        $direction = 'ASC';
    }

    $nextDirection = $direction === 'ASC' ? 'DESC' : 'ASC';

    return $this->render('back/user/index.html.twig', [
        'users' => $userRepository->findBy([], [$sortBy => $direction]),
        'currentSort' => $sortBy,
        'currentDirection' => $direction,
        'nextDirection' => $nextDirection,
    ]);
}
    #[Route('/new', name: 'back_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $user = new User();
        $user->setCreatedAt(new \DateTime());
        $user->setUpdatedAt(new \DateTime());
        $user->setFailedLoginCount(0);
        $user->setTotpEnabled(false);

        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => false
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $plainPassword = $user->getPasswordUser();

                if (!$plainPassword) {
                    $this->addFlash('danger', 'Le mot de passe est obligatoire.');
                } else {
                    $user->setPasswordUser(
                        $hasher->hashPassword($user, $plainPassword)
                    );

                    $em->persist($user);
                    $em->flush();

                    $this->addFlash('success', 'Utilisateur ajouté avec succès.');
                    return $this->redirectToRoute('back_user_index');
                }
            } else {
                $this->addFlash('danger', 'Formulaire invalide. Vérifie les champs.');
            }
        }

        return $this->render('back/user/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Ajouter un utilisateur'
        ]);
    }

    #[Route('/{idUser}/edit', name: 'back_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity(mapping: ['idUser' => 'idUser'])] User $user,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
    if ($form->isValid()) {
        $user->setUpdatedAt(new \DateTime());
        $em->flush();

        $this->addFlash('success', 'Utilisateur modifié avec succès.');
        return $this->redirectToRoute('back_user_index');
    } else {
        $this->addFlash('danger', 'Formulaire invalide. Vérifie les champs.');
    }
}
        return $this->render('back/user/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Modifier l\'utilisateur'
        ]);
    }

    #[Route('/{idUser}/delete', name: 'back_user_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        #[MapEntity(mapping: ['idUser' => 'idUser'])] User $user,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete_user_' . $user->getIdUser(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();

            $this->addFlash('success', 'Utilisateur supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('back_user_index');
    }
}