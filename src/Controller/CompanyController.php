<?php

namespace App\Controller;

use App\Form\CompanyConfigType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CompanyController extends AbstractController
{
    #[Route('/company', name: 'app_company')]
    public function index(): Response
    {
        return $this->render('company/index.html.twig', [
            'controller_name' => 'CompanyController',
        ]);
    }

    #[Route('/config', name: 'app_company_config')]
    public function config(Request $request, EntityManagerInterface $entityManager): Response
    {
        // 1. Obtenemos la empresa del administrador que está logueado
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        // 2. Creamos el formulario y le pasamos la empresa
        $form = $this->createForm(CompanyConfigType::class, $company);
        $form->handleRequest($request);

        // 3. Comprobamos si se ha enviado y es válido
        if ($form->isSubmitted() && $form->isValid()) {
            // 4. Guardamos los cambios en la base de datos
            $entityManager->flush();

            $this->addFlash('success', '¡Configuración de la empresa guardada!');

            // Redirigimos a la misma página
            return $this->redirectToRoute('app_company_config');
        }

        // 5. Renderizamos la plantilla y le pasamos el formulario
        return $this->render('company/config.html.twig', [
            'config_form' => $form->createView(),
        ]);
    }
}
