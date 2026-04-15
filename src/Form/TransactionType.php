<?php

namespace App\Form;

use App\Entity\Transaction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class TransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('DateTransac', DateType::class, [
                'label' => 'Date de transaction',
                'required' => true,
                'widget' => 'single_text',
                'attr' => [
                    'data-validation-label' => 'Date de transaction',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La date de transaction est obligatoire.']),
                ],
            ])
            ->add('MontantTransac', NumberType::class, [
                'label' => 'Montant a transferer',
                'required' => true,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0.01,
                    'step' => '0.01',
                    'placeholder' => 'Ex: 1200',
                    'data-validation-label' => 'Montant de transaction',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le montant de la transaction est obligatoire.']),
                    new GreaterThan(['value' => 0, 'message' => 'Le montant de la transaction doit etre strictement superieur a 0.']),
                ],
            ])
            ->add('type', TextType::class, [
                'label' => 'Type',
                'required' => false,
                'attr' => [
                    'maxlength' => 100,
                    'placeholder' => 'Ex: INVESTMENT_PAYMENT',
                    'data-validation-label' => 'Type de transaction',
                ],
                'help' => 'Si vous laissez vide, la valeur `INVESTMENT_PAYMENT` sera utilisee.',
                'constraints' => [
                    new Length([
                        'max' => 100,
                        'maxMessage' => 'Le type de transaction ne doit pas depasser 100 caracteres.',
                    ]),
                    new Regex([
                        'pattern' => '/^[A-Za-z][A-Za-z0-9_-]*$/',
                        'message' => 'Le type de transaction doit commencer par une lettre et ne contenir que des lettres, chiffres, tirets ou underscores.',
                    ]),
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
            'data_class' => Transaction::class,
            'submit_label' => 'Enregistrer',
        ]);

        $resolver->setAllowedTypes('submit_label', 'string');
    }
}
