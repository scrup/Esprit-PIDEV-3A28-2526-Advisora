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
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProjectType extends AbstractType
{
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
                'help' => 'Si vous saisissez un budget, il doit être strictement supérieur à 0.',
                'constraints' => [
                    new NotBlank(['message' => 'Le budget est requis.']),
                    new GreaterThan(['value' => 0, 'message' => 'Le budget doit être strictement supérieur à 0.']),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Décrivez l objectif du projet, son contexte et ses priorités.',
                    'maxlength' => 2000,
                    'data-validation-label' => 'Description',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La description est requise.']),
                ],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de création',
                'required' => false,
                'widget' => 'single_text',
                'disabled' => true,
                'attr' => [
                    'data-validation-label' => 'Date de création',
                ],
                'help' => 'Date technique de création du projet, renseignée automatiquement.',
            ]);

        if ($options['include_status']) {
            $builder->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'required' => true,
                'choices' => array_flip(Project::STATUSES),
                'placeholder' => 'Choisir un statut',
                'attr' => [
                    'data-validation-label' => 'Statut',
                ],
                'help' => 'État actuel du projet dans la plateforme.',
                'constraints' => [
                    new NotBlank(['message' => 'Le statut du projet est requis.']),
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
