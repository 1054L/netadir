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
        $this->log = $logger;
        $this->client = new PredictionServiceClient([
            'credentials' => $credentialsPath,
        ]);
    }

    public function analyzeText(string $ocrText): ?string
    {
        // Define las instrucciones para el modelo (el "prompt")
        $prompt = <<<EOT
        Eres un asistente de IA experto en la digitalización de documentos y contabilidad. Tu tarea es extraer de forma precisa y estructurada la información del texto de un ticket o factura que ha sido procesado por un OCR.

        La salida debe ser un único objeto JSON válido. Si un campo no se encuentra en el texto, su valor debe ser `null`.

        ### Reglas de Extracción:
        1.  **Fechas**: La fecha (`date`) y la hora (`time`) deben estar separadas. La fecha debe estar en formato `YYYY-MM-DD`.
        2.  **Importes Numéricos**: Todos los importes (`total`, `subtotal`, `taxes.amount`) deben ser números, no strings, utilizando un punto `.` como separador decimal.
        3.  **CIF/NIF**: Busca un identificador fiscal claro. A menudo va precedido por "CIF", "NIF" o "ES".
        4. **ticketNumber**: El número de ticket o factura debe estar en el campo `ticketNumber`, suele ir precedido por "FT", "Factura", "Ticket", "FActura Nº", "num. fact.", etc.; es importante respetar signos de puntuación, espacios, guiones, barras, etc.
        5. **taxes**: La base imponible se trata del total sin impuestos, y los impuestos deben detallarse en el array `taxes` con su tipo, porcentaje y cantidad. Si no hay desglose, crea un único elemento con el total de impuestos. Suele ir precedido por "IVA", "IGIC", "Impuesto", "IVA 21%", "IVA 10%", etc.
        6. **company**: El nombre de la empresa suele estar en la parte superior del ticket o factura, a veces en mayúsculas o con un formato destacado. Suele aparecer junto al CIF/NIF. En el caso de empresas españolas, puede ir precedido por "S.L.", "S.A.", "SL", "SA", etc. Si ya tienes el CIF busca el nombre de la empresa en internet para mayor precisión.
        7. **address**: La dirección completa de la empresa, que puede incluir calle, número, ciudad, código postal y país. Suele ir debajo del nombre de la empresa.

        ### Ejemplo de Salida Esperada:
        {
        "company": "BricoSol S.L.",
        "CIF": "B12345678",
        "address": "Calle Falsa 123, 28080 Madrid",
        "ticketNumber": "FT 24/00567",
        "date": "2025-09-05",
        "time": "13:45",
        "taxes": [
            {
            "base": 25.00,
            "taxType": "IVA",
            "rate": 21.0,
            "amount": 5.25
            }
        ],
        "total": 30.25,
        "paymentMethod": "Tarjeta"
        }   
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


/*
$prompt = <<<EOT
        Eres un asistente de IA experto en la digitalización de documentos y contabilidad. Tu tarea es extraer de forma precisa y estructurada la información del texto de un ticket o factura que ha sido procesado por un OCR.

        La salida debe ser un único objeto JSON válido. Si un campo no se encuentra en el texto, su valor debe ser `null`.

        ### Reglas de Extracción:
        1.  **Fechas**: La fecha (`date`) y la hora (`time`) deben estar separadas. La fecha debe estar en formato `YYYY-MM-DD`.
        2.  **Importes Numéricos**: Todos los importes (`total`, `subtotal`, `taxes.amount`) deben ser números, no strings, utilizando un punto `.` como separador decimal.
        3.  **CIF/NIF**: Busca un identificador fiscal claro. A menudo va precedido por "CIF", "NIF" o "ES".
        4.  **Líneas de Concepto**: Extrae cada producto o servicio como un objeto dentro del array `lineItems`. Si no hay un desglose claro, crea un único elemento con la descripción general.

        ### Ejemplo de Salida Esperada:
        {
        "company": "BricoSol S.L.",
        "CIF": "B12345678",
        "address": "Calle Falsa 123, 28080 Madrid",
        "ticketNumber": "FT 24/00567",
        "date": "2025-09-05",
        "time": "13:45",
        "lineItems": [
            {
            "description": "Tornillo autoperforante",
            "quantity": 20,
            "unitPrice": 0.50,
            "totalPrice": 10.00
            },
            {
            "description": "Pintura blanca 5L",
            "quantity": 1,
            "unitPrice": 15.00,
            "totalPrice": 15.00
            }
        ],
        "subtotal": 25.00,
        "taxes": [
            {
            "taxType": "IVA",
            "rate": 21.0,
            "amount": 5.25
            }
        ],
        "total": 30.25,
        "paymentMethod": "Tarjeta"
        }   
$ocrText
EOT;
*/