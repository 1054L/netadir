<?php

namespace App\Service;

use Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient;
use Google\Cloud\AIPlatform\V1\Content;
use Google\Cloud\AIPlatform\V1\GenerateContentRequest;
use Google\Cloud\AIPlatform\V1\Part;
use Psr\Log\LoggerInterface;

class GoogleAiService
{
    private PredictionServiceClient $client;
    private string $modelName;
    private LoggerInterface $log;

    public function __construct(string $googleProject, string $region, string $model, string $credentialsPath, string $projectDir, LoggerInterface $logger)
    {
        // El nombre completo del modelo en Vertex AI
        $publisher = 'google'; 
        $this->modelName = "projects/{$googleProject}/locations/{$region}/publishers/{$publisher}/models/{$model}";
        // Se inicializa el cliente con las credenciales directamente
        $fullCredentialsPath = $projectDir . '/' . $credentialsPath;
        $this->log = $logger;
        $this->client = new PredictionServiceClient([
            'credentials' => $fullCredentialsPath,
        ]);
    }

    public function analyzeText(string $ocrText): ?string
    {
        // Define las instrucciones para el modelo (el "prompt")
        $prompt = <<<EOT
        Extract the following data from the ticket text. If a piece of data doesn't exist, return it as null. The output format must be JSON:

- company: Company name
- CIF: The company's tax identification number (NIF)
- date: Document date in YYYY-MM-DD format
- total: The total amount, including decimals
- concept: A brief description of the document's purpose

Ticket text:
$ocrText
EOT;

        // Construye el objeto Content con el prompt
        $part = new Part();
        $part->setText($prompt);

        $content = new Content();
        $content->setRole('user');
        $content->setParts([$part]);
        
        // Crea la solicitud GenerateContentRequest
        $request = new GenerateContentRequest();
        $request->setModel($this->modelName);
        $request->setContents([$content]);
        
        try {
            // Realiza la llamada a la API
            $response = $this->client->generateContent($request);
            
            // Procesa la respuesta para obtener el texto generado
            foreach ($response->getCandidates() as $candidate) {
                foreach ($candidate->getContent()->getParts() as $part) {
                    $this->log->info("AI Response: " . $part->getText());
                    return $part->getText();
                }
            }

            return 'null try again'; // Si no hay candidatos, devuelve un mensaje de error

        } catch (\Throwable $e) {
            // Maneja y registra los errores para depuración
            $this->log->error("Error al llamar a la API de Vertex AI: " . $e->getMessage());
            return 'null error';
        }
    }
}