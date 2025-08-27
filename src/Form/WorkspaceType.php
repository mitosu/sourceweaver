<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class WorkspaceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre del Workspace',
                'attr' => [
                    'placeholder' => 'Ingresa un nombre para el workspace',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'El nombre del workspace es obligatorio']),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 255,
                        'minMessage' => 'El nombre debe tener al menos {{ limit }} caracteres',
                        'maxMessage' => 'El nombre no puede tener más de {{ limit }} caracteres'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'workspace_new',
        ]);
    }
}