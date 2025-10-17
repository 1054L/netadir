<?php

namespace App\Controller;

use App\Entity\OcrRequest;
use App\Service\GoogleOcrService;
use App\Service\GoogleAiService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/gastos')]
class OcrWebController extends AbstractController
{
    private $log;
    function __construct(LoggerInterface $logger)
    {
        $this->log = $logger;
    }

    #[Route('/subir', name: 'gasto_subir', methods: ['POST'])]
    public function upload(
        Request $request,
        UserInterface $user,
        EntityManagerInterface $em,
        GoogleOcrService $ocrService,
        GoogleAiService $aiService
    ): RedirectResponse {
        $customId = $request->request->get('custom_id');
        /** @var UploadedFile|null $file */
        $file = $request->files->get('image');
        $this->log->info('_____________________________________________________________________________ 1');
        
        if (!$file || !$file->isValid() || !str_starts_with($file->getMimeType(), 'image/')) {
            $this->addFlash('error', 'Archivo inválido. Asegúrate de subir una imagen.');
            return $this->redirectToRoute('user_profile'); // o donde muestres el formulario
        }
        
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        
        $filename = uniqid('ocr_') . '.' . $file->guessExtension();
        $file->move($uploadDir, $filename);
        
        $this->log->info('_____________________________________________________________________________ 2');
        // Crear nueva entidad OcrRequest
        $ocr = new OcrRequest();
        $ocr->setUser($user);
        $ocr->setFilename($filename);
        $ocr->setCustomId($customId);
        $ocr->setStatus('pending');
        $ocr->setCreatedAt(new \DateTimeImmutable());
        
        // Ejecutar OCR y análisis IA
        $ocrTxt = $ocrService->extractTextFromImage($filename);
        $aiResult = null;
        
        $this->log->info('_____________________________________________________________________________ 3');
        if ($ocrTxt) {
            $this->log->info('_____________________________________________________________________________ 4a');
            $ocr->setTxt($ocrTxt);
            try {
                $this->log->info('_____________________________________________________________________________Analizando texto con IA...');
                $aiResult = $aiService->analyzeText($ocrTxt);
                $jsonString = trim($aiResult, "```json\n\n ");
                $jsonString = trim($jsonString, "\n```");
                // $aiResult = json_decode($jsonString, true);
            } catch (\Exception $e) {
                $this->log->info('_____________________________________________________________________________Exception en el analisis...');
                $ocr->setResult('Error en el análisis de IA: ' . $e->getMessage());
                $ocr->setStatus('error');
                $em->persist($ocr);
                $em->flush();
                dd($e);
                
                $this->log->error('error', 'Error al analizar el texto con IA: ' . $e->getMessage());
                return $this->redirectToRoute('user_profile');
            }
            
            $ocr->setResult($jsonString ?? 'Sin respuesta de la IA');
            $ocr->setStatus('completed');
        } else {
            $this->log->info('_____________________________________________________________________________ 4b');
            $ocr->setTxt(null);
            $ocr->setResult('OCR fallido');
            $ocr->setStatus('error');
        }
        
        $em->persist($ocr);
        $em->flush();
        
        $this->addFlash('success', 'Gasto subido correctamente.');
        $this->log->info('_____________________________________________________________________________ 5');
        return $this->redirectToRoute('user_profile');
    }
}
