<?php

namespace AiOrchestrator\Utils\Storage;

interface StorageInterface
{
    /**
     * Return URL to the file
     */
    public function persistFile(File $file): string;
}
