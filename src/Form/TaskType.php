<?php

namespace App\Form;

use App\Entity\Task;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/**
 * @extends AbstractType<Task>
 */
class TaskType extends AbstractType
{
    /**
     * @param FormBuilderInterface<Task|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de la tache',
                'required' => true,
                'disabled' => $options['is_readonly'],
                'attr' => [
                    'maxlength' => 160,
                    'placeholder' => 'Ex: Preparer le livrable client',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le titre de la tache est obligatoire.']),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'required' => true,
                'disabled' => $options['is_readonly'],
                'choices' => array_flip(Task::STATUSES),
            ])
            ->add('weight', IntegerType::class, [
                'label' => 'Poids',
                'required' => true,
                'disabled' => $options['is_readonly'],
                'attr' => [
                    'step' => 1,
                    'placeholder' => '1',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le poids est obligatoire.']),
                    new Range([
                        'min' => 1,
                        'max' => 9,
                        'notInRangeMessage' => 'Le poids doit etre entre 1 et 9.',
                    ]),
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => $options['submit_label'],
                'disabled' => $options['is_readonly'],
                'attr' => [
                    'class' => 'pm-btn pm-btn-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'submit_label' => 'Enregistrer la tache',
            'is_readonly' => false,
        ]);

        $resolver->setAllowedTypes('submit_label', 'string');
        $resolver->setAllowedTypes('is_readonly', 'bool');
    }
}