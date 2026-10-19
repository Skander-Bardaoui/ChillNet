<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client dédié au classifieur de catégories du module signalements.
 */
class SignalementAiClient
{
    /**
     * @return array<string, mixed>|null
     */
    public function classify(string $description): ?array
    {
        if (! (bool) config('ai.enabled', true)
            || trim((string) config('ai.service_url', '')) === '') {
            return null;
        }

        $path = '/signalements/classify';
        try {
            $response = Http::withHeaders([
                'X-AI-TOKEN' => (string) config('ai.token', ''),
            ])
                ->timeout((float) config('ai.timeout', 2.0))
                ->acceptJson()
                ->asJson()
                ->post(
                    rtrim((string) config('ai.service_url', ''), '/').$path,
                    ['description' => $description],
                );
        } catch (\Throwable $exception) {
            Log::warning('Service IA signalements : appel impossible', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Service IA signalements : statut inattendu', [
                'path' => $path,
                'status' => $response->status(),
            ]);

            return null;
        }

        $result = $response->json();
        if (! is_array($result)) {
            Log::warning('Service IA signalements : réponse JSON invalide', ['path' => $path]);

            return null;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function retrain(): ?array
    {
        if (! (bool) config('ai.enabled', true)
            || trim((string) config('ai.service_url', '')) === '') {
            return null;
        }

        $path = '/retrain-signalements';
        try {
            $response = Http::withHeaders([
                'X-AI-TOKEN' => (string) config('ai.token', ''),
            ])
                ->timeout((float) env('AI_SERVICE_RETRAIN_TIMEOUT', 300.0))
                ->acceptJson()
                ->asJson()
                ->post(rtrim((string) config('ai.service_url', ''), '/').$path, []);
        } catch (\Throwable $exception) {
            Log::warning('Service IA signalements : demande de ré-entraînement impossible', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Service IA signalements : statut inattendu', [
                'path' => $path,
                'status' => $response->status(),
            ]);

            return null;
        }

        $result = $response->json();
        if (! is_array($result)) {
            Log::warning('Service IA signalements : réponse JSON invalide', ['path' => $path]);

            return null;
        }

        return $result;
    }
}
