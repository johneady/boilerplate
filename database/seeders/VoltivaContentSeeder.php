<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Page;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * Voltiva's content pages (the "Why Voltiva" and "About" pages) and the
 * example News & Advice articles from the brief.
 *
 * PLACEHOLDER COPY. The brief has Voltiva supplying the final wording and
 * SEO text; this is written to show the templates working with realistic
 * content, and is replaced in the admin panel. Created by slug and never
 * overwritten, so an editor's changes survive a redeploy.
 *
 * Runs before PagesSeeder, so Voltiva's About page is the one created.
 */
class VoltivaContentSeeder extends Seeder
{
    /**
     * @var list<array{slug: string, title: string, sort_order: int, seo_description: string, image_path: string|null, body: string}>
     */
    private const array PAGES = [
        [
            'slug' => 'batteries-and-range',
            'title' => 'Batteries & Range',
            'sort_order' => 100,
            'image_path' => 'site/batteries.webp',
            'seo_description' => 'How far our electric cars go on a charge, how long the batteries last, and why we use LiFePO4.',
            'body' => <<<'MARKDOWN'
            Most people in Mallorca drive less than 40 km a day. Every car in our range goes well beyond that on a single charge – so for most owners, range is simply not something they think about.

            ## How far will it go?

            Our cars travel between **75 and 177 km** on a charge, depending on the model. That is enough for:

            - a week of school runs and shopping trips around town, or
            - a day trip from Palma to the north of the island and back.

            Range depends on how you drive. Hills, air conditioning and higher speeds use more energy; gentle town driving uses less. The figures we quote are for mixed island driving, not a laboratory.

            ## What the battery numbers mean

            You will see batteries described like this: **72V 150Ah LiFePO4 – 10.8 kWh**.

            - **kWh** is the amount of energy the battery stores. It is the best single number for comparing range.
            - **Ah** (amp-hours) is the battery's capacity at its voltage. At the same voltage, more Ah means more range.
            - **LiFePO4** is the battery chemistry – lithium iron phosphate.

            ## Why LiFePO4?

            Lithium iron phosphate is one of the safest and longest-lasting lithium chemistries. It copes well with heat – important in a Mallorcan summer – can be charged thousands of times, and needs no maintenance at all.

            ## How long does a battery last?

            A LiFePO4 battery typically keeps most of its capacity for thousands of charges. Charged a couple of times a week, that is many years of driving. Every battery is covered by a warranty of 5 to 8 years, depending on the model.
            MARKDOWN,
        ],
        [
            'slug' => 'charging',
            'title' => 'Charging',
            'sort_order' => 110,
            'image_path' => 'site/charging.webp',
            'seo_description' => 'Charge your Voltiva electric car at home from a normal 230V socket. No wallbox, no installation.',
            'body' => <<<'MARKDOWN'
            Charging a small electric car is as simple as charging a phone. Park, plug in, and it is full by morning.

            ## Charge at home from a normal socket

            Every Voltiva car comes with a portable charging cable that plugs into a standard 230V household socket – no wallbox, no electrician, no installation. From empty to full takes between four and eight hours, depending on the model, so an overnight charge always does it.

            ## What does it cost?

            Our batteries hold between 5.8 and 14.4 kWh. At a typical home electricity price, a full charge costs **between about €1 and €3** – for 75 to 177 km of driving. With a night-time tariff it can be even less.

            ## Charging away from home

            Most owners never need a public charger. If you do, our L7e cars can use public Type 2 chargers with an adapter, which is available as an accessory.

            ## Good charging habits

            - Plug in whenever it is convenient – LiFePO4 batteries do not mind being topped up.
            - Use a socket on its own circuit if possible, and never an extension reel.
            - In summer, park in the shade when you can. The car and the battery will thank you.

            If you are not sure your parking space has a suitable socket, ask us – we can check before you buy.
            MARKDOWN,
        ],
        [
            'slug' => 'registration',
            'title' => 'Registration',
            'sort_order' => 120,
            'image_path' => 'site/registration.webp',
            'seo_description' => 'We register your electric car with the DGT, supply the Certificate of Conformity and fit the plates.',
            'body' => <<<'MARKDOWN'
            Registering a car in Spain involves paperwork most people would rather avoid. We handle all of it, so your car arrives with its plates on and is ready to drive.

            ## What we do for you

            1. **Certificate of Conformity (CoC).** Every car comes with its CoC, the manufacturer's confirmation that it matches its EU-approved design.
            2. **Registration with the DGT.** We register the car and obtain its registration certificate and technical sheet.
            3. **Number plates.** We fit the plates before handover.
            4. **Environmental label.** Your car qualifies for the DGT “0 emissions” label, which we help you obtain.

            ## What you need

            - Your ID (DNI, NIE or passport).
            - Proof of address in Spain.
            - The right driving licence: an **AM licence** (from 15) for L6e cars, or a **B1** (from 16) or **B** licence for L7e cars.
            - Insurance, which we can help you arrange.

            ## Road tax and inspections

            Electric cars pay reduced road tax in many Mallorcan councils. Like every vehicle, your car will need periodic ITV inspections – we will remind you when one is due.
            MARKDOWN,
        ],
        [
            'slug' => 'servicing-and-support',
            'title' => 'Servicing & Support',
            'sort_order' => 130,
            'image_path' => 'site/servicing.webp',
            'seo_description' => 'Simple, affordable servicing for your electric car in Mallorca, with collection from your home.',
            'body' => <<<'MARKDOWN'
            Electric cars need far less maintenance than petrol or diesel ones: no oil changes, no clutch, no exhaust, no timing belt. What they do need, we look after locally.

            ## Servicing

            We recommend a check once a year. We inspect the brakes, tyres, suspension, lights, battery health and software, and we can collect the car from your home anywhere in Mallorca and bring it back the same day.

            ## Warranty

            - **2 to 3 years** on the car, depending on the model.
            - **5 to 8 years** on the battery.

            ## Help when you need it

            Call or message us and you will speak to the same small team that sold you the car. If you have a breakdown, our roadside assistance partner covers the whole island.

            ## Parts

            We keep common parts – tyres, brake pads, bulbs, wipers – in stock in Palma, so most repairs are done in a day.
            MARKDOWN,
        ],
        [
            'slug' => 'finance',
            'title' => 'Finance',
            'sort_order' => 140,
            'image_path' => 'site/finance.webp',
            'seo_description' => 'Spread the cost of your electric car over 24 to 60 months, with a no-obligation quote.',
            'body' => <<<'MARKDOWN'
            You can buy any car in our range outright, or spread the cost with monthly payments.

            ## How it works

            - Choose a **deposit** – from nothing to half the price of the car.
            - Choose a **term** of 24, 36, 48 or 60 months.
            - We prepare a clear written quote, with the monthly payment, the interest rate and the total amount payable.

            Every car page has a finance estimate you can play with to see roughly what your monthly payment would be.

            ## Running costs

            When you compare an electric car with a petrol one, remember the running costs. A full charge costs between about €1 and €3, servicing is simpler, and many councils charge electric cars less road tax.

            ## Getting a quote

            Tick “I would like a finance quote” on any enquiry form, or ask us when we call. There is no obligation.

            *Finance is subject to status. The figures on this website are illustrations, not offers of credit.*
            MARKDOWN,
        ],
        [
            'slug' => 'accessories',
            'title' => 'Accessories',
            'sort_order' => 150,
            'image_path' => 'vehicles/brisa-rear.webp',
            'seo_description' => 'Chargers, covers, mats and more for your Voltiva electric car.',
            'body' => <<<'MARKDOWN'
            A small selection of well-made accessories, chosen because our customers actually use them. Ask us for prices – we fit everything before handover at no extra cost.

            ## Charging

            - **Longer charging cable** – 10 metres, for parking spaces a little further from the socket.
            - **Type 2 adapter** – for L7e cars, to use public chargers.

            ## Protection

            - **Weatherproof car cover** – keeps off sun, salt air and dust when the car is parked outside.
            - **All-weather floor mats** – for sandy feet after the beach.

            ## Carrying

            - **Roof bars and bike carrier** – for selected models.
            - **Boot organiser** – keeps the shopping upright.

            ## Comfort

            - **Sunshade set** – for the windscreen and side windows.
            - **Phone holder** – mounted where you can see the maps.
            MARKDOWN,
        ],
        [
            'slug' => 'about',
            'title' => 'About Voltiva',
            'sort_order' => 160,
            'image_path' => 'site/why-voltiva.webp',
            'seo_description' => 'Voltiva Mobility sells compact electric cars in Mallorca, with registration, servicing and finance handled locally.',
            'body' => <<<'MARKDOWN'
            Voltiva Mobility sells compact electric cars in Mallorca – and only in Mallorca. We believe small electric cars are the most sensible way to get around the island, and we want owning one to be simple.

            ## What makes us different

            - **We are local.** We know the roads, the hills and the parking, because we drive them every day.
            - **We explain things plainly.** Benefits first, jargon explained. If you have a question, we will answer it straight.
            - **We handle the paperwork.** Registration, plates and finance are all done for you.
            - **We look after you afterwards.** Servicing on the island, with collection from your home.

            ## Our cars

            We offer two kinds of car. **L6e** light quadricycles can be driven from age 15 and are perfect for town. **L7e** cars are faster and can use the main roads between towns. Every one charges from a normal socket at home.

            ## Visit us

            Come and see the cars in Palma, or ask us to bring one to you for a test drive anywhere on the island.
            MARKDOWN,
        ],
        [
            'slug' => 'faq',
            'title' => 'Frequently asked questions',
            'sort_order' => 170,
            'image_path' => null,
            'seo_description' => 'Answers to common questions about electric cars in Mallorca: licences, charging, range, registration and finance.',
            'body' => <<<'MARKDOWN'
            ### Which driving licence do I need?

            For an **L6e** car, an AM licence, which you can hold from age 15. For an **L7e** car, a B1 licence (from 16) or a normal B car licence.

            ### How far can I drive on a charge?

            Between 75 and 177 km, depending on the model and how you drive. Most people in Mallorca drive less than 40 km a day.

            ### How do I charge it?

            From a normal 230V household socket, using the cable supplied with the car. No wallbox or installation is needed.

            ### How much does a charge cost?

            Between about €1 and €3 for a full battery, at a typical home electricity price.

            ### Can I drive on the motorway?

            L6e cars are limited to 45 km/h and are made for town and local roads. L7e cars reach 80 to 90 km/h and can use main roads between towns, but are not intended for motorways.

            ### Do you handle registration?

            Yes. We supply the Certificate of Conformity, register the car with the DGT and fit the plates.

            ### Can I spread the cost?

            Yes – we offer finance over 24 to 60 months. Every car page has an estimate you can adjust.

            ### Where do you service the cars?

            In Mallorca. We can collect your car from home and return it the same day.
            MARKDOWN,
        ],
    ];

    /**
     * @var list<array{slug: string, title: string, topic: string, vehicle: string|null, image_path: string, days_ago: int, excerpt: string, body: string}>
     */
    private const array ARTICLES = [
        [
            'slug' => 'how-long-does-an-electric-car-battery-last',
            'title' => 'How long does an electric car battery last?',
            'topic' => 'batteries',
            'vehicle' => null,
            'image_path' => 'site/tramuntana.webp',
            'days_ago' => 3,
            'excerpt' => 'Longer than most people think. Here is what affects battery life, and how the warranty protects you.',
            'body' => <<<'MARKDOWN'
            It is the question we are asked most often – and the answer is reassuring. A modern electric car battery is designed to last for many years, and the type we use is one of the longest-lasting of all.

            ## Batteries are measured in charges, not years

            Battery life is described in **charge cycles** – one full charge from empty to full. The LiFePO4 (lithium iron phosphate) batteries in our cars are typically rated for thousands of cycles before their capacity falls noticeably.

            If you charge twice a week, that is a very long time.

            ## What happens as a battery ages?

            It does not suddenly stop working. It slowly holds a little less energy, so your range gradually reduces. A car that did 150 km when new might do a little less after many years – still more than most people drive in a day.

            ## What affects battery life?

            - **Heat.** Very high temperatures age batteries faster. LiFePO4 copes with heat better than most chemistries, but parking in the shade still helps.
            - **Sitting full or empty for weeks.** If you are away for a long time, leave the battery around half full.
            - **How you charge.** Slow charging at home, which is how most of our owners charge, is the gentlest way.

            ## The warranty

            Every Voltiva battery has its own warranty of 5 to 8 years, depending on the model – separate from, and longer than, the warranty on the rest of the car.

            **In short:** for everyday driving in Mallorca, the battery will outlast most people's reasons for owning the car.
            MARKDOWN,
        ],
        [
            'slug' => 'what-is-an-l6e-electric-car',
            'title' => 'What is an L6e electric car?',
            'topic' => 'cars',
            'vehicle' => 'voltiva-nova',
            'image_path' => 'vehicles/nova.webp',
            'days_ago' => 8,
            'excerpt' => 'A small, light electric car you can drive from age 15. Here is what the L6e class means in practice.',
            'body' => <<<'MARKDOWN'
            L6e is the European category for **light quadricycles** – small four-wheeled vehicles that sit between a scooter and a car.

            ## The rules

            An L6e vehicle is limited to a top speed of **45 km/h**, has a low weight limit and a small motor. In Spain, you can drive one from **age 15** with an **AM licence** – the same licence as for a moped.

            ## Who are they for?

            - **Young drivers** who want something safer and drier than a scooter.
            - **Town drivers** who never go faster than 45 km/h anyway.
            - **Second-car households** looking for a cheap runabout for short trips.

            ## What are they like to drive?

            Easy. They are automatic, quiet and very small, so parking is simple. In a town like Palma, where traffic rarely moves faster than 45 km/h, you will not feel held back.

            ## What they are not for

            L6e cars cannot use motorways or fast main roads. If you need to travel between towns regularly, an **L7e** car is the better choice.

            In our range, the **Voltiva Nova** and **Voltiva Brisa** are L6e cars.
            MARKDOWN,
        ],
        [
            'slug' => 'what-is-an-l7e-electric-car',
            'title' => 'What is an L7e electric car?',
            'topic' => 'cars',
            'vehicle' => 'voltiva-terra',
            'image_path' => 'vehicles/terra.webp',
            'days_ago' => 12,
            'excerpt' => 'Faster and more capable than an L6e, but still compact and simple. Here is what the L7e class means.',
            'body' => <<<'MARKDOWN'
            L7e is the European category for **heavy quadricycles**: compact four-wheeled vehicles that are faster and more capable than the L6e class, but lighter and simpler than a normal car.

            ## The rules

            L7e passenger cars can reach up to **90 km/h** and have more powerful motors than L6e cars. In Spain, you need a **B1 licence** (available from age 16) or a normal **B car licence**.

            ## Who are they for?

            - Drivers who travel **between towns** as well as within them.
            - Anyone who wants **air conditioning and more comfort** for longer trips.
            - Households replacing a second car completely.

            ## What are they like to drive?

            They keep up comfortably with traffic on main roads around the island, while staying small enough to park easily. Most have electric power steering, so they feel light at low speed.

            ## What they are not for

            L7e cars are not designed for motorways. For everything else in Mallorca – towns, villages, coast roads and the main routes between them – they are ideal.

            In our range, the **Voltiva Terra** and **Voltiva Isla** are L7e cars.
            MARKDOWN,
        ],
        [
            'slug' => 'charging-an-electric-car-at-home',
            'title' => 'Charging an electric car at home',
            'topic' => 'charging',
            'vehicle' => null,
            'image_path' => 'site/charging.webp',
            'days_ago' => 17,
            'excerpt' => 'No wallbox, no installation: how home charging works with a small electric car, and what it costs.',
            'body' => <<<'MARKDOWN'
            One of the best things about a small electric car is that you rarely need to think about charging at all. You simply plug it in at home.

            ## What you need

            A normal **230V household socket** near where you park. That is it. Every Voltiva car comes with a portable charging cable – one end plugs into the socket, the other into the car.

            ## How long does it take?

            Between about four and eight hours from empty to full, depending on the model. Because you rarely run the battery flat, most top-ups are much quicker. Plug in in the evening and it is ready in the morning.

            ## How much does it cost?

            Our batteries hold between 5.8 and 14.4 kWh. Multiply that by your electricity price and you have the cost of a full charge – typically **between €1 and €3**. If your tariff is cheaper at night, charge then.

            ## Is it safe?

            Yes, provided the socket and wiring are in good condition. We recommend:

            - using a socket on its own circuit where possible,
            - never using an extension reel,
            - asking an electrician to check older installations.

            ## What if I cannot charge at home?

            Talk to us. Some customers charge at work; others use public chargers with an adapter. We will help you work out what suits you.
            MARKDOWN,
        ],
        [
            'slug' => 'electric-car-registration-in-spain',
            'title' => 'Electric car registration in Spain',
            'topic' => 'registration',
            'vehicle' => null,
            'image_path' => 'site/registration.webp',
            'days_ago' => 23,
            'excerpt' => 'CoC, VIN, DGT, plates: what registering a small electric car involves – and what we do for you.',
            'body' => <<<'MARKDOWN'
            Every new car must be registered with the DGT (Dirección General de Tráfico) before it can be driven on Spanish roads. When you buy from Voltiva, we do this for you – but it helps to know what is involved.

            ## The documents

            - **Certificate of Conformity (CoC).** Issued by the manufacturer, it confirms the car matches its **EU type-approved** design. Without it, a car cannot be registered.
            - **VIN.** The Vehicle Identification Number is a unique 17-character code stamped on the car. It links the car to its paperwork.
            - **Your details.** ID (DNI, NIE or passport) and proof of address.

            ## The steps

            1. We check the car's CoC and VIN.
            2. We apply for registration and obtain the registration certificate and technical sheet.
            3. We receive the number and fit the plates.
            4. We help you obtain the DGT “0 emissions” environmental label.

            ## After registration

            You will need insurance before driving – we can help arrange it. Road tax is paid to your local council, and many offer reductions for electric vehicles. Like all vehicles, your car will need periodic ITV inspections.

            **The short version:** hand us your ID, and we give you a car that is ready to drive.
            MARKDOWN,
        ],
        [
            'slug' => 'electric-car-batteries-explained',
            'title' => 'Electric car batteries explained',
            'topic' => 'batteries',
            'vehicle' => 'voltiva-terra',
            'image_path' => 'site/batteries.webp',
            'days_ago' => 30,
            'excerpt' => 'Volts, amp-hours, kilowatt-hours and LiFePO4 – what the numbers on a battery mean for your range.',
            'body' => <<<'MARKDOWN'
            Battery specifications can look like alphabet soup. Here is what they actually mean – and which number matters most.

            ## An example

            The Voltiva Terra has a **72V 150Ah LiFePO4** battery with **10.8 kWh** of energy.

            ## Volts (V)

            The battery's voltage. Higher-voltage systems can deliver power more efficiently, but on its own voltage does not tell you much about range.

            ## Amp-hours (Ah)

            How much charge the battery holds at that voltage. At the same voltage, a battery with more Ah stores more energy – and gives more range.

            ## Kilowatt-hours (kWh)

            **The number to look at.** Multiply volts by amp-hours and divide by 1,000: 72 × 150 ÷ 1,000 = 10.8 kWh. That is the total energy in the battery, and it is the best single figure for comparing range between cars. It is also how your electricity is billed, so it tells you what a full charge costs.

            ## LiFePO4

            The battery chemistry: lithium iron phosphate. Compared with other lithium batteries it is more tolerant of heat, very stable and very long-lasting – which is why we chose it for Mallorca.

            ## So which car goes furthest?

            Compare the kWh figures – or use our **Compare Cars** page, which puts every car's battery and range side by side.
            MARKDOWN,
        ],
        [
            'slug' => 'servicing-an-electric-car',
            'title' => 'Servicing an electric car: what to expect',
            'topic' => 'servicing',
            'vehicle' => null,
            'image_path' => 'site/servicing.webp',
            'days_ago' => 38,
            'excerpt' => 'Fewer moving parts means less to go wrong. Here is what a service on a small electric car involves.',
            'body' => <<<'MARKDOWN'
            A petrol engine has hundreds of moving parts. An electric motor has very few. That is why electric cars are so much cheaper and simpler to maintain.

            ## What you no longer need

            No oil changes. No oil filter, air filter or spark plugs. No clutch, gearbox oil, exhaust or timing belt.

            ## What still needs checking

            - **Brakes.** Electric cars use regenerative braking, so the brake pads last longer – but they still need checking.
            - **Tyres.** Tread, pressure and alignment.
            - **Suspension, steering and lights.**
            - **Battery health and software.** We check the battery's condition and install any updates.

            ## How often?

            We recommend a check once a year. We can collect the car from your home anywhere in Mallorca and return it the same day.

            ## What does it cost?

            Much less than a petrol car's service. Ask us for a fixed price for your model.
            MARKDOWN,
        ],
    ];

    /**
     * Seed the pages, the articles and the photo credits page.
     */
    public function run(): void
    {
        foreach (self::PAGES as $attributes) {
            Page::firstOrCreate(
                ['slug' => $attributes['slug']],
                [
                    ...$attributes,
                    'is_published' => true,
                    // Linked from the header and footer groups instead.
                    'show_in_footer' => false,
                ],
            );
        }

        Page::firstOrCreate(
            ['slug' => 'photo-credits'],
            [
                'title' => 'Photo credits',
                'body' => $this->photoCredits(),
                'seo_description' => 'Credits for the photographs used on this website.',
                'is_published' => true,
                'show_in_footer' => true,
                'sort_order' => 900,
            ],
        );

        foreach (self::ARTICLES as $attributes) {
            $vehicleId = $attributes['vehicle'] === null
                ? null
                : Vehicle::query()->where('slug', $attributes['vehicle'])->value('id');

            Article::firstOrCreate(
                ['slug' => $attributes['slug']],
                [
                    'title' => $attributes['title'],
                    'topic' => $attributes['topic'],
                    'excerpt' => $attributes['excerpt'],
                    'body' => $attributes['body'],
                    'vehicle_id' => $vehicleId,
                    'image_path' => $attributes['image_path'],
                    'image_credit' => VoltivaSeeder::PHOTOS[$attributes['image_path']],
                    'is_published' => true,
                    'published_at' => now()->subDays($attributes['days_ago'])->setTime(9, 0),
                ],
            );
        }
    }

    /**
     * The Photo credits page body, built from the photograph list so a credit
     * cannot be forgotten. The Creative Commons licences require it.
     */
    private function photoCredits(): string
    {
        $lines = ['The photographs on this demonstration website are used under Creative Commons licences from Wikimedia Commons, and stand in for Voltiva\'s own photography.', ''];

        foreach (VoltivaSeeder::PHOTOS as $path => $credit) {
            $lines[] = '- **'.basename($path).'** – '.$credit;
        }

        return implode("\n", $lines);
    }
}
