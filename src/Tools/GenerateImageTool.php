<?php

declare(strict_types=1);

namespace AiOrchestrator\Tools;

use AiOrchestrator\Utils\Storage\File;
use AiOrchestrator\Utils\Storage\StorageInterface;
use OpenAI\Client;
use Psr\Log\LoggerInterface;
use AiOrchestrator\Exceptions\AiOrchestratorToolException;

class GenerateImageTool implements ToolInterface {

    private Client $client;
    private LoggerInterface $logger;
    private StorageInterface $storage;

    public function __construct(Client $client, LoggerInterface $logger, StorageInterface $storage) {
        $this->client = $client;
        $this->logger = $logger;
        $this->storage = $storage;
    }

    public function getDefinition(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'generate_image',
                'description' => 'Generates an image from a prompt and returns <img> tag with the saved image.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['prompt' => ['type' => 'string']],
                    'required' => ['prompt']
                ]
            ]
        ];
    }

    public function handle($call, $args): array {
        $prompt = $args['prompt'];
        $this->logger->info("Starting image generation for prompt: {$prompt}");

        try {
            $result = $this->client->images()->create([
                'model' => 'gpt-image-1',
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'quality' => 'high',
                'background' => 'transparent'
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("OpenAI image generation failed: " . $e->getMessage());
            throw new AiOrchestratorToolException("OpenAI image generation failed: " . $e->getMessage(), 0, $e);
        }

        $this->logger->debug("OpenAI API response received");

        $base64 = $result->data[0]['b64_json'] ?? '';
        if ($base64 === '') {
            $this->logger->error("Image generation returned empty base64 for prompt: {$prompt}");
            throw new AiOrchestratorToolException("Image generation returned empty base64 data.");
        }

        $path = $this->storage->persistFile(new File($base64, 'image/png'));
        $this->logger->info("Image generation successful: {$path}");

        return [
            'role' => 'tool',
            'tool_call_id' => $call->id,
            'name' => 'generate_image',
            'content' => "<img src=\"{$path}\">",
        ];
    }
}
