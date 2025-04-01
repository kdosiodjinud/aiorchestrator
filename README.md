# 🖼️ AiOrchestrator

**AiOrchestrator** orchestrates the generation and editing of images using **OpenAI GPT-4** and **Image APIs**.  
Tools (functions) are modular and easily extendable via an interface.

---

## 🚀 Features

- **AiOrchestrator**: Orchestrates messages for GPT-4 and processes requests using registered tools.
- **GenerateImageTool**: Generates new images based on prompts.
- **EditImageFromUrlTool**: Edits an image from a URL based on a prompt while preserving style.

## Example Usage

Monolog is used for logging, and the OpenAI client is used to interact with the GPT-4 API.

```php
<?php
require __DIR__ . '/vendor/autoload.php';

$apiKey = ''; // replace with your OpenAI API key (or set it in config.php - git ignore it)
$jwtTokenStorage = '';

use AiOrchestrator\Utils\Storage\StorageStoryCreatorCom;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use AiOrchestrator\AiOrchestrator;
use AiOrchestrator\Tools\EditImageFromUrlTool;
use AiOrchestrator\Tools\GenerateImageTool;
use OpenAI\Factory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
// Logger setup
$logger = new Logger('ai_logger');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// OpenAI client setup
$httpClient = HttpClient::create(['timeout' => 300, 'max_duration' => 300]);
$psr18Client = new Psr18Client($httpClient);
$openAiClient = (new Factory())->withHttpClient($psr18Client)->withApiKey($apiKey)->make();

// Storage setup (StorageInterface implementation)
$httpClientStorage = HttpClient::create([
    'base_uri' => 'https://localhost',
    'verify_peer' => false,
    'verify_host' => false,
]);
$storage = new StorageStoryCreatorCom($httpClientStorage, $jwtTokenStorage);

// Orchestrator setup
$orchestrator = new AiOrchestrator(
    $openAiClient, // OpenAI\Client implementation
    $logger, // LoggerInterface implementation
    [
        new GenerateImageTool($openAiClient, $logger, $storage), // ToolInterface implementation
        new EditImageFromUrlTool($apiKey, $logger, $storage), // ToolInterface implementation
    ]
);

// Process orchestrator request
$response = $orchestrator
    ->addSystemMessage("You are a helpful assistant writing HTML articles (h1, p, img tags only).
     Use tools to generate images (get inspiration how images looks, with respect style). If I sent 
     you an image URL, you should create an image inspired by it, not just copy it. Use the images 
     in the article. Result must be only valid HTML with <h1>, <p> and <img> tags only. The result 
     must not contain any other characters around html.")
    ->addInitChatMessage('Write an story for kids about a travel to town, use image 
    for https://story-creator.com/homepage/homepage-1.png for inspiration for ilustrations. Include 
    exactly 1 image. Add one image at the end with random person (as author), created as photorealistic. Result must be in CZECH language.')
    ->run();

// Log the response
file_put_contents('output.html', $response->message);
$logger->info("Result saved to output.html");
```
