<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\File;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomUser', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le nom est obligatoire.',
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le nom doit contenir au moins 2 caractÃ¨res.',
                        'maxMessage' => 'Le nom ne doit pas dÃ©passer 50 caractÃ¨res.',
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[a-zA-ZÃ€-Ã¿\s\-]+$/u',
                        'message' => 'Nom invalide. Pas de chiffres ni caractÃ¨res spÃ©ciaux.',
                    ]),
                ],
            ])
            ->add('PrenomUser', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le prÃ©nom est obligatoire.',
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le prÃ©nom doit contenir au moins 2 caractÃ¨res.',
                        'maxMessage' => 'Le prÃ©nom ne doit pas dÃ©passer 50 caractÃ¨res.',
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[a-zA-ZÃ€-Ã¿\s\-]+$/u',
                        'message' => 'PrÃ©nom invalide. Pas de chiffres ni caractÃ¨res spÃ©ciaux.',
                    ]),
                ],
            ])
            ->add('EmailUser', EmailType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Lâ€™email est obligatoire.',
                    ]),
                    new Assert\Email([
                        'message' => 'Email invalide. Exemple : xx@xx.xx',
                    ]),
                ],
            ])
            ->add('roleUser', ChoiceType::class, [
                'choices' => [
                    'Admin' => 'admin',
                    'Gerant' => 'gerant',
                    'Client' => 'client',
                ],
                'placeholder' => 'Choisir un role',
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le rÃ´le est obligatoire.',
                    ]),
                    new Assert\Choice([
                        'choices' => ['admin', 'gerant', 'client'],
                        'message' => 'RÃ´le invalide.',
                    ]),
                ],
            ])
            ->add('cin', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le CIN est obligatoire.',
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^\d{8}$/',
                        'message' => 'Le CIN doit contenir exactement 8 chiffres numÃ©riques.',
                    ]),
                ],
            ])
            ->add('NumTelUser', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le numÃ©ro de tÃ©lÃ©phone est obligatoire.',
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^\d{8}$/',
                        'message' => 'Le numÃ©ro de tÃ©lÃ©phone doit contenir exactement 8 chiffres numÃ©riques.',
                    ]),
                ],
            ])
            ->add('dateNUser', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'html5' => true,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'La date de naissance est obligatoire.',
                    ]),
                ],
            ])
            ->add('expertiseAreaUser', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Assert\Length([
                        'max' => 100,
                        'maxMessage' => 'Lâ€™expertise ne doit pas dÃ©passer 100 caractÃ¨res.',
                    ]),
                ],
            ])
            ->add('profileImage', FileType::class, [
                'label' => 'Photo de profil',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '4M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/jpg',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG, WEBP).',
                    ]),
                ],
            ]);

        if (!$options['is_edit']) {
            $builder->add('passwordUser', PasswordType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le mot de passe est obligatoire.',
                    ]),
                    new Assert\Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit contenir au moins 6 caractÃ¨res.',
                    ]),
                ],
            ]);
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            /** @var User|null $user */
            $user = $event->getData();

            if (!$user) {
                return;
            }

            $birthDate = $user->getDateNUser();
            if ($birthDate) {
                $today = new \DateTime();
                $minDate = new \DateTime('1900-01-01');
                $age = $birthDate->diff($today)->y;

                if ($birthDate > $today) {
                    $form->get('dateNUser')->addError(new FormError('La date de naissance ne peut pas Ãªtre dans le futur.'));
                } elseif ($birthDate < $minDate) {
                    $form->get('dateNUser')->addError(new FormError('Date de naissance trop ancienne.'));
                } elseif ($age < 17) {
                    $form->get('dateNUser')->addError(new FormError('Lâ€™utilisateur doit avoir au moins 17 ans.'));
                } elseif ($age > 100) {
                    $form->get('dateNUser')->addError(new FormError('Ã‚ge invalide.'));
                }
            }

            if ($user->getRoleUser() === 'gerant') {
                $expertise = $user->getExpertiseAreaUser();
                if ($expertise === null || trim($expertise) === '') {
                    $form->get('expertiseAreaUser')->addError(new FormError('Lâ€™expertise est obligatoire pour un gÃ©rant.'));
                }
            } else {
                $user->setExpertiseAreaUser('');
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
            'csrf_protection' => true,
            'csrf_token_id' => 'user_form',
        ]);
    }
}