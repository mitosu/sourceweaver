<?php

namespace App\Form;

use App\Entity\ApiConfiguration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ApiConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre de la API',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: AbuseIPDB, VirusTotal, etc.'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Descripción',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Descripción opcional de la API y su propósito'
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Activa',
                'required' => false,
                'data' => true,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('options', CollectionType::class, [
                'entry_type' => ApiConfigurationOptionType::class,
                'label' => 'Opciones de configuración',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__option_name__',
                'attr' => [
                    'data-prototype' => true
                ]
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Guardar configuración',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ApiConfiguration::class,
        ]);
    }
}