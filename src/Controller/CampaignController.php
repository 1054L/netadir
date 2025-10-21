<?php
// src/Controller/CampaignController.php
namespace App\Controller;

use App\Entity\Campaign;
use App\Entity\CampaignResult;
use App\Form\CampaignType;
use App\Repository\CampaignRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/campaign')]
#[IsGranted('ROLE_ADMIN')]
class CampaignController extends AbstractController
{
    #[Route('/', name: 'app_campaign_index')]
    public function index(CampaignRepository $campaignRepository): Response
    {
        $company = $this->getUser()->getCompany();
        $campaigns = $campaignRepository->findBy(['company' => $company], ['createdAt' => 'DESC']);

        return $this->render('campaign/index.html.twig', [
            'campaigns' => $campaigns,
        ]);
    }

    #[Route('/new', name: 'app_campaign_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $campaign = new Campaign();
        $form = $this->createForm(CampaignType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Obtenemos los datos del formulario
            $data = $form->getData();

            // Configuramos la nueva campaña
            $campaign->setName($data['name']);
            $campaign->setTemplateName($data['templateName']);
            $campaign->setCompany($this->getUser()->getCompany());
            $campaign->setStatus('Borrador'); // La guardamos como borrador

            // Guardamos la campaña
            $entityManager->persist($campaign);

            // Aquí viene la magia:
            // Creamos un CampaignResult para cada usuario seleccionado
            foreach ($data['users'] as $user) {
                $result = new CampaignResult();
                $result->setCampaign($campaign);
                $result->setUser($user);
                $result->setIsSent(false); // Aún no se ha enviado
                $result->setIsClicked(false);
                $result->setIsOpened(false);
                $result->setIsCompromised(false);
                $entityManager->persist($result);
            }

            $entityManager->flush();

            $this->addFlash('success', '¡Campaña creada como borrador! Ya puedes lanzarla.');
            return $this->redirectToRoute('app_campaign_index');
        }

        return $this->render('campaign/new.html.twig', [
            'campaignForm' => $form->createView(),
        ]);
    }
    #[Route('/{id}/launch', name: 'app_campaign_launch')]
    public function launch(Campaign $campaign, EntityManagerInterface $entityManager): Response
    {
        // 1. Comprobación de seguridad: ¿Pertenece esta campaña a mi empresa?
        if ($campaign->getCompany() !== $this->getUser()->getCompany()) {
            $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        }

        // 2. Comprobación de estado: No lanzar una campaña que ya está completada
        if ($campaign->getStatus() !== 'Borrador') {
            $this->addFlash('warning', 'Esta campaña no se puede lanzar porque no está en modo borrador.');
            return $this->redirectToRoute('app_campaign_index');
        }

        // 3. ¡La simulación! Obtenemos todos los resultados de usuario
        $results = $campaign->getCampaignResults();

        foreach ($results as $result) {
            // Simulamos que todos los emails se enviaron
            $result->setIsSent(true);

            // Simulamos una tasa de click del 40% (puedes ajustar este número)
            $clicked = (bool) (rand(0, 100) < 40);
            $result->setIsClicked($clicked);

            // Si hicieron clic, simulamos una tasa de "compromiso" del 25%
            if ($clicked) {
                $compromised = (bool) (rand(0, 100) < 25);
                $result->setIsCompromised($compromised);
            } else {
                $result->setIsCompromised(false);
            }
        }

        // 4. Actualizamos el estado de la campaña
        $campaign->setStatus('Completada');
        $campaign->setCompletedAt(new \DateTimeImmutable()); // Marcamos la fecha de finalización

        // 5. Guardamos todos los cambios en la base de datos
        $entityManager->flush();

        $this->addFlash('success', '¡Campaña lanzada y datos simulados correctamente!');

        // 6. Redirigimos al reporte de esta campaña
        return $this->redirectToRoute('app_report_show', ['id' => $campaign->getId()]);
    }
}