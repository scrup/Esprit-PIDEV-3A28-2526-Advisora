<?php

namespace App\Form;

use App\Entity\Event;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titleEv', TextType::class, [
                'label' => 'Titre de l evenement',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: Forum investissement 2026',
                    'maxlength' => 160,
                    'data-validation-label' => 'Titre de l evenement',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le titre de l evenement est obligatoire.']),
                    new Length(['max' => 160, 'maxMessage' => 'Le titre ne doit pas depasser 160 caracteres.']),
                ],
            ])
            ->add('organisateurName', TextType::class, [
                'label' => 'Organisateur',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: Club innovation',
                    'maxlength' => 160,
                    'data-validation-label' => 'Organisateur',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom de l organisateur est obligatoire.']),
                    new Length(['max' => 160, 'maxMessage' => 'Le nom de l organisateur ne doit pas depasser 160 caracteres.']),
                ],
            ])
            ->add('localisationEv', TextType::class, [
                'label' => 'Localisation',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: Tunis, Centre de congres',
                    'maxlength' => 190,
                    'data-validation-label' => 'Localisation',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La localisation est obligatoire.']),
                    new Length(['max' => 190, 'maxMessage' => 'La localisation ne doit pas depasser 190 caracteres.']),
                ],
            ])
            ->add('capaciteEvnt', IntegerType::class, [
                'label' => 'Capacite',
                'required' => true,
                'attr' => [
                    'min' => 1,
                    'step' => 1,
                    'data-validation-label' => 'Capacite',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La capacite est obligatoire.']),
                    new GreaterThanOrEqual(['value' => 1, 'message' => 'La capacite doit etre superieure ou egale a 1.']),
                ],
            ])
            ->add('startDateEv', DateTimeType::class, [
                'label' => 'Date de debut',
                'required' => true,
                'widget' => 'single_text',
                'attr' => [
                    'data-validation-label' => 'Date de debut',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La date de debut est obligatoire.']),
                ],
            ])
            ->add('endDateEv', DateTimeType::class, [
                'label' => 'Date de fin',
                'required' => true,
                'widget' => 'single_text',
                'attr' => [
                    'data-validation-label' => 'Date de fin',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La date de fin est obligatoire.']),
                ],
            ])
            ->add('descriptionEv', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => [
                    'rows' => 6,
                    'maxlength' => 2000,
                    'placeholder' => 'Presentez le contexte, le programme et l objectif de l evenement.',
                    'data-validation-label' => 'Description',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La description est obligatoire.']),
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => $options['submit_label'],
                'attr' => [
                    'class' => 'pm-btn pm-btn-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
            'submit_label' => 'Enregistrer',
        ]);

        $resolver->setAllowedTypes('submit_label', 'string');
    }
}
