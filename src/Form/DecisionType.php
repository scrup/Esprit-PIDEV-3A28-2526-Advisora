<?php

namespace App\Form;

use App\Entity\Decision;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class DecisionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $project = $options['project'] ?? null;

        if ($project) {
            $client = $project->getUser()?->getPrenomUser() . ' ' . $project->getUser()?->getNomUser();

            $builder
                ->add('projectId', HiddenType::class, [
                    'mapped' => false,
                    'data' => (string) $project->getIdProj(),
                ])
                ->add('projectTitle', TextType::class, [
                    'mapped' => false,
                    'label' => 'Projet',
                    'data' => (string) $project->getTitleProj(),
                    'disabled' => true,
                ])
                ->add('projectClient', TextType::class, [
                    'mapped' => false,
                    'label' => 'Client',
                    'data' => trim((string) $client),
                    'disabled' => true,
                ]);
        }
        $builder
            ->add('decisionTitle', ChoiceType::class, [
                'label' => 'Statut de la decision',
                'required' => true,
                'choices' => [
                    'En attente' => 'pending',
                    'Accepté' => 'accepted',
                    'Refusé' => 'rejected',
                ],
                'placeholder' => 'Choisir une decision',
                'attr' => [
                    'data-validation-label' => 'Statut de la decision',
                ],
                'help' => 'Choisissez si le projet reste en attente, est accepte ou est refuse.',
                'constraints' => [
                    new NotBlank(['message' => 'Le statut de la décision est requis.']),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Justification de la decision',
                'required' => true,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Expliquez pourquoi le projet est accepte, refuse ou laisse en attente.',
                    'maxlength' => 2000,
                    'data-validation-label' => 'Justification de la decision',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La justification de la décision est requise.']),
                ],
            ])
            ->add('decisionDate', DateType::class, [
                'label' => 'Date de decision',
                'required' => true,
                'widget' => 'single_text',
                'disabled' => true,
                'attr' => [
                    'data-validation-label' => 'Date de decision',
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
            'data_class' => Decision::class,
            'submit_label' => 'Enregistrer la decision',
            'project' => null,
        ]);

        $resolver->setAllowedTypes('submit_label', 'string');
        $resolver->setAllowedTypes('project', ['null', 'App\\Entity\\Project']);
    }
}
