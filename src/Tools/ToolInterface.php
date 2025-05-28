<?php

namespace AiOrchestrator\Tools;

interface ToolInterface {
    public function getDefinition(): array;

    /**
     * @return array{role: string, tool_call_id: string, name: string, content: string}
     */
    public function handle(object $call, array $args): array;
}
