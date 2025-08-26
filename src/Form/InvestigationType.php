<?php

namespace App\Form;

use App\Entity\Investigation;
use App\Entity\Workspace;
use App\Repository\WorkspaceRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvestigationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre de la investigación',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: Análisis de dominio sospechoso'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Descripción',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Describe el objetivo y contexto de la investigación'
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Estado',
                'choices' => [
                    'Borrador' => 'draft',
                    'Activa' => 'active',
                    'Completada' => 'completed',
                    'Archivada' => 'archived'
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Prioridad',
                'choices' => [
                    'Baja' => 'low',
                    'Media' => 'medium',
                    'Alta' => 'high',
                    'Urgente' => 'urgent'
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('workspace', EntityType::class, [
                'class' => Workspace::class,
                'choice_label' => 'name',
                'label' => 'Workspace',
                'placeholder' => 'Selecciona un workspace',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Guardar investigación',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Investigation::class,
        ]);
    }
}