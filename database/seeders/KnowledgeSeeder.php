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
            [
                'title' => 'Ladakh and Leh high-altitude guide',
                'destinations' => 'ladakh, leh, nubra, pangong, kargil',
                'content' => <<<'TEXT'
Ladakh is high desert—days are sunny and nights cold even in summer. Pack layers, lip balm, sunscreen, and a warm jacket.

Altitude acclimatization: rest in Leh for 24–48 hours before Pangong or Nubra. Drink plenty of water; avoid heavy alcohol on arrival. Headaches and breathlessness are common—descend if symptoms worsen.

Inner Line Permit is required for Nubra Valley, Pangong Lake, and several border areas—arrange through a registered agent or your hotel in Leh.

Best season: June–September for road access; winter is harsh with limited connectivity. Khardung La and Chang La can be snow-affected—check conditions.

Respect local Buddhist culture: walk clockwise around stupas, ask before photographing monks, and dress modestly at monasteries (Hemis, Thiksey, Diskit).

Food: thukpa, momos, and butter tea. ATMs exist in Leh but carry cash for remote valleys.
TEXT,
            ],
            [
                'title' => 'Varanasi ghats and spiritual etiquette',
                'destinations' => 'varanasi, banaras, kashi, uttar pradesh',
                'content' => <<<'TEXT'
Varanasi unfolds along the Ganges ghats—Dasaswamedh and Assi are central. Sunrise boat rides offer the calmest views of morning rituals.

Evening Ganga Aarti at Dashashwamedh Ghat draws large crowds—arrive early for a good spot or book a boat view.

Etiquette: cremation ghats (Manikarnika, Harishchandra) are active sacred sites—observe quietly, no flash photography, and follow guide instructions.

Food: try kachori sabzi, malaiyyo (seasonal), and lassi in the old city lanes. Stick to bottled water and busy, freshly cooked stalls.

Navigation: the old city lanes are narrow and easy to get lost in—use offline maps and fixed landmarks. Footwear is removed at many temples.

Best months: October–March for comfortable weather; summers are very hot. Sarnath (Buddhist site) pairs well as a half-day trip from Varanasi.
TEXT,
            ],
            [
                'title' => 'Mumbai local travel and neighborhoods',
                'destinations' => 'mumbai, maharashtra, bombay, colaba, bandra',
                'content' => <<<'TEXT'
Mumbai is spread out—plan by neighborhood rather than trying to cross the city at peak hours. Local trains are fast but extremely crowded at rush hour; avoid 8–10 AM and 6–8 PM if possible.

Colaba and Fort: Gateway of India, Chhatrapati Shivaji Terminus, and museums. Bandra–Worli Sea Link area for cafes and coastal walks.

Food essentials: vada pav, pav bhaji, bhelpuri at Chowpatty/Juhu, and Irani café breakfast. Seafood is excellent in coastal suburbs—confirm freshness.

Monsoon (June–September): carry umbrella and expect local train delays. Marine Drive and promenades are dramatic in rain but surfaces get slick.

Elephanta Caves ferry from Gateway—check last return boat times. Book Bollywood studio tours or Dhobi Ghat visits through reputable operators only.

Cashless payments are widely accepted, but keep small change for local trains and street food.
TEXT,
            ],
            [
                'title' => 'Darjeeling tea hills and toy train',
                'destinations' => 'darjeeling, west bengal, kalimpong, ghoom',
                'content' => <<<'TEXT'
Darjeeling sits at altitude with cool misty mornings—pack warm layers year-round. Tiger Hill sunrise over Kanchenjunga requires a very early start (around 4 AM)—pre-book transport.

Darjeeling Himalayan Railway toy train to Ghoom is a heritage experience—tickets sell out in peak season.

Tea gardens: visit established estates with guided factory tours; buy orthodox tea directly when possible. Avoid unlicensed “garden” shops in town.

Food: momos, thukpa, and local bakery buns. Tibetan and Nepali influences are strong.

Roads from Siliguri/New Jalpaiguri (NJP) are winding—allow 3–4 hours and consider motion sickness tablets. NJP is the main railhead.

Combine with Kalimpong or Sikkim entry (permits required for many Sikkim areas) if you have extra days. Monsoon can trigger landslides—check road status.
TEXT,
            ],
            [
                'title' => 'Hampi boulder ruins and Karnataka heritage',
                'destinations' => 'hampi, karnataka, hospet, badami, bijapur',
                'content' => <<<'TEXT'
Hampi’s Vijayanagara ruins sprawl across a boulder-strenched landscape—hire a local guide or use a mapped circuit over 2–3 days.

Start early to avoid midday heat; hat, sunscreen, and sturdy shoes are essential. Many sites involve uneven stone paths.

Key clusters: Virupaksha Temple, Vittala Temple (stone chariot), Royal Enclosure, and Hemakuta Hill sunset viewpoints. Coracle rides on the Tungabhadra are seasonal.

Base in Hampi village (boulder-side) or Hosapete (Hospet) for better connectivity. Hospet junction has trains from Bangalore and Goa.

Combine with Badami cave temples or Pattadakal (UNESCO) if touring north Karnataka. Limited nightlife—plan relaxed evenings.

Best season: October–February. Summer is harsh. Carry cash; ATMs can be limited in the village.
TEXT,
            ],
            [
                'title' => 'Andaman Islands beaches and permits',
                'destinations' => 'andaman, port blair, havelock, neil island, swaraj dweep',
                'content' => <<<'TEXT'
Port Blair is the gateway—Cellular Jail and light-and-sound show provide historical context. Ferries connect to Havelock (Swaraj Dweep) and Neil (Shaheed Dweep); book tickets ahead in peak season.

Radhanagar Beach on Havelock is the headline beach—snorkelling and diving seasons depend on sea conditions; check operator safety credentials.

Restricted area permits: Indian nationals need permits for some islands; foreign nationals have additional rules—verify current requirements before travel.

Connectivity: mobile data can be patchy outside Port Blair. Carry cash for smaller islands; card acceptance varies.

Best weather: November–April. Monsoon brings rough seas and ferry cancellations. Plastic restrictions are strict—avoid carrying single-use plastics to beaches.

Respect marine life: do not touch coral, feed fish, or leave litter. Bioluminescence tours are weather-dependent—manage expectations.
TEXT,
            ],
            [
                'title' => 'Rishikesh yoga, rafting, and Ganga etiquette',
                'destinations' => 'rishikesh, haridwar, uttarakhand, yoga, rafting',
                'content' => <<<'TEXT'
Rishikesh splits into Tapovan (cafes, yoga schools) and Lakshman Jhula/Ram Jhula areas—pick stay location based on noise tolerance.

White-water rafting runs seasonally (typically until mid-summer depending on water levels)—use licensed operators with life jackets and safety briefings.

Yoga and wellness: research school credentials; drop-in classes differ widely in quality. Silent hours and vegetarian culture are common in ashram areas.

Ganga Aarti at Parmarth or Triveni ghats is moving but crowded—secure belongings. Alcohol and non-vegetarian food are restricted in much of Rishikesh.

Haridwar is 45–60 minutes away for a day trip—distinct pilgrimage atmosphere, especially during Kumbh periods when crowds surge.

Trekking gateways to smaller Himalayan trails exist—do not hike alone without local guidance. Monkeys near jhulas can snatch food and bags.
TEXT,
            ],
            [
                'title' => 'Amritsar Golden Temple and Punjab food',
                'destinations' => 'amritsar, punjab, golden temple, wagah',
                'content' => <<<'TEXT'
Sri Harmandir Sahib (Golden Temple) is open nearly 24 hours—visit at dawn or late evening for calmer reflection. Cover head, wash feet, and no smoking/alcohol on the complex.

Langar (community kitchen) serves free meals—volunteer briefly if you wish; sit on the floor per custom. Shoes are deposited at counters before entry.

Wagah border ceremony (India–Pakistan) is popular—arrive early for seating; expect strict security and no large bags.

Food: Amritsari kulcha, chole, lassi, and pinni sweets. Old city lanes near the temple have legendary dhabas—prioritize busy kitchens.

Partition Museum offers sobering historical context—allow 1–2 hours. Jallianwala Bagh is adjacent to the Golden Temple complex.

Best weather: October–March. Summers are intense. Auto-rickshaws and app cabs are easy; parking near the temple is limited.
TEXT,
            ],
            [
                'title' => 'Meghalaya living root bridges and rain',
                'destinations' => 'meghalaya, shillong, cherrapunji, sohra, mawlynnong',
                'content' => <<<'TEXT'
Meghalaya is one of India’s wettest regions—pack rain gear and quick-dry clothing year-round, especially May–September.

Shillong is the capital hub—Police Bazaar for stays and food. Cherrapunji (Sohra) and Mawlynnong offer root bridges and village walks.

Double-decker living root bridge at Nongriat requires a steep trek (~3,000+ steps down and back)—start early, carry water, and assess fitness honestly.

Roads are scenic but slow; allow full days for transfers. Shared sumos and private cabs are common—fix rates or use reputable drivers.

Food: jadoh, pork with bamboo shoot (try only if you eat pork), and tea from local stalls. Northeast cuisine differs from mainland Indian norms.

Inner Line Permit is not required for Indian citizens for main tourist areas; verify if venturing to restricted zones. Mobile connectivity can be weak in valleys.
TEXT,
            ],
            [
                'title' => 'Tamil Nadu temples and Pondicherry coast',
                'destinations' => 'tamil nadu, chennai, madurai, pondicherry, mahabalipuram, rameswaram',
                'content' => <<<'TEXT'
Tamil Nadu blends towering temples with coastal heritage. Chennai: Kapaleeshwarar, Government Museum, and Marina promenade—humid most of the year.

Madurai Meenakshi Amman Temple is best early morning or evening—dress modestly (shoulders/knees covered), no leather items in some shrines.

Mahabalipuram (Mamallapuram): Shore Temple and rock-cut sculptures—easy day trip from Chennai. Pondicherry offers French-quarter walks and quieter beaches.

Food: idli-dosa-sambhar breakfasts, Chettinad spice if you tolerate heat, filter coffee, and meals on banana leaf. Chennai and Madurai have strong vegetarian traditions.

Rameswaram and Kanyakumari extend the southern circuit—long drives; check rail options from Madurai.

Best season: November–February for lower humidity. Summer is sweltering inland. Temple queues swell on festival days—check lunar calendar.
TEXT,
            ],
            [
                'title' => 'Kolkata culture, food, and colonial lanes',
                'destinations' => 'kolkata, west bengal, calcutta, darjeeling',
                'content' => <<<'TEXT'
Kolkata rewards slow exploration—colonial architecture in BBD Bagh, Victoria Memorial, and Kumartuli idol workshops (especially before Durga Puja).

Trams and metro help avoid traffic; app cabs are reliable. Howrah Bridge and Princep Ghat are best at sunset.

Food is a primary attraction: machher jhol, kosha mangsho (if you eat meat), kathi rolls, puchkas, and mishti doi. Park Street and College Street cafés have literary history.

Durga Puja (Sep/Oct) transforms the city—book hotels months ahead. Summers are humid; carry cotton clothing and electrolytes.

Day trips: Chandernagore French heritage, Sunderbans tiger reserve (multi-day boat tours with licensed operators), or Shantiniketan for Tagore legacy.

Respect queue culture at popular sweet shops—many have separate lines for takeaway vs dine-in.
TEXT,
            ],
            [
                'title' => 'Manali and Rohtang pass mountain trips',
                'destinations' => 'manali, kullu, rohtang, solang, himachal pradesh',
                'content' => <<<'TEXT'
Manali sits in the Kullu Valley with Old Manali (cafés, guesthouses) and Mall Road (busier, commercial). Pick base by noise and budget.

Rohtang Pass and Atal Tunnel access high mountain scenery—permits and weather govern same-day trips; snow activities at Solang Valley are seasonal.

Altitude and cold: even in summer, high passes need jackets. Sunglasses and sunscreen matter at snow points.

Adventure: paragliding and zorbing at Solang—verify operator safety records. River rafting on the Beas runs in safer seasons only.

Hidimba Temple and Vashisht hot springs are easy half-day sights. Kasol–Manikaran and Spiti routes are longer extensions—check road status.

Monsoon landslide risk on Himalayan highways—confirm bus or cab schedules. Winter brings snow in town occasionally; carry grip footwear.
TEXT,
            ],
            [
                'title' => 'Hyderabad biryani and Nizami heritage',
                'destinations' => 'hyderabad, telangana, charminar, secunderabad',
                'content' => <<<'TEXT'
Hyderabad pairs Deccan history with modern tech corridors. Charminar and Old City bazaars (Laad Bazaar for bangles) are best in morning before heat peaks.

Golconda Fort sound-and-light show or sunset visit rewards climbers—fort steps are steep; carry water.

Food: Hyderabadi biryani (Paradise, Shah Ghouse, and local debates abound), haleem in Ramadan season, irani chai and Osmania biscuits.

Ramoji Film City is a full-day outing east of the city—book tickets online. Hussain Sagar lake boating is mild sightseeing.

New city (HITEC City/Gachibowli) has malls and global dining if staying in business districts.

Best months: October–February. April–June is very hot. Metro and app cabs ease Old City parking pain.
TEXT,
            ],
            [
                'title' => 'Sikkim monasteries, permits, and mountain roads',
                'destinations' => 'sikkim, gangtok, pelling, lachung, lachen, nathula',
                'content' => <<<'TEXT'
Sikkim requires Inner Line Permits for many areas—Indian citizens can obtain these in Gangtok or online depending on current rules; foreign nationals need Restricted Area Permits through registered agents.

Gangtok is the main hub with MG Marg pedestrian zone, Rumtek Monastery, and views of Kanchenjunga on clear mornings. Enchey and Do Drul Chorten are easy city sights.

North Sikkim (Lachung, Lachen, Yumthang Valley, Gurudongmar Lake) needs overnight stays and permits—roads are narrow; travel only with registered operators in season (typically March–May and October–December).

Pelling offers Pemayangtse Monastery and Khecheopalri Lake. Shared jeeps and private cabs are the norm; self-drive requires confidence on mountain roads.

Altitude and weather change quickly—pack layers, rain gear, and sunglasses. Plastic is restricted in many parts of the state.

Food: momos, thukpa, gundruk soup, and churpi. Many homestays serve simple local meals—confirm dietary needs ahead.

Respect Buddhist customs: remove shoes at monasteries, walk clockwise around chortens, and ask before photographing rituals.
TEXT,
            ],
            [
                'title' => 'Gujarat heritage, Rann, and wildlife circuit',
                'destinations' => 'gujarat, ahmedabad, kutch, rann of kutch, dwarka, somnath, gir',
                'content' => <<<'TEXT'
Ahmedabad blends old pol houses with Sabarmati Ashram and the Calico Museum—hot and dry most of the year; plan outdoor sights for morning and evening.

Rann of Kutch white desert is best during Rann Utsav (winter)—book tented stays early. Full moon nights are popular for photography; carry warm layers for chilly evenings.

Dwarka and Somnath form a coastal pilgrimage circuit—modest dress at temples, early darshan queues on weekends.

Gir National Park is the last home of Asiatic lions—safari slots are limited and must be booked in advance through official channels. Closed mid-June to mid-October typically.

Food: dhokla, thepla, undhiyu, Gujarati thali (often vegetarian), and street snacks like khandvi. Jain and vegetarian norms are strong in many cities.

Statue of Unity (Kevadia) pairs with Sardar Sarovar viewpoints—allow a full day from Vadodara or Ahmedabad. Roads are good but summer heat is intense March–June.

Best season: October–March for most regions; Kutch and Gir timings vary—verify park and festival dates before booking.
TEXT,
            ],
            [
                'title' => 'Kashmir valleys, houseboats, and seasonal access',
                'destinations' => 'kashmir, srinagar, gulmarg, pahalgam, sonamarg, dal lake',
                'content' => <<<'TEXT'
Srinagar Dal and Nigeen lakes are famous for houseboats—inspect boats in daylight, agree meals and heating, and book through reputable houseboat owners' associations when possible.

Shikara rides at sunrise are quieter; negotiate duration and price before boarding. Old city markets ( spices, papier-mâché) involve narrow lanes—use licensed guides if unfamiliar.

Gulmarg gondola runs seasonally depending on snow and maintenance—check same-day status. Pahalgam and Sonamarg are bases for day hikes and pony rides; dress in layers.

Security and road conditions can change—follow local advisories, carry ID, and register at hotels as required. Mobile data may be limited during restrictions; download offline maps.

Best season: April–June and September–November for most sightseeing; winter for snow in Gulmarg. Ramadan and Eid periods affect restaurant hours.

Food: wazwan feasts (meat-heavy), kahwa tea, and bakery breads. Vegetarian options exist but meat-centric cuisine dominates celebrations.

Permits may be required for some border-adjacent areas—verify Ladakh/Kargil onward travel separately if combining regions.
TEXT,
            ],
            [
                'title' => 'Odisha temples, coast, and Konark heritage',
                'destinations' => 'odisha, bhubaneswar, puri, konark, chilika, cuttack',
                'content' => <<<'TEXT'
Bhubaneswar is the temple city—Lingaraj, Mukteshwar, and old town lanes reward early starts before heat and crowds. Many shrines restrict non-Hindus from inner sanctums—observe signage respectfully.

Puri Jagannath Temple has strict entry rules and heavy festival crowds during Rath Yatra—plan lodging months ahead if visiting during the festival.

Konark Sun Temple (UNESCO) and Chandrabhaga beach pair well as a day trip from Puri or Bhubaneswar—sunrise light on the stone chariot is memorable.

Chilika Lake offers dolphin-watching boat trips (Satapada)—seasonal and weather-dependent; use life jackets and licensed boats only.

Coastal humidity is high year-round; monsoon (June–September) brings heavy rain. Cotton clothing, umbrella, and electrolytes help.

Food: mahaprasad in Puri, dalma, pakhala (fermented rice, seasonal), and fresh seafood on the coast—choose busy kitchens for hygiene.

Bhitarkanika mangroves and Similipal tiger reserve are longer extensions—check permit and season windows. Roads improve steadily but allow buffer time between cities.
TEXT,
            ],
            [
                'title' => 'Assam tea, Kaziranga rhinos, and Brahmaputra culture',
                'destinations' => 'assam, guwahati, kaziranga, tezpur, majuli, dibrugarh',
                'content' => <<<'TEXT'
Guwahati is the gateway—Kamakhya Temple on Nilachal Hill draws pilgrims year-round; expect queues during Ambubachi Mela. Umananda island peacock island ferry is a short Brahmaputra outing.

Kaziranga National Park protects one-horned rhinos—book jeep or elephant safari slots through official park channels; park zones (Kohora, Bagori, Agaratoli) differ in landscape.

Tea estate stays near Dibrugarh or Jorhat offer factory tours during plucking seasons (typically March–November)—book heritage bungalows ahead.

Majuli river island (world's largest river island) shrinks seasonally—ferry timings from Jorhat/Nimati ghat vary with Brahmaputra flow; verify same-day return options.

Monsoon floods affect lowland roads June–September; winter (November–February) is pleasant for wildlife. Carry insect repellent near wetlands.

Food: Assamese thali with fish tenga (sour fish curry), pitha during Bihu festivals, and tea-tasting sessions at estates. Many communities eat pork and duck—confirm if you have dietary restrictions.

Respect tribal and tea-community customs in upper Assam; photography in sensitive border areas may require permits.
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
