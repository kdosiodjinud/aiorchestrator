<?php

namespace AiOrchestrator\Tools;

interface ToolInterface {
    public function getDefinition(): array;
    public function handle($call, $args): array;
}
