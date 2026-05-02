<?php

namespace App\Form;

use App\Entity\Project;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice as AssertChoice;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<Project>
 */
class ProjectType extends AbstractType
{
    /**
     * @param FormBuilderInterface<Project|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du projet',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: Plateforme e-commerce',
                    'maxlength' => 160,
                    'data-validation-label' => 'Titre du projet',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le titre du projet est requis.']),
                    new Length([
                        'min' => 3,
                        'minMessage' => 'Le titre du projet doit contenir au moins 3 caracteres.',
                        'max' => 160,
                        'maxMessage' => 'Le titre du projet ne doit pas depasser 160 caracteres.',
                    ]),
                ],
            ])
            ->add('legacyType', TextType::class, [
                'label' => 'Type du projet',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: E-commerce, Energie, Fintech...',
                    'maxlength' => 100,
                    'data-validation-label' => 'Type du projet',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le type du projet est requis.']),
                    new Length([
                        'min' => 2,
                        'minMessage' => 'Le type du projet doit contenir au moins 2 caracteres.',
                        'max' => 100,
                        'maxMessage' => 'Le type du projet ne doit pas depasser 100 caracteres.',
                    ]),
                ],
            ])
            ->add('legacyBudget', NumberType::class, [
                'label' => 'Budget',
                'required' => true,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'placeholder' => 'Ex: 15000',
                    'min' => 0.01,
                    'step' => '0.01',
                    'data-validation-label' => 'Budget',
                ],
                'help' => 'Si vous saisissez un budget, il doit etre strictement superieur a 0.',
                'constraints' => [
                    new NotBlank(['message' => 'Le budget est requis.']),
                    new GreaterThan([
                        'value' => 0,
                        'message' => 'Le budget doit etre strictement superieur a 0.',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Decrivez l objectif du projet, son contexte et ses priorites.',
                    'maxlength' => 2000,
                    'data-validation-label' => 'Description',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La description est requise.']),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'La description doit contenir au moins 10 caracteres.',
                        'max' => 2000,
                        'maxMessage' => 'La description ne doit pas depasser 2000 caracteres.',
                    ]),
                ],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de creation',
                'required' => false,
                'widget' => 'single_text',
                'disabled' => true,
                'attr' => [
                    'data-validation-label' => 'Date de creation',
                ],
                'help' => 'Date technique de creation du projet, renseignee automatiquement.',
            ]);

        if ($options['include_status'] === true) {
            $builder->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'required' => true,
                'choices' => array_flip(Project::STATUSES),
                'placeholder' => 'Choisir un statut',
                'attr' => [
                    'data-validation-label' => 'Statut',
                ],
                'help' => 'Etat actuel du projet dans la plateforme.',
                'constraints' => [
                    new NotBlank(['message' => 'Le statut du projet est requis.']),
                    new AssertChoice([
                        'choices' => array_keys(Project::STATUSES),
                        'message' => 'Le statut du projet selectionne est invalide.',
                    ]),
                ],
            ]);
        }

        $builder->add('save', SubmitType::class, [
            'label' => $options['submit_label'],
            'attr' => [
                'class' => 'pm-btn pm-btn-primary',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
            'submit_label' => 'Enregistrer',
            'include_status' => true,
        ]);

        $resolver->setAllowedTypes('submit_label', 'string');
        $resolver->setAllowedTypes('include_status', 'bool');
    }
}