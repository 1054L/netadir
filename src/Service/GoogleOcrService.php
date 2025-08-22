<?php
namespace App\Service;

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Psr\Log\LoggerInterface;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class GoogleOcrService
{
    private ImageAnnotatorClient $vision;
    private string $projectDir;
    private $log;

    public function __construct(string $projectDir, string $googleCredentialsPath, LoggerInterface $logger)
    {
        $this->projectDir = $projectDir;
        $this->log = $logger;
        
        // La clave es pasar las credenciales directamente al constructor del cliente
        // en lugar de usar putenv(). La versión v2.0 lo soporta de esta forma.
        $this->vision = new ImageAnnotatorClient([
            'credentials' => $googleCredentialsPath
        ]);
    }

    public function extractTextFromImage(string $relativePath): ?string
    {
        $path = "{$this->projectDir}/public/uploads/{$relativePath}";
        if (!file_exists($path)) {
            // Considera usar un logger para registrar este error
            $this->log->error("File not found: {$path}");
            return null;
        }

        $content = file_get_contents($path);

        try {
            // Paso 1: Crear un objeto Image con el contenido de la imagen
            $image = new Image();
            $image->setContent($content);

            // Paso 2: Crear un objeto Feature para la detección de texto
            $feature = new Feature();
            $feature->setType(Feature\Type::TEXT_DETECTION);

            // Paso 3: Crear un objeto AnnotateImageRequest con la imagen y la feature
            $request = new AnnotateImageRequest();
            $request->setImage($image);
            $request->setFeatures([$feature]);

            // Paso 4: Crear un BatchAnnotateImagesRequest con la solicitud
            $batchRequest = new BatchAnnotateImagesRequest();
            $batchRequest->setRequests([$request]);

            // Paso 5: Llamar al método batchAnnotateImages del cliente
            $response = $this->vision->batchAnnotateImages($batchRequest);

            // Procesar la respuesta
            $responses = $response->getResponses();
            if (empty($responses)) {
                return null;
            }

            $firstResponse = $responses[0];
            $annotation = $firstResponse->getFullTextAnnotation();

            return $annotation ? $annotation->getText() : null;

        } catch (\Throwable $e) {
            // Logger es esencial aquí para saber por qué falla la conexión
            $this->log->error('Error calling Google Vision API: ' . $e->getMessage());
            return null;
        } finally {
            $this->vision->close();
        }
    }
}