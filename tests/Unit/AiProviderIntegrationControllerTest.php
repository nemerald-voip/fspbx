<?php

namespace Tests\Unit;

use App\Contracts\AiProviderClient;
use App\Exceptions\AiProviderException;
use App\Http\Controllers\AiProviderIntegrationController;
use App\Http\Requests\StoreAiProviderIntegrationRequest;
use App\Models\AiProviderIntegration;
use App\Services\AiProviderIntegrationService;
use App\Services\AiProviderRegistry;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Tests\TestCase;

class AiProviderIntegrationControllerTest extends TestCase
{
    public function test_connection_failure_is_returned_as_a_notification_without_persisting_it(): void
    {
        $stored = new AiProviderIntegration([
            'provider' => 'retell',
            'api_key' => 'stored-key',
        ]);
        $stored->syncOriginal();

        $request = $this->createMock(StoreAiProviderIntegrationRequest::class);
        $request->expects($this->once())->method('validated')->willReturn(['api_key' => null]);

        $service = $this->createMock(AiProviderIntegrationService::class);
        $service->method('retell')->willReturn($stored);

        $client = $this->createMock(AiProviderClient::class);
        $client->method('test')->willThrowException(new AiProviderException('Invalid API key.', 401));

        $providers = $this->createMock(AiProviderRegistry::class);
        $providers->method('client')->with('retell')->willReturn($client);

        $exceptionHandler = $this->createMock(ExceptionHandler::class);
        $exceptionHandler->expects($this->once())
            ->method('report')
            ->with($this->isInstanceOf(AiProviderException::class));
        $this->app->instance(ExceptionHandler::class, $exceptionHandler);

        $response = (new AiProviderIntegrationController())->test($request, $service, $providers);
        $payload = $response->getData(true);

        $this->assertSame(502, $response->getStatusCode());
        $this->assertSame(
            'Retell API connection failed: Invalid API key.',
            $payload['messages']['error'][0]
        );
        $this->assertArrayNotHasKey('errors', $payload);
        $this->assertFalse($stored->isDirty());
    }
}
