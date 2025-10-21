<?php

namespace App\Controller;

use App\Entity\Campaign;
use App\Repository\CampaignRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report')]
#[IsGranted('ROLE_ADMIN')] // Solo los admins pueden ver reportes
class ReportController extends AbstractController
{
    #[Route('/', name: 'app_report_index')]
    public function index(CampaignRepository $campaignRepository): Response
    {
        // Obtenemos la compañía del admin actual
        $company = $this->getUser()->getCompany();

        // Buscamos todas las campañas completadas de esta compañía
        $campaigns = $campaignRepository->findBy(
            ['company' => $company, 'status' => 'Completada'],
            ['completedAt' => 'DESC']
        );

        return $this->render('report/index.html.twig', [
            'campaigns' => $campaigns,
        ]);
    }

    #[Route('/{id}', name: 'app_report_show')]
    public function show(Campaign $campaign): Response
    {
        // Comprobamos que el admin no pueda ver reportes de otra empresa
        if ($campaign->getCompany() !== $this->getUser()->getCompany()) {
            $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        }

        // --- Cálculos de Estadísticas (Ejemplo) ---
        $totalUsers = count($campaign->getCampaignResults());
        $totalClicked = 0;
        $totalCompromised = 0;
        $compromisedUsers = [];

        foreach ($campaign->getCampaignResults() as $result) {
            if ($result->isClicked()) {
                $totalClicked++;
            }
            if ($result->isCompromised()) {
                $totalCompromised++;
                $compromisedUsers[] = $result->getUser();
            }
        }

        $clickRate = ($totalUsers > 0) ? ($totalClicked / $totalUsers) * 100 : 0;
        $compromiseRate = ($totalUsers > 0) ? ($totalCompromised / $totalUsers) * 100 : 0;

        return $this->render('report/show.html.twig', [
            'campaign' => $campaign,
            'totalUsers' => $totalUsers,
            'totalClicked' => $totalClicked,
            'totalCompromised' => $totalCompromised,
            'clickRate' => $clickRate,
            'compromiseRate' => $compromiseRate,
            'compromisedUsers' => $compromisedUsers,
        ]);
    }
}