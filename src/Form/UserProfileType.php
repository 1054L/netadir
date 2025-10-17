<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
            ])
            ->add('apellidos', TextType::class, [
                'label' => 'Apellidos',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Correo Electrónico',
            ])
            ->add('dni', TextType::class, [
                'label' => 'DNI',
                'required' => false,
            ])
            ->add('puesto', TextType::class, [
                'label' => 'Puesto en la Empresa',
                'required' => false,
            ])
            ->add('guardar', SubmitType::class, [
                'label' => 'Guardar Cambios',
                'attr' => ['class' => 'btn btn-primary w-100 mt-3'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
