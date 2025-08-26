<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BulkTargetImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('targets', TextareaType::class, [
                'label' => 'Targets (uno por línea)',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 10,
                    'placeholder' => "192.168.1.1\nexample.com\nhttps://malicious-site.com\nuser@domain.com\nd41d8cd98f00b204e9800998ecf8427e"
                ],
                'help' => 'Introduce los targets uno por línea. El sistema detectará automáticamente el tipo.'
            ])
            ->add('autoAnalyze', CheckboxType::class, [
                'label' => 'Analizar automáticamente tras importar',
                'required' => false,
                'data' => true,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Si está marcado, se ejecutará automáticamente el análisis de todos los targets importados.'
            ])
            ->add('import', SubmitType::class, [
                'label' => 'Importar targets',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}