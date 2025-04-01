<?php

declare(strict_types=1);

namespace AiOrchestrator\Utils\Storage;

class File
{
    private string $base64Content;
    private string $fileType;

    public function __construct(string $base64Content, string $fileType)
    {
        $this->base64Content = $base64Content;
        $this->fileType = $fileType;
    }

    public function getBase64Content(): string
    {
        return $this->base64Content;
    }

    public function getFileType(): string
    {
        return $this->fileType;
    }
}