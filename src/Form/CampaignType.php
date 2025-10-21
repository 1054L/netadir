<?php

namespace App\Form;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bundle\SecurityBundle\Security;

class CampaignType extends AbstractType
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Obtenemos la compañía del admin para filtrar el query
        $company = $this->security->getUser()->getCompany();

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre de la Campaña',
                'help' => 'Un nombre interno para identificar esta simulación.'
            ])
            ->add('templateName', ChoiceType::class, [
                'label' => 'Plantilla de Email',
                'choices' => [
                    'Factura Pendiente (Fácil)' => 'factura_pendiente.html.twig',
                    'Aviso de Google (Medio)' => 'aviso_google.html.twig',
                    'Premio Ganado (Difícil)' => 'premio_ganado.html.twig',
                ],
                'help' => 'El email falso que se enviará a los usuarios.'
            ])
            ->add('users', EntityType::class, [
                'label' => 'Usuarios Objetivo',
                'class' => User::class,
                // Query builder para mostrar SOLO usuarios de la empresa del admin
                'query_builder' => function (UserRepository $ur) use ($company) {
                    return $ur->createQueryBuilder('u')
                        ->where('u.company = :company')
                        ->andWhere('u.activo = true')
                        ->setParameter('company', $company)
                        ->orderBy('u.email', 'ASC');
                },
                'choice_label' => 'email',
                'multiple' => true,
                'expanded' => true, // Muestra esto como checkboxes
                'help' => 'Selecciona los usuarios que recibirán esta simulación.'
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Guardar como Borrador',
                'attr' => ['class' => 'btn btn-primary']
            ])
        ;
    }
}