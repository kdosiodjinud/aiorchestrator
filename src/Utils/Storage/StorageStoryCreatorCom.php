<?php

declare(strict_types=1);

namespace AiOrchestrator\Utils\Storage;

use AiOrchestrator\Exceptions\AiOrchestratorUtilException;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class StorageStoryCreatorCom implements StorageInterface
{
    private ?string $jwtToken;

    public function __construct(private HttpClientInterface $httpClient, ?string $jwtToken = null)
    {
        $this->jwtToken = $jwtToken;
    }

    public function persistFile(File $file): string
    {
        $base64Content = $file->getBase64Content();
        $fileType = $file->getFileType();

        if (empty($base64Content) || empty($fileType)) {
            throw new AiOrchestratorUtilException('Invalid file content or type');
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        if ($this->jwtToken !== null) {
            $headers['Authorization'] = 'Bearer ' . $this->jwtToken;
        }

        try {
            $response = $this->httpClient->request('POST', '/api/image/save', [
                'headers' => $headers,
                'json' => [
                    'base64' => $base64Content,
                    'fileType' => $fileType,
                ],
            ]);

            $content = $response->toArray();

            if ($content['status'] !== 'success') {
                throw new AiOrchestratorUtilException('File saving failed: ' . ($content['message'] ?? 'Unknown error'));
            }

            return $content['filePath'] ?? '';
        } catch (TransportExceptionInterface | ServerExceptionInterface $e) {
            throw new AiOrchestratorUtilException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new AiOrchestratorUtilException('Unexpected error during file persistence: ' . $e->getMessage(), 0, $e);
        }
    }
}