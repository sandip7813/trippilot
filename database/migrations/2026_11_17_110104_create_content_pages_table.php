<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 120);
            $table->string('description', 300)->nullable();
            $table->longText('body');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_published')->default(true);
            $table->boolean('show_in_footer')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        DB::table('content_pages')->insert(array_map(fn (array $page): array => [
            ...$page,
            'is_system' => true,
            'is_published' => true,
            'show_in_footer' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            [
                'slug' => 'terms',
                'title' => 'Terms & Conditions',
                'description' => 'The rules for using TripPilot, buying credits and using AI features.',
                'sort_order' => 1,
                'body' => $this->termsBody(),
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'description' => 'What data TripPilot collects, how it is used and the choices you have.',
                'sort_order' => 2,
                'body' => $this->privacyBody(),
            ],
            [
                'slug' => 'refund-policy',
                'title' => 'Refund & Cancellation Policy',
                'description' => 'When credit purchases can be refunded and how to ask for a refund.',
                'sort_order' => 3,
                'body' => $this->refundBody(),
            ],
        ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }

    private function termsBody(): string
    {
        return <<<'MARKDOWN'
## 1. About TripPilot

TripPilot is an online trip-planning service operated by {business_name} ("we", "us"). It helps you plan trips and road trips, organise group tours, and get AI-assisted itineraries and travel suggestions. By creating an account or using TripPilot, you agree to these Terms & Conditions.

TripPilot is a planning tool. We are not a travel agent and we do not book, sell or guarantee flights, trains, hotels, tours or any other travel service. Any booking you make with a third party is between you and that provider.

## 2. Your account

- You must be at least 18 years old, or use TripPilot with the permission and supervision of a parent or guardian.
- The information you give us (name, email address, mobile number) must be accurate, and you must keep your password safe.
- You are responsible for everything done through your account. Tell us straight away if you think someone else has accessed it.

## 3. AI-generated content

Itineraries, chat answers, break suggestions and other AI-generated content are produced automatically and may be incomplete, out of date or wrong. Always check important details such as opening hours, prices, travel times, visa rules, weather and safety advice with official sources before you travel. You use AI suggestions at your own risk.

## 4. Credits and payments

- Some AI features use credits. The number of credits each feature uses is shown on the [Pricing](/pricing) page and next to each AI button before you use it.
- Credits are bought in one-time packs. Prices are in Indian Rupees (INR) and there are no subscriptions or automatic renewals.
- Payments are processed securely by Razorpay. We never see or store your full card, UPI or bank details.
- Credits are added to your account as soon as the payment succeeds. Credits do not expire, have no cash value, cannot be transferred to another account and cannot be exchanged for money.
- If an AI request fails, the credits it used are returned to your balance automatically.
- We may change credit prices or the number of credits a feature uses. Changes never affect credits you have already bought.
- We may give free credits (for example a welcome bonus) at our discretion. Free credits work like bought credits but cannot be refunded.

## 5. Refunds and cancellations

Credit purchases are final once the credits are added to your account. Refunds are only given for failed, duplicate or wrongly charged payments, as explained in our [Refund & Cancellation Policy](/refund-policy).

## 6. Acceptable use

You agree not to:

- post content that is unlawful, misleading, abusive, hateful, sexually explicit or that infringes anyone else’s rights;
- use group tours, trip sharing or messages to spam, defraud or harass other users;
- try to break, overload, scrape or reverse-engineer the service, or get around credit charges or usage limits;
- create accounts in someone else’s name or use automated tools to create accounts.

## 7. Your content

You keep ownership of the trips, notes, photos and messages you add. You allow us to store, display and process them to run the service, including sending trip details and messages to our AI provider when you use AI features. If you make a group tour public or share a trip, the people you share it with can see it.

## 8. Third-party services

TripPilot uses third-party services such as Google Gemini (AI), Geoapify (maps and routes), weather providers, Google reCAPTCHA and Razorpay (payments). Their own terms and privacy policies also apply, and we are not responsible for their availability or accuracy.

## 9. Suspension and account closure

You can delete your account at any time from Settings. We may suspend or close accounts, and remove content, that break these terms. Unused credits are lost when an account is deleted or closed for breaking these terms, and are not refunded.

## 10. Limitation of liability

TripPilot is provided "as is". To the extent allowed by law, we are not liable for indirect or consequential losses, for travel plans that do not work out, or for losses caused by third-party services. Our total liability to you for any claim is limited to the amount you paid us in the 6 months before the claim.

## 11. Changes to these terms

We may update these terms from time to time. The date at the top of this page shows when they last changed. If you keep using TripPilot after a change, you accept the updated terms.

## 12. Governing law

These terms are governed by the laws of India. Any dispute will be handled by the courts of {jurisdiction}.
MARKDOWN;
    }

    private function privacyBody(): string
    {
        return <<<'MARKDOWN'
## 1. Who we are

TripPilot is operated by {business_name}. This policy explains what personal data we collect when you use TripPilot, why we collect it, and the choices you have.

## 2. Data we collect

- **Account details:** your name, email address, mobile number, password (stored encrypted) and profile picture.
- **Trip data:** trips, road trips, group tours, itineraries, notes, expenses, members and the places you search for.
- **Messages:** what you type into the trip chat, the travel assistant and the contact form.
- **Payment details:** the pack you bought, the amount, the date, and the order and payment IDs from Razorpay. Card, UPI and bank details are collected by Razorpay, not by us.
- **Credit activity:** credits added, used and refunded.
- **Technical data:** IP address, browser type, log entries and cookies needed to keep you signed in.

## 3. How we use your data

- to run your account and the features you use;
- to generate itineraries, chat answers and suggestions when you use AI features;
- to process payments, add credits and handle refunds;
- to send you important emails such as password, trip reminder, payment receipt and credit notifications;
- to prevent fraud, spam and abuse, and to keep the service secure;
- to answer your messages and improve TripPilot.

## 4. Services that process your data

We share data only with the providers we need to run TripPilot, and only what they need:

- **Google Gemini:** trip details and messages you send to AI features, to generate answers.
- **Razorpay:** payment information, to process your purchase.
- **Geoapify and weather providers:** places and coordinates, to show maps, routes and forecasts.
- **Google reCAPTCHA:** technical data during sign-up, to block bots.
- **Our email and hosting providers:** to deliver emails and store the service’s data.

## 5. What we never do

We do not sell your personal data, and we do not share it with advertisers.

## 6. Cookies

We use essential cookies to keep you signed in and protect forms, and small preference cookies to remember your theme and sidebar layout. We do not use advertising cookies.

## 7. How long we keep data

We keep your data while your account is open. When you delete your account, we delete your profile and trips. We keep payment and credit records for as long as Indian tax and accounting laws require.

## 8. Your choices and rights

- View and correct your profile details in Settings.
- Delete your account at any time from Settings.
- Ask us for a copy of your data, or ask questions about it, by writing to {support_email}.

## 9. Security

Passwords are stored encrypted, API keys and secrets are encrypted in our database, and all connections use HTTPS. No online service is completely secure, so please use a strong, unique password.

## 10. Children

TripPilot is not meant for children under 18 without a parent or guardian’s supervision, and we do not knowingly collect data from children.

## 11. Changes to this policy

If we change this policy, we will update the date at the top of this page. For important changes, we will also let you know by email or in the app.
MARKDOWN;
    }

    private function refundBody(): string
    {
        return <<<'MARKDOWN'
## 1. What you buy

TripPilot sells credits in one-time packs. Credits are a digital product: they are added to your account as soon as your payment succeeds, they never expire, and they can only be used for AI features inside TripPilot.

## 2. All purchases are final

Because credits are delivered instantly, credit purchases cannot be cancelled or refunded once the credits have been added to your account, whether or not you have used them. Free credits, such as the welcome bonus or credits added by our team, are never refundable.

## 3. When we do refund

We refund the full amount of a payment in these cases:

- **Failed payment:** money was taken from your account but the payment failed or the credits were never added.
- **Duplicate payment:** you were charged more than once for the same order.
- **Wrong amount:** you were charged a different amount from the pack price because of a technical error.

## 4. How to ask for a refund

Contact us within 7 days of the payment at {support_email} or through the [contact page](/contact). Please include your order number (shown as TP-… in your payment receipt email), the Razorpay payment ID, the date and the amount.

We reply within 2 working days. If the refund is approved, it is sent back to the original payment method (UPI, card, netbanking or wallet) and usually reaches you within 5–7 working days, depending on your bank.

Any credits that were added for a refunded payment are removed from your balance.

## 5. Failed AI requests

If an AI feature fails after using credits, the credits are returned to your balance automatically. You do not need to ask for this, and it is not a money refund.

## 6. Cancellation

You can cancel a purchase at any time before you complete the payment by closing the payment window; you will not be charged. TripPilot has no subscriptions or automatic renewals, so there is nothing to cancel after a purchase.

Deleting your account does not entitle you to a refund for unused credits.

## 7. Payments abroad

Prices are in Indian Rupees (INR). Your bank may charge currency conversion or other fees, which we cannot refund. {business_name} is not responsible for charges added by your bank or payment provider.
MARKDOWN;
    }
};
