<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ClientProfileController extends AbstractController
{
    #[Route('/profile', name: 'client_profile_show', methods: ['GET'])]
    public function show(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getRoleUser() !== 'client') {
            throw $this->createAccessDeniedException('Acces reserve au profil client.');
        }

        return $this->render('front/profile/show.html.twig', [
            'userProfile' => $user,
        ]);
    }

    #[Route('/profile/edit', name: 'client_profile_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getRoleUser() !== 'client') {
            throw $this->createAccessDeniedException('Acces reserve au profil client.');
        }

        $oldImagePath = $user->getImage_path();

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('profileImage')->getData();

            if ($imageFile instanceof UploadedFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), \PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $extension = $imageFile->guessExtension() ?: 'bin';
                $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $extension;

                try {
                    $usersDir = $this->getStringParameter('user_images_directory');
                    $projectDir = $this->getStringParameter('kernel.project_dir');

                    $imageFile->move($usersDir, $newFilename);
                    $user->setImage_path('uploads/users/' . $newFilename);

                    if ($oldImagePath !== null && str_starts_with($oldImagePath, 'uploads/users/')) {
                        $oldAbsolutePath = $projectDir . '/public/' . $oldImagePath;

                        $resolvedOld = realpath($oldAbsolutePath);
                        $resolvedUsersDir = realpath($usersDir);

                        if (
                            is_string($resolvedOld)
                            && is_string($resolvedUsersDir)
                            && str_starts_with($resolvedOld, $resolvedUsersDir)
                            && is_file($resolvedOld)
                        ) {
                            @unlink($resolvedOld);
                        }
                    }
                } catch (FileException) {
                    $this->addFlash('error', 'Erreur lors du telechargement de l image.');
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Profil mis a jour avec succes.');

            return $this->redirectToRoute('client_profile_show');
        }

        return $this->render('front/profile/edit.html.twig', [
            'form' => $form->createView(),
            'userProfile' => $user,
            'oldImagePath' => $oldImagePath,
        ]);
    }

    private function getStringParameter(string $name): string
    {
        $value = $this->getParameter($name);

        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('Le parametre "%s" est invalide.', $name));
        }

        return $value;
    }
}
