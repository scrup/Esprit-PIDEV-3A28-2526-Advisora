<?php

namespace App\Controller\Back;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/back/user')]
final class UserController extends AbstractController
{
    #[Route('/', name: 'back_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository, Request $request): Response
    {
        $allowedSortFields = ['idUser', 'roleUser', 'dateNUser'];

        $sortBy = (string) $request->query->get('sort', 'idUser');
        $direction = strtoupper((string) $request->query->get('direction', 'ASC'));

        if (!in_array($sortBy, $allowedSortFields, true)) {
            $sortBy = 'idUser';
        }

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        return $this->render('back/User/index.html.twig', [
            'users' => $userRepository->findBy([], [$sortBy => $direction]),
            'currentSort' => $sortBy,
            'currentDirection' => $direction,
            'nextDirection' => $direction === 'ASC' ? 'DESC' : 'ASC',
        ]);
    }

    #[Route('/new', name: 'back_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = new User();
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());
        $user->setFailed_login_count(0);
        $user->setTotp_enabled(false);

        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $plainPassword = (string) $user->getPasswordUser();

                if ($plainPassword === '') {
                    $this->addFlash('error', 'Le mot de passe est obligatoire.');
                } else {
                    $user->setPasswordUser($passwordHasher->hashPassword($user, $plainPassword));
                    $entityManager->persist($user);
                    $entityManager->flush();

                    $this->addFlash('success', 'Utilisateur ajoute avec succes.');

                    return $this->redirectToRoute('back_user_index');
                }
            } else {
                $this->addFlash('error', 'Formulaire invalide. Verifiez les champs.');
            }
        }

        return $this->render('back/User/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Ajouter un utilisateur',
        ]);
    }

    #[Route('/{idUser}/edit', name: 'back_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity(mapping: ['idUser' => 'idUser'])] User $user,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $user->setUpdatedAt(new \DateTimeImmutable());
                $entityManager->flush();

                $this->addFlash('success', 'Utilisateur modifie avec succes.');

                return $this->redirectToRoute('back_user_index');
            }

            $this->addFlash('error', 'Formulaire invalide. Verifiez les champs.');
        }

        return $this->render('back/User/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Modifier l utilisateur',
        ]);
    }

    #[Route('/{idUser}/delete', name: 'back_user_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        #[MapEntity(mapping: ['idUser' => 'idUser'])] User $user,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete_user_' . $user->getIdUser(), (string) $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur supprime avec succes.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('back_user_index');
    }
}
