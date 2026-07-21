<?php

namespace Database\Seeders;

use App\Actions\Knowledge\StoreKnowledgeDocument;
use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeIndexer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KnowledgeSeeder extends Seeder
{
    /**
     * @return list<array{title: string, destinations: string, content: string}>
     */
    public static function documents(): array
    {
        return [
            [
                'title' => 'Goa monsoon and beach guide',
                'destinations' => 'goa, panaji, margao, calangute',
                'content' => <<<'TEXT'
Goa is best enjoyed with a mix of north and south beaches. Calangute and Baga are lively; Palolem and Agonda are calmer.

Monsoon (June–September) brings lush greenery and fewer crowds, but sea swimming can be unsafe on open beaches. Carry quick-dry clothing, mosquito repellent, and a light rain jacket.

Food: try fish thali, xacuti, and bebinca. Many beach shacks close in peak monsoon—check ahead.

Transport: rent a scooter only if confident in wet roads; app cabs work well between north and south clusters.

Festivals: Carnival (Feb/Mar) and Sao Joao (June) add local colour—book stays early.
TEXT,
            ],
            [
                'title' => 'Shimla and Kalka toy train tips',
                'destinations' => 'shimla, kalka, himachal pradesh',
                'content' => <<<'TEXT'
Shimla sits in the hills with cool evenings even in summer. Layer clothing and pack comfortable walking shoes for Mall Road and Jakhoo slopes.

The Kalka–Shimla toy train is a scenic UNESCO heritage ride—book tickets well in advance in holiday season. Last mile from Kalka to Shimla can also be by bus or taxi.

Altitude: mild shortness of breath is common for some travelers. Stay hydrated and pace sightseeing on day one.

Winter (Dec–Feb) may bring snow—carry thermals and check road closures. Ridge and Mall Road get crowded on weekends.
TEXT,
            ],
            [
                'title' => 'Rajasthan golden triangle pacing',
                'destinations' => 'rajasthan, jaipur, udaipur, jodhpur, delhi, agra',
                'content' => <<<'TEXT'
The golden triangle (Delhi, Agra, Jaipur) works well over 5–7 days with early starts to beat heat and crowds.

Jaipur: Amber Fort, City Palace, and old city bazaars—allow a full day. Udaipur adds lakes and slower pacing; Jodhpur’s Mehrangarh is best near sunset.

Summer (Apr–Jun) is very hot—plan indoor sights midday. Winter is peak season; book heritage hotels early.

Food: dal baati churma, gatte ki sabzi, and lassi. Many forts involve stairs—wear breathable clothing and sun protection.

Inter-city: trains and private cabs are common; night drives on highways are tiring—prefer daylight transfers when possible.
TEXT,
            ],
            [
                'title' => 'Kerala backwaters and monsoon travel',
                'destinations' => 'kerala, kochi, alleppey, munnar, ernakulam',
                'content' => <<<'TEXT'
Kerala combines backwaters, tea hills, and coastal food. Alleppey houseboats are iconic—choose licensed operators and clarify meal inclusions.

Munnar is cooler than the coast; carry a light jacket. Ernakulam/Kochi is the main rail hub for hill transfers.

Monsoon greenery is spectacular but houseboat schedules can shift with heavy rain. Ayurveda treatments are popular—verify clinic credentials.

Food: appam with stew, Kerala fish curry, and banana chips. Respect temple dress codes when visiting historic sites.
TEXT,
            ],
        ];
    }

    public function run(): void
    {
        if (! filled(config('integrations.ai.drivers.gemini.api_key'))) {
            $this->command?->warn('Skipping knowledge indexing: GEMINI_API_KEY is not configured.');

            return;
        }

        $store = app(StoreKnowledgeDocument::class);
        $indexer = app(KnowledgeIndexer::class);

        foreach (self::documents() as $document) {
            $slug = Str::slug($document['title']);

            $existing = KnowledgeDocument::query()->where('slug', $slug)->first();

            if ($existing !== null) {
                if ((int) ($existing->chunk_count ?? 0) === 0) {
                    $indexer->index($existing);
                }

                continue;
            }

            $store([
                ...$document,
                'status' => KnowledgeDocumentStatus::Published->value,
            ]);
        }
    }
}
