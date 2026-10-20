<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Plomberie HTTP commune aux clients du microservice IA.
 *
 * Règle d'or : aucune méthode ne lève jamais d'exception et aucune ne bloque
 * au-delà du timeout configuré. Timeout, connexion refusée, statut non-2xx ou
 * JSON invalide → `null`, ce qui déclenche le repli « règles » côté appelant.
 *
 * Chaque requête porte l'en-tête `X-AI-TOKEN` (clé partagée avec le service).
 */
abstract class AbstractAiClient
{
    /**
     * Libellé du module, utilisé dans les logs (ex. « canicule », « coupures »).
     */
    abstract protected function service(): string;

    /**
     * Le service est-il activé et configuré ?
     */
    public function enabled(): bool
    {
        return (bool) config('ai.enabled', true)
            && trim((string) config('ai.service_url', '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function post(string $path, array $payload, ?float $timeout = null): ?array
    {
        return $this->request('post', $path, $payload, $timeout);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function get(string $path): ?array
    {
        return $this->request('get', $path);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function request(string $methode, string $path, array $payload = [], ?float $timeout = null): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $requete = Http::withHeaders($this->headers())
                ->timeout($timeout ?? (float) config('ai.timeout', 2.0))
                ->acceptJson();

            $reponse = $methode === 'get'
                ? $requete->get($this->url($path))
                : $requete->asJson()->post($this->url($path), $payload);
        } catch (\Throwable $e) {
            $this->journaliser('appel impossible', $path, ['message' => $e->getMessage()]);

            return null;
        }

        return $this->decoder($reponse, $path);
    }

    /**
     * Décode une réponse HTTP en tableau, ou `null` si inexploitable.
     *
     * @return array<string, mixed>|null
     */
    private function decoder(Response $reponse, string $path): ?array
    {
        if (! $reponse->successful()) {
            $this->journaliser('statut inattendu', $path, ['status' => $reponse->status()]);

            return null;
        }

        $json = $reponse->json();

        if (! is_array($json)) {
            $this->journaliser('réponse JSON invalide', $path);

            return null;
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $contexte
     */
    private function journaliser(string $message, string $path, array $contexte = []): void
    {
        Log::warning("Service IA {$this->service()} : {$message}", ['path' => $path] + $contexte);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-AI-TOKEN' => (string) config('ai.token', '')];
    }

    private function url(string $path): string
    {
        return rtrim((string) config('ai.service_url', ''), '/').'/'.ltrim($path, '/');
    }
}
