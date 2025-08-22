<?php
namespace App\Controller;

use App\Entity\ApiKey;
use App\Repository\OcrRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\User\UserInterface;

class UserController extends AbstractController
{
    #[Route('/me', name: 'user_profile')]
    public function me(UserInterface $user, OcrRequestRepository $ocrRequestRepository): Response
    {
        $ocrRequests = $ocrRequestRepository->findBy(['user' => $this->getUser()], ['createdAt' => 'DESC']);
        return $this->render('user/me.html.twig', [
            'user' => $user,
            'ocrRequests' => $ocrRequests,
        ]);
    }

    #[Route('/me/apikey/new', name: 'user_generate_apikey', methods: ['POST'])]
    public function generateApiKey(UserInterface $user, EntityManagerInterface $em): Response
    {
        $token = bin2hex(random_bytes(20));

        $key = new ApiKey();
        $key->setUser($user);
        $key->setToken($token);
        $key->setActive(true);
        $key->setCreatedAt(new \DateTimeImmutable());

        $em->persist($key);
        $em->flush();

        $this->addFlash('success', 'API Key generada correctamente.');

        return $this->redirectToRoute('user_profile');
    }
}
