<?php

namespace App\Form;

use App\Entity\Cataloguefournisseur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class SupplierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomFr', TextType::class, [
                'label' => 'Nom fournisseur',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: Fournisseur principal IT',
                    'maxlength' => 160,
                    'data-validation-label' => 'Nom fournisseur',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Nom fournisseur obligatoire.']),
                    new Length(['max' => 160, 'maxMessage' => 'Le nom fournisseur ne doit pas depasser 160 caracteres.']),
                ],
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantite du produit',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: 10',
                    'min' => 0,
                    'data-validation-label' => 'Quantite du produit',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Quantite du produit est obligatoire.']),
                    new GreaterThanOrEqual(['value' => 0, 'message' => 'Quantite >= 0 obligatoire.']),
                ],
            ])
            ->add('fournisseur', TextType::class, [
                'label' => 'Entreprise fournisseur',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: HP Tunisie',
                    'maxlength' => 160,
                    'data-validation-label' => 'Entreprise fournisseur',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Entreprise fournisseur obligatoire.']),
                    new Length(['max' => 160, 'maxMessage' => 'Le nom de l entreprise ne doit pas depasser 160 caracteres.']),
                ],
            ])
            ->add('emailFr', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'attr' => [
                    'placeholder' => 'contact@fournisseur.com',
                    'maxlength' => 180,
                    'data-validation-label' => 'Email',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Email obligatoire.']),
                    new Email(['message' => 'Email invalide.']),
                    new Length(['max' => 180, 'maxMessage' => 'L email ne doit pas depasser 180 caracteres.']),
                ],
            ])
            ->add('localisationFr', TextType::class, [
                'label' => 'Localisation',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: Tunis',
                    'maxlength' => 180,
                    'data-validation-label' => 'Localisation',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Localisation obligatoire.']),
                    new Length(['max' => 180, 'maxMessage' => 'La localisation ne doit pas depasser 180 caracteres.']),
                ],
            ])
            ->add('numTelFr', TextType::class, [
                'label' => 'Numero de telephone',
                'required' => true,
                'attr' => [
                    'placeholder' => '+21612345678',
                    'maxlength' => 20,
                    'data-validation-label' => 'Numero de telephone',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Numero de telephone obligatoire.']),
                    new Regex([
                        'pattern' => '/^\+?[0-9]{7,15}$/',
                        'message' => 'Numero de telephone invalide.',
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
            'data_class' => Cataloguefournisseur::class,
            'submit_label' => 'Enregistrer',
        ]);

        $resolver->setAllowedTypes('submit_label', 'string');
    }
}
