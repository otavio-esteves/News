<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class CartaCapitalSourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::firstOrCreate(['slug' => 'cartacapital'], [
            'name' => 'CartaCapital',
            'homepage_url' => 'https://www.cartacapital.com.br/',
            'feed_url' => 'https://www.cartacapital.com.br/feed/',
            'enabled' => false,
            'fetch_interval_minutes' => 60,
            'config' => [
                'access' => 'public_rss_headlines_only_pending_permission',
                'attribution' => 'CartaCapital',
                'policy_url' => 'https://www.cartacapital.com.br/politica-de-privacidade/',
                'robots_url' => 'https://www.cartacapital.com.br/robots.txt',
                'verified_at' => '2026-09-29',
            ],
        ]);
    }
}
