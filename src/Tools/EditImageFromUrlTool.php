<?php

declare(strict_types=1);

namespace AiOrchestrator\Tools;

use AiOrchestrator\Utils\Storage\File;
use AiOrchestrator\Utils\Storage\StorageInterface;
use CURLFile;
use Psr\Log\LoggerInterface;
use AiOrchestrator\Exceptions\AiOrchestratorToolException;

class EditImageFromUrlTool implements ToolInterface {
    private string $apiKey;
    private LoggerInterface $logger;
    private StorageInterface $storage;

    public function __construct(string $apiKey, LoggerInterface $logger, StorageInterface $storage) {
        $this->apiKey = $apiKey;
        $this->logger = $logger;
        $this->storage = $storage;
    }

    public function getDefinition(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'edit_image_from_url',
                'description' => 'Create image with inspiration by image from a URL based on a prompt and returns <img> tag with the created image.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => ['type' => 'string'],
                        'prompt' => ['type' => 'string']
                    ],
                    'required' => ['url', 'prompt']
                ]
            ]
        ];
    }

    public function handle($call, $args): array {
        $promptSpecificForTool = "Repaint the image preserving its original style, composition, color palette, and texture exactly as in the reference. Do not change the style. Maintain the detailed elements, character designs, and shading exactly like the original image. Make the modifications requested without altering the artistic style, keeping the same type of drawing and anime aesthetic as the reference. ";

        $url = $args['url'];
        $prompt = $promptSpecificForTool . $args['prompt'];
        $this->logger->debug("Starting image edit for URL: {$url}");
        $this->logger->debug("Full prompt: {$prompt}");

        $imageContent = @file_get_contents($url);
        if ($imageContent === false) {
            $this->logger->error("Failed to download image from URL: {$url}");
            throw new AiOrchestratorToolException("Could not download image from URL: {$url}");
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'edit_img_') . '.png';
        if (file_put_contents($tmpFile, $imageContent) === false) {
            $this->logger->error("Failed to save temporary image file: {$tmpFile}");
            throw new AiOrchestratorToolException("Failed to save temporary image file");
        }

        $this->logger->info("Temporary image file saved: {$tmpFile}");

        $ch = curl_init('https://api.openai.com/v1/images/edits');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$this->apiKey}"]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'image' => new CURLFile($tmpFile, 'image/png'),
            'prompt' => $prompt,
            'model' => 'gpt-image-1',
            'n' => '1',
            'size' => 'auto',
            'quality' => 'high',
            'background' => 'transparent'
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        unlink($tmpFile);

        if ($response === false) {
            $this->logger->error("Curl error while editing image: {$curlError}");
            throw new AiOrchestratorToolException("Curl error during image edit: {$curlError}");
        }

        $this->logger->info("Received response from OpenAI API with HTTP code {$httpCode}");

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("JSON decode error: " . json_last_error_msg());
            throw new AiOrchestratorToolException("Invalid JSON response from API");
        }

        if (isset($result['error'])) {
            $errorMsg = $result['error']['message'] ?? 'Unknown error';
            $this->logger->error("OpenAI API returned error: {$errorMsg}");
            throw new AiOrchestratorToolException("OpenAI API error: {$errorMsg}");
        }

        $base64 = $result['data'][0]['b64_json'] ?? '';
        if ($base64 === '') {
            $this->logger->warning("API response missing image data for URL: {$url}");
            throw new AiOrchestratorToolException("Missing image data in API response");
        }

        $path = $this->storage->persistFile(new File($base64, 'image/png'));

        return [
            'role' => 'tool',
            'tool_call_id' => $call->id,
            'name' => 'edit_image_from_url',
            'content' => "<img src=\"{$path}\">",
        ];
    }
}
