<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<User>
 */
class ProfileType extends AbstractType
{
    /**
     * @param FormBuilderInterface<User|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomUser', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire.'),
                    new Assert\Length(min: 2, max: 50),
                ],
            ])
            ->add('PrenomUser', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le prénom est obligatoire.'),
                    new Assert\Length(min: 2, max: 50),
                ],
            ])
            ->add('EmailUser', EmailType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(message: 'L’email est obligatoire.'),
                    new Assert\Email(message: 'Email invalide.'),
                ],
            ])
            ->add('NumTelUser', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le numéro de téléphone est obligatoire.'),
                    new Assert\Regex(
                        pattern: '/^\d{8}$/',
                        message: 'Le numéro de téléphone doit contenir exactement 8 chiffres.'
                    ),
                ],
            ])
            ->add('profileImage', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File(
                        maxSize: '4M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Téléchargez une image JPG, PNG ou WEBP.'
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}