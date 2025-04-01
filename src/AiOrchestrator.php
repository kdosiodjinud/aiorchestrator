<?php

namespace AiOrchestrator;

use AiOrchestrator\Tools\ToolInterface;
use OpenAI\Client;
use Psr\Log\LoggerInterface;
use AiOrchestrator\Exceptions\AiOrchestratorToolException;
use AiOrchestrator\Exceptions\AiOrchestratorException;

class AiOrchestrator {

    private Client $client;
    private LoggerInterface $logger;
    private array $messages = [];
    private array $attachments = [];
    private array $tools;

    public function __construct(Client $client, LoggerInterface $logger, array $tools) {
        $this->client = $client;
        $this->logger = $logger;
        $this->tools = $tools;
    }

    public function addSystemMessage(string $content): self {
        $this->messages[] = ['role' => 'system', 'content' => $content];
        return $this;
    }

    public function addInitChatMessage(string $content): self {
        $this->messages[] = ['role' => 'user', 'content' => $content];
        return $this;
    }

    public function run(): AiOrchestratorResponse {
        if (empty($this->messages)) {
            $this->logger->error("No initial messages provided to the orchestrator.");
            throw new AiOrchestratorException("No initial messages set.");
        }

        $this->logger->info("Starting orchestrator...");

        try {
            $finalText = $this->handleToolCalls($this->messages);
        } catch (\Throwable $e) {
            $this->logger->error("Orchestrator encountered an error: " . $e->getMessage());
            throw new AiOrchestratorException("Orchestrator failed: " . $e->getMessage(), 0, $e);
        }

        $response = new AiOrchestratorResponse();
        $response->message = $finalText;
        $response->attachments = $this->attachments;
        return $response;
    }

    private function handleToolCalls(array $messages): string {
        $this->logger->info("Sending messages to GPT-4 Turbo...");
        $this->logger->debug("Messages: " . json_encode($messages, JSON_THROW_ON_ERROR));

        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-4-turbo',
                'messages' => $messages,
                'tools' => array_map(fn($tool) => $tool->getDefinition(), $this->tools),
                'tool_choice' => 'auto'
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to communicate with GPT-4 Turbo: " . $e->getMessage());
            throw new AiOrchestratorException("OpenAI API communication failed: " . $e->getMessage(), 0, $e);
        }

        $this->logger->info("Received response from GPT-4 Turbo.");
        $message = $response->choices[0]->message ?? null;

        if (!$message) {
            $this->logger->error("No message returned from GPT-4 Turbo.");
            throw new AiOrchestratorException("No message returned from GPT-4 Turbo.");
        }

        if (!empty($message->toolCalls)) {
            $this->logger->info("GPT wants to call tools...");
            $toolMessages = [];

            foreach ($message->toolCalls as $call) {
                $toolName = $call->function->name;
                $this->logger->info("- Tool: {$toolName}");

                try {
                    $args = json_decode($call->function->arguments, true, 512, JSON_THROW_ON_ERROR);
                    $this->logger->debug("  Args: " . json_encode($args, JSON_THROW_ON_ERROR));
                } catch (\JsonException $e) {
                    $this->logger->error("Failed to parse arguments for tool '{$toolName}': " . $e->getMessage());
                    throw new AiOrchestratorToolException("Invalid JSON arguments for tool '{$toolName}'", 0, $e);
                }

                $tool = $this->findToolByName($toolName);
                if (!$tool) {
                    $this->logger->warning("Unknown tool: {$toolName}");
                    continue;
                }

                try {
                    $toolMessages[] = $tool->handle($call, $args);
                } catch (AiOrchestratorToolException $e) {
                    $this->logger->error("Tool '{$toolName}' failed: " . $e->getMessage());
                    throw $e;
                } catch (\Throwable $e) {
                    $this->logger->error("Unexpected error in tool '{$toolName}': " . $e->getMessage());
                    throw new AiOrchestratorToolException("Unexpected error in tool '{$toolName}'", 0, $e);
                }
            }

            $newMessages = array_merge(
                $messages,
                [['role' => $message->role, 'content' => $message->content ?? '', 'tool_calls' => $message->toolCalls]],
                $toolMessages
            );
            return $this->handleToolCalls($newMessages);
        }

        return $message->content ?? '';
    }

    private function findToolByName(string $name): ?ToolInterface {
        foreach ($this->tools as $tool) {
            if ($tool->getDefinition()['function']['name'] === $name) {
                return $tool;
            }
        }
        return null;
    }
}
