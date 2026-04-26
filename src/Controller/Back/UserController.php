<?php

namespace App\Controller\Back;

use App\Entity\User;
use App\Form\ProfileType;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;


#[Route('/back/user')]
final class UserController extends AbstractController
{
    #[Route('/', name: 'back_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository, Request $request): Response
    {
        $allowedSortFields = ['idUser', 'roleUser', 'dateNUser'];

        $sortBy = (string) $request->query->get('sort', 'idUser');
        $direction = strtoupper((string) $request->query->get('direction', 'ASC'));
        $search = trim((string) $request->query->get('search', ''));

        if (!in_array($sortBy, $allowedSortFields, true)) {
            $sortBy = 'idUser';
        }

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        $users = $userRepository->findBySearchAndSort($search, $sortBy, $direction);

        return $this->render('back/User/index.html.twig', [
            'users' => $users,
            'currentSort' => $sortBy,
            'currentDirection' => $direction,
            'currentSearch' => $search,
        ]);
    }

    #[Route('/profile', name: 'back_user_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non connectÃ©.');
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('profileImage')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $extension = $imageFile->guessExtension() ?: 'bin';
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

                try {
                    $imageFile->move(
                        $this->getParameter('user_images_directory'),
                        $newFilename
                    );

                    $user->setImage_path('uploads/users/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du tÃ©lÃ©chargement de lâ€™image.');
                }
            }

            $user->setUpdatedAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Profil mis Ã  jour avec succÃ¨s.');

            return $this->redirectToRoute('back_user_profile');
        }

        return $this->render('back/User/profile.html.twig', [
            'form' => $form->createView(),
            'userProfile' => $user,
        ]);
    }

   #[Route('/new', name: 'back_user_new', methods: ['GET', 'POST'])]
public function new(
    Request $request,
    EntityManagerInterface $entityManager,
    UserPasswordHasherInterface $passwordHasher,
    SluggerInterface $slugger
): Response {
    $user = new User();
    $user->setCreatedAt(new \DateTime());
    $user->setUpdatedAt(new \DateTime());
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
                $imageFile = $form->get('profileImage')->getData();

                if ($imageFile) {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $extension = $imageFile->guessExtension() ?: 'bin';
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

                    try {
                        $imageFile->move(
                            $this->getParameter('user_images_directory'),
                            $newFilename
                        );

                        $user->setImage_path('uploads/users/' . $newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Erreur lors du tÃ©lÃ©chargement de lâ€™image.');
                    }
                }

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
    EntityManagerInterface $entityManager,
    SluggerInterface $slugger
): Response {
    $form = $this->createForm(UserType::class, $user, [
        'is_edit' => true,
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted()) {
        if ($form->isValid()) {
            $imageFile = $form->get('profileImage')->getData();

            if ($imageFile) {
                $oldImage = $user->getImage_path();

                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $extension = $imageFile->guessExtension() ?: 'bin';
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

                try {
                    $imageFile->move(
                        $this->getParameter('user_images_directory'),
                        $newFilename
                    );

                    $user->setImage_path('uploads/users/' . $newFilename);

                    if ($oldImage) {
                        $oldPath = $this->getParameter('kernel.project_dir') . '/public/' . $oldImage;
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du tÃ©lÃ©chargement de lâ€™image.');
                }
            }

            $user->setUpdatedAt(new \DateTime());
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
