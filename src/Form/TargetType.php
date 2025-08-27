<?php

namespace App\Form;

use App\Entity\Target;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TargetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Tipo de target',
                'choices' => [
                    'Dirección IP' => 'ip',
                    'URL' => 'url',
                    'Dominio' => 'domain',
                    'Email' => 'email',
                    'Hash (MD5/SHA1/SHA256)' => 'hash',
                    'Teléfono' => 'phone'
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('value', TextType::class, [
                'label' => 'Valor',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: 192.168.1.1, example.com, user@domain.com'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Descripción',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Información adicional sobre este target'
                ]
            ])
            ->add('osintTools', ChoiceType::class, [
                'label' => 'Herramientas OSINT',
                'choices' => [
                    'VirusTotal' => 'virustotal',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'Selecciona las herramientas OSINT que deseas usar para analizar este target',
                'attr' => [
                    'class' => 'osint-tools-checkboxes'
                ]
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Añadir target',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Target::class,
        ]);
    }
}