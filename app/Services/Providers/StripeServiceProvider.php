<?php

namespace App\Services\Providers;

use Stripe\StripeClient;

class StripeServiceProvider
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function createCheckoutSession(array $params): ?array
    {
        try {
            $session = $this->stripe->checkout->sessions->create($params);
            return ['url' => $session->url, 'id' => $session->id];
        } catch (\Exception $e) {
            return null;
        }
    }

    public function retrieveSession(string $sessionId): ?object
    {
        try {
            return $this->stripe->checkout->sessions->retrieve($sessionId);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function constructWebhookEvent(string $payload, string $sigHeader): ?object
    {
        try {
            return \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            return null;
        }
    }
}
