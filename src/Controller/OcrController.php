<?php
namespace App\Controller;

use App\Entity\OcrRequest;
use App\Repository\OcrRequestRepository;
use App\Service\GoogleAiService;
use App\Service\GoogleOcrService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api/ocr')]
class OcrController extends AbstractController
{
    #[Route('', name: 'api_ocr', methods: ['POST'])]
    public function upload(Request $request, 
        UserInterface $user, 
        EntityManagerInterface $em,
        GoogleOcrService $ocrService,
        GoogleAiService $aiService): JsonResponse
    {
        $customId = $request->request->get('custom_id');
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        // Opción 1: archivo subido como multipart/form-data
        if ($request->files->has('image')) {
            $file = $request->files->get('image');

            if (!$file || !$file->isValid()) {
                return new JsonResponse(['error' => 'Imagen no válida'], Response::HTTP_BAD_REQUEST);
            }

            $mime = $file->getMimeType();
            if (!str_starts_with($mime, 'image/')) {
                return new JsonResponse(['error' => 'Solo se aceptan imágenes'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
            }

            $filename = uniqid('ocr_') . '.' . $file->guessExtension();
            $file->move($uploadDir, $filename);
        } elseif ($request->getContentTypeFormat() === 'json') {
            $data = json_decode($request->getContent(), true);
            $base64 = $data['image_base64'] ?? null;
            $customId = $data['custom_id'] ?? null;

            if (!$base64) {
                return new JsonResponse(['error' => 'Falta image_base64'], Response::HTTP_BAD_REQUEST);
            }

            // Detectar tipo MIME de la imagen base64 (con prefijo data:image/...)
            if (str_starts_with($base64, 'data:image/')) {
                [$metadata, $encoded] = explode(',', $base64, 2);
                preg_match('/data:image\/(\w+);base64/', $metadata, $matches);
                $extension = $matches[1] ?? 'jpg';
            } else {
                $encoded = $base64;
                $extension = 'jpg'; // por defecto si no se indica
            }

            $binaryData = base64_decode($encoded);

            if ($binaryData === false) {
                return new JsonResponse(['error' => 'Base64 inválido'], Response::HTTP_BAD_REQUEST);
            }

            $filename = uniqid('ocr_') . '.' . $extension;
            file_put_contents($uploadDir . '/' . $filename, $binaryData);
        } else {
            return new JsonResponse(['error' => 'Formato de solicitud no válido'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $ocr = new OcrRequest();
        $ocr->setUser($user);
        $ocr->setFilename($filename);
        $ocr->setCustomId($customId);
        $ocr->setStatus('pending');
        $ocr->setCreatedAt(new \DateTimeImmutable());
        
        $ocrTxt = $ocrService->extractTextFromImage($filename);
        $aiData = '';
        if ($ocrTxt) {
            $aiResult = $aiService->analyzeText($ocrTxt);
            $jsonString = trim($aiResult, "```json\n\n ");
            $jsonString = trim($jsonString, "\n```");
            $aiData = json_decode($jsonString, true);
            
            $ocr->setTxt($ocrTxt);
            $ocr->setResult($jsonString);
            $ocr->setStatus('completed');
        } else {
            $ocr->setTxt(null);
            $ocr->setResult('OCR fallido');
            $ocr->setStatus('error');
        }

        $em->persist($ocr);
        $em->flush();
        
        if (json_last_error() === JSON_ERROR_NONE) {
            // Si es un JSON válido, puedes usarlo en tu respuesta final
            $response = [
                'id' => $ocr->getId(),
                'filename' => $ocr->getFilename(),
                'custom_id' => $ocr->getCustomId(),
                'status' => $ocr->getStatus(),
                'created_at' => $ocr->getCreatedAt()->format('Y-m-d H:i:s'),
                'data' => $aiData, // Aquí está el objeto JSON limpio
                'user' => $user->getUserIdentifier(),
            ];
        } else {
            // Manejar el caso en que el JSON no es válido
            $response = [
                'error' => 'No se pudo procesar la respuesta de la IA',
                'raw_data' => $aiResult // Devuelve la respuesta original para depurar
            ];
        }
        // Simulación de respuesta
        return new JsonResponse($response);
    }

    #[Route('/list', name: 'api_ocr_list', methods: ['GET'])]
    public function list(
        UserInterface $user,
        OcrRequestRepository $ocrRequestRepo
    ): JsonResponse {
        $requests = $ocrRequestRepo->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        $data = array_map(function ($req) {
            return [
                'id' => $req->getId(),
                'filename' => $req->getFilename(),
                'custom_id' => $req->getCustomId(),
                'status' => $req->getStatus(),
                'created_at' => $req->getCreatedAt()->format('Y-m-d H:i:s'),
                'result' => $req->getTxt(),
            ];
        }, $requests);

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'api_ocr_detail', methods: ['GET'])]
    public function detail(int $id, OcrRequestRepository $ocrRequestRepo): JsonResponse
    {
        $request = $ocrRequestRepo->find($id);

        if (!$request) {
            return new JsonResponse(['error' => 'Solicitud no encontrada'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $request->getId(),
            'filename' => $request->getFilename(),
            'custom_id' => $request->getCustomId(),
            'status' => $request->getStatus(),
            'created_at' => $request->getCreatedAt()->format('Y-m-d H:i:s'),
            'result' => $request->getTxt(),
        ]);
    }
}
