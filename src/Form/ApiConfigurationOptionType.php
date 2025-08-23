<?php

namespace App\Form;

use App\Entity\ApiConfigurationOption;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ApiConfigurationOptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('optionName', TextType::class, [
                'label' => 'Opción',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: API_KEY, BASE_URL, TIMEOUT'
                ]
            ])
            ->add('optionValue', TextType::class, [
                'label' => 'Valor',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Valor de la opción'
                ]
            ])
            ->add('isEncrypted', CheckboxType::class, [
                'label' => 'Encriptar valor (para API keys sensibles)',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ApiConfigurationOption::class,
        ]);
    }
}