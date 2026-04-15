<?php

namespace App\Form;

use App\Entity\Booking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numTicketBk', IntegerType::class, [
                'label' => 'Nombre de tickets',
                'required' => true,
                'attr' => [
                    'min' => 1,
                    'step' => 1,
                    'data-validation-label' => 'Nombre de tickets',
                ],
                'help' => 'Le total est conserve a 0 dans cette version car la base ne contient pas de tarification evenement.',
                'constraints' => [
                    new NotBlank(['message' => 'Le nombre de tickets est obligatoire.']),
                    new GreaterThanOrEqual(['value' => 1, 'message' => 'Le nombre de tickets doit etre superieur ou egal a 1.']),
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
            'data_class' => Booking::class,
            'submit_label' => 'Enregistrer',
        ]);

        $resolver->setAllowedTypes('submit_label', 'string');
    }
}
