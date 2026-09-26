<?php
declare(strict_types=1);

namespace Belis\Support;

use Belis\Core\Env;

/**
 * Site-wide content that is structure, not catalogue: navigation, trust points, audiences, contact
 * details and social links. Wording follows the owner's mockup (PG-033, PG-034). Promises and
 * contact details are mock until the owner confirms or supplies them (PG-037, PG-038, AS-08), so
 * contact details show only when configured, or as clearly labelled samples in mock mode.
 */
final class Site
{
    /** @return list<array{label:string,href:string}> Only pages that exist. */
    public static function nav(): array
    {
        return [
            ['label' => 'Home', 'href' => '/'],
            ['label' => 'Shop', 'href' => '/shop'],
            ['label' => 'Categories', 'href' => '/categories'],
            ['label' => 'For Businesses', 'href' => '/for-businesses'],
            ['label' => 'About', 'href' => '/about'],
            ['label' => 'Contact', 'href' => '/contact'],
        ];
    }

    /**
     * Trust row under the hero. "Secure payments" stays out until live payments are approved (PG-038).
     *
     * @return list<array{icon:string,title:string,line:string}>
     */
    public static function trust(): array
    {
        $items = [
            ['icon' => 'truck', 'title' => 'Fast delivery', 'line' => 'Across Ghana'],
            ['icon' => 'award', 'title' => 'Quality products', 'line' => 'For every space'],
            ['icon' => 'headset', 'title' => 'Trusted support', 'line' => 'Here to help'],
        ];
        if (Env::bool('PAYMENTS_LIVE_APPROVED')) {
            $items[] = ['icon' => 'shield-check', 'title' => 'Secure payments', 'line' => 'Pay with confidence'];
        }
        return $items;
    }

    /** @return list<array{icon:string,title:string,line:string}> */
    public static function why(): array
    {
        return [
            ['icon' => 'award', 'title' => 'Quality Brands', 'line' => 'Trusted and effective products.'],
            ['icon' => 'truck', 'title' => 'Fast Delivery', 'line' => 'Across Ghana.'],
            ['icon' => 'tag', 'title' => 'Great Value', 'line' => 'Competitive prices.'],
            ['icon' => 'headset', 'title' => 'Dedicated Support', 'line' => 'We are here to help.'],
        ];
    }

    /** @return list<array{slot:string,icon:string,title:string,line:string,alt:string,focus:string}> */
    public static function audiences(): array
    {
        return [
            ['slot' => 'home/audience-homes', 'icon' => 'house', 'title' => 'Homes',
                'line' => 'Keep your home clean, fresh and comfortable with trusted products.', 'alt' => 'A clean, comfortable home', 'focus' => 'focus-mid'],
            ['slot' => 'home/audience-businesses', 'icon' => 'building', 'title' => 'Businesses',
                'line' => 'Reliable supplies for offices, shops, hotels, restaurants and more.', 'alt' => 'A bright, modern office', 'focus' => 'focus-mid'],
            ['slot' => 'home/audience-institutions', 'icon' => 'users', 'title' => 'Institutions',
                'line' => 'Trusted by schools, churches, hospitals and public facilities across Ghana.', 'alt' => 'A school building with a Ghana flag', 'focus' => 'focus-top'],
        ];
    }

    /**
     * Contact details. Values come from the environment. In mock mode only, clearly labelled sample
     * values fill any that are missing. Anything not set is null and the page hides it.
     *
     * @return array{address:?string,phone:?string,email:?string,hours:?string,sample:bool}
     */
    public static function contact(): array
    {
        $mock = Env::bool('MOCK_DATA');
        $pick = static fn (string $key, string $sample): ?string => (($v = trim(Env::get($key, '') ?? '')) !== '')
            ? $v
            : ($mock ? $sample : null);
        $address = $pick('CONTACT_ADDRESS', 'Accra, Ghana');
        $phone = $pick('CONTACT_PHONE', '+233 24 123 4567');
        $email = $pick('CONTACT_EMAIL', 'info@example.test');
        $hours = $pick('CONTACT_HOURS', 'Mon to Sat, 8:00 AM to 6:00 PM');
        $sample = $mock && (trim(Env::get('CONTACT_PHONE', '') ?? '') === '' || trim(Env::get('CONTACT_EMAIL', '') ?? '') === '');
        return ['address' => $address, 'phone' => $phone, 'email' => $email, 'hours' => $hours, 'sample' => $sample];
    }

    /** Digits only, for tel: links. */
    public static function phoneHref(string $phone): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', $phone) ?? '';
        return preg_match('/^\+?\d{7,15}$/', $digits) === 1 ? 'tel:' . $digits : null;
    }

    /**
     * Social links appear only when a real https address is set (PG-037).
     *
     * @return list<array{label:string,url:string}>
     */
    public static function socials(): array
    {
        $out = [];
        foreach (['Facebook' => 'SOCIAL_FACEBOOK', 'Instagram' => 'SOCIAL_INSTAGRAM', 'LinkedIn' => 'SOCIAL_LINKEDIN', 'TikTok' => 'SOCIAL_TIKTOK'] as $label => $key) {
            $url = trim(Env::get($key, '') ?? '');
            if (str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false) {
                $out[] = ['label' => $label, 'url' => $url];
            }
        }
        return $out;
    }

    /**
     * Click-to-chat link only (DEC-008): no WhatsApp API and no data sent by us. The number comes
     * from WHATSAPP_NUMBER (digits, international format). Without it, no link is shown.
     */
    public static function whatsappLink(string $message): ?string
    {
        $number = preg_replace('/\D+/', '', Env::get('WHATSAPP_NUMBER', '') ?? '') ?? '';
        if ($number === '' || strlen($number) < 8 || strlen($number) > 15) {
            return null;
        }
        return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
    }
}
